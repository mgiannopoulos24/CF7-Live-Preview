# CF7 Live Preview — Todo

The original planning document was removed; this file is now the only record of scope. Decisions already made are recorded under **Decisions locked** below.

Priorities: **P0 (Critical)** → **P1 (High)** → **P2 (Medium)** → **P3 (Low)**.

---

## Needs a decision (blocks the work below)

- [x] **Validation approach for an unsaved form** — **A** (no DB writes). Built as a CF7-shaped feedback route that CF7's own front-end script posts to; see Phase 4.
- [x] **Panel position** — resolved as **modals**, not an inline panel (the inline panel with a stacked / side-by-side toggle was built first and dropped; side by side was rejected outright). "Preview form" on the Form tab, "Preview email" on the Mail tab.
- [x] **Live-update scope** — Form and Mail, each in its own modal, rendered from the unsaved editor contents when opened. Messages belong with Phase 4's validation testing (they only appear after a submit).

---

## Decisions locked

- **PHP 8.3 everywhere** — because CF7 6.2.1 requires it (CF7 6.1.7 only needs 7.4). Dev env, `Requires PHP`, `composer.json`, PHPCS `testVersion` and CI all agree on 8.3.
- **No namespaces, no PSR-4** — WPCS `WordPress.Files.FileName` demands `class-*.php`, which is incompatible with PSR-4 file naming. The sibling plugin resolves this with a hand-rolled `spl_autoload_register`; we match it. Classes are `CF7_Live_Preview_*` in `includes/class-cf7-live-preview-*.php`.
- **CF7 screens are matched by page slug, not hook suffix** — CF7 6.2 moved its screens under a new `wpcf7-dashboard` top-level menu, and the suffix is built from the *translated* menu title, so it differs per language. `CF7_Live_Preview_Admin::is_editor_screen()` matches `$plugin_page` (`wpcf7-new`, or `wpcf7` with a current form).
- **Boot on `plugins_loaded`** — `cf7-live-preview` sorts before `contact-form-7`, so `WPCF7_VERSION` isn't defined at include time. Phase 0 checked it there and showed the "requires CF7" notice even with CF7 active; fixed.
- **The preview never submits** — `frame.js` swallows `submit` in the capture phase until Phase 4 replaces it with `/validate`.
- **No PHPUnit** — removed (dependency, config, `test:php`, CI job). PHP behaviour is covered by the Playwright suite against wp-env (rendering, email composition, preview-frame access). The REST capability checks were verified by hand with `wp eval-file`; they have no automated test now.
- **`Requires at least: 6.5`** stays even though CF7 6.2.1 wants WP 7.1 — a WP 6.5 user can pair us with CF7 6.1.x. Only the locally testable combination is WP 7.1 + CF7 6.2.1.

---

## P0 – Critical

### Phase 0 — Setup (done)
- [x] `cf7-live-preview.php` — plugin header (`Requires Plugins: contact-form-7`, `Requires at least: 6.5`, `Tested up to: 7.1.2`, `Requires PHP: 8.3`), the four `CF7_LIVE_PREVIEW_*` constants, `cf7_live_preview()` accessor.
- [x] `includes/class-cf7-live-preview.php` — singleton via `get_instance()`, loads the text domain on `init`.
- [x] `includes/class-cf7-live-preview-autoloader.php` — maps `CF7_Live_Preview_Foo_Bar` → `includes/class-cf7-live-preview-foo-bar.php`. Verified with a throwaway harness: loads correctly, converts underscores, and ignores other plugins' classes.
- [x] CF7 dependency guard — capability-gated `admin_notices` error when `! defined( 'WPCF7_VERSION' )`.
- [x] `uninstall.php` — `WP_UNINSTALL_PLUGIN` guard, no-op until the settings page lands.
- [x] `composer.json` — real metadata, PHP `>=8.3` + `platform.php 8.3`, dev deps cut to phpcs / wpcs.
- [x] `.wp-env.json` — PHP 8.3, `WordPress/twentytwentyfive`, CF7 latest zip, `mappings` pins the slug to `cf7-live-preview`, `lifecycleScripts.afterStart` activates it.
- [x] `phpcs.xml` — aligned to the sibling plugin's ruleset (`WordPress` + `WordPress-Docs` + `Generic.Commenting.Todo`, text domain `cf7-live-preview`, `testVersion 8.3-`, `minimum_supported_wp_version 6.5`).
- [x] CI — replaced the five template workflows with `ci.yml` (lint → test + e2e) and `release.yml`, ported from the sibling plugin.
- [x] Verified: `phpcs` 0 errors / 0 warnings on all four PHP files, `php -l` clean, autoloader harness passing.

