<?php
/**
 * Plugin settings
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Settings class
 *
 * Settings > CF7 Live Preview: the preview's background colour and the
 * width it opens at. Stored in one option.
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Settings {

	/**
	 * Option name
	 *
	 * @var string
	 */
	const OPTION = 'cf7_live_preview_options';

	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	const PAGE = 'cf7-live-preview';

	/**
	 * Device widths the preview offers, by key
	 *
	 * @return array<string, string> Labels by key.
	 */
	public static function widths() {
		return array(
			'mobile' => __( 'Mobile (375px)', 'cf7-live-preview' ),
			'tablet' => __( 'Tablet (768px)', 'cf7-live-preview' ),
			'full'   => __( 'Full width', 'cf7-live-preview' ),
		);
	}

	/**
	 * Default values
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Empty: the theme's own background.
			'background_color' => '',
			'default_width'    => 'full',
		);
	}

	/**
	 * Saved settings over the defaults
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? self::sanitize( $saved ) : array() );
	}

	/**
	 * Hook the settings page
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . CF7_LIVE_PREVIEW_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker' ) );
	}

	/**
	 * WordPress's colour picker on the background colour field
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_color_picker( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery( function ( $ ) { $( "#cf7lp-background-color" ).wpColorPicker(); } );'
		);
	}

	/**
	 * Add Settings > CF7 Live Preview
	 */
	public function add_page() {
		add_options_page(
			__( 'CF7 Live Preview', 'cf7-live-preview' ),
			__( 'CF7 Live Preview', 'cf7-live-preview' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option, section and fields
	 */
	public function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'cf7lp_preview',
			__( 'Form preview', 'cf7-live-preview' ),
			static function () {
				printf(
					'<p>%s</p>',
					esc_html__( 'How the "Preview form" window looks when it opens.', 'cf7-live-preview' )
				);
			},
			self::PAGE
		);

		add_settings_field(
			'background_color',
			__( 'Background colour', 'cf7-live-preview' ),
			array( $this, 'render_background_field' ),
			self::PAGE,
			'cf7lp_preview',
			array( 'label_for' => 'cf7lp-background-color' )
		);

		add_settings_field(
			'default_width',
			__( 'Opens at', 'cf7-live-preview' ),
			array( $this, 'render_width_field' ),
			self::PAGE,
			'cf7lp_preview',
			array( 'label_for' => 'cf7lp-default-width' )
		);
	}

	/**
	 * Sanitize submitted settings
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		if ( isset( $input['background_color'] ) ) {
			$color                     = sanitize_hex_color( (string) $input['background_color'] );
			$clean['background_color'] = $color ? $color : '';
		}

		if ( isset( $input['default_width'] ) && array_key_exists( (string) $input['default_width'], self::widths() ) ) {
			$clean['default_width'] = (string) $input['default_width'];
		}

		return $clean;
	}

	/**
	 * Background colour field
	 */
	public function render_background_field() {
		$settings = self::get();

		printf(
			'<input type="text" id="cf7lp-background-color" name="%1$s[background_color]" value="%2$s" class="regular-text" placeholder="#ffffff" pattern="^#([0-9a-fA-F]{3}){1,2}$" aria-describedby="cf7lp-background-color-description" />',
			esc_attr( self::OPTION ),
			esc_attr( $settings['background_color'] )
		);

		printf(
			'<p class="description" id="cf7lp-background-color-description">%s</p>',
			esc_html__( 'A hex colour behind the form, such as #f6f7f7. Leave empty to use the theme\'s background.', 'cf7-live-preview' )
		);
	}

	/**
	 * Default width field
	 */
	public function render_width_field() {
		$settings = self::get();

		printf( '<select id="cf7lp-default-width" name="%s[default_width]" aria-describedby="cf7lp-default-width-description">', esc_attr( self::OPTION ) );

		foreach ( self::widths() as $key => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $settings['default_width'], $key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		printf(
			'<p class="description" id="cf7lp-default-width-description">%s</p>',
			esc_html__( 'Until someone picks a width in the preview; their choice is then remembered in their browser.', 'cf7-live-preview' )
		);
	}

	/**
	 * The settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * "Settings" link on the Plugins screen
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
				esc_html__( 'Settings', 'cf7-live-preview' )
			)
		);

		return $links;
	}
}
