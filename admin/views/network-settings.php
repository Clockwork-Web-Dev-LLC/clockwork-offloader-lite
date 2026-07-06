<?php
/**
 * Network Settings View
 *
 * Network-wide settings page for multisite
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_multisite() || ! is_network_admin() || ! current_user_can( 'manage_network_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Clockwork Offloader - Network Settings', 'clockwork-offloader' ); ?></h1>
	
	<?php
	$settings_source = Clockwork_Offloader_Settings_Helper::get_settings_source();
	$is_network_mode = Clockwork_Offloader_Settings_Helper::is_network_mode_enabled();
	?>
	
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Network Mode:', 'clockwork-offloader' ); ?></strong>
			<?php if ( $is_network_mode ) : ?>
				<span style="color: green;"><?php esc_html_e( 'Enabled', 'clockwork-offloader' ); ?></span>
				- <?php esc_html_e( 'All sites will use these network-wide settings.', 'clockwork-offloader' ); ?>
			<?php else : ?>
				<span style="color: orange;"><?php esc_html_e( 'Disabled', 'clockwork-offloader' ); ?></span>
				- <?php esc_html_e( 'Each site uses its own settings. Enable network mode to use shared settings across all sites.', 'clockwork-offloader' ); ?>
			<?php endif; ?>
		</p>
		<?php if ( $settings_source === 'wp-config' ) : ?>
			<p>
				<strong><?php esc_html_e( 'Note:', 'clockwork-offloader' ); ?></strong>
				<?php esc_html_e( 'Settings are loaded from wp-config.php constants, which take priority over network and site settings.', 'clockwork-offloader' ); ?>
			</p>
		<?php endif; ?>
	</div>
	
	<form method="post" action="">
		<?php wp_nonce_field( 'clockwork_network_settings' ); ?>
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="clockwork_network_mode"><?php esc_html_e( 'Network Mode', 'clockwork-offloader' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="clockwork_network_mode" id="clockwork_network_mode" value="1" <?php checked( $is_network_mode ); ?> />
						<?php esc_html_e( 'Enable network-wide settings', 'clockwork-offloader' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, all sites in the network will use these shared settings. When disabled, each site can have its own settings.', 'clockwork-offloader' ); ?>
					</p>
				</td>
			</tr>
		</table>
		
		<?php if ( $is_network_mode ) : ?>
			<h2><?php esc_html_e( 'Network Settings', 'clockwork-offloader' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'These settings will be used by all sites in the network when network mode is enabled.', 'clockwork-offloader' ); ?>
			</p>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="clockwork_network_settings_provider"><?php esc_html_e( 'Provider', 'clockwork-offloader' ); ?></label>
					</th>
					<td>
						<select name="clockwork_network_settings[provider]" id="clockwork_network_settings_provider">
							<option value="aws" <?php selected( $network_settings['provider'] ?? 'aws', 'aws' ); ?>><?php esc_html_e( 'AWS S3', 'clockwork-offloader' ); ?></option>
							<option value="digitalocean" <?php selected( $network_settings['provider'] ?? '', 'digitalocean' ); ?>><?php esc_html_e( 'DigitalOcean Spaces', 'clockwork-offloader' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="clockwork_network_settings_s3_access_key"><?php esc_html_e( 'Access Key', 'clockwork-offloader' ); ?></label>
					</th>
					<td>
						<input type="text" name="clockwork_network_settings[s3_access_key]" id="clockwork_network_settings_s3_access_key" value="<?php echo esc_attr( $network_settings['s3_access_key'] ?? '' ); ?>" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Your S3-compatible access key ID.', 'clockwork-offloader' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="clockwork_network_settings_s3_secret_key"><?php esc_html_e( 'Secret Key', 'clockwork-offloader' ); ?></label>
					</th>
					<td>
						<input type="password" name="clockwork_network_settings[s3_secret_key]" id="clockwork_network_settings_s3_secret_key" value="<?php echo esc_attr( $network_settings['s3_secret_key'] ?? '' ); ?>" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Your S3-compatible secret access key.', 'clockwork-offloader' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="clockwork_network_settings_s3_bucket"><?php esc_html_e( 'Bucket', 'clockwork-offloader' ); ?></label>
					</th>
					<td>
						<input type="text" name="clockwork_network_settings[s3_bucket]" id="clockwork_network_settings_s3_bucket" value="<?php echo esc_attr( $network_settings['s3_bucket'] ?? '' ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="clockwork_network_settings_s3_region"><?php esc_html_e( 'Region', 'clockwork-offloader' ); ?></label>
					</th>
					<td>
						<input type="text" name="clockwork_network_settings[s3_region]" id="clockwork_network_settings_s3_region" value="<?php echo esc_attr( $network_settings['s3_region'] ?? 'us-east-1' ); ?>" class="regular-text" />
					</td>
				</tr>
			</table>
		<?php endif; ?>
		
		<p class="submit">
			<?php submit_button( __( 'Save Network Settings', 'clockwork-offloader' ), 'primary', 'clockwork_network_settings_submit', false ); ?>
		</p>
	</form>
	
	<?php if ( ! $is_network_mode ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Note:', 'clockwork-offloader' ); ?></strong>
				<?php esc_html_e( 'Network mode is currently disabled. Each site manages its own settings. Enable network mode above to configure shared settings for all sites.', 'clockwork-offloader' ); ?>
			</p>
		</div>
	<?php endif; ?>
	
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Site Settings:', 'clockwork-offloader' ); ?></strong>
			<?php esc_html_e( 'Individual sites can still configure their own settings in Settings > Clockwork Offloader. Site settings are used when network mode is disabled, or as a fallback when network settings are not configured.', 'clockwork-offloader' ); ?>
		</p>
	</div>
</div>

