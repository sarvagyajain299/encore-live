/**
 * GA4 list events from rendered show cards: view_item_list (one per list on
 * the page) and select_item (on click). Item data comes from
 * window.encoreAnalytics, printed by the server for every card on the page.
 */
( () => {
	'use strict';

	const config = window.encoreAnalytics;
	if ( ! config || ! window.dataLayer ) {
		return;
	}

	const push = ( event, items, listName ) => {
		window.dataLayer.push( { ecommerce: null } );
		window.dataLayer.push( {
			event,
			ecommerce: { item_list_id: listName, item_list_name: listName, items },
		} );
	};

	const itemFor = ( card ) => {
		const base = config.items[ card.dataset.showId ];
		if ( ! base ) {
			return null;
		}
		return { ...base, item_list_name: card.dataset.list, index: Number( card.dataset.index || 0 ) };
	};

	// view_item_list: group rendered cards by their data-list name.
	const lists = new Map();
	document.querySelectorAll( '[data-show-id][data-list]' ).forEach( ( card ) => {
		const name = card.dataset.list;
		const item = name && itemFor( card );
		if ( item ) {
			lists.set( name, [ ...( lists.get( name ) || [] ), item ] );
		}
	} );
	lists.forEach( ( items, name ) => push( 'view_item_list', items, name ) );

	// select_item: the card's link is clicked (also middle-click / keyboard via "click").
	document.addEventListener( 'click', ( event ) => {
		const card = event.target.closest( '[data-show-id][data-list]' );
		if ( ! card || ! event.target.closest( 'a' ) ) {
			return;
		}
		const item = itemFor( card );
		if ( item ) {
			push( 'select_item', [ item ], card.dataset.list );
		}
	} );
} )();
