<?php
/**
 * Front-end shell the preview iframe loads
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Preview_Frame class
 *
 * Serves home_url( '/?cf7lp_preview=1&_wpnonce=…' ) as a bare page that
 * prints the theme's head and footer, so the form is styled exactly as on
 * the front end. The admin screen sends rendered form HTML into it.
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Preview_Frame {

	/**
	 * Query argument that marks a preview frame request
	 *
	 * @var string
	 */
	const QUERY_ARG = 'cf7lp_preview';

	/**
	 * Nonce action for the frame URL
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'cf7lp_preview';

	/**
	 * Hidden fields frame.js adds to the previewed form, so a submission
	 * carries what is needed to rebuild it. Underscored, like CF7's own
	 * hidden fields, so CF7 never treats them as user input.
	 *
	 * @var string
	 */
	const FORM_FIELD     = '_cf7lp_form';
	const LOCALE_FIELD   = '_cf7lp_locale';
	const MESSAGES_FIELD = '_cf7lp_messages';

	/**
	 * Hook the frame handler
	 */
	public function register() {
		add_filter( 'show_admin_bar', array( $this, 'filter_admin_bar' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 1 );
	}

	/**
	 * URL of the preview frame for the current user
	 *
	 * @return string
	 */
	public static function url() {
		return add_query_arg(
			array(
				self::QUERY_ARG => '1',
				'_wpnonce'      => wp_create_nonce( self::NONCE_ACTION ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Whether the current request asks for the preview frame
	 *
	 * Says nothing about whether the request is allowed; see maybe_render().
	 *
	 * @return bool
	 */
	public static function is_frame_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only; the nonce is verified in maybe_render().
		return isset( $_GET[ self::QUERY_ARG ] );
	}

	/**
	 * Hide the admin bar inside the frame
	 *
	 * Filtered rather than toggled in maybe_render() because WordPress
	 * initialises the admin bar on template_redirect at priority 0.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function filter_admin_bar( $show ) {
		return self::is_frame_request() ? false : $show;
	}

	/**
	 * Serve the frame shell and stop, if this is a frame request
	 */
	public function maybe_render() {
		if ( ! self::is_frame_request() ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if (
			! wp_verify_nonce( $nonce, self::NONCE_ACTION )
			|| ! current_user_can( 'wpcf7_edit_contact_forms' )
		) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to preview contact forms.', 'cf7-live-preview' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		// Only the admin, on this site, may frame it.
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( "Content-Security-Policy: frame-ancestors 'self'" );
		// The URL carries a nonce; don't send it to theme assets on other hosts.
		header( 'Referrer-Policy: same-origin' );

		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

		require CF7_LIVE_PREVIEW_PLUGIN_DIR . 'templates/preview-frame.php';
		exit;
	}

	/**
	 * Enqueue CF7's front-end assets and the frame's own
	 *
	 * CF7 normally loads its assets everywhere, but sites commonly switch
	 * that off (WPCF7_LOAD_JS / WPCF7_LOAD_CSS) and load them only on pages
	 * with a form, so they are forced on here.
	 */
	public function enqueue_assets() {
		if ( function_exists( 'wpcf7_enqueue_scripts' ) && ! wp_script_is( 'contact-form-7' ) ) {
			wpcf7_enqueue_scripts();
		}

		if ( function_exists( 'wpcf7_enqueue_styles' ) && ! wp_style_is( 'contact-form-7' ) ) {
			wpcf7_enqueue_styles();
		}

		/**
		 * Fires when the preview frame enqueues its assets
		 *
		 * The frame is a page of its own, so CSS a theme loads only on its
		 * contact page (is_page( 'contact' ) and the like) is missing there.
		 * Enqueue it on this hook to style the preview the same way.
		 */
		do_action( 'cf7lp_enqueue_preview_assets' );

		wp_enqueue_style(
			'cf7-live-preview-frame',
			CF7_LIVE_PREVIEW_PLUGIN_URL . 'assets/css/frame.css',
			array(),
			CF7_LIVE_PREVIEW_VERSION
		);

		$background = CF7_Live_Preview_Settings::get()['background_color'];

		if ( '' !== $background ) {
			// Sanitized to a hex colour on save and again on read.
			wp_add_inline_style(
				'cf7-live-preview-frame',
				sprintf( 'body.cf7lp-preview { background: %s; }', $background )
			);
		}

		wp_enqueue_script(
			'cf7-live-preview-frame',
			CF7_LIVE_PREVIEW_PLUGIN_URL . 'assets/js/frame.js',
			wp_script_is( 'contact-form-7', 'registered' ) ? array( 'contact-form-7' ) : array(),
			CF7_LIVE_PREVIEW_VERSION,
			array( 'in_footer' => true )
		);

		$validate = CF7_Live_Preview_Validator::is_supported() && wp_script_is( 'contact-form-7' );

		wp_add_inline_script(
			'cf7-live-preview-frame',
			sprintf(
				'var cf7lpFrame = %s;',
				wp_json_encode(
					array(
						'validate' => $validate,
						'fields'   => array(
							'form'     => self::FORM_FIELD,
							'locale'   => self::LOCALE_FIELD,
							'messages' => self::MESSAGES_FIELD,
						),
					)
				)
			),
			'before'
		);

		if ( $validate ) {
			// CF7's script posts submissions to {root}{namespace}/contact-forms/
			// {id}/feedback and drops any X-WP-Nonce header, so the REST nonce
			// rides in the query string, with rest_route last so CF7 can
			// append its path to it.
			$api_root = home_url( '/' ) . '?_wpnonce=' . rawurlencode( wp_create_nonce( 'wp_rest' ) ) . '&rest_route=/';

			wp_add_inline_script(
				'contact-form-7',
				sprintf(
					'wpcf7.api = %s;',
					wp_json_encode(
						array(
							'root'      => $api_root,
							'namespace' => CF7_Live_Preview_REST::ROUTE_NAMESPACE . '/preview',
						),
						JSON_UNESCAPED_SLASHES
					)
				),
				'after'
			);
		}
	}
}
