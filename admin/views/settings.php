<?php
/**
 * Settings View - 2-Column Storage vs. Delivery
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

// Check if network mode is enabled (multisite only)
$is_network_mode = false;
$settings_source = 'site';
if ( is_multisite() && class_exists( 'Clockwork_Offloader_Settings_Helper' ) ) {
	$is_network_mode = Clockwork_Offloader_Settings_Helper::is_network_mode_enabled();
	$settings_source = Clockwork_Offloader_Settings_Helper::get_settings_source();
}

$settings = Clockwork_Offloader_Settings_Helper::get_settings();
$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
$using_wp_config = ( ! empty( $wp_config_creds ) && ( ! empty( $wp_config_creds['secret-access-key'] ) || ! empty( $wp_config_creds['access-key-id'] ) || ! empty( $wp_config_creds['key'] ) ) );

// Extract settings
$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'aws';
$access_key = isset( $settings['s3_access_key'] ) ? $settings['s3_access_key'] : '';
$secret_key = isset( $settings['s3_secret_key'] ) ? $settings['s3_secret_key'] : '';
$bucket = isset( $settings['s3_bucket'] ) ? $settings['s3_bucket'] : '';
$region = isset( $settings['s3_region'] ) ? $settings['s3_region'] : 'us-east-1';
$base_path = isset( $settings['s3_base_path'] ) ? $settings['s3_base_path'] : '';
$cdn_domain = isset( $settings['cdn_domain'] ) ? $settings['cdn_domain'] : '';

$auto_offload = ! empty( $settings['auto_offload'] );
$delete_after_upload = ! empty( $settings['delete_after_upload'] );
$rewrite_urls = ! empty( $settings['rewrite_urls'] );
$queue_batch_size = isset( $settings['queue_batch_size'] ) ? absint( $settings['queue_batch_size'] ) : 10;
$enable_throttle = ! empty( $settings['enable_throttle'] );
$throttle_rate = isset( $settings['throttle_rate'] ) ? absint( $settings['throttle_rate'] ) : 100;
$file_status_per_page = isset( $settings['file_status_per_page'] ) ? absint( $settings['file_status_per_page'] ) : 20;

// Format Region Label
$region_labels = array(
	'us-east-1'      => 'US East (N. Virginia)',
	'us-east-2'      => 'US East (Ohio)',
	'us-west-1'      => 'US West (N. California)',
	'us-west-2'      => 'US West (Oregon)',
	'eu-west-1'      => 'EU (Ireland)',
	'eu-west-2'      => 'EU (London)',
	'eu-central-1'   => 'EU (Frankfurt)',
	'ap-southeast-1' => 'Asia Pacific (Singapore)',
	'ap-southeast-2' => 'Asia Pacific (Sydney)',
	'ap-northeast-1' => 'Asia Pacific (Tokyo)',
	'nyc3'           => 'New York (NYC3)',
	'ams3'           => 'Amsterdam (AMS3)',
	'sfo3'           => 'San Francisco (SFO3)',
	'sgp1'           => 'Singapore (SGP1)',
	'fra1'           => 'Frankfurt (FRA1)',
);
$region_display = isset( $region_labels[ $region ] ) ? $region_labels[ $region ] : $region;

$has_creds = ( $using_wp_config || ( ! empty( $access_key ) && ! empty( $secret_key ) ) );
$has_storage_configured = ( $has_creds && ! empty( $bucket ) );

// Live preview URL sample
$sample_key = ( ! empty( $base_path ) ? trim( $base_path, '/' ) . '/' : 'wp-content/uploads/' ) . '2026/09/sample-image.jpg';
if ( ! empty( $cdn_domain ) ) {
	$sample_preview_url = rtrim( $cdn_domain, '/' ) . '/' . ltrim( $sample_key, '/' );
} elseif ( ! empty( $bucket ) && class_exists( 'Clockwork_Offloader_S3_Service' ) ) {
	$sample_preview_url = Clockwork_Offloader_S3_Service::build_public_url( $bucket, $sample_key, $region, $provider );
} else {
	$sample_preview_url = 'https://' . ( $bucket ? $bucket : 'example-bucket' ) . '.s3.' . $region . '.amazonaws.com/' . $sample_key;
}
?>

<div class="clockwork-tab-content-wrapper clockwork-settings-tab">
	<div class="clockwork-tab-header">
		<h2 class="clockwork-tab-title"><?php esc_html_e( 'Settings', 'clockwork-offloader' ); ?></h2>
		<p class="clockwork-tab-description"><?php esc_html_e( 'Manage storage provider credentials, upload rules, and live media delivery settings.', 'clockwork-offloader' ); ?></p>
	</div>

	<?php if ( is_multisite() ) : 
		$is_main_site = ( get_current_blog_id() === (int) get_main_site_id() );
		$is_inherited = ! $is_main_site && Clockwork_Offloader_Settings_Helper::is_inherited_from_main();
	?>
		<?php if ( $is_inherited ) : ?>
			<div class="notice notice-info inline" style="margin-bottom: 20px; border-left-color: #2271b1; padding: 14px 18px; background: #f0f6fc;">
				<p style="margin: 0; font-size: 14px; line-height: 1.5;">
					<span class="dashicons dashicons-networking" style="color: #2271b1; vertical-align: middle; margin-right: 6px; font-size: 20px;"></span>
					<strong><?php esc_html_e( 'Network Managed Site:', 'clockwork-offloader' ); ?></strong>
					<?php
					$main_blog_details = get_blog_details( get_main_site_id() );
					$main_site_name = $main_blog_details ? $main_blog_details->blogname : get_site_url( get_main_site_id() );
					printf(
						esc_html__( 'This site is automatically using the storage and delivery settings configured on the main network site (%s). Media uploaded on this site is stored under %s in bucket %s.', 'clockwork-offloader' ),
						'<strong>' . esc_html( $main_site_name ) . '</strong>',
						'<code>sites/' . esc_html( get_current_blog_id() ) . '/</code>',
						'<strong>' . esc_html( $bucket ) . '</strong>'
					);
					?>
				</p>
				<?php if ( current_user_can( 'manage_network_options' ) ) : ?>
					<p style="margin: 10px 0 0 0;">
						<a href="<?php echo esc_url( get_admin_url( get_main_site_id(), 'options-general.php?page=clockwork-offloader&tab=settings' ) ); ?>" class="button button-secondary button-small">
							<span class="dashicons dashicons-admin-generic" style="font-size: 14px; vertical-align: middle; margin-top: -2px;"></span>
							<?php esc_html_e( 'Manage Global Settings on Main Site', 'clockwork-offloader' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_main_site ) : ?>
			<div class="notice notice-info inline" style="margin-bottom: 20px; border-left-color: #00a32a; padding: 14px 18px;">
				<div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
					<div>
						<p style="margin: 0 0 4px 0; font-size: 14px;">
							<span class="dashicons dashicons-admin-multisite" style="color: #00a32a; vertical-align: middle; margin-right: 6px; font-size: 20px;"></span>
							<strong><?php esc_html_e( 'Multisite Primary Network Site', 'clockwork-offloader' ); ?></strong>
						</p>
						<p style="margin: 0; color: #50575e; font-size: 13px;">
							<?php esc_html_e( 'Settings configured below serve as the network default for all subsites.', 'clockwork-offloader' ); ?>
						</p>
					</div>
					<div class="clockwork-switch-item" style="margin: 0; padding: 0;">
						<label class="clockwork-switch" for="force_multisite_subsites">
							<input type="checkbox" name="clockwork_offloader_settings[force_multisite_subsites]" id="force_multisite_subsites" value="1" <?php checked( ! empty( $settings['force_multisite_subsites'] ) ); ?> />
							<span class="clockwork-slider"></span>
						</label>
						<div class="clockwork-switch-content">
							<label for="force_multisite_subsites" class="clockwork-switch-title" style="font-size: 13px;">
								<?php esc_html_e( 'Force all subsites to use these settings', 'clockwork-offloader' ); ?>
							</label>
							<div class="clockwork-switch-desc" style="font-size: 12px;">
								<?php esc_html_e( 'Automatically shares this bucket & delivery configuration with all subsites (uploads isolated under sites/{id}/).', 'clockwork-offloader' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	
	<?php settings_errors( 'clockwork_offloader_settings' ); ?>
	
	<form method="post" action="options.php" id="clockwork-settings-form">
		<?php settings_fields( 'clockwork_offloader_settings' ); ?>
		<input type="hidden" name="clockwork_offloader_settings[provider]" value="<?php echo esc_attr( $provider ); ?>" />

		<div class="clockwork-settings-grid">
			<!-- Column 1: Storage Settings -->
			<div class="clockwork-settings-card">
				<div class="clockwork-card-header">
					<h3>
						<span class="dashicons dashicons-cloud" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Storage Settings', 'clockwork-offloader' ); ?>
					</h3>
				</div>

				<!-- Connected Provider Box -->
				<div class="clockwork-provider-box">
					<div class="clockwork-provider-icon">
						<span class="dashicons dashicons-database" style="font-size: 20px; width: 20px; height: 20px; line-height: 1;"></span>
					</div>
					<div class="clockwork-provider-details">
						<div class="clockwork-provider-name">
							<?php echo $provider === 'digitalocean' ? esc_html__( 'DigitalOcean Spaces', 'clockwork-offloader' ) : esc_html__( 'Amazon S3', 'clockwork-offloader' ); ?>
						</div>
						<div class="clockwork-provider-meta">
							<?php if ( ! empty( $bucket ) ) : ?>
								<span class="clockwork-bucket-tag"><?php echo esc_html( $bucket ); ?></span>
								<span class="clockwork-region-tag"><?php echo esc_html( $region_display ); ?></span>
							<?php else : ?>
								<span style="color: #d63638; font-weight: 500;"><?php esc_html_e( 'Bucket not configured', 'clockwork-offloader' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<button type="button" class="button button-secondary button-small" id="toggle-storage-creds">
						<?php esc_html_e( 'Edit', 'clockwork-offloader' ); ?>
					</button>
				</div>

				<!-- Credentials Drawer (Collapsible) -->
				<div class="clockwork-creds-drawer" id="storage-creds-drawer" style="<?php echo ! $has_storage_configured ? 'display: block;' : 'display: none;'; ?>">
					<div class="form-group">
						<label for="s3_access_key"><?php esc_html_e( 'Access Key ID', 'clockwork-offloader' ); ?></label>
						<?php if ( $using_wp_config ) : ?>
							<input type="text" value="••••••••••••••••" disabled="disabled" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Loaded securely from wp-config.php constant.', 'clockwork-offloader' ); ?></p>
						<?php else : ?>
							<input type="text" id="s3_access_key" name="clockwork_offloader_settings[s3_access_key]" value="<?php echo esc_attr( $access_key ); ?>" class="regular-text" placeholder="AKIA..." />
						<?php endif; ?>
					</div>

					<div class="form-group">
						<label for="s3_secret_key"><?php esc_html_e( 'Secret Access Key', 'clockwork-offloader' ); ?></label>
						<?php if ( $using_wp_config ) : ?>
							<input type="password" value="••••••••••••••••" disabled="disabled" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Loaded securely from wp-config.php constant.', 'clockwork-offloader' ); ?></p>
						<?php else : ?>
							<input type="password" id="s3_secret_key" name="clockwork_offloader_settings[s3_secret_key]" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text" placeholder="••••••••••••••••" />
						<?php endif; ?>
					</div>

					<div class="form-group">
						<label for="s3_region"><?php esc_html_e( 'S3 Region', 'clockwork-offloader' ); ?></label>
						<select id="s3_region" name="clockwork_offloader_settings[s3_region]" class="regular-text">
							<?php foreach ( $region_labels as $reg_val => $reg_name ) : ?>
								<option value="<?php echo esc_attr( $reg_val ); ?>" <?php selected( $region, $reg_val ); ?>>
									<?php echo esc_html( $reg_name . ' (' . $reg_val . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="s3_bucket_text"><?php esc_html_e( 'S3 Bucket Name', 'clockwork-offloader' ); ?></label>
						<div style="display: flex; gap: 8px; align-items: center;">
							<input type="text" id="s3_bucket_text" name="clockwork_offloader_settings[s3_bucket]" value="<?php echo esc_attr( $bucket ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter bucket name', 'clockwork-offloader' ); ?>" />
							<button type="button" class="button button-secondary" id="load-buckets" title="<?php esc_attr_e( 'Load Buckets from AWS', 'clockwork-offloader' ); ?>">
								<span class="dashicons dashicons-update"></span>
							</button>
							<span class="spinner" id="bucket-spinner" style="float: none; margin: 0;"></span>
						</div>
						<select id="s3_bucket" class="regular-text" style="display: none; margin-top: 6px;">
							<option value=""><?php esc_html_e( 'Select a bucket...', 'clockwork-offloader' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Connection Health Status Box -->
				<?php if ( $has_storage_configured ) : ?>
					<div class="clockwork-health-box clockwork-health-connected">
						<span class="dashicons dashicons-yes-alt"></span>
						<div class="clockwork-health-text">
							<?php esc_html_e( 'Storage provider is successfully connected and ready to offload new media.', 'clockwork-offloader' ); ?>
						</div>
					</div>
				<?php else : ?>
					<div class="clockwork-health-box clockwork-health-warning">
						<span class="dashicons dashicons-warning"></span>
						<div class="clockwork-health-text">
							<?php esc_html_e( 'Storage provider is not fully configured. Click "Edit" above to enter your bucket and credentials.', 'clockwork-offloader' ); ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Storage Toggles -->
				<div class="clockwork-toggles-list">
					<!-- Toggle 1: Offload Media -->
					<div class="clockwork-switch-item">
						<label class="clockwork-switch" for="auto_offload">
							<input type="checkbox" id="auto_offload" name="clockwork_offloader_settings[auto_offload]" value="1" <?php checked( $auto_offload ); ?> />
							<span class="clockwork-slider"></span>
						</label>
						<div class="clockwork-switch-content">
							<div class="clockwork-switch-title"><?php esc_html_e( 'Offload Media', 'clockwork-offloader' ); ?></div>
							<div class="clockwork-switch-desc">
								<?php esc_html_e( 'Copies media files to the storage provider automatically after being uploaded, edited, or resized.', 'clockwork-offloader' ); ?>
							</div>
						</div>
					</div>

					<!-- Toggle 2: Remove Local Media -->
					<div class="clockwork-switch-item">
						<label class="clockwork-switch" for="delete_after_upload">
							<input type="checkbox" id="delete_after_upload" name="clockwork_offloader_settings[delete_after_upload]" value="1" <?php checked( $delete_after_upload ); ?> />
							<span class="clockwork-slider"></span>
						</label>
						<div class="clockwork-switch-content">
							<div class="clockwork-switch-title"><?php esc_html_e( 'Remove Local Media', 'clockwork-offloader' ); ?></div>
							<div class="clockwork-switch-desc">
								<?php esc_html_e( 'Frees up local web server storage space by deleting media files from the server after they are safely offloaded to S3.', 'clockwork-offloader' ); ?>
							</div>
						</div>
					</div>

					<!-- Toggle 3: Add Prefix to Bucket Path -->
					<div class="clockwork-switch-item">
						<label class="clockwork-switch" for="enable_bucket_prefix">
							<input type="checkbox" id="enable_bucket_prefix" <?php checked( ! empty( $base_path ) ); ?> />
							<span class="clockwork-slider"></span>
						</label>
						<div class="clockwork-switch-content">
							<div class="clockwork-switch-title"><?php esc_html_e( 'Add Prefix to Bucket Path', 'clockwork-offloader' ); ?></div>
							<div class="clockwork-switch-desc">
								<?php esc_html_e( 'Groups media from this site together by organizing uploads into a common subfolder inside your bucket.', 'clockwork-offloader' ); ?>
							</div>
							<div class="clockwork-switch-subfield" id="bucket_prefix_field" style="<?php echo ! empty( $base_path ) ? 'display: block;' : 'display: none;'; ?>">
								<input type="text" id="s3_base_path" name="clockwork_offloader_settings[s3_base_path]" value="<?php echo esc_attr( $base_path ); ?>" placeholder="wp-content/uploads/" class="regular-text" style="width: 100%;" />
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Column 2: Delivery Settings -->
			<div class="clockwork-settings-card">
				<div class="clockwork-card-header">
					<h3>
						<span class="dashicons dashicons-admin-site-alt3" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Delivery Settings', 'clockwork-offloader' ); ?>
					</h3>
				</div>

				<!-- Connected Delivery Box -->
				<div class="clockwork-provider-box">
					<div class="clockwork-provider-icon" style="color: #2271b1;">
						<span class="dashicons dashicons-cloud" style="font-size: 20px; width: 20px; height: 20px; line-height: 1;"></span>
					</div>
					<div class="clockwork-provider-details">
						<div class="clockwork-provider-name">
							<?php echo ! empty( $cdn_domain ) ? esc_html__( 'CloudFront / Custom CDN', 'clockwork-offloader' ) : esc_html__( 'Amazon S3', 'clockwork-offloader' ); ?>
						</div>
						<div class="clockwork-provider-meta">
							<?php if ( ! empty( $cdn_domain ) ) : ?>
								<span class="clockwork-bucket-tag"><?php echo esc_html( $cdn_domain ); ?></span>
							<?php else : ?>
								<span><?php esc_html_e( 'Direct S3 URLs', 'clockwork-offloader' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<button type="button" class="button button-secondary button-small" id="toggle-delivery-drawer">
						<?php esc_html_e( 'Edit', 'clockwork-offloader' ); ?>
					</button>
				</div>

				<!-- Delivery Credentials / CDN Drawer (Collapsible) -->
				<div class="clockwork-creds-drawer" id="delivery-creds-drawer" style="display: none;">
					<div class="form-group">
						<label for="cdn_domain"><?php esc_html_e( 'CDN / Custom Domain', 'clockwork-offloader' ); ?></label>
						<input type="url" id="cdn_domain" name="clockwork_offloader_settings[cdn_domain]" value="<?php echo esc_attr( $cdn_domain ); ?>" class="regular-text" placeholder="https://cdn.example.com" />
						<p class="description">
							<?php esc_html_e( 'Optional CloudFront or custom CDN domain to deliver offloaded assets through a global edge network instead of direct S3 URLs.', 'clockwork-offloader' ); ?>
						</p>
					</div>
				</div>

				<!-- Delivery Health Status Box -->
				<?php if ( $rewrite_urls ) : ?>
					<div class="clockwork-health-box clockwork-health-connected">
						<span class="dashicons dashicons-yes-alt"></span>
						<div class="clockwork-health-text">
							<?php esc_html_e( 'Delivery provider is successfully connected and serving offloaded media.', 'clockwork-offloader' ); ?>
						</div>
					</div>
				<?php else : ?>
					<div class="clockwork-health-box clockwork-health-warning">
						<span class="dashicons dashicons-warning"></span>
						<div class="clockwork-health-text">
							<?php esc_html_e( 'Delivery is off: media is currently loaded from the local server. Enable "Deliver Offloaded Media" below to serve directly from S3 or CDN.', 'clockwork-offloader' ); ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Delivery Toggles -->
				<div class="clockwork-toggles-list">
					<!-- Toggle 1: Deliver Offloaded Media -->
					<div class="clockwork-switch-item">
						<label class="clockwork-switch" for="rewrite_urls">
							<input type="checkbox" id="rewrite_urls" name="clockwork_offloader_settings[rewrite_urls]" value="1" <?php checked( $rewrite_urls ); ?> />
							<span class="clockwork-slider"></span>
						</label>
						<div class="clockwork-switch-content">
							<div class="clockwork-switch-title"><?php esc_html_e( 'Deliver Offloaded Media', 'clockwork-offloader' ); ?></div>
							<div class="clockwork-switch-desc">
								<?php esc_html_e( 'Serves offloaded media files by rewriting local URLs in pages, posts, and feeds so visitors fetch them from S3 or your CDN.', 'clockwork-offloader' ); ?>
							</div>
						</div>
					</div>

					<!-- Toggle 2: Force HTTPS -->
					<div class="clockwork-switch-item">
						<label class="clockwork-switch" for="force_https_toggle">
							<input type="checkbox" id="force_https_toggle" checked="checked" disabled="disabled" />
							<span class="clockwork-slider"></span>
						</label>
						<div class="clockwork-switch-content">
							<div class="clockwork-switch-title"><?php esc_html_e( 'Force HTTPS', 'clockwork-offloader' ); ?></div>
							<div class="clockwork-switch-desc">
								<?php esc_html_e( 'Uses secure HTTPS for all offloaded media URLs (standard for Amazon S3 and CloudFront distributions).', 'clockwork-offloader' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Live URL Preview Card -->
		<div class="clockwork-preview-card">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
				<h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #0f172a;">
					<span class="dashicons dashicons-admin-links" style="color: #2271b1; margin-right: 6px; font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
					<?php esc_html_e( 'URL Preview', 'clockwork-offloader' ); ?>
				</h4>
				<span style="font-size: 12px; color: #64748b;">
					<?php esc_html_e( 'How offloaded media links will appear to website visitors', 'clockwork-offloader' ); ?>
				</span>
			</div>
			<div class="clockwork-preview-url" id="clockwork-live-url-preview">
				<?php echo esc_html( $sample_preview_url ); ?>
			</div>
		</div>

		<!-- Collapsible Advanced Options -->
		<details class="clockwork-advanced-settings" <?php echo ! empty( $base_path ) ? 'open' : ''; ?>>
			<summary>
				<?php esc_html_e( 'Advanced Performance & Queue Settings', 'clockwork-offloader' ); ?>
			</summary>
			<div class="clockwork-advanced-content">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
					<div>
						<label for="s3_base_path" style="display: block; font-weight: 600; margin-bottom: 4px;">
							<?php esc_html_e( 'Custom Path Prefix', 'clockwork-offloader' ); ?>
						</label>
						<input type="text" id="s3_base_path" name="clockwork_offloader_settings[s3_base_path]" value="<?php echo esc_attr( $base_path ); ?>" class="regular-text" style="width: 100%; max-width: 240px;" placeholder="uploads/" />
						<p class="description">
							<?php esc_html_e( 'Optional folder path in your bucket (e.g. "uploads/"). Leave empty to offload to root.', 'clockwork-offloader' ); ?>
						</p>
					</div>

					<div>
						<label for="queue_batch_size" style="display: block; font-weight: 600; margin-bottom: 4px;">
							<?php esc_html_e( 'Queue Batch Size', 'clockwork-offloader' ); ?>
						</label>
						<input type="number" id="queue_batch_size" name="clockwork_offloader_settings[queue_batch_size]" value="<?php echo esc_attr( $queue_batch_size ); ?>" min="1" max="100" class="small-text" style="width: 100px;" />
						<p class="description">
							<?php esc_html_e( 'Items processed per background batch (10-50 recommended).', 'clockwork-offloader' ); ?>
						</p>
					</div>

					<div>
						<label for="file_status_per_page" style="display: block; font-weight: 600; margin-bottom: 4px;">
							<?php esc_html_e( 'Files Per Page (Files Tab)', 'clockwork-offloader' ); ?>
						</label>
						<input type="number" id="file_status_per_page" name="clockwork_offloader_settings[file_status_per_page]" value="<?php echo esc_attr( $file_status_per_page ); ?>" min="10" max="100" class="small-text" style="width: 100px;" />
						<p class="description">
							<?php esc_html_e( 'Number of files listed per page in the Files tab.', 'clockwork-offloader' ); ?>
						</p>
					</div>

					<div>
						<div class="clockwork-switch-item" style="padding: 0; border: none;">
							<label class="clockwork-switch" for="enable_throttle">
								<input type="checkbox" id="enable_throttle" name="clockwork_offloader_settings[enable_throttle]" value="1" <?php checked( $enable_throttle ); ?> />
								<span class="clockwork-slider"></span>
							</label>
							<div class="clockwork-switch-content">
								<div class="clockwork-switch-title"><?php esc_html_e( 'Throttle Uploads', 'clockwork-offloader' ); ?></div>
								<div class="clockwork-switch-desc">
									<?php esc_html_e( 'Pause between items to protect server memory on constrained shared hosts.', 'clockwork-offloader' ); ?>
								</div>
								<div id="throttle_rate_field" style="<?php echo $enable_throttle ? 'margin-top: 8px;' : 'display: none; margin-top: 8px;'; ?>">
									<input type="number" id="throttle_rate" name="clockwork_offloader_settings[throttle_rate]" value="<?php echo esc_attr( $throttle_rate ); ?>" min="10" max="5000" class="small-text" style="width: 100px;" />
									<span style="font-size: 12px; color: #64748b;">ms delay</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</details>

		<?php
		// Pro Upgrade Callout Banner (Lite only)
		if ( ! class_exists( 'Clockwork_Offloader_Pro' ) && file_exists( CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/partials/pro-upgrade-ad.php' ) ) {
			include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/partials/pro-upgrade-ad.php';
		}
		?>

		<p class="submit" style="margin-top: 24px;">
			<?php if ( ! empty( $is_inherited ) ) : ?>
				<button type="button" class="button button-primary" disabled="disabled">
					<span class="dashicons dashicons-lock" style="vertical-align: middle; font-size: 14px; margin-top: -2px;"></span>
					<?php esc_html_e( 'Settings Managed Globally by Main Site', 'clockwork-offloader' ); ?>
				</button>
			<?php else : ?>
				<?php submit_button( __( 'Save Changes', 'clockwork-offloader' ), 'primary', 'submit', false, array( 'id' => 'clockwork-save-settings-btn' ) ); ?>
			<?php endif; ?>
		</p>
	</form>
</div>
