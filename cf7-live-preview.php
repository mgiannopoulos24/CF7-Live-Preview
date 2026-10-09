<?php
/**
 * Plugin Name: CF7 Live Preview
 * Description: Preview a Contact Form 7 form and its email from the editor, with your theme's styles and without saving.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP:      8.3
 * Requires Plugins: contact-form-7
 * Author: Marios Giannopoulos
 * Author URI: https://github.com/mgiannopoulos24
 * License: GNU General Public License v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: cf7-live-preview
 * Domain Path: /languages
 *
 * @package CF7_Live_Preview
 */

/**
 * Exit if accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants
 */
define( 'CF7_LIVE_PREVIEW_VERSION', '1.0.0' );
define( 'CF7_LIVE_PREVIEW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF7_LIVE_PREVIEW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CF7_LIVE_PREVIEW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader
 */
require_once CF7_LIVE_PREVIEW_PLUGIN_DIR . 'includes/class-cf7-live-preview-autoloader.php';
CF7_Live_Preview_Autoloader::register();

/**
 * Initialize the plugin
 */
require_once CF7_LIVE_PREVIEW_PLUGIN_DIR . 'includes/class-cf7-live-preview.php';

/**
 * Get the main plugin instance
 *
 * @return CF7_Live_Preview
 */
function cf7_live_preview() {
	return CF7_Live_Preview::get_instance();
}

/**
 * Initialize the plugin
 */
cf7_live_preview();