- [x] CF7 render API confirmed public in **6.2.1** (probed via `wp eval-file` in wp-env, PHP 8.3.35): `WPCF7_ContactForm::get_template( $options )` is public static, `set_properties( $properties )` and `form_html( $options )` are public. `get_template()` → `set_properties( [ 'form' => … ] )` → `form_html()` renders the fields from an **unsaved** form (no post `id`, no DB write).
- [x] `wp-env` booted for real: WordPress 7.1.3, PHP 8.3, CF7 6.2.1 and CF7 Live Preview both active, front end returns 200.
- [x] `readme.txt` and `changelog.txt` written (`= x.y.z =` headings, which `release.yml`'s awk expects); GPLv3 `LICENSE` added and included in `bundle`. `Tested up to: 7.1.2` matches between the header and `readme.txt`. Verified `bun run bundle` builds and the release-notes extraction yields the 0.1.0 entry.

### Vertical slice (prove the core loop end to end) — done
- [x] REST route `POST cf7lp/v1/render` with `permission_callback` → `current_user_can( 'wpcf7_edit_contact_forms' )`.
- [x] `includes/class-cf7-live-preview-renderer.php` — markup → HTML via `WPCF7_ContactForm::get_template()` + `set_properties()` + `form_html()`; guard each with `is_callable()` and degrade to a clear admin error if CF7's API moves; catch exceptions and return the error markup for the panel.
- [x] `includes/class-cf7-live-preview-preview-frame.php` + `templates/preview-frame.php` — `template_redirect` handler: verify nonce **and** capability, then load the shell and `exit`.
- [x] `includes/class-cf7-live-preview-admin.php` + `assets/js/admin-preview.js` — enqueue only on the CF7 edit screen, read `textarea#wpcf7-form`, send markup into the frame.
- [x] Verified in wp-env: REST 200 for admin / 403 subscriber / 401 anonymous, bad `locale` → 400, no posts created; Playwright suite (7 tests) passes.

---

## P1 – High

### Phase 1 — Admin panel — done
- [x] Enqueue assets **only** on the CF7 edit screen — by page slug, see *Decisions locked* (the planned `toplevel_page_wpcf7` suffix no longer exists in CF7 6.2). e2e asserts the form list stays clean.
- [x] "Preview form" button beside the Form heading opens a native `<dialog>` modal (Esc / backdrop / × close; focus starts on ×). Modals are appended to `<body>`, **outside** CF7's `<form>`: inside it, Enter in a field would save the form and CF7's unsaved-changes check would see the modal's inputs.
- [x] Modal: Refresh in the header; width presets (mobile 375 / tablet 768 / full) as a pill switcher in the footer, remembered per browser. A device wider than the modal keeps its real width and is scaled to fit. Switching animates (sliding pill highlight, frame resize); off under `prefers-reduced-motion`. The front end loads on first open only.
- [x] Iframe `src = home_url( '/?cf7lp_preview=1&_wpnonce=…' )`.
- [x] Auto-resize iframe height: `ResizeObserver` in the frame → `postMessage` → parent.

### Phase 2 — Preview frame (front end) — done
- [x] Force-enqueue CF7 front-end assets in the frame: `wpcf7_enqueue_scripts()`, `wpcf7_enqueue_styles()`.
- [x] `X-Robots-Tag: noindex`, hide the admin bar, and `nocache_headers()` so caching plugins never serve a stale preview. Also `DONOTCACHEPAGE`, `wp_robots_no_robots`, `X-Frame-Options: SAMEORIGIN`.
- [x] `assets/js/frame.js` — listen for `message` and **verify `event.origin === location.origin`** (and `event.source === window.parent`). Non-negotiable; this is the XSS/clickjacking surface.
- [x] `assets/css/frame.css` — preview padding, background colour var, "preview" badge.

### Phase 3 — Rendering
- [x] Swap returned HTML into the frame, then re-init CF7: `wpcf7.init( formEl )`, guarded by `typeof wpcf7?.init === 'function'`.
- [x] Cancel superseded requests with `AbortController`; sample-value edits in the email modal are debounced (400 ms). Keystroke debouncing in the editor went away with the inline panel.
- [x] ~~Preserve scroll position inside the frame across refreshes.~~ Moot: the frame auto-sizes and the modal body scrolls.
- [x] `assets/css/admin-preview.css` — modals, pill switcher, email preview.

### Tests
- [x] Playwright e2e (`tests/e2e-pw/specs/live-preview.spec.js`, 11 tests): edit → preview shows it, width pill + persistence, scaling, no submit, list screen clean, email with samples (plain + sandboxed HTML), Mail (2) only when active, frame 403s. `tests/e2e-pw/tsconfig.json` type-checks config, helpers and JSDoc in specs. Layout matches the sibling plugin: `specs/`, `setup` project, `webServer`, results under `tests/e2e-pw/`. The config previously referenced a missing `global-setup.ts`, so CI's e2e job could never have run.

---

### Email preview — done
- [x] `POST cf7lp/v1/mail` (same capability) + `includes/class-cf7-live-preview-mail-renderer.php`. Calls CF7's private `WPCF7_Mail::compose( false )` via Reflection, which returns the components **before** `wp_mail()` — nothing can be sent by construction (intercepting `wp_mail` would not be safe: SMTP plugins often replace it). `pre_wp_mail` is also short-circuited during the call as a backstop. Guarded by `is_supported()`; without it the "Preview email" button is left out.
- [x] Sample values per field type (pipes respected: `"Sales|sales@example.com"` mails the address), editable in the modal; special tags that need a submission (`[_date]`, `[_url]`, `[_remote_ip]`, `[_post_*]`…) get samples; site tags are CF7's own.
- [x] Modal: From / To / Subject / extra headers / attachments, plain body in `<pre>`, HTML body in an `<iframe sandbox="allow-same-origin">` (no scripts). Mail / Mail (2) pill; Mail (2) disabled unless "Use Mail (2)" is ticked.
- [x] Verified: 0 `pre_wp_mail` / `phpmailer_init` hits while composing; unknown `mail` properties → 400; anonymous → 401.

---

## P2 – Medium

### Phase 4 — Validation testing (no mail) — done
- [x] Submitting in the preview goes through **CF7's own script**: the frame sets `wpcf7.api` to `{root: /?_wpnonce=…&rest_route=/, namespace: cf7lp/v1/preview}` (CF7's fetch drops `X-WP-Nonce`, so the REST nonce rides in the query string). CF7 posts to `POST cf7lp/v1/preview/contact-forms/{id}/feedback` and renders the answer itself — tips, response box, classes, events. `{id}` is any segment: an unsaved form asks for `contact-forms/NaN/feedback`.
- [x] `includes/class-cf7-live-preview-validator.php` builds a `WPCF7_Submission` through Reflection (private constructor invoked in-class for its `readonly $contact_form`) **without** `proceed()`, and calls only `setup_meta_data()`, `setup_posted_data()`, `validate()`, `accepted()`. Add-ons' validation hooks find a normal current submission (Multi-Step calls `WPCF7_Submission::get_instance()->get_posted_data()` there — running the filters without a submission fataled). Spam checks, `wpcf7_before_send_mail`, mail, `wpcf7_mail_sent`/`wpcf7_submit` never run — verified by hooking all of them plus `pre_wp_mail`/`phpmailer_init`: none fire; the current submission is restored to `null`.
- [x] Each previewed form carries hidden `_cf7lp_form`, `_cf7lp_locale`, `_cf7lp_messages[…]` so the server rebuilds it; **Messages-tab edits apply unsaved**.
- [x] Valid input answers with the form's `mail_sent_ok` message under status `cf7lp_valid` (class `custom-cf7lp-valid`, styled like `sent`) — not `mail_sent`, which would reset the form and fire `wpcf7mailsent` (redirect/analytics add-ons).
- [x] Belt-and-braces: `wpcf7_skip_mail` returns true for any request carrying `_cf7lp_form`.
- [x] e2e: required/email errors via CF7's own tips, valid → sent message, only `cf7lp/v1/preview` is hit (never `contact-forms/…` of CF7), Messages tab unsaved, unchecked acceptance → `unaccepted`.

