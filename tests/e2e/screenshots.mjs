// Captures the README screenshots into docs/screenshots/.
// Needs: seeded data + a waitlist run (.\scripts\smoke-waitlist.ps1) so Mailpit
// and the admin screen have content, and a sold-out "Omar Farouk" show.
// Run: cd tests/e2e; node screenshots.mjs
import { chromium, devices } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const site = process.env.ENCORE_URL || 'http://localhost:8080';
const mailpit = process.env.MAILPIT_URL || 'http://localhost:8025';
const adminPass = process.env.WP_ADMIN_PASSWORD || 'change-me-admin';
const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );
const out = path.join( root, 'docs/screenshots' );
mkdirSync( out, { recursive: true } );

const desktop = { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 };
const phone = { ...devices[ 'Pixel 7' ], deviceScaleFactor: 2 };

const browser = await chromium.launch( { channel: 'chrome' } );

async function context( options ) {
	const ctx = await browser.newContext( options );
	// The screenshots show our site, not Google's.
	await ctx.route( /googletagmanager\.com/, ( route ) => route.abort() );
	return ctx;
}

async function shot( page, name, options = {} ) {
	await page.waitForLoadState( 'networkidle' );
	await page.evaluate( () => document.fonts.ready );
	await page.screenshot( { path: path.join( out, `${ name }.png` ), ...options } );
	console.log( `saved ${ name }.png` );
}

// 1–4. Desktop: home, filtered archive, A–Z menu, show page, sold-out waitlist.
{
	const ctx = await context( desktop );
	const page = await ctx.newPage();

	await page.goto( `${ site }/` );
	await shot( page, '01-home-desktop' );

	await page.goto( `${ site }/shows/` );
	await page.getByLabel( 'City' ).selectOption( 'dubai' );
	await page.getByLabel( 'Genre' ).selectOption( 'comedy' );
	await page.getByRole( 'button', { name: 'Apply filters' } ).click();
	await page.waitForURL( /show_city=dubai/ );
	await shot( page, '03-archive-filtered' );

	await page.locator( '[data-az-toggle]' ).click();
	await page.locator( '#az-panel' ).waitFor( { state: 'visible' } );
	await shot( page, '04-az-menu' );

	await page.goto( `${ site }/shows/layla-haddad-brunch-is-a-personality/` );
	await shot( page, '05-show-desktop' );

	await page.goto( `${ site }/shows/omar-farouk-uncle-knows-best/` );
	await page.locator( '#waitlist' ).scrollIntoViewIfNeeded();
	await page.evaluate( () => window.scrollBy( 0, -120 ) );
	await shot( page, '07-sold-out-waitlist' );

	await ctx.close();
}

// 2, 6, 8, 9. Phone: home, sticky ticket bar, cart, checkout.
{
	const ctx = await context( phone );
	const page = await ctx.newPage();

	await page.goto( `${ site }/` );
	await shot( page, '02-home-phone' );

	await page.goto( `${ site }/shows/layla-haddad-brunch-is-a-personality/` );
	// The bar hides while the real ticket panel is on screen, so stay near the top.
	await page.evaluate( () => window.scrollTo( 0, 250 ) );
	await page.locator( '[data-sticky-bar]:not(.is-hidden)' ).waitFor();
	await page.waitForTimeout( 400 );
	await shot( page, '06-show-phone-sticky-bar' );

	await page.getByRole( 'radio', { name: /General/ } ).check();
	await page.getByRole( 'combobox', { name: 'Tickets' } ).selectOption( '2' );
	await page.getByRole( 'button', { name: 'Add to cart' } ).click();
	await page.waitForURL( /\/cart\/?$/ );
	await page.getByText( 'Layla Haddad: Brunch Is a Personality – Tickets' ).first().waitFor();
	await shot( page, '08-cart-phone', { fullPage: true } );

	await page.getByRole( 'link', { name: 'Proceed to Checkout' } ).click();
	await page.waitForURL( /\/checkout\/?/ );
	await page.getByLabel( 'Email address' ).fill( 'fan@encore.test' );
	await page.getByLabel( 'First name' ).fill( 'Noor' );
	await page.getByLabel( 'Last name' ).fill( 'Fan' );
	await page.getByLabel( 'Attendee mobile number (UAE)' ).fill( '050 123 4567' );
	await page.getByLabel( 'Attendee mobile number (UAE)' ).evaluate( ( el ) => el.scrollIntoView( { block: 'center' } ) );
	await page.getByLabel( 'Attendee mobile number (UAE)' ).blur();
	// Payment methods and the order summary load after the form: wait for both.
	await page.getByLabel( 'Cash on delivery' ).waitFor();
	await page.getByText( 'Layla Haddad: Brunch Is a Personality – Tickets' ).first().waitFor();
	await shot( page, '09-checkout-phone' );

	await ctx.close();
}

// 10. Mailpit: the waitlist emails.
{
	const ctx = await context( desktop );
	const page = await ctx.newPage();
	await page.goto( mailpit );
	await shot( page, '10-mailpit-inbox' );
	await page.getByText( /tickets are back/i ).first().click();
	await page.waitForTimeout( 800 );
	await shot( page, '11-email-tickets-back' );
	await ctx.close();
}

// 11. Admin: Encore → Waitlists.
{
	const ctx = await context( desktop );
	const page = await ctx.newPage();
	await page.goto( `${ site }/wp-login.php` );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( adminPass );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await page.waitForURL( /wp-admin/ );
	await page.goto( `${ site }/wp-admin/admin.php?page=encore-waitlists` );
	await shot( page, '12-admin-waitlists' );
	await ctx.close();
}

// 12. Lighthouse after-report (saved HTML, opened from disk).
{
	// Reduced motion skips the report's confetti and score count-up animations.
	const ctx = await browser.newContext( { ...desktop, reducedMotion: 'reduce' } );
	const page = await ctx.newPage();
	await page.goto( 'file:///' + path.join( root, 'docs/perf/after/home.report.html' ).replaceAll( '\\', '/' ) );
	await page.waitForTimeout( 4000 );
	// Hide the perfect-score confetti ("pyro") and the large gauge, which renders its label twice in headless Chrome; the four scores at the top say the same.
	await page.addStyleTag( { content: '.lh-pyro, .lh-pyro-before, .lh-pyro-after, .lh-category-header__description, .lh-exp-gauge-component { display: none !important; }' } );
	await shot( page, '13-lighthouse-home' );
	await ctx.close();
}

await browser.close();
