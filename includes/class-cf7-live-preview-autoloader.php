<?php
/**
 * Autoloader for CF7 Live Preview plugin
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Autoloader class
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Autoloader {

	/**
	 * Register autoloader
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes
	 *
	 * @param string $class_name Class name to load.
	 */
	public static function autoload( $class_name ) {
		/**
		 * Only load our classes
		 */
		if ( strpos( $class_name, 'CF7_Live_Preview' ) !== 0 ) {
			return;
		}

		/**
		 * Convert class name to file name.
		 *
		 * Maps CF7_Live_Preview_Admin to
		 * includes/class-cf7-live-preview-admin.php.
		 */
		$file_name = str_replace( 'CF7_Live_Preview_', '', $class_name );
		$file_name = str_replace( '_', '-', $file_name );
		$file_name = strtolower( $file_name );
		$file_path = CF7_LIVE_PREVIEW_PLUGIN_DIR . 'includes/class-cf7-live-preview-' . $file_name . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}
