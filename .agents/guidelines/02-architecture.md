# Architecture

> Directory structure, plugin patterns, and coding conventions.

---

## Small Plugin Structure

For plugins with limited scope — settings pages, simple integrations, utility tools.

**Characteristics**: No namespace, `class-` prefix, flat `includes/`, hooks registered directly in main file.

### Directory Structure

```
{plugin-name}/
├── {plugin-name}.php              # Main plugin file (constants, hooks)
├── includes/                      # PHP class files (flat, no namespaces)
│   ├── class-{plugin-name}.php    # Main class
│   ├── class-{plugin-name}-admin.php
│   └── class-{plugin-name}-frontend.php
├── assets/                        # Static assets
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── views/                         # Admin view templates
│   └── admin-settings.php
├── tests/                         # Test files
│   ├── phpunit/                   # PHPUnit tests
│   │   ├── bootstrap.php          # PHPUnit bootstrap
│   │   └── SanityTest.php
│   └── e2e-pw/                    # Playwright E2E tests
│       ├── playwright.config.ts   # Playwright config
│       └── tests/
├── languages/                     # Translation files
│   └── {plugin-name}.pot
├── scripts/                       # Utility scripts
│   └── i18n-lang.sh
├── vendor/                        # Composer autoloader
├── templates/                     # Custom templates (optional)
├── woocommerce/                   # WooCommerce template overrides (optional)
├── composer.json
├── package.json
├── phpcs.xml
├── phpstan.neon
├── phpunit.xml
├── readme.txt
├── CHANGELOG.md
├── uninstall.php
└── .wp-env.json
```

### Main Plugin File

```php
<?php
/**
 * Plugin Name: {Plugin Name}
 * Description: {Description}
 * Version:     1.0.0
 * Author:      DigitalNest
 * Author URI:  https://digitalnest.gr
 * Text Domain: {plugin-name}
 * Requires PHP: 8.3
 *
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( '{PLUGIN_NAME}_FILE', __FILE__ );
define( '{PLUGIN_NAME}_PATH', plugin_dir_path( __FILE__ ) );
define( '{PLUGIN_NAME}_URL', plugin_dir_url( __FILE__ ) );
define( '{PLUGIN_NAME}_VERSION', '1.0.0' );

// Load Composer autoloader (if present).
if ( file_exists( {PLUGIN_NAME}_PATH . 'vendor/autoload.php' ) ) {
	require_once {PLUGIN_NAME}_PATH . 'vendor/autoload.php';
}

// Load classes.
require_once {PLUGIN_NAME}_PATH . 'includes/class-{plugin-name}.php';
require_once {PLUGIN_NAME}_PATH . 'includes/class-{plugin-name}-admin.php';

// Initialize.
add_action( 'plugins_loaded', function(): void {
	$plugin = new {Plugin_Name}();
	$plugin->init();

	if ( is_admin() ) {
		$admin = new {Plugin_Name}_Admin();
		$admin->init();
	}
} );
```

