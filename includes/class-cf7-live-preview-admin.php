<?php
/**
 * Admin integration with the Contact Form 7 editor
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Admin class
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Admin {

	/**
	 * Hook the admin integration
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_unsupported_notice' ) );
	}

	/**
	 * Whether the current screen is the CF7 form editor
	 *
	 * Matched on the page slug, not the hook suffix: CF7 6.2 moved its
	 * screens under a new top-level menu, and the suffix is built from the
	 * translated menu title, so it differs per site language. The editor is
	 * page=wpcf7-new, or page=wpcf7 once CF7 has loaded a form to edit (the
	 * same slug without one is the form list).
	 *
	 * @return bool
	 */
	public static function is_editor_screen() {
		global $plugin_page;

		if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
			return false;
		}

		if ( 'wpcf7-new' === $plugin_page ) {
			return true;
		}

		return 'wpcf7' === $plugin_page
			&& function_exists( 'wpcf7_get_current_contact_form' )
			&& null !== wpcf7_get_current_contact_form();
	}

	/**
	 * Enqueue the preview modals on the CF7 editor only
	 */
	public function enqueue_assets() {
		if ( ! self::is_editor_screen() || ! CF7_Live_Preview_Renderer::is_supported() ) {
			return;
		}

		wp_enqueue_style(
			'cf7-live-preview-admin',
			CF7_LIVE_PREVIEW_PLUGIN_URL . 'assets/css/admin-preview.css',
			array(),
			CF7_LIVE_PREVIEW_VERSION
		);

		wp_enqueue_script(
			'cf7-live-preview-admin',
			CF7_LIVE_PREVIEW_PLUGIN_URL . 'assets/js/admin-preview.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			CF7_LIVE_PREVIEW_VERSION,
			array( 'in_footer' => true )
		);

		wp_set_script_translations(
			'cf7-live-preview-admin',
			'cf7-live-preview',
			CF7_LIVE_PREVIEW_PLUGIN_DIR . 'languages'
		);

		wp_add_inline_script(
			'cf7-live-preview-admin',
			sprintf(
				'var cf7lpAdmin = %s;',
				wp_json_encode(
					array(
						'renderPath'   => '/' . CF7_Live_Preview_REST::ROUTE_NAMESPACE . '/render',
						// Without it, "Preview email" is left out.
						'mailPath'     => CF7_Live_Preview_Mail_Renderer::is_supported()
							? '/' . CF7_Live_Preview_REST::ROUTE_NAMESPACE . '/mail'
							: '',
						'frameUrl'     => CF7_Live_Preview_Preview_Frame::url(),
						'defaultWidth' => CF7_Live_Preview_Settings::get()['default_width'],
					)
				)
			),
			'before'
		);
	}

	/**
	 * Explain on the editor why the preview is missing when CF7's API moved
	 */
	public function render_unsupported_notice() {
		if ( ! self::is_editor_screen() || CF7_Live_Preview_Renderer::is_supported() ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'CF7 Live Preview does not support the installed version of Contact Form 7, so the live preview is disabled.',
				'cf7-live-preview'
			)
		);
	}
}
