<?php
/**
 * Setup Wizard View
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap clockwork-setup-wizard">
	<!-- Plugin Header -->
	<div class="clockwork-offloader-header">
		<div class="clockwork-offloader-header-content">
			<div class="clockwork-offloader-header-left">
				<div class="clockwork-offloader-logo">
					<div class="clockwork-offloader-logo-placeholder">
						<?php
						$logo_path = CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/images/clockwork-logo.svg';
						$logo_url = CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/images/clockwork-logo.svg';
						if ( file_exists( $logo_path ) ) :
							?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Clockwork Offloader', 'clockwork-offloader' ); ?>" />
						<?php else : ?>
							<span class="dashicons dashicons-cloud"></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="clockwork-offloader-title">
					<h1><?php esc_html_e( 'Setup Clockwork Offloader Lite', 'clockwork-offloader' ); ?></h1>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Breadcrumbs -->
	<div class="clockwork-setup-breadcrumbs">
		<a href="#" class="clockwork-breadcrumb-link<?php echo $current_step >= 1 ? ' active' : ''; ?>" data-step="1">
			<span class="clockwork-breadcrumb-number">1</span>
			<span class="clockwork-breadcrumb-text"><?php esc_html_e( 'Connection Method', 'clockwork-offloader' ); ?></span>
		</a>
		<span class="clockwork-breadcrumb-separator">&gt;</span>
		<a href="#" class="clockwork-breadcrumb-link<?php echo $current_step >= 2 ? ' active' : ''; ?><?php echo $current_step < 2 ? ' disabled' : ''; ?>" data-step="2">
			<span class="clockwork-breadcrumb-number">2</span>
			<span class="clockwork-breadcrumb-text"><?php esc_html_e( 'Region & Bucket', 'clockwork-offloader' ); ?></span>
		</a>
		<span class="clockwork-breadcrumb-separator">&gt;</span>
		<a href="#" class="clockwork-breadcrumb-link<?php echo $current_step >= 3 ? ' active' : ''; ?><?php echo $current_step < 3 ? ' disabled' : ''; ?>" data-step="3">
			<span class="clockwork-breadcrumb-number">3</span>
			<span class="clockwork-breadcrumb-text"><?php esc_html_e( 'Test & Complete', 'clockwork-offloader' ); ?></span>
		</a>
	</div>
	
	<!-- Setup Content -->
	<div class="clockwork-setup-content">
		<?php if ( $current_step === 1 ) : ?>
			<!-- Step 1: Provider & Connection Method -->
			<div class="clockwork-setup-step" data-step="1">
				<h2><?php esc_html_e( '1. Provider & Connection Method', 'clockwork-offloader' ); ?></h2>
				<p class="description"><?php esc_html_e( 'First, choose your storage provider, then choose how you want to store your credentials. We recommend storing them in wp-config.php for better security.', 'clockwork-offloader' ); ?></p>
				
				<!-- Provider Selection -->
				<div class="clockwork-setup-provider" style="margin-bottom: 30px;">
					<h3><?php esc_html_e( 'Storage Provider', 'clockwork-offloader' ); ?></h3>
					<div class="clockwork-setup-connection-method">
						<label class="clockwork-connection-option<?php echo ( isset( $setup_data['provider'] ) && $setup_data['provider'] === 'aws' ) || ! isset( $setup_data['provider'] ) ? ' selected' : ''; ?>">
							<input type="radio" name="provider" value="aws" <?php checked( isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws', 'aws' ); ?> />
							<div class="clockwork-connection-option-content">
								<strong><?php esc_html_e( 'Amazon S3', 'clockwork-offloader' ); ?></strong>
								<p class="description"><?php esc_html_e( 'Use Amazon Web Services S3 for storage.', 'clockwork-offloader' ); ?></p>
							</div>
						</label>
						
						<label class="clockwork-connection-option<?php echo ( isset( $setup_data['provider'] ) && $setup_data['provider'] === 'digitalocean' ) ? ' selected' : ''; ?>">
							<input type="radio" name="provider" value="digitalocean" <?php checked( isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws', 'digitalocean' ); ?> />
							<div class="clockwork-connection-option-content">
								<strong><?php esc_html_e( 'Digital Ocean Spaces', 'clockwork-offloader' ); ?></strong>
								<p class="description"><?php esc_html_e( 'Use Digital Ocean Spaces for storage (S3-compatible).', 'clockwork-offloader' ); ?></p>
							</div>
						</label>
					</div>
				</div>
				
				<!-- Connection Method Selection -->
				<div class="clockwork-setup-connection">
					<h3><?php esc_html_e( 'Connection Method', 'clockwork-offloader' ); ?></h3>
				
				<?php
				// Check if wp-config.php constants are already defined
				$has_access_key = defined( 'CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY' );
				$has_secret_key = defined( 'CLOCKWORK_OFFLOADER_AWS_SECRET_KEY' );
				$wp_config_configured = $has_access_key && $has_secret_key;
				?>
				
				<div class="clockwork-setup-connection-method">
					<label class="clockwork-connection-option<?php echo ( isset( $setup_data['connection_method'] ) && $setup_data['connection_method'] === 'wp-config' ) ? ' selected' : ''; ?><?php echo $wp_config_configured ? ' clockwork-already-configured' : ''; ?>">
						<input type="radio" name="connection_method" value="wp-config" <?php checked( isset( $setup_data['connection_method'] ) ? $setup_data['connection_method'] : 'wp-config', 'wp-config' ); ?> />
						<div class="clockwork-connection-option-content">
							<strong><?php esc_html_e( 'Define access keys in wp-config.php', 'clockwork-offloader' ); ?></strong>
							<?php if ( $wp_config_configured ) : ?>
								<span style="color: #00a32a; margin-left: 8px;">✓ <?php esc_html_e( 'Already configured', 'clockwork-offloader' ); ?></span>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Recommended for better security. Credentials are stored outside the database.', 'clockwork-offloader' ); ?></p>
						</div>
					</label>
					
					<label class="clockwork-connection-option<?php echo ( isset( $setup_data['connection_method'] ) && $setup_data['connection_method'] === 'database' ) ? ' selected' : ''; ?>">
						<input type="radio" name="connection_method" value="database" <?php checked( isset( $setup_data['connection_method'] ) ? $setup_data['connection_method'] : '', 'database' ); ?> />
						<div class="clockwork-connection-option-content">
							<strong><?php esc_html_e( 'Store access keys in database', 'clockwork-offloader' ); ?></strong>
							<p class="description"><?php esc_html_e( 'Less secure but easier to manage through the WordPress admin.', 'clockwork-offloader' ); ?></p>
						</div>
					</label>
				</div>
				
				<!-- WP-Config.php Instructions -->
				<div id="clockwork-wp-config-instructions" class="clockwork-wp-config-section" style="<?php echo ( isset( $setup_data['connection_method'] ) && $setup_data['connection_method'] === 'wp-config' ) ? '' : 'display: none;'; ?>">
					<h3><?php esc_html_e( 'Add to wp-config.php', 'clockwork-offloader' ); ?></h3>
					<p><?php esc_html_e( 'Copy the following snippet to near the top of your wp-config.php file (before the "That\'s all, stop editing!" line). Replace the placeholder values with your actual credentials:', 'clockwork-offloader' ); ?></p>
					<div class="clockwork-code-block">
						<pre><code id="clockwork-wp-config-code"><?php echo esc_html( $wp_config_code_snippet ); ?></code></pre>
						<button type="button" class="button button-secondary clockwork-copy-code" id="copy-wp-config-code" data-target="clockwork-wp-config-code">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy Code', 'clockwork-offloader' ); ?>
						</button>
					</div>
					<div id="clockwork-wp-config-status" style="margin: 20px 0;">
						<div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px;">
							<button type="button" class="button button-secondary" id="verify-wp-config" style="flex-shrink: 0;">
								<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span>
								<?php esc_html_e( 'Verify wp-config.php', 'clockwork-offloader' ); ?>
							</button>
							<div id="clockwork-wp-config-status-message" style="flex: 1; padding-top: 4px;">
								<?php
								// Pre-populate verification status if credentials are already detected
								$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
								$has_wp_config = ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) );
								if ( $has_wp_config ) {
									$details = array();
									if ( ! empty( $wp_config_creds['provider'] ) ) {
										$provider_name = $wp_config_creds['provider'] === 'digitalocean' ? __( 'DigitalOcean Spaces', 'clockwork-offloader' ) : __( 'AWS S3', 'clockwork-offloader' );
										$details[] = __( 'Provider', 'clockwork-offloader' ) . ': ' . $provider_name;
									}
									$settings = Clockwork_Offloader_Settings_Helper::get_settings();
									if ( ! empty( $settings['s3_region'] ) ) {
										$details[] = __( 'Region', 'clockwork-offloader' ) . ': ' . $settings['s3_region'] . ' (database)';
									}
									if ( ! empty( $settings['s3_bucket'] ) ) {
										$details[] = __( 'Bucket', 'clockwork-offloader' ) . ': ' . $settings['s3_bucket'] . ' (database)';
									}
									?>
									<span style="color: #00a32a; font-weight: 600;">✓ <?php esc_html_e( 'wp-config.php constants detected successfully!', 'clockwork-offloader' ); ?></span>
									<?php if ( ! empty( $details ) ) : ?>
										<div id="clockwork-wp-config-status-notice" style="margin-top: 10px;">
											<div class="notice notice-success inline">
												<p><small><?php echo esc_html( implode( ', ', $details ) ); ?></small></p>
											</div>
										</div>
									<?php endif; ?>
								<?php } ?>
							</div>
						</div>
						<div id="clockwork-wp-config-status-notice" style="display: none; margin-top: 10px;"></div>
					</div>
					<p class="description" style="margin-top: 10px;">
						<?php esc_html_e( 'After adding the code to wp-config.php, click "Verify wp-config.php" to check if the constants are detected. Once verified, you can proceed to Step 2. Note: Region and bucket are stored in the database and can be configured in the next step.', 'clockwork-offloader' ); ?>
					</p>
				</div>
				
				<!-- Database Credentials Form -->
				<div id="clockwork-database-credentials" class="clockwork-database-section" style="<?php echo ( isset( $setup_data['connection_method'] ) && $setup_data['connection_method'] === 'database' ) ? '' : 'display: none;'; ?>">
					<h3><?php esc_html_e( 'Enter Credentials', 'clockwork-offloader' ); ?></h3>
					<p class="description"><?php esc_html_e( 'These credentials will be stored in the WordPress database. You can modify them if needed.', 'clockwork-offloader' ); ?></p>
					<table class="form-table" style="margin-top: 15px;">
						<tr>
							<th scope="row">
								<label for="setup_access_key"><?php esc_html_e( 'Access Key ID', 'clockwork-offloader' ); ?></label>
							</th>
							<td>
								<input type="text" id="setup_access_key" name="access_key" value="<?php echo esc_attr( isset( $setup_data['access_key'] ) ? $setup_data['access_key'] : '' ); ?>" class="regular-text" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Your AWS Access Key ID', 'clockwork-offloader' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="setup_secret_key"><?php esc_html_e( 'Secret Access Key', 'clockwork-offloader' ); ?></label>
							</th>
							<td>
								<input type="password" id="setup_secret_key" name="secret_key" value="" class="regular-text" autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Your AWS Secret Access Key', 'clockwork-offloader' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
				
				<div class="clockwork-setup-actions">
					<a href="?page=clockwork-offloader&step=2" class="button button-primary clockwork-setup-next" id="continue-to-step2" data-step="1" style="display: none;">
						<?php esc_html_e( 'Continue to Step 2', 'clockwork-offloader' ); ?>
						<span class="clockwork-arrow">→</span>
					</a>
				</div>
			</div>
		<?php elseif ( $current_step === 2 ) : ?>
			<!-- Step 2: Region and Bucket -->
			<div class="clockwork-setup-step" data-step="2">
				<h2><?php esc_html_e( '2. Region & Bucket', 'clockwork-offloader' ); ?></h2>
				<?php
				$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
				$provider_names = array(
					'aws' => __( 'AWS S3', 'clockwork-offloader' ),
					'digitalocean' => __( 'Digital Ocean Spaces', 'clockwork-offloader' ),
				);
				$provider_name = isset( $provider_names[ $provider ] ) ? $provider_names[ $provider ] : __( 'Storage Provider', 'clockwork-offloader' );
				?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Select your %s region and bucket where media files will be stored.', 'clockwork-offloader' ), $provider_name ) ); ?></p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="setup_region"><?php echo esc_html( sprintf( __( '%s Region', 'clockwork-offloader' ), $provider_name ) ); ?></label>
						</th>
						<td>
							<select id="setup_region" name="region" class="regular-text" data-provider="<?php echo esc_attr( $provider ); ?>">
								<option value=""><?php esc_html_e( 'Select a region...', 'clockwork-offloader' ); ?></option>
								<?php if ( $provider === 'digitalocean' ) : ?>
									<!-- Digital Ocean Spaces Regions -->
									<option value="nyc1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'nyc1' ); ?>>New York 1 - nyc1</option>
									<option value="nyc3" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'nyc3' ); ?>>New York 3 - nyc3</option>
									<option value="sfo3" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'sfo3' ); ?>>San Francisco 3 - sfo3</option>
									<option value="sfo2" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'sfo2' ); ?>>San Francisco 2 - sfo2</option>
									<option value="ams3" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'ams3' ); ?>>Amsterdam 3 - ams3</option>
									<option value="ams2" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'ams2' ); ?>>Amsterdam 2 - ams2</option>
									<option value="sgp1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'sgp1' ); ?>>Singapore 1 - sgp1</option>
									<option value="fra1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'fra1' ); ?>>Frankfurt 1 - fra1</option>
									<option value="blr1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'blr1' ); ?>>Bangalore 1 - blr1</option>
									<option value="syd1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'syd1' ); ?>>Sydney 1 - syd1</option>
								<?php else : ?>
									<!-- AWS S3 Regions -->
									<option value="us-east-1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'us-east-1' ); ?>>US East (N. Virginia) - us-east-1</option>
									<option value="us-east-2" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'us-east-2' ); ?>>US East (Ohio) - us-east-2</option>
									<option value="us-west-1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'us-west-1' ); ?>>US West (N. California) - us-west-1</option>
									<option value="us-west-2" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'us-west-2' ); ?>>US West (Oregon) - us-west-2</option>
									<option value="eu-west-1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'eu-west-1' ); ?>>EU (Ireland) - eu-west-1</option>
									<option value="eu-west-2" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'eu-west-2' ); ?>>EU (London) - eu-west-2</option>
									<option value="eu-central-1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'eu-central-1' ); ?>>EU (Frankfurt) - eu-central-1</option>
									<option value="ap-southeast-1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'ap-southeast-1' ); ?>>Asia Pacific (Singapore) - ap-southeast-1</option>
									<option value="ap-southeast-2" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'ap-southeast-2' ); ?>>Asia Pacific (Sydney) - ap-southeast-2</option>
									<option value="ap-northeast-1" <?php selected( isset( $setup_data['region'] ) ? $setup_data['region'] : '', 'ap-northeast-1' ); ?>>Asia Pacific (Tokyo) - ap-northeast-1</option>
								<?php endif; ?>
							</select>
							<p class="description"><?php echo esc_html( sprintf( __( 'The %s region where your bucket is located.', 'clockwork-offloader' ), $provider_name ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="setup_bucket">
								<?php
								$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
								if ( $provider === 'digitalocean' ) {
									esc_html_e( 'Space Name', 'clockwork-offloader' );
								} else {
									esc_html_e( 'S3 Bucket', 'clockwork-offloader' );
								}
								?>
							</label>
						</th>
						<td>
							<div class="clockwork-bucket-selector" style="display: block;">
								<div style="display: flex; align-items: center; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #c3c4c7; clear: both; width: 100%;">
									<label style="display: flex; align-items: center; margin-right: 12px; margin-bottom: 0; white-space: nowrap;">
										<input type="radio" name="bucket_method" value="manual" checked style="margin-right: 6px;" />
										<?php esc_html_e( 'Enter bucket name', 'clockwork-offloader' ); ?>
									</label>
									<input type="text" id="setup_bucket" name="bucket" value="<?php echo esc_attr( isset( $setup_data['bucket'] ) ? $setup_data['bucket'] : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter bucket name...', 'clockwork-offloader' ); ?>" style="flex: 1; max-width: 400px;" />
								</div>
								
								<div style="display: flex; align-items: center; clear: both; width: 100%;">
									<label style="display: flex; align-items: center; margin-right: 12px; margin-bottom: 0; white-space: nowrap;">
										<input type="radio" name="bucket_method" value="browse" style="margin-right: 6px;" />
										<?php esc_html_e( 'Browse existing buckets', 'clockwork-offloader' ); ?>
									</label>
									<div style="display: flex; align-items: center; flex: 1; max-width: 400px;">
										<select id="setup_bucket_select" name="bucket_select" class="regular-text" style="display: none; width: 100%;">
											<option value=""><?php esc_html_e( 'Loading buckets...', 'clockwork-offloader' ); ?></option>
										</select>
										<span class="spinner" id="setup-bucket-spinner" style="float: none; margin-left: 5px; display: none;"></span>
									</div>
								</div>
							</div>
							<p class="description"><?php esc_html_e( 'Enter your S3 bucket name manually or browse your existing buckets.', 'clockwork-offloader' ); ?></p>
						</td>
					</tr>
				</table>
				
				<div class="clockwork-setup-actions">
					<button type="button" class="button button-secondary clockwork-setup-back" data-step="2">
						<span class="clockwork-arrow">←</span>
						<?php esc_html_e( 'Back', 'clockwork-offloader' ); ?>
					</button>
					<button type="button" class="button button-primary clockwork-setup-next" data-step="2">
						<?php esc_html_e( 'Continue to Step 3', 'clockwork-offloader' ); ?>
						<span class="clockwork-arrow">→</span>
					</button>
				</div>
			</div>
		<?php elseif ( $current_step === 3 ) : ?>
			<!-- Step 3: Test and Complete -->
			<div class="clockwork-setup-step" data-step="3">
				<h2><?php esc_html_e( '3. Test & Complete', 'clockwork-offloader' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Let\'s test your connection to make sure everything is configured correctly.', 'clockwork-offloader' ); ?></p>
				
				<div class="clockwork-setup-summary">
					<h3><?php esc_html_e( 'Configuration Summary', 'clockwork-offloader' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Storage Provider', 'clockwork-offloader' ); ?></th>
							<td>
								<strong>
									<?php
									$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
									if ( $provider === 'digitalocean' ) {
										esc_html_e( 'Digital Ocean Spaces', 'clockwork-offloader' );
									} else {
										esc_html_e( 'Amazon S3', 'clockwork-offloader' );
									}
									?>
								</strong>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Connection Method', 'clockwork-offloader' ); ?></th>
							<td>
								<?php if ( isset( $setup_data['connection_method'] ) && $setup_data['connection_method'] === 'wp-config' ) : ?>
									<strong><?php esc_html_e( 'wp-config.php', 'clockwork-offloader' ); ?></strong>
								<?php else : ?>
									<strong><?php esc_html_e( 'Database', 'clockwork-offloader' ); ?></strong>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php
								$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
								if ( $provider === 'digitalocean' ) {
									esc_html_e( 'DO Spaces Region', 'clockwork-offloader' );
								} else {
									esc_html_e( 'AWS Region', 'clockwork-offloader' );
								}
								?>
							</th>
							<td><strong><?php echo esc_html( isset( $setup_data['region'] ) ? $setup_data['region'] : '' ); ?></strong></td>
						</tr>
						<tr>
							<th scope="row">
								<?php
								$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
								if ( $provider === 'digitalocean' ) {
									esc_html_e( 'Space Name', 'clockwork-offloader' );
								} else {
									esc_html_e( 'S3 Bucket', 'clockwork-offloader' );
								}
								?>
							</th>
							<td><strong><?php echo esc_html( isset( $setup_data['bucket'] ) ? $setup_data['bucket'] : '' ); ?></strong></td>
						</tr>
					</table>
				</div>
				
				<div class="clockwork-setup-test">
					<button type="button" class="button button-primary button-large" id="clockwork-setup-test-connection">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Test Connection', 'clockwork-offloader' ); ?>
					</button>
					<span class="spinner" id="clockwork-setup-test-spinner" style="float: none; margin-left: 10px; display: none;"></span>
					<div id="clockwork-setup-test-result" style="margin-top: 15px;"></div>
				</div>
				
				<div class="clockwork-setup-actions">
					<button type="button" class="button button-secondary clockwork-setup-back" data-step="3">
						<span class="clockwork-arrow">←</span>
						<?php esc_html_e( 'Back', 'clockwork-offloader' ); ?>
					</button>
					<button type="button" class="button button-primary button-large clockwork-setup-complete" id="clockwork-setup-complete-btn" style="display: none;">
						<span class="clockwork-checkmark">✓</span>
						<?php esc_html_e( 'Complete Setup', 'clockwork-offloader' ); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

