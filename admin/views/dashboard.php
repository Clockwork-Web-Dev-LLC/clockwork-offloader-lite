<?php
/**
 * Dashboard View
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
}

$s3_service = new Clockwork_Offloader_S3_Service();
$connection_test = get_transient( 'clockwork_offloader_s3_connection_test' );

// Check if credentials are configured (wp-config.php or database)
// $has_credentials is passed from render_dashboard() method
if ( ! isset( $has_credentials ) ) {
	$credentials = $s3_service->get_credentials();
	$has_credentials = ! empty( $credentials['access_key'] ) && ! empty( $credentials['secret_key'] );
	
	// Also check if migration was completed - if so, credentials should be available
	$migration_complete = get_option( 'clockwork_offloader_migration_complete', false );
	if ( $migration_complete && ! $has_credentials ) {
		// Migration was completed but credentials aren't detected - check wp-config.php directly
		$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
		if ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) ) {
			$has_credentials = true;
		}
	}
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php settings_errors( 'clockwork_offloader_settings' ); ?>
	
	<?php 
	// Only show this error if migration hasn't been completed
	// If migration was completed, credentials should be available (even if not detected yet)
	$migration_complete = get_option( 'clockwork_offloader_migration_complete', false );
	if ( ! $has_credentials && ! $migration_complete ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Please enter Access Key and Secret Key first.', 'clockwork-offloader' ); ?></p>
		</div>
	<?php endif; ?>
	
	<?php
	// Check if we should show the "deactivate migrator plugin" notice
	// Show if: migrator is active, files are offloaded (migration successful), and user hasn't dismissed
	$migrator_active = is_plugin_active( 'clockwork-offloader-migrator/clockwork-offloader-migrator.php' );
	$has_offloaded_files = isset( $stats['total_offloaded'] ) && $stats['total_offloaded'] > 0;
	$user_id = get_current_user_id();
	$dismissed_migrator_notice = get_user_meta( $user_id, 'clockwork_offloader_dismiss_migrator_notice', true );
	
	$show_migrator_notice = $migrator_active && $has_offloaded_files && ! $dismissed_migrator_notice;
	?>
	
	<?php if ( $show_migrator_notice ) : ?>
		<div class="notice notice-warning is-dismissible clockwork-migrator-deactivate-notice">
			<p>
				<strong><?php esc_html_e( 'Migration Complete!', 'clockwork-offloader' ); ?></strong>
				<?php esc_html_e( 'Your migration was successful. We recommend deactivating the Clockwork Offloader Migrator plugin as it is no longer needed.', 'clockwork-offloader' ); ?>
			</p>
			<button type="button" class="notice-dismiss clockwork-dismiss-migrator-notice">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'clockwork-offloader' ); ?></span>
			</button>
		</div>
	<?php endif; ?>
	
	<?php
	// Show license limit warnings if Pro is active and license is valid
	if ( class_exists( 'Clockwork_Offloader_Lite_Restrictions' ) && Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
		if ( class_exists( 'Clockwork_Offloader_License_Limits' ) && class_exists( 'Clockwork_Offloader_Pro_License' ) ) {
			$license = Clockwork_Offloader_Pro_License::get_instance();
			$item_limit = $license->get_item_limit();
			
			if ( $license->is_license_valid() && $item_limit > 0 ) {
				$limits = new Clockwork_Offloader_License_Limits();
				$usage_stats = $limits->get_usage_stats( true ); // Include all sites
				
				$percentage = $usage_stats['usage_percentage'];
				$current = $usage_stats['current_count'];
				$limit = $usage_stats['item_limit'];
				$is_in_grace = $usage_stats['is_in_grace_period'];
				$grace_limit = $usage_stats['grace_period_limit'];
				
				// Show warning if at 80% or above
				if ( $percentage >= 80 ) {
					$notice_class = 'notice-error';
					if ( $current >= $grace_limit ) {
						$notice_class = 'notice-error';
						$message = sprintf(
							/* translators: %1$d: current count, %2$d: grace limit */
							__( '<strong>Item Limit Exceeded!</strong> You have %1$d items, which exceeds your grace period limit of %2$d items. Please upgrade your license to continue offloading files.', 'clockwork-offloader' ),
							number_format_i18n( $current ),
							number_format_i18n( $grace_limit )
						);
					} elseif ( $is_in_grace ) {
						$notice_class = 'notice-warning';
						$message = sprintf(
							/* translators: %1$d: current count, %2$d: limit, %3$d: grace limit */
							__( '<strong>Grace Period Active:</strong> You have %1$d items (limit: %2$d). Please upgrade before reaching %3$d items to avoid being blocked.', 'clockwork-offloader' ),
							number_format_i18n( $current ),
							number_format_i18n( $limit ),
							number_format_i18n( $grace_limit )
						);
					} elseif ( $percentage >= 100 ) {
						$notice_class = 'notice-warning';
						$message = sprintf(
							/* translators: %1$d: current count, %2$d: limit */
							__( '<strong>Item Limit Reached!</strong> You have %1$d items (limit: %2$d). You are now in a grace period. Please upgrade soon.', 'clockwork-offloader' ),
							number_format_i18n( $current ),
							number_format_i18n( $limit )
						);
					} elseif ( $percentage >= 90 ) {
						$notice_class = 'notice-warning';
						$message = sprintf(
							/* translators: %1$d: current count, %2$d: limit, %s: percentage */
							__( '<strong>Approaching Item Limit:</strong> You have %1$d of %2$d items (%3$s%% used). Consider upgrading soon.', 'clockwork-offloader' ),
							number_format_i18n( $current ),
							number_format_i18n( $limit ),
							number_format_i18n( $percentage, 1 )
						);
					} else {
						$notice_class = 'notice-info';
						$message = sprintf(
							/* translators: %1$d: current count, %2$d: limit, %s: percentage */
							__( '<strong>Item Usage:</strong> You have %1$d of %2$d items (%3$s%% used).', 'clockwork-offloader' ),
							number_format_i18n( $current ),
							number_format_i18n( $limit ),
							number_format_i18n( $percentage, 1 )
						);
					}
					?>
					<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
						<p>
							<?php echo wp_kses_post( $message ); ?>
							<?php if ( $percentage >= 80 ) : ?>
								<a href="https://aaronreimann.com" target="_blank" class="button button-primary" style="margin-left: 10px;">
									<?php esc_html_e( 'Upgrade License', 'clockwork-offloader' ); ?>
								</a>
							<?php endif; ?>
						</p>
					</div>
					<?php
				}
			}
		}
	}
	?>
	
	<?php include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/partials/get-started.php'; ?>

	<?php
	$dashboard_settings = Clockwork_Offloader_Settings_Helper::get_settings();
	$delete_after_upload = ! empty( $dashboard_settings['delete_after_upload'] );
	$rewrite_urls = ! empty( $dashboard_settings['rewrite_urls'] );
	?>

	<div class="clockwork-offloader-stats">
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Statistics', 'clockwork-offloader' ); ?></h2>
			</div>
			<div class="inside">
				<div class="stat-boxes">
					<div class="stat-box">
						<h3><?php esc_html_e( 'Total Attachments', 'clockwork-offloader' ); ?></h3>
						<p class="stat-number"><?php echo esc_html( number_format_i18n( $stats['total_attachments'] ) ); ?></p>
					</div>
					
					<div class="stat-box">
						<h3><?php esc_html_e( 'Offloaded Attachments', 'clockwork-offloader' ); ?></h3>
						<p class="stat-number"><?php echo esc_html( number_format_i18n( $stats['offloaded_attachments'] ) ); ?></p>
					</div>
					
					<div class="stat-box">
						<h3><?php esc_html_e( 'Total Files Offloaded', 'clockwork-offloader' ); ?></h3>
						<p class="stat-number"><?php echo esc_html( number_format_i18n( $stats['total_offloaded'] ) ); ?></p>
						<p class="description" style="font-size: 11px; margin-top: 5px; color: #646970;">
							<?php esc_html_e( 'Includes all image sizes', 'clockwork-offloader' ); ?>
						</p>
					</div>
					
					<div class="stat-box">
						<?php if ( $delete_after_upload ) : ?>
							<h3><?php esc_html_e( 'Disk Space Saved', 'clockwork-offloader' ); ?></h3>
							<p class="stat-number" style="color: #00a32a;"><?php echo esc_html( size_format( $stats['total_size'], 2 ) ); ?></p>
							<p class="description" style="font-size: 11px; margin-top: 5px; color: #00a32a;">
								<i class="fa-solid fa-check"></i> <?php esc_html_e( 'Local files deleted from server', 'clockwork-offloader' ); ?>
							</p>
						<?php else : ?>
							<h3><?php esc_html_e( 'Total Storage on S3', 'clockwork-offloader' ); ?></h3>
							<p class="stat-number"><?php echo esc_html( size_format( $stats['total_size'], 2 ) ); ?></p>
							<p class="description" style="font-size: 11px; margin-top: 5px; color: #646970;">
								<i class="fa-solid fa-hard-drive"></i> <?php esc_html_e( 'Local disk saved: 0 B (files kept on server)', 'clockwork-offloader' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="clockwork-stats-breakdown" style="margin-top: 20px; padding: 14px 18px; background: #fbfbfc; border: 1px solid #e2e4e7; border-radius: 4px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; font-size: 13px;">
					<div>
						<strong style="color: #1d2327;"><i class="fa-solid fa-hard-drive" style="color: #2271b1; margin-right: 6px;"></i><?php esc_html_e( 'Local Server Disk:', 'clockwork-offloader' ); ?></strong>
						<?php if ( $delete_after_upload ) : ?>
							<span style="color: #00a32a; font-weight: 600; margin-left: 4px;">
								<?php printf( esc_html__( '%s freed from web server disk', 'clockwork-offloader' ), esc_html( size_format( $stats['total_size'], 2 ) ) ); ?>
							</span>
							<span class="description" style="color: #646970; margin-left: 4px;">
								(<?php esc_html_e( 'Delete After Upload is ON', 'clockwork-offloader' ); ?>)
							</span>
						<?php else : ?>
							<span style="color: #646970; margin-left: 4px;">
								<?php esc_html_e( '0 B freed — local copies are preserved on your web server disk.', 'clockwork-offloader' ); ?>
							</span>
							<a href="<?php echo esc_url( admin_url( 'options-general.php?page=clockwork-offloader&tab=settings' ) ); ?>" style="margin-left: 6px; font-size: 12px;">
								<?php esc_html_e( 'Configure in Settings →', 'clockwork-offloader' ); ?>
							</a>
						<?php endif; ?>
					</div>
					<div>
						<strong style="color: #1d2327;"><i class="fa-solid fa-bolt" style="color: #2271b1; margin-right: 6px;"></i><?php esc_html_e( 'Web Server Delivery:', 'clockwork-offloader' ); ?></strong>
						<?php if ( $rewrite_urls ) : ?>
							<span style="color: #00a32a; font-weight: 600; margin-left: 4px;">
								<i class="fa-solid fa-check"></i> <?php esc_html_e( 'Offloaded to S3/CDN (saving server bandwidth)', 'clockwork-offloader' ); ?>
							</span>
						<?php else : ?>
							<span style="color: #9a6700; font-weight: 600; margin-left: 4px;">
								<i class="fa-solid fa-triangle-exclamation"></i> <?php esc_html_e( 'Served by local web server (Rewrite URLs is OFF)', 'clockwork-offloader' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<?php
	// Check if Migrator plugin is active
	$migrator_active = is_plugin_active( 'clockwork-offloader-migrator/clockwork-offloader-migrator.php' );
	$migrator_url = admin_url( 'tools.php?page=clockwork-offload-migrator' );
	?>
	
	<?php if ( $migrator_active ) : ?>
		<div class="clockwork-offloader-tools" style="margin-top: 20px;">
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Migration Tools', 'clockwork-offloader' ); ?></h2>
				</div>
				<div class="inside">
					<p style="margin-bottom: 15px;">
						<?php esc_html_e( 'Migrate from other offload plugins to Clockwork Offloader.', 'clockwork-offloader' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( $migrator_url ); ?>" class="button button-primary">
							<span class="dashicons dashicons-migrate" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Open Migration Tool', 'clockwork-offloader' ); ?>
						</a>
						<a href="<?php echo esc_url( $migrator_url ); ?>" class="button button-secondary" style="margin-left: 10px;">
							<?php esc_html_e( 'View in Tools Menu', 'clockwork-offloader' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>
	
	<div class="clockwork-offloader-status">
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'System Status', 'clockwork-offloader' ); ?></h2>
			</div>
			<div class="inside">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'S3 Connection', 'clockwork-offloader' ); ?></th>
						<td>
							<span id="connection-status" class="status-indicator">
								<?php if ( $connection_test ) : ?>
									<span class="status-success"><?php esc_html_e( 'Connected', 'clockwork-offloader' ); ?></span>
								<?php else : ?>
									<span class="status-unknown"><?php esc_html_e( 'Not tested', 'clockwork-offloader' ); ?></span>
								<?php endif; ?>
							</span>
							<button type="button" class="button button-secondary" id="test-connection">
								<?php esc_html_e( 'Test Connection', 'clockwork-offloader' ); ?>
							</button>
						</td>
					</tr>
					
					<tr>
						<th scope="row"><?php esc_html_e( 'AWS SDK', 'clockwork-offloader' ); ?></th>
						<td>
							<?php if ( class_exists( 'Aws\S3\S3Client' ) ) : ?>
								<span class="status-success"><?php esc_html_e( 'Installed', 'clockwork-offloader' ); ?></span>
							<?php else : ?>
								<span class="status-error"><?php esc_html_e( 'Not installed', 'clockwork-offloader' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Please run "composer install" in the plugin directory.', 'clockwork-offloader' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</div>

	<?php if ( $has_credentials && ! empty( $credentials['bucket'] ) && isset( $url_preview ) ) : ?>
		<div class="clockwork-offloader-url-preview" style="margin-top: 20px;">
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'URL Preview', 'clockwork-offloader' ); ?></h2>
				</div>
				<div class="inside">
					<p style="margin-bottom: 15px; color: #646970;">
						<?php esc_html_e( 'When a media URL is rewritten, it will use the following structure based on the current Storage and Delivery settings:', 'clockwork-offloader' ); ?>
					</p>
					
					<div class="clockwork-url-preview-container" style="background: #f6f7f7; padding: 20px; border-radius: 4px; margin: 15px 0;">
						<div class="clockwork-url-components" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.8; cursor: default;">
							<?php
							$comp = $url_preview['components'];
							$show_bucket = ! empty( $comp['bucket'] );
							$show_prefix = ! empty( $comp['prefix'] );
							?>
							
							<!-- Scheme -->
							<div style="display: inline-flex; align-items: center; cursor: default;">
								<span style="background: #e5e5e5; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #50575e; margin-right: 6px;"><?php esc_html_e( 'Scheme', 'clockwork-offloader' ); ?></span>
								<span style="background: #fff; padding: 6px 10px; border-radius: 3px; border: 1px solid #dcdcde; cursor: text; user-select: text;"><?php echo esc_html( $comp['scheme'] ); ?></span>
							</div>
							
							<!-- Domain -->
							<div style="display: inline-flex; align-items: center; cursor: default;">
								<span style="background: #e5e5e5; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #50575e; margin-right: 6px;"><?php esc_html_e( 'Domain', 'clockwork-offloader' ); ?></span>
								<span style="background: #fff; padding: 6px 10px; border-radius: 3px; border: 1px solid #dcdcde; cursor: text; user-select: text;"><?php echo esc_html( $comp['domain'] ); ?></span>
							</div>
							
							<?php if ( $show_bucket ) : ?>
								<!-- Bucket -->
								<div style="display: inline-flex; align-items: center; cursor: default;">
									<span style="background: #e5e5e5; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #50575e; margin-right: 6px;"><?php esc_html_e( 'Bucket', 'clockwork-offloader' ); ?></span>
									<span style="background: #fff; padding: 6px 10px; border-radius: 3px; border: 1px solid #dcdcde; cursor: text; user-select: text;"><?php echo esc_html( $comp['bucket'] ); ?></span>
								</div>
							<?php endif; ?>
							
							<?php if ( $show_prefix ) : ?>
								<!-- Prefix -->
								<div style="display: inline-flex; align-items: center; cursor: default;">
									<span style="background: #e5e5e5; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #50575e; margin-right: 6px;"><?php esc_html_e( 'Prefix', 'clockwork-offloader' ); ?></span>
									<span style="background: #fff; padding: 6px 10px; border-radius: 3px; border: 1px solid #dcdcde; cursor: text; user-select: text;"><?php echo esc_html( $comp['prefix'] ); ?></span>
								</div>
							<?php endif; ?>
							
							<!-- Path -->
							<div style="display: inline-flex; align-items: center; cursor: default;">
								<span style="background: #e5e5e5; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #50575e; margin-right: 6px;"><?php esc_html_e( 'Path', 'clockwork-offloader' ); ?></span>
								<span style="background: #fff; padding: 6px 10px; border-radius: 3px; border: 1px solid #dcdcde; cursor: text; user-select: text;"><?php echo esc_html( $comp['path'] ); ?></span>
							</div>
							
							<!-- Year & Month -->
							<div style="display: inline-flex; align-items: center; cursor: default;">
								<span style="background: #e5e5e5; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #50575e; margin-right: 6px;"><?php esc_html_e( 'Year & Month', 'clockwork-offloader' ); ?></span>
								<span style="background: #fff; padding: 6px 10px; border-radius: 3px; border: 1px solid #dcdcde; cursor: text; user-select: text;"><?php echo esc_html( $comp['year_month'] ); ?></span>
							</div>
							
							<!-- Filename -->
							<div style="display: inline-flex; align-items: center; cursor: default;">
								<span style="background: #e5e5e5; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #50575e; margin-right: 6px;"><?php esc_html_e( 'Filename', 'clockwork-offloader' ); ?></span>
								<span style="background: #fff; padding: 6px 10px; border-radius: 3px; border: 1px solid #dcdcde; cursor: text; user-select: text;"><?php echo esc_html( $comp['filename'] ); ?></span>
							</div>
						</div>
						
						<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dcdcde;">
							<p style="margin: 0; font-size: 12px; color: #646970;">
								<strong><?php esc_html_e( 'Full URL:', 'clockwork-offloader' ); ?></strong>
								<code style="background: #fff; padding: 4px 8px; border-radius: 3px; border: 1px solid #dcdcde; display: inline-block; margin-left: 8px; font-size: 13px; word-break: break-all; cursor: text; user-select: text;"><?php echo esc_html( $url_preview['full_url'] ); ?></code>
							</p>
						</div>
					</div>
					
					<?php if ( $url_preview['has_cdn'] ) : ?>
						<div class="notice notice-info inline" style="margin: 15px 0 0 0;">
							<p>
								<strong><?php esc_html_e( 'CDN Domain Active:', 'clockwork-offloader' ); ?></strong>
								<?php esc_html_e( 'URLs will use your configured CDN domain instead of the storage provider domain.', 'clockwork-offloader' ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>