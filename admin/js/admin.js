/**
 * Clockwork Offloader Admin JavaScript
 */
(function($) {
	'use strict';
	
	$(document).ready(function() {
		// Hoist any external admin notices out of the header banner to above it
		$('.clockwork-offloader-header').find('.notice, div.updated, div.error').each(function() {
			$(this).insertBefore('.clockwork-offloader-header');
		});

		// Safety check: ensure clockworkOffloader is defined
		if (typeof clockworkOffloader === 'undefined') {
			console.error('clockworkOffloader is not defined! Make sure the script is properly enqueued.');
			return;
		}
		
		console.log('clockworkOffloader object:', {
			ajaxUrl: clockworkOffloader.ajaxUrl,
			nonce: clockworkOffloader.nonce ? 'defined' : 'missing'
		});
		
		// Debug: Check if URL rewrite buttons exist
		if ($('#clockwork-force-s3-urls').length > 0) {
			console.log('Force S3 URLs button found');
		}
		if ($('#clockwork-switch-back-local').length > 0) {
			console.log('Switch Back to Local URLs button found');
		} else {
			console.warn('Switch Back to Local URLs button NOT found');
		}
		
		var $bucketSelect = $('#s3_bucket');
		var $bucketText = $('#s3_bucket_text');
		var $loadBucketsBtn = $('#load-buckets');
		var $bucketSpinner = $('#bucket-spinner');
		
		// Disable Load Buckets button if bucket field is disabled (defined in wp-config.php)
		if ($bucketText.length && $bucketText.prop('disabled')) {
			$loadBucketsBtn.prop('disabled', true);
		}
		
		// Load buckets from AWS
		$loadBucketsBtn.on('click', function(e) {
			e.preventDefault();
			
			var accessKey = $('#s3_access_key').val();
			var secretKey = $('#s3_secret_key').val();
			var region = $('#s3_region').val() || 'us-east-1';
			
			if (!accessKey || !secretKey) {
				showNotice('Please enter Access Key and Secret Key first.', 'error');
				return;
			}
			
			$loadBucketsBtn.prop('disabled', true);
			$bucketSpinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_list_buckets',
					nonce: clockworkOffloader.nonce,
					access_key: accessKey,
					secret_key: secretKey,
					region: region
				},
				success: function(response) {
					if (response.success && response.data.buckets) {
						var buckets = response.data.buckets;
						
						if (buckets.length === 0) {
							showNotice('No buckets found in your AWS account.', 'warning');
							$bucketSelect.hide();
							$bucketText.show();
						} else {
							// Populate dropdown
							$bucketSelect.empty();
							$bucketSelect.append('<option value="">' + 'Select a bucket...' + '</option>');
							
							$.each(buckets, function(index, bucket) {
								var selected = bucket === $bucketText.val() ? ' selected' : '';
								$bucketSelect.append('<option value="' + $('<div>').text(bucket).html() + '"' + selected + '>' + $('<div>').text(bucket).html() + '</option>');
							});
							
							// Show dropdown, hide text input
							$bucketText.hide();
							$bucketSelect.show();
							
							// Sync values - update text input when dropdown changes
							$bucketSelect.off('change.bucketSync').on('change.bucketSync', function() {
								$bucketText.val($(this).val());
							});
							
							// If there's a saved value, select it
							if ($bucketText.val()) {
								$bucketSelect.val($bucketText.val());
							}
							
							showNotice('Loaded ' + buckets.length + ' bucket(s) successfully.', 'success');
						}
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to load buckets.';
						showNotice(errorMsg, 'error');
					}
				},
				error: function() {
					showNotice('An error occurred while loading buckets.', 'error');
				},
				complete: function() {
					$loadBucketsBtn.prop('disabled', false);
					$bucketSpinner.removeClass('is-active');
				}
			});
		});
		
		// Sync text input to dropdown when typing manually
		$bucketText.on('input', function() {
			if ($bucketSelect.is(':visible')) {
				$bucketSelect.val($(this).val());
			}
		});
		
		// Allow manual entry by showing text input
		$bucketText.on('focus', function() {
			if ($bucketSelect.is(':visible')) {
				$bucketSelect.hide();
				$bucketText.show();
			}
		});
		
		// Auto-load buckets when secret key is entered and user moves away
		$('#s3_secret_key').on('blur', function() {
			var accessKey = $('#s3_access_key').val();
			var secretKey = $(this).val();
			
			// Only auto-load if both fields have values and bucket is empty
			if (accessKey && secretKey && !$bucketText.val() && !$loadBucketsBtn.prop('disabled')) {
				// Small delay to avoid triggering on tab navigation
				setTimeout(function() {
					if ($('#s3_secret_key').is(':focus') === false) {
						$loadBucketsBtn.trigger('click');
					}
				}, 500);
			}
		});
		
		// Enable/disable throttle rate field based on throttle toggle
		$('#enable_throttle').on('change', function() {
			var $throttleField = $('#throttle_rate_field');
			var $throttleRate = $('#throttle_rate');
			var $throttleWrapper = $throttleRate.closest('.throttle-rate-wrapper');
			
			if ($(this).is(':checked')) {
				$throttleField.slideDown(150);
				$throttleRate.prop('disabled', false);
				$throttleWrapper.css('opacity', '1');
				// Set default to 100 if empty or 0
				var currentValue = $throttleRate.val();
				if (!currentValue || currentValue === '0' || currentValue === '') {
					$throttleRate.val('100');
				}
			} else {
				$throttleField.slideUp(150);
				$throttleRate.prop('disabled', true);
				$throttleWrapper.css('opacity', '0.5');
			}
		});
		
		// Trigger on page load to set initial state
		if ($('#enable_throttle').length) {
			$('#enable_throttle').trigger('change');
		}

		// Toggle Storage Credentials Drawer
		$('#toggle-storage-creds').on('click', function(e) {
			e.preventDefault();
			var $drawer = $('#storage-creds-drawer');
			$drawer.slideToggle(200);
			var isVisible = $drawer.is(':visible');
			$(this).text(isVisible ? 'Close' : 'Edit');
		});

		// Toggle Delivery Drawer
		$('#toggle-delivery-drawer').on('click', function(e) {
			e.preventDefault();
			var $drawer = $('#delivery-creds-drawer');
			$drawer.slideToggle(200);
			var isVisible = $drawer.is(':visible');
			$(this).text(isVisible ? 'Close' : 'Edit');
		});

		// Prefix Toggle
		$('#enable_bucket_prefix').on('change', function() {
			var $prefixField = $('#bucket_prefix_field');
			if ($(this).is(':checked')) {
				$prefixField.slideDown(150);
				if (!$('#s3_base_path').val()) {
					$('#s3_base_path').val('wp-content/uploads/').trigger('input');
				}
			} else {
				$prefixField.slideUp(150);
				$('#s3_base_path').val('').trigger('input');
			}
			updateLiveUrlPreview();
		});

		// Dynamic URL Preview Updates
		function updateLiveUrlPreview() {
			if (!$('#clockwork-live-url-preview').length) return;
			var cdn = ($('#cdn_domain').val() || '').trim();
			var bucket = ($('#s3_bucket_text').val() || '').trim() || 'example-bucket';
			var region = $('#s3_region').val() || 'us-east-1';
			var prefix = $('#enable_bucket_prefix').is(':checked') ? ($('#s3_base_path').val() || '').trim() : '';
			if (prefix && prefix.substr(-1) !== '/') prefix += '/';
			if (prefix && prefix.charAt(0) === '/') prefix = prefix.substr(1);
			var sampleKey = (prefix || 'wp-content/uploads/') + '2026/09/sample-image.jpg';

			var url = '';
			if (cdn) {
				url = cdn.replace(/\/+$/, '') + '/' + sampleKey;
			} else {
				if (bucket.indexOf('.') !== -1) {
					url = 'https://s3.' + region + '.amazonaws.com/' + bucket + '/' + sampleKey;
				} else {
					url = 'https://' + bucket + '.s3.' + region + '.amazonaws.com/' + sampleKey;
				}
			}
			$('#clockwork-live-url-preview').text(url);
		}

		$('#cdn_domain, #s3_bucket_text, #s3_region, #s3_base_path').on('input change', updateLiveUrlPreview);
		
		// Before form submission, ensure the correct field value is used
		$('form').on('submit', function() {
			if ($bucketSelect.is(':visible') && $bucketSelect.val()) {
				$bucketText.val($bucketSelect.val());
			}
		});
		
		// Test connection (works on both Settings and Dashboard pages)
		$('#test-connection').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $status = $('#connection-status');
			
			// Get values from form (if on settings page)
			// If form fields don't exist (dashboard page) or are disabled (wp-config.php), send empty values and let server check wp-config.php
			var $accessKeyField = $('#s3_access_key');
			var $secretKeyField = $('#s3_secret_key');
			var $regionField = $('#s3_region');
			
			var accessKey = $accessKeyField.length ? $accessKeyField.val() : '';
			var secretKey = $secretKeyField.length ? $secretKeyField.val() : '';
			var bucket = '';
			if ($bucketSelect.length && $bucketSelect.is(':visible')) {
				bucket = $bucketSelect.val();
			} else if ($bucketText.length) {
				bucket = $bucketText.val();
			}
			var region = $regionField.length ? $regionField.val() : '';
			
			// Check if fields are disabled (defined in wp-config.php)
			var usingWpConfig = false;
			if ($accessKeyField.length && $accessKeyField.prop('disabled')) {
				usingWpConfig = true;
			}
			
			// Only validate if we're on the settings page AND fields are NOT disabled (not using wp-config.php)
			// If fields are disabled or on dashboard, let server check wp-config.php constants
			if ($accessKeyField.length && !usingWpConfig) {
				// We're on settings page with editable fields - validate form fields
				if (!accessKey || !secretKey) {
					showNotice('Please enter Access Key and Secret Key first.', 'error');
					return;
				}
				
				if (!bucket) {
					showNotice('Please enter or select a bucket name first.', 'error');
					return;
				}
			}
			// If fields are disabled (wp-config.php) or on dashboard page, just proceed - server will check wp-config.php
			
			$button.prop('disabled', true).text(clockworkOffloader.strings.testing || 'Testing...');
			$status.html('<span class="status-unknown">' + (clockworkOffloader.strings.testing || 'Testing...') + '</span>');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_test_connection',
					nonce: clockworkOffloader.nonce,
					access_key: accessKey,
					secret_key: secretKey,
					bucket: bucket,
					region: region
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span class="status-success">✓ ' + (response.data.message || clockworkOffloader.strings.success || 'Connection successful!') + '</span>');
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : (clockworkOffloader.strings.error || 'Connection failed');
						$status.html('<span class="status-error">✗ ' + errorMsg + '</span>');
					}
				},
				error: function() {
					$status.html('<span class="status-error">✗ ' + (clockworkOffloader.strings.error || 'Connection failed') + '</span>');
				},
				complete: function() {
					$button.prop('disabled', false).text(clockworkOffloader.strings.testConnection || 'Test Connection');
				}
			});
		});
		
		// Offload attachment
		$('.offload-attachment').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $row = $button.closest('tr');
			var $spinner = $row.find('.spinner');
			var attachmentId = $button.data('attachment-id');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_bulk_offload',
					nonce: clockworkOffloader.nonce,
					attachment_id: attachmentId
				},
				success: function(response) {
					if (response.success) {
						// Verify files were actually offloaded
						if (response.data && response.data.files_offloaded > 0) {
							// Show success message
							showNotice(clockworkOffloader.strings.fileOffloadedSuccess, 'success');
							
							// Refresh page after a short delay to ensure status is accurate
							setTimeout(function() {
								window.location.reload();
							}, 1000);
						} else {
							// No files were offloaded - show error
							var errorMsg = response.data && response.data.errors && response.data.errors.length > 0 
								? response.data.errors.join(' ') 
								: clockworkOffloader.strings.offloadFailed;
							showNotice(errorMsg, 'error');
							$button.prop('disabled', false);
							$spinner.removeClass('is-active');
						}
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : clockworkOffloader.strings.offloadFailed;
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice(clockworkOffloader.strings.errorOccurred, 'error');
					$button.prop('disabled', false);
				},
				complete: function() {
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Offload from server (delete from server)
		$(document).on('click', '.offload-from-server', function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to delete this file from the server? It will remain on CDN.')) {
				return;
			}
			
			var $button = $(this);
			var $row = $button.closest('tr');
			var $spinner = $row.find('.spinner');
			var attachmentId = $button.data('attachment-id');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_offload_from_server',
					nonce: clockworkOffloader.nonce,
					attachment_id: attachmentId
				},
				success: function(response) {
					if (response.success) {
						showNotice('File deleted from server successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to delete file from server.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice('An error occurred while deleting from server.', 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				},
				complete: function() {
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Delete from CDN
		$(document).on('click', '.delete-from-cdn', function(e) {
			e.preventDefault();
			
			var $button = $(this);
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
			
			var $button = $(this);
			var $row = $button.closest('tr');
			var $spinner = $row.find('.spinner');
			var attachmentId = $button.data('attachment-id');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_delete_from_cdn',
					nonce: clockworkOffloader.nonce,
					attachment_id: attachmentId
				},
				success: function(response) {
					if (response.success) {
						showNotice('File deleted from Cloud successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to delete file from Cloud.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function(xhr, status, error) {
					var errorMsg = 'An error occurred while deleting from Cloud.';
					
					// Try to parse error response if available
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					} else if (xhr.responseText) {
						try {
							var response = JSON.parse(xhr.responseText);
							if (response.data && response.data.message) {
								errorMsg = response.data.message;
							}
						} catch (e) {
							// If parsing fails, use default message
						}
					}
					
					showNotice(errorMsg, 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				},
				complete: function() {
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Restore/Download attachment
		$(document).on('click', '.restore-attachment', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $row = $button.closest('tr');
			var $spinner = $row.find('.spinner');
			var attachmentId = $button.data('attachment-id');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_restore',
					nonce: clockworkOffloader.nonce,
					attachment_id: attachmentId
				},
				success: function(response) {
					if (response.success) {
						showNotice('File downloaded from Cloud successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to download file from Cloud.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice('An error occurred while downloading from Cloud.', 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				},
				complete: function() {
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Show notice - modern floating toast notification
		function showNotice(message, type, duration) {
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
		
		// Expose globally
		window.clockworkShowToast = showNotice;
		
		// Queue Management
		var queueUpdateInterval = null;
		
		// Update queue stats
		function updateQueueStats() {
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_get_queue_stats',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						var stats = response.data;
						
						// Update File Status page stats
						if ($('#queue-pending').length) {
							$('#queue-pending').text(stats.pending || 0);
							$('#queue-processing').text(stats.processing || 0);
							$('#queue-completed').text(stats.completed || 0);
							$('#queue-failed').text(stats.failed || 0);
						}
						
						// Update Bulk Upload page stats
						if ($('#queue-pending-count').length) {
							$('#queue-pending-count').text(stats.pending || 0);
							$('#queue-processing-count').text(stats.processing || 0);
						}
						
						if (stats.total > 0) {
							var progress = stats.progress || 0;
							$('#queue-progress-text').text(progress.toFixed(1) + '%');
							$('#queue-progress-bar').css('width', progress + '%');
						}
						
						// Stop updating if queue is empty
						if (stats.pending === 0 && stats.processing === 0 && !stats.is_processing) {
							if (queueUpdateInterval) {
								clearInterval(queueUpdateInterval);
								queueUpdateInterval = null;
							}
						}
					}
				}
			});
		}
		
		// Start queue stats updates if on bulk offload page or bulk upload page
		if ($('#queue-pending').length || $('#queue-pending-count').length) {
			queueUpdateInterval = setInterval(updateQueueStats, 5000); // Update every 5 seconds
		}
		
		// Bulk Tools page: Update all statistics dynamically
		var bulkStatsUpdateInterval = null;
		
		function updateBulkStats() {
			// Only update if we're on the Bulk Tools page
			if (!$('.clockwork-bulk-tools-content').length) {
				return;
			}
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_get_bulk_stats',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						var stats = response.data;
						
						// Update URL Rewrite Statistics
						var $rewritePercentage = $('.clockwork-rewrite-percentage');
						var $rewriteCount = $('.clockwork-rewrite-count');
						var $rewriteProgressBar = $('.clockwork-rewrite-progress-bar');
						
						if ($rewritePercentage.length) {
							var currentRewritePct = parseInt($rewritePercentage.text().replace('%', '')) || 0;
							if (currentRewritePct !== stats.rewrite_percentage) {
								animateCounter($rewritePercentage, currentRewritePct, stats.rewrite_percentage, '%', 500);
								$rewritePercentage.css('color', stats.rewrite_urls_enabled ? '#00a32a' : '#2271b1');
							}
						}
						
						if ($rewriteCount.length) {
							var currentText = $rewriteCount.text();
							var newText = stats.offloaded_attachments_for_rewrite.toLocaleString() + ' of ' + stats.total_attachments_for_rewrite.toLocaleString() + ' media items';
							if (currentText !== newText) {
								var currentCount = parseInt(currentText.match(/([\d,]+)\s+of/)?.[1]?.replace(/,/g, '') || '0') || 0;
								animateCounter($rewriteCount, currentCount, stats.offloaded_attachments_for_rewrite, ' of ' + stats.total_attachments_for_rewrite.toLocaleString() + ' media items', 500);
							}
						}
						
						if ($rewriteProgressBar.length) {
							var currentWidth = parseInt($rewriteProgressBar.css('width').replace('px', '')) || 0;
							var containerWidth = $rewriteProgressBar.parent().width();
							var currentPct = containerWidth > 0 ? Math.round((currentWidth / containerWidth) * 100) : 0;
							if (currentPct !== stats.rewrite_percentage) {
								animateProgressBar($rewriteProgressBar, currentPct, stats.rewrite_percentage, 500);
								$rewriteProgressBar.css('background', stats.rewrite_urls_enabled ? '#00a32a' : '#2271b1');
							}
						}
						
						// Update Offload Statistics
						var $offloadPercentage = $('.clockwork-offload-percentage');
						var $offloadCount = $('.clockwork-offload-count');
						var $offloadProgressBar = $('.clockwork-offload-progress-bar, .clockwork-progress-bar').not('.clockwork-rewrite-progress-bar, #clockwork-bulk-queue-progress-bar, #remove-progress-bar, #download-progress-bar');
						
						if ($offloadPercentage.length) {
							var currentOffloadPct = parseInt($offloadPercentage.text().replace('%', '')) || 0;
							if (currentOffloadPct !== stats.offload_percentage) {
								animateCounter($offloadPercentage, currentOffloadPct, stats.offload_percentage, '%', 500);
								$offloadPercentage.css('color', stats.offload_percentage >= 100 ? '#00a32a' : '#2271b1');
							}
						}
						
						if ($offloadCount.length) {
							var currentText = $offloadCount.text();
							var offloadedDisplay = stats.offloaded_count;
							var newText = offloadedDisplay.toLocaleString() + ' of ' + stats.total_attachments.toLocaleString() + ' media items';
							if (currentText !== newText) {
								var currentCount = parseInt(currentText.match(/([\d,]+)\s+of/)?.[1]?.replace(/,/g, '') || '0') || 0;
								animateCounter($offloadCount, currentCount, offloadedDisplay, ' of ' + stats.total_attachments.toLocaleString() + ' media items', 500);
							}
						}
						
						if ($offloadProgressBar.length) {
							var currentWidth = parseInt($offloadProgressBar.css('width').replace('px', '')) || 0;
							var containerWidth = $offloadProgressBar.parent().width();
							var currentPct = containerWidth > 0 ? Math.round((currentWidth / containerWidth) * 100) : 0;
							if (currentPct !== stats.offload_percentage) {
								animateProgressBar($offloadProgressBar, currentPct, stats.offload_percentage, 500);
								$offloadProgressBar.css('background', stats.offload_percentage >= 100 ? '#00a32a' : '#2271b1');
							}
						}
						
						// Update "100% done" message visibility
						var $successMessage = $('.clockwork-success-message');
						var $offloadButton = $('#add-all-to-queue');
						if (stats.total_not_offloaded === 0 && !stats.has_active_queue) {
							if ($offloadButton.is(':visible')) {
								$offloadButton.hide();
							}
							if (!$successMessage.length) {
								$offloadButton.after('<span class="clockwork-success-message" style="color: #00a32a; font-weight: 600;"><i class="fa-solid fa-circle-check" style="margin-right: 4px;"></i>100% of your media has been offloaded to S3!</span>');
							}
						} else {
							if ($successMessage.is(':visible')) {
								$successMessage.hide();
							}
							if (!$offloadButton.is(':visible')) {
								$offloadButton.show();
							}
						}
						
						// Update status text
						var $statusText = $('.clockwork-offload-stats .description strong');
						if ($statusText.length && stats.has_active_queue) {
							$statusText.parent().html('<strong>' + (stats.offload_percentage >= 100 && !stats.has_active_queue ? 'All media has been offloaded to S3!' : 'Status:</strong> Offloading in progress — files are uploading to S3 in the background.'));
						}

						// Update Queue Progress Bar & Text
						if (stats.queue_stats && stats.has_active_queue) {
							var qTotal = stats.queue_stats.total || 0;
							var qCompleted = stats.queue_stats.completed || 0;
							var qProgress = qTotal > 0 ? Math.round((qCompleted / qTotal) * 100) : 0;
							var $queueText = $('#clockwork-bulk-queue-progress-text');
							var $queueBar = $('#clockwork-bulk-queue-progress-bar');
							if ($queueText.length) {
								$queueText.text(qProgress + '% (' + qCompleted.toLocaleString() + ' of ' + qTotal.toLocaleString() + ') Offloading...');
							}
							if ($queueBar.length) {
								$queueBar.css('width', qProgress + '%');
							}
						}
						
						// Stop updating if everything is done and no active queue
						if (stats.total_not_offloaded === 0 && !stats.has_active_queue && stats.offload_percentage >= 100) {
							if (bulkStatsUpdateInterval) {
								clearInterval(bulkStatsUpdateInterval);
								bulkStatsUpdateInterval = null;
							}
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('Failed to update bulk stats:', status, error);
				}
			});
		}
		
		// Start bulk stats updates if on Bulk Tools page
		if ($('.clockwork-bulk-tools-content').length) {
			// Update immediately
			updateBulkStats();
			// Then update every 3 seconds
			bulkStatsUpdateInterval = setInterval(updateBulkStats, 3000);
		}
		
		// Process queue now
		$('#process-queue-now').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $spinner = $('#queue-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_process_queue_now',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success) {
						updateQueueStats();
						showNotice('Processed ' + (response.data.succeeded || 0) + ' item(s).', 'success');
					} else {
						showNotice(response.data.message || 'Failed to process queue.', 'error');
					}
				},
				error: function() {
					showNotice('An error occurred while processing the queue.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Cancel queue
		$('#cancel-queue').on('click', function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to cancel all pending items?')) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#queue-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_cancel_queue',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success) {
						updateQueueStats();
						showNotice(response.data.message || 'Queue cancelled.', 'success');
					} else {
						showNotice(response.data.message || 'Failed to cancel queue.', 'error');
					}
				},
				error: function() {
					showNotice('An error occurred while cancelling the queue.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Retry failed
		$('#retry-failed').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $spinner = $('#queue-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_retry_failed',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success) {
						updateQueueStats();
						showNotice(response.data.message || 'Failed items reset for retry.', 'success');
					} else {
						showNotice(response.data.message || 'Failed to retry items.', 'error');
					}
				},
				error: function() {
					showNotice('An error occurred while retrying failed items.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Bulk actions
		$('#doaction').on('click', function(e) {
			var action = $('#bulk-action-selector-top').val();
			
			if (!action) {
				return; // Let default form submission handle it
			}
			
			e.preventDefault();
			
			var checked = $('input[name="attachments[]"]:checked');
			if (checked.length === 0) {
				showNotice('Please select at least one attachment.', 'error');
				return;
			}
			
			var attachmentIds = [];
			checked.each(function() {
				attachmentIds.push($(this).val());
			});
			
			var $button = $(this);
			
			if (action === 'add-to-queue') {
				$button.prop('disabled', true).val('Adding...');
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: 'clockwork_offloader_add_to_queue',
						nonce: clockworkOffloader.nonce,
						attachment_ids: attachmentIds
					},
					success: function(response) {
						if (response.success) {
							showNotice(response.data.message || 'Items added to queue.', 'success');
							updateQueueStats();
							// Uncheck all
							checked.prop('checked', false);
							$('#cb-select-all').prop('checked', false);
						} else {
							showNotice(response.data.message || 'Failed to add items to queue.', 'error');
						}
					},
					error: function() {
						showNotice('An error occurred while adding items to queue.', 'error');
					},
					complete: function() {
						$button.prop('disabled', false).val('Apply');
					}
				});
			} else if (action === 'bulk-offload-from-server') {
				if (!confirm('Are you sure you want to delete these files from the server? They will remain on CDN.')) {
					return;
				}
				
				$button.prop('disabled', true).val('Processing...');
				
				processBulkAction('clockwork_offloader_bulk_offload_from_server', attachmentIds, $button, 'Offloading from server...', function() {
					showNotice('Files deleted from server successfully.', 'success');
					setTimeout(function() {
						window.location.reload();
					}, 1000);
				});
			} else if (action === 'bulk-delete-from-server') {
				if (!confirm('WARNING: This will permanently delete these files from the server. Are you sure you want to continue?')) {
					return;
				}
				
				$button.prop('disabled', true).val('Processing...');
				
				processBulkAction('clockwork_offloader_bulk_delete_from_server', attachmentIds, $button, 'Deleting from server...', function() {
					showNotice('Files deleted from server successfully.', 'success');
					setTimeout(function() {
						window.location.reload();
					}, 1000);
				});
			}
		});
		
		// Helper function for bulk actions
		function processBulkAction(ajaxAction, attachmentIds, $button, processingText, successCallback) {
			var processed = 0;
			var failed = 0;
			var total = attachmentIds.length;
			var errors = [];
			
			function processNext(index) {
				if (index >= total) {
					// All done
					$button.prop('disabled', false).val('Apply');
					if (failed === 0) {
						successCallback();
					} else {
						showNotice('Processed ' + processed + ' file(s). ' + failed + ' failed.', 'error');
					}
					return;
				}
				
				$button.val(processingText + ' (' + (index + 1) + '/' + total + ')');
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: ajaxAction,
						nonce: clockworkOffloader.nonce,
						attachment_id: attachmentIds[index]
					},
					success: function(response) {
						if (response.success) {
							processed++;
						} else {
							failed++;
							if (response.data && response.data.message) {
								errors.push(response.data.message);
							}
						}
						processNext(index + 1);
					},
					error: function() {
						failed++;
						processNext(index + 1);
					}
				});
			}
			
			processNext(0);
		}
		
		// Status toggle change
		$(document).on('change', '.clockwork-status-toggle', function() {
			var $toggle = $(this);
			var $row = $toggle.closest('tr');
			var $spinner = $row.find('.spinner');
			var attachmentId = $toggle.data('attachment-id');
			var statusType = $toggle.data('status-type');
			var enabled = $toggle.is(':checked');
			var $label = $toggle.siblings('.clockwork-toggle-label');
			
			$toggle.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_toggle_status',
					nonce: clockworkOffloader.nonce,
					attachment_id: attachmentId,
					status_type: statusType,
					enabled: enabled ? 1 : 0
				},
				success: function(response) {
					if (response.success) {
						// Update label
						$label.text(enabled ? 'Yes' : 'No');
						showNotice(response.data.message || 'Status updated successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					} else {
						// Revert toggle
						$toggle.prop('checked', !enabled);
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to update status.';
						showNotice(errorMsg, 'error');
						$toggle.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					// Revert toggle
					$toggle.prop('checked', !enabled);
					showNotice('An error occurred while updating status.', 'error');
					$toggle.prop('disabled', false);
					$spinner.removeClass('is-active');
				},
				complete: function() {
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Add All to Queue (Bulk Upload page)
		$(document).on('click', '#add-all-to-queue', function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to add all non-offloaded media files to the queue? This may take a moment for large sites.')) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#add-all-spinner');
			var totalAdded = 0;
			var totalProcessed = 0;
			var offset = 0;
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			// Show initial message
			showNotice('Starting to add items to queue...', 'info');
			
			// Function to process next chunk
			function processNextChunk() {
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: 'clockwork_offloader_add_all_to_queue',
						nonce: clockworkOffloader.nonce,
						offset: offset
					},
					timeout: 120000, // 2 minute timeout per chunk
					success: function(response) {
						if (response.success) {
							totalAdded += response.data.added || 0;
							totalProcessed += response.data.processed || 0;
							
							// Update progress message
							if (response.data.has_more) {
								showNotice(
									'Processed ' + totalProcessed + ' items, added ' + totalAdded + ' to queue. Continuing...',
									'info'
								);
								// Continue with next chunk
								offset = response.data.offset || offset + 500;
								setTimeout(processNextChunk, 500); // Small delay to prevent overwhelming the server
							} else {
								// All done
								showNotice(
									'Completed! Processed ' + totalProcessed + ' items, added ' + totalAdded + ' to queue. Processing will begin automatically via cron.',
									'success'
								);
								
								// Update counts immediately
								var currentPending = parseInt($('#queue-pending-count').text().replace(/,/g, '')) || 0;
								$('#queue-pending-count').text((currentPending + totalAdded).toLocaleString());
								
								// Update not-offloaded count
								var currentNotOffloaded = parseInt($('#not-offloaded-count').text().replace(/,/g, '')) || 0;
								$('#not-offloaded-count').text(Math.max(0, currentNotOffloaded - totalAdded).toLocaleString());
								
								// Refresh page after a moment to show updated queue stats
								setTimeout(function() {
									window.location.reload();
								}, 1500);
							}
						} else {
							var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to add items to queue.';
							showNotice(errorMsg, 'error');
							$button.prop('disabled', false);
							$spinner.removeClass('is-active');
						}
					},
					error: function(xhr, status, error) {
						if (status === 'timeout') {
							showNotice('Request timed out. ' + totalAdded + ' items were added so far. You can try again to continue.', 'error');
						} else {
							showNotice('An error occurred while adding items to queue. ' + totalAdded + ' items were added so far.', 'error');
						}
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			}
			
			// Start processing
			processNextChunk();
		});
		
		
		// Cleanup on page unload
		$(window).on('beforeunload', function() {
			if (queueUpdateInterval) {
				clearInterval(queueUpdateInterval);
			}
		});
		
		// Details Modal
		var $modal = $('#clockwork-details-modal');
		var $modalContent = $('#clockwork-details-content');
		
		// Open modal
		$(document).on('click', '.clockwork-show-details', function(e) {
			e.preventDefault();
			var $button = $(this);
			var detailsData = $button.data('details');
			
			if (!detailsData) {
				return;
			}
			
			// Format file size
			function formatFileSize(bytes) {
				if (!bytes || bytes === 0) return '0 B';
				var k = 1024;
				var sizes = ['B', 'KB', 'MB', 'GB'];
				var i = Math.floor(Math.log(bytes) / Math.log(k));
				return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
			}
			
			// Format date
			function formatDate(dateString) {
				if (!dateString) return 'N/A';
				var date = new Date(dateString);
				return date.toLocaleString();
			}
			
			// Build HTML
			var html = '<div class="clockwork-details-section">';
			html += '<h3>File Information</h3>';
			html += '<div class="clockwork-details-row">';
			html += '<span class="clockwork-details-label">File Name:</span>';
			html += '<span class="clockwork-details-value">' + (detailsData.file_name || 'N/A') + '</span>';
			html += '</div>';
			html += '<div class="clockwork-details-row">';
			html += '<span class="clockwork-details-label">Local File Size:</span>';
			html += '<span class="clockwork-details-value">' + formatFileSize(detailsData.local_file_size) + '</span>';
			html += '</div>';
			html += '<div class="clockwork-details-row">';
			html += '<span class="clockwork-details-label">Upload Date:</span>';
			html += '<span class="clockwork-details-value">' + formatDate(detailsData.upload_date) + '</span>';
			html += '</div>';
			html += '</div>';
			
			// CDN Information
			if (detailsData.offload_details && detailsData.offload_details.length > 0) {
				html += '<div class="clockwork-details-section">';
				html += '<h3>CDN Information</h3>';
				
				detailsData.offload_details.forEach(function(offload) {
					html += '<div class="clockwork-offload-item">';
					html += '<div style="font-weight: 600; margin-bottom: 8px; color: #2271b1;">' + (offload.size_name || 'Original') + '</div>';
					
					if (offload.s3_url) {
						html += '<div class="clockwork-details-row">';
						html += '<span class="clockwork-details-label">S3 URL:</span>';
						html += '<span class="clockwork-details-value">';
						html += '<a href="' + offload.s3_url + '" target="_blank">' + offload.s3_url + '</a>';
						html += '<span class="clockwork-details-copy" data-copy="' + offload.s3_url.replace(/"/g, '&quot;') + '" title="Copy to clipboard">📋</span>';
						html += '</span>';
						html += '</div>';
					}
					
					if (offload.s3_key) {
						html += '<div class="clockwork-details-row">';
						html += '<span class="clockwork-details-label">S3 Key:</span>';
						html += '<span class="clockwork-details-value">' + offload.s3_key + '</span>';
						html += '</div>';
					}
					
					if (offload.bucket) {
						html += '<div class="clockwork-details-row">';
						html += '<span class="clockwork-details-label">Bucket:</span>';
						html += '<span class="clockwork-details-value">' + offload.bucket + '</span>';
						html += '</div>';
					}
					
					if (offload.file_size) {
						html += '<div class="clockwork-details-row">';
						html += '<span class="clockwork-details-label">File Size:</span>';
						html += '<span class="clockwork-details-value">' + formatFileSize(offload.file_size) + '</span>';
						html += '</div>';
					}
					
					if (offload.offload_date) {
						html += '<div class="clockwork-details-row">';
						html += '<span class="clockwork-details-label">Offload Date:</span>';
						html += '<span class="clockwork-details-value">' + formatDate(offload.offload_date) + '</span>';
						html += '</div>';
					}
					
					html += '</div>';
				});
				
				html += '</div>';
			} else {
				html += '<div class="clockwork-details-section">';
				html += '<h3>CDN Information</h3>';
				html += '<p style="color: #646970; font-style: italic;">This file has not been offloaded to CDN.</p>';
				html += '</div>';
			}
			
			$modalContent.html(html);
			$modal.show();
			
			// Copy to clipboard functionality
			$modalContent.on('click', '.clockwork-details-copy', function(e) {
				e.preventDefault();
				var textToCopy = $(this).data('copy');
				if (textToCopy) {
					navigator.clipboard.writeText(textToCopy).then(function() {
						var $copyBtn = $(e.target);
						var originalText = $copyBtn.html();
						$copyBtn.html('✓');
						setTimeout(function() {
							$copyBtn.html(originalText);
						}, 2000);
					}).catch(function(err) {
						console.error('Failed to copy:', err);
					});
				}
			});
		});
		
		// Close modal
		$(document).on('click', '.clockwork-modal-close', function(e) {
			e.preventDefault();
			$modal.hide();
		});
		
		$(document).on('click', '.clockwork-modal', function(e) {
			if (e.target === this) {
				$modal.hide();
			}
		});
		
		// Close on Escape key
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $modal.is(':visible')) {
				$modal.hide();
			}
		});
		
		// Development Mode - File Type Selection
		// Category header click to select/deselect all in category
		$('.category-header').on('click', function(e) {
			e.preventDefault();
			var category = $(this).data('category');
			var $checkboxes = $('.file-type-checkbox[data-category="' + category + '"]');
			
			// Check if all are checked
			var allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
			
			// Toggle: if all checked, uncheck all; otherwise check all
			$checkboxes.prop('checked', !allChecked);
		});
		
		// Download Test Files
		$('#download-test-files').on('click', function(e) {
			e.preventDefault();
			
			var selectedTypes = $('.file-type-checkbox:checked').map(function() {
				return $(this).val();
			}).get();
			
			if (selectedTypes.length === 0) {
				showNotice('Please select at least one file type to download.', 'error');
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#download-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_download_test_files',
					nonce: clockworkOffloader.nonce,
					file_types: selectedTypes
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'Files downloaded successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to download files.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice('An error occurred while downloading files.', 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Select all files checkbox
		$('#select-all-files').on('change', function() {
			$('.file-select-checkbox').prop('checked', $(this).prop('checked'));
		});
		
		// Select all downloaded files
		// Update select all checkbox when individual checkboxes change
		$(document).on('change', '.file-select-checkbox', function() {
			var total = $('.file-select-checkbox').length;
			var checked = $('.file-select-checkbox:checked').length;
			$('#select-all-files').prop('checked', total === checked);
		});
		
		// Delete single file
		$(document).on('click', '.delete-single-file', function(e) {
			e.preventDefault();
			
			var fileId = $(this).data('file-id');
			var fileName = $(this).closest('tr').find('td:nth-child(3)').text();
			
			if (!confirm('Are you sure you want to delete "' + fileName + '"? This action cannot be undone.')) {
				return;
			}
			
			var $button = $(this);
			var $row = $(this).closest('tr');
			
			$button.prop('disabled', true);
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_delete_single_downloaded_file',
					nonce: clockworkOffloader.nonce,
					file_id: fileId
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'File deleted successfully.', 'success');
						$row.fadeOut(300, function() {
							$(this).remove();
							// Update count
							var remaining = $('.file-select-checkbox').length;
							$('#downloaded-files-count').text(remaining);
							// Hide section if no files left
							if (remaining === 0) {
								$('#downloaded-files-section').fadeOut();
							}
						});
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to delete file.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					showNotice('An error occurred while deleting the file.', 'error');
					$button.prop('disabled', false);
				}
			});
		});
		
		// Add to Media Library
		$('#add-to-media-library').on('click', function(e) {
			e.preventDefault();
			
			var storageOption = $('input[name="storage_option"]:checked').val();
			
			if (!storageOption) {
				showNotice('Please select a storage option.', 'error');
				return;
			}
			
			// Get selected file IDs
			var selectedFileIds = $('.file-select-checkbox:checked').map(function() {
				return $(this).val();
			}).get();
			
			if (selectedFileIds.length === 0) {
				showNotice('Please select at least one file to add.', 'error');
				return;
			}
			
			var confirmMsg = 'Are you sure you want to add ' + selectedFileIds.length + ' selected file(s) to the media library?';
			if (storageOption === 's3') {
				confirmMsg = 'Are you sure you want to upload ' + selectedFileIds.length + ' selected file(s) directly to S3?';
			} else if (storageOption === 'both') {
				confirmMsg = 'Are you sure you want to add ' + selectedFileIds.length + ' selected file(s) to the media library and upload to S3?';
			}
			
			if (!confirm(confirmMsg)) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#add-media-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_add_test_files_to_media',
					nonce: clockworkOffloader.nonce,
					storage_option: storageOption,
					selected_file_ids: selectedFileIds
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'Files added successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 2000);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to add files.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice('An error occurred while adding files.', 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Delete Downloaded Files
		$('#delete-downloaded-files').on('click', function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to delete all downloaded files? This will remove the files from the server but will not affect files already added to the media library. This action cannot be undone.')) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#delete-downloaded-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_delete_downloaded_files',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'Downloaded files deleted successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to delete files.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice('An error occurred while deleting files.', 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Delete All Development Files
		$('#delete-all-dev-files').on('click', function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to delete ALL development files? This will remove them from both the media library and S3. This action cannot be undone.')) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#delete-dev-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_delete_dev_files',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'Development files deleted successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to delete files.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice('An error occurred while deleting files.', 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Clear Test Data
		$('#clear-test-data').on('click', function(e) {
			e.preventDefault();
			
			var deleteOption = $('input[name="clear_test_data_option"]:checked').val();
			if (!deleteOption) {
				deleteOption = 'both';
			}
			
			var confirmMessage = 'Are you sure you want to clear all test data?';
			if (deleteOption === 'server') {
				confirmMessage = 'Are you sure you want to delete all test data from the server? Files on S3 will be kept.';
			} else if (deleteOption === 's3') {
				confirmMessage = 'Are you sure you want to delete all test data from S3? Files in the media library will be kept.';
			} else {
				confirmMessage = 'Are you sure you want to delete all test data from both the server and S3? This action cannot be undone.';
			}
			
			if (!confirm(confirmMessage)) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#clear-test-data-spinner');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_clear_test_data',
					nonce: clockworkOffloader.nonce,
					delete_option: deleteOption
				},
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'Test data cleared successfully.', 'success');
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to clear test data.';
						showNotice(errorMsg, 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					showNotice('An error occurred while clearing test data.', 'error');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Uninstall plugin data
		// Migration Diagnostic
		$('#run-migration-diagnostic').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $spinner = $('#diagnostic-spinner');
			var $result = $('#diagnostic-result');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.html('<p>Running diagnostic...</p>');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_migration_diagnostic',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					$button.prop('disabled', false);
					
					if (response.success && response.data) {
						var data = response.data;
						var html = '<div class="notice notice-info"><h3>Migration Diagnostic Results</h3></div>';
						
						// Table status
						html += '<div class="postbox" style="margin-top: 20px;"><div class="inside">';
						html += '<h4>Database Status</h4>';
						html += '<ul style="list-style: disc; padding-left: 30px;">';
						html += '<li>Table exists: <strong>' + (data.table_exists ? 'YES' : 'NO') + '</strong></li>';
						if (data.table_exists) {
							html += '<li>Total records: <strong>' + data.total_records + '</strong></li>';
							html += '<li>Offloaded records: <strong>' + data.offloaded_records + '</strong></li>';
							html += '<li>Attachments with records: <strong>' + data.attachments_with_records + '</strong></li>';
						} else {
							html += '</ul>';
							html += '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">';
							html += '<p><strong>Table does not exist!</strong> This is why files are not showing as offloaded.</p>';
							html += '<button type="button" class="button button-primary" id="create-table-button" style="margin-top: 10px;">';
							html += 'Create Database Table';
							html += '</button>';
							html += '<span class="spinner" id="create-table-spinner" style="float: none; margin-left: 10px;"></span>';
							html += '<div id="create-table-result" style="margin-top: 10px;"></div>';
							html += '</div>';
							html += '<ul style="list-style: disc; padding-left: 30px;">';
						}
						html += '</ul>';
						html += '</div></div>';
						
						// Add handler for create table button if table doesn't exist
						if (!data.table_exists) {
							setTimeout(function() {
								$('#create-table-button').on('click', function(e) {
									e.preventDefault();
									
									var $button = $(this);
									var $spinner = $('#create-table-spinner');
									var $result = $('#create-table-result');
									
									$button.prop('disabled', true);
									$spinner.addClass('is-active');
									$result.html('');
									
									$.ajax({
										url: clockworkOffloader.ajaxUrl,
										type: 'POST',
										data: {
											action: 'clockwork_offloader_create_table',
											nonce: clockworkOffloader.nonce
										},
										success: function(response) {
											$spinner.removeClass('is-active');
											$button.prop('disabled', false);
											
											if (response.success) {
												$result.html('<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p></div>');
												// Reload diagnostic after a short delay
												setTimeout(function() {
													$('#run-migration-diagnostic').click();
												}, 1000);
											} else {
												$result.html('<div class="notice notice-error"><p><strong>Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</strong></p></div>');
											}
										},
										error: function() {
											$spinner.removeClass('is-active');
											$button.prop('disabled', false);
											$result.html('<div class="notice notice-error"><p><strong>Failed to create table. Please try again.</strong></p></div>');
										}
									});
								});
							}, 100);
						}
						
						// Sample records
						if (data.sample_records && data.sample_records.length > 0) {
							html += '<div class="postbox" style="margin-top: 20px;"><div class="inside">';
							html += '<h4>Sample Records (First 5)</h4>';
							html += '<table class="widefat" style="margin-top: 10px;">';
							html += '<thead><tr><th>Attachment ID</th><th>Size</th><th>Status</th><th>is_offloaded()</th><th>S3 URL</th></tr></thead>';
							html += '<tbody>';
							data.sample_records.forEach(function(record) {
								html += '<tr>';
								html += '<td>' + record.attachment_id + '</td>';
								html += '<td>' + record.size_name + '</td>';
								html += '<td>' + record.status + '</td>';
								html += '<td><strong>' + (record.is_offloaded_check ? 'TRUE' : 'FALSE') + '</strong></td>';
								html += '<td style="word-break: break-all; font-size: 11px;">' + (record.s3_url || 'NONE') + '</td>';
								html += '</tr>';
							});
							html += '</tbody></table>';
							html += '</div></div>';
						}
						
						// Test results
						if (data.test_results && data.test_results.length > 0) {
							html += '<div class="postbox" style="margin-top: 20px;"><div class="inside">';
							html += '<h4>URL Rewriting Tests</h4>';
							html += '<table class="widefat" style="margin-top: 10px;">';
							html += '<thead><tr><th>Attachment ID</th><th>is_offloaded()</th><th>URL Rewritten</th><th>Original URL</th><th>S3 URL</th></tr></thead>';
							html += '<tbody>';
							data.test_results.forEach(function(test) {
								var statusClass = test.url_rewritten ? 'notice-success' : 'notice-error';
								html += '<tr class="' + statusClass + '">';
								html += '<td>' + test.attachment_id + '</td>';
								html += '<td><strong>' + (test.is_offloaded ? 'TRUE' : 'FALSE') + '</strong></td>';
								html += '<td><strong>' + (test.url_rewritten ? 'YES' : 'NO') + '</strong></td>';
								html += '<td style="word-break: break-all; font-size: 11px;">' + test.original_url + '</td>';
								html += '<td style="word-break: break-all; font-size: 11px;">' + (test.s3_url || 'NONE') + '</td>';
								html += '</tr>';
							});
							html += '</tbody></table>';
							html += '</div></div>';
						}
						
						// Settings
						html += '<div class="postbox" style="margin-top: 20px;"><div class="inside">';
						html += '<h4>Settings</h4>';
						html += '<ul style="list-style: disc; padding-left: 30px;">';
						html += '<li>Rewrite URLs: <strong>' + (data.settings.rewrite_urls ? 'ENABLED' : 'DISABLED') + '</strong></li>';
						html += '<li>Provider: <strong>' + data.settings.provider + '</strong></li>';
						html += '<li>Bucket: <strong>' + data.settings.bucket + '</strong></li>';
						html += '<li>Region: <strong>' + data.settings.region + '</strong></li>';
						html += '<li>CDN Domain: <strong>' + data.settings.cdn_domain + '</strong></li>';
						html += '</ul>';
						html += '</div></div>';
						
						// URL Rewriter status
						html += '<div class="postbox" style="margin-top: 20px;"><div class="inside">';
						html += '<h4>URL Rewriter</h4>';
						html += '<p>URL Rewriter class loaded: <strong>' + (data.url_rewriter_status ? 'YES' : 'NO') + '</strong></p>';
						html += '</div></div>';
						
						$result.html(html);
					} else {
						$result.html('<div class="notice notice-error"><p>Error running diagnostic: ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</p></div>');
					}
				},
				error: function() {
					$spinner.removeClass('is-active');
					$button.prop('disabled', false);
					$result.html('<div class="notice notice-error"><p>Failed to run diagnostic. Please try again.</p></div>');
				}
			});
		});
		
		// Reset migrator notice
		$('#reset-migrator-notice').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $spinner = $('#reset-notice-spinner');
			var $result = $('#reset-notice-result');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.html('');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_reset_migrator_notice',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					$spinner.removeClass('is-active');
					$button.prop('disabled', false);
					
					if (response.success) {
						$result.html('<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p></div>');
						// Optionally redirect to dashboard after a short delay
						setTimeout(function() {
							window.location.href = clockworkOffloader.adminUrl + 'options-general.php?page=clockwork-offloader';
						}, 1500);
					} else {
						$result.html('<div class="notice notice-error"><p><strong>Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</strong></p></div>');
					}
				},
				error: function() {
					$spinner.removeClass('is-active');
					$button.prop('disabled', false);
					$result.html('<div class="notice notice-error"><p><strong>Failed to reset notice. Please try again.</strong></p></div>');
				}
			});
		});
		
		$('#uninstall-plugin-data').on('click', function(e) {
			e.preventDefault();
			
			var confirmMessage = 'Are you absolutely sure you want to uninstall all plugin data?\n\n' +
				'This will permanently delete:\n' +
				'- All plugin settings\n' +
				'- All offload tracking records\n' +
				'- All queue data\n' +
				'- All plugin transients\n' +
				'- All plugin postmeta data\n\n' +
				'This action CANNOT be undone!\n\n' +
				'Type "UNINSTALL" to confirm:';
			
			var userInput = prompt(confirmMessage);
			
			if (userInput !== 'UNINSTALL') {
				if (userInput !== null) {
					alert('Uninstall cancelled. You must type "UNINSTALL" exactly to proceed.');
				}
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#uninstall-spinner');
			var $result = $('#uninstall-result');
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.html('');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_uninstall_data',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					if (response.success) {
						var results = response.data.results || {};
						var message = response.data.message || 'Plugin data uninstalled successfully.';
						
						var details = '<div class="notice notice-success"><p><strong>' + message + '</strong></p>';
						if (results.options_deleted !== undefined) {
							details += '<ul style="margin: 10px 0;">';
							details += '<li>Options deleted: ' + results.options_deleted + '</li>';
							details += '<li>Transients deleted: ' + results.transients_deleted + '</li>';
							details += '<li>Database tables dropped: ' + results.tables_dropped + '</li>';
							details += '<li>Postmeta entries deleted: ' + results.postmeta_deleted + '</li>';
							details += '</ul>';
						}
						details += '<p>Page will reload in 3 seconds...</p></div>';
						
						$result.html(details);
						
						setTimeout(function() {
							window.location.reload();
						}, 3000);
					} else {
						var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to uninstall plugin data.';
						$result.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				},
				error: function() {
					$result.html('<div class="notice notice-error"><p>An error occurred while uninstalling plugin data.</p></div>');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
		
		// Bulk Download functionality
		$('#start-bulk-download').on('click', function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to download all files from S3 that are not on the server? This may take a moment for large sites.')) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#download-spinner');
			var $progressDiv = $('.download-progress');
			var $resultsDiv = $('#download-results');
			var $messagesDiv = $('#download-messages');
			
			var allAttachmentIds = [];
			var offset = 0;
			var downloaded = 0;
			var failed = 0;
			var totalNeedsDownload = parseInt($('#needs-download-count').text().replace(/,/g, '')) || 0;
			
			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$progressDiv.show();
			$resultsDiv.show();
			$messagesDiv.html('<p>Starting bulk download...</p>');
			
			// Function to get all attachment IDs that need downloading
			function getAllNeedingDownload() {
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: 'clockwork_offloader_add_all_to_download_queue',
						nonce: clockworkOffloader.nonce,
						offset: offset
					},
					timeout: 120000,
					success: function(response) {
						if (response.success) {
							if (response.data.attachment_ids && response.data.attachment_ids.length > 0) {
								allAttachmentIds = allAttachmentIds.concat(response.data.attachment_ids);
							}
							
							if (response.data.has_more) {
								offset = response.data.offset;
								setTimeout(getAllNeedingDownload, 500);
							} else {
								// All IDs collected, start downloading
								if (allAttachmentIds.length > 0) {
									$messagesDiv.append('<p>Found ' + allAttachmentIds.length + ' files to download. Starting download...</p>');
									processDownloads();
								} else {
									$messagesDiv.append('<p>No files need to be downloaded.</p>');
									$button.prop('disabled', false);
									$spinner.removeClass('is-active');
								}
							}
						} else {
							showNotice(response.data.message || 'Failed to get list of files to download.', 'error');
							$button.prop('disabled', false);
							$spinner.removeClass('is-active');
						}
					},
					error: function() {
						showNotice('An error occurred while getting list of files to download.', 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			}
			
			// Function to process downloads in batches
			function processDownloads() {
				if (allAttachmentIds.length === 0) {
					// All done
					$messagesDiv.append('<p><strong>Download complete!</strong> Downloaded: ' + downloaded + ', Failed: ' + failed + '</p>');
					$('#downloaded-count').text(downloaded.toLocaleString());
					$('#failed-count').text(failed.toLocaleString());
					$('#download-progress-bar').css('width', '100%');
					$('#download-progress-text').text('100%');
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
					
					// Update needs download count
					$('#needs-download-count').text('0');
					return;
				}
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: 'clockwork_offloader_process_download_queue_now',
						nonce: clockworkOffloader.nonce,
						attachment_ids: allAttachmentIds
					},
					timeout: 120000,
					success: function(response) {
						if (response.success) {
							downloaded += response.data.succeeded || 0;
							failed += response.data.failed || 0;
							
							if (response.data.errors && response.data.errors.length > 0) {
								var errorList = '<ul>';
								response.data.errors.forEach(function(error) {
									errorList += '<li>' + error + '</li>';
								});
								errorList += '</ul>';
								$messagesDiv.append(errorList);
							}
							
							// Update counts
							$('#downloaded-count').text(downloaded.toLocaleString());
							$('#failed-count').text(failed.toLocaleString());
							
							// Update progress
							var total = totalNeedsDownload;
							var completed = downloaded + failed;
							var progress = total > 0 ? Math.round((completed / total) * 100) : 0;
							$('#download-progress-bar').css('width', progress + '%');
							$('#download-progress-text').text(progress + '%');
							
							// Update remaining IDs
							allAttachmentIds = response.data.remaining || [];
							
							if (response.data.has_more) {
								// Continue with next batch
								setTimeout(processDownloads, 1000);
							} else {
								// All done
								$messagesDiv.append('<p><strong>Download complete!</strong> Downloaded: ' + downloaded + ', Failed: ' + failed + '</p>');
								$('#download-progress-bar').css('width', '100%');
								$('#download-progress-text').text('100%');
								$button.prop('disabled', false);
								$spinner.removeClass('is-active');
								
								// Update needs download count
								$('#needs-download-count').text('0');
							}
						} else {
							showNotice(response.data.message || 'Failed to process downloads.', 'error');
							$button.prop('disabled', false);
							$spinner.removeClass('is-active');
						}
					},
					error: function() {
						showNotice('An error occurred while downloading files.', 'error');
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			}
			
			// Start the process
			getAllNeedingDownload();
		});
		
		// Remove all files from bucket
		$(document).on('click', '#remove-all-from-bucket', function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to remove all files from the bucket? Files that don\'t exist on the server will be downloaded first, then all files will be deleted from S3. This action cannot be undone.')) {
				return;
			}
			
			var $button = $(this);
			var $spinner = $('#bulk-tools-spinner');
			var $progressDiv = $('.remove-progress');
			var $resultsDiv = $('#remove-results');
			var $messagesDiv = $('#remove-messages');
			
			var offset = 0;
			var deleted = 0;
			var downloaded = 0;
			var failed = 0;
			var totalOnBucket = parseInt($('#on-bucket-count').text().replace(/,/g, '')) || 0;
			
			$button.prop('disabled', true);
			$spinner.show().addClass('is-active');
			$progressDiv.show();
			$resultsDiv.show();
			$messagesDiv.html('<p>Starting removal process...</p>');
			
			// Function to process removals in chunks
			function processRemovals() {
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: 'clockwork_offloader_remove_all_from_bucket',
						nonce: clockworkOffloader.nonce,
						offset: offset
					},
					timeout: 120000,
					success: function(response) {
						if (response.success) {
							deleted += response.data.deleted || 0;
							downloaded += response.data.downloaded || 0;
							failed += response.data.failed || 0;
							
							if (response.data.errors && response.data.errors.length > 0) {
								var errorList = '<ul>';
								response.data.errors.forEach(function(error) {
									errorList += '<li style="color: #d63638;">' + error + '</li>';
								});
								errorList += '</ul>';
								$messagesDiv.append(errorList);
							}
							
							// Update progress
							var total = totalOnBucket;
							var processed = response.data.processed || 0;
							var progress = total > 0 ? Math.round(((offset + processed) / total) * 100) : 0;
							progress = Math.min(progress, 100);
							$('#remove-progress-bar').css('width', progress + '%');
							$('#remove-progress-text').text(progress + '%');
							
							// Update message
							$messagesDiv.append('<p>' + response.data.message + '</p>');
							
							if (response.data.has_more && !response.data.completed) {
								// Continue with next chunk
								offset = response.data.offset;
								setTimeout(processRemovals, 1000);
							} else {
								// All done
								$messagesDiv.append('<p><strong>Removal complete!</strong> Downloaded: ' + downloaded + ', Deleted from S3: ' + deleted + ', Failed: ' + failed + '</p>');
								$('#remove-progress-bar').css('width', '100%');
								$('#remove-progress-text').text('100%');
								$button.prop('disabled', false);
								$spinner.hide().removeClass('is-active');
								
								// Update on bucket count
								$('#on-bucket-count').text('0');
								
								// Reload page after 2 seconds to refresh stats
								setTimeout(function() {
									window.location.reload();
								}, 2000);
							}
						} else {
							showNotice(response.data.message || 'Failed to remove files from bucket.', 'error');
							$button.prop('disabled', false);
							$spinner.hide().removeClass('is-active');
						}
					},
					error: function() {
						showNotice('An error occurred while removing files from bucket.', 'error');
						$button.prop('disabled', false);
						$spinner.hide().removeClass('is-active');
					}
				});
			}
			
			// Start the process
			processRemovals();
		});
		
		// Setup Wizard
		if ($('.clockwork-setup-wizard').length) {
			var currentStep = parseInt($('.clockwork-setup-step').data('step') || 1);
			
			// Debug: Log that setup wizard JS is loading
			console.log('Clockwork Setup Wizard JS loaded, current step:', currentStep);
			
			// Debug: Check if buttons exist
			console.log('Button check:', {
				'continue-to-step2': $('#continue-to-step2').length,
				'clockwork-setup-next (step 2)': $('.clockwork-setup-next[data-step="2"]').length,
				'clockwork-setup-test-connection': $('#clockwork-setup-test-connection').length,
				'clockwork-setup-complete-btn': $('#clockwork-setup-complete-btn').length
			});
			
			// Debug: Add click listener to ALL buttons to see if clicks are registered
			$(document).on('click', 'button, a.button', function(e) {
				var $btn = $(this);
				var id = $btn.attr('id');
				var classes = $btn.attr('class');
				var dataStep = $btn.data('step');
				console.log('Button clicked:', {
					id: id,
					classes: classes,
					dataStep: dataStep,
					text: $btn.text().trim(),
					element: $btn[0]
				});
			});
			
			// Expose test function to window for debugging
			window.testClockworkButton = function(buttonSelector) {
				console.log('Testing button:', buttonSelector);
				var $btn = $(buttonSelector);
				console.log('Button found:', $btn.length > 0);
				if ($btn.length > 0) {
					console.log('Button details:', {
						id: $btn.attr('id'),
						classes: $btn.attr('class'),
						dataStep: $btn.data('step'),
						text: $btn.text().trim()
					});
					console.log('Triggering click...');
					$btn.trigger('click');
				} else {
					console.error('Button not found:', buttonSelector);
				}
			};
			
			// Connection method toggle
			$('input[name="connection_method"]').on('change', function() {
				var method = $(this).val();
				$('.clockwork-connection-option').removeClass('selected');
				$(this).closest('.clockwork-connection-option').addClass('selected');
				
				if (method === 'wp-config') {
					$('#clockwork-wp-config-instructions').show();
					$('#clockwork-database-credentials').hide();
					// Check wp-config status on selection (auto-verifies if constants exist)
					checkWpConfigStatus();
					initStep2Button();
				} else {
					$('#clockwork-wp-config-instructions').hide();
					$('#clockwork-database-credentials').show();
					// Check if credentials are filled for database method
					initStep2Button();
				}
			});
			
			// Copy code button
			$(document).on('click', '#copy-wp-config-code, .clockwork-copy-code', function() {
				var $codeElement = $('#clockwork-wp-config-code');
				var code = $codeElement.text();
				
				var $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(code).select();
				document.execCommand('copy');
				$temp.remove();
				
				var $btn = $(this);
				var originalText = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes"></span> Copied!');
				setTimeout(function() {
					$btn.html(originalText);
				}, 2000);
			});
			
			// Verify wp-config.php button
			$(document).on('click', '#verify-wp-config', function() {
				var $button = $(this);
				var $statusMessage = $('#clockwork-wp-config-status-message');
				var $statusNotice = $('#clockwork-wp-config-status-notice');
				
				$button.prop('disabled', true);
				$statusMessage.html('<span class="spinner is-active" style="float: none; margin: 0 5px; vertical-align: middle;"></span> <span style="vertical-align: middle;">Verifying...</span>');
				$statusNotice.hide().html('');
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: 'clockwork_offloader_verify_wp_config',
						nonce: clockworkOffloader.nonce
					},
					success: function(response) {
						$button.prop('disabled', false);
						if (response.success && response.data.verified) {
							$statusMessage.html('<span style="color: #00a32a; font-weight: 600;">✓ ' + response.data.message + '</span>');
							if (response.data.details && response.data.details.length > 0) {
								$statusNotice.html('<div class="notice notice-success inline"><p><small>' + response.data.details.join(', ') + '</small></p></div>').show();
							}
							// Show Step 2 button immediately after verification
							showStep2Button();
						} else {
							$statusMessage.html('<span style="color: #d63638; font-weight: 600;">✗ ' + (response.data.message || 'Constants not found') + '</span>');
							var noticeHtml = '';
							if (response.data.missing && response.data.missing.length > 0) {
								noticeHtml += '<p><strong>Missing:</strong> ' + response.data.missing.join(', ') + '</p>';
							}
							if (response.data.full_message) {
								noticeHtml += '<p>' + response.data.full_message + '</p>';
							}
							if (noticeHtml) {
								$statusNotice.html('<div class="notice notice-error inline">' + noticeHtml + '</div>').show();
							}
							hideStep2Button();
						}
					},
					error: function() {
						$button.prop('disabled', false);
						$statusMessage.html('<span style="color: #d63638; font-weight: 600;">✗ Error verifying wp-config.php</span>');
						$statusNotice.html('<div class="notice notice-error inline"><p>An error occurred while verifying. Please try again.</p></div>').show();
						hideStep2Button();
					}
				});
			});
			
			// Validate database fields on input
			$(document).on('input blur', '#setup_access_key, #setup_secret_key', function() {
				var accessKey = $('#setup_access_key').val().trim();
				var secretKey = $('#setup_secret_key').val().trim();
				
				// Show Step 2 button when both credentials are filled
				if (accessKey && secretKey) {
					showStep2Button();
				} else {
					hideStep2Button();
				}
			});
			
			// Check wp-config status on page load
			function checkWpConfigStatus() {
				if ($('input[name="connection_method"]:checked').val() === 'wp-config') {
					// Auto-check on page load if wp-config method is selected
					$('#verify-wp-config').trigger('click');
				}
			}
			
			// Initialize Step 2 button state
			function initStep2Button() {
				var connectionMethod = $('input[name="connection_method"]:checked').val();
				if (connectionMethod === 'wp-config') {
					// Check if already verified
					var $statusMessage = $('#clockwork-wp-config-status-message');
					var isVerified = $statusMessage.find('span[style*="color: #00a32a"]').length > 0;
					if (isVerified) {
						showStep2Button();
					} else {
						hideStep2Button();
					}
				} else if (connectionMethod === 'database') {
					// Check if credentials are filled
					var accessKey = $('#setup_access_key').val().trim();
					var secretKey = $('#setup_secret_key').val().trim();
					if (accessKey && secretKey) {
						showStep2Button();
					} else {
						hideStep2Button();
					}
				}
			}
			
			// Show/hide Step 2 button
			function showStep2Button() {
				var $step2Button = $('#continue-to-step2');
				if ($step2Button.length) {
					$step2Button.show().css('display', 'inline-block');
				} else {
					// Button might not exist yet, try again after a short delay
					setTimeout(function() {
						var $btn = $('#continue-to-step2');
						if ($btn.length) {
							$btn.show().css('display', 'inline-block');
						}
					}, 100);
				}
			}
			
			function hideStep2Button() {
				var $step2Button = $('#continue-to-step2');
				if ($step2Button.length) {
					$step2Button.hide();
				}
			}
			
			// Initialize button state on page load
			initStep2Button();
			
			// If wp-config method is selected, check if already verified (from server-side pre-population)
			if ($('input[name="connection_method"]:checked').val() === 'wp-config') {
				var $statusMessage = $('#clockwork-wp-config-status-message');
				var isVerified = $statusMessage.find('span[style*="color: #00a32a"]').length > 0;
				
				if (isVerified) {
					// Already verified (pre-populated from server), show button immediately
					showStep2Button();
				} else {
					// Not verified yet, auto-verify after a short delay
					setTimeout(function() {
						$('#verify-wp-config').trigger('click');
					}, 500);
				}
			}
			
			// Handle "Continue to Step 2" button - save step 1 data first
			$(document).on('click', '#continue-to-step2', function(e) {
				var $button = $(this);
				var connectionMethod = $('input[name="connection_method"]:checked').val();
				var provider = $('input[name="provider"]:checked').val() || 'aws';
				
				console.log('Continue to Step 2 handler triggered');
				
				// Prevent default navigation
				e.preventDefault();
				e.stopPropagation();
				
				// Validate based on connection method
				if (connectionMethod === 'wp-config') {
					var $statusMessage = $('#clockwork-wp-config-status-message');
					// Check for success checkmark (✓) in the message text
					var messageText = $statusMessage.text();
					var isVerified = messageText.indexOf('✓') !== -1 || messageText.indexOf('successfully') !== -1;
					console.log('Verification check:', { messageText: messageText, isVerified: isVerified });
					
					if (!isVerified) {
						showSetupNotice('Please verify wp-config.php first.', 'error');
						return false;
					}
				} else if (connectionMethod === 'database') {
					var accessKey = $('#setup_access_key').val().trim();
					var secretKey = $('#setup_secret_key').val().trim();
					
					if (!accessKey || !secretKey) {
						showSetupNotice('Please fill in all credential fields.', 'error');
						// Highlight empty fields
						if (!accessKey) {
							$('#setup_access_key').css('border-color', '#d63638');
						}
						if (!secretKey) {
							$('#setup_secret_key').css('border-color', '#d63638');
						}
						return false;
					}
					
					// Remove error highlighting
					$('#setup_access_key, #setup_secret_key').css('border-color', '');
				}
				
				// Save step 1 data, then proceed to step 2
				var originalHtml = $button.html();
				$button.prop('disabled', true).html('Saving...');
				setSetupStatus('Checking your credentials with the storage provider. This can take up to 30 seconds.', 'info');
				
				var postData = {
					action: 'clockwork_offloader_setup_step1',
					nonce: clockworkOffloader.nonce,
					provider: provider,
					connection_method: connectionMethod
				};
				
				if (connectionMethod === 'database') {
					postData.access_key = $('#setup_access_key').val();
					postData.secret_key = $('#setup_secret_key').val();
				}
				
				console.log('Sending AJAX to save step 1:', postData);
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: postData,
					timeout: 30000,
					success: function(response) {
						console.log('Step 1 AJAX response:', response);
						if (response && response.success) {
							// Proceed to step 2
							setSetupStatus('Credentials verified. Loading step 2…', 'success');
							console.log('Navigating to step 2...');
							window.location.href = '?page=clockwork-offloader&step=2';
						} else {
							var errorMsg = 'Failed to save step 1 data.';
							if (response && response.data && response.data.message) {
								errorMsg = response.data.message;
							}
							console.log('Step 1 error:', errorMsg);
							showSetupNotice(errorMsg, 'error');
							$button.prop('disabled', false).html(originalHtml);
						}
					},
					error: function(xhr, status, error) {
						console.log('Step 1 AJAX error:', status, error, xhr.responseText);
						var errorMsg = 'An error occurred while saving.';
						if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							errorMsg = xhr.responseJSON.data.message;
						} else if (status === 'timeout') {
							errorMsg = 'The server did not answer within 30 seconds. It may be unable to reach the storage provider; try again or check the server\'s outbound connectivity.';
						} else if (xhr.status) {
							errorMsg = 'The server returned HTTP ' + xhr.status + ' while saving step 1. Check the PHP error log for details.';
						}
						showSetupNotice(errorMsg, 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				});
				
				return false;
			});
			
			// Bucket method toggle
			$('input[name="bucket_method"]').on('change', function() {
				if ($(this).val() === 'browse') {
					$('#setup_bucket').hide();
					$('#setup_bucket_select').show();
					// Automatically load buckets when switching to browse mode
					// Use a small delay to ensure UI is updated first
					setTimeout(function() {
						loadBucketsForSetup();
					}, 100);
				} else {
					$('#setup_bucket').show();
					$('#setup_bucket_select').hide();
				}
			});
			
			// Load buckets for setup wizard (extracted to function for reuse)
			function loadBucketsForSetup() {
				var $spinner = $('#setup-bucket-spinner');
				var $select = $('#setup_bucket_select');
				
				var connectionMethod = $('input[name="connection_method"]:checked').val();
				var region = $('#setup_region').val() || 'us-east-1';
				
				if (!region) {
					showSetupNotice('Please select a region first.', 'error');
					return;
				}
				
				// Get credentials from setup data or form
				var accessKey = '';
				var secretKey = '';
				
				if (connectionMethod === 'database') {
					accessKey = $('#setup_access_key').val() || '';
					secretKey = $('#setup_secret_key').val() || '';
					
					if (!accessKey || !secretKey) {
						showSetupNotice('Please complete step 1 first.', 'error');
						return;
					}
				}
				// If wp-config method, don't send credentials - server will use get_credentials()
				
				$spinner.addClass('is-active').show();
				$select.html('<option value="">Loading buckets...</option>');
				
				var ajaxData = {
					action: 'clockwork_offloader_list_buckets',
					nonce: clockworkOffloader.nonce,
					region: region
				};
				
				// Only send credentials if using database method
				if (connectionMethod === 'database') {
					ajaxData.access_key = accessKey;
					ajaxData.secret_key = secretKey;
				}
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: ajaxData,
					success: function(response) {
						if (response.success && response.data.buckets) {
							var buckets = response.data.buckets;
							var currentBucket = $('#setup_bucket').val();
							$select.html('<option value="">Select a bucket...</option>');
							$.each(buckets, function(i, bucket) {
								var $option = $('<option></option>').val(bucket).text(bucket);
								if (bucket === currentBucket) {
									$option.prop('selected', true);
								}
								$select.append($option);
							});
							// If there's a current bucket value, ensure it's synced
							if (currentBucket) {
								$select.val(currentBucket);
								$('#setup_bucket').val(currentBucket);
							}
							if (buckets.length === 0) {
								showSetupNotice('No buckets found in your AWS account.', 'warning');
							}
						} else {
							$select.html('<option value="">Unable to list buckets</option>');
							if (response.data && response.data.bucket_scoped) {
								// Key can't list buckets: fall back to manual entry and hide browse.
								$('input[name="bucket_method"][value="manual"]').prop('checked', true).trigger('change');
								$('input[name="bucket_method"][value="browse"]').closest('div').hide();
							}
							showSetupNotice(response.data.message || 'Failed to load buckets.', 'error');
						}
					},
					error: function(xhr, status, error) {
						$select.html('<option value="">Unable to list buckets</option>');
						var errorMsg = 'An error occurred while loading buckets.';
						if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							errorMsg = xhr.responseJSON.data.message;
						} else if (status === 'timeout') {
							errorMsg = 'Request timed out. Please check your connection and try again.';
						} else if (error) {
							errorMsg = 'Network error: ' + error;
						}
						showSetupNotice(errorMsg, 'error');
					},
					complete: function() {
						$spinner.removeClass('is-active').hide();
					}
				});
			}
			
			// Bucket select change - always sync to input field
			$('#setup_bucket_select').on('change', function() {
				var bucket = $(this).val();
				// Always sync to the input field, even if it's hidden
				$('#setup_bucket').val(bucket || '');
			});
			
			// Step navigation - use document.on for better event delegation
			$(document).on('click', '.clockwork-setup-next:not(#continue-to-step2)', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var step = parseInt($(this).data('step'));
				console.log('Continue button clicked for step:', step, 'Button:', $(this), 'Button HTML:', $(this)[0].outerHTML);
				
				if (!step || isNaN(step)) {
					console.error('Invalid step number:', step, 'Element:', $(this), 'data-step attr:', $(this).attr('data-step'));
					alert('Error: Invalid step number. Please refresh the page and try again.');
					return;
				}
				
				try {
					processStep(step);
				} catch (error) {
					console.error('Error in processStep:', error);
					alert('An error occurred: ' + error.message);
				}
			});
			
			$('.clockwork-setup-back').on('click', function() {
				var step = parseInt($(this).data('step'));
				navigateToStep(step - 1);
			});
			
			// Breadcrumb navigation
			$('.clockwork-breadcrumb-link:not(.disabled)').on('click', function(e) {
				e.preventDefault();
				var step = parseInt($(this).data('step'));
				if (step < currentStep) {
					navigateToStep(step);
				}
			});
			
			// Test connection - use document.on for dynamic binding
			$(document).on('click', '#clockwork-setup-test-connection', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var $btn = $(this);
				var $spinner = $('#clockwork-setup-test-spinner');
				var $result = $('#clockwork-setup-test-result');
				
				console.log('Test Connection button clicked', $btn.length, $spinner.length, $result.length);
				
				// Verify elements exist
				if (!$btn.length) {
					console.error('Test Connection button not found!');
					return;
				}
				if (!$spinner.length) {
					console.error('Test Connection spinner not found!');
				}
				if (!$result.length) {
					console.error('Test Connection result div not found!');
				}
				
				var originalHtml = $btn.html();
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-admin-tools"></span> Testing...');
				$spinner.addClass('is-active').show();
				$result.html('');
				
				// Use dedicated test connection action (doesn't complete setup)
				var setupData = {
					action: 'clockwork_offloader_setup_test_connection',
					nonce: clockworkOffloader.nonce
				};
				
				console.log('Sending test connection request:', setupData);
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: setupData,
					timeout: 30000,
					success: function(response) {
						console.log('Test connection response:', response);
						if (response && response.success) {
							var message = response.data && response.data.message ? response.data.message : 'Connection test successful!';
							// Show prominent success message with green checkbox
							var successHtml = '<div class="notice notice-success clockwork-notice inline" style="margin: 15px 0; padding: 15px; border-left: 4px solid #00a32a; background: #fff; display: flex; align-items: center; gap: 10px;"><span style="color: #00a32a; font-size: 24px;">✓</span><p style="margin: 0; font-size: 14px; font-weight: 600; color: #00a32a;">' + message + '</p></div>';
							console.log('Showing success message:', message);
							console.log('Result element:', $result, 'length:', $result.length);
							if (response.data && response.data.warning) {
								var esc = function(t) { return $('<div>').text(t).html(); };
								successHtml += '<div class="notice notice-warning clockwork-notice inline" style="margin: 0 0 15px; padding: 15px; border-left: 4px solid #dba617; background: #fcf9e8; border-radius: 4px;">' +
									'<p style="margin: 0 0 8px; font-weight: 600;">' + esc(response.data.warning) + '</p>';
								if (response.data.policy_json) {
									successHtml += '<pre style="margin: 8px 0 0; padding: 12px; background: #1d2327; color: #f0f0f1; border-radius: 4px; font-size: 12px; overflow: auto; user-select: all;">' + esc(response.data.policy_json) + '</pre>';
								}
								if (response.data.probe_url) {
									successHtml += '<p style="margin: 8px 0 0; color: #646970; font-size: 12px;">Probe URL tried: ' + esc(response.data.probe_url) + '</p>';
								}
								successHtml += '</div>';
							}
							$result.html(successHtml);
							console.log('Result div after update:', $result.html());
							$('#clockwork-setup-complete-btn').show();
						} else {
							var errorMsg = 'Connection test failed.';
							if (response && response.data && response.data.message) {
								errorMsg = response.data.message;
							}
							console.log('Showing error message:', errorMsg);
							var errorHtml = '<div class="clockwork-notice" style="margin: 15px 0; padding: 15px; border-left: 4px solid #d63638; background: #fcf0f1; border-radius: 4px;">' +
								'<p style="margin: 0; color: #d63638; font-size: 14px; font-weight: 600;">' +
								'<span style="font-size: 18px; margin-right: 8px;">✗</span>' + errorMsg + '</p></div>';
							console.log('Error HTML:', errorHtml);
							$result.empty().append(errorHtml);
							console.log('Result div content after update:', $result.html());
						}
					},
					error: function(xhr, status, error) {
						console.error('Test connection error:', status, error, xhr);
						var errorMsg = 'An error occurred while testing the connection.';
						if (status === 'timeout') {
							errorMsg = 'Connection test timed out. Please check your credentials and try again.';
						} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							errorMsg = xhr.responseJSON.data.message;
						} else if (xhr.responseText) {
							try {
								var jsonResponse = JSON.parse(xhr.responseText);
								if (jsonResponse.data && jsonResponse.data.message) {
									errorMsg = jsonResponse.data.message;
								}
							} catch(e) {
								// Not JSON, use default message
							}
						}
						$result.html('<div class="notice notice-error"><p><strong>✗ Error:</strong> ' + errorMsg + '</p></div>');
					},
					complete: function() {
						$btn.prop('disabled', false).html(originalHtml);
						$spinner.removeClass('is-active').hide();
					}
				});
			});
			
			// Complete setup - use document.on for better event delegation
			$(document).on('click', '#clockwork-setup-complete-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var $btn = $(this);
				console.log('Complete Setup button clicked');
				$btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0;"></span> Completing...');
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: {
						action: 'clockwork_offloader_setup_step3',
						nonce: clockworkOffloader.nonce
					},
					success: function(response) {
						if (response.success && response.data.redirect_url) {
							window.location.href = response.data.redirect_url;
						} else {
							showSetupNotice(response.data.message || 'Setup completed but redirect failed.', 'error');
							$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Complete Setup');
						}
					},
					error: function() {
						showSetupNotice('An error occurred while completing setup.', 'error');
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Complete Setup');
					}
				});
			});
			
			// Handle provider selection change
			$('input[name="provider"]').on('change', function() {
				var provider = $(this).val();
				updateRegionOptions(provider);
			});
			
			// Update region options based on provider
			function updateRegionOptions(provider) {
				var $regionSelect = $('#setup_region');
				var currentValue = $regionSelect.val();
				
				$regionSelect.empty();
				$regionSelect.append('<option value="">Select a region...</option>');
				
				if (provider === 'digitalocean') {
					// Digital Ocean Spaces regions
					var doRegions = [
						{ value: 'nyc1', label: 'New York 1 - nyc1' },
						{ value: 'nyc3', label: 'New York 3 - nyc3' },
						{ value: 'sfo3', label: 'San Francisco 3 - sfo3' },
						{ value: 'sfo2', label: 'San Francisco 2 - sfo2' },
						{ value: 'ams3', label: 'Amsterdam 3 - ams3' },
						{ value: 'ams2', label: 'Amsterdam 2 - ams2' },
						{ value: 'sgp1', label: 'Singapore 1 - sgp1' },
						{ value: 'fra1', label: 'Frankfurt 1 - fra1' },
						{ value: 'blr1', label: 'Bangalore 1 - blr1' },
						{ value: 'syd1', label: 'Sydney 1 - syd1' }
					];
					doRegions.forEach(function(region) {
						var selected = (region.value === currentValue) ? ' selected' : '';
						$regionSelect.append('<option value="' + region.value + '"' + selected + '>' + region.label + '</option>');
					});
				} else {
					// AWS S3 regions
					var awsRegions = [
						{ value: 'us-east-1', label: 'US East (N. Virginia) - us-east-1' },
						{ value: 'us-east-2', label: 'US East (Ohio) - us-east-2' },
						{ value: 'us-west-1', label: 'US West (N. California) - us-west-1' },
						{ value: 'us-west-2', label: 'US West (Oregon) - us-west-2' },
						{ value: 'eu-west-1', label: 'EU (Ireland) - eu-west-1' },
						{ value: 'eu-west-2', label: 'EU (London) - eu-west-2' },
						{ value: 'eu-central-1', label: 'EU (Frankfurt) - eu-central-1' },
						{ value: 'ap-southeast-1', label: 'Asia Pacific (Singapore) - ap-southeast-1' },
						{ value: 'ap-southeast-2', label: 'Asia Pacific (Sydney) - ap-southeast-2' },
						{ value: 'ap-northeast-1', label: 'Asia Pacific (Tokyo) - ap-northeast-1' }
					];
					awsRegions.forEach(function(region) {
						var selected = (region.value === currentValue) ? ' selected' : '';
						$regionSelect.append('<option value="' + region.value + '"' + selected + '>' + region.label + '</option>');
					});
				}
			}
			
			// Initialize region options on page load
			var initialProvider = $('input[name="provider"]:checked').val() || 'aws';
			updateRegionOptions(initialProvider);
			
			function processStep(step) {
				console.log('Processing step:', step);
				var data = {
					action: 'clockwork_offloader_setup_step' + step,
					nonce: clockworkOffloader.nonce
				};
				
				if (step === 1) {
					data.provider = $('input[name="provider"]:checked').val() || 'aws';
					data.connection_method = $('input[name="connection_method"]:checked').val();
					if (data.connection_method === 'database') {
						data.access_key = $('#setup_access_key').val();
						data.secret_key = $('#setup_secret_key').val();
					}
				} else if (step === 2) {
					data.region = $('#setup_region').val();
					
					// Validate region first
					if (!data.region) {
						showSetupNotice('Please select a region.', 'error');
						return;
					}
					
					// Get bucket value - check both fields and use whichever has a value
					var bucketMethod = $('input[name="bucket_method"]:checked').val();
					var bucket = '';
					
					// Always check both fields regardless of method, in case user switched methods
					var selectBucket = $('#setup_bucket_select').val();
					var inputBucket = $('#setup_bucket').val();
					
					if (bucketMethod === 'browse') {
						// For browse mode, prefer select but fallback to input
						bucket = selectBucket || inputBucket;
						// Sync to both fields for consistency
						if (bucket) {
							$('#setup_bucket').val(bucket);
							if (selectBucket !== bucket && $('#setup_bucket_select option[value="' + bucket + '"]').length > 0) {
								$('#setup_bucket_select').val(bucket);
							}
						}
					} else {
						// For manual mode, use input field
						bucket = inputBucket || selectBucket;
						// Sync to select if it exists there
						if (bucket && $('#setup_bucket_select option[value="' + bucket + '"]').length > 0) {
							$('#setup_bucket_select').val(bucket);
						}
					}
					
					// Validate bucket
					if (!bucket || bucket.trim() === '') {
						showSetupNotice('Please enter or select a bucket name.', 'error');
						return;
					}
					
					data.bucket = bucket.trim();
					console.log('Step 2 data:', { region: data.region, bucket: data.bucket, bucketMethod: bucketMethod });
				}
				
				// Disable button to prevent double-clicks
				var $button = $('.clockwork-setup-next[data-step="' + step + '"]');
				var originalHtml = $button.html();
				$button.prop('disabled', true).html('Processing...');
				setSetupStatus(step === 2
					? 'Checking that your key can access the bucket in the selected region. This can take up to 30 seconds.'
					: 'Saving settings. This can take up to 30 seconds.', 'info');
				
				console.log('Sending AJAX request for step', step, 'with data:', data);
				
				$.ajax({
					url: clockworkOffloader.ajaxUrl,
					type: 'POST',
					data: data,
					timeout: 30000,
					success: function(response) {
						console.log('Step', step, 'AJAX response:', response);
						if (response.success) {
							setSetupStatus('Done. Loading the next step…', 'success');
							if (response.data.next_step) {
								navigateToStep(response.data.next_step);
							} else {
								navigateToStep(step + 1);
							}
						} else {
							var errorMsg = response.data && response.data.message ? response.data.message : 'Validation failed.';
							console.error('Step', step, 'validation failed:', errorMsg);
							
							// Handle region mismatch - try to extract correct region and suggest it
							if (response.data && response.data.error_code === 'region_mismatch') {
								// Check if error message contains a region suggestion
								var regionMatch = errorMsg.match(/region "([^"]+)"/i);
								if (regionMatch && regionMatch[1]) {
									var correctRegion = regionMatch[1];
									// Try to find and select the correct region in the dropdown
									var $regionSelect = $('#setup_region');
									if ($regionSelect.length) {
										var $correctOption = $regionSelect.find('option[value="' + correctRegion + '"]');
										if ($correctOption.length) {
											errorMsg += ' <br><strong>Tip:</strong> Try selecting "' + $correctOption.text() + '" from the region dropdown.';
										}
									}
								}
							}
							
							showSetupNotice(errorMsg, 'error');
							$button.prop('disabled', false).html(originalHtml);
						}
					},
					error: function(xhr, status, error) {
						var errorMsg = 'An error occurred. Please try again.';
						if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							errorMsg = xhr.responseJSON.data.message;
						} else if (status === 'timeout') {
							errorMsg = 'The server did not answer within 30 seconds. It may be unable to reach the storage provider; try again or check the server\'s outbound connectivity.';
						} else if (xhr.status === 0) {
							errorMsg = 'Network error. Please check your connection.';
						} else if (xhr.status) {
							errorMsg = 'The server returned HTTP ' + xhr.status + '. Check the PHP error log for details.';
						}
						console.error('Step ' + step + ' error:', status, error, xhr);
						showSetupNotice(errorMsg, 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				});
			}
			
			function navigateToStep(step) {
				if (step < 1 || step > 3) return;
				var url = new URL(clockworkOffloader.adminUrl + 'options-general.php');
				url.searchParams.set('page', 'clockwork-offloader');
				url.searchParams.set('step', step);
				window.location.href = url.toString();
			}
			
			// Inline status line under the wizard buttons, so a slow or failing
			// request never looks like a frozen page.
			function setSetupStatus(message, state) {
				var $actions = $('.clockwork-setup-step .clockwork-setup-actions').first();
				if (!$actions.length) { return; }
				var $status = $actions.find('.clockwork-setup-status');
				if (!$status.length) {
					$status = $('<p class="clockwork-setup-status" style="flex-basis: 100%; width: 100%; margin: 12px 0 0; font-size: 13px;"></p>');
					$actions.css('flex-wrap', 'wrap').append($status);
				}
				if (!message) { $status.hide().empty(); return; }
				var color = state === 'error' ? '#d63638' : (state === 'success' ? '#00a32a' : '#646970');
				var prefix = state === 'error' ? '✕ ' : (state === 'success' ? '✓ ' : '⏳ ');
				$status.css('color', color).html(prefix + message).show();
			}

			function showSetupNotice(message, type) {
				type = type || 'info';
				// Replace any previous wizard notice so errors don't stack up.
				$('.clockwork-setup-content > .clockwork-setup-notice').remove();
				var $notice = $('<div class="notice notice-' + type + ' is-dismissible clockwork-setup-notice"><p>' + message + '</p></div>');
				$('.clockwork-setup-content').prepend($notice);
				if ($notice[0] && $notice[0].scrollIntoView) {
					$notice[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
				if (type === 'error') {
					setSetupStatus(message, 'error');
				}
				// Errors stay until the next action; informational notices fade.
				if (type !== 'error') {
					setTimeout(function() {
						$notice.fadeOut(function() {
							$(this).remove();
						});
					}, 5000);
				}
			}
		}
		
		// Migration Popup handlers
		var $migrationPopup = $('#clockwork-migration-popup');
		var $migrationPopupClose = $('#clockwork-migration-popup-close');
		var $migrationPopupDismiss = $('#clockwork-migration-popup-dismiss');
		var $migrationPopupDontShow = $('#clockwork-migration-popup-dont-show');
		
		// Close popup on X button
		$migrationPopupClose.on('click', function() {
			dismissMigrationPopup();
		});
		
		// Dismiss button
		$migrationPopupDismiss.on('click', function() {
			dismissMigrationPopup();
		});
		
		// Close on background click
		$migrationPopup.on('click', function(e) {
			if ($(e.target).is($migrationPopup)) {
				dismissMigrationPopup();
			}
		});
		
		// Dismiss popup function
		function dismissMigrationPopup() {
			var dontShowAgain = $migrationPopupDontShow.is(':checked');
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_dismiss_migration_popup',
					nonce: clockworkOffloader.nonce,
					dont_show_again: dontShowAgain ? 'true' : 'false'
				},
				success: function(response) {
					$migrationPopup.fadeOut(300, function() {
						$(this).remove();
					});
				},
				error: function() {
					// Still close the popup even if AJAX fails
					$migrationPopup.fadeOut(300, function() {
						$(this).remove();
					});
				}
			});
		}
		
		// Dismiss migrator plugin notice
		$(document).on('click', '.clockwork-dismiss-migrator-notice', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var $notice = $(this).closest('.clockwork-migrator-deactivate-notice');
			
			// Hide immediately for better UX
			$notice.fadeOut(300);
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_dismiss_migrator_notice',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					// Remove after fade completes
					setTimeout(function() {
						$notice.remove();
					}, 300);
				},
				error: function() {
					// Remove after fade completes even if AJAX fails
					setTimeout(function() {
						$notice.remove();
					}, 300);
				}
			});
		});
		
		// Force all media to use S3 URLs
		// URL rewrite button handlers - use document.on for better event delegation
		$(document).on('click', '#clockwork-force-s3-urls', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			console.log('Force S3 URLs button clicked', {
				button: $(this),
				ajaxUrl: typeof clockworkOffloader !== 'undefined' ? clockworkOffloader.ajaxUrl : 'undefined',
				nonce: typeof clockworkOffloader !== 'undefined' ? clockworkOffloader.nonce : 'undefined'
			});
			
			// Safety check
			if (typeof clockworkOffloader === 'undefined') {
				console.error('clockworkOffloader is not defined!');
				alert('Error: Plugin JavaScript not loaded. Please refresh the page.');
				return;
			}
			
			var $button = $(this);
			var $spinner = $('.clockwork-url-rewrite-spinner');
			var $percentageEl = $('.clockwork-rewrite-percentage');
			var $countEl = $('.clockwork-rewrite-count');
			var $progressBar = $('.clockwork-rewrite-progress-bar');
			
			// Debug: Check if elements exist
			if ($percentageEl.length === 0) {
				console.warn('Percentage element not found');
			}
			if ($countEl.length === 0) {
				console.warn('Count element not found');
			}
			if ($progressBar.length === 0) {
				console.warn('Progress bar element not found');
			}
			
			if (!confirm('Are you sure you want to force all media URLs to use S3 URLs? This will enable URL rewriting for all offloaded media.')) {
				return;
			}
			
			$button.prop('disabled', true);
			$spinner.css('visibility', 'visible');
			
			// Get current values (with fallbacks if elements don't exist)
			var currentPercentage = $percentageEl.length > 0 ? parseInt($percentageEl.text().replace('%', '')) || 0 : 0;
			var countMatch = $countEl.length > 0 ? $countEl.text().match(/([\d,]+)\s+of/) : null;
			var currentCount = countMatch ? parseInt(countMatch[1].replace(/,/g, '')) : 0;
			
			console.log('Current values:', {
				percentage: currentPercentage,
				count: currentCount
			});
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_force_s3_urls',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					console.log('AJAX response:', response);
					
					if (response.success) {
						var targetPercentage = response.data.percentage || 0;
						var targetCount = response.data.offloaded_count || 0;
						var totalCount = response.data.total_attachments || 0;
						
						// Animate counter (only if elements exist)
						if ($percentageEl.length > 0) {
							animateCounter($percentageEl, currentPercentage, targetPercentage, '%', 800);
							$percentageEl.css('color', '#00a32a');
						}
						if ($countEl.length > 0) {
							animateCounter($countEl, currentCount, targetCount, ' of ' + totalCount.toLocaleString() + ' media items', 800);
						}
						if ($progressBar.length > 0) {
							animateProgressBar($progressBar, currentPercentage, targetPercentage, 800);
							$progressBar.css('background', '#00a32a');
						}
						
						// Show success message
						showNotice(response.data.message, 'success');
						
						// Update statistics immediately instead of reloading
						if (typeof updateBulkStats === 'function') {
							setTimeout(updateBulkStats, 500);
						}
					} else {
						console.error('AJAX error:', response.data);
						showNotice(response.data.message || 'An error occurred.', 'error');
						$button.prop('disabled', false);
						$spinner.css('visibility', 'hidden');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX request failed:', {
						status: status,
						error: error,
						response: xhr.responseText,
						statusCode: xhr.status,
						readyState: xhr.readyState
					});
					
					var errorMsg = 'An error occurred while updating settings.';
					if (xhr.responseText) {
						try {
							var jsonResponse = JSON.parse(xhr.responseText);
							if (jsonResponse.data && jsonResponse.data.message) {
								errorMsg = jsonResponse.data.message;
							}
						} catch(e) {
							// Not JSON, use default
						}
					}
					
					showNotice(errorMsg + ' Please check the console for details.', 'error');
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				},
				complete: function() {
					console.log('AJAX request completed');
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				}
			});
		});
		
		// Switch back to local URLs
		$(document).on('click', '#clockwork-switch-back-local', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			console.log('Switch Back to Local URLs button clicked');
			
			var $button = $(this);
			var $spinner = $('.clockwork-url-rewrite-spinner');
			var $percentageEl = $('.clockwork-rewrite-percentage');
			var $countEl = $('.clockwork-rewrite-count');
			var $progressBar = $('.clockwork-rewrite-progress-bar');
			
			// Debug: Check if elements exist
			if ($percentageEl.length === 0) {
				console.warn('Percentage element not found');
			}
			if ($countEl.length === 0) {
				console.warn('Count element not found');
			}
			if ($progressBar.length === 0) {
				console.warn('Progress bar element not found');
			}
			
			if (!confirm('Are you sure you want to switch all media URLs back to local URLs? This will disable URL rewriting.')) {
				return;
			}
			
			$button.prop('disabled', true);
			$spinner.css('visibility', 'visible');
			
			// Get current values (with fallbacks if elements don't exist)
			var currentPercentage = $percentageEl.length > 0 ? parseInt($percentageEl.text().replace('%', '')) || 0 : 0;
			var countMatch = $countEl.length > 0 ? $countEl.text().match(/([\d,]+)\s+of/) : null;
			var currentCount = countMatch ? parseInt(countMatch[1].replace(/,/g, '')) : 0;
			
			console.log('Current values:', {
				percentage: currentPercentage,
				count: currentCount
			});
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_switch_back_local',
					nonce: clockworkOffloader.nonce
				},
				success: function(response) {
					console.log('AJAX response:', response);
					
					if (response.success) {
						var targetPercentage = 0; // Always 0 when switching to local
						var targetCount = 0;
						var totalCount = response.data.total_attachments || 0;
						
						// Animate counter down (only if elements exist)
						if ($percentageEl.length > 0) {
							animateCounter($percentageEl, currentPercentage, targetPercentage, '%', 800);
						}
						if ($countEl.length > 0) {
							animateCounter($countEl, currentCount, targetCount, ' of ' + totalCount.toLocaleString() + ' media items', 800);
						}
						if ($progressBar.length > 0) {
							animateProgressBar($progressBar, currentPercentage, targetPercentage, 800);
							$progressBar.css('background', '#2271b1');
						}
						
						// Update color (only if element exists)
						if ($percentageEl.length > 0) {
							$percentageEl.css('color', '#646970');
						}
						
						// Show success message
						showNotice(response.data.message, 'success');
						
						// Update statistics immediately instead of reloading
						if (typeof updateBulkStats === 'function') {
							setTimeout(updateBulkStats, 500);
						}
					} else {
						console.error('AJAX error:', response.data);
						showNotice(response.data.message || 'An error occurred.', 'error');
						$button.prop('disabled', false);
						$spinner.css('visibility', 'hidden');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX request failed:', {
						status: status,
						error: error,
						response: xhr.responseText
					});
					showNotice('An error occurred while updating settings. Please check the console for details.', 'error');
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				}
			});
		});
		
		// Counter animation function
		function animateCounter($element, start, end, suffix, duration) {
			var startTime = null;
			var isPercentage = suffix === '%';
			var totalCount = null;
			
			// Extract total count from current text if it's a count element
			if (!isPercentage) {
				var match = $element.text().match(/of\s+([\d,]+)\s+media items/);
				if (match) {
					totalCount = match[1].replace(/,/g, '');
				}
			}
			
			function animate(currentTime) {
				if (startTime === null) startTime = currentTime;
				var progress = Math.min((currentTime - startTime) / duration, 1);
				
				// Easing function (ease-out)
				var easeOut = 1 - Math.pow(1 - progress, 3);
				var current = Math.round(start + (end - start) * easeOut);
				
				if (isPercentage) {
					$element.text(current + suffix);
				} else {
					// For count, preserve the "of X media items" structure
					if (totalCount) {
						$element.text(current.toLocaleString() + ' of ' + parseInt(totalCount).toLocaleString() + ' media items');
					} else {
						$element.text(current.toLocaleString() + suffix);
					}
				}
				
				if (progress < 1) {
					requestAnimationFrame(animate);
				} else {
					// Final value
					if (isPercentage) {
						$element.text(end + suffix);
					} else {
						if (totalCount) {
							$element.text(end.toLocaleString() + ' of ' + parseInt(totalCount).toLocaleString() + ' media items');
						} else {
							$element.text(end.toLocaleString() + suffix);
						}
					}
				}
			}
			
			requestAnimationFrame(animate);
		}
		
		// Progress bar animation function
		function animateProgressBar($element, start, end, duration) {
			var startTime = null;
			
			function animate(currentTime) {
				if (startTime === null) startTime = currentTime;
				var progress = Math.min((currentTime - startTime) / duration, 1);
				
				// Easing function (ease-out)
				var easeOut = 1 - Math.pow(1 - progress, 3);
				var current = start + (end - start) * easeOut;
				
				$element.css('width', current + '%');
				
				if (progress < 1) {
					requestAnimationFrame(animate);
				} else {
					$element.css('width', end + '%');
				}
			}
			
			requestAnimationFrame(animate);
		}
		
		// URL Verification - Confirm URLs Are Using S3
		$(document).on('click', '#clockwork-verify-s3-urls', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $spinner = $('.clockwork-url-verify-spinner');
			var $resultsDiv = $('#url-verification-results');
			var $notice = $('#url-verification-notice');
			var $message = $('#url-verification-message');
			var $details = $('#url-verification-details');
			
			$button.prop('disabled', true);
			$spinner.css('visibility', 'visible');
			$resultsDiv.hide();
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_verify_urls',
					nonce: clockworkOffloader.nonce,
					expected_location: 's3'
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						$notice.removeClass('notice-success notice-warning notice-error');
						
						if (data.all_correct) {
							$notice.addClass('notice-success');
							$message.html('<strong>✓ All URLs are correctly pointing to S3/CDN.</strong>');
						} else {
							$notice.addClass('notice-warning');
							$message.html('<strong>⚠ Some URLs may not be pointing to S3.</strong> ' + data.message);
						}
						
						// Show sample URLs if available
						if (data.sample_urls && data.sample_urls.length > 0) {
							var detailsHtml = '<p><strong>Sample URLs:</strong></p><ul style="margin-left: 20px; font-size: 12px;">';
							data.sample_urls.forEach(function(url) {
								var icon = url.is_s3 ? '✓' : '✗';
								var color = url.is_s3 ? '#00a32a' : '#d63638';
								detailsHtml += '<li style="margin: 5px 0; color: ' + color + ';">' + icon + ' ' + url.url + '</li>';
							});
							detailsHtml += '</ul>';
							$details.html(detailsHtml);
						}
						
						$resultsDiv.show();
					} else {
						showNotice(response.data.message || 'Verification failed.', 'error');
					}
				},
				error: function() {
					showNotice('An error occurred during verification.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				}
			});
		});
		
		// URL Verification - Confirm URLs Are Using Server
		$(document).on('click', '#clockwork-verify-local-urls', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $spinner = $('.clockwork-url-verify-spinner');
			var $resultsDiv = $('#url-verification-results');
			var $notice = $('#url-verification-notice');
			var $message = $('#url-verification-message');
			var $details = $('#url-verification-details');
			
			$button.prop('disabled', true);
			$spinner.css('visibility', 'visible');
			$resultsDiv.hide();
			
			$.ajax({
				url: clockworkOffloader.ajaxUrl,
				type: 'POST',
				data: {
					action: 'clockwork_offloader_verify_urls',
					nonce: clockworkOffloader.nonce,
					expected_location: 'local'
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						$notice.removeClass('notice-success notice-warning notice-error');
						
						if (data.all_correct) {
							$notice.addClass('notice-success');
							$message.html('<strong>✓ All URLs are correctly pointing to your local server.</strong>');
						} else {
							$notice.addClass('notice-warning');
							$message.html('<strong>⚠ Some URLs may not be pointing to your server.</strong> ' + data.message);
						}
						
						// Show sample URLs if available
						if (data.sample_urls && data.sample_urls.length > 0) {
							var detailsHtml = '<p><strong>Sample URLs:</strong></p><ul style="margin-left: 20px; font-size: 12px;">';
							data.sample_urls.forEach(function(url) {
								var icon = url.is_local ? '✓' : '✗';
								var color = url.is_local ? '#00a32a' : '#d63638';
								detailsHtml += '<li style="margin: 5px 0; color: ' + color + ';">' + icon + ' ' + url.url + '</li>';
							});
							detailsHtml += '</ul>';
							$details.html(detailsHtml);
						}
						
						$resultsDiv.show();
					} else {
						showNotice(response.data.message || 'Verification failed.', 'error');
					}
				},
				error: function() {
					showNotice('An error occurred during verification.', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				}
			});
		});
		
	});
	
})(jQuery);