### Class Pattern (No Namespace)

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class {Plugin_Name} {

	public function init(): void {
		add_action( 'init', [ $this, 'register_hooks' ] );
	}

	public function register_hooks(): void {
		// Register hooks here.
	}
}
```

---

## Large Plugin Structure (PSR-4 + Namespace)

For plugins with complex business logic, multiple services, REST APIs, or WooCommerce integrations.

**Characteristics**: `{PluginName}\` namespace, PSR-4 autoloading, singleton `Plugin` class, service container, DI.

### Directory Structure

```
{plugin-name}/
├── {plugin-name}.php              # Main plugin file (constants, autoloader, Plugin::instance())
├── includes/                      # PHP classes (PSR-4 root)
│   ├── Plugin.php                 # Main plugin class (singleton)
│   ├── Admin/
│   │   └── Settings.php
│   ├── Frontend/
│   │   └── Assets.php
│   ├── REST/
│   │   └── Controller.php
│   ├── Services/
│   │   └── Main_Service.php
│   ├── Contracts/
│   │   └── Service_Interface.php
│   └── Enums/                     # PHP 8.1+
│       └── Status.php
├── assets/                        # Static assets
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── views/                         # View templates
│   └── admin/
│       └── settings.php
├── tests/                         # Test files
│   ├── phpunit/                   # PHPUnit tests
│   │   ├── bootstrap.php          # PHPUnit bootstrap
│   │   └── SanityTest.php
│   └── e2e-pw/                    # Playwright E2E tests
│       ├── playwright.config.ts   # Playwright config
│       └── tests/
├── languages/                     # Translation files
│   └── {plugin-name}.pot
├── scripts/                       # Utility scripts
│   └── i18n-lang.sh
├── templates/                     # Custom templates (optional)
├── woocommerce/                   # WooCommerce template overrides (optional)
├── vendor/                        # Composer autoloader
├── composer.json
├── package.json
├── phpcs.xml
├── phpstan.neon
├── phpunit.xml
├── readme.txt
├── CHANGELOG.md
├── uninstall.php
└── .wp-env.json
```

### Main Plugin File

```php
<?php
/**
 * Plugin Name: {Plugin Name}
 * Description: {Description}
 * Version:     1.0.0
 * Author:      DigitalNest
 * Author URI:  https://digitalnest.gr
 * Text Domain: {plugin-name}
 * Requires PHP: 8.3
 *
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( '{PLUGIN_NAME}_FILE', __FILE__ );
define( '{PLUGIN_NAME}_PATH', plugin_dir_path( __FILE__ ) );

require_once {PLUGIN_NAME}_PATH . 'vendor/autoload.php';

{PluginName}\Plugin::instance();
```

### Plugin Class Pattern (Singleton + DI)

```php
<?php
declare(strict_types=1);

namespace {PluginName};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Plugin {

	public const VERSION = '1.0.0';
	public const SLUG = '{plugin-name}';
	public const PREFIX = '{plugin_name}_';
	public const WC_MIN_VERSION = '8.0';

	private static ?self $instance = null;
	private array $services = [];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function url( string $path = '' ): string {
		return plugin_dir_url( {PLUGIN_NAME}_FILE ) . ltrim( $path, '/' );
	}

	public static function path( string $path = '' ): string {
		return {PLUGIN_NAME}_PATH . ltrim( $path, '/' );
	}

	public function get_service( string $class_name ): object {
		if ( ! isset( $this->services[ $class_name ] ) ) {
			throw new \RuntimeException(
				sprintf( 'Service %s is not registered.', $class_name )
			);
		}
		return $this->services[ $class_name ];
	}

	private function __construct() {
		$this->register_hooks();
	}

	private function __clone() {}

	private function register_hooks(): void {
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
		register_activation_hook( {PLUGIN_NAME}_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( {PLUGIN_NAME}_FILE, [ $this, 'deactivate' ] );
	}

	public function declare_hpos_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				{PLUGIN_NAME}_FILE,
				true
			);
		}
	}

	public function init(): void {
		if ( ! $this->check_dependencies() ) {
			return;
		}

		$this->init_services();
		$this->init_components();

		do_action( '{plugin-name}/loaded', $this );
	}

	private function check_dependencies(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'notice_woocommerce_missing' ] );
			return false;
		}

		if ( version_compare( WC_VERSION, self::WC_MIN_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'notice_woocommerce_version' ] );
			return false;
		}

		return true;
	}

	private function init_services(): void {
		$this->services[ Services\Main_Service::class ] = new Services\Main_Service();
	}

	private function init_components(): void {
		if ( is_admin() ) {
			new Admin\Settings(
				$this->get_service( Services\Main_Service::class )
			);
		}

		new REST\Controller(
			$this->get_service( Services\Main_Service::class )
		);
	}

	public function activate(): void {
		update_option( self::PREFIX . 'version', self::VERSION );
		do_action( '{plugin-name}/activated' );
	}

	public function deactivate(): void {
		as_unschedule_all_actions( '{plugin-name}/sync', [], '{plugin-name}' );
		do_action( '{plugin-name}/deactivated' );
	}

	public function notice_woocommerce_missing(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					esc_html__( '%s requires WooCommerce to be installed and active.', '{plugin-name}' ),
					'<strong>{Plugin Name}</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	public function notice_woocommerce_version(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					esc_html__( '%1$s requires WooCommerce %2$s or higher.', '{plugin-name}' ),
					'<strong>{Plugin Name}</strong>',
					esc_html( self::WC_MIN_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}
}
```

### composer.json (Small Plugin)

```json
{
	"name": "digitalnest/{plugin-name}",
	"description": "{Description}",
	"type": "wordpress-plugin",
	"license": "GPL-2.0-or-later",
	"require": {},
	"require-dev": {
		"phpunit/phpunit": "*",
		"brain/monkey": "*",
		"yoast/phpunit-polyfills": "*",
		"mockery/mockery": "*",
		"phpstan/phpstan": "*",
		"szepeviktor/phpstan-wordpress": "*",
		"php-stubs/woocommerce-stubs": "*",
		"squizlabs/php_codesniffer": "*",
		"wp-coding-standards/wpcs": "*"
	},
	"autoload": {},
	"autoload-dev": {
		"psr-4": {
			"{PluginName}\\Tests\\": "tests/phpunit/"
		}
	},
	"config": {
		"optimize-autoloader": true,
		"sort-packages": true,
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		}
	}
}
```

### composer.json (Large Plugin)

```json
{
	"name": "digitalnest/{plugin-name}",
	"description": "{Description}",
	"type": "wordpress-plugin",
	"license": "GPL-2.0-or-later",
	"require": {},
	"require-dev": {
		"phpunit/phpunit": "*",
		"brain/monkey": "*",
		"yoast/phpunit-polyfills": "*",
		"mockery/mockery": "*",
		"phpstan/phpstan": "*",
		"szepeviktor/phpstan-wordpress": "*",
		"php-stubs/woocommerce-stubs": "*",
		"squizlabs/php_codesniffer": "*",
		"wp-coding-standards/wpcs": "*"
	},
	"autoload": {
		"psr-4": {
			"{PluginName}\\": "includes/"
		}
	},
	"autoload-dev": {
		"psr-4": {
			"{PluginName}\\Tests\\": "tests/phpunit/"
		}
	},
	"config": {
		"optimize-autoloader": true,
		"sort-packages": true,
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		}
	}
}
```

---

## Architecture Rules (Both)

- Main plugin file should **only**: define constants, load autoloader/classes, bootstrap.
- Static assets live in `assets/` — never in `includes/`.
- PHP templates live in `views/` — never in `includes/`.
- WooCommerce template overrides go in `woocommerce/`.
- Custom templates go in `templates/`.
- **Constants strategy**: Minimal globals (FILE, PATH, URL, VERSION) + class constants for everything else.
- **Enums** (PHP 8.1+): Use backed enums for fixed sets (statuses, types).

### Small Plugin Rules

- No namespace — use `class-` prefix for all PHP files.
- Hooks registered directly in main file or main class.
- Flat `includes/` directory (or simple subdirs like `admin/`, `api/` if needed — ask user).
- Classes instantiated directly, no service container.

### Large Plugin Rules

- `{PluginName}\` namespace with PSR-4 autoloading.
- Singleton `Plugin` class with service container and DI.
- Group classes by purpose: `Admin/`, `Frontend/`, `REST/`, `Services/`, `Contracts/`, `Enums/`.
- Use **dependency injection** for services.

---

## Bundle Script (package.json)

Use `bestzip` via `package.json` for production bundling:

```json
{
	"scripts": {
		"bundle": "bunx bestzip {plugin-name}.zip {plugin-name}.php readme.txt includes/ assets/ views/ languages/ templates/ woocommerce/"
	}
}
```

Run:

```bash
bun run bundle
```

### What Gets Excluded

- `tests/` — Unit and E2E tests
- `phpunit.xml`, `phpstan.neon`, `phpcs.xml` — Config files
- `.git/`, `.github/` — Version control and CI
- `node_modules/` — Node dependencies
- `.env` — Environment files
- `scripts/` — Utility scripts
- Dev dependencies in `vendor/`

### Directory Structure After Bundle

```
{plugin-name}.zip
├── {plugin-name}.php
├── includes/
├── assets/
├── views/
├── languages/
├── templates/        # if exists
├── woocommerce/      # if exists
└── vendor/           # production dependencies only
```
