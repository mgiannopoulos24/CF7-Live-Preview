<?php
/**
 * Renders unsaved form markup with Contact Form 7
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Renderer class
 *
 * Builds a throwaway WPCF7_ContactForm from the editor's markup and returns
 * its front-end HTML. The form is never saved, so it has no post ID and
 * writes nothing to the database.
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Renderer {

	/**
	 * Whether the Contact Form 7 API this renderer relies on is available
	 *
	 * Checked rather than assumed: these are CF7 internals, not a documented
	 * extension API, and they may move between releases.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return class_exists( 'WPCF7_ContactForm' )
			&& is_callable( array( 'WPCF7_ContactForm', 'get_template' ) )
			&& method_exists( 'WPCF7_ContactForm', 'set_properties' )
			&& method_exists( 'WPCF7_ContactForm', 'form_html' );
	}

	/**
	 * Build an unsaved contact form with the given properties
	 *
	 * Loading a saved form runs its properties through
	 * wpcf7_contact_form_property_{name} and wpcf7_contact_form_properties;
	 * add-ons rewrite the markup there (Conditional Fields turns [group]
	 * into HTML, for one). set_properties() skips both, so they are run here
	 * for the properties that were set — the rest were already filtered when
	 * the template was built.
	 *
	 * @param array  $properties Properties to set, such as 'form' and 'messages'.
	 * @param string $locale     Optional. Locale of the form.
	 * @param string $title      Optional. Title of the form.
	 * @return WPCF7_ContactForm
	 */
	public static function create_form( $properties, $locale = '', $title = '' ) {
		$options = array();

		if ( '' !== $locale ) {
			$options['locale'] = $locale;
		}

		if ( '' !== $title ) {
			$options['title'] = $title;
		}

		$contact_form = WPCF7_ContactForm::get_template( $options );
		$contact_form->set_properties( $properties );

		$all = $contact_form->get_properties();

		foreach ( array_keys( $properties ) as $name ) {
			if ( array_key_exists( $name, $all ) ) {
				$all[ $name ] = apply_filters( "wpcf7_contact_form_property_{$name}", $all[ $name ], $contact_form );
			}
		}

		$all = (array) apply_filters( 'wpcf7_contact_form_properties', $all, $contact_form );

		$contact_form->set_properties( array_intersect_key( $all, $properties ) );

		return $contact_form;
	}

	/**
	 * Render form markup to HTML
	 *
	 * @param string $markup Form template, as typed into the CF7 editor.
	 * @param string $locale Optional. Locale of the form being edited.
	 * @param array  $addons Optional. Add-on context; see CF7_Live_Preview_Compat.
	 * @return array {
	 *     @type string $html  Rendered form, or error markup for the panel.
	 *     @type bool   $error Whether rendering failed.
	 * }
	 */
	public function render( $markup, $locale = '', $addons = array() ) {
		if ( ! self::is_supported() ) {
			return $this->error(
				__( 'This version of Contact Form 7 is not supported by CF7 Live Preview.', 'cf7-live-preview' )
			);
		}

		// Catches stray output from add-ons hooked into rendering.
		ob_start();

		try {
			$html = CF7_Live_Preview_Compat::with_context(
				$addons,
				static function () use ( $markup, $locale ) {
					return self::create_form( array( 'form' => $markup ), $locale )->form_html();
				}
			);
		} catch ( Throwable $e ) {
			ob_end_clean();

			return $this->error(
				sprintf(
					/* translators: %s: error message */
					__( 'The form could not be rendered: %s', 'cf7-live-preview' ),
					$e->getMessage()
				)
			);
		}

		ob_end_clean();

		return array(
			'html'  => $html,
			'error' => false,
		);
	}

	/**
	 * Build the error result shown in the preview panel
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	private function error( $message ) {
		return array(
			'html'  => sprintf(
				'<div class="cf7lp-error" role="alert"><p>%s</p></div>',
				esc_html( $message )
			),
			'error' => true,
		);
	}
}
