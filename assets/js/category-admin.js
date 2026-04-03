/**
 * Category management with WP REST API, drag-and-drop reorder, and visual feedback.
 */
( function ( $ ) {
	'use strict';

	var debounceTimers = {};
	var restUrl = sbdCategories.restUrl;
	var nonce = sbdCategories.nonce;

	/**
	 * Show a brief status indicator on a row.
	 */
	function showStatus( $row, text, type ) {
		var $status = $row.find( '.sbd-cat-status' );
		$status
			.text( text )
			.removeClass( 'sbd-saved sbd-error' )
			.addClass( type === 'error' ? 'sbd-error' : 'sbd-saved' )
			.stop( true )
			.css( 'opacity', 1 )
			.delay( 1500 )
			.animate( { opacity: 0 }, 400 );
	}

	/**
	 * Make a REST request.
	 */
	function restRequest( method, url, data ) {
		return $.ajax( {
			url: url,
			method: method,
			data: data ? JSON.stringify( data ) : undefined,
			contentType: 'application/json',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', nonce );
			}
		} );
	}

	/**
	 * Save a single category row (create or update).
	 */
	function saveRow( $row ) {
		var id = $row.data( 'id' ) || '';
		var name = $row.find( '.sbd-cat-name' ).val().trim();
		var overdue = parseInt( $row.find( '.sbd-cat-overdue' ).val(), 10 ) || 48;

		if ( ! name ) {
			return;
		}

		var data = {
			name: name,
			overdue_hours: overdue
		};

		if ( id ) {
			data.id = id;
		}

		restRequest( 'POST', restUrl, data )
			.done( function ( response ) {
				var newId = response && response.id ? response.id : '';
				if ( ! $row.data( 'id' ) && newId ) {
					$row.data( 'id', newId );
					$row.attr( 'data-id', newId );
				}
				showStatus( $row, '\u2713', 'success' );
			} )
			.fail( function () {
				showStatus( $row, '\u2717', 'error' );
			} );
	}

	/**
	 * Debounced save — triggers 600ms after last input.
	 */
	function debounceSave( $row ) {
		var key = $row.data( 'id' ) || 'new-' + $row.index();
		clearTimeout( debounceTimers[ key ] );
		debounceTimers[ key ] = setTimeout( function () {
			saveRow( $row );
		}, 600 );
	}

	/**
	 * Save sort order after drag-and-drop.
	 */
	function saveOrder() {
		var order = [];
		$( '#sbd-categories-body .sbd-cat-row' ).each( function () {
			var id = $( this ).data( 'id' );
			if ( id ) {
				order.push( id );
			}
		} );

		restRequest( 'POST', restUrl + '/reorder', { order: order } );
	}

	/**
	 * Create a new empty row.
	 */
	function addRow() {
		var $row = $( '<tr class="sbd-cat-row" data-id="">' +
			'<td class="sbd-drag-handle" style="cursor:move">&#9776;</td>' +
			'<td><input type="text" class="sbd-cat-name regular-text" value="" placeholder="Category name\u2026" /></td>' +
			'<td><input type="number" class="sbd-cat-overdue" value="48" min="1" style="width:80px" /></td>' +
			'<td>' +
				'<button type="button" class="button-link sbd-cat-delete" style="color:#b32d2e">Delete</button>' +
				'<span class="sbd-cat-status"></span>' +
			'</td>' +
			'</tr>' );

		$( '#sbd-categories-body' ).append( $row );
		$row.find( '.sbd-cat-name' ).trigger( 'focus' );
	}

	// Init sortable.
	$( '#sbd-categories-body' ).sortable( {
		handle: '.sbd-drag-handle',
		axis: 'y',
		update: function () {
			saveOrder();
		}
	} );

	// Auto-save on input change (debounced).
	$( '#sbd-categories-table' ).on( 'input', '.sbd-cat-name, .sbd-cat-overdue', function () {
		debounceSave( $( this ).closest( '.sbd-cat-row' ) );
	} );

	// Delete button.
	$( '#sbd-categories-table' ).on( 'click', '.sbd-cat-delete', function () {
		var $row = $( this ).closest( '.sbd-cat-row' );
		var id = $row.data( 'id' );

		if ( ! id ) {
			$row.remove();
			return;
		}

		if ( ! window.confirm( 'Delete this category? Sites using it will become uncategorized.' ) ) {
			return;
		}

		restRequest( 'DELETE', restUrl + '/' + id )
			.done( function () {
				$row.fadeOut( 300, function () {
					$row.remove();
				} );
			} )
			.fail( function () {
				showStatus( $row, '\u2717', 'error' );
			} );
	} );

	// Add button.
	$( '#sbd-add-category' ).on( 'click', addRow );

} )( jQuery );
