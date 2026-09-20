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
				confirmMessage = 'WARNING: This file is NOT on the server. Deleting from Cloud will permanently delete this file. Are you absolutely sure you want to continue?';
			} else {
				// File is on server - regular warning
				confirmMessage = 'Are you sure you want to delete this file from Cloud? The file will remain on the server.';
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
	
	// Show notice function - modern floating toast notification
	function showNotice(message, type, duration) {
		if (typeof window.clockworkShowToast === 'function') {
			window.clockworkShowToast(message, type, duration);
			return;
		}

		type = type || 'info';
		duration = typeof duration !== 'undefined' ? duration : (type === 'error' ? 6000 : 4000);
		
		// Get or create toast container
		var $container = $('#clockwork-toast-container');
		if (!$container.length) {
			$container = $('<div id="clockwork-toast-container" class="clockwork-toast-container" aria-live="polite" aria-atomic="true"></div>');
			$('body').append($container);
		}
		
		// Icon mapping
		var iconSvg = '';
		if (type === 'success') {
			iconSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.172 7.707 8.879a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
		} else if (type === 'error') {
			iconSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
		} else if (type === 'warning') {
			iconSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
		} else {
			iconSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>';
		}
		
		// Escape HTML in message
		var escapedMessage = $('<div>').text(message).html();
		
		var $toast = $(
			'<div class="clockwork-toast clockwork-toast-' + type + '" role="alert">' +
				'<div class="clockwork-toast-icon-wrap">' + iconSvg + '</div>' +
				'<div class="clockwork-toast-content">' +
					'<div class="clockwork-toast-message">' + escapedMessage + '</div>' +
				'</div>' +
				'<button type="button" class="clockwork-toast-close" aria-label="Dismiss">&times;</button>' +
			'</div>'
		);
		
		$container.append($toast);
		
		// Dismiss function
		var isDismissing = false;
		function dismiss() {
			if (isDismissing) return;
			isDismissing = true;
			$toast.addClass('clockwork-toast-hiding');
			setTimeout(function() {
				$toast.slideUp(180, function() {
					$toast.remove();
				});
			}, 200);
		}
		
		// Auto-dismiss timer with pause on hover
		var dismissTimer = null;
		function startDismissTimer() {
			if (duration > 0) {
				dismissTimer = setTimeout(dismiss, duration);
			}
		}
		
		$toast.on('mouseenter', function() {
			if (dismissTimer) clearTimeout(dismissTimer);
		}).on('mouseleave', function() {
			startDismissTimer();
		});
		
		$toast.find('.clockwork-toast-close').on('click', function(e) {
			e.preventDefault();
			if (dismissTimer) clearTimeout(dismissTimer);
			dismiss();
		});
		
		startDismissTimer();
	}
	
})(jQuery);