### Phase 5 — Compatibility — done
- [x] **Add-on property filters**: loading a saved form runs `wpcf7_contact_form_property_{name}` / `wpcf7_contact_form_properties`, where add-ons rewrite markup; `set_properties()` skipped them, so previews missed e.g. Conditional Fields' `[group]` markup. `CF7_Live_Preview_Renderer::create_form()` now runs them (for the properties set) and is used by all three paths.
- [x] **Conditional Fields for CF7** (`cf7-conditional-fields` 2.8.1): unsaved conditions from the "Conditional fields" tab are sent as add-on context (`CF7_Live_Preview_Compat`) and swapped into `_wpcf7cf_options`; the builder UI is synced first (`wpcf7cf.copyFieldsToText()`); `frame.js` calls `wpcf7cf.initForm()` after each swap. Hidden-group required fields are skipped on submit, shown ones enforced.
- [x] **Multi-step**: Webheadcoder Multi-Step Forms (`contact-form-7-multi-step-module` 4.7.1). Each step previews and validates; it advances on `wpcf7mailsent`, which the preview never fires, so it stays put.
- [x] **Styling add-on**: Ultra Addons for CF7 (`ultimate-addons-for-contact-form-7` 3.5.54) — previews and validates with it active. *Material Design for CF7 and Contact Form 7 Style were the planned picks; both are closed on WordPress.org.*
- [x] Full suite passes with all three add-ons active as well as without. `tests/e2e-pw/specs/compat.spec.js` (5 tests) switches them on/off around itself; `bun run compat:on` / `compat:off` do it by hand. They're installed by `.wp-env.json` and deactivated in `afterStart`.
- [x] **Block editor flow vs classic CF7 admin**: nothing to do — forms are only ever edited in CF7's admin screen; CF7's block just embeds a saved form.
- [x] **Block theme vs classic theme**: Twenty Twenty-Five and Twenty Twenty-One (installed by `.wp-env.json`) both style the preview. Theme CSS loaded only on certain pages can't be detected; the `cf7lp_enqueue_preview_assets` action lets a theme enqueue it for the frame (readme FAQ).
- [x] Fire a `cf7lp_after_render` JS event so add-ons can re-init themselves.

