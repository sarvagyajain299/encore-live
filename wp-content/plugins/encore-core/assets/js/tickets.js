/**
 * Tier selector enhancement: keep the quantity list within the chosen
 * tier's limit (remaining stock or per-order max, whichever is lower).
 * Server-side validation is still the real gate; this only prevents
 * a pointless round trip.
 */
( () => {
	'use strict';

	document.querySelectorAll( '[data-tier-form]' ).forEach( ( form ) => {
		const qty = form.querySelector( '[data-qty]' );
		if ( ! qty ) {
			return;
		}

		const sync = () => {
			const tier = form.querySelector( 'input[name="variation_id"]:checked' );
			const max = tier ? Number( tier.dataset.max ) : 0;
			Array.from( qty.options ).forEach( ( option ) => {
				option.disabled = Number( option.value ) > max;
				option.hidden = option.disabled;
			} );
			if ( Number( qty.value ) > max ) {
				qty.value = String( Math.max( 1, max ) );
			}
		};

		form.addEventListener( 'change', ( event ) => {
			if ( event.target.name === 'variation_id' ) {
				sync();
			}
		} );
		sync();
	} );
} )();
