# CF7 Live Preview

Preview a Contact Form 7 form and its email from the editor, with your theme's styles and without saving.

## Description

CF7 Live Preview adds two buttons to the Contact Form 7 editor. Both work from what is in the editor right now, so a change can be checked before it is saved.

- **Preview form** shows the form as visitors will see it, rendered inside the site's own front end so the theme's styles apply. Submitting it runs Contact Form 7's validation and shows its own messages.
- **Preview email** shows the email the form would send, filled with sample values that can be edited.

Nothing is ever saved, stored or sent: the form isn't saved, a preview submission is only validated, and the email is composed without being handed to the mailer.

## Features

- **Form preview**
  - Mobile (375px), tablet (768px) and full-width views; a device wider than the window is scaled to fit rather than cropped
  - Rendered with the active theme's styles, block or classic theme
  - Validation testing with Contact Form 7's own rules, error tips and response messages, including unsaved edits to the Messages tab
- **Email preview**
  - From, To, Subject, extra headers and attachments, then the body as plain text or HTML
  - Mail and Mail (2); Mail (2) is offered while it's switched on
  - Sample values per field type, editable in the preview; special mail-tags that need a real submission (`[_date]`, `[_url]`, `[_remote_ip]`, …) get samples too
- **Add-on compatibility**: add-ons that change a form's markup or validation work in both previews, including unsaved Conditional Fields conditions
- **Settings**: background colour of the form preview and the width it opens at
- **Accessible**: keyboard-operable modals that return focus to their button, labelled controls, reduced-motion support
- **Clean uninstall**: removes the plugin's only stored data, its settings

## Installation

### WordPress Installation

1. Install and activate [Contact Form 7](https://wordpress.org/plugins/contact-form-7/)
2. Download the plugin zip file or clone this repository
3. Upload the `cf7-live-preview` folder to the `/wp-content/plugins/` directory
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Edit a contact form: **Preview form** is beside the Form heading, **Preview email** beside the Mail heading

### Development Setup

This project uses [Bun](https://bun.sh) as the package manager and [@wordpress/env](https://github.com/WordPress/gutenberg/tree/trunk/packages/env) for local WordPress development.

#### Prerequisites

- [Bun](https://bun.sh) installed on your system
- [Composer](https://getcomposer.org) for the PHP coding standards
- Docker and Docker Compose (required for wp-env)

#### Setup Steps

1. **Clone the repository**
```bash
git clone https://github.com/mgiannopoulos24/CF7-Live-Preview.git
cd CF7-Live-Preview
```

2. **Install dependencies**
```bash
bun install
composer install
```

3. **Start the local WordPress environment**
```bash
bun run start
```
This starts WordPress at `http://localhost:8888` (user `admin`, password `password`) with Contact Form 7 and this plugin active.

## Available Scripts

### Development Scripts

- `bun run start` - Start the local WordPress environment using wp-env
- `bun run stop` - Stop the WordPress environment
- `bun run destroy` - Destroy the WordPress environment (removes all data)
- `bun run shell` - Open a bash shell in the WordPress CLI container
- `bun run compat:on` / `bun run compat:off` - Activate or deactivate the CF7 add-ons used for compatibility testing

### Build Scripts

- `bun run bundle` - Create a distribution zip file (`build/cf7-live-preview.zip`) containing the plugin files
- `bun run lint` - Check all PHP files against the WordPress Coding Standards
- `bun run lint:fix` - Fix what PHPCS can fix automatically

### Test Scripts

- `bun run test:e2e:install` - Download the Chromium build Playwright needs (once)
- `bun run test:e2e` - Run the Playwright end-to-end tests against the wp-env site (started if not running)
- `bun run test:e2e:report` - Open the HTML report of the last run

### Translation Scripts

- `bun run i18n` - Regenerate the POT template (`languages/cf7-live-preview.pot`) after adding translatable strings

## Usage

1. Edit a contact form under **Contact → Contact Forms**
2. Click **Preview form** to see the form as it is in the editor, unsaved
   - Pick **Mobile**, **Tablet** or **Full** under the preview; the choice is remembered in your browser
   - Fill it in and submit it to see Contact Form 7's validation messages
   - **Refresh** reloads the front end, for example after changing theme styles
3. On the **Mail** tab, click **Preview email** to see the email the form would send
   - Switch between **Mail** and **Mail (2)**
   - Open **Sample values** to change what the fields contain
4. Optional: **Settings → CF7 Live Preview** sets the preview's background colour and the width it opens at

## Project Structure

```
cf7-live-preview/
├── cf7-live-preview.php                       # Plugin header, constants, bootstrap
├── uninstall.php                              # Removes the settings option
├── includes/
│   ├── class-cf7-live-preview.php             # Main class: boots the components once CF7 has loaded
│   ├── class-cf7-live-preview-autoloader.php  # CF7_Live_Preview_Foo → class-cf7-live-preview-foo.php
│   ├── class-cf7-live-preview-admin.php       # Assets and config on the CF7 editor screen
│   ├── class-cf7-live-preview-rest.php        # REST routes: render, mail, preview feedback
│   ├── class-cf7-live-preview-renderer.php    # Unsaved form → HTML
│   ├── class-cf7-live-preview-mail-renderer.php  # Mail template + samples → composed email
│   ├── class-cf7-live-preview-validator.php   # Validation-only submission
│   ├── class-cf7-live-preview-preview-frame.php  # Front-end page the form preview loads
│   ├── class-cf7-live-preview-compat.php      # Unsaved add-on settings (Conditional Fields)
│   └── class-cf7-live-preview-settings.php    # Settings → CF7 Live Preview
├── templates/
│   └── preview-frame.php                      # The preview frame's page shell
├── assets/
│   ├── css/
│   │   ├── admin-preview.css                  # Modals, pill switcher, email preview
│   │   └── frame.css                          # Inside the preview frame
│   └── js/
│       ├── admin-preview.js                   # Modals and requests on the editor screen
│       └── frame.js                           # Renders into the frame, re-initialises CF7
├── languages/
│   └── cf7-live-preview.pot
├── tests/e2e-pw/                              # Playwright suite
│   ├── playwright.config.ts
│   ├── tsconfig.json
│   └── specs/
├── .github/                                   # CI and release workflows, issue and PR templates
├── .wp-env.json
├── composer.json
├── package.json
├── phpcs.xml
├── readme.txt                                 # WordPress.org readme
├── changelog.txt
└── CHANGELOG.md
```

## Requirements

- WordPress 6.5 or higher
- PHP 8.3 or higher
- Contact Form 7 (tested with 6.2.1)
- Bun, Composer and Docker (for development)

## Development

The plugin follows the WordPress Coding Standards (`phpcs.xml`), with classes named `CF7_Live_Preview_*` in `includes/class-cf7-live-preview-*.php` and loaded by a small autoloader; there are no namespaces. The local environment (`.wp-env.json`) uses:

- WordPress latest version
- PHP 8.3
- Twenty Twenty-Five (active) and Twenty Twenty-One, for block and classic theme checks
- Contact Form 7, plus Conditional Fields for CF7, Webheadcoder Multi-Step Forms and Ultra Addons for CF7 — installed but inactive; `bun run compat:on` activates them, and `tests/e2e-pw/specs/compat.spec.js` switches them on around itself
- Debug mode enabled

### How the previews avoid side effects

The previews build on Contact Form 7 internals rather than saving or submitting anything:

- **Rendering**: an unsaved `WPCF7_ContactForm` from `get_template()`, with the editor's markup set and the add-on property filters run on it.
- **Email**: `WPCF7_Mail::compose( false )`, called through Reflection, which returns the composed email before it reaches `wp_mail()`.
- **Validation**: a `WPCF7_Submission` created through Reflection without running its pipeline; only its validation steps are called, so no spam check, `wpcf7_before_send_mail`, mail or `wpcf7_mail_sent` happens.

Each of these is checked for availability first; if a Contact Form 7 release changes them, the affected preview is switched off.

### Hooks

- `cf7lp_enqueue_preview_assets` (action) - Fires when the preview frame enqueues its assets; enqueue theme CSS that normally loads only on certain pages
- `cf7lp_after_render` (DOM event, in the frame) - Fired on `document` after each render, with `detail.root`, for add-ons that need to re-initialise

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes and version history.

## License

This plugin is licensed under the GPL v3 or later license. See [LICENSE](LICENSE).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
