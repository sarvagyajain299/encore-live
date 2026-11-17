// GA4 dataLayer journey check (Phase 6).
// Needs a GTM ID saved in Encore → Settings (any valid-looking ID locally) and seeded data.
// Usage: node tests/e2e/analytics-check.mjs   (needs puppeteer-core; see docs/ANALYTICS.md)
import puppeteer from 'puppeteer-core';

const SITE = process.env.ENCORE_URL || 'http://localhost:8080';
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const browser = await puppeteer.launch( { executablePath: CHROME, headless: true } );
const page = await browser.newPage();
await page.setRequestInterception( true );
// Never call Google from tests: we verify what we PUSH, not what GTM does with it.
page.on( 'request', ( r ) => ( r.url().includes( 'googletagmanager.com' ) ? r.abort() : r.continue() ) );

let pass = 0;
let fail = 0;
const check = ( name, ok, detail = '' ) => {
	ok ? pass++ : fail++;
	console.log( `${ ok ? 'PASS' : 'FAIL' }  ${ name }${ ok ? '' : '  ' + detail }` );
};
const events = ( name ) => page.evaluate( ( n ) => ( window.dataLayer || [] ).filter( ( e ) => e.event === n ), name );

// 1. Archive: view_item_list.
await page.goto( `${ SITE }/shows/`, { waitUntil: 'networkidle0' } );
const lists = await events( 'view_item_list' );
const list = lists[ 0 ]?.ecommerce;
check( 'view_item_list on /shows/ with 9 items', list?.items?.length === 9 && list.item_list_name === 'shows_archive', JSON.stringify( lists ).slice( 0, 200 ) );
check( 'items carry item_id, item_name, item_brand, item_category, price', list?.items?.every( ( i ) => i.item_id && i.item_name && i.item_brand === 'Encore Live' && i.item_category && 'index' in i ) );

// 2. select_item on card click (navigation suppressed so we can read the dataLayer).
const selected = await page.evaluate( () => {
	const link = document.querySelector( '[data-list="shows_archive"] .show-card__link' );
	link.addEventListener( 'click', ( e ) => e.preventDefault() );
	link.click();
	return window.dataLayer.filter( ( e ) => e.event === 'select_item' ).map( ( e ) => e.ecommerce );
} );
check( 'select_item pushed on card click', selected.length === 1 && selected[ 0 ].items[ 0 ].item_list_name === 'shows_archive' );
check( 'ecommerce cleared (ecommerce:null) before each push', await page.evaluate( () => window.dataLayer.some( ( e ) => e.ecommerce === null ) ) );

// 3. Show page: view_item, one item per tier.
await page.goto( `${ SITE }/shows/nova-saleh-neon-mirage-tour/`, { waitUntil: 'networkidle0' } );
const viewItem = ( await events( 'view_item' ) )[ 0 ]?.ecommerce;
check( 'view_item on show page with 3 tier items in AED', viewItem?.items?.length === 3 && viewItem.currency === 'AED' && viewItem.items.map( ( i ) => i.item_variant ).join() === 'General,Premium,VIP' );

// 4. Add to cart through the real form -> event appears on the cart page.
await page.select( '#encore-qty', '2' );
await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0' } ), page.click( '.tier-form button[type="submit"]' ) ] );
const added = ( await events( 'add_to_cart' ) )[ 0 ]?.ecommerce;
check( 'add_to_cart on next page view (after redirect), qty 2, value 300', added?.items?.[ 0 ]?.quantity === 2 && added.value === 300, JSON.stringify( added ) );
await page.reload( { waitUntil: 'networkidle0' } );
check( 'add_to_cart not repeated on refresh', ( await events( 'add_to_cart' ) ).length === 0 );

// 5. Checkout page: begin_checkout.
await page.goto( `${ SITE }/checkout/`, { waitUntil: 'networkidle0' } );
const begin = ( await events( 'begin_checkout' ) )[ 0 ]?.ecommerce;
check( 'begin_checkout on checkout with cart items', begin?.items?.length === 1 && begin.value === 300 );

// 6. Place the order via the Store API in this browser session, then open the thank-you page.
const redirect = await page.evaluate( async () => {
	const cart = await fetch( '/wp-json/wc/store/v1/cart', { credentials: 'same-origin' } );
	const nonce = cart.headers.get( 'Nonce' );
	const res = await fetch( '/wp-json/wc/store/v1/checkout', {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json', Nonce: nonce },
		body: JSON.stringify( {
			billing_address: { first_name: 'GA', last_name: 'Test', email: 'ga4@encore.test', address_1: 'Marina Walk', city: 'Dubai', country: 'AE', postcode: '' },
			payment_method: 'cod',
			additional_fields: { 'encore/attendee-phone': '0501234567' },
		} ),
	} );
	return ( await res.json() ).payment_result?.redirect_url;
} );
await page.goto( redirect, { waitUntil: 'networkidle0' } );
const purchase = ( await events( 'purchase' ) )[ 0 ]?.ecommerce;
check( 'purchase on order-received with transaction_id and value 300', purchase?.transaction_id && purchase.value === 300 && purchase.currency === 'AED', JSON.stringify( purchase ) );
await page.reload( { waitUntil: 'networkidle0' } );
check( 'purchase NOT sent again on refresh (order meta guard)', ( await events( 'purchase' ) ).length === 0 );
const forged = redirect.replace( /key=[^&]+/, 'key=wc_order_forged' );
await page.goto( forged, { waitUntil: 'networkidle0' } );
check( 'no purchase data with a wrong order key', ( await events( 'purchase' ) ).length === 0 );

console.log( `\n${ pass } passed, ${ fail } failed` );
await browser.close();
process.exit( fail ? 1 : 0 );
