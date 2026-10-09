# New Plugin Workflow

> Step-by-step guide for creating a new WordPress/WooCommerce plugin.

---

## Step 1: Register Plugin Slug

**Read**: `01-technical-setup.md`

Before writing any code, register the plugin in the slug registry to avoid collisions.

| Decision | Example |
|----------|---------|
| Plugin folder name | `{plugin-name}` |
| Short slug (3-5 chars) | `{SLUG}` |
| Namespace | `{PluginName}` (ask user — only for large plugins) |
| Global prefix | `{PLUGIN_NAME_}` |
| Class prefix | `{plugin_name_}` |

Add to the registry table in `01-technical-setup.md`.

---

## Step 2: Create Directory Structure

**Read**: `02-architecture.md`

Create the folder structure:

```bash
mkdir -p {plugin-name}/{includes/{Admin,Frontend,REST,Services,Contracts},assets/{css,js,images},views/admin,tests/Unit,languages,docs,scripts}
```

Result:
```
{plugin-name}/
├── plugin.php
├── includes/
│   ├── Plugin.php
│   ├── Admin/
│   ├── Frontend/
│   ├── REST/
│   ├── Services/
│   └── Contracts/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── views/
├── tests/
├── languages/
├── docs/
├── scripts/
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── package.json
├── CHANGELOG.md
└── .wp-env.json
```

Result:
```
dn-{plugin-name}/
├── dn-{plugin-name}.php
├── src/
│   ├── Plugin.php
│   ├── Admin/
│   ├── Frontend/
│   ├── REST/
│   ├── Services/
│   └── Contracts/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── templates/
├── tests/
├── languages/
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── build.sh
└── build.ps1
```

---

## Step 3: Create composer.json

**Read**: `02-architecture.md` (composer.json section)

```json
{
	"name": "digitalnest/{plugin-name}",
	"description": "{Description}",
	"type": "wordpress-plugin",
	"license": "GPL-2.0-or-later",
	"require": {
		"php": ">=8.3"
	},
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
			"{PluginName}\\Tests\\": "tests/"
		}
	},
	"autoload-dev": {
		"psr-4": {
			"{Plugin_Namespace}\\Tests\\": "tests/"
		}
	},
	"scripts": {
		"test": "phpunit",
		"test:unit": "phpunit --testsuite unit",
		"analyse": "phpstan analyse --memory-limit=512M",
		"build": "composer install --no-dev --optimize-autoloader --classmap-authoritative"
	}
}
```

Run: `composer install`

---

## Step 4: Create Main Plugin File

**Read**: `02-architecture.md` (Main Plugin File Pattern)

Create `plugin.php`:

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

// Simple plugin: hooks registered directly here.
// Large plugin: uncomment the line below.
// {PluginName}\Plugin::instance();
```

---

## Step 5: Create Plugin Class (Optional — Large Plugins Only)

**Read**: `02-architecture.md` (Plugin Class Pattern)

For **simple plugins**, skip this step. Register hooks directly in `plugin.php`.

For **large plugins**, create `includes/Plugin.php` with:
- Class constants: `VERSION`, `SLUG`, `PREFIX`, `WC_MIN_VERSION`
- Singleton pattern
- HPOS compatibility declaration
- Dependency checking
- Service container
- Activation/deactivation hooks

---

## Step 6: Create First Service

**Read**: `03-modern-php.md`

Create a service class in `includes/Services/`:

```php
<?php
declare(strict_types=1);

namespace {PluginName}\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * {Service description}.
 *
 * @since 1.0.0
 */
final class {Name}_Service {

	public function __construct(
		// Dependencies via constructor promotion
	) {}

	// Public methods with full type hints
}
```

Register in `Plugin::init_services()`.

---

## Step 7: Add Security Layer

**Read**: `04-security.md`

For any user-facing functionality:

- [ ] AJAX handlers follow the mandatory pattern (nonce → capability → sanitize → respond)
- [ ] REST endpoints have proper `permission_callback`
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] Nonces use `Plugin::PREFIX`

---

## Step 8: WooCommerce Integration

**Read**: `05-woocommerce.md`

If integrating with WooCommerce:

- [ ] HPOS compatibility declared
- [ ] Using CRUD classes (`wc_get_product`, `wc_get_order`)
- [ ] No direct SQL for orders/products
- [ ] Block checkout considered if extending checkout
- [ ] Logging uses `wc_get_logger()`

---

## Step 9: Performance Optimization

**Read**: `06-performance.md`

Before shipping:

- [ ] Assets load conditionally (not globally)
- [ ] Heavy tasks use Action Scheduler
- [ ] Expensive operations cached with `Plugin::PREFIX`
- [ ] All queries have `limit` set
- [ ] Cache invalidation on data changes

---

## Step 10: Create Uninstall Handler

Create `uninstall.php` in the plugin root to clean up when the plugin is deleted:

```php
<?php
/**
 * Uninstall handler.
 *
 * @package {PluginName}
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up plugin data (options, transients, custom tables).
```

Delete options, custom tables, and transients created by the plugin. Only run code from this file — never call `uninstall.php` from elsewhere.

---

## Step 11: Add JavaScript

**Read**: `07-javascript.md`

- Frontend: Vanilla JS with `fetch()`
- Admin: jQuery acceptable
- Data: `wp_add_inline_script()` for config, `wp_localize_script()` for i18n only

---

## Step 12: Write Tests

**Read**: `09-testing.md`

Create test infrastructure:

1. `tests/phpunit/bootstrap.php`
2. `tests/Unit_Test_Case.php`
3. `phpunit.xml`

Write tests for:
- [ ] All service class public methods
- [ ] AJAX handlers (security paths)
- [ ] REST controllers (permissions)
- [ ] Edge cases (zero, negative, null, empty)

Run: `composer test`

---

## Step 13: Static Analysis

**Read**: `10-static-analysis.md`

Create `phpstan.neon` and run:

```bash
composer analyse
```

Fix all errors before shipping.

---

## Step 14: Final Checklist

**Read**: `11-checklist.md`

Go through every item before committing.

---

## Quick Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse

# Bundle for production (creates zip)
bun run bundle
```

---

## Files to Create (Summary)

| Order | File | Template In |
|-------|------|-------------|
| 1 | `composer.json` | `02-architecture.md` |
| 2 | `plugin.php` | `02-architecture.md` |
| 3 | `includes/Plugin.php` | `02-architecture.md` |
| 4 | `includes/Services/*.php` | `03-modern-php.md` |
| 5 | `includes/Admin/*.php` | `04-security.md` |
| 6 | `includes/REST/*.php` | `04-security.md` |
| 7 | `assets/js/*.js` | `07-javascript.md` |
| 8 | `tests/phpunit/bootstrap.php` | `09-testing.md` |
| 9 | `tests/Unit_Test_Case.php` | `09-testing.md` |
| 10 | `phpunit.xml` | `09-testing.md` |
| 11 | `phpstan.neon` | `10-static-analysis.md` |
| 12 | `uninstall.php` | `02-architecture.md` |
| 13 | `package.json` | `02-architecture.md` |
