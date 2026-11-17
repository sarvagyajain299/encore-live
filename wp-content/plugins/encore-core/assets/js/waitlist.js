/**
 * Waitlist form: progressive enhancement from a POST form to fetch() + REST.
 * Without JS the form still posts to admin-post.php and redirects back.
 */
( () => {
	'use strict';

	document.querySelectorAll( '[data-waitlist-form]' ).forEach( ( form ) => {
		const message = form.querySelector( '[data-waitlist-message]' );
		const button = form.querySelector( 'button[type="submit"]' );

		const clearErrors = () => {
			form.querySelectorAll( '[aria-invalid="true"]' ).forEach( ( el ) => el.removeAttribute( 'aria-invalid' ) );
			form.querySelectorAll( '.field__error' ).forEach( ( el ) => el.remove() );
		};

		const showErrors = ( errors ) => {
			let first = null;
			Object.entries( errors || {} ).forEach( ( [ name, text ] ) => {
				const input = form.querySelector( `[name="${ name }"]` );
				if ( ! input ) {
					return;
				}
				const note = document.createElement( 'span' );
				note.className = 'field__error';
				note.id = `${ input.id }-error`;
				note.textContent = text;
				input.setAttribute( 'aria-invalid', 'true' );
				input.setAttribute( 'aria-describedby', note.id );
				input.closest( '.field' )?.append( note );
				first = first || input;
			} );
			first?.focus();
		};

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			clearErrors();
			button.disabled = true;
			message.textContent = '';

			const data = Object.fromEntries( new FormData( form ) );
			data.consent = form.elements.consent.checked;

			try {
				const response = await fetch( form.dataset.endpoint, {
					method: 'POST',
					// X-WP-Nonce keeps a logged-in visitor logged in for the REST request;
					// without it WordPress treats the call as anonymous and our form nonce
					// (created for that user) would not verify.
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': form.dataset.restNonce },
					credentials: 'same-origin',
					body: JSON.stringify( data ),
				} );
				const body = await response.json();
				message.textContent = body.message || '';
				if ( response.ok ) {
					form.reset();
				} else {
					showErrors( body.errors );
				}
			} catch ( error ) {
				// Network failure: fall back to a normal form submit.
				form.submit();
			} finally {
				button.disabled = false;
			}
		} );
	} );
} )();
