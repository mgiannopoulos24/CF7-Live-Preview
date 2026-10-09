# Wordpress-Plugin-Template
<!-- Change the title -->
This repository serves as a template for any plugin. 

<!-- Change the description -->

## Overview

## Features

## Requirements

## Installation

## Development & Testing

## Configuration

## Development

## Project Structure

The plugin follows a modular architecture. Two standard structures are available depending on plugin complexity:

### Small Plugin (No Namespace)

For plugins with limited scope — settings pages, simple integrations, utility tools.

```bash
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

### Large Plugin (PSR-4 + Namespace)

For plugins with complex business logic, multiple services, REST APIs, or WooCommerce integrations.

```bash
{plugin-name}/
├── {plugin-name}.php              # Main plugin file (constants, autoloader)
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
│   └── Enums/
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
## Documentation

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## Contributing

## License

## Support

## Author