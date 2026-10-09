# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-09

### Added
- **Form preview.** A "Preview form" button beside the Form heading opens a modal that renders the editor's unsaved template (`POST cf7lp/v1/render`) inside an iframe of the site's front end, so the theme's styles apply.
  - Mobile (375px), tablet (768px) and full-width views, switched by a pill under the frame; a device wider than the modal keeps its real width and is scaled to fit.
  - The frame (`/?cf7lp_preview=1&_wpnonce=…`) checks its nonce and the `wpcf7_edit_contact_forms` capability, hides the admin bar, and sends `noindex`, no-cache, `X-Frame-Options`/`frame-ancestors` and `Referrer-Policy` headers.
  - The admin and the frame talk over `postMessage`, each checking the other's origin and window.
- **Validation testing.** Submitting the previewed form goes through Contact Form 7's own script, pointed at `POST cf7lp/v1/preview/contact-forms/{id}/feedback`, so CF7 renders its own tips and response messages. Unsaved Messages-tab edits apply. Only a submission's validation steps run; nothing is stored, spam-checked or mailed.
- **Email preview.** A "Preview email" button beside the Mail heading composes the Mail or Mail (2) template (`POST cf7lp/v1/mail`) with editable sample values, without handing it to the mailer. HTML bodies are shown in a sandboxed iframe without scripts.
- **Add-on compatibility.** Add-ons' property filters run on the unsaved form, so markup they generate (such as Conditional Fields groups) appears. Unsaved Conditional Fields conditions are sent along and applied; Conditional Fields is re-initialised on each render. Tested with Conditional Fields for Contact Form 7, Webheadcoder Multi-Step Forms and Ultra Addons for Contact Form 7.
- **Settings** (Settings > CF7 Live Preview): background colour of the form preview, and the width it opens at.
- `cf7lp_enqueue_preview_assets` action for themes that load form CSS only on certain pages, and a `cf7lp_after_render` DOM event in the frame for add-ons.
- Admin notice when Contact Form 7 is not active.
- `uninstall.php` removes the settings option (on every site of a network).
- Translation template `languages/cf7-live-preview.pot`.
- Development: wp-env with the add-ons and a classic theme for compatibility testing, PHPCS (WordPress Coding Standards), a Playwright suite (`tests/e2e-pw/`), and CI and release workflows.

### Security
- Every REST route and the preview frame require the `wpcf7_edit_contact_forms` capability; the settings page requires `manage_options`.
- The CF7 internals the previews rely on are checked before use; if a Contact Form 7 release changes them, the affected preview is switched off instead of falling back to anything that could send or store a submission.
