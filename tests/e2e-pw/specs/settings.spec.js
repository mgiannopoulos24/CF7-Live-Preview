import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { openFormPreview, routes } from './helpers';

const resetSettings = () =>
	execSync( 'bunx wp-env run cli wp option delete cf7_live_preview_options', { stdio: 'ignore' } );

test.describe( 'Settings', () => {
	test.beforeAll( resetSettings );
	test.afterAll( resetSettings );

	test( 'background colour and opening width apply to the preview', async ( { page } ) => {
		await page.goto( '/wp-admin/options-general.php?page=cf7-live-preview' );
		await expect( page.getByRole( 'heading', { name: 'CF7 Live Preview' } ) ).toBeVisible();

		// WordPress's colour picker shows the text field once opened.
		await page.getByRole( 'button', { name: 'Select Color' } ).click();
		await page.locator( '#cf7lp-background-color' ).fill( '#ffeedd' );
		await page.getByLabel( 'Opens at' ).selectOption( 'tablet' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.locator( '#setting-error-settings_updated' ) ).toBeVisible();
		await expect( page.locator( '#cf7lp-background-color' ) ).toHaveValue( '#ffeedd' );

		// No width remembered in this browser, so the setting decides.
		await page.goto( routes.newForm );
		await page.evaluate( () => window.localStorage.removeItem( 'cf7lp-preferences' ) );
		await page.reload();

		const { frame } = await openFormPreview( page );
		await expect( page.getByRole( 'button', { name: 'Tablet' } ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( frame.locator( 'body' ) ).toHaveCSS( 'background-color', 'rgb(255, 238, 221)' );
	} );

	test( 'rejects a colour that is not hex', async ( { page } ) => {
		await page.goto( '/wp-admin/options-general.php?page=cf7-live-preview' );
		await page.locator( '#cf7lp-background-color' ).evaluate( ( input ) => {
			/** @type {HTMLInputElement} */ ( input ).removeAttribute( 'pattern' );
			/** @type {HTMLInputElement} */ ( input ).value = 'red; } body { display: none';
		} );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.locator( '#cf7lp-background-color' ) ).toHaveValue( '' );
	} );

	test( 'linked from the Plugins screen', async ( { page } ) => {
		await page.goto( '/wp-admin/plugins.php' );
		await expect(
			page.locator( 'tr[data-slug="cf7-live-preview"] a', { hasText: 'Settings' } )
		).toHaveAttribute( 'href', /options-general\.php\?page=cf7-live-preview/ );
	} );
} );
