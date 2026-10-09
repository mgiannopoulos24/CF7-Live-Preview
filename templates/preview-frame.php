<?php
/**
 * Preview frame shell
 *
 * Loaded by CF7_Live_Preview_Preview_Frame::maybe_render() after the nonce
 * and capability checks. The form HTML is inserted into #cf7lp-root by
 * assets/js/frame.js.
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cf7lp_main_class = wp_is_block_theme()
	? 'cf7lp-frame wp-block-group is-layout-constrained has-global-padding'
	: 'cf7lp-frame';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'cf7lp-preview' ); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<main class="<?php echo esc_attr( $cf7lp_main_class ); ?>">
		<span class="cf7lp-badge"><?php esc_html_e( 'Preview', 'cf7-live-preview' ); ?></span>
		<div id="cf7lp-root" class="entry-content"></div>
	</main>
</div>
<?php wp_footer(); ?>
</body>
</html>
