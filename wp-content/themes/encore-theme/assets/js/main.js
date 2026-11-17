/**
 * Encore Theme — progressive enhancement. Vanilla JS, no jQuery.
 *
 * Everything works without this file: links still navigate, the filter
 * form is a normal GET form, and the nav is visible by default (.no-js).
 */
( () => {
	'use strict';

	const root = document.documentElement;
	root.classList.remove( 'no-js' );
	root.classList.add( 'js' );

	/**
	 * Wire a disclosure button to the element it controls (aria-expanded pattern).
	 *
	 * @param {HTMLElement} button  Toggle button.
	 * @param {HTMLElement} target  Controlled element.
	 * @param {Object}      options open/close callbacks.
	 */
	const disclosure = ( button, target, { onOpen, onClose } ) => {
		const isOpen = () => button.getAttribute( 'aria-expanded' ) === 'true';
		const set = ( open ) => {
			button.setAttribute( 'aria-expanded', String( open ) );
			( open ? onOpen : onClose )();
		};

		button.addEventListener( 'click', () => set( ! isOpen() ) );

		// Escape closes and returns focus to the button (keyboard users aren't stranded).
		document.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' && isOpen() ) {
				set( false );
				button.focus();
			}
		} );

		// A click outside closes.
		document.addEventListener( 'click', ( event ) => {
			if ( isOpen() && ! target.contains( event.target ) && ! button.contains( event.target ) ) {
				set( false );
			}
		} );

		return { close: () => isOpen() && set( false ) };
	};

	// Mobile navigation.
	const navButton = document.querySelector( '[data-nav-toggle]' );
	const nav = document.querySelector( '[data-nav]' );
	if ( navButton && nav ) {
		disclosure( navButton, nav, {
			onOpen: () => nav.classList.add( 'is-open' ),
			onClose: () => nav.classList.remove( 'is-open' ),
		} );
		// TODO(sarvagya): if the mobile menu is open and the window is resized past 900px,
		// close it (otherwise aria-expanded stays "true" on a hidden button).
		// Hint: const wide = window.matchMedia( '(min-width: 900px)' );
		// wide.addEventListener( 'change', ( e ) => { if ( e.matches ) { ... } } );
		// Keep the object returned by disclosure() and call its close().
	}

	// Shows A–Z mega-menu.
	const azButton = document.querySelector( '[data-az-toggle]' );
	const azPanel = document.querySelector( '[data-az-panel]' );
	if ( azButton && azPanel ) {
		const az = disclosure( azButton, azPanel, {
			onOpen: () => {
				azPanel.hidden = false;
			},
			onClose: () => {
				azPanel.hidden = true;
			},
		} );

		// Letter links scroll inside the panel instead of jumping the page.
		azPanel.addEventListener( 'click', ( event ) => {
			const link = event.target.closest( 'a[href^="#az-"]' );
			if ( ! link ) {
				return;
			}
			event.preventDefault();
			const group = azPanel.querySelector( link.getAttribute( 'href' ) );
			if ( group ) {
				group.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
				group.querySelector( 'a' )?.focus( { preventScroll: true } );
			}
		} );

		// Following an artist link closes the panel (matters for bfcache back navigation).
		window.addEventListener( 'pagehide', az.close );
	}

	// Sticky ticket bar: hide it while the real ticket panel is on screen.
	const bar = document.querySelector( '[data-sticky-bar]' );
	const panel = document.getElementById( 'tickets' );
	if ( bar && panel && 'IntersectionObserver' in window ) {
		new IntersectionObserver( ( [ entry ] ) => {
			bar.classList.toggle( 'is-hidden', entry.isIntersecting );
		} ).observe( panel );
	}
} )();
