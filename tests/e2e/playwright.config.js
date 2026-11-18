// Playwright config: one smoke test of the purchase journey on a phone-sized viewport.
import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: './specs',
	testMatch: '*.spec.js',
	timeout: 90_000,
	retries: 0,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.ENCORE_URL || 'http://localhost:8080',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'mobile-chrome',
			// Installed Chrome via channel: no 150 MB browser download.
			use: { ...devices[ 'Pixel 7' ], channel: 'chrome' },
		},
	],
} );
