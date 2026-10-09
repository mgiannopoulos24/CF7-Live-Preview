=== CF7 Live Preview ===
Contributors: mgiannopoulos24
Tags: contact form 7, cf7, preview, email preview, forms
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.3
Requires Plugins: contact-form-7
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Preview a Contact Form 7 form and its email from the editor, with your theme's styles and without saving.

== Description ==

CF7 Live Preview adds two buttons to the Contact Form 7 editor. Both work from what is in the editor right now, so you can check a change before you save it.

**Preview form** shows the form as visitors will see it, styled by your theme:

* Mobile, tablet and full-width views
* Submit it to test validation: Contact Form 7's own rules and error messages, including unsaved edits to the Messages tab
* A preview submission is only validated. Nothing is stored, no spam check runs and no email is sent.

**Preview email** shows the email the form would send:

* From, To, Subject, extra headers and attachments, then the body as plain text or HTML
* Mail and Mail (2)
* Filled with sample values you can edit, so you can try different inputs
* The message is composed but never handed to the mailer

Add-ons that change a form's markup or validation work in both previews, including unsaved Conditional Fields conditions.

The plugin is free and open source. Development happens on GitHub: https://github.com/mgiannopoulos24/CF7-Live-Preview

== Installation ==

1. Install and activate Contact Form 7.
2. In your WordPress admin go to Plugins > Add New, click "Upload Plugin" and choose the cf7-live-preview.zip file.
3. Click "Install", then "Activate".
4. Edit a contact form. "Preview form" is beside the Form heading, and "Preview email" is beside the Mail heading.
5. Optional: Settings > CF7 Live Preview sets the preview's background colour and the width it opens at.

== Frequently Asked Questions ==

= Does it require Contact Form 7? =

Yes. The plugin does nothing without Contact Form 7 and shows an admin notice until it is active.

= Does previewing send email or save anything? =

No. Neither preview saves the form. Submitting the previewed form only validates it, and the email preview composes the message without handing it to the mailer.

= Who can use the previews? =

Users who can edit contact forms in Contact Form 7 (by default, administrators and editors). Everyone else gets a "not allowed" response, including from the preview's own address.

= Are spam checks run when I submit the previewed form? =

No. reCAPTCHA, Turnstile and Akismet only check real submissions. The preview runs Contact Form 7's validation only.

= My theme styles forms only on the contact page. Why does the preview look plain? =

The preview is a page of its own, so CSS that a theme loads only on certain pages doesn't load there. Enqueue that CSS on the `cf7lp_enqueue_preview_assets` action as well:

`add_action( 'cf7lp_enqueue_preview_assets', function () { wp_enqueue_style( 'my-contact-styles' ); } );`

= Does it work with Contact Form 7 add-ons? =

Add-ons that change the form's markup or validation work in both previews. Tested with Conditional Fields for Contact Form 7 (including unsaved conditions), Webheadcoder Multi-Step Forms and Ultra Addons for Contact Form 7.

= Does it load anything from external servers? =

No. The preview loads your own site's front end, and the plugin's scripts and styles are served from the plugin folder.

== Screenshots ==

1. "Preview form" shows the form from the editor, unsaved, with the theme's styles. Switch between mobile, tablet and full width.
2. Submitting the previewed form shows Contact Form 7's own validation messages. Nothing is stored or sent.
3. "Preview email" composes the Mail or Mail (2) template with sample values you can edit.
4. Settings > CF7 Live Preview: the preview's background colour and the width it opens at.

== Changelog ==

= 1.0.0 =
* Initial release.
* Form preview: the form from the editor, unsaved, styled by the theme, at mobile, tablet or full width.
* Validation testing: submit the previewed form to see Contact Form 7's own validation messages, including unsaved Messages-tab edits. Nothing is stored or sent.
* Email preview: Mail or Mail (2) composed with editable sample values. Nothing is sent.
* Works with add-ons that change a form's markup or validation, including unsaved Conditional Fields conditions.
* Settings: background colour of the form preview and the width it opens at.

== Copyright ==

CF7 Live Preview WordPress Plugin, (C) 2026 Marios Giannopoulos
CF7 Live Preview is distributed under the terms of the GNU GPL v3 or later.
