<?php
/**
 * Validates a preview submission without submitting it
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Validator class
 *
 * Runs the validation part of a CF7 submission against an unsaved form
 * built from the editor, and nothing else.
 *
 * A real submission (WPCF7_Submission::get_instance()) runs everything in
 * one go: validation, acceptance, spam checks, wpcf7_before_send_mail, mail
 * and wpcf7_mail_sent — add-ons store messages and call webhooks from those
 * last hooks, even with skip_mail. So a submission object is created here
 * through Reflection *without* that pipeline, and only its validation steps
 * are called: setup_meta_data(), setup_posted_data(), validate() and
 * accepted(). Add-ons hooked into validation still find a normal current
 * submission with posted data, as they expect.
 *
 * The answer mirrors CF7's feedback response, so CF7's own front-end
 * script renders it with its own markup and classes.
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Validator {

	/**
	 * Status returned when the input is valid
	 *
	 * Not "mail_sent": CF7's script would reset the form and fire
	 * wpcf7mailsent, which redirect and analytics add-ons act on.
	 *
	 * @var string
	 */
	const STATUS_VALID = 'cf7lp_valid';

	/**
	 * Private WPCF7_Submission methods called, in order
	 *
	 * @var string[]
	 */
	const SUBMISSION_STEPS = array( 'setup_meta_data', 'setup_posted_data', 'validate', 'accepted' );

	/**
	 * Whether the CF7 internals this validator relies on are available
	 *
	 * @return bool
	 */
	public static function is_supported() {
		if ( ! CF7_Live_Preview_Renderer::is_supported() || ! class_exists( 'WPCF7_Submission' ) ) {
			return false;
		}

		$class = new ReflectionClass( 'WPCF7_Submission' );

		foreach ( self::SUBMISSION_STEPS as $method ) {
			if ( ! $class->hasMethod( $method ) ) {
				return false;
			}
		}

		return $class->hasProperty( 'instance' )
			&& $class->hasMethod( 'get_invalid_fields' )
			&& null !== $class->getConstructor()
			&& method_exists( 'WPCF7_ContactForm', 'message' );
	}

	/**
	 * Validate the posted fields against the form markup
	 *
	 * The fields themselves are read from $_POST and $_FILES by CF7, as in a
	 * real submission.
	 *
	 * @param string $markup    Form template.
	 * @param array  $messages  Messages from the editor's Messages tab.
	 * @param string $unit_tag  Unit tag of the form in the preview.
	 * @param string $locale    Optional. Locale of the form.
	 * @return array CF7 feedback response.
	 */
	public function validate( $markup, $messages, $unit_tag, $locale = '' ) {
		$response = array(
			'contact_form_id'  => 0,
			'status'           => 'aborted',
			'message'          => '',
			'posted_data_hash' => '',
			'into'             => '#' . $unit_tag,
			'invalid_fields'   => array(),
		);

		if ( ! self::is_supported() ) {
			$response['message'] = __( 'This version of Contact Form 7 is not supported by CF7 Live Preview.', 'cf7-live-preview' );
			return $response;
		}

		ob_start();

		try {
			$properties = array( 'form' => $markup );

			if ( $messages ) {
				// Over the defaults, so a message missing from the editor keeps CF7's.
				$defaults               = WPCF7_ContactForm::get_template( array( 'locale' => '' !== $locale ? $locale : null ) )->prop( 'messages' );
				$properties['messages'] = array_merge( (array) $defaults, $messages );
			}

			$contact_form = CF7_Live_Preview_Renderer::create_form( $properties, $locale );

			list( $invalid, $accepted ) = $this->run_validation( $contact_form );

			if ( $invalid ) {
				$response['status']         = 'validation_failed';
				$response['message']        = $contact_form->message( 'validation_error' );
				$response['invalid_fields'] = $this->format_invalid_fields( $invalid, $unit_tag );
			} elseif ( ! $accepted ) {
				$response['status']  = 'acceptance_missing';
				$response['message'] = $contact_form->message( 'accept_terms' );
			} else {
				$response['status']  = self::STATUS_VALID;
				$response['message'] = $contact_form->message( 'mail_sent_ok' );
			}
		} catch ( Throwable $e ) {
			$response['status']  = 'aborted';
			$response['message'] = sprintf(
				/* translators: %s: error message */
				__( 'The preview could not validate the form: %s', 'cf7-live-preview' ),
				$e->getMessage()
			);
		} finally {
			ob_end_clean();
		}

		return $response;
	}

	/**
	 * Run a submission's validation steps, and only those
	 *
	 * @param WPCF7_ContactForm $contact_form Form.
	 * @return array{0: array, 1: bool} Invalid fields keyed by name, and
	 *                                  whether acceptance was given.
	 */
	private function run_validation( $contact_form ) {
		$class    = new ReflectionClass( 'WPCF7_Submission' );
		$instance = $class->getProperty( 'instance' );
		$instance->setAccessible( true );
		$previous = $instance->getValue();

		// Through the constructor, inside the class, so it can set its
		// readonly $contact_form; get_instance() would also run proceed().
		$submission  = $class->newInstanceWithoutConstructor();
		$constructor = $class->getConstructor();
		$constructor->setAccessible( true );
		$constructor->invoke( $submission, $contact_form, array( 'skip_mail' => true ) );

		$results = array();

		try {
			// Current, so add-ons calling WPCF7_Submission::get_instance() find it.
			$instance->setValue( null, $submission );

			foreach ( self::SUBMISSION_STEPS as $step ) {
				$method = $class->getMethod( $step );
				$method->setAccessible( true );

				$results[ $step ] = $method->invoke( $submission );
			}
		} finally {
			$instance->setValue( null, $previous );
		}

		return array(
			(array) $submission->get_invalid_fields(),
			(bool) $results['accepted'],
		);
	}

	/**
	 * Invalid fields in the shape CF7's feedback endpoint returns them
	 *
	 * @param array  $invalid  Invalid fields from WPCF7_Validation.
	 * @param string $unit_tag Unit tag.
	 * @return array[]
	 */
	private function format_invalid_fields( $invalid, $unit_tag ) {
		$fields = array();

		foreach ( $invalid as $name => $field ) {
			if ( function_exists( 'wpcf7_is_name' ) && ! wpcf7_is_name( $name ) ) {
				continue;
			}

			$name = strtr( $name, '.', '_' );

			$fields[] = array(
				'field'    => $name,
				'message'  => $field['reason'],
				'idref'    => $field['idref'],
				'error_id' => sprintf( '%1$s-ve-%2$s', $unit_tag, $name ),
			);
		}

		return $fields;
	}
}
