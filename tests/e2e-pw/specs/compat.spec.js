import { test, expect } from '@playwright/test';
import { openFormPreview, routes, setAddons } from './helpers';

/*
 * With the add-ons from .wp-env.json active. They are off for the other
 * specs, so this file switches them on and back off.
 */
test.describe( 'With CF7 add-ons active', () => {
	test.beforeAll( async ( { browser } ) => {
		setAddons( true );

		// Add-ons redirect the first admin page after activation to their
		// own onboarding screens; load the editor until it really appears,
		// so no test starts on one of those.
		const page = await browser.newPage();
		for ( let attempt = 0; attempt < 3; attempt++ ) {
			await page.goto( routes.newForm );
			if ( await page.locator( 'textarea#wpcf7-form' ).isVisible() ) {
				break;
			}
		}
		await page.close();
	} );
	test.afterAll( () => setAddons( false ) );

	/** @type {string[]} */
	let errors = [];

	test.beforeEach( ( { page } ) => {
		errors = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	} );

	test.afterEach( () => {
		expect( errors ).toEqual( [] );
	} );

	test( 'Conditional Fields: unsaved conditions drive the preview', async ( { page } ) => {
		await page.goto( routes.newForm );
		await page.locator( 'textarea#wpcf7-form' ).fill(
			'<label>Topic [select topic "Sales" "Support"]</label>\n' +
				'[group support-only]<label>Ticket [text* ticket]</label>[/group]\n' +
				'[submit "Send"]'
		);
		await page.getByRole( 'tab', { name: 'Conditional fields' } ).click();
		// The rules are a builder UI over this textarea; text mode shows it.
		await page.locator( '#wpcf7cf-text-only-checkbox' ).check( { force: true } );
		await page.locator( '#wpcf7cf-settings-text' ).fill( 'show [support-only] if [topic] equals "Support"' );
		await page.getByRole( 'tab', { name: 'Form' } ).click();

		const { frame } = await openFormPreview( page );
		const ticket = frame.locator( 'input[name="ticket"]' );
		await expect( frame.locator( '.wpcf7 form' ) ).toBeVisible();

		// [group] became markup, and the group starts hidden.
		await expect( frame.locator( '[data-id="support-only"]' ) ).toHaveCount( 1 );
		await expect( ticket ).toBeHidden();

		// A required field in a hidden group isn't required.
		await frame.locator( 'input[type="submit"]' ).click();
		await expect( frame.locator( '.wpcf7 form' ) ).toHaveClass( /custom-cf7lp-valid/ );

		// Shown, it is.
		await frame.locator( 'select[name="topic"]' ).selectOption( 'Support' );
		await expect( ticket ).toBeVisible();
		await frame.locator( 'input[type="submit"]' ).click();
		await expect( frame.locator( '[data-name="ticket"] .wpcf7-not-valid-tip' ) ).toBeVisible();
	} );

	test( 'Conditional Fields: rules made in the builder UI apply too', async ( { page } ) => {
		await page.goto( routes.newForm );
		const editor = page.locator( 'textarea#wpcf7-form' );
		await editor.fill(
			'<label>Topic [select topic "Sales" "Support"]</label>\n' +
				'[group support-only]<label>Ticket [text ticket]</label>[/group]\n' +
				'[submit "Send"]'
		);
		// The builder lists the form's fields once the editor loses focus.
		await editor.blur();

		await page.getByRole( 'tab', { name: 'Conditional fields' } ).click();
		await page.getByText( 'add new conditional rule' ).click();
		const rule = page.locator( '#wpcf7cf-entries .entry' ).last();
		await rule.locator( '.then-field-select' ).selectOption( 'support-only' );
		await rule.locator( '.if-field-select' ).selectOption( 'topic' );
		await rule.locator( '.operator' ).selectOption( 'equals' );
		// Left focused on purpose: no "change" has synced it yet.
		await rule.locator( '.if-value' ).fill( 'Support' );

		await page.getByRole( 'tab', { name: 'Form' } ).click();
		const { frame } = await openFormPreview( page );
		await expect( frame.locator( 'input[name="ticket"]' ) ).toBeHidden();
		await frame.locator( 'select[name="topic"]' ).selectOption( 'Support' );
		await expect( frame.locator( 'input[name="ticket"]' ) ).toBeVisible();
	} );

	test( 'Multi-Step: a step previews and validates without moving on', async ( { page } ) => {
		await page.goto( routes.newForm );
		await page.locator( 'textarea#wpcf7-form' ).fill(
			'<label>Name [text* step-name]</label>\n[multistep multistep-1 first_step "/next-step/"]\n[submit "Next"]'
		);

		const { frame, iframe } = await openFormPreview( page );
		await expect( frame.locator( 'input[name="step-name"]' ) ).toBeVisible();

		await frame.locator( 'input[name="step-name"]' ).fill( 'Ada' );
		await frame.locator( 'input[type="submit"]' ).click();
		await expect( frame.locator( '.wpcf7 form' ) ).toHaveClass( /custom-cf7lp-valid/ );

		// Moving on happens on wpcf7mailsent, which the preview never fires.
		await page.waitForTimeout( 500 );
		expect( await iframe.getAttribute( 'src' ) ).toContain( 'cf7lp_preview=1' );
		await expect( frame.locator( 'input[name="step-name"]' ) ).toBeVisible();
	} );

	test( 'Ultra Addons: the default form previews and validates', async ( { page } ) => {
		await page.goto( routes.newForm );
		const { frame } = await openFormPreview( page );

		await expect( frame.locator( '.wpcf7 form' ) ).toBeVisible();
		await frame.locator( 'input[type="submit"]' ).click();
		await expect( frame.locator( '.wpcf7 form' ) ).toHaveClass( /\binvalid\b/ );
	} );
} );
