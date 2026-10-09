<?php
/**
 * REST API routes
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_REST class
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_REST {

	/**
	 * Route namespace
	 *
	 * @var string
	 */
	const ROUTE_NAMESPACE = 'cf7lp/v1';

	/**
	 * Hook the route registration
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'wpcf7_skip_mail', array( $this, 'skip_mail_for_preview' ) );
	}

	/**
	 * Never send mail for a request that comes from the preview
	 *
	 * The preview frame points CF7 at the /preview route, which never
	 * submits. This is a backstop for a preview form that reaches CF7's own
	 * endpoint anyway, for example if an add-on resets wpcf7.api.
	 *
	 * @param bool $skip Whether to skip sending mail.
	 * @return bool
	 */
	public function skip_mail_for_preview( $skip ) {
		if ( $skip ) {
			return $skip;
		}

		// Previews come only from users who can edit forms; anyone else
		// sending the field is not previewing, and mail goes out as usual.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only; it can only turn sending off.
		return isset( $_POST[ CF7_Live_Preview_Preview_Frame::FORM_FIELD ] )
			&& current_user_can( 'wpcf7_edit_contact_forms' );
	}

	/**
	 * Register the routes
	 */
	public function register_routes() {
		$this->register_preview_feedback_route();

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/render',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'render' ),
				'permission_callback' => array( $this, 'can_edit_forms' ),
				'args'                => array(
					'form'   => array(
						'description' => __( 'Form template markup.', 'cf7-live-preview' ),
						'type'        => 'string',
						'required'    => true,
					),
					'locale' => array(
						'description' => __( 'Locale of the form being edited.', 'cf7-live-preview' ),
						'type'        => 'string',
						'default'     => '',
						'pattern'     => '^([a-z]{2,3}(_[a-zA-Z_]{2,})?)?$',
					),
					'addons' => array(
						'description'          => __( 'Unsaved settings of CF7 add-ons that the form depends on.', 'cf7-live-preview' ),
						'type'                 => 'object',
						'default'              => array(),
						'properties'           => CF7_Live_Preview_Compat::context_schema(),
						'additionalProperties' => false,
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/mail',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mail' ),
				'permission_callback' => array( $this, 'can_edit_forms' ),
				'args'                => array(
					'form'    => array(
						'description' => __( 'Form template markup.', 'cf7-live-preview' ),
						'type'        => 'string',
						'required'    => true,
					),
					'mail'    => array(
						'description'          => __( 'Mail template, as in the Mail tab.', 'cf7-live-preview' ),
						'type'                 => 'object',
						'required'             => true,
						'properties'           => array(
							'recipient'          => array( 'type' => 'string' ),
							'sender'             => array( 'type' => 'string' ),
							'subject'            => array( 'type' => 'string' ),
							'additional_headers' => array( 'type' => 'string' ),
							'body'               => array( 'type' => 'string' ),
							'attachments'        => array( 'type' => 'string' ),
							'use_html'           => array( 'type' => 'boolean' ),
							'exclude_blank'      => array( 'type' => 'boolean' ),
						),
						'additionalProperties' => false,
					),
					'samples' => array(
						'description'          => __( 'Sample values by field name.', 'cf7-live-preview' ),
						'type'                 => 'object',
						'default'              => array(),
						'additionalProperties' => array( 'type' => 'string' ),
					),
					'title'   => array(
						'description' => __( 'Title of the form being edited.', 'cf7-live-preview' ),
						'type'        => 'string',
						'default'     => '',
					),
					'locale'  => array(
						'description' => __( 'Locale of the form being edited.', 'cf7-live-preview' ),
						'type'        => 'string',
						'default'     => '',
						'pattern'     => '^([a-z]{2,3}(_[a-zA-Z_]{2,})?)?$',
					),
				),
			)
		);
	}

	/**
	 * Register the preview submission route
	 *
	 * Shaped like CF7's own contact-forms/{id}/feedback, under a namespace
	 * the preview frame swaps in for CF7's, so CF7's front-end script posts
	 * here and renders the answer itself.
	 */
	private function register_preview_feedback_route() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			// Any id: an unsaved form has none, and CF7's script then asks for
			// contact-forms/NaN/feedback. The form is rebuilt from the posted
			// template, not looked up.
			'/preview/contact-forms/(?P<id>[^/]+)/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_feedback' ),
				'permission_callback' => array( $this, 'can_edit_forms' ),
			)
		);
	}

	/**
	 * Validate a submission from the preview frame
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_feedback( WP_REST_Request $request ) {
		$markup = $request->get_param( CF7_Live_Preview_Preview_Frame::FORM_FIELD );

		if ( ! is_string( $markup ) ) {
			return new WP_Error(
				'cf7lp_missing_form',
				__( 'The preview did not send its form template.', 'cf7-live-preview' ),
				array( 'status' => 400 )
			);
		}

		$unit_tag = function_exists( 'wpcf7_sanitize_unit_tag' )
			? wpcf7_sanitize_unit_tag( (string) $request->get_param( '_wpcf7_unit_tag' ) )
			: '';

		if ( '' === $unit_tag ) {
			return new WP_Error(
				'cf7lp_unit_tag_not_found',
				__( 'There is no valid unit tag.', 'cf7-live-preview' ),
				array( 'status' => 400 )
			);
		}

		$locale = (string) $request->get_param( CF7_Live_Preview_Preview_Frame::LOCALE_FIELD );

		if ( ! preg_match( '/^([a-z]{2,3}(_[a-zA-Z_]{2,})?)?$/', $locale ) ) {
			$locale = '';
		}

		$messages = array();

		foreach ( (array) $request->get_param( CF7_Live_Preview_Preview_Frame::MESSAGES_FIELD ) as $key => $message ) {
			if ( is_string( $key ) && is_scalar( $message ) && preg_match( '/^[a-z0-9_]+$/', $key ) ) {
				$messages[ $key ] = (string) $message;
			}
		}

		$validator = new CF7_Live_Preview_Validator();

		return rest_ensure_response(
			$validator->validate( $markup, $messages, $unit_tag, $locale )
		);
	}

	/**
	 * Permission check: only users who can edit contact forms may preview them
	 *
	 * @return bool
	 */
	public function can_edit_forms() {
		return current_user_can( 'wpcf7_edit_contact_forms' );
	}

	/**
	 * Render the posted markup
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function render( WP_REST_Request $request ) {
		$renderer = new CF7_Live_Preview_Renderer();

		return rest_ensure_response(
			$renderer->render( $request['form'], $request['locale'], $request['addons'] )
		);
	}

	/**
	 * Compose the posted mail template with sample data
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function mail( WP_REST_Request $request ) {
		$renderer = new CF7_Live_Preview_Mail_Renderer();

		return rest_ensure_response(
			$renderer->render(
				$request['form'],
				$request['mail'],
				$request['samples'],
				array(
					'title'  => $request['title'],
					'locale' => $request['locale'],
				)
			)
		);
	}
}
