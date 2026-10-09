import { execSync } from 'node:child_process';
import type { Page } from '@playwright/test';

/** wp-env's default admin account. */
export const credentials = {
	username: process.env.WP_USERNAME ?? 'admin',
	password: process.env.WP_PASSWORD ?? 'password',
};

export const routes = {
	newForm: '/wp-admin/admin.php?page=wpcf7-new',
	formList: '/wp-admin/admin.php?page=wpcf7',
} as const;

/** Open the form preview modal and return its parts. */
export async function openFormPreview( page: Page ) {
	await page.getByRole( 'button', { name: 'Preview form' } ).click();
	const modal = page.locator( 'dialog.cf7lp-form-modal' );

	return {
		modal,
		iframe: modal.locator( 'iframe.cf7lp-iframe' ),
		frame: page.frameLocator( 'dialog.cf7lp-form-modal iframe.cf7lp-iframe' ),
	};
}

/** Switch to the Mail tab and open the email preview modal. */
export async function openMailPreview( page: Page ) {
	await page.getByRole( 'tab', { name: 'Mail' } ).click();
	await page.getByRole( 'button', { name: 'Preview email' } ).click();

	return page.locator( 'dialog.cf7lp-mail-modal' );
}

/** The CF7 add-ons installed by .wp-env.json for compatibility testing. */
export const ADDONS = [
	'cf7-conditional-fields',
	'contact-form-7-multi-step-module',
	'ultimate-addons-for-contact-form-7',
];

/** Switch the compatibility add-ons on or off. */
export function setAddons( active: boolean ) {
	execSync( `bunx wp-env run cli wp plugin ${ active ? 'activate' : 'deactivate' } ${ ADDONS.join( ' ' ) }`, {
		stdio: 'ignore',
	} );
}
