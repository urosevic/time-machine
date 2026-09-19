/**
 * Front-end auto-refresh for Time Machine lists.
 *
 * Keeps the visible list current even when a full page cache plugin has
 * cached a page for longer than a day. Each `<ul class="time-machine-list">`
 * carrying `data-time-machine-*` attributes (added server side by
 * Content_Generator::get_refresh_attribute(), unless disabled through the
 * time_machine_frontend_refresh filter) is refetched from the REST endpoint
 * registered by Rest_Controller and swapped in.
 *
 * The cached markup already on the page is left untouched until (and
 * unless) the request succeeds, so there is never a blank or loading state.
 *
 * This file is enqueued with in_footer set to true, so by the time it runs
 * the DOM is already fully parsed and there is no need to wait for a
 * DOMContentLoaded event.
 */
( function () {

	'use strict';

	var endpoint = ( window.timeMachineRefresh && window.timeMachineRefresh.root ) || '';

	// Maps each `dataset` property (as the browser camelCases
	// `data-time-machine-*`) to the REST query arg Rest_Controller reads.
	var FIELD_MAP = [
		[ 'timeMachineMessage', 'message' ],
		[ 'timeMachinePosts', 'posts' ],
		[ 'timeMachinePrivate', 'private' ],
		[ 'timeMachineExcludePages', 'exclude_pages' ],
		[ 'timeMachineExcludeCurrent', 'exclude_current' ],
		[ 'timeMachineDisplayCommentnum', 'display_commentnum' ],
		[ 'timeMachineRange', 'range' ],
		[ 'timeMachineOffset', 'offset' ],
		[ 'timeMachineDirection', 'direction' ],
		[ 'timeMachineExcerpt', 'excerpt' ],
		[ 'timeMachineExcerptCut', 'excerpt_cut' ],
		[ 'timeMachineExcerptLength', 'excerpt_length' ]
	];

	if ( ! endpoint || 'function' !== typeof window.fetch ) {
		return;
	}

	/**
	 * Build a query string from the settings carried by a list's dataset.
	 *
	 * @param {DOMStringMap} dataset `<ul>` element's `dataset`.
	 *
	 * @return {string} Query string, without a leading "?".
	 */
	function buildQuery( dataset ) {

		var params = new URLSearchParams();
		var i;
		var field;

		for ( i = 0; i < FIELD_MAP.length; i++ ) {

			field = FIELD_MAP[ i ];

			if ( undefined !== dataset[ field[ 0 ] ] ) {
				params.append( field[ 1 ], dataset[ field[ 0 ] ] );
			}
		}

		return params.toString();
	}

	/**
	 * Refetch a single list and swap it in place of the cached one.
	 *
	 * @param {Element} list `<ul class="time-machine-list">` element.
	 *
	 * @return {void}
	 */
	function refresh( list ) {

		if ( '1' !== list.dataset.timeMachineRefresh ) {
			return;
		}

		window
			.fetch( endpoint + '?' + buildQuery( list.dataset ), { credentials: 'omit' } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {

				var wrapper;
				var fresh;

				if ( ! data || 'string' !== typeof data.html ) {
					return;
				}

				wrapper = document.createElement( 'div' );
				wrapper.innerHTML = data.html;
				fresh = wrapper.firstElementChild;

				if ( fresh && list.parentNode ) {
					list.parentNode.replaceChild( fresh, list );
				}
			} )
			.catch( function () {
				// Network or parsing error: keep whatever markup is already on the page.
			} );
	}

	var lists = document.querySelectorAll( 'ul.time-machine-list[data-time-machine-refresh]' );
	var i;

	for ( i = 0; i < lists.length; i++ ) {
		refresh( lists[ i ] );
	}
} )();