### Housekeeping
- [x] `languages/cf7-live-preview.pot` generated via `bun run i18n` (55 strings, PHP and JS).
- [x] `uninstall.php` deletes `cf7_live_preview_options` (every site on multisite, `number => 0`). Nothing else is stored. Verified by running the file in wp-env — **not** with `wp plugin uninstall`, which would delete this repo through the wp-env mapping.

---

## P3 – Low

### Phase 6 — Polish & release
- [x] `includes/class-cf7-live-preview-settings.php`: Settings > CF7 Live Preview (Settings API, `manage_options`, like the sibling plugin). Background colour (WP colour picker, hex only — sanitized on save and read, output as inline CSS) and opening width (until the user picks one; then localStorage wins). "Default layout" dropped: layouts went with side-by-side. Plugins-screen "Settings" link. Fixed on the way: `frame.css` forced the frame body to `transparent`, hiding the theme's background. e2e: `settings.spec.js` (3).
- [x] Accessibility: axe-core (WCAG 2 A/AA, 2.1 AA, best practice) finds no violations on the triggers, both modals and the settings page. Manual: triggers `aria-haspopup="dialog"`; dialogs labelled by their heading, focus starts on Close and returns to the trigger; iframes titled; status `role="status"`; pills `role="group"` + `aria-pressed`; Mail (2) uses `aria-disabled` (focusable, reason in its title) instead of `disabled`; settings descriptions linked; reduced motion respected. e2e: keyboard open → pill → Escape → focus back.
- [x] Security review. Every entry point is gated on `wpcf7_edit_contact_forms` (`manage_options` for settings): `/render`, `/mail`, `/preview/…/feedback` via REST cookie auth + nonce; the frame via its own nonce **and** capability (403 otherwise). Inputs are schema-typed (`mail` / `addons` reject unknown keys), locale and message keys pattern-checked, unit tag via CF7's sanitizer, colour via `sanitize_hex_color`. Output: PHP escaped (PHPCS `WordPress.Security` clean); admin JS writes text only (`textContent`); the frame inserts CF7's HTML unescaped — trusted, capability-gated, and accepted only from `window.parent` on the same origin; the email's HTML in an iframe sandbox without `allow-scripts`. Never sends/stores: mail via `compose( false )`, validation without `proceed()` (hooks verified silent).
  Hardened during the review: frame now sends `Content-Security-Policy: frame-ancestors 'self'` and `Referrer-Policy: same-origin` (its URL carries a nonce; theme assets on other hosts would have received it); the `wpcf7_skip_mail` backstop applied to **any** request with `_cf7lp_form`, so an anonymous visitor could suppress mail for their own real submission — now only for users who can edit forms.
- [x] `readme.txt` — Screenshots section (captions for screenshot-1…4, which go in the WordPress.org SVN `assets/` folder, not this repo), FAQ (who can use it, spam checks, theme CSS hook, add-ons), install steps, settings. `Tested up to: 7.1` in both the header and readme (WordPress.org reads major.minor; wp-env runs 7.1.3).
- [ ] Push a real tag to exercise `release.yml` end to end.
- [ ] Submit to WordPress.org.

---

## Done / no action

- Repo scaffolding: `composer.json`, `phpcs.xml`, `.wp-env.json`, `package.json`, `tests/e2e-pw/playwright.config.ts`, and `ci.yml` + `release.yml`. All present and lint/test pass locally.
- Directory layout for `includes/`, `templates/`, `assets/`, `languages/`, `tests/` exists.
- Author metadata and licence taken from the sibling plugin (`Marios Giannopoulos`, GPL-3.0-or-later).
- The DigitalNest template's `.agents/guidelines/` and `phpstan.neon` were removed — static analysis is deliberately not part of this project's tooling.
