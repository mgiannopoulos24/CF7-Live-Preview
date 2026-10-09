<?php
/**
 * Main plugin class
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview class
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview {

	/**
	 * Plugin instance
	 *
	 * @var CF7_Live_Preview
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return CF7_Live_Preview
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Register the plugin components once every plugin has loaded
	 *
	 * Contact Form 7 loads after this plugin (active plugins load in
	 * alphabetical order), so its constants only exist from here on. Nothing
	 * beyond the dependency notice is registered while it is unavailable.
	 */
	public function boot() {
		if ( ! defined( 'WPCF7_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_cf7_notice' ) );
			return;
		}

		( new CF7_Live_Preview_REST() )->register();
		( new CF7_Live_Preview_Preview_Frame() )->register();

		if ( is_admin() ) {
			( new CF7_Live_Preview_Admin() )->register();
			( new CF7_Live_Preview_Settings() )->register();
		}
	}

	/**
	 * Load the plugin translations
	 *
	 * Loaded explicitly because the plugin supports WordPress 6.5, which
	 * predates just-in-time text domain loading.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'cf7-live-preview',
			false,
			dirname( CF7_LIVE_PREVIEW_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Warn administrators that the plugin cannot run without Contact Form 7
	 */
	public function render_missing_cf7_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'CF7 Live Preview requires Contact Form 7 to be installed and activated.',
				'cf7-live-preview'
			)
		);
	}
}
