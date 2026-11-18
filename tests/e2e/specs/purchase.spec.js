// Browse → filter → show → add tickets → checkout (Cash on Delivery) → thank-you page.
// Needs seeded data: .\scripts\encore.ps1 seed --force
import { test, expect } from '@playwright/test';

test( 'fan buys two tickets for a Dubai comedy show', async ( { page } ) => {
	// Block third parties: the test checks our site, not Google's.
	await page.route( /googletagmanager\.com/, ( route ) => route.abort() );

	// 1. Browse and filter with the GET form (works without JS).
	await page.goto( '/shows/' );
	await expect( page.getByRole( 'heading', { level: 1, name: 'All shows' } ) ).toBeVisible();
	await page.getByLabel( 'City' ).selectOption( 'dubai' );
	await page.getByLabel( 'Genre' ).selectOption( 'comedy' );
	await page.getByRole( 'button', { name: 'Apply filters' } ).click();
	await expect( page ).toHaveURL( /show_city=dubai/ );
	await expect( page.getByRole( 'status' ) ).toHaveText( '2 shows' );

	// 2. Open the Layla Haddad show from the filtered list.
	await page.getByRole( 'link', { name: 'Layla Haddad: Brunch Is a Personality' } ).click();
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveText( 'Layla Haddad: Brunch Is a Personality' );

	// 3. Choose a tier and quantity, add to cart.
	await page.getByRole( 'radio', { name: /General/ } ).check();
	await page.getByRole( 'combobox', { name: 'Tickets' } ).selectOption( '2' );
	await page.getByRole( 'button', { name: 'Add to cart' } ).click();
	await expect( page ).toHaveURL( /\/cart\/?$/ );
	await expect( page.getByText( 'Layla Haddad: Brunch Is a Personality – Tickets' ).first() ).toBeVisible();
	await expect( page.getByText( 'The Lantern Room, Dubai' ).first() ).toBeVisible();

	// 4. Checkout block.
	await page.getByRole( 'link', { name: 'Proceed to Checkout' } ).click();
	await expect( page ).toHaveURL( /\/checkout\/?/ );
	await page.getByLabel( 'Email address' ).fill( 'playwright@encore.test' );
	await page.getByLabel( 'First name' ).fill( 'Play' );
	await page.getByLabel( 'Last name' ).fill( 'Wright' );
	await page.getByLabel( 'Address', { exact: true } ).fill( 'Marina Walk' );
	await page.getByLabel( 'City' ).fill( 'Dubai' );
	await page.getByLabel( 'Attendee mobile number (UAE)' ).fill( '050 123 4567' );
	await page.getByLabel( 'Cash on delivery' ).check();
	await page.getByRole( 'button', { name: 'Place order' } ).click();

	// 5. Thank-you page with the ticket details.
	await expect( page ).toHaveURL( /order-received/, { timeout: 30_000 } );
	await expect( page.getByText( /Thank you|order has been received/i ).first() ).toBeVisible();
	await expect( page.getByText( 'Show date' ).first() ).toBeVisible();
} );
