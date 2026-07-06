/**
 * Media Library JavaScript
 *
 * Adds offload status indicators to media library grid view
 */
(function($) {
	'use strict';
	
	// Cache for statuses
	var statusCache = {};
	
	/**
	 * Get status for an attachment (from cache only)
	 */
	function getStatus( attachmentId ) {
		return statusCache[ attachmentId ] || null;
	}
	
	/**
	 * Load statuses for multiple attachments in one batch request
	 */
	function loadStatuses( attachmentIds, callback ) {
		// Filter out IDs we already have cached
		var idsToLoad = attachmentIds.filter( function( id ) {
			return ! statusCache[ id ];
		});
		
		if ( idsToLoad.length === 0 ) {
			if ( callback ) {
				callback();
			}
			return;
		}
		
		// Load all statuses in ONE request (non-blocking)
		$.ajax({
			url: clockworkOffloaderMedia.ajaxUrl,
			type: 'POST',
			data: {
				action: 'clockwork_offloader_get_media_status',
				nonce: clockworkOffloaderMedia.nonce,
				attachment_ids: idsToLoad
			},
			success: function( response ) {
				if ( response.success && response.data ) {
					// Cache all returned statuses
					$.extend( statusCache, response.data );
				}
				
				if ( callback ) {
					callback();
				}
			}
		});
	}
	
	/**
	 * Add indicator to attachment element
	 */
	function addIndicator( $attachment, attachmentId ) {
		// Remove existing indicator
		$attachment.find( '.clockwork-offload-grid-indicator' ).remove();
		
		var status = getStatus( attachmentId );
		if ( ! status ) {
			return;
		}
		
		var indicator = wp.template( 'clockwork-offload-indicator' );
		var $indicator = $( indicator( { status: status } ) );
		$attachment.append( $indicator );
	}
	
	/**
	 * Initialize indicators for visible attachments
	 */
	function initIndicators() {
		// Collect all visible attachment IDs
		var attachmentIds = [];
		$( '#wp-media-grid .attachment' ).each( function() {
			var id = $( this ).data( 'id' );
			if ( id ) {
				attachmentIds.push( id );
			}
		});
		
		if ( attachmentIds.length === 0 ) {
			return;
		}
		
		// Load all statuses in one batch, then add indicators
		loadStatuses( attachmentIds, function() {
		$( '#wp-media-grid .attachment' ).each( function() {
			var $attachment = $( this );
			var attachmentId = $attachment.data( 'id' );
			
			if ( attachmentId ) {
				addIndicator( $attachment, attachmentId );
			}
			});
		});
	}
	
	// Wait for media grid to be ready
	$( document ).on( 'wp-media-grid-ready', function( event, frame ) {
		if ( ! clockworkOffloaderMedia ) {
			return;
		}
		
		// Initialize indicators when grid is ready
		setTimeout( initIndicators, 500 );
		
		// Update indicators when library changes
		if ( frame && frame.state ) {
			var library = frame.state().get( 'library' );
			
			if ( library ) {
				library.on( 'add remove reset update', function() {
					setTimeout( initIndicators, 100 );
				});
			}
		}
		
		// Also handle when new attachments are rendered (for infinite scroll)
		var renderTimeout;
		$( '#wp-media-grid' ).on( 'DOMNodeInserted', '.attachment', function() {
			// Debounce: wait for all new attachments to be inserted before loading statuses
			clearTimeout( renderTimeout );
			renderTimeout = setTimeout( function() {
				initIndicators();
			}, 200 );
		});
	});
	
	// Fallback: initialize on page load
	$( document ).ready( function() {
		if ( $( '#wp-media-grid' ).length ) {
			setTimeout( initIndicators, 1000 );
		}
	});
	
	// Handle attachment details view
	$( document ).on( 'click', '.attachment', function() {
		var attachmentId = $( this ).data( 'id' );
		if ( ! attachmentId ) {
			return;
		}
		
		// Update status in details sidebar if it exists
		setTimeout( function() {
			var status = getStatus( attachmentId );
			if ( status ) {
				var $details = $( '.attachment-details' );
				if ( $details.length ) {
					var $existing = $details.find( '.clockwork-offload-status-indicator' );
					if ( ! $existing.length ) {
						var indicator = wp.template( 'clockwork-offload-indicator' );
						var $indicator = $( indicator( { status: status } ) );
						$indicator.addClass( 'clockwork-offload-status-indicator' );
						$details.find( '.attachment-info' ).prepend( $indicator );
					}
				}
			}
		}, 200 );
	});
	
})( jQuery );
