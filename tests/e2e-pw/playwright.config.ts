import { defineConfig } from '@playwright/test';
import path from 'path';

const WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

const STORAGE_STATE = path.resolve( __dirname, '.auth/admin.json' );

export default defineConfig( {
	globalSetup: require.resolve( './global-setup' ),
	testDir: './tests',
	fullyParallel: false,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI
		? [ [ 'github' ], [ 'html', { outputFolder: 'playwright-report' } ] ]
		: [ [ 'list' ], [ 'html', { outputFolder: 'playwright-report' } ] ],
	use: {
		baseURL: WP_BASE_URL,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'auth',
			testDir: '.',
			testMatch: /auth\.setup\.ts/,
		},
		{
			name: 'chromium',
			dependencies: [ 'auth' ],
			use: {
				browserName: 'chromium',
				storageState: STORAGE_STATE,
				launchOptions: {
					args: [ '--disable-dev-shm-usage' ],
				},
			},
		},
	],
} );