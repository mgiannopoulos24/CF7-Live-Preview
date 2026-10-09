import { test, expect } from '@playwright/test';
import { openFormPreview, openMailPreview, routes } from './helpers';

test.describe( 'Form preview', () => {
	test( 'renders the current template, unsaved', async ( { page } ) => {
		await page.goto( routes.newForm );

		let { modal, frame } = await openFormPreview( page );
		await expect( modal ).toBeVisible();
		await expect( frame.locator( '.wpcf7 form.wpcf7-form' ) ).toBeVisible();
		// CF7's own initialisation ran on the swapped-in form.
		await expect( frame.locator( '.wpcf7' ) ).toHaveClass( /\bjs\b/ );

		await page.keyboard.press( 'Escape' );
		await expect( modal ).toBeHidden();

		await page.locator( 'textarea#wpcf7-form' ).fill(
			'[text* cf7lp-e2e placeholder "Typed in the editor"] [submit "Send it"]'
		);

		( { frame } = await openFormPreview( page ) );
		await expect( frame.locator( 'input[name="cf7lp-e2e"]' ) ).toHaveAttribute(
			'placeholder',
			'Typed in the editor'
		);
		await expect( frame.locator( 'input[type="submit"]' ) ).toHaveValue( 'Send it' );
	} );

	test( 'width pill sets and remembers the device width', async ( { page } ) => {
		await page.goto( routes.newForm );
		let { iframe } = await openFormPreview( page );

		await page.getByRole( 'button', { name: 'Mobile' } ).click();
		await expect( page.getByRole( 'button', { name: 'Mobile' } ) ).toHaveAttribute( 'aria-pressed', 'true' );
		// The width animates; wait for it to settle.
		await expect.poll( async () => ( await iframe.boundingBox() )?.width ).toBe( 375 );

		await page.reload();
		( { iframe } = await openFormPreview( page ) );
		await expect( page.getByRole( 'button', { name: 'Mobile' } ) ).toHaveAttribute( 'aria-pressed', 'true' );

		// Restore the default for the other tests.
		await page.getByRole( 'button', { name: 'Full' } ).click();
	} );

	test( 'a device wider than the modal is scaled to fit, not cropped', async ( { page } ) => {
		await page.setViewportSize( { width: 700, height: 900 } );
		await page.goto( routes.newForm );
		const { iframe } = await openFormPreview( page );

		await page.getByRole( 'button', { name: 'Tablet' } ).click();

		// The frame keeps its real 768px width, so theme media queries match…
		await expect( iframe ).toHaveCSS( 'width', '768px' );
		// …and is drawn smaller to fit the modal.
		await expect
			.poll( async () => ( await iframe.boundingBox() )?.width ?? 768 )
			.toBeLessThan( 768 );

		await page.getByRole( 'button', { name: 'Full' } ).click();
	} );

	test( 'submitting validates with CF7 but never reaches CF7\'s endpoint', async ( { page } ) => {
		await page.goto( routes.newForm );
		const { frame } = await openFormPreview( page );
		const form = frame.locator( '.wpcf7 form' );
		await expect( form ).toBeVisible();

		/** @type {string[]} */
		const requests = [];
		page.on( 'request', ( request ) => {
			if ( /contact-form-7\/v1|cf7lp\/v1\/preview/.test( decodeURIComponent( request.url() ) ) ) {
				requests.push( decodeURIComponent( request.url() ) );
			}
		} );

		// Empty: CF7's own required-field tips and response box.
		await frame.locator( 'input[type="submit"]' ).click();
		await expect( form ).toHaveClass( /\binvalid\b/ );
		await expect( frame.locator( '[data-name="your-name"] .wpcf7-not-valid-tip' ) ).toBeVisible();
		await expect( frame.locator( '.wpcf7-response-output' ) ).toContainText( /errors?/i );

		// Bad email: CF7's email rule.
		await frame.locator( 'input[name="your-name"]' ).fill( 'Ada' );
		await frame.locator( 'input[name="your-email"]' ).fill( 'not-an-email' );
		await frame.locator( 'input[name="your-subject"]' ).fill( 'Hi' );
		await frame.locator( 'input[type="submit"]' ).click();
		await expect( frame.locator( '[data-name="your-email"] .wpcf7-not-valid-tip' ) ).toBeVisible();
		await expect( frame.locator( '[data-name="your-name"] .wpcf7-not-valid-tip' ) ).toHaveCount( 0 );

		// Valid: the form's "sent" message, under the preview's own status.
		await frame.locator( 'input[name="your-email"]' ).fill( 'ada@example.com' );
		await frame.locator( 'input[type="submit"]' ).click();
		await expect( form ).toHaveClass( /custom-cf7lp-valid/ );
		await expect( frame.locator( '.wpcf7-response-output' ) ).toContainText( /thank you/i );

		expect( requests.length ).toBe( 3 );
		expect( requests.every( ( url ) => url.includes( 'cf7lp/v1/preview/contact-forms/' ) ) ).toBe( true );
	} );

	test( 'submission messages follow the Messages tab, unsaved', async ( { page } ) => {
		await page.goto( routes.newForm );
		await page.getByRole( 'tab', { name: 'Messages' } ).click();
		await page.locator( '#wpcf7-message-validation-error' ).fill( 'Please fix the highlighted fields (preview).' );
		await page.locator( '#wpcf7-message-invalid-required' ).fill( 'You missed this one.' );
		await page.getByRole( 'tab', { name: 'Form' } ).click();

		const { frame } = await openFormPreview( page );
		await expect( frame.locator( '.wpcf7 form' ) ).toBeVisible();
		await frame.locator( 'input[type="submit"]' ).click();

		await expect( frame.locator( '.wpcf7-response-output' ) ).toHaveText( 'Please fix the highlighted fields (preview).' );
		await expect( frame.locator( '[data-name="your-name"] .wpcf7-not-valid-tip' ) ).toHaveText( 'You missed this one.' );
	} );

	test( 'an unchecked required acceptance is reported', async ( { page } ) => {
		await page.goto( routes.newForm );
		await page.locator( 'textarea#wpcf7-form' ).fill( '[text your-name] [acceptance terms] I agree [/acceptance] [submit]' );

		const { frame } = await openFormPreview( page );
		await expect( frame.locator( '.wpcf7 form' ) ).toBeVisible();
		// CF7 disables submit until the box is ticked; force the submission.
		await frame.locator( '.wpcf7 form' ).evaluate( ( form ) => /** @type {HTMLFormElement} */ ( form ).requestSubmit() );

		await expect( frame.locator( '.wpcf7 form' ) ).toHaveClass( /\bunaccepted\b/ );
	} );

	test( 'a control pinned to the panel\'s corner leaves both buttons clickable', async ( { page } ) => {
		await page.goto( routes.newForm );

		// What Ultra Addons for CF7 does: an absolutely positioned button in
		// the panel's top-right corner, with its exact styles.
		await page.evaluate( () => {
			[ 'form-panel', 'mail-panel' ].forEach( ( id ) => {
				const control = document.createElement( 'button' );
				control.type = 'button';
				control.textContent = 'Addons Settings';
				control.className = 'button button-primary';
				control.style.cssText = 'position:absolute;top:30px;right:20px;padding:10px 28px';
				document.getElementById( id )?.append( control );
			} );
		} );

		/** @param {string} name */
		const onTop = ( name ) =>
			page.getByRole( 'button', { name } ).evaluate( ( button ) => {
				const r = button.getBoundingClientRect();
				return document.elementFromPoint( r.left + r.width / 2, r.top + r.height / 2 )?.closest( 'button' ) === button;
			} );

		expect( await onTop( 'Preview form' ) ).toBe( true );
		await page.getByRole( 'tab', { name: 'Mail' } ).click();
		expect( await onTop( 'Preview email' ) ).toBe( true );
	} );

	test( 'is not loaded on the contact form list', async ( { page } ) => {
		await page.goto( routes.formList );
		await expect( page.getByRole( 'button', { name: 'Preview form' } ) ).toHaveCount( 0 );
		await expect( page.locator( 'dialog.cf7lp-modal' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'Keyboard', () => {
	test( 'the form preview opens, works and closes from the keyboard', async ( { page } ) => {
		await page.goto( routes.newForm );
		const trigger = page.getByRole( 'button', { name: 'Preview form' } );
		await expect( trigger ).toHaveAttribute( 'aria-haspopup', 'dialog' );

		await trigger.focus();
		await page.keyboard.press( 'Enter' );
		const modal = page.locator( 'dialog.cf7lp-form-modal' );
		await expect( modal ).toBeVisible();
		// Focus starts on Close, inside the dialog.
		await expect( modal.getByRole( 'button', { name: 'Close' } ) ).toBeFocused();

		// The width pill is reachable and works with the keyboard.
		const mobile = modal.getByRole( 'button', { name: 'Mobile' } );
		await mobile.focus();
		await page.keyboard.press( 'Space' );
		await expect( mobile ).toHaveAttribute( 'aria-pressed', 'true' );
		await modal.getByRole( 'button', { name: 'Full' } ).focus();
		await page.keyboard.press( 'Enter' );

		// Escape closes it and focus goes back to the button that opened it.
		await page.keyboard.press( 'Escape' );
		await expect( modal ).toBeHidden();
		await expect( trigger ).toBeFocused();
	} );
} );

test.describe( 'Email preview', () => {
	test( 'composes a plain-text email with sample values', async ( { page } ) => {
		await page.goto( routes.newForm );
		await page.getByRole( 'tab', { name: 'Mail' } ).click();
		await page.locator( '#wpcf7-mail-use-html' ).uncheck();

		const modal = await openMailPreview( page );
		const body = modal.locator( '.cf7lp-mail-text' );

		await expect( body ).toContainText( 'Jane Doe' );
		await expect( body ).not.toContainText( '[your-name]' );
		await expect( modal.locator( '.cf7lp-mail-headers' ) ).toContainText( 'Subject' );

		// Editing a sample value re-composes the email.
		await modal.getByText( 'Sample values' ).click();
		await modal.locator( '#cf7lp-sample-your-name' ).fill( 'Ada Lovelace' );
		await expect( body ).toContainText( 'Ada Lovelace' );
	} );

	test( 'shows an HTML email in a sandbox without running it', async ( { page } ) => {
		await page.goto( routes.newForm );
		await page.getByRole( 'tab', { name: 'Mail' } ).click();
		await page.locator( '#wpcf7-mail-use-html' ).check();
		await page.locator( '#wpcf7-mail-body' ).fill( '<h1>Hi [your-name]</h1><script>parent.cf7lpRan = true</script>' );

		const modal = await openMailPreview( page );
		const html = modal.locator( 'iframe.cf7lp-mail-html' );

		await expect( html ).toHaveAttribute( 'sandbox', 'allow-same-origin' );
		await expect( page.frameLocator( 'iframe.cf7lp-mail-html' ).locator( 'h1' ) ).toHaveText( 'Hi Jane Doe' );
		expect( await page.evaluate( () => /** @type {any} */ ( window ).cf7lpRan ) ).toBeUndefined();
	} );

	test( 'Mail (2) is offered only when it is in use', async ( { page } ) => {
		await page.goto( routes.newForm );
		let modal = await openMailPreview( page );
		const mail2 = modal.getByRole( 'button', { name: 'Mail (2)' } );
		await expect( mail2 ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( mail2 ).toHaveAttribute( 'title', /Use Mail \(2\)/ );
		// Focusable, so the reason can be read out, but activating it does nothing.
		await mail2.focus();
		await expect( mail2 ).toBeFocused();
		await page.keyboard.press( 'Enter' );
		await expect( mail2 ).toHaveAttribute( 'aria-pressed', 'false' );
		await page.keyboard.press( 'Escape' );

		await page.locator( '#wpcf7-mail-2-active' ).check();
		modal = await openMailPreview( page );
		await modal.getByRole( 'button', { name: 'Mail (2)' } ).click();
		// The default Mail (2) is an autoresponder to the sender.
		await expect( modal.locator( '.cf7lp-mail-headers' ) ).toContainText( 'jane.doe@example.com' );
	} );
} );

test.describe( 'Preview frame access', () => {
	test( 'rejects a request without a valid nonce', async ( { page } ) => {
		const response = await page.goto( '/?cf7lp_preview=1&_wpnonce=invalid' );
		expect( response?.status() ).toBe( 403 );
	} );

	test( 'rejects logged-out visitors', async ( { browser } ) => {
		const context = await browser.newContext( { storageState: { cookies: [], origins: [] } } );
		const response = await ( await context.newPage() ).goto( '/?cf7lp_preview=1&_wpnonce=x' );
		expect( response?.status() ).toBe( 403 );
		await context.close();
	} );
} );
