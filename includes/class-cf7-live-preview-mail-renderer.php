<?php
/**
 * Composes an unsaved form's email with sample data, without sending it
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Mail_Renderer class
 *
 * CF7 composes mail only while handling a real submission, and its composer
 * (WPCF7_Mail::compose()) is private. It is called here through Reflection
 * with $send = false, which returns the finished components before the
 * wp_mail() call — so nothing can be sent by construction, unlike
 * intercepting wp_mail(), which SMTP plugins often replace.
 *
 * With no submission, mail-tags have no values; sample values are supplied
 * through CF7's own replacement filters.
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Mail_Renderer {

	/**
	 * Mail template keys accepted from the editor
	 *
	 * @var string[]
	 */
	const TEMPLATE_KEYS = array( 'recipient', 'sender', 'subject', 'additional_headers', 'body', 'attachments' );

	/**
	 * Sample values for this render, by field name
	 *
	 * @var array<string, string>
	 */
	private $samples = array();

	/**
	 * Title of the form being edited, for [_contact_form_title]
	 *
	 * @var string
	 */
	private $title = '';

	/**
	 * Whether the CF7 internals this renderer relies on are available
	 *
	 * @return bool
	 */
	public static function is_supported() {
		if ( ! CF7_Live_Preview_Renderer::is_supported() || ! class_exists( 'WPCF7_Mail' ) ) {
			return false;
		}

		$class = new ReflectionClass( 'WPCF7_Mail' );

		return $class->hasMethod( 'compose' )
			&& $class->hasProperty( 'current' )
			&& null !== $class->getConstructor()
			&& 2 === $class->getConstructor()->getNumberOfParameters()
			&& method_exists( 'WPCF7_ContactForm', 'scan_form_tags' );
	}

	/**
	 * Compose the email
	 *
	 * @param string $markup   Form template, for the fields and their samples.
	 * @param array  $template Mail template: recipient, sender, subject,
	 *                         additional_headers, body, attachments,
	 *                         use_html, exclude_blank.
	 * @param array  $samples  Optional. Sample values by field name,
	 *                         overriding the generated ones.
	 * @param array  $context  Optional. 'locale' and 'title' of the form.
	 * @return array {
	 *     @type array  $fields Fields with a mail-tag: name, type, value.
	 *     @type array  $mail   recipient, sender, subject, additional_headers,
	 *                          body, use_html, attachments (file names).
	 *     @type string $error  Error message, when composing failed.
	 * }
	 */
	public function render( $markup, $template, $samples = array(), $context = array() ) {
		if ( ! self::is_supported() ) {
			return array(
				'error' => __( 'This version of Contact Form 7 is not supported by CF7 Live Preview.', 'cf7-live-preview' ),
			);
		}

		$this->title = isset( $context['title'] ) ? (string) $context['title'] : '';

		$template = $this->normalize_template( $template );

		ob_start();

		try {
			$contact_form = CF7_Live_Preview_Renderer::create_form(
				array( 'form' => $markup ),
				isset( $context['locale'] ) ? (string) $context['locale'] : '',
				$this->title
			);

			$fields        = $this->fields( $contact_form, $samples );
			$this->samples = wp_list_pluck( $fields, 'value', 'name' );

			$components = $this->compose( $template );
		} catch ( Throwable $e ) {
			return array(
				'error' => sprintf(
					/* translators: %s: error message */
					__( 'The email could not be composed: %s', 'cf7-live-preview' ),
					$e->getMessage()
				),
			);
		} finally {
			ob_end_clean();
			$this->samples = array();
		}

		return array(
			'fields' => $fields,
			'mail'   => array(
				'recipient'          => (string) $components['recipient'],
				'sender'             => (string) $components['sender'],
				'subject'            => (string) $components['subject'],
				'additional_headers' => (string) $components['additional_headers'],
				'body'               => (string) $components['body'],
				'use_html'           => $template['use_html'],
				'attachments'        => array_values( array_map( 'wp_basename', (array) $components['attachments'] ) ),
			),
		);
	}

	/**
	 * Keep only known template keys, as strings and booleans
	 *
	 * @param array $template Raw template.
	 * @return array
	 */
	private function normalize_template( $template ) {
		$normalized = array();

		foreach ( self::TEMPLATE_KEYS as $key ) {
			$normalized[ $key ] = isset( $template[ $key ] ) ? (string) $template[ $key ] : '';
		}

		$normalized['use_html']      = ! empty( $template['use_html'] );
		$normalized['exclude_blank'] = ! empty( $template['exclude_blank'] );

		return $normalized;
	}

	/**
	 * The form's named fields with a sample value each
	 *
	 * @param WPCF7_ContactForm $contact_form Form.
	 * @param array             $overrides    Sample values from the editor.
	 * @return array[]
	 */
	private function fields( $contact_form, $overrides ) {
		$fields = array();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			$name = (string) $tag->name;

			if ( '' === $name || isset( $fields[ $name ] ) || 'submit' === $tag->basetype ) {
				continue;
			}

			$value = isset( $overrides[ $name ] ) && is_scalar( $overrides[ $name ] )
				? (string) $overrides[ $name ]
				: $this->sample_value( $tag );

			$fields[ $name ] = array(
				'name'  => $name,
				'type'  => (string) $tag->basetype,
				'value' => $value,
			);
		}

		return array_values( $fields );
	}

	/**
	 * A plausible value for a form-tag
	 *
	 * @param WPCF7_FormTag $tag Form-tag.
	 * @return string
	 */
	private function sample_value( $tag ) {
		$choice = $this->first_choice( $tag );

		switch ( $tag->basetype ) {
			case 'email':
				return 'jane.doe@example.com';
			case 'url':
				return 'https://example.com';
			case 'tel':
				return '+1 555 0100';
			case 'number':
			case 'range':
				return '42';
			case 'date':
				return wp_date( 'Y-m-d' );
			case 'textarea':
				return __( "Hello,\nThis is a sample message written for the preview.", 'cf7-live-preview' );
			case 'select':
			case 'radio':
			case 'checkbox':
				return null !== $choice ? $choice : __( 'Option 1', 'cf7-live-preview' );
			case 'acceptance':
				return '1';
			case 'file':
				return 'document.pdf';
		}

		$name = strtolower( (string) $tag->name );

		if ( str_contains( $name, 'name' ) ) {
			return 'Jane Doe';
		}

		if ( str_contains( $name, 'subject' ) ) {
			return __( 'A question about your services', 'cf7-live-preview' );
		}

		if ( str_contains( $name, 'phone' ) || str_contains( $name, 'tel' ) ) {
			return '+1 555 0100';
		}

		return __( 'Sample text', 'cf7-live-preview' );
	}

	/**
	 * The value a choice field mails for its first option
	 *
	 * With pipes ("Label|value"), the mail gets the part after the pipe.
	 *
	 * @param WPCF7_FormTag $tag Form-tag.
	 * @return string|null
	 */
	private function first_choice( $tag ) {
		if ( $tag->pipes instanceof WPCF7_Pipes && method_exists( $tag->pipes, 'collect_afters' ) ) {
			$afters = $tag->pipes->collect_afters();

			if ( ! empty( $afters ) ) {
				return (string) reset( $afters );
			}
		}

		return empty( $tag->values ) ? null : (string) reset( $tag->values );
	}

	/**
	 * Run CF7's composer with $send = false
	 *
	 * @param array $template Normalized mail template.
	 * @return array Mail components.
	 */
	private function compose( $template ) {
		$class   = new ReflectionClass( 'WPCF7_Mail' );
		$current = $class->getProperty( 'current' );
		$current->setAccessible( true );
		$previous = $current->getValue();

		$mail = $class->newInstanceWithoutConstructor();
		$ctor = $class->getConstructor();
		$ctor->setAccessible( true );
		$ctor->invoke( $mail, 'mail', $template );

		$compose = $class->getMethod( 'compose' );
		$compose->setAccessible( true );

		add_filter( 'wpcf7_mail_tag_replaced', array( $this, 'filter_mail_tag' ), 5, 4 );
		add_filter( 'wpcf7_special_mail_tags', array( $this, 'filter_special_mail_tag' ), 20, 4 );
		// compose( false ) returns before wp_mail(); this is only a backstop
		// in case an add-on's filter tries to send while composing.
		add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MIN );

		try {
			// Current, so body-only behaviour (list separators) applies.
			$current->setValue( null, $mail );

			return (array) $compose->invoke( $mail, false );
		} finally {
			$current->setValue( null, $previous );

			remove_filter( 'wpcf7_mail_tag_replaced', array( $this, 'filter_mail_tag' ), 5 );
			remove_filter( 'wpcf7_special_mail_tags', array( $this, 'filter_special_mail_tag' ), 20 );
			remove_filter( 'pre_wp_mail', '__return_false', PHP_INT_MIN );
		}
	}

	/**
	 * Supply the sample value for a field's mail-tag
	 *
	 * @param string|null   $replaced  Replacement so far; null without a submission.
	 * @param mixed         $submitted Submitted value.
	 * @param bool          $html      Whether the output is HTML.
	 * @param WPCF7_MailTag $mail_tag  Mail-tag.
	 * @return string|null
	 */
	public function filter_mail_tag( $replaced, $submitted, $html, $mail_tag ) {
		if ( null !== $replaced || ! is_object( $mail_tag ) || ! method_exists( $mail_tag, 'field_name' ) ) {
			return $replaced;
		}

		$name = $mail_tag->field_name();

		if ( ! isset( $this->samples[ $name ] ) ) {
			return $replaced;
		}

		$value = $this->samples[ $name ];

		return $html ? wptexturize( esc_html( $value ) ) : $value;
	}

	/**
	 * Sample values for special mail-tags that need a submission
	 *
	 * Site tags such as [_site_title] work without one and are left to CF7.
	 *
	 * @param string|null $output Replacement so far.
	 * @param string      $name   Tag name.
	 * @param bool        $html   Whether the output is HTML.
	 * @return string|null
	 */
	public function filter_special_mail_tag( $output, $name, $html ) {
		if ( null !== $output ) {
			return $output;
		}

		$now = time();

		$values = array(
			'_remote_ip'          => '192.0.2.1',
			'_user_agent'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
			'_url'                => home_url( '/contact/' ),
			'_date'               => wp_date( get_option( 'date_format' ), $now ),
			'_time'               => wp_date( get_option( 'time_format' ), $now ),
			'_invalid_fields'     => '0',
			'_contact_form_title' => $this->title,
			'_post_id'            => '1',
			'_post_name'          => 'contact',
			'_post_title'         => __( 'Contact', 'cf7-live-preview' ),
			'_post_url'           => home_url( '/contact/' ),
			'_post_author'        => wp_get_current_user()->display_name,
			'_post_author_email'  => wp_get_current_user()->user_email,
		);

		$name = preg_replace( '/^wpcf7\./', '_', (string) $name );

		if ( ! isset( $values[ $name ] ) ) {
			return $output;
		}

		return $html ? esc_html( $values[ $name ] ) : $values[ $name ];
	}
}
