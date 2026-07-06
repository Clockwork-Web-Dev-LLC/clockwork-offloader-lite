/**
 * Attachment Details JavaScript
 *
 * Handles CDN controls in attachment details modal
 */
(function($) {
	'use strict';
	
	// Handle action buttons
	$(document).on('click', '.clockwork-action-btn', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var action = $button.data('action');
		var attachmentId = $button.data('attachment-id');
		var $controls = $button.closest('.clockwork-offloader-attachment-controls');
		var $spinner = $controls.find('.clockwork-action-spinner');
		
		// Confirm delete action with appropriate warning
		if (action === 'delete-from-cdn') {
			var onServer = $button.data('on-server') === 1 || $button.data('on-server') === '1';
			var confirmMessage;
			
			if (!onServer) {
				// File is not on server - show severe warning
				confirmMessage = 'WARNING: This file is NOT on the server. Deleting from CDN will permanently delete this file. Are you absolutely sure you want to continue?';
			} else {
				// File is on server - regular warning
				confirmMessage = 'Are you sure you want to delete this file from CDN? The file will remain on the server.';
			}
			
			if (!confirm(confirmMessage)) {
				return;
			}
		}
		
		// Disable all buttons
		$controls.find('.clockwork-action-btn').prop('disabled', true);
		$spinner.show();
		
		var ajaxAction = '';
		switch(action) {
			case 'offload':
				ajaxAction = 'clockwork_offloader_bulk_offload';
				break;
			case 'delete-from-cdn':
				ajaxAction = 'clockwork_offloader_delete_from_cdn';
				break;
			case 'restore':
				ajaxAction = 'clockwork_offloader_restore';
				break;
		}
		
		$.ajax({
			url: clockworkOffloaderAttachment.ajaxUrl,
			type: 'POST',
			data: {
				action: ajaxAction,
				nonce: clockworkOffloaderAttachment.nonce,
				attachment_id: attachmentId
			},
			success: function(response) {
				if (response.success) {
					var message = '';
					switch(action) {
						case 'offload':
							message = clockworkOffloaderAttachment.strings.offloadSuccess;
							break;
						case 'delete-from-cdn':
							message = clockworkOffloaderAttachment.strings.deleteSuccess;
							break;
						case 'restore':
							message = clockworkOffloaderAttachment.strings.restoreSuccess;
							break;
					}
					
					// Show success notice
					showNotice(message, 'success');
					
					// Reload the attachment details to update icons and buttons
					setTimeout(function() {
						// Reload the entire attachment details panel
						refreshAttachmentDetails(attachmentId, $controls);
					}, 500);
				} else {
					var errorMsg = response.data && response.data.message 
						? response.data.message 
						: clockworkOffloaderAttachment.strings.offloadError;
					
					switch(action) {
						case 'offload':
							errorMsg = response.data && response.data.message 
								? response.data.message 
								: clockworkOffloaderAttachment.strings.offloadError;
							break;
						case 'delete-from-cdn':
							errorMsg = response.data && response.data.message 
								? response.data.message 
								: clockworkOffloaderAttachment.strings.deleteError;
							break;
						case 'restore':
							errorMsg = response.data && response.data.message 
								? response.data.message 
								: clockworkOffloaderAttachment.strings.restoreError;
							break;
					}
					
					showNotice(errorMsg, 'error');
					$controls.find('.clockwork-action-btn').prop('disabled', false);
				}
			},
			error: function() {
				var errorMsg = '';
				switch(action) {
					case 'offload':
						errorMsg = clockworkOffloaderAttachment.strings.offloadError;
						break;
					case 'delete-from-cdn':
						errorMsg = clockworkOffloaderAttachment.strings.deleteError;
						break;
					case 'restore':
						errorMsg = clockworkOffloaderAttachment.strings.restoreError;
						break;
				}
				showNotice(errorMsg, 'error');
				$controls.find('.clockwork-action-btn').prop('disabled', false);
			},
			complete: function() {
				$spinner.hide();
			}
		});
	});
	
	// Copy S3 URL to clipboard
	$(document).on('click', '.copy-s3-url', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var url = $button.data('url');
		
		// Copy to clipboard
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(function() {
				$button.text('Copied!').addClass('button-primary');
				setTimeout(function() {
					$button.text(clockworkOffloaderAttachment.strings.copyUrl || 'Copy CDN URL').removeClass('button-primary');
				}, 2000);
			});
		} else {
			// Fallback for older browsers
			var $input = $button.siblings('input');
			$input.select();
			document.execCommand('copy');
			$button.text('Copied!').addClass('button-primary');
			setTimeout(function() {
				$button.text(clockworkOffloaderAttachment.strings.copyUrl || 'Copy CDN URL').removeClass('button-primary');
			}, 2000);
		}
	});
	
	// Refresh attachment details after action
	function refreshAttachmentDetails(attachmentId, $controls) {
		// Get updated status via AJAX
		$.ajax({
			url: clockworkOffloaderAttachment.ajaxUrl,
			type: 'POST',
			data: {
				action: 'clockwork_offloader_get_media_status',
				nonce: clockworkOffloaderAttachment.nonce,
				attachment_ids: [attachmentId]
			},
			success: function(response) {
				if (response.success && response.data[attachmentId]) {
					var status = response.data[attachmentId];
					
					// Update status icons immediately
					var $statusIcons = $controls.find('.clockwork-status-icons');
					$statusIcons.find('.fa-cloud').css('color', status.cdn_color);
					$statusIcons.find('.fa-computer').css('color', status.server_color);
					
					// Update status text
					$controls.find('.clockwork-status-text').text(status.label);
					
					// Reload the entire controls section by reloading the attachment
					// This ensures buttons are updated correctly
					if (wp && wp.media && wp.media.frame) {
						var frame = wp.media.frame;
						if (frame && frame.content && frame.content.get) {
							var selection = frame.state().get('selection');
							if (selection && selection.length > 0) {
								var attachment = selection.first();
								if (attachment && attachment.id == attachmentId) {
									// Refresh the attachment model to get updated data
									attachment.fetch().done(function() {
										// Trigger refresh of attachment details view
										if (frame.content.get('details')) {
											frame.content.get('details').render();
										}
									});
								}
							}
						}
					}
					
					// Also reload page after a delay to ensure everything is updated
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					// Fallback: reload page
					location.reload();
				}
			},
			error: function() {
				// Fallback: reload page
				location.reload();
			}
		});
	}
	
	// Show notice function
	function showNotice(message, type) {
		var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + $('<div>').text(message).html() + '</p></div>');
		
		// Try to find the media modal or use body
		var $target = $('.media-modal-content').length ? $('.media-modal-content') : $('body');
		$target.prepend($notice);
		
		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			$notice.fadeOut(function() {
				$notice.remove();
			});
		}, 5000);
	}
	
})(jQuery);

