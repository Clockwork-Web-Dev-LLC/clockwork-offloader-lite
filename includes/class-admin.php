<?php
/**
 * Admin Interface
 *
 * Handles admin pages and AJAX requests
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clockwork_Offloader_Admin class
 */
class Clockwork_Offloader_Admin {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'show_migration_notice' ) );
		
		// Load Lite restrictions class
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-lite-restrictions.php';
		
		// AJAX handlers (Lite features)
		add_action( 'wp_ajax_clockwork_offloader_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_clockwork_offloader_list_buckets', array( $this, 'ajax_list_buckets' ) );
		add_action( 'wp_ajax_clockwork_offloader_restore', array( $this, 'ajax_restore' ) );
		add_action( 'wp_ajax_clockwork_offloader_delete_from_cdn', array( $this, 'ajax_delete_from_cdn' ) );
		add_action( 'wp_ajax_clockwork_offloader_offload_from_server', array( $this, 'ajax_offload_from_server' ) );
		add_action( 'wp_ajax_clockwork_offloader_bulk_offload', array( $this, 'ajax_bulk_offload' ) );
		add_action( 'wp_ajax_clockwork_offloader_update_status', array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_clockwork_offloader_toggle_status', array( $this, 'ajax_toggle_status' ) );
		add_action( 'wp_ajax_clockwork_offloader_get_stats', array( $this, 'ajax_get_stats' ) );
		
		// Pro-only AJAX handlers (only register if Pro is active)
		if ( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			add_action( 'wp_ajax_clockwork_offloader_bulk_offload_from_server', array( $this, 'ajax_offload_from_server' ) );
			add_action( 'wp_ajax_clockwork_offloader_bulk_delete_from_server', array( $this, 'ajax_delete_from_server' ) );
			add_action( 'wp_ajax_clockwork_offloader_get_bulk_stats', array( $this, 'ajax_get_bulk_stats' ) );
			// Media library status icons removed for performance
			// add_action( 'wp_ajax_clockwork_offloader_get_media_status', array( $this, 'ajax_get_media_status' ) );
			add_action( 'wp_ajax_clockwork_offloader_add_to_queue', array( $this, 'ajax_add_to_queue' ) );
			add_action( 'wp_ajax_clockwork_offloader_add_all_to_queue', array( $this, 'ajax_add_all_to_queue' ) );
			add_action( 'wp_ajax_clockwork_offloader_get_queue_stats', array( $this, 'ajax_get_queue_stats' ) );
			add_action( 'wp_ajax_clockwork_offloader_cancel_queue', array( $this, 'ajax_cancel_queue' ) );
			add_action( 'wp_ajax_clockwork_offloader_retry_failed', array( $this, 'ajax_retry_failed' ) );
			add_action( 'wp_ajax_clockwork_offloader_process_queue_now', array( $this, 'ajax_process_queue_now' ) );
			
			// Bulk download AJAX handlers
			add_action( 'wp_ajax_clockwork_offloader_add_all_to_download_queue', array( $this, 'ajax_add_all_to_download_queue' ) );
			add_action( 'wp_ajax_clockwork_offloader_process_download_queue_now', array( $this, 'ajax_process_download_queue_now' ) );
			add_action( 'wp_ajax_clockwork_offloader_get_download_queue_stats', array( $this, 'ajax_get_download_queue_stats' ) );
			
			// Remove all files from bucket AJAX handler
			add_action( 'wp_ajax_clockwork_offloader_remove_all_from_bucket', array( $this, 'ajax_remove_all_from_bucket' ) );
			
			// Development mode AJAX handlers
			add_action( 'wp_ajax_clockwork_offloader_download_test_files', array( $this, 'ajax_download_test_files' ) );
			add_action( 'wp_ajax_clockwork_offloader_add_test_files_to_media', array( $this, 'ajax_add_test_files_to_media' ) );
			add_action( 'wp_ajax_clockwork_offloader_get_downloaded_files', array( $this, 'ajax_get_downloaded_files' ) );
			add_action( 'wp_ajax_clockwork_offloader_delete_dev_files', array( $this, 'ajax_delete_dev_files' ) );
			add_action( 'wp_ajax_clockwork_offloader_clear_test_data', array( $this, 'ajax_clear_test_data' ) );
			add_action( 'wp_ajax_clockwork_offloader_get_dev_files_count', array( $this, 'ajax_get_dev_files_count' ) );
			add_action( 'wp_ajax_clockwork_offloader_delete_downloaded_files', array( $this, 'ajax_delete_downloaded_files' ) );
			add_action( 'wp_ajax_clockwork_offloader_delete_single_downloaded_file', array( $this, 'ajax_delete_single_downloaded_file' ) );
			add_action( 'wp_ajax_clockwork_offloader_uninstall_data', array( $this, 'ajax_uninstall_data' ) );
			add_action( 'wp_ajax_clockwork_offloader_migration_diagnostic', array( $this, 'ajax_migration_diagnostic' ) );
			add_action( 'wp_ajax_clockwork_offloader_create_table', array( $this, 'ajax_create_table' ) );
			add_action( 'wp_ajax_clockwork_offloader_reset_migrator_notice', array( $this, 'ajax_reset_migrator_notice' ) );
		}
		
		// Setup wizard AJAX handlers
		add_action( 'wp_ajax_clockwork_offloader_setup_step1', array( $this, 'ajax_setup_step1' ) );
		add_action( 'wp_ajax_clockwork_offloader_setup_step2', array( $this, 'ajax_setup_step2' ) );
		add_action( 'wp_ajax_clockwork_offloader_setup_step3', array( $this, 'ajax_setup_step3' ) );
		add_action( 'wp_ajax_clockwork_offloader_setup_test_connection', array( $this, 'ajax_setup_test_connection' ) );
		add_action( 'wp_ajax_clockwork_offloader_verify_wp_config', array( $this, 'ajax_verify_wp_config' ) );
		
		// Migration popup AJAX handler
		add_action( 'wp_ajax_clockwork_offloader_dismiss_migration_popup', array( $this, 'ajax_dismiss_migration_popup' ) );
		add_action( 'wp_ajax_clockwork_offloader_dismiss_migrator_notice', array( $this, 'ajax_dismiss_migrator_notice' ) );
		
		// URL rewrite toggle AJAX handlers
		add_action( 'wp_ajax_clockwork_offloader_force_s3_urls', array( $this, 'ajax_force_s3_urls' ) );
		add_action( 'wp_ajax_clockwork_offloader_switch_back_local', array( $this, 'ajax_switch_back_local' ) );
		add_action( 'wp_ajax_clockwork_offloader_verify_urls', array( $this, 'ajax_verify_urls' ) );
	}
	
	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Determine title based on Pro status
		$page_title = Clockwork_Offloader_Lite_Restrictions::is_pro_active() 
			? __( 'Clockwork Offloader Pro', 'clockwork-offloader' )
			: __( 'Clockwork Offloader Lite', 'clockwork-offloader' );
		
		// Network admin menu (multisite only)
		if ( is_multisite() && is_network_admin() ) {
			$icon_url = CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/images/clockwork-logo.svg';
			add_menu_page(
				$page_title,
				$page_title,
				'manage_network_options',
				'clockwork-offloader',
				array( $this, 'render_network_settings_page' ),
				$icon_url,
				30
			);
		}
		
		// Site admin menu (always show for site settings)
		add_options_page(
			$page_title,
			$page_title,
			'manage_options',
			'clockwork-offloader',
			array( $this, 'render_main_page' )
		);
	}
	
	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_scripts( $hook ) {
		// Check if this is a Clockwork Offloader page (site or network admin)
		// Hook can be: settings_page_clockwork-offloader (site admin) or clockwork-offloader (network admin)
		$is_clockwork_page = ( strpos( $hook, 'clockwork-offloader' ) !== false );
		
		// Also check if we're on the options-general.php page with our page parameter
		if ( ! $is_clockwork_page && isset( $_GET['page'] ) && $_GET['page'] === 'clockwork-offloader' ) {
			$is_clockwork_page = true;
		}
		
		if ( ! $is_clockwork_page ) {
			return;
		}
		
		// Enqueue Dashicons for status icons (WordPress core native)
		wp_enqueue_style( 'dashicons' );
		
		$css_file = CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/css/admin.css';
		$css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : CLOCKWORK_OFFLOADER_VERSION;
		
		$js_file  = CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/js/admin.js';
		$js_ver   = file_exists( $js_file ) ? filemtime( $js_file ) : CLOCKWORK_OFFLOADER_VERSION;

		wp_enqueue_style(
			'clockwork-offloader-admin',
			CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			$css_ver
		);
		
		wp_enqueue_script(
			'clockwork-offloader-admin',
			CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			$js_ver,
			true
		);
		
		wp_localize_script(
			'clockwork-offloader-admin',
			'clockworkOffloader',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'adminUrl' => admin_url(),
				'nonce' => wp_create_nonce( 'clockwork_offloader_nonce' ),
				'strings' => array(
					'testing' => __( 'Testing connection...', 'clockwork-offloader' ),
					'success' => __( 'Connection successful!', 'clockwork-offloader' ),
					'error' => __( 'Connection failed.', 'clockwork-offloader' ),
					'offloading' => __( 'Offloading...', 'clockwork-offloader' ),
					'restoring' => __( 'Restoring...', 'clockwork-offloader' ),
					'offloaded' => __( 'Offloaded', 'clockwork-offloader' ),
					'notOffloaded' => __( 'Not Offloaded', 'clockwork-offloader' ),
					'restore' => __( 'Restore', 'clockwork-offloader' ),
					'offload' => __( 'Offload', 'clockwork-offloader' ),
					'testConnection' => __( 'Test Connection', 'clockwork-offloader' ),
					'fileOffloadedSuccess' => __( 'File offloaded successfully.', 'clockwork-offloader' ),
					'fileRestoredSuccess' => __( 'File restored successfully.', 'clockwork-offloader' ),
					'offloadFailed' => __( 'Failed to offload file.', 'clockwork-offloader' ),
					'restoreFailed' => __( 'Failed to restore file.', 'clockwork-offloader' ),
					'errorOccurred' => __( 'An error occurred.', 'clockwork-offloader' ),
					'loadingBuckets' => __( 'Loading buckets...', 'clockwork-offloader' ),
					'selectBucket' => __( 'Select a bucket...', 'clockwork-offloader' ),
				),
			)
		);
	}
	
	/**
	 * Register settings using WordPress Settings API
	 */
	public function register_settings() {
		register_setting(
			'clockwork_offloader_settings',
			'clockwork_offloader_settings',
			array( $this, 'sanitize_settings' )
		);
		
		// S3 Credentials Section
		add_settings_section(
			'clockwork_offloader_s3_section',
			__( 'S3 Configuration', 'clockwork-offloader' ),
			array( $this, 'render_s3_section_description' ),
			'clockwork-offloader-settings'
		);
		
		// S3 Fields
		add_settings_field(
			'provider',
			__( 'Storage Provider', 'clockwork-offloader' ),
			array( $this, 'render_s3_provider_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);

		add_settings_field(
			's3_access_key',
			__( 'AWS Access Key ID', 'clockwork-offloader' ),
			array( $this, 'render_s3_access_key_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);
		
		add_settings_field(
			's3_secret_key',
			__( 'AWS Secret Access Key', 'clockwork-offloader' ),
			array( $this, 'render_s3_secret_key_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);
		
		add_settings_field(
			's3_bucket',
			__( 'S3 Bucket Name', 'clockwork-offloader' ),
			array( $this, 'render_s3_bucket_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);
		
		add_settings_field(
			's3_region',
			__( 'S3 Region', 'clockwork-offloader' ),
			array( $this, 'render_s3_region_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);

		add_settings_field(
			's3_custom_endpoint',
			__( 'Custom S3 Endpoint (Optional)', 'clockwork-offloader' ),
			array( $this, 'render_s3_custom_endpoint_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);
		
		add_settings_field(
			's3_base_path',
			__( 'Base Path (Optional)', 'clockwork-offloader' ),
			array( $this, 'render_s3_base_path_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);
		
		add_settings_field(
			'cdn_domain',
			__( 'CDN Domain (Optional)', 'clockwork-offloader' ),
			array( $this, 'render_cdn_domain_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_s3_section'
		);
		
		// Options Section
		add_settings_section(
			'clockwork_offloader_options_section',
			__( 'Options', 'clockwork-offloader' ),
			null,
			'clockwork-offloader-settings'
		);
		
		add_settings_field(
			'auto_offload',
			__( 'Auto Upload', 'clockwork-offloader' ),
			array( $this, 'render_auto_offload_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_options_section'
		);
		
		add_settings_field(
			'delete_after_upload',
			__( 'Delete After Upload', 'clockwork-offloader' ),
			array( $this, 'render_delete_after_upload_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_options_section'
		);
		
		add_settings_field(
			'rewrite_urls',
			__( 'Rewrite URLs', 'clockwork-offloader' ),
			array( $this, 'render_rewrite_urls_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_options_section'
		);
		
		add_settings_field(
			'queue_batch_size',
			__( 'Queue Batch Size', 'clockwork-offloader' ),
			array( $this, 'render_queue_batch_size_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_options_section'
		);
		
		add_settings_field(
			'enable_throttle',
			__( 'Throttle Uploads', 'clockwork-offloader' ),
			array( $this, 'render_enable_throttle_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_options_section'
		);
		
		add_settings_field(
			'throttle_rate',
			__( 'Upload Rate', 'clockwork-offloader' ),
			array( $this, 'render_throttle_rate_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_options_section'
		);
		
		// Media library status icons removed for performance
		// add_settings_field(
		// 	'show_media_library_status',
		// 	__( 'Show CDN Status in Media Library', 'clockwork-offloader' ),
		// 	array( $this, 'render_show_media_library_status_field' ),
		// 	'clockwork-offloader-settings',
		// 	'clockwork_offloader_options_section'
		// );
		
		add_settings_field(
			'file_status_per_page',
			__( 'Items Per Page (File Status)', 'clockwork-offloader' ),
			array( $this, 'render_file_status_per_page_field' ),
			'clockwork-offloader-settings',
			'clockwork_offloader_options_section'
		);
		
		// Development Section
		// Development Mode section (Pro-only)
		if ( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			add_settings_section(
				'clockwork_offloader_development_section',
				__( 'Development', 'clockwork-offloader' ),
				null,
				'clockwork-offloader-settings'
			);
			
			add_settings_field(
				'development_mode',
				__( 'Development Mode', 'clockwork-offloader' ),
				array( $this, 'render_development_mode_field' ),
				'clockwork-offloader-settings',
				'clockwork_offloader_development_section'
			);
		}
	}
	
	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( $input ) {
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			return Clockwork_Offloader_Settings_Helper::get_settings();
		}
		
		$sanitized = array();
		
		// Sanitize provider field
		if ( isset( $input['provider'] ) ) {
			$provider = sanitize_text_field( $input['provider'] );
			$allowed_providers = array( 'aws', 'digitalocean', 'cloudflare_r2', 'wasabi', 'backblaze', 'minio', 'custom' );
			if ( in_array( $provider, $allowed_providers, true ) ) {
				$sanitized['provider'] = $provider;
			} else {
				$sanitized['provider'] = 'aws'; // Default to AWS
			}
		} else {
			// Default to AWS if not set (backward compatibility)
			$sanitized['provider'] = 'aws';
		}

		if ( isset( $input['s3_custom_endpoint'] ) ) {
			$sanitized['s3_custom_endpoint'] = esc_url_raw( trim( $input['s3_custom_endpoint'] ) );
		}
		
		if ( isset( $input['s3_access_key'] ) ) {
			$sanitized['s3_access_key'] = sanitize_text_field( $input['s3_access_key'] );
		}
		
		if ( isset( $input['s3_secret_key'] ) ) {
			$sanitized['s3_secret_key'] = sanitize_text_field( $input['s3_secret_key'] );
		}
		
		if ( isset( $input['s3_bucket'] ) ) {
			$sanitized['s3_bucket'] = sanitize_text_field( $input['s3_bucket'] );
		}
		
		if ( isset( $input['s3_region'] ) ) {
			$sanitized['s3_region'] = sanitize_text_field( $input['s3_region'] );
		}
		
		if ( isset( $input['s3_base_path'] ) ) {
			$sanitized['s3_base_path'] = sanitize_text_field( $input['s3_base_path'] );
		}
		
		if ( isset( $input['cdn_domain'] ) ) {
			$sanitized['cdn_domain'] = esc_url_raw( trim( $input['cdn_domain'] ) );
		}
		
		$sanitized['auto_offload'] = isset( $input['auto_offload'] ) && $input['auto_offload'];
		$sanitized['delete_after_upload'] = isset( $input['delete_after_upload'] ) && $input['delete_after_upload'];
		$sanitized['rewrite_urls'] = isset( $input['rewrite_urls'] ) && $input['rewrite_urls'];
		if ( is_multisite() && is_main_site() ) {
			$sanitized['force_multisite_subsites'] = ! empty( $input['force_multisite_subsites'] );
		}
		
		if ( isset( $input['queue_batch_size'] ) ) {
			$batch_size = absint( $input['queue_batch_size'] );
			$sanitized['queue_batch_size'] = max( 1, min( 100, $batch_size ) ); // Between 1 and 100
		}
		
		$sanitized['enable_throttle'] = isset( $input['enable_throttle'] ) && $input['enable_throttle'];
		
		if ( isset( $input['throttle_rate'] ) ) {
			$throttle_rate = absint( $input['throttle_rate'] );
			$sanitized['throttle_rate'] = max( 1, min( 10000, $throttle_rate ) ); // Between 1 and 10000
		} elseif ( $sanitized['enable_throttle'] ) {
			// Default to 100 if enabling throttle but no rate provided
			$sanitized['throttle_rate'] = 100;
		}
		
		// Media library status icons removed for performance
		// if ( isset( $input['show_media_library_status'] ) ) {
		// 	$sanitized['show_media_library_status'] = (bool) $input['show_media_library_status'];
		// } else {
		// 	$sanitized['show_media_library_status'] = false;
		// }
		
		if ( isset( $input['file_status_per_page'] ) ) {
			$per_page = absint( $input['file_status_per_page'] );
			$sanitized['file_status_per_page'] = max( 1, min( 999, $per_page ) ); // Between 1 and 999
		} else {
			$sanitized['file_status_per_page'] = 20; // Default to 20
		}
		
		$sanitized['development_mode'] = isset( $input['development_mode'] ) && $input['development_mode'];
		
		// Clear connection test cache when settings change
		delete_transient( 'clockwork_offloader_s3_connection_test' );
		
		return $sanitized;
	}
	
	/**
	 * Render S3 section description
	 */
	public function render_s3_section_description() {
		$s3_service = new Clockwork_Offloader_S3_Service();
		$using_wp_config = $s3_service->is_using_wp_config();
		
		echo '<p>' . esc_html__( 'Configure your Amazon S3 credentials and settings.', 'clockwork-offloader' ) . '</p>';
		
		if ( $using_wp_config ) {
			echo '<div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;"><p>';
			echo '<strong>' . esc_html__( 'Using wp-config.php credentials', 'clockwork-offloader' ) . '</strong><br />';
			echo esc_html__( 'Your AWS credentials and provider are currently loaded from wp-config.php. Region and bucket are stored in the database and can be edited below.', 'clockwork-offloader' );
			echo '</p></div>';
		} else {
			echo '<div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;"><p>';
			echo '<strong>' . esc_html__( 'Security Recommendation', 'clockwork-offloader' ) . '</strong><br />';
			echo esc_html__( 'For better security, consider storing your AWS credentials in wp-config.php instead of the database. Add this constant to your wp-config.php file:', 'clockwork-offloader' );
			echo '</p>';
			echo '<pre style="background: #f0f0f1; padding: 10px; margin: 10px 0; overflow-x: auto;">';
			echo esc_html( "define( 'CLOCKWORK_OFFLOADER_SETTINGS', serialize( array(\n" );
			echo esc_html( "    'provider' => 'aws',\n" );
			echo esc_html( "    'access-key-id' => 'your-access-key-here',\n" );
			echo esc_html( "    'secret-access-key' => 'your-secret-key-here',\n" );
			echo esc_html( ") ) );" );
			echo '</pre>';
			echo '<p>' . esc_html__( 'Note: Region and bucket are always stored in the database and can be edited below.', 'clockwork-offloader' ) . '</p>';
			echo '</div>';
		}
	}
	
	/**
	 * Render S3 access key field
	 */
	public function render_s3_access_key_field() {
		$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
		$using_wp_config = ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) );
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['s3_access_key'] ) ? $settings['s3_access_key'] : '';
		
		if ( $using_wp_config ) {
			$wp_config_value = $wp_config_creds['access-key-id'];
			?>
			<input type="text" id="s3_access_key" name="clockwork_offloader_settings[s3_access_key]" value="<?php echo esc_attr( $wp_config_value ); ?>" class="regular-text" disabled="disabled" />
			<p class="description">
				<?php esc_html_e( 'Your AWS Access Key ID is loaded from wp-config.php. To change it, update the CLOCKWORK_OFFLOADER_SETTINGS constant.', 'clockwork-offloader' ); ?>
			</p>
			<?php
		} else {
			?>
			<input type="text" id="s3_access_key" name="clockwork_offloader_settings[s3_access_key]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
			<p class="description"><?php esc_html_e( 'Your AWS Access Key ID. For better security, consider storing this in wp-config.php instead.', 'clockwork-offloader' ); ?></p>
			<?php
		}
	}
	
	/**
	 * Render S3 secret key field
	 */
	public function render_s3_secret_key_field() {
		$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
		$using_wp_config = ( $wp_config_creds && ! empty( $wp_config_creds['secret-access-key'] ) );
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['s3_secret_key'] ) ? $settings['s3_secret_key'] : '';
		
		if ( $using_wp_config ) {
			?>
			<input type="password" id="s3_secret_key" name="clockwork_offloader_settings[s3_secret_key]" value="••••••••" class="regular-text" disabled="disabled" />
			<p class="description">
				<?php esc_html_e( 'Your AWS Secret Access Key is loaded from wp-config.php. To change it, update the CLOCKWORK_OFFLOADER_SETTINGS constant.', 'clockwork-offloader' ); ?>
			</p>
			<?php
		} else {
			?>
			<input type="password" id="s3_secret_key" name="clockwork_offloader_settings[s3_secret_key]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
			<p class="description"><?php esc_html_e( 'Your AWS Secret Access Key. For better security, consider storing this in wp-config.php instead.', 'clockwork-offloader' ); ?></p>
			<?php
		}
	}
	
	/**
	 * Render S3 bucket field
	 * Always editable from database (never from wp-config)
	 */
	public function render_s3_bucket_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['s3_bucket'] ) ? $settings['s3_bucket'] : '';
			?>
			<div class="clockwork-bucket-selector">
				<select id="s3_bucket" name="clockwork_offloader_settings[s3_bucket]" class="regular-text" style="display: none;">
					<option value=""><?php esc_html_e( 'Select a bucket...', 'clockwork-offloader' ); ?></option>
				</select>
				<input type="text" id="s3_bucket_text" name="clockwork_offloader_settings[s3_bucket]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter bucket name or select from list', 'clockwork-offloader' ); ?>" />
				<button type="button" class="button button-secondary" id="load-buckets">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Load Buckets', 'clockwork-offloader' ); ?>
				</button>
				<span class="spinner" id="bucket-spinner" style="float: none; margin-left: 5px;"></span>
			</div>
			<p class="description">
				<?php esc_html_e( 'Enter the bucket name manually or click "Load Buckets" to select from your existing buckets.', 'clockwork-offloader' ); ?>
			</p>
			<?php
	}
	
	/**
	 * Render S3 region field
	 * Always editable from database (never from wp-config)
	 */
	public function render_s3_region_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['s3_region'] ) ? $settings['s3_region'] : 'us-east-1';
			?>
			<input type="text" id="s3_region" name="clockwork_offloader_settings[s3_region]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
			<p class="description"><?php esc_html_e( 'AWS region where your bucket is located (e.g., us-east-1, eu-west-1).', 'clockwork-offloader' ); ?></p>
			<?php
	}
	
	/**
	 * Render S3 base path field
	 */
	public function render_s3_base_path_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['s3_base_path'] ) ? $settings['s3_base_path'] : '';
		?>
		<input type="text" id="s3_base_path" name="clockwork_offloader_settings[s3_base_path]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="wp-uploads" />
		<p class="description"><?php esc_html_e( 'Optional prefix/path for all uploaded files in S3.', 'clockwork-offloader' ); ?></p>
		<?php
	}
	
	/**
	 * Render CDN domain field
	 */
	public function render_cdn_domain_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['cdn_domain'] ) ? $settings['cdn_domain'] : '';
		?>
		<input type="url" id="cdn_domain" name="clockwork_offloader_settings[cdn_domain]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="https://cdn.example.com" />
		<p class="description"><?php esc_html_e( 'Optional CDN domain to use instead of S3 URLs.', 'clockwork-offloader' ); ?></p>
		<?php
	}
	
	/**
	 * Render auto-offload field
	 */
	public function render_auto_offload_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['auto_offload'] ) ? $settings['auto_offload'] : false;
		?>
		<label class="clockwork-toggle-wrapper">
			<input type="checkbox" id="auto_offload" name="clockwork_offloader_settings[auto_offload]" value="1" class="clockwork-toggle-input" <?php checked( $value, true ); ?> />
			<span class="clockwork-toggle-slider"></span>
			<span class="clockwork-toggle-label"><?php esc_html_e( 'Automatically upload new media uploads to S3', 'clockwork-offloader' ); ?></span>
		</label>
		<?php
	}
	
	/**
	 * Render delete after upload field
	 */
	public function render_delete_after_upload_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['delete_after_upload'] ) ? $settings['delete_after_upload'] : false;
		?>
		<label class="clockwork-toggle-wrapper">
			<input type="checkbox" id="delete_after_upload" name="clockwork_offloader_settings[delete_after_upload]" value="1" class="clockwork-toggle-input" <?php checked( $value, true ); ?> />
			<span class="clockwork-toggle-slider"></span>
			<span class="clockwork-toggle-label"><?php esc_html_e( 'Delete files from local server after uploading to S3', 'clockwork-offloader' ); ?></span>
		</label>
		<p class="description">
			<?php if ( ! class_exists( 'Clockwork_Offloader_Pro' ) ) : ?>
				<?php esc_html_e( 'Warning: This will permanently delete files from your server. Make sure you have backups. To restore files from S3 back to your server, you will need Clockwork Offloader Pro. Do not do this unless you want to buy Pro very soon.', 'clockwork-offloader' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Warning: This will permanently delete files from your server. Make sure you have backups.', 'clockwork-offloader' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}
	
	/**
	 * Render rewrite URLs field
	 */
	public function render_rewrite_urls_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['rewrite_urls'] ) ? $settings['rewrite_urls'] : false;
		?>
		<label class="clockwork-toggle-wrapper">
			<input type="checkbox" id="rewrite_urls" name="clockwork_offloader_settings[rewrite_urls]" value="1" class="clockwork-toggle-input" <?php checked( $value, true ); ?> />
			<span class="clockwork-toggle-slider"></span>
			<span class="clockwork-toggle-label"><?php esc_html_e( 'Rewrite media URLs to point to S3', 'clockwork-offloader' ); ?></span>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, all media URLs will be rewritten to use S3 URLs.', 'clockwork-offloader' ); ?></p>
		<?php
	}
	
	/**
	 * Render queue batch size field
	 */
	public function render_queue_batch_size_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['queue_batch_size'] ) ? absint( $settings['queue_batch_size'] ) : 10;
		?>
		<input type="number" id="queue_batch_size" name="clockwork_offloader_settings[queue_batch_size]" value="<?php echo esc_attr( $value ); ?>" min="1" max="100" class="small-text" />
		<p class="description"><?php esc_html_e( 'Number of items to process per batch. Recommended: 10-50 for large sites. Lower values reduce server load but take longer.', 'clockwork-offloader' ); ?></p>
		<?php
	}
	
	/**
	 * Render enable throttle field
	 */
	public function render_enable_throttle_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['enable_throttle'] ) ? $settings['enable_throttle'] : false;
		?>
		<label class="clockwork-toggle-wrapper">
			<input type="checkbox" id="enable_throttle" name="clockwork_offloader_settings[enable_throttle]" value="1" class="clockwork-toggle-input" <?php checked( $value, true ); ?> />
			<span class="clockwork-toggle-slider"></span>
			<span class="clockwork-toggle-label"><?php esc_html_e( 'Enable upload throttling', 'clockwork-offloader' ); ?></span>
		</label>
		<p class="description"><?php esc_html_e( 'Limit the number of uploads per minute to avoid rate limiting or excessive API usage.', 'clockwork-offloader' ); ?></p>
		<?php
	}
	
	/**
	 * Render throttle rate field
	 */
	public function render_throttle_rate_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$throttle_enabled = isset( $settings['enable_throttle'] ) ? $settings['enable_throttle'] : false;
		$value = isset( $settings['throttle_rate'] ) ? absint( $settings['throttle_rate'] ) : 100;
		?>
		<div class="throttle-rate-wrapper" style="<?php echo $throttle_enabled ? '' : 'opacity: 0.5;'; ?>">
			<input type="number" id="throttle_rate" name="clockwork_offloader_settings[throttle_rate]" value="<?php echo esc_attr( $value ); ?>" min="1" max="10000" class="small-text" <?php echo $throttle_enabled ? '' : 'disabled'; ?> />
			<span><?php esc_html_e( 'objects per minute', 'clockwork-offloader' ); ?></span>
		</div>
		<p class="description">
			<?php esc_html_e( 'Maximum number of objects to upload per minute. Leave throttling disabled for unlimited uploads.', 'clockwork-offloader' ); ?>
		</p>
		<?php
	}
	
	// Media library status icons removed for performance - function removed
	
	/**
	 * Render file status per page field
	 */
	public function render_file_status_per_page_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['file_status_per_page'] ) ? absint( $settings['file_status_per_page'] ) : 20;
		if ( $value < 1 || $value > 999 ) {
			$value = 20;
		}
		?>
		<input type="number" id="file_status_per_page" name="clockwork_offloader_settings[file_status_per_page]" value="<?php echo esc_attr( $value ); ?>" min="1" max="999" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Number of items to display per page on the File Status tab. Default: 20. Maximum: 999.', 'clockwork-offloader' ); ?></p>
		<?php
	}
	
	/**
	 * Show migration notice if old constants were migrated
	 */
	public function show_migration_notice() {
		if ( get_transient( 'clockwork_offloader_migration_notice' ) ) {
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Clockwork Offloader: Settings Migrated', 'clockwork-offloader' ); ?></strong><br />
					<?php esc_html_e( 'Your settings have been migrated to the new format. Region and bucket are now stored in the database. If you had region/bucket in wp-config.php, they have been moved to the database.', 'clockwork-offloader' ); ?>
				</p>
			</div>
			<?php
			delete_transient( 'clockwork_offloader_migration_notice' );
		}
	}
	
	public function render_development_mode_field() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$value = isset( $settings['development_mode'] ) ? $settings['development_mode'] : false;
		?>
		<label class="clockwork-toggle-wrapper">
			<input type="checkbox" id="development_mode" name="clockwork_offloader_settings[development_mode]" value="1" class="clockwork-toggle-input" <?php checked( $value, true ); ?> />
			<span class="clockwork-toggle-slider"></span>
			<span class="clockwork-toggle-label"><?php esc_html_e( 'Enable Development Mode to access development tools and test file generation.', 'clockwork-offloader' ); ?></span>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, a Development tab will appear in the menu with tools to generate test media files.', 'clockwork-offloader' ); ?></p>
		<?php
	}
	
	/**
	 * Validate file path is within uploads directory
	 *
	 * @param string $file_path File path to validate
	 * @return bool True if valid, false otherwise
	 */
	private function is_valid_upload_path( $file_path ) {
		return Clockwork_Offloader::is_valid_upload_path( $file_path );
	}
	
	/**
	 * Render main page with tab navigation
	 */
	public function render_main_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		// Check if a successful migration has been completed
		$migration_complete = get_option( 'clockwork_offloader_migration_complete', false );
		$migration_settings_migrated = get_option( 'clockwork_offloader_migration_settings_migrated', false );
		$migration_files_migrated = get_option( 'clockwork_offloader_migration_files_migrated', false );
		
		// Check if credentials are configured - if not, show setup wizard
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		
		// Check if we have minimum required credentials (access key and secret key)
		$has_basic_credentials = ! empty( $credentials['access_key'] ) && ! empty( $credentials['secret_key'] );
		
		// Check if we have complete configuration (including bucket and region)
		$has_complete_config = $has_basic_credentials && ! empty( $credentials['bucket'] ) && ! empty( $credentials['region'] );
		
		// Check if files have been migrated/offloaded (indicates successful migration)
		$tracker = new Clockwork_Offloader_Tracker();
		$stats = $tracker->get_statistics();
		$has_offloaded_files = isset( $stats['total_offloaded'] ) && $stats['total_offloaded'] > 0;
		
		// If migration was completed (settings AND files migrated), skip setup wizard
		if ( $migration_complete && $migration_settings_migrated && ( $migration_files_migrated || $has_offloaded_files ) ) {
			// Migration was successful - skip setup wizard and go directly to dashboard
		} elseif ( $has_complete_config && $has_offloaded_files ) {
			// Complete config and offloaded files exist - skip setup wizard
		} elseif ( ! $has_basic_credentials ) {
			// If we don't have basic credentials, show setup wizard
			$this->render_setup_wizard();
			return;
		} elseif ( ! $has_complete_config ) {
			// If we have basic credentials but not complete config, show setup wizard starting at step 2
			$this->render_setup_wizard();
			return;
		}
		
		// Check if Pro is active and should show welcome screen (after Lite setup is complete)
		if ( Clockwork_Offloader_Lite_Restrictions::is_pro_active() && 
		     class_exists( 'Clockwork_Offloader_Pro_Admin' ) && 
		     Clockwork_Offloader_Pro_Admin::should_show_welcome() ) {
			Clockwork_Offloader_Pro_Admin::render_welcome_screen();
			return;
		}
		
		// Get active tab from URL, default to 'dashboard'
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		
		// Define tabs - Lite tabs first
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'clockwork-offloader' ),
			'file-status' => __( 'Files', 'clockwork-offloader' ),
		);
		
		// Pro-only tabs (only show if Pro is active)
		if ( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			$tabs['bulk'] = __( 'Bulk', 'clockwork-offloader' );
		}
		
		// Settings tab (always available)
		$tabs['settings'] = __( 'Settings', 'clockwork-offloader' );
		
		// Pro-only tabs continued
		if ( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			$tabs['migration'] = __( 'Migration', 'clockwork-offloader' );
			$tabs['cloudfront'] = __( 'CDN / CloudFront', 'clockwork-offloader' );
			$tabs['license'] = __( 'License', 'clockwork-offloader' );
			
			// Add Development tab conditionally
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			if ( ! empty( $settings['development_mode'] ) ) {
				$tabs['development'] = __( 'Development Mode', 'clockwork-offloader' );
			}
		} else {
			// In Lite, add "Upgrade to Pro" link
			$tabs['upgrade'] = __( 'Upgrade to Pro', 'clockwork-offloader' );
		}
		
		// Get statistics for header status
		$tracker = new Clockwork_Offloader_Tracker();
		$stats = $tracker->get_statistics();
		
		// Check if we should show migration popup
		$show_migration_popup = $this->should_show_migration_popup( $credentials, $stats );
		
		// Calculate percentage based on unique attachments that are offloaded
		// We need to count attachments that have at least the original file offloaded (size_name = '')
		global $wpdb;
		$table_name = $wpdb->prefix . 'clockwork_offloads';
		// Table name is safe (constructed from $wpdb->prefix), but escape for WordPress standards
		$table_name_escaped = esc_sql( $table_name );
		$posts_table_escaped = esc_sql( $wpdb->posts );
		
		$offloaded_attachments = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT attachment_id) FROM `{$table_name_escaped}` WHERE status = %s AND size_name = %s",
				'offloaded',
				''
			)
		);
		
		// Get total attachments
		$total_attachments = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$posts_table_escaped}` WHERE post_type = %s AND post_status = %s",
				'attachment',
				'inherit'
			)
		);
		
		$offload_percentage = $total_attachments > 0 
			? round( ( $offloaded_attachments / $total_attachments ) * 100 ) 
			: 0;
		
		// Cap at 100% to prevent display issues
		$offload_percentage = min( $offload_percentage, 100 );
		
		// Render tab navigation
		?>
		<div class="wrap clockwork-offloader-wrap">
			<h1 class="wp-heading-inline screen-reader-text"><?php echo esc_html( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ? __( 'Clockwork Offloader Pro', 'clockwork-offloader' ) : __( 'Clockwork Offloader Lite', 'clockwork-offloader' ) ); ?></h1>
			<hr class="wp-header-end">

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
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ? __( 'Clockwork Offloader Pro', 'clockwork-offloader' ) : __( 'Clockwork Offloader Lite', 'clockwork-offloader' ) ); ?>" />
								<?php else : ?>
									<span class="dashicons dashicons-cloud"></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="clockwork-offloader-title">
							<span class="clockwork-brand-heading"><?php echo esc_html( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ? __( 'Clockwork Offloader Pro', 'clockwork-offloader' ) : __( 'Clockwork Offloader Lite', 'clockwork-offloader' ) ); ?></span>
						</div>
					</div>
					<div class="clockwork-offloader-header-right">
						<div class="clockwork-offloader-status-indicator">
							<?php if ( $offload_percentage >= 100 ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: var(--cwk-accent); margin-right: 5px;"></span>
							<?php endif; ?>
							<span class="clockwork-offloader-status-text"><?php echo esc_html( $offload_percentage ); ?>% Offloaded</span>
							<div class="clockwork-offloader-progress-bar">
								<div class="clockwork-offloader-progress-fill" style="width: <?php echo esc_attr( $offload_percentage ); ?>%;"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			
			<!-- Status Bar -->
			<div class="clockwork-offloader-status-bar">
				<nav class="nav-tab-wrapper clockwork-offloader-nav-tabs">
				<?php
				foreach ( $tabs as $tab_slug => $tab_label ) {
					$tab_url = add_query_arg( array(
						'page' => 'clockwork-offloader',
						'tab' => $tab_slug,
					), admin_url( 'options-general.php' ) );
					$active_class = ( $active_tab === $tab_slug ) ? ' nav-tab-active' : '';
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab<?php echo esc_attr( $active_class ); ?><?php echo $tab_slug === 'upgrade' ? ' nav-tab-upgrade' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
					<?php
				}
				?>
				</nav>
			</div>
			
			<div class="clockwork-offloader-tab-content">
				<?php
				// Route to appropriate render method
				switch ( $active_tab ) {
					case 'dashboard':
						$this->render_dashboard();
						break;
					case 'settings':
						$this->render_settings();
						break;
					case 'file-status':
						// Lite version: show simple file list
						// Pro version: show bulk offload view
						if ( Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
							$this->render_bulk_offload();
						} else {
							$this->render_file_list();
						}
						break;
					case 'bulk':
						// Pro feature - check if Pro is active
						if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
							Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'Bulk Tools', 'clockwork-offloader' ) );
							$this->render_dashboard();
							break;
						}
						$this->render_bulk_tools();
						break;
					case 'development':
						// Pro feature - check if Pro is active
						if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
							Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'Development Mode', 'clockwork-offloader' ) );
							$this->render_dashboard();
							break;
						}
						if ( ! empty( $settings['development_mode'] ) ) {
							$this->render_development();
						}
						break;
					case 'migration':
						// Pro feature - check if Pro is active
						if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
							Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'Migration', 'clockwork-offloader' ) );
							$this->render_dashboard();
							break;
						}
						$this->render_migration();
						break;
					case 'cloudfront':
						// Pro feature - check if Pro is active
						if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
							Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'CDN / CloudFront', 'clockwork-offloader' ) );
							$this->render_dashboard();
							break;
						}
						$this->render_cloudfront();
						break;
					case 'license':
						// Pro feature - check if Pro is active
						if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
							Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'License', 'clockwork-offloader' ) );
							$this->render_dashboard();
							break;
						}
						$this->render_license();
						break;
					case 'upgrade':
						// Show upgrade page
						$this->render_upgrade_page();
						break;
					default:
						$this->render_dashboard();
						break;
				}
				?>
			</div>
		</div>
		
		<?php if ( $show_migration_popup ) : ?>
			<!-- Migration Popup Modal -->
			<div id="clockwork-migration-popup" class="clockwork-modal" style="display: block;">
				<div class="clockwork-modal-content" style="max-width: 600px;">
					<div class="clockwork-modal-header">
						<h2><?php esc_html_e( 'Start with Migration Tool', 'clockwork-offloader' ); ?></h2>
						<span class="clockwork-modal-close" id="clockwork-migration-popup-close">&times;</span>
					</div>
					<div class="clockwork-modal-body">
		<?php
						$source_plugin_installed = file_exists( WP_PLUGIN_DIR . '/amazon-s3-and-cloudfront/wordpress-s3.php' ) || 
						                            file_exists( WP_PLUGIN_DIR . '/offload-media-cloud-storage/start.php' );
						?>
						<p style="font-size: 15px; line-height: 1.6; margin-bottom: 20px;">
							<?php 
							echo esc_html( __( 'We detected that you have another offload plugin installed. Before configuring Clockwork Offloader, we recommend using the Migration Tool to migrate your existing offloaded files and settings.', 'clockwork-offloader' ) ); 
							?>
						</p>
						<p style="font-size: 15px; line-height: 1.6; margin-bottom: 25px;">
							<?php 
							echo esc_html( __( 'The Migration Tool will help you transfer all your offloaded media and settings from your existing plugin to Clockwork Offloader seamlessly.', 'clockwork-offloader' ) ); 
							?>
						</p>
						<div style="display: flex; gap: 10px; align-items: center; margin-top: 25px;">
							<a href="<?php echo esc_url( admin_url( 'tools.php?page=clockwork-offload-migrator' ) ); ?>" class="button button-primary" style="font-size: 14px; padding: 8px 16px;">
								<span class="dashicons dashicons-migrate" style="margin-top: 3px; margin-right: 5px;"></span>
								<?php esc_html_e( 'Open Migration Tool', 'clockwork-offloader' ); ?>
							</a>
							<button type="button" class="button button-secondary" id="clockwork-migration-popup-dismiss" style="font-size: 14px; padding: 8px 16px;">
								<?php esc_html_e( 'Dismiss', 'clockwork-offloader' ); ?>
							</button>
						</div>
						<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" id="clockwork-migration-popup-dont-show" />
								<span style="font-size: 13px; color: #646970;">
									<?php esc_html_e( "Don't show this message again", 'clockwork-offloader' ); ?>
								</span>
							</label>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}
	
	/**
	 * Render network settings page (multisite only)
	 */
	public function render_network_settings_page() {
		if ( ! is_multisite() || ! is_network_admin() ) {
			wp_die( esc_html__( 'This page is only available in network admin.', 'clockwork-offloader' ) );
		}
		
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		// Handle form submission
		if ( isset( $_POST['clockwork_network_settings_submit'] ) && check_admin_referer( 'clockwork_network_settings' ) ) {
			$network_mode = isset( $_POST['clockwork_network_mode'] ) ? (bool) $_POST['clockwork_network_mode'] : false;
			Clockwork_Offloader_Settings_Helper::set_network_mode( $network_mode );
			
			// If network mode is enabled, save network settings
			if ( $network_mode ) {
				$network_settings = Clockwork_Offloader_Settings_Helper::get_network_settings();
				
				// Update settings from form with proper sanitization
				if ( isset( $_POST['clockwork_network_settings'] ) && is_array( $_POST['clockwork_network_settings'] ) ) {
					$new_settings = array();
					$post_settings = wp_unslash( $_POST['clockwork_network_settings'] );
					
					// Sanitize provider (must be in whitelist)
					if ( isset( $post_settings['provider'] ) ) {
						$allowed_providers = array( 'aws', 'digitalocean', 'cloudflare_r2', 'wasabi', 'backblaze', 'minio', 'custom' );
						$provider = sanitize_text_field( $post_settings['provider'] );
						if ( in_array( $provider, $allowed_providers, true ) ) {
							$new_settings['provider'] = $provider;
						}
					}
					
					// Sanitize credentials
					if ( isset( $post_settings['s3_access_key'] ) ) {
						$new_settings['s3_access_key'] = sanitize_text_field( $post_settings['s3_access_key'] );
					}
					if ( isset( $post_settings['s3_secret_key'] ) ) {
						$new_settings['s3_secret_key'] = sanitize_text_field( $post_settings['s3_secret_key'] );
					}
					if ( isset( $post_settings['s3_bucket'] ) ) {
						$new_settings['s3_bucket'] = sanitize_text_field( $post_settings['s3_bucket'] );
					}
					if ( isset( $post_settings['s3_region'] ) ) {
						$new_settings['s3_region'] = sanitize_text_field( $post_settings['s3_region'] );
					}
					if ( isset( $post_settings['s3_base_path'] ) ) {
						$new_settings['s3_base_path'] = trim( sanitize_text_field( $post_settings['s3_base_path'] ), '/' );
					}
					if ( isset( $post_settings['cdn_domain'] ) ) {
						$new_settings['cdn_domain'] = esc_url_raw( trim( $post_settings['cdn_domain'] ) );
					}

					// Feature toggles. Checkboxes are absent from $_POST when unticked, so these
					// are always written (true/false) rather than only when present.
					foreach ( array( 'auto_offload', 'delete_after_upload', 'rewrite_urls', 'enable_throttle' ) as $toggle ) {
						$new_settings[ $toggle ] = ! empty( $post_settings[ $toggle ] );
					}
					if ( isset( $post_settings['queue_batch_size'] ) ) {
						$new_settings['queue_batch_size'] = max( 1, min( 100, absint( $post_settings['queue_batch_size'] ) ) );
					}
					if ( isset( $post_settings['throttle_rate'] ) ) {
						$new_settings['throttle_rate'] = max( 1, min( 10000, absint( $post_settings['throttle_rate'] ) ) );
					}

					$network_settings = array_merge( $network_settings, $new_settings );
					Clockwork_Offloader_Settings_Helper::update_network_settings( $network_settings );
				}
			}
			
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'clockwork-offloader' ) . '</p></div>';
		}
		
		$network_mode = Clockwork_Offloader_Settings_Helper::is_network_mode_enabled();
		$network_settings = Clockwork_Offloader_Settings_Helper::get_network_settings();
		$settings_source = Clockwork_Offloader_Settings_Helper::get_settings_source();
		
		include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/network-settings.php';
	}
	
	/**
	 * Render dashboard page
	 */
	public function render_dashboard() {
		$tracker = new Clockwork_Offloader_Tracker();
		$stats = $tracker->get_statistics();
		
		// Check if credentials are configured (wp-config.php or database)
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		$has_credentials = ! empty( $credentials['access_key'] ) && ! empty( $credentials['secret_key'] );
		
		// Get URL preview data
		$url_preview = $this->get_url_preview_data( $credentials );
		
		include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/dashboard.php';
	}
	
	/**
	 * Get URL preview data for display
	 *
	 * @param array $credentials Current credentials
	 * @return array URL preview components
	 */
	private function get_url_preview_data( $credentials ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$provider = $credentials['provider'] ?? 'aws';
		$bucket = $credentials['bucket'] ?? '';
		$region = $credentials['region'] ?? 'us-east-1';
		$account_id = $credentials['account_id'] ?? '';
		$base_path = ! empty( $settings['s3_base_path'] ) ? trim( $settings['s3_base_path'], '/' ) : '';
		$cdn_domain = ! empty( $settings['cdn_domain'] ) ? rtrim( $settings['cdn_domain'], '/' ) : '';
		
		// Example file path — matches what generate_s3_key() actually produces:
		// the uploads basedir is stripped entirely, leaving just the year/month/file structure.
		$example_path = '2025/12/example.jpg';
		
		// Build S3 key
		$s3_key = $example_path;
		if ( ! empty( $base_path ) ) {
			$s3_key = $base_path . '/' . $s3_key;
		}
		
		// Determine scheme
		$scheme = 'https://';
		
		// Determine domain based on provider and CDN
		$domain = '';
		$show_bucket_in_path = false;
		
		if ( ! empty( $cdn_domain ) ) {
			// CDN domain takes priority
			$domain = str_replace( array( 'http://', 'https://' ), '', $cdn_domain );
			$show_bucket_in_path = false;
		} else {
			// Use provider-specific domain
			switch ( $provider ) {
				case 'digitalocean':
					if ( ! empty( $bucket ) && ! empty( $region ) ) {
						$domain = $bucket . '.' . $region . '.digitaloceanspaces.com';
					} else {
						$domain = 'bucket.region.digitaloceanspaces.com';
					}
					$show_bucket_in_path = false;
					break;
				default: // AWS
					if ( ! empty( $bucket ) && ! empty( $region ) ) {
						if ( Clockwork_Offloader_S3_Service::bucket_requires_path_style( $bucket ) ) {
							// Dotted bucket names must be path-style over HTTPS (see build_public_url).
							$domain              = 's3.' . $region . '.amazonaws.com';
							$show_bucket_in_path = true;
						} else {
							$domain              = $bucket . '.s3.' . $region . '.amazonaws.com';
							$show_bucket_in_path = false;
						}
					} else {
						$domain              = 'bucket.s3.region.amazonaws.com';
						$show_bucket_in_path = false;
					}
					break;
			}
		}
		
		// Build full URL (bucket goes in the path for path-style AWS buckets)
		$full_url = $scheme . $domain . '/' . ( ( $show_bucket_in_path && ! empty( $bucket ) ) ? $bucket . '/' : '' ) . $s3_key;
		
		// Parse components for display
		$components = array(
			'scheme' => $scheme,
			'domain' => $domain . '/',
			'bucket' => ( $show_bucket_in_path && ! empty( $bucket ) ) ? $bucket . '/' : '',
			'prefix' => ! empty( $base_path ) ? $base_path . '/' : '',
			'path' => 'wp-content/uploads/',
			'year_month' => '2025/12/',
			'filename' => 'example.jpg',
		);
		
		// For providers where bucket is in domain (not path), don't show bucket separately
		if ( ! $show_bucket_in_path ) {
			$components['bucket'] = '';
		}
		
		return array(
			'components' => $components,
			'full_url' => $full_url,
			'provider' => $provider,
			'has_cdn' => ! empty( $cdn_domain ),
			'has_base_path' => ! empty( $base_path ),
		);
	}
	
	/**
	 * Render settings page
	 */
	public function render_settings() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/settings.php';
	}
	
	/**
	 * Render file list page (Lite version)
	 */
	public function render_file_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/file-list.php';
	}
	
	/**
	 * Render bulk offload page
	 */
	/**
	 * Render bulk offload page
	 */
	public function render_bulk_offload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		$bulk_offloader = new Clockwork_Offloader_Bulk_Offloader();
		
		// Get filter - sanitize input
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';
		$allowed_filters = array( 'all', 'offloaded', 'not-offloaded' );
		if ( ! in_array( $filter, $allowed_filters, true ) ) {
			$filter = 'all';
		}
		
		// Get search query
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		
		// Get file type filter
		$mime_type = isset( $_GET['mime_type'] ) ? sanitize_text_field( wp_unslash( $_GET['mime_type'] ) ) : '';
		
		// Get date filter
		$month = isset( $_GET['m'] ) ? absint( $_GET['m'] ) : 0;
		
		// Get orderby and order
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}
		
		// Get pagination
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		
		// Get per-page setting from plugin settings (default 20, max 999)
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$per_page = isset( $settings['file_status_per_page'] ) ? absint( $settings['file_status_per_page'] ) : 20;
		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 999 ) {
			$per_page = 999;
		}
		
		// Build query args
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => $per_page,
			'paged' => $paged,
		);
		
		// Add search
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
			// Enable filename search
			add_filter( 'wp_allow_query_attachment_by_filename', '__return_true' );
		}
		
		// Add mime type filter
		if ( ! empty( $mime_type ) ) {
			$args['post_mime_type'] = $mime_type;
		}
		
		// Add date filter
		if ( $month > 0 ) {
			$args['m'] = $month;
		}
		
		// Add ordering
		$args['orderby'] = $orderby;
		$args['order'] = $order;
		
		// Get attachments
		$attachments_query = new WP_Query( $args );
		$all_attachments = $attachments_query->posts;
		$total_attachments = $attachments_query->found_posts;
		$total_pages = $attachments_query->max_num_pages;
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Apply offload filter if needed (after getting all attachments)
		$attachments = array();
		foreach ( $all_attachments as $attachment ) {
			$is_offloaded = $tracker->is_offloaded( $attachment->ID );
			if ( 'all' === $filter ) {
				$attachments[] = $attachment;
			} elseif ( 'offloaded' === $filter && $is_offloaded ) {
				$attachments[] = $attachment;
			} elseif ( 'not-offloaded' === $filter && ! $is_offloaded ) {
				$attachments[] = $attachment;
			}
		}
		
		// Get available mime types for filter
		$mime_types = get_post_mime_types();
		
		// Pass pagination data to view
		$pagination_data = array(
			'total_items' => $total_attachments,
			'total_pages' => $total_pages,
			'current_page' => $paged,
			'per_page' => $per_page,
		);
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/bulk-offload.php';
	}
	
	/**
	 * Render bulk download page
	 */
	public function render_bulk_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		// Use efficient SQL query instead of loading all attachments (prevents timeout with 600k+ items)
		global $wpdb;
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Count offloaded attachments efficiently
		$tracker_table = $wpdb->prefix . 'clockwork_offloads';
		$offloaded_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT attachment_id) FROM " . esc_sql( $tracker_table ) . " WHERE status = 'offloaded'"
		);
		
		// For very large sites, show approximate count
		// Note: We can't efficiently check which files exist locally without loading them,
		// so we show the total offloaded count as an approximation
		$total_needs_download = $offloaded_count;
		$is_large_site = $offloaded_count > 10000;
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/bulk-download.php';
	}
	
	/**
	 * Render bulk tools page (combined bulk operations)
	 */
	public function render_bulk_tools() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'Bulk Tools', 'clockwork-offloader' ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		$queue = new Clockwork_Offloader_Queue();
		$queue_stats = $queue->get_statistics();
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Get failed items with error messages for display
		$failed_items = array();
		if ( $queue_stats['failed'] > 0 ) {
			$failed_queue_items = $queue->get_failed_items( 10 ); // Get up to 10 failed items
			foreach ( $failed_queue_items as $item ) {
				$attachment = get_post( $item->attachment_id );
				if ( $attachment ) {
					$failed_items[] = array(
						'attachment_id' => $item->attachment_id,
						'filename' => get_the_title( $item->attachment_id ),
						'error' => $item->error_message,
					);
				}
			}
		}
		
		// Calculate bulk offload stats using efficient SQL queries (prevents timeout with 600k+ items)
		global $wpdb;
		
		// Count total attachments efficiently
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);
		
		// Count offloaded attachments efficiently
		$tracker_table = $wpdb->prefix . 'clockwork_offloads';
		$offloaded_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT o.attachment_id) FROM " . esc_sql( $tracker_table ) . " o
			INNER JOIN {$wpdb->posts} p ON o.attachment_id = p.ID
			WHERE o.status = 'offloaded' AND p.post_type = 'attachment' AND p.post_status = 'inherit'"
		);
		
		// Count queued attachments (pending/processing) - these are being worked on
		$queue_table = $wpdb->prefix . 'clockwork_offload_queue';
		$queued_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT q.attachment_id) FROM " . esc_sql( $queue_table ) . " q
			INNER JOIN {$wpdb->posts} p ON q.attachment_id = p.ID
			WHERE q.status IN ('pending', 'processing') AND p.post_type = 'attachment' AND p.post_status = 'inherit'"
		);
		
		// Calculate not offloaded count (total - offloaded)
		$total_not_offloaded = max( 0, $total_attachments - $offloaded_count );
		
		// Calculate actual offload percentage: offloaded / total * 100
		$offload_percentage = $total_attachments > 0 ? round( ( $offloaded_count / $total_attachments ) * 100 ) : 0;
		
		// Cap at 100% to prevent display issues
		$offload_percentage = min( $offload_percentage, 100 );
		
		// Calculate actual offloaded count (for display)
		$offloaded_count_display = $offloaded_count;
		
		// Calculate "Download all files from bucket to server" stats
		// Count offloaded items that don't have local files (efficient SQL query)
		$total_needs_download = 0; // Note: This would require checking file existence, which is expensive
		// For now, we'll calculate this on-demand when needed, or use a cached value
		// This prevents timeouts on large sites
		
		// Calculate "Remove all files from bucket" stats (same as offloaded_count)
		$total_on_bucket = $offloaded_count;
		
		// Check if there are items still processing in the queue (for view)
		$has_active_queue = ( $queue_stats['pending'] > 0 || $queue_stats['processing'] > 0 );
		
		// Calculate URL rewrite statistics - use same data source as offload stats for consistency
		$total_attachments_for_rewrite = $total_attachments; // Same total
		$offloaded_attachments_for_rewrite = $offloaded_count; // Same offloaded count
		$rewrite_percentage = $total_attachments_for_rewrite > 0 ? round( ( $offloaded_attachments_for_rewrite / $total_attachments_for_rewrite ) * 100 ) : 0;
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/bulk-tools.php';
	}
	
	/**
	 * Render bulk upload page
	 */
	public function render_bulk_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		$queue = new Clockwork_Offloader_Queue();
		$queue_stats = $queue->get_statistics();
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Use efficient SQL query instead of loading all attachments (prevents timeout with 600k+ items)
		global $wpdb;
		
		// Count total attachments efficiently
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);
		
		// Count offloaded attachments efficiently
		$tracker_table = $wpdb->prefix . 'clockwork_offloads';
		$offloaded_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT attachment_id) FROM " . esc_sql( $tracker_table ) . " WHERE status = 'offloaded'"
		);
		
		// Count queued attachments (pending/processing)
		$queue_table = $wpdb->prefix . 'clockwork_offload_queue';
		$queued_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT attachment_id) FROM " . esc_sql( $queue_table ) . " WHERE status IN ('pending', 'processing')"
		);
		
		// Calculate not offloaded count (total - offloaded - queued)
		// Note: This is an approximation, but much more efficient than loading all 600k items
		$total_not_offloaded = max( 0, $total_attachments - $offloaded_count - $queued_count );
		
		// For very large sites, show approximate count with note
		$is_large_site = $total_attachments > 10000;
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/bulk-upload.php';
	}
	
	/**
	 * Render development page
	 */
	public function render_development() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'Development Mode', 'clockwork-offloader' ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		// Check if development mode is still enabled
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		if ( empty( $settings['development_mode'] ) ) {
			wp_die( esc_html__( 'Development Mode is not enabled. Please enable it in Settings.', 'clockwork-offloader' ) );
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		// Get count of development files
		$dev_files_count = $dev_helper->get_dev_files_count();
		
		// Get downloaded files (this will also clean up unsupported types)
		$downloaded_files = $dev_helper->get_downloaded_files();
		
		// Get supported file types
		$file_types = $dev_helper->get_supported_file_types();
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/development.php';
	}
	
	/**
	 * Render migration page
	 */
	public function render_migration() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'Migration', 'clockwork-offloader' ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		// Check if migrator class exists
		if ( ! class_exists( 'Clockwork_Offload_Migrator_Migrator' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Migration classes not found. Please ensure Clockwork Offloader Pro is properly installed.', 'clockwork-offloader' ) . '</p></div>';
			return;
		}
		
		$migrator = new Clockwork_Offload_Migrator_Migrator();
		$is_clockwork_configured = $migrator->is_clockwork_configured();
		
		// Get logo URL
		$logo_url = CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/images/clockwork-logo.svg';
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/migration-wizard.php';
	}
	
	/**
	 * Render CloudFront settings page
	 */
	public function render_cloudfront() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'CDN / CloudFront', 'clockwork-offloader' ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/cloudfront-settings.php';
	}

	/**
	 * Render license settings page
	 */
	public function render_license() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			Clockwork_Offloader_Lite_Restrictions::show_upgrade_notice( __( 'License', 'clockwork-offloader' ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		include CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'admin/views/license-settings.php';
	}

	/**
	 * Render setup wizard
	 */
	public function render_setup_wizard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
		}
		
		// Hide admin notices from other plugins on setup wizard page
		add_action( 'admin_notices', array( $this, 'filter_setup_wizard_notices' ), 1 );
		
		// Get current step from URL
		$current_step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		
		// Check if credentials are already defined
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		$has_basic_credentials = ! empty( $credentials['access_key'] ) && ! empty( $credentials['secret_key'] );
		$has_complete_config = $has_basic_credentials && ! empty( $credentials['bucket'] ) && ! empty( $credentials['region'] );
		
		// If no step specified and basic credentials exist but config is incomplete, default to step 2
		// But allow explicit navigation to step 1 if user wants to see it
		if ( ! isset( $_GET['step'] ) && $has_basic_credentials && ! $has_complete_config ) {
			$current_step = 2;
		}
		
		$current_step = max( 1, min( 3, $current_step ) ); // Ensure step is between 1 and 3
		
		// Get saved setup data from transient (for going back/forward)
		$setup_data = get_transient( 'clockwork_offloader_setup_data' );
		
		// Check if wp-config.php constants are already defined
		$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
		$has_wp_config = ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) );
		
		// Also check for WP Offload Media's AS3CF_SETTINGS constant
		$detected_access_key = '';
		$detected_secret_key = '';
		$detected_provider = 'aws';
		
		if ( $has_wp_config ) {
			$detected_access_key = $wp_config_creds['access-key-id'];
			$detected_secret_key = $wp_config_creds['secret-access-key'];
			$detected_provider = ! empty( $wp_config_creds['provider'] ) ? $wp_config_creds['provider'] : 'aws';
		} elseif ( defined( 'AS3CF_SETTINGS' ) ) {
			// Check WP Offload Media's constant
			$as3cf_settings = AS3CF_SETTINGS;
			if ( is_string( $as3cf_settings ) ) {
				$as3cf_settings = @unserialize( $as3cf_settings );
			}
			if ( is_array( $as3cf_settings ) ) {
				$detected_access_key = isset( $as3cf_settings['access-key-id'] ) ? $as3cf_settings['access-key-id'] : '';
				$detected_secret_key = isset( $as3cf_settings['secret-access-key'] ) ? $as3cf_settings['secret-access-key'] : '';
				$detected_provider = isset( $as3cf_settings['provider'] ) ? $as3cf_settings['provider'] : 'aws';
			}
		}
		
		// If credentials are found from any source, generate the code snippet with actual values
		$wp_config_code_snippet = '';
		if ( ! empty( $detected_access_key ) && ! empty( $detected_secret_key ) ) {
			$wp_config_code_snippet = "define( 'CLOCKWORK_OFFLOADER_SETTINGS', serialize( array(\n";
			$wp_config_code_snippet .= "    'provider' => '" . esc_js( $detected_provider ) . "',\n";
			$wp_config_code_snippet .= "    'access-key-id' => '" . esc_js( $detected_access_key ) . "',\n";
			$wp_config_code_snippet .= "    'secret-access-key' => '" . esc_js( $detected_secret_key ) . "',\n";
			$wp_config_code_snippet .= ") ) );";
		} else {
			// Default placeholder code
			$wp_config_code_snippet = "define( 'CLOCKWORK_OFFLOADER_SETTINGS', serialize( array(\n";
			$wp_config_code_snippet .= "    'provider' => 'aws',\n";
			$wp_config_code_snippet .= "    'access-key-id' => 'your-access-key-here',\n";
			$wp_config_code_snippet .= "    'secret-access-key' => 'your-secret-key-here',\n";
			$wp_config_code_snippet .= ") ) );";
		}
		
		if ( ! is_array( $setup_data ) ) {
			// Get provider from settings or default to 'aws'
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			$default_provider = isset( $settings['provider'] ) ? $settings['provider'] : 'aws';
			
			$setup_data = array(
				'provider' => $default_provider,
				'connection_method' => $has_wp_config ? 'wp-config' : 'wp-config', // Default to wp-config.php (recommended)
				'access_key' => '',
				'secret_key' => '',
				'region' => isset( $settings['s3_region'] ) ? $settings['s3_region'] : '',
				'bucket' => isset( $settings['s3_bucket'] ) ? $settings['s3_bucket'] : '',
			);
		}
		
		// Ensure provider is set in setup_data
		if ( ! isset( $setup_data['provider'] ) ) {
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			$setup_data['provider'] = isset( $settings['provider'] ) ? $settings['provider'] : 'aws';
		}
		
		// If credentials are in wp-config.php, pre-populate from settings (region/bucket are in database)
		if ( $has_wp_config ) {
			$setup_data['connection_method'] = 'wp-config';
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			if ( empty( $setup_data['region'] ) && ! empty( $settings['s3_region'] ) ) {
				$setup_data['region'] = $settings['s3_region'];
			}
			if ( empty( $setup_data['bucket'] ) && ! empty( $settings['s3_bucket'] ) ) {
				$setup_data['bucket'] = $settings['s3_bucket'];
			}
		}
		
		// If setup data is still missing region/bucket, try to get from current credentials
		if ( empty( $setup_data['region'] ) || empty( $setup_data['bucket'] ) ) {
			$credentials = $s3_service->get_credentials();
			if ( empty( $setup_data['region'] ) && ! empty( $credentials['region'] ) ) {
				$setup_data['region'] = $credentials['region'];
			}
			if ( empty( $setup_data['bucket'] ) && ! empty( $credentials['bucket'] ) ) {
				$setup_data['bucket'] = $credentials['bucket'];
			}
		}
		
		// Get current user for header
		$current_user = wp_get_current_user();
		
		include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/setup-wizard.php';
	}
	
	/**
	 * Filter admin notices on setup wizard page to hide notices from other plugins
	 */
	public function filter_setup_wizard_notices() {
		// Get all notices
		global $wp_filter;
		
		if ( ! isset( $wp_filter['admin_notices'] ) ) {
			return;
		}
		
		// Remove all admin notice callbacks except Clockwork ones
		$callbacks = $wp_filter['admin_notices']->callbacks;
		
		foreach ( $callbacks as $priority => $hooks ) {
			foreach ( $hooks as $hook_id => $hook ) {
				// Check if this is a Clockwork notice
				$is_clockwork = false;
				
				// Check if callback is an array with our class
				if ( is_array( $hook['function'] ) && is_object( $hook['function'][0] ) ) {
					$class_name = get_class( $hook['function'][0] );
					if ( strpos( $class_name, 'Clockwork' ) !== false || strpos( $class_name, 'cloudbound' ) !== false ) {
						$is_clockwork = true;
					}
				}
				
				// Check if callback function name contains cloudbound
				if ( is_string( $hook['function'] ) && ( stripos( $hook['function'], 'cloudbound' ) !== false ) ) {
					$is_clockwork = true;
				}
				
				// Remove non-Clockwork notices
				if ( ! $is_clockwork ) {
					remove_action( 'admin_notices', $hook['function'], $priority );
				}
			}
		}
	}
	
	/**
	 * AJAX: Test S3 connection
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Use get_credentials() to ensure wp-config.php constants are checked first
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		
		// Priority: 1. POST data (temporary test), 2. wp-config.php constants (from get_credentials), 3. Database settings
		// Only use POST data if it's actually provided (not empty string from dashboard)
		$access_key = '';
		$secret_key = '';
		$bucket = '';
		$region = '';
		
		if ( isset( $_POST['access_key'] ) && ! empty( trim( $_POST['access_key'] ) ) ) {
			$access_key = sanitize_text_field( wp_unslash( $_POST['access_key'] ) );
		} else {
			$access_key = ! empty( $credentials['access_key'] ) ? $credentials['access_key'] : '';
		}
		
		if ( isset( $_POST['secret_key'] ) && ! empty( trim( $_POST['secret_key'] ) ) ) {
			$secret_key = sanitize_text_field( wp_unslash( $_POST['secret_key'] ) );
		} else {
			$secret_key = ! empty( $credentials['secret_key'] ) ? $credentials['secret_key'] : '';
		}
		
		if ( isset( $_POST['region'] ) && ! empty( trim( $_POST['region'] ) ) ) {
			$region = sanitize_text_field( wp_unslash( $_POST['region'] ) );
		} else {
			$region = ! empty( $credentials['region'] ) ? $credentials['region'] : '';
		}
		
		if ( isset( $_POST['bucket'] ) && ! empty( trim( $_POST['bucket'] ) ) ) {
			$bucket = sanitize_text_field( wp_unslash( $_POST['bucket'] ) );
		} else {
			$bucket = ! empty( $credentials['bucket'] ) ? $credentials['bucket'] : '';
		}
		
		if ( empty( $access_key ) || empty( $secret_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Access Key and Secret Key are required.', 'clockwork-offloader' ) ) );
		}
		
		if ( empty( $bucket ) ) {
			wp_send_json_error( array( 'message' => __( 'Bucket name is required.', 'clockwork-offloader' ) ) );
		}
		
		if ( empty( $region ) ) {
			$region = 'us-east-1';
		}
		
		// Check if AWS SDK is available
		if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
			wp_send_json_error( array( 'message' => __( 'AWS SDK is not installed.', 'clockwork-offloader' ) ) );
		}
		
		try {
			$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : ( $credentials['provider'] ?? 'aws' );
			$endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : ( $credentials['endpoint'] ?? '' );
			
			$client_config = array(
				'version' => 'latest',
				'region' => $region,
				'credentials' => array(
					'key' => $access_key,
					'secret' => $secret_key,
				),
			);
			if ( ! empty( $endpoint ) ) {
				$client_config['endpoint'] = $endpoint;
				$client_config['use_path_style_endpoint'] = true;
			} elseif ( $provider === 'digitalocean' ) {
				$client_config['endpoint'] = 'https://' . $region . '.digitaloceanspaces.com';
				$client_config['use_path_style_endpoint'] = true;
			} elseif ( $provider === 'wasabi' ) {
				$client_config['endpoint'] = 'https://s3.' . $region . '.wasabisys.com';
				$client_config['use_path_style_endpoint'] = true;
			} elseif ( $provider === 'backblaze' ) {
				$client_config['endpoint'] = 'https://s3.' . $region . '.backblazeb2.com';
				$client_config['use_path_style_endpoint'] = true;
			}
			
			$s3_client = new Aws\S3\S3Client( $client_config );
			
			// Try to list objects (limited to 1) to test connection
			$s3_client->listObjects( array(
				'Bucket' => $bucket,
				'MaxKeys' => 1,
			) );
			
			// Cache successful connection
			set_transient( 'clockwork_offloader_s3_connection_test', true, HOUR_IN_SECONDS );
			
			wp_send_json_success( array( 'message' => __( 'Connection successful!', 'clockwork-offloader' ) ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
	
	/**
	 * AJAX: List S3 buckets
	 */
	public function ajax_list_buckets() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Priority: 1. POST data (temporary test), 2. wp-config.php constants, 3. Database settings
		$access_key = isset( $_POST['access_key'] ) ? sanitize_text_field( wp_unslash( $_POST['access_key'] ) ) : '';
		$secret_key = isset( $_POST['secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) : '';
		$region = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';
		
		// Use get_credentials() to ensure wp-config.php constants are checked first
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		
		// Allow POST data to override wp-config.php constants (for testing)
		if ( ! empty( $_POST['access_key'] ) ) {
			$access_key = sanitize_text_field( wp_unslash( $_POST['access_key'] ) );
		} else {
			$access_key = $credentials['access_key'];
		}
		
		if ( ! empty( $_POST['secret_key'] ) ) {
			$secret_key = sanitize_text_field( wp_unslash( $_POST['secret_key'] ) );
		} else {
			$secret_key = $credentials['secret_key'];
		}
		
		if ( ! empty( $_POST['region'] ) ) {
			$region = sanitize_text_field( wp_unslash( $_POST['region'] ) );
		} else {
			$region = $credentials['region'];
		}
		
		if ( empty( $access_key ) || empty( $secret_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Access Key and Secret Key are required.', 'clockwork-offloader' ) ) );
		}
		
		// Check if AWS SDK is available
		if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
			wp_send_json_error( array( 'message' => __( 'AWS SDK is not installed.', 'clockwork-offloader' ) ) );
		}
		
		try {
			$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : ( $credentials['provider'] ?? 'aws' );
			$endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : ( $credentials['endpoint'] ?? '' );
			
			$client_config = array(
				'version' => 'latest',
				'region' => $region,
				'credentials' => array(
					'key' => $access_key,
					'secret' => $secret_key,
				),
			);
			if ( ! empty( $endpoint ) ) {
				$client_config['endpoint'] = $endpoint;
				$client_config['use_path_style_endpoint'] = true;
			} elseif ( $provider === 'digitalocean' ) {
				$client_config['endpoint'] = 'https://' . $region . '.digitaloceanspaces.com';
				$client_config['use_path_style_endpoint'] = true;
			} elseif ( $provider === 'wasabi' ) {
				$client_config['endpoint'] = 'https://s3.' . $region . '.wasabisys.com';
				$client_config['use_path_style_endpoint'] = true;
			} elseif ( $provider === 'backblaze' ) {
				$client_config['endpoint'] = 'https://s3.' . $region . '.backblazeb2.com';
				$client_config['use_path_style_endpoint'] = true;
			}
			
			$s3_client = new Aws\S3\S3Client( $client_config );
			
			// List buckets
			$result = $s3_client->listBuckets();
			$buckets = array();
			
			if ( isset( $result['Buckets'] ) && is_array( $result['Buckets'] ) ) {
				foreach ( $result['Buckets'] as $bucket ) {
					if ( isset( $bucket['Name'] ) ) {
						$buckets[] = $bucket['Name'];
					}
				}
			}
			
			Clockwork_Offloader_S3_Service::remember_can_list_buckets( true );
			wp_send_json_success( array( 'buckets' => $buckets ) );
		} catch ( Exception $e ) {
			$error_message = $e->getMessage();
			
			// Bucket-scoped IAM keys (no s3:ListAllMyBuckets) are valid but cannot
			// browse. Tell the UI so it falls back to manual bucket entry.
			if ( $e instanceof \Aws\S3\Exception\S3Exception && $e->getAwsErrorCode() === 'AccessDenied' ) {
				Clockwork_Offloader_S3_Service::remember_can_list_buckets( false );
				wp_send_json_error( array(
					'message'       => __( 'This access key is scoped to a single bucket and cannot list buckets. Enter the bucket name manually instead.', 'clockwork-offloader' ),
					'bucket_scoped' => true,
				) );
			}
			
			// Provide more helpful error messages for common SSL/certificate issues
			if ( stripos( $error_message, 'certificate' ) !== false || 
				 stripos( $error_message, 'SSL' ) !== false ||
				 stripos( $error_message, 'TLS' ) !== false ||
				 stripos( $error_message, 'handshake' ) !== false ||
				 stripos( $error_message, 'ERR_CERT' ) !== false ) {
				$error_message = __( 'SSL certificate validation failed. This is usually a server configuration issue. Please check your server\'s SSL certificates and ensure they are valid and trusted. You can try entering the bucket name manually instead of browsing.', 'clockwork-offloader' ) . ' ' . $error_message;
			}
			
			wp_send_json_error( array( 'message' => $error_message ) );
		}
	}
	
	/**
	 * AJAX: Bulk offload
	 */
	public function ajax_bulk_offload() {
		// Check both nonces (for bulk page and attachment details)
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			$nonce_valid = wp_verify_nonce( $nonce, 'clockwork_offloader_nonce' ) || 
			               wp_verify_nonce( $nonce, 'clockwork_offloader_attachment_nonce' );
		}
		
		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'clockwork-offloader' ) ) );
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'clockwork-offloader' ) ) );
		}
		
		if ( class_exists( 'Clockwork_Offloader_Bulk_Offloader' ) ) {
			$bulk_offloader = new Clockwork_Offloader_Bulk_Offloader();
			$result = $bulk_offloader->offload_attachment( $attachment_id );
		} else {
			$result = Clockwork_Offloader::get_instance()->offload_attachment( $attachment_id );
		}
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		// Check if there were errors in the result
		if ( ! empty( $result['errors'] ) && $result['files_offloaded'] === 0 ) {
			$error_message = implode( ' ', $result['errors'] );
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		if ( 0 === $result['files_offloaded'] ) {
			wp_send_json_error( array( 'message' => __( 'No files were uploaded to Cloud.', 'clockwork-offloader' ) ) );
		}
		
		// Verify the offload was actually recorded
		$tracker = new Clockwork_Offloader_Tracker();
		$is_offloaded = $tracker->is_offloaded( $attachment_id );
		
		if ( ! $is_offloaded && $result['files_offloaded'] > 0 ) {
			// Upload succeeded but database record failed - this is a problem
			wp_send_json_error( array( 'message' => __( 'File uploaded but failed to record in database. Please check database connection.', 'clockwork-offloader' ) ) );
		}

		if ( ! empty( $result['errors'] ) ) {
			$error_message = implode( ' ', $result['errors'] );
			wp_send_json_success( array(
				'files_offloaded' => $result['files_offloaded'],
				'errors'          => $result['errors'],
				'message'         => sprintf(
					/* translators: 1: number of files, 2: error details */
					__( 'Uploaded %1$d file(s) to Cloud. Some sizes could not be uploaded: %2$s', 'clockwork-offloader' ),
					$result['files_offloaded'],
					$error_message
				),
			) );
		}
		
		wp_send_json_success( $result );
	}
	
	/**
	 * AJAX: Restore attachment
	 */
	public function ajax_restore() {
		// Check both nonces (for bulk page and attachment details)
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			$nonce_valid = wp_verify_nonce( $nonce, 'clockwork_offloader_nonce' ) || 
			               wp_verify_nonce( $nonce, 'clockwork_offloader_attachment_nonce' );
		}
		
		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'clockwork-offloader' ) ) );
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'clockwork-offloader' ) ) );
		}
		
		if ( class_exists( 'Clockwork_Offloader_Bulk_Offloader' ) ) {
			$bulk_offloader = new Clockwork_Offloader_Bulk_Offloader();
			$result = $bulk_offloader->restore_attachment( $attachment_id );
		} else {
			$result = Clockwork_Offloader::get_instance()->restore_attachment( $attachment_id );
		}
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		// Fire action for CloudFront invalidation (Pro feature)
		if ( ! empty( $result['files_restored'] ) && $result['files_restored'] > 0 ) {
			do_action( 'clockwork_offloader_attachment_restored', $attachment_id );
		}
		
		// Check if there were errors
		if ( ! empty( $result['errors'] ) && $result['files_restored'] === 0 ) {
			$error_message = implode( ' ', $result['errors'] );
			wp_send_json_error( array( 'message' => $error_message ) );
		} elseif ( ! empty( $result['errors'] ) ) {
			// Some files restored but some failed
			$error_message = implode( ' ', $result['errors'] );
			wp_send_json_success( array(
				'files_restored' => $result['files_restored'],
				'errors' => $result['errors'],
				'message' => sprintf( __( 'Restored %d file(s). Some files could not be restored: %s', 'clockwork-offloader' ), $result['files_restored'], $error_message ),
			) );
		}
		
		wp_send_json_success( $result );
	}
	
	/**
	 * AJAX: Delete from CDN
	 */
	public function ajax_delete_from_cdn() {
		// Check both nonces (for bulk page and attachment details)
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			$nonce_valid = wp_verify_nonce( $nonce, 'clockwork_offloader_nonce' ) || 
			               wp_verify_nonce( $nonce, 'clockwork_offloader_attachment_nonce' );
		}
		
		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'clockwork-offloader' ) ) );
		}
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'clockwork-offloader' ) ) );
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		$s3_service = new Clockwork_Offloader_S3_Service();
		
		// Get all offload records for this attachment
		$offloads = $tracker->get_attachment_offloads( $attachment_id );
		
		if ( empty( $offloads ) ) {
			wp_send_json_error( array( 'message' => __( 'Attachment is not offloaded to Cloud.', 'clockwork-offloader' ) ) );
		}
		
		$deleted = 0;
		$errors = array();
		
		foreach ( $offloads as $offload ) {
			// Delete from S3
			$delete_result = $s3_service->delete_file( $offload->s3_key );
			
			if ( is_wp_error( $delete_result ) ) {
				$error_message = $delete_result->get_error_message();
				// Check if it's a permissions error
				if ( strpos( $error_message, '403' ) !== false || strpos( $error_message, 'Forbidden' ) !== false || strpos( $error_message, 'AccessDenied' ) !== false ) {
					$errors[] = sprintf( __( 'Permission denied for %s. Please check your AWS credentials have delete permissions.', 'clockwork-offloader' ), $offload->s3_key );
				} else {
					$errors[] = sprintf( __( 'Failed to delete %s: %s', 'clockwork-offloader' ), $offload->s3_key, $error_message );
				}
			} else {
				$deleted++;
				// Delete database record for this file
				$tracker->delete_record( $attachment_id, $offload->size_name );
			}
		}
		
		if ( ! empty( $errors ) && $deleted === 0 ) {
			// All deletions failed
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		} elseif ( ! empty( $errors ) ) {
			// Some deletions failed
			wp_send_json_success( array(
				'deleted' => $deleted,
				'errors' => $errors,
				'message' => sprintf( __( 'Deleted %d file(s) from Cloud. Some files could not be deleted: %s', 'clockwork-offloader' ), $deleted, implode( ' ', $errors ) ),
			) );
		} else {
			// All deletions succeeded
			wp_send_json_success( array(
				'deleted' => $deleted,
				'message' => sprintf( __( 'Deleted %d file(s) from Cloud.', 'clockwork-offloader' ), $deleted ),
			) );
		}
	}
	
	/**
	 * AJAX: Offload from server (delete files from server)
	 */
	public function ajax_offload_from_server() {
		// Check both nonces (for settings page and attachment details)
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			$nonce_valid = wp_verify_nonce( $nonce, 'clockwork_offloader_nonce' ) || 
			               wp_verify_nonce( $nonce, 'clockwork_offloader_attachment_nonce' );
		}
		
		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'clockwork-offloader' ) ) );
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'clockwork-offloader' ) ) );
		}
		
		// Verify file is in Cloud
		$tracker = new Clockwork_Offloader_Tracker();
		if ( ! $tracker->is_offloaded( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'File must be in Cloud before offloading from server.', 'clockwork-offloader' ) ) );
		}
		
		$deleted = 0;
		$errors = array();
		
		// Get original file
		$file_path = get_attached_file( $attachment_id );
		if ( $file_path && file_exists( $file_path ) && $this->is_valid_upload_path( $file_path ) ) {
			if ( @unlink( $file_path ) ) {
				$deleted++;
			} else {
				$errors[] = sprintf( __( 'Failed to delete original file: %s', 'clockwork-offloader' ), $file_path );
			}
		}
		
		// Delete all image sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( $metadata && ! empty( $metadata['sizes'] ) ) {
			$upload_dir = wp_upload_dir();
			$file_dir = dirname( $file_path );
			
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				$size_file_path = $file_dir . '/' . $size_data['file'];
				
				if ( file_exists( $size_file_path ) && $this->is_valid_upload_path( $size_file_path ) ) {
					if ( @unlink( $size_file_path ) ) {
						$deleted++;
					} else {
						$errors[] = sprintf( __( 'Failed to delete %s: %s', 'clockwork-offloader' ), $size_name, $size_file_path );
					}
				}
			}
		}
		
		if ( ! empty( $errors ) && $deleted === 0 ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		} elseif ( ! empty( $errors ) ) {
			wp_send_json_success( array(
				'deleted' => $deleted,
				'errors' => $errors,
				'message' => sprintf( __( 'Deleted %d file(s) from server. Some files could not be deleted: %s', 'clockwork-offloader' ), $deleted, implode( ' ', $errors ) ),
			) );
		} else {
			wp_send_json_success( array(
				'deleted' => $deleted,
				'message' => sprintf( __( 'Deleted %d file(s) from server.', 'clockwork-offloader' ), $deleted ),
			) );
		}
	}
	
	/**
	 * AJAX: Delete from server (permanently delete files from server)
	 */
	public function ajax_delete_from_server() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'clockwork-offloader' ) ) );
		}
		
		$deleted = 0;
		$errors = array();
		
		// Get original file
		$file_path = get_attached_file( $attachment_id );
		if ( $file_path && file_exists( $file_path ) && $this->is_valid_upload_path( $file_path ) ) {
			if ( @unlink( $file_path ) ) {
				$deleted++;
			} else {
				$errors[] = sprintf( __( 'Failed to delete original file: %s', 'clockwork-offloader' ), $file_path );
			}
		}
		
		// Delete all image sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( $metadata && ! empty( $metadata['sizes'] ) ) {
			$upload_dir = wp_upload_dir();
			$file_dir = dirname( $file_path );
			
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				$size_file_path = $file_dir . '/' . $size_data['file'];
				
				if ( file_exists( $size_file_path ) && $this->is_valid_upload_path( $size_file_path ) ) {
					if ( @unlink( $size_file_path ) ) {
						$deleted++;
					} else {
						$errors[] = sprintf( __( 'Failed to delete %s: %s', 'clockwork-offloader' ), $size_name, $size_file_path );
					}
				}
			}
		}
		
		if ( ! empty( $errors ) && $deleted === 0 ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		} elseif ( ! empty( $errors ) ) {
			wp_send_json_success( array(
				'deleted' => $deleted,
				'errors' => $errors,
				'message' => sprintf( __( 'Deleted %d file(s) from server. Some files could not be deleted: %s', 'clockwork-offloader' ), $deleted, implode( ' ', $errors ) ),
			) );
		} else {
			wp_send_json_success( array(
				'deleted' => $deleted,
				'message' => sprintf( __( 'Deleted %d file(s) from server.', 'clockwork-offloader' ), $deleted ),
			) );
		}
	}
	
	/**
	 * AJAX: Update file status
	 */
	public function ajax_update_status() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'clockwork-offloader' ) ) );
		}
		
		$allowed_statuses = array( 'both', 'cdn-only', 'server-only', 'neither' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'clockwork-offloader' ) ) );
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		$s3_service = new Clockwork_Offloader_S3_Service();
		$bulk_offloader = class_exists( 'Clockwork_Offloader_Bulk_Offloader' ) ? new Clockwork_Offloader_Bulk_Offloader() : null;
		
		$current_cdn_status = $tracker->is_offloaded( $attachment_id );
		$file_path = get_attached_file( $attachment_id );
		$current_server_status = $file_path && file_exists( $file_path );
		
		$actions_taken = array();
		
		// Determine what needs to happen based on desired status
		switch ( $status ) {
			case 'both':
				// Need both on CDN and on server
				if ( ! $current_cdn_status ) {
					// Upload to Cloud
					$result = $bulk_offloader 
						? $bulk_offloader->offload_attachment( $attachment_id ) 
						: Clockwork_Offloader::get_instance()->offload_attachment( $attachment_id );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( array( 'message' => sprintf( __( 'Failed to upload to Cloud: %s', 'clockwork-offloader' ), $result->get_error_message() ) ) );
					}
					$actions_taken[] = __( 'Uploaded to Cloud', 'clockwork-offloader' );
				}
				if ( ! $current_server_status ) {
					// Download from Cloud to server
					$result = $bulk_offloader 
						? $bulk_offloader->restore_attachment( $attachment_id ) 
						: Clockwork_Offloader::get_instance()->restore_attachment( $attachment_id );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( array( 'message' => sprintf( __( 'Failed to download from Cloud: %s', 'clockwork-offloader' ), $result->get_error_message() ) ) );
					}
					$actions_taken[] = __( 'Downloaded to server', 'clockwork-offloader' );
				}
				break;
				
			case 'cdn-only':
				// Need in Cloud, not on server
				if ( ! $current_cdn_status ) {
					// Upload to Cloud
					$result = $bulk_offloader 
						? $bulk_offloader->offload_attachment( $attachment_id ) 
						: Clockwork_Offloader::get_instance()->offload_attachment( $attachment_id );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( array( 'message' => sprintf( __( 'Failed to upload to Cloud: %s', 'clockwork-offloader' ), $result->get_error_message() ) ) );
					}
					$actions_taken[] = __( 'Uploaded to Cloud', 'clockwork-offloader' );
				}
				if ( $current_server_status ) {
					// Delete from server safely
					$deleted = 0;
					$file_path = get_attached_file( $attachment_id );
					if ( $file_path && file_exists( $file_path ) ) {
						if ( Clockwork_Offloader::delete_local_file( $file_path ) ) {
							$deleted++;
						}
					}
					$metadata = wp_get_attachment_metadata( $attachment_id );
					if ( $metadata && ! empty( $metadata['sizes'] ) ) {
						$file_dir = dirname( $file_path );
						foreach ( $metadata['sizes'] as $size_data ) {
							$size_file = $file_dir . '/' . $size_data['file'];
							if ( file_exists( $size_file ) ) {
								if ( Clockwork_Offloader::delete_local_file( $size_file ) ) {
									$deleted++;
								}
							}
						}
					}
					if ( $deleted > 0 ) {
						$actions_taken[] = __( 'Deleted from server', 'clockwork-offloader' );
					}
				}
				break;
				
			case 'server-only':
				// Need on server, not in Cloud
				if ( $current_cdn_status ) {
					// Delete from Cloud - call the method directly
					$offloads = $tracker->get_attachment_offloads( $attachment_id );
					$deleted = 0;
					foreach ( $offloads as $offload ) {
						$delete_result = $s3_service->delete_file( $offload->s3_key );
						if ( ! is_wp_error( $delete_result ) ) {
							$deleted++;
							$tracker->delete_record( $attachment_id, $offload->size_name );
						}
					}
					if ( $deleted > 0 ) {
						$actions_taken[] = __( 'Deleted from Cloud', 'clockwork-offloader' );
					}
				}
				if ( ! $current_server_status ) {
					wp_send_json_error( array( 'message' => __( 'Cannot set to server-only: file does not exist on server and is not in Cloud to download.', 'clockwork-offloader' ) ) );
				}
				break;
				
			case 'neither':
				// Need neither in Cloud nor on server
				if ( $current_cdn_status ) {
					// Delete from Cloud
					$offloads = $tracker->get_attachment_offloads( $attachment_id );
					$deleted = 0;
					foreach ( $offloads as $offload ) {
						$delete_result = $s3_service->delete_file( $offload->s3_key );
						if ( ! is_wp_error( $delete_result ) ) {
							$deleted++;
							$tracker->delete_record( $attachment_id, $offload->size_name );
						}
					}
					if ( $deleted > 0 ) {
						$actions_taken[] = __( 'Deleted from Cloud', 'clockwork-offloader' );
					}
				}
				if ( $current_server_status ) {
					// Delete from server safely
					$deleted = 0;
					$file_path = get_attached_file( $attachment_id );
					if ( $file_path && file_exists( $file_path ) ) {
						if ( Clockwork_Offloader::delete_local_file( $file_path ) ) {
							$deleted++;
						}
					}
					$metadata = wp_get_attachment_metadata( $attachment_id );
					if ( $metadata && ! empty( $metadata['sizes'] ) ) {
						$file_dir = dirname( $file_path );
						foreach ( $metadata['sizes'] as $size_data ) {
							$size_file = $file_dir . '/' . $size_data['file'];
							if ( file_exists( $size_file ) ) {
								if ( Clockwork_Offloader::delete_local_file( $size_file ) ) {
									$deleted++;
								}
							}
						}
					}
					if ( $deleted > 0 ) {
						$actions_taken[] = __( 'Deleted from server', 'clockwork-offloader' );
					}
				}
				break;
		}
		
		if ( empty( $actions_taken ) ) {
			wp_send_json_success( array( 'message' => __( 'Status is already correct. No changes needed.', 'clockwork-offloader' ) ) );
		} else {
			wp_send_json_success( array( 
				'message' => sprintf( __( 'Status updated. Actions taken: %s', 'clockwork-offloader' ), implode( ', ', $actions_taken ) ),
				'actions' => $actions_taken,
			) );
		}
	}
	
	/**
	 * AJAX: Toggle status (server or CDN)
	 */
	public function ajax_toggle_status() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$status_type = isset( $_POST['status_type'] ) ? sanitize_text_field( wp_unslash( $_POST['status_type'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'clockwork-offloader' ) ) );
		}
		
		if ( ! in_array( $status_type, array( 'server', 'cdn' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status type.', 'clockwork-offloader' ) ) );
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Bulk operations are a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		$bulk_offloader = new Clockwork_Offloader_Bulk_Offloader();
		
		if ( $status_type === 'server' ) {
			// Toggling server status
			$file_path = get_attached_file( $attachment_id );
			$file_exists = $file_path && file_exists( $file_path );
			
			if ( $enabled ) {
				// Enable server - download from Cloud if available
				if ( ! $tracker->is_offloaded( $attachment_id ) ) {
					wp_send_json_error( array( 'message' => __( 'Cannot add to server: file is not in Cloud to download.', 'clockwork-offloader' ) ) );
				}
				$result = $bulk_offloader->restore_attachment( $attachment_id );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => sprintf( __( 'Failed to download from Cloud: %s', 'clockwork-offloader' ), $result->get_error_message() ) ) );
				}
				wp_send_json_success( array( 'message' => __( 'File downloaded to server.', 'clockwork-offloader' ) ) );
			} else {
				// Disable server - delete from server
				if ( ! $file_exists ) {
					wp_send_json_success( array( 'message' => __( 'File already not on server.', 'clockwork-offloader' ) ) );
				}
				$deleted = 0;
				if ( $file_path && file_exists( $file_path ) ) {
					if ( @unlink( $file_path ) ) {
						$deleted++;
					}
				}
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( $metadata && ! empty( $metadata['sizes'] ) ) {
					$file_dir = dirname( $file_path );
					foreach ( $metadata['sizes'] as $size_data ) {
						$size_file = $file_dir . '/' . $size_data['file'];
						if ( file_exists( $size_file ) ) {
							@unlink( $size_file );
							$deleted++;
						}
					}
				}
				wp_send_json_success( array( 'message' => sprintf( __( 'Deleted %d file(s) from server.', 'clockwork-offloader' ), $deleted ) ) );
			}
		} else {
			// Toggling Cloud status
			$is_offloaded = $tracker->is_offloaded( $attachment_id );
			
			if ( $enabled ) {
				// Enable Cloud - upload to Cloud
				if ( $is_offloaded ) {
					wp_send_json_success( array( 'message' => __( 'File already in Cloud.', 'clockwork-offloader' ) ) );
				}
				$result = $bulk_offloader->offload_attachment( $attachment_id );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => sprintf( __( 'Failed to upload to Cloud: %s', 'clockwork-offloader' ), $result->get_error_message() ) ) );
				}
				wp_send_json_success( array( 'message' => __( 'File uploaded to Cloud.', 'clockwork-offloader' ) ) );
			} else {
				// Disable Cloud - delete from Cloud
				if ( ! $is_offloaded ) {
					wp_send_json_success( array( 'message' => __( 'File already not in Cloud.', 'clockwork-offloader' ) ) );
				}
				$s3_service = new Clockwork_Offloader_S3_Service();
				$offloads = $tracker->get_attachment_offloads( $attachment_id );
				$deleted = 0;
				foreach ( $offloads as $offload ) {
					$delete_result = $s3_service->delete_file( $offload->s3_key );
					if ( ! is_wp_error( $delete_result ) ) {
						$deleted++;
						$tracker->delete_record( $attachment_id, $offload->size_name );
					}
				}
				wp_send_json_success( array( 'message' => sprintf( __( 'Deleted %d file(s) from Cloud.', 'clockwork-offloader' ), $deleted ) ) );
			}
		}
	}
	
	/**
	 * AJAX: Get statistics
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		$stats = $tracker->get_statistics();
		
		wp_send_json_success( $stats );
	}
	
	/**
	 * AJAX: Get bulk statistics for the Bulk Tools page
	 * Returns all statistics needed for dynamic updates
	 */
	public function ajax_get_bulk_stats() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Bulk statistics are a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		global $wpdb;
		$queue = new Clockwork_Offloader_Queue();
		$queue_stats = $queue->get_statistics();
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Count total attachments efficiently
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);
		
		// Count offloaded attachments efficiently
		$tracker_table = $wpdb->prefix . 'clockwork_offloads';
		$offloaded_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT o.attachment_id) FROM " . esc_sql( $tracker_table ) . " o
			INNER JOIN {$wpdb->posts} p ON o.attachment_id = p.ID
			WHERE o.status = 'offloaded' AND p.post_type = 'attachment' AND p.post_status = 'inherit'"
		);
		
		// Count queued attachments (pending/processing)
		$queue_table = $wpdb->prefix . 'clockwork_offload_queue';
		$queued_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT q.attachment_id) FROM " . esc_sql( $queue_table ) . " q
			INNER JOIN {$wpdb->posts} p ON q.attachment_id = p.ID
			WHERE q.status IN ('pending', 'processing') AND p.post_type = 'attachment' AND p.post_status = 'inherit'"
		);
		
		// Calculate not offloaded count (total - offloaded)
		$total_not_offloaded = max( 0, $total_attachments - $offloaded_count );
		
		// Calculate actual offload percentage: offloaded / total * 100
		$offload_percentage = $total_attachments > 0 ? round( ( $offloaded_count / $total_attachments ) * 100 ) : 0;
		$offload_percentage = min( $offload_percentage, 100 );
		
		// Check if there are items still processing in the queue
		$has_active_queue = ( $queue_stats['pending'] > 0 || $queue_stats['processing'] > 0 );
		
		// URL rewrite statistics (same data source for consistency)
		$total_attachments_for_rewrite = $total_attachments;
		$offloaded_attachments_for_rewrite = $offloaded_count;
		$rewrite_percentage = $total_attachments_for_rewrite > 0 ? round( ( $offloaded_attachments_for_rewrite / $total_attachments_for_rewrite ) * 100 ) : 0;
		
		// Get settings for rewrite URL status
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$rewrite_urls_enabled = ! empty( $settings['rewrite_urls'] );
		
		wp_send_json_success( array(
			'total_attachments' => $total_attachments,
			'offloaded_count' => $offloaded_count,
			'queued_count' => $queued_count,
			'total_not_offloaded' => $total_not_offloaded,
			'offload_percentage' => $offload_percentage,
			'has_active_queue' => $has_active_queue,
			'rewrite_percentage' => $rewrite_percentage,
			'offloaded_attachments_for_rewrite' => $offloaded_attachments_for_rewrite,
			'total_attachments_for_rewrite' => $total_attachments_for_rewrite,
			'rewrite_urls_enabled' => $rewrite_urls_enabled,
			'queue_stats' => $queue_stats,
		) );
	}
	
	// Media library status icons removed for performance - AJAX methods removed
	
	/**
	 * AJAX: Add attachments to queue
	 */
	public function ajax_add_to_queue() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Queue system is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$attachment_ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['attachment_ids'] ) ) : array();
		
		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No attachment IDs provided.', 'clockwork-offloader' ) ) );
		}
		
		$queue = new Clockwork_Offloader_Queue();
		$added = $queue->add_to_queue( $attachment_ids );
		
		wp_send_json_success( array(
			'added' => $added,
			'total' => count( $attachment_ids ),
			'message' => sprintf( __( 'Added %d item(s) to queue.', 'clockwork-offloader' ), $added ),
		) );
	}
	
	/**
	 * AJAX: Add all non-offloaded attachments to queue
	 */
	public function ajax_add_all_to_queue() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Increase time limit for large batch processing (prevent timeouts with 600k+ items)
		@set_time_limit( 300 ); // 5 minutes
		
		// Increase memory limit if possible
		if ( function_exists( 'ini_set' ) ) {
			$current_memory = ini_get( 'memory_limit' );
			$current_bytes = $this->convert_memory_to_bytes( $current_memory );
			$min_bytes = 256 * 1024 * 1024; // 256MB minimum
			if ( $current_bytes < $min_bytes ) {
				@ini_set( 'memory_limit', '256M' );
			}
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		$queue = new Clockwork_Offloader_Queue();
		
		// Get offset from request (for chunked processing)
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$chunk_size = 500; // Process 500 attachments at a time to avoid timeouts
		
		// Get attachments in chunks to avoid memory/timeout issues
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => $chunk_size,
			'offset' => $offset,
			'fields' => 'ids', // Only get IDs for performance
			'orderby' => 'ID',
			'order' => 'ASC',
		);
		
		$attachments = get_posts( $args );
		
		// If no more attachments, we're done
		if ( empty( $attachments ) ) {
			// Final check - are there any remaining?
			if ( $offset === 0 ) {
				wp_send_json_success( array(
					'added' => 0,
					'total' => 0,
					'message' => __( 'No attachments found to offload.', 'clockwork-offloader' ),
					'completed' => true,
				) );
			} else {
				wp_send_json_success( array(
					'added' => 0,
					'total' => 0,
					'message' => __( 'All items have been added to queue.', 'clockwork-offloader' ),
					'completed' => true,
				) );
			}
		}
		
		// Filter out already offloaded attachments using a more efficient method
		// Use efficient SQL queries instead of loading all data
		global $wpdb;
		$tracker_table = $wpdb->prefix . 'clockwork_offloads';
		
		// Sanitize attachment IDs
		$attachment_ids = array_map( 'absint', $attachments );
		$attachment_ids = array_filter( $attachment_ids );
		
		if ( empty( $attachment_ids ) ) {
			wp_send_json_success( array(
				'added' => 0,
				'processed' => 0,
				'offset' => $offset + $chunk_size,
				'has_more' => count( $attachments ) === $chunk_size,
				'message' => __( 'No valid attachments found in this batch.', 'clockwork-offloader' ),
				'completed' => count( $attachments ) < $chunk_size,
			) );
		}
		
		// Build placeholders for IN clause (optimized for large batches)
		// Split into smaller chunks if too many IDs to avoid query size limits
		$max_ids_per_query = 1000; // MySQL IN clause limit
		$offloaded_ids = array();
		
		if ( count( $attachment_ids ) <= $max_ids_per_query ) {
			// Single query for small batches
			$placeholders = implode( ',', array_fill( 0, count( $attachment_ids ), '%d' ) );
			$offloaded_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT attachment_id FROM " . esc_sql( $tracker_table ) . " WHERE attachment_id IN ($placeholders) AND status = 'offloaded' AND size_name = ''",
				$attachment_ids
			) );
		} else {
			// Multiple queries for large batches (prevents query size limits)
			$id_chunks = array_chunk( $attachment_ids, $max_ids_per_query );
			foreach ( $id_chunks as $id_chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $id_chunk ), '%d' ) );
				$chunk_offloaded = $wpdb->get_col( $wpdb->prepare(
					"SELECT DISTINCT attachment_id FROM " . esc_sql( $tracker_table ) . " WHERE attachment_id IN ($placeholders) AND status = 'offloaded' AND size_name = ''",
					$id_chunk
				) );
				$offloaded_ids = array_merge( $offloaded_ids, $chunk_offloaded );
			}
		}
		
		$offloaded_ids = array_map( 'absint', $offloaded_ids );
		
		// Filter out already offloaded (use array_diff for efficiency)
		$not_offloaded = array_diff( $attachments, $offloaded_ids );
		
		// Add to queue in batches to avoid memory issues and database query size limits
		$queue_batch_size = 100; // Optimal batch size for queue insertion
		$total_added = 0;
		if ( ! empty( $not_offloaded ) ) {
			$batches = array_chunk( $not_offloaded, $queue_batch_size );
			
			foreach ( $batches as $batch ) {
				$added = $queue->add_to_queue( $batch );
				$total_added += $added;
				
			}
		}
		
		// Check if there are more attachments to process
		$has_more = count( $attachments ) === $chunk_size;
		
		wp_send_json_success( array(
			'added' => $total_added,
			'processed' => count( $attachments ),
			'offset' => $offset + $chunk_size,
			'has_more' => $has_more,
			'message' => sprintf( 
				__( 'Processed %d attachment(s), added %d to queue. %s', 'clockwork-offloader' ),
				count( $attachments ),
				$total_added,
				$has_more ? __( 'Continuing...', 'clockwork-offloader' ) : __( 'Processing will begin automatically via cron.', 'clockwork-offloader' )
			),
			'completed' => ! $has_more,
		) );
	}
	
	/**
	 * AJAX: Download test files
	 */
	public function ajax_download_test_files() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Check development mode
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		if ( empty( $settings['development_mode'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Development Mode is not enabled.', 'clockwork-offloader' ) ) );
		}
		
		$file_types = isset( $_POST['file_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['file_types'] ) ) : array();
		
		if ( empty( $file_types ) ) {
			wp_send_json_error( array( 'message' => __( 'No file types selected.', 'clockwork-offloader' ) ) );
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$results = array();
		$success_count = 0;
		$error_count = 0;
		
		foreach ( $file_types as $file_type ) {
			$result = $dev_helper->download_test_file( $file_type );
			if ( is_wp_error( $result ) ) {
				$results[] = array(
					'type' => $file_type,
					'success' => false,
					'error' => $result->get_error_message(),
				);
				$error_count++;
			} else {
				$results[] = array(
					'type' => $file_type,
					'success' => true,
					'file' => $result,
				);
				$success_count++;
			}
		}
		
		wp_send_json_success( array(
			'results' => $results,
			'success_count' => $success_count,
			'error_count' => $error_count,
			'message' => sprintf( __( 'Downloaded %d file(s) successfully. %d failed.', 'clockwork-offloader' ), $success_count, $error_count ),
		) );
	}
	
	/**
	 * AJAX: Add test files to media library
	 */
	public function ajax_add_test_files_to_media() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Check development mode
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		if ( empty( $settings['development_mode'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Development Mode is not enabled.', 'clockwork-offloader' ) ) );
		}
		
		$storage_option = isset( $_POST['storage_option'] ) ? sanitize_text_field( wp_unslash( $_POST['storage_option'] ) ) : 'both';
		$allowed_options = array( 'server', 's3', 'both' );
		if ( ! in_array( $storage_option, $allowed_options, true ) ) {
			$storage_option = 'both';
		}
		
		// Get selected file IDs
		$selected_file_ids = isset( $_POST['selected_file_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_file_ids'] ) ) : array();
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$result = $dev_helper->add_downloaded_files_to_media( $storage_option, $selected_file_ids );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		wp_send_json_success( $result );
	}
	
	/**
	 * AJAX: Get downloaded files
	 */
	public function ajax_get_downloaded_files() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$files = $dev_helper->get_downloaded_files();
		
		wp_send_json_success( array( 'files' => $files ) );
	}
	
	/**
	 * AJAX: Delete all development files
	 */
	public function ajax_delete_dev_files() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$result = $dev_helper->delete_all_dev_files( 'both' );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		wp_send_json_success( $result );
	}
	
	/**
	 * AJAX: Clear test data with options
	 */
	public function ajax_clear_test_data() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$delete_option = isset( $_POST['delete_option'] ) ? sanitize_text_field( wp_unslash( $_POST['delete_option'] ) ) : 'both';
		
		// Validate option
		if ( ! in_array( $delete_option, array( 'server', 's3', 'both' ), true ) ) {
			$delete_option = 'both';
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$result = $dev_helper->delete_all_dev_files( $delete_option );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		wp_send_json_success( $result );
	}
	
	/**
	 * AJAX: Uninstall all plugin data
	 */
	public function ajax_uninstall_data() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		global $wpdb;
		
		$results = array(
			'options_deleted' => 0,
			'transients_deleted' => 0,
			'tables_dropped' => 0,
			'postmeta_deleted' => 0,
		);
		
		// Delete all options
		$options_to_delete = array(
			'clockwork_offloader_settings',
			'clockwork_offloader_last_queue_process',
			'clockwork_dev_attachment_ids',
		);
		
		foreach ( $options_to_delete as $option ) {
			if ( delete_option( $option ) ) {
				$results['options_deleted']++;
			}
		}
		
		// Delete all transients starting with clockwork_offloader_
		$transient_prefix = '_transient_clockwork_offloader_';
		$transient_timeout_prefix = '_transient_timeout_clockwork_offloader_';
		
		$transients = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( $transient_prefix ) . '%',
			$wpdb->esc_like( $transient_timeout_prefix ) . '%'
		) );
		
		$deleted_transient_names = array();
		foreach ( $transients as $transient_option ) {
			// Extract transient name from option name
			if ( strpos( $transient_option, '_transient_' ) === 0 ) {
				$transient_name = str_replace( '_transient_', '', $transient_option );
				if ( ! in_array( $transient_name, $deleted_transient_names, true ) ) {
					delete_transient( $transient_name );
					$deleted_transient_names[] = $transient_name;
					$results['transients_deleted']++;
				}
			}
		}
		
		// Drop database tables
		$table_offloads = $wpdb->prefix . 'clockwork_offloads';
		$table_queue = $wpdb->prefix . 'clockwork_offload_queue';
		
		// Escape table names for security
		$table_offloads = esc_sql( $table_offloads );
		$table_queue = esc_sql( $table_queue );
		
		$wpdb->query( "DROP TABLE IF EXISTS `{$table_offloads}`" );
		$results['tables_dropped']++;
		
		$wpdb->query( "DROP TABLE IF EXISTS `{$table_queue}`" );
		$results['tables_dropped']++;
		
		// Delete all postmeta entries starting with _cloudbound_
		$postmeta_deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_clockwork_' ) . '%'
		) );
		$results['postmeta_deleted'] = $postmeta_deleted;
		
		wp_send_json_success( array(
			'message' => __( 'All plugin data has been uninstalled successfully.', 'clockwork-offloader' ),
			'results' => $results,
		) );
	}
	
	/**
	 * AJAX: Create database table
	 */
	public function ajax_create_table() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Migration tools are a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Ensure Clockwork Offloader classes are available
		if ( ! class_exists( 'Clockwork_Offloader_Tracker' ) ) {
			wp_send_json_error( array( 'message' => __( 'Clockwork Offloader is not available.', 'clockwork-offloader' ) ) );
		}
		
		global $wpdb;
		$table_name = $wpdb->prefix . 'clockwork_offloads';
		
		// Check if table already exists
		// Note: SHOW TABLES LIKE doesn't work with $wpdb->prepare(), so we use esc_sql() instead
		$table_name_escaped = esc_sql( $table_name );
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_escaped}'" ) === $table_name;
		
		if ( $table_exists ) {
			wp_send_json_success( array( 'message' => __( 'Table already exists.', 'clockwork-offloader' ) ) );
		}
		
		// Create the table
		Clockwork_Offloader_Tracker::create_table();
		
		// Also create the queue table
		if ( class_exists( 'Clockwork_Offloader_Queue' ) ) {
			Clockwork_Offloader_Queue::create_table();
		}
		
		// Verify it was created
		$table_exists_after = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_escaped}'" ) === $table_name;
		
		if ( $table_exists_after ) {
			wp_send_json_success( array( 'message' => __( 'Database table created successfully!', 'clockwork-offloader' ) ) );
		} else {
			$error = $wpdb->last_error ? $wpdb->last_error : __( 'Unknown database error', 'clockwork-offloader' );
			wp_send_json_error( array( 'message' => sprintf( __( 'Failed to create table: %s', 'clockwork-offloader' ), $error ) ) );
		}
	}
	
	/**
	 * AJAX: Reset migrator notice dismissal
	 */
	public function ajax_reset_migrator_notice() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Migration tools are a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$user_id = get_current_user_id();
		delete_user_meta( $user_id, 'clockwork_offloader_dismiss_migrator_notice' );
		
		wp_send_json_success( array( 'message' => __( 'Migrator notice reset. The notice will appear again on the dashboard.', 'clockwork-offloader' ) ) );
	}
	
	/**
	 * AJAX: Migration diagnostic
	 */
	public function ajax_migration_diagnostic() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Migration tools are a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		global $wpdb;
		$tracker = new Clockwork_Offloader_Tracker();
		$table_name = $wpdb->prefix . 'clockwork_offloads';
		
		$diagnostic = array(
			'table_exists' => false,
			'total_records' => 0,
			'offloaded_records' => 0,
			'attachments_with_records' => 0,
			'sample_records' => array(),
			'test_results' => array(),
			'settings' => array(),
			'url_rewriter_status' => false,
		);
		
		// Check if table exists
		// Note: SHOW TABLES LIKE doesn't work with $wpdb->prepare(), so we use esc_sql() instead
		$table_name_escaped = esc_sql( $table_name );
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_escaped}'" ) === $table_name;
		$diagnostic['table_exists'] = $table_exists;
		
		if ( $table_exists ) {
			// Table name is safe (constructed from $wpdb->prefix), but escape for WordPress standards
			$table_name_escaped = esc_sql( $table_name );
			
			// Get total records
			$diagnostic['total_records'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name_escaped}`" );
			
			// Get offloaded records
			$diagnostic['offloaded_records'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name_escaped}` WHERE status = %s", 'offloaded' )
			);
			
			// Get unique attachments
			$diagnostic['attachments_with_records'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(DISTINCT attachment_id) FROM `{$table_name_escaped}` WHERE status = %s", 'offloaded' )
			);
			
			// Get sample records (first 5)
			$sample_records = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM `{$table_name_escaped}` WHERE status = %s ORDER BY attachment_id LIMIT 5", 'offloaded' )
			);
			
			foreach ( $sample_records as $record ) {
				$diagnostic['sample_records'][] = array(
					'attachment_id' => $record->attachment_id,
					'size_name' => $record->size_name ? $record->size_name : '(original)',
					'status' => $record->status,
					'bucket' => $record->bucket,
					's3_key' => $record->s3_key,
					'is_offloaded_check' => $tracker->is_offloaded( $record->attachment_id, $record->size_name ),
					's3_url' => $tracker->get_s3_url( $record->attachment_id, $record->size_name ),
				);
				
				// Test URL rewriting for original files
				if ( empty( $record->size_name ) ) {
					$original_url = wp_get_attachment_url( $record->attachment_id );
					$diagnostic['test_results'][] = array(
						'attachment_id' => $record->attachment_id,
						'original_url' => $original_url,
						's3_url' => $tracker->get_s3_url( $record->attachment_id ),
						'is_offloaded' => $tracker->is_offloaded( $record->attachment_id ),
						'url_rewritten' => ( strpos( $original_url, 's3' ) !== false || strpos( $original_url, 'amazonaws' ) !== false || strpos( $original_url, 'digitaloceanspaces' ) !== false ),
					);
				}
			}
		}
		
		// Check settings
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$diagnostic['settings'] = array(
			'rewrite_urls' => ! empty( $settings['rewrite_urls'] ),
			'provider' => isset( $settings['provider'] ) ? $settings['provider'] : 'not set',
			'bucket' => isset( $settings['s3_bucket'] ) ? $settings['s3_bucket'] : 'not set',
			'region' => isset( $settings['s3_region'] ) ? $settings['s3_region'] : 'not set',
			'cdn_domain' => isset( $settings['cdn_domain'] ) && ! empty( $settings['cdn_domain'] ) ? $settings['cdn_domain'] : 'not set',
		);
		
		// Check URL rewriter
		$diagnostic['url_rewriter_status'] = class_exists( 'Clockwork_Offloader_URL_Rewriter' );
		
		wp_send_json_success( $diagnostic );
	}
	
	/**
	 * AJAX: Get development files count
	 */
	public function ajax_get_dev_files_count() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$count = $dev_helper->get_dev_files_count();
		
		wp_send_json_success( array( 'count' => $count ) );
	}
	
	/**
	 * AJAX: Delete downloaded files
	 */
	public function ajax_delete_downloaded_files() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$result = $dev_helper->delete_downloaded_files();
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		wp_send_json_success( $result );
	}
	
	/**
	 * AJAX: Delete single downloaded file
	 */
	public function ajax_delete_single_downloaded_file() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Development mode is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$file_id = isset( $_POST['file_id'] ) ? sanitize_text_field( wp_unslash( $_POST['file_id'] ) ) : '';
		
		if ( empty( $file_id ) ) {
			wp_send_json_error( array( 'message' => __( 'File ID is required.', 'clockwork-offloader' ) ) );
		}
		
		require_once CLOCKWORK_OFFLOADER_PRO_PLUGIN_DIR . 'includes/class-development-helper.php';
		$dev_helper = new Clockwork_Offloader_Development_Helper();
		
		$result = $dev_helper->delete_single_downloaded_file( $file_id );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		
		wp_send_json_success( $result );
	}
	
	/**
	 * AJAX: Get queue statistics
	 */
	public function ajax_get_queue_stats() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Queue system is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$queue = new Clockwork_Offloader_Queue();
		$stats = $queue->get_statistics();
		
		// Calculate progress percentage
		$progress = 0;
		if ( $stats['total'] > 0 ) {
			$progress = round( ( $stats['completed'] / $stats['total'] ) * 100, 2 );
		}
		
		$stats['progress'] = $progress;
		$stats['is_processing'] = wp_next_scheduled( 'clockwork_offloader_process_queue' ) !== false;
		
		wp_send_json_success( $stats );
	}
	
	/**
	 * AJAX: Cancel queue
	 */
	public function ajax_cancel_queue() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Queue system is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$queue = new Clockwork_Offloader_Queue();
		$cancelled = $queue->cancel_pending();
		
		// Unschedule processing
		$timestamp = wp_next_scheduled( 'clockwork_offloader_process_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'clockwork_offloader_process_queue' );
		}
		
		wp_send_json_success( array(
			'cancelled' => $cancelled,
			'message' => sprintf( __( 'Cancelled %d pending item(s).', 'clockwork-offloader' ), $cancelled ),
		) );
	}
	
	/**
	 * AJAX: Retry failed items
	 */
	public function ajax_retry_failed() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Queue system is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$queue = new Clockwork_Offloader_Queue();
		$retried = $queue->retry_failed();
		
		// Schedule processing if not already scheduled
		if ( $retried > 0 && ! wp_next_scheduled( 'clockwork_offloader_process_queue' ) ) {
			wp_schedule_event( time(), 'clockwork_offloader_queue_interval', 'clockwork_offloader_process_queue' );
		}
		
		wp_send_json_success( array(
			'retried' => $retried,
			'message' => sprintf( __( 'Reset %d failed item(s) for retry.', 'clockwork-offloader' ), $retried ),
		) );
	}
	
	/**
	 * AJAX: Process queue now (manual trigger)
	 */
	public function ajax_process_queue_now() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Queue system is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$queue = new Clockwork_Offloader_Queue();
		
		// Get batch size from settings
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$batch_size = ! empty( $settings['queue_batch_size'] ) ? absint( $settings['queue_batch_size'] ) : 10;
		
		$stats = $queue->process_batch( $batch_size );
		
		// Schedule next run if there are more items
		$queue_stats = $queue->get_statistics();
		if ( ( $queue_stats['pending'] > 0 || $queue_stats['processing'] > 0 ) && ! wp_next_scheduled( 'clockwork_offloader_process_queue' ) ) {
			wp_schedule_event( time(), 'clockwork_offloader_queue_interval', 'clockwork_offloader_process_queue' );
		}
		
		wp_send_json_success( $stats );
	}
	
	/**
	 * AJAX: Add all files to download queue (files on S3 but not on server)
	 */
	public function ajax_add_all_to_download_queue() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Increase time limit for large batch processing (prevent timeouts with 600k+ items)
		@set_time_limit( 300 ); // 5 minutes
		
		// Increase memory limit if possible
		if ( function_exists( 'ini_set' ) ) {
			$current_memory = ini_get( 'memory_limit' );
			$current_bytes = $this->convert_memory_to_bytes( $current_memory );
			$min_bytes = 256 * 1024 * 1024; // 256MB minimum
			if ( $current_bytes < $min_bytes ) {
				@ini_set( 'memory_limit', '256M' );
			}
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Get offset from request (for chunked processing)
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$chunk_size = 500; // Process 500 attachments at a time
		
		// Get attachments in chunks
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => $chunk_size,
			'offset' => $offset,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
		);
		
		$attachments = get_posts( $args );
		
		if ( empty( $attachments ) ) {
			wp_send_json_success( array(
				'added' => 0,
				'processed' => 0,
				'message' => __( 'All items have been processed.', 'clockwork-offloader' ),
				'completed' => true,
			) );
		}
		
		// Filter to only attachments that are on S3 but NOT on server
		$needs_download = array();
		foreach ( $attachments as $attachment_id ) {
			// Must be on S3 (offloaded)
			$is_offloaded = $tracker->is_offloaded( $attachment_id );
			
			if ( ! $is_offloaded ) {
				continue; // Skip if not on S3
			}
			
			// Must NOT be on server
			$file_path = get_attached_file( $attachment_id );
			$file_exists = $file_path && file_exists( $file_path );
			
			// If file exists on server, skip it (we only want files that are ONLY on S3)
			if ( $file_exists ) {
				continue;
			}
			
			// This file is on S3 but not on server - it needs to be downloaded
			$needs_download[] = $attachment_id;
		}
		
		// Check if there are more attachments to process
		$has_more = count( $attachments ) === $chunk_size;
		
		wp_send_json_success( array(
			'needs_download' => count( $needs_download ),
			'processed' => count( $attachments ),
			'attachment_ids' => $needs_download,
			'offset' => $offset + $chunk_size,
			'has_more' => $has_more,
			'message' => sprintf( 
				__( 'Processed %d attachment(s), found %d that need downloading. %s', 'clockwork-offloader' ),
				count( $attachments ),
				count( $needs_download ),
				$has_more ? __( 'Continuing...', 'clockwork-offloader' ) : __( 'Ready to download.', 'clockwork-offloader' )
			),
			'completed' => ! $has_more,
		) );
	}
	
	/**
	 * AJAX: Process bulk download (download files from S3)
	 */
	public function ajax_process_download_queue_now() {
		// Pro feature check
		if ( ! Clockwork_Offloader_Lite_Restrictions::is_pro_active() ) {
			wp_send_json_error( array( 'message' => __( 'Bulk download is a Pro feature. Please upgrade to Clockwork Offloader Pro.', 'clockwork-offloader' ) ) );
		}
		
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Increase time limit for large batch processing (prevent timeouts with 600k+ items)
		@set_time_limit( 300 ); // 5 minutes
		
		// Increase memory limit if possible
		if ( function_exists( 'ini_set' ) ) {
			$current_memory = ini_get( 'memory_limit' );
			$current_bytes = $this->convert_memory_to_bytes( $current_memory );
			$min_bytes = 256 * 1024 * 1024; // 256MB minimum
			if ( $current_bytes < $min_bytes ) {
				@ini_set( 'memory_limit', '256M' );
			}
		}
		
		$attachment_ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['attachment_ids'] ) ) : array();
		
		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No attachments to download.', 'clockwork-offloader' ) ) );
		}
		
		$bulk_offloader = new Clockwork_Offloader_Bulk_Offloader();
		$batch_size = 10; // Process 10 at a time to avoid timeouts
		$batch = array_slice( $attachment_ids, 0, $batch_size );
		
		$stats = array(
			'succeeded' => 0,
			'failed' => 0,
			'errors' => array(),
		);
		
		foreach ( $batch as $attachment_id ) {
			$result = $bulk_offloader->restore_attachment( $attachment_id );
			
			if ( is_wp_error( $result ) ) {
				$stats['failed']++;
				$stats['errors'][] = sprintf( __( 'Attachment %d: %s', 'clockwork-offloader' ), $attachment_id, $result->get_error_message() );
			} else {
				$stats['succeeded']++;
			}
		}
		
		// Return remaining attachment IDs
		$remaining = array_slice( $attachment_ids, $batch_size );
		
		wp_send_json_success( array(
			'succeeded' => $stats['succeeded'],
			'failed' => $stats['failed'],
			'errors' => $stats['errors'],
			'remaining' => $remaining,
			'has_more' => ! empty( $remaining ),
		) );
	}
	
	/**
	 * AJAX: Get download queue statistics
	 */
	public function ajax_get_download_queue_stats() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Get all attachments
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		
		$all_attachments = get_posts( $args );
		$needs_download = 0;
		
		foreach ( $all_attachments as $attachment_id ) {
			// Must be on S3 (offloaded)
			$is_offloaded = $tracker->is_offloaded( $attachment_id );
			
			if ( ! $is_offloaded ) {
				continue; // Skip if not on S3
			}
			
			// Must NOT be on server
			$file_path = get_attached_file( $attachment_id );
			$file_exists = $file_path && file_exists( $file_path );
			
			// If file exists on server, skip it (we only want files that are ONLY on S3)
			if ( $file_exists ) {
				continue;
			}
			
			// This file is on S3 but not on server
			$needs_download++;
		}
		
		wp_send_json_success( array(
			'needs_download' => $needs_download,
		) );
	}
	
	/**
	 * AJAX: Remove all files from bucket
	 * Downloads files to server first if they don't exist locally, then deletes from S3
	 */
	public function ajax_remove_all_from_bucket() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Increase time limit for large batch processing (prevent timeouts with 600k+ items)
		@set_time_limit( 300 ); // 5 minutes
		
		// Increase memory limit if possible
		if ( function_exists( 'ini_set' ) ) {
			$current_memory = ini_get( 'memory_limit' );
			$current_bytes = $this->convert_memory_to_bytes( $current_memory );
			$min_bytes = 256 * 1024 * 1024; // 256MB minimum
			if ( $current_bytes < $min_bytes ) {
				@ini_set( 'memory_limit', '256M' );
			}
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		$s3_service = new Clockwork_Offloader_S3_Service();
		$bulk_offloader = new Clockwork_Offloader_Bulk_Offloader();
		
		// Get offset from request (for chunked processing)
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$chunk_size = 50; // Process 50 attachments at a time
		
		// Get attachments in chunks
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => $chunk_size,
			'offset' => $offset,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
		);
		
		$attachments = get_posts( $args );
		
		if ( empty( $attachments ) ) {
			wp_send_json_success( array(
				'deleted' => 0,
				'downloaded' => 0,
				'processed' => 0,
				'message' => __( 'All items have been processed.', 'clockwork-offloader' ),
				'completed' => true,
			) );
		}
		
		$stats = array(
			'deleted' => 0,
			'downloaded' => 0,
			'errors' => array(),
		);
		
		foreach ( $attachments as $attachment_id ) {
			// Only process if offloaded
			if ( ! $tracker->is_offloaded( $attachment_id ) ) {
				continue;
			}
			
			// Check if file exists on server
			$file_path = get_attached_file( $attachment_id );
			$file_exists = $file_path && file_exists( $file_path );
			
			// If file doesn't exist on server, download it first
			if ( ! $file_exists ) {
				$result = $bulk_offloader->restore_attachment( $attachment_id );
				if ( ! is_wp_error( $result ) && $result['files_restored'] > 0 ) {
					$stats['downloaded']++;
				} elseif ( is_wp_error( $result ) ) {
					$stats['errors'][] = sprintf( __( 'Failed to download attachment %d: %s', 'clockwork-offloader' ), $attachment_id, $result->get_error_message() );
					continue; // Skip deletion if download failed
				}
			}
			
			// Now delete from S3
			$offloads = $tracker->get_attachment_offloads( $attachment_id );
			$deleted_count = 0;
			foreach ( $offloads as $offload ) {
				$delete_result = $s3_service->delete_file( $offload->s3_key );
				if ( ! is_wp_error( $delete_result ) ) {
					$deleted_count++;
					$tracker->delete_record( $attachment_id, $offload->size_name );
				} else {
					$stats['errors'][] = sprintf( __( 'Failed to delete %s from S3: %s', 'clockwork-offloader' ), $offload->s3_key, $delete_result->get_error_message() );
				}
			}
			
			if ( $deleted_count > 0 ) {
				$stats['deleted']++;
			}
		}
		
		// Check if there are more attachments to process
		$has_more = count( $attachments ) === $chunk_size;
		
		$message = sprintf(
			__( 'Processed %d attachment(s). Downloaded: %d, Deleted from S3: %d. %s', 'clockwork-offloader' ),
			count( $attachments ),
			$stats['downloaded'],
			$stats['deleted'],
			$has_more ? __( 'Continuing...', 'clockwork-offloader' ) : __( 'Completed.', 'clockwork-offloader' )
		);
		
		if ( ! empty( $stats['errors'] ) ) {
			$message .= ' ' . __( 'Some errors occurred:', 'clockwork-offloader' ) . ' ' . implode( '; ', array_slice( $stats['errors'], 0, 3 ) );
			if ( count( $stats['errors'] ) > 3 ) {
				$message .= ' ' . sprintf( __( '...and %d more.', 'clockwork-offloader' ), count( $stats['errors'] ) - 3 );
			}
		}
		
		wp_send_json_success( array(
			'deleted' => $stats['deleted'],
			'downloaded' => $stats['downloaded'],
			'processed' => count( $attachments ),
			'offset' => $offset + $chunk_size,
			'has_more' => $has_more,
			'errors' => $stats['errors'],
			'message' => $message,
			'completed' => ! $has_more,
		) );
	}
	
	/**
	 * AJAX: Setup wizard step 1 - Connection method and credentials
	 */
	public function ajax_setup_step1() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : 'aws';
		$connection_method = isset( $_POST['connection_method'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_method'] ) ) : '';
		$access_key = isset( $_POST['access_key'] ) ? sanitize_text_field( wp_unslash( $_POST['access_key'] ) ) : '';
		$secret_key = isset( $_POST['secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) : '';
		
		// Validate provider
		if ( ! in_array( $provider, array( 'aws', 'digitalocean', 'cloudflare_r2', 'wasabi', 'backblaze', 'minio', 'custom' ), true ) ) {
			$provider = 'aws'; // Default to AWS
		}
		
		// Validate connection method
		if ( ! in_array( $connection_method, array( 'wp-config', 'database' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid connection method selected.', 'clockwork-offloader' ) ) );
		}
		
		// If wp-config.php method, validate that constants are defined
		if ( $connection_method === 'wp-config' ) {
			$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
			if ( ! $wp_config_creds || empty( $wp_config_creds['access-key-id'] ) || empty( $wp_config_creds['secret-access-key'] ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Please add the credentials to your wp-config.php file first. The code snippet is shown on this page. After adding the code, refresh this page to continue.', 'clockwork-offloader' ),
					'requires_wp_config' => true
				) );
			}
			// Use constants for testing
			$access_key = $wp_config_creds['access-key-id'];
			$secret_key = $wp_config_creds['secret-access-key'];
			
			// Save setup data
			$setup_data = get_transient( 'clockwork_offloader_setup_data' );
			if ( ! is_array( $setup_data ) ) {
				$setup_data = array();
			}
			$setup_data['provider'] = $provider;
			$setup_data['connection_method'] = 'wp-config';
			set_transient( 'clockwork_offloader_setup_data', $setup_data, HOUR_IN_SECONDS );
		} else {
			// For database method, validate that credentials are provided
			if ( empty( $access_key ) || empty( $secret_key ) ) {
				wp_send_json_error( array( 'message' => __( 'Access Key and Secret Key are required.', 'clockwork-offloader' ) ) );
			}
			// Database method - save credentials temporarily for step 2
			$setup_data = get_transient( 'clockwork_offloader_setup_data' );
			if ( ! is_array( $setup_data ) ) {
				$setup_data = array();
			}
			$setup_data['provider'] = $provider;
			$setup_data['connection_method'] = 'database';
			$setup_data['access_key'] = $access_key;
			$setup_data['secret_key'] = $secret_key;
			set_transient( 'clockwork_offloader_setup_data', $setup_data, HOUR_IN_SECONDS );
		}
		
		// Test credentials with a simple S3 operation (use default region for provider)
		$s3_service = new Clockwork_Offloader_S3_Service();
		$default_region = ( $provider === 'digitalocean' ) ? 'nyc3' : 'us-east-1';
		$test_result = $s3_service->test_connection( $access_key, $secret_key, null, $default_region, $provider );
		
		if ( is_wp_error( $test_result ) ) {
			wp_send_json_error( array( 
				'message' => sprintf( __( 'Connection test failed: %s', 'clockwork-offloader' ), $test_result->get_error_message() )
			) );
		}
		
		// Save setup data
		$setup_data = get_transient( 'clockwork_offloader_setup_data' );
		if ( ! is_array( $setup_data ) ) {
			$setup_data = array();
		}
		$setup_data['provider'] = $provider;
		$setup_data['connection_method'] = $connection_method;
		if ( $connection_method === 'database' ) {
			$setup_data['access_key'] = $access_key;
			$setup_data['secret_key'] = $secret_key;
		}
		set_transient( 'clockwork_offloader_setup_data', $setup_data, HOUR_IN_SECONDS );
		
		wp_send_json_success( array( 
			'message' => __( 'Credentials validated successfully!', 'clockwork-offloader' ),
			'next_step' => 2
		) );
	}
	
	/**
	 * AJAX: Setup wizard step 2 - Region and bucket
	 */
	public function ajax_setup_step2() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$region = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';
		$bucket = isset( $_POST['bucket'] ) ? sanitize_text_field( wp_unslash( $_POST['bucket'] ) ) : '';
		
		// Get setup data from step 1
		$setup_data = get_transient( 'clockwork_offloader_setup_data' );
		
		// If transient is missing, try to reconstruct from current configuration
		if ( ! is_array( $setup_data ) || empty( $setup_data['connection_method'] ) ) {
			// Try to reconstruct setup data from current configuration
			$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
			$has_wp_config = ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) );
			
			if ( $has_wp_config ) {
				// Reconstruct from wp-config
				$setup_data = array(
					'connection_method' => 'wp-config',
					'provider' => 'aws', // Default to AWS if not specified
				);
				set_transient( 'clockwork_offloader_setup_data', $setup_data, HOUR_IN_SECONDS );
			} else {
				// Check if credentials exist in database
				$s3_service = new Clockwork_Offloader_S3_Service();
				$credentials = $s3_service->get_credentials();
				
				if ( ! empty( $credentials['access_key'] ) && ! empty( $credentials['secret_key'] ) ) {
					// Reconstruct from database
					$setup_data = array(
						'connection_method' => 'database',
						'provider' => isset( $credentials['provider'] ) ? $credentials['provider'] : 'aws',
						'access_key' => $credentials['access_key'],
						'secret_key' => $credentials['secret_key'],
					);
					set_transient( 'clockwork_offloader_setup_data', $setup_data, HOUR_IN_SECONDS );
				} else {
					// No credentials found - user needs to complete step 1
					wp_send_json_error( array( 'message' => __( 'Please complete step 1 first.', 'clockwork-offloader' ) ) );
				}
			}
		}
		
		// Get provider from setup data
		$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
		
		// Validate region
		if ( empty( $region ) ) {
			$provider_names = array(
				'aws' => __( 'AWS', 'clockwork-offloader' ),
				'digitalocean' => __( 'Digital Ocean', 'clockwork-offloader' ),
			);
			$provider_name = isset( $provider_names[ $provider ] ) ? $provider_names[ $provider ] : __( 'Storage Provider', 'clockwork-offloader' );
			wp_send_json_error( array( 'message' => sprintf( __( '%s Region is required.', 'clockwork-offloader' ), $provider_name ) ) );
		}
		
		// Validate bucket
		if ( empty( $bucket ) ) {
			wp_send_json_error( array( 'message' => __( 'Bucket name is required.', 'clockwork-offloader' ) ) );
		}
		
		// Get credentials for testing
		$access_key = '';
		$secret_key = '';
		
		if ( $setup_data['connection_method'] === 'wp-config' ) {
			$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
			if ( ! $wp_config_creds || empty( $wp_config_creds['access-key-id'] ) || empty( $wp_config_creds['secret-access-key'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Credentials not found in wp-config.php. Please complete step 1.', 'clockwork-offloader' ) ) );
			}
			$access_key = $wp_config_creds['access-key-id'];
			$secret_key = $wp_config_creds['secret-access-key'];
		} else {
			$access_key = isset( $setup_data['access_key'] ) ? $setup_data['access_key'] : '';
			$secret_key = isset( $setup_data['secret_key'] ) ? $setup_data['secret_key'] : '';
		}
		
		// Test bucket access with provider
		$s3_service = new Clockwork_Offloader_S3_Service();
		$test_result = $s3_service->test_connection( $access_key, $secret_key, $bucket, $region, $provider, null );
		
		if ( is_wp_error( $test_result ) ) {
			$error_code = $test_result->get_error_code();
			$error_message = $test_result->get_error_message();
			
			// Provide more helpful message for region mismatch
			if ( $error_code === 'region_mismatch' ) {
				wp_send_json_error( array( 
					'message' => $error_message,
					'error_code' => 'region_mismatch'
				) );
			} else {
				wp_send_json_error( array( 
					'message' => sprintf( __( 'Bucket test failed: %s', 'clockwork-offloader' ), $error_message )
				) );
			}
		}
		
		// Save setup data
		$setup_data['region'] = $region;
		$setup_data['bucket'] = $bucket;
		set_transient( 'clockwork_offloader_setup_data', $setup_data, HOUR_IN_SECONDS );
		
		wp_send_json_success( array( 
			'message' => __( 'Region and bucket validated successfully!', 'clockwork-offloader' ),
			'next_step' => 3
		) );
	}
	
	/**
	 * AJAX: Test connection in setup wizard (Step 3) - just tests, doesn't complete setup
	 */
	public function ajax_setup_test_connection() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Get setup data from transient (saved in steps 1 and 2)
		$setup_data = get_transient( 'clockwork_offloader_setup_data' );
		
		// Get credentials from wp-config or setup data
		$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
		$has_wp_config = ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) );
		
		$access_key = '';
		$secret_key = '';
		$bucket = '';
		$region = '';
		$provider = 'aws';
		
		if ( $has_wp_config ) {
			$access_key = $wp_config_creds['access-key-id'];
			$secret_key = $wp_config_creds['secret-access-key'];
			$provider = isset( $wp_config_creds['provider'] ) ? $wp_config_creds['provider'] : 'aws';
		} elseif ( is_array( $setup_data ) ) {
			$access_key = isset( $setup_data['access_key'] ) ? $setup_data['access_key'] : '';
			$secret_key = isset( $setup_data['secret_key'] ) ? $setup_data['secret_key'] : '';
			$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
		}
		
		// Get bucket and region from setup data (saved in step 2)
		if ( is_array( $setup_data ) ) {
			$bucket = isset( $setup_data['bucket'] ) ? $setup_data['bucket'] : '';
			$region = isset( $setup_data['region'] ) ? $setup_data['region'] : '';
		}
		
		// Fallback to database settings if not in transient
		if ( empty( $bucket ) || empty( $region ) ) {
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			if ( empty( $bucket ) ) {
				$bucket = isset( $settings['s3_bucket'] ) ? $settings['s3_bucket'] : '';
			}
			if ( empty( $region ) ) {
				$region = isset( $settings['s3_region'] ) ? $settings['s3_region'] : '';
			}
		}
		
		$missing = array();
		if ( empty( $access_key ) ) $missing[] = 'Access Key';
		if ( empty( $secret_key ) ) $missing[] = 'Secret Key';
		if ( empty( $bucket ) ) $missing[] = 'Bucket';
		if ( empty( $region ) ) $missing[] = 'Region';
		
		if ( ! empty( $missing ) ) {
			wp_send_json_error( array( 
				'message' => sprintf( 
					__( 'Configuration incomplete. Missing: %s. Please complete steps 1 and 2 first.', 'clockwork-offloader' ),
					implode( ', ', $missing )
				)
			) );
		}
		
		// Test connection with the setup data
		$s3_service = new Clockwork_Offloader_S3_Service();
		$test_result = $s3_service->test_connection( $access_key, $secret_key, $bucket, $region, $provider, null );
		
		if ( is_wp_error( $test_result ) ) {
			wp_send_json_error( array( 
				'message' => sprintf( __( 'Connection test failed: %s', 'clockwork-offloader' ), $test_result->get_error_message() )
			) );
		}
		
		// Uploads work. Now check the part users actually notice: can a browser read what we upload?
		$probe    = $s3_service->probe_public_read( $access_key, $secret_key, $bucket, $region, $provider );
		$response = array(
			'message' => __( 'Connection test successful! Your S3 configuration is working correctly.', 'clockwork-offloader' ),
		);

		if ( $probe['public'] === false ) {
			$response['message'] = __( 'Connected: uploads to the bucket work.', 'clockwork-offloader' );
			$response['warning'] = sprintf(
				/* translators: 1: HTTP status code, 2: provider name */
				__( 'But files in this bucket are not publicly readable (a test object returned HTTP %1$d). Offloaded images will upload fine and then fail to display. %2$s', 'clockwork-offloader' ),
				$probe['status'],
				( $provider === 'digitalocean' )
					? __( 'In the DigitalOcean control panel set the Space\'s file listing / permissions so uploaded files are public, or serve through a CDN endpoint.', 'clockwork-offloader' )
					: __( 'This bucket most likely has "Bucket owner enforced" object ownership (the default for new buckets), which disables ACLs, so public access has to come from a bucket policy. Add this policy under Permissions → Bucket policy in the S3 console, then re-run the test:', 'clockwork-offloader' )
			);
			$response['policy_json'] = $probe['policy_json'];
			$response['probe_url']   = $probe['url'];
		} elseif ( $probe['public'] === null && ! empty( $probe['error'] ) ) {
			$response['warning'] = sprintf(
				/* translators: %s: error text */
				__( 'Could not confirm that uploaded files are publicly readable: %s. Check an offloaded image in a browser after setup.', 'clockwork-offloader' ),
				$probe['error']
			);
		}

		wp_send_json_success( $response );
	}
	
	/**
	 * AJAX: Setup wizard step 3 - Save settings and test connection
	 */
	public function ajax_setup_step3() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		// Check permissions: network admin for network mode, site admin for site mode
		$is_network_admin = is_multisite() && is_network_admin();
		$required_cap = $is_network_admin ? 'manage_network_options' : 'manage_options';
		
		if ( ! current_user_can( $required_cap ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Get setup data from previous steps, or reconstruct from current state
		$setup_data = get_transient( 'clockwork_offloader_setup_data' );
		
		// If transient is missing, try to reconstruct from current configuration
		if ( ! is_array( $setup_data ) ) {
			$s3_service = new Clockwork_Offloader_S3_Service();
			$credentials = $s3_service->get_credentials();
			
			// Reconstruct setup data from current configuration
			$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
			$has_wp_config = ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) );
			$setup_data = array(
				'connection_method' => $has_wp_config ? 'wp-config' : 'database',
				'region' => $credentials['region'],
				'bucket' => $credentials['bucket'],
			);
			
			// If still missing critical data, show error
			if ( empty( $setup_data['region'] ) || empty( $setup_data['bucket'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Missing setup data. Please complete all steps.', 'clockwork-offloader' ) ) );
			}
		}
		
		$connection_method = isset( $setup_data['connection_method'] ) ? $setup_data['connection_method'] : '';
		$region = isset( $setup_data['region'] ) ? $setup_data['region'] : '';
		$bucket = isset( $setup_data['bucket'] ) ? $setup_data['bucket'] : '';
		
		// If still missing, try to get from current credentials
		if ( empty( $region ) || empty( $bucket ) ) {
			$s3_service = new Clockwork_Offloader_S3_Service();
			$credentials = $s3_service->get_credentials();
			if ( empty( $region ) ) {
				$region = $credentials['region'];
			}
			if ( empty( $bucket ) ) {
				$bucket = $credentials['bucket'];
			}
		}
		
		if ( empty( $region ) || empty( $bucket ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing setup data. Please complete all steps.', 'clockwork-offloader' ) ) );
		}
		
		// Get provider from setup data
		$provider = isset( $setup_data['provider'] ) ? $setup_data['provider'] : 'aws';
		
		// Save settings to database
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Save provider
		$settings['provider'] = $provider;
		
		// If database method, save credentials
		if ( $connection_method === 'database' ) {
			$settings['s3_access_key'] = isset( $setup_data['access_key'] ) ? $setup_data['access_key'] : '';
			$settings['s3_secret_key'] = isset( $setup_data['secret_key'] ) ? $setup_data['secret_key'] : '';
		}
		
		// Save region and bucket to database (always stored in database, never in wp-config)
		$settings['s3_region'] = $region;
		$settings['s3_bucket'] = $bucket;
		
		// Save settings based on context (network or site)
		if ( $is_network_admin ) {
			// Network admin: save to network settings
			Clockwork_Offloader_Settings_Helper::update_network_settings( $settings );
			// Enable network mode
			Clockwork_Offloader_Settings_Helper::set_network_mode( true );
		} else {
			// Site admin: save to site settings
			Clockwork_Offloader_Settings_Helper::update_site_settings( $settings );
		}
		
		// Test final connection
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		
		if ( empty( $credentials['access_key'] ) || empty( $credentials['secret_key'] ) || empty( $credentials['bucket'] ) || empty( $credentials['region'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Configuration incomplete. Please check your settings.', 'clockwork-offloader' ) ) );
		}
		
		$test_result = $s3_service->test_connection();
		
		if ( is_wp_error( $test_result ) ) {
			wp_send_json_error( array( 
				'message' => sprintf( __( 'Final connection test failed: %s', 'clockwork-offloader' ), $test_result->get_error_message() )
			) );
		}
		
		// Clear setup data transient
		delete_transient( 'clockwork_offloader_setup_data' );
		
		// Set connection test transient
		set_transient( 'clockwork_offloader_s3_connection_test', true, DAY_IN_SECONDS );
		
		wp_send_json_success( array( 
			'message' => __( 'Setup completed successfully!', 'clockwork-offloader' ),
			'redirect_url' => admin_url( 'options-general.php?page=clockwork-offloader&tab=dashboard' )
		) );
	}
	
	/**
	 * AJAX: Verify wp-config.php constants
	 */
	public function ajax_verify_wp_config() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Check for the NEW CLOCKWORK_OFFLOADER_SETTINGS constant specifically
		$has_new_constant = defined( 'CLOCKWORK_OFFLOADER_SETTINGS' );
		$has_new_separate = defined( 'CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOCKWORK_OFFLOADER_AWS_SECRET_KEY' );
		
		// Check for OLD CLOUDBOUND constants (backward compatibility, but user should update)
		$has_old_constant = defined( 'CLOUDBOUND_OFFLOADER_SETTINGS' );
		$has_old_separate = defined( 'CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY' );
		
		// If user has OLD constants but not NEW ones, warn them to update
		if ( ( $has_old_constant || $has_old_separate ) && ! $has_new_constant && ! $has_new_separate ) {
			wp_send_json_error( array( 
				'message' => __( 'Old CLOUDBOUND_OFFLOADER constants detected. Please update your wp-config.php to use the new CLOCKWORK_OFFLOADER_SETTINGS constant shown above, then remove the old CLOUDBOUND constants.', 'clockwork-offloader' ),
				'has_old_constants' => true,
			) );
		}
		
		// Check if NEW constant is defined
		if ( $has_new_constant || $has_new_separate ) {
			$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
			$message = __( 'wp-config.php constants detected successfully!', 'clockwork-offloader' );
			$details = array();
			
			if ( ! empty( $wp_config_creds['provider'] ) ) {
				$provider_name = $wp_config_creds['provider'] === 'digitalocean' ? __( 'DigitalOcean Spaces', 'clockwork-offloader' ) : __( 'AWS S3', 'clockwork-offloader' );
				$details[] = __( 'Provider', 'clockwork-offloader' ) . ': ' . $provider_name;
			}
			
			// Note: Region and bucket are stored in database, not wp-config
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			if ( ! empty( $settings['s3_region'] ) ) {
				$details[] = __( 'Region', 'clockwork-offloader' ) . ': ' . $settings['s3_region'] . ' (database)';
			}
			if ( ! empty( $settings['s3_bucket'] ) ) {
				$details[] = __( 'Bucket', 'clockwork-offloader' ) . ': ' . $settings['s3_bucket'] . ' (database)';
			}
			
			wp_send_json_success( array(
				'message' => $message,
				'details' => $details,
				'verified' => true,
			) );
		} else {
			$missing = array();
			$missing[] = 'CLOCKWORK_OFFLOADER_SETTINGS';
			
			$full_message = __( 'wp-config.php constants are not currently defined. Please add the code snippet to your wp-config.php file and reload this page.', 'clockwork-offloader' );
			
			wp_send_json_error( array(
				'message' => __( 'Constants not found', 'clockwork-offloader' ),
				'missing' => $missing,
				'full_message' => $full_message,
				'verified' => false,
			) );
		}
	}
	
	/**
	 * Check if migration popup should be shown
	 *
	 * Based on user's requirements:
	 * 1B: If Clockwork Offloader is not configured AND no files are offloaded
	 * 2A: Dismissible with "Don't show again" option
	 * 3B: No offloaded files tracked in the database
	 * 4A: Only show if a source offload plugin is detected
	 * 5C: Check if Clockwork Offloader has offloaded files — if yes, don't show popup
	 *
	 * @param array $credentials Clockwork Offloader credentials
	 * @param array $stats Statistics from tracker
	 * @return bool
	 */
	private function should_show_migration_popup( $credentials, $stats ) {
		// Check if migrator plugin is active
		if ( ! is_plugin_active( 'clockwork-offloader-migrator/clockwork-offloader-migrator.php' ) ) {
			return false;
		}
		
		// Check if user has dismissed the popup
		$user_id = get_current_user_id();
		$dismissed = get_user_meta( $user_id, 'clockwork_offloader_migration_popup_dismissed', true );
		if ( $dismissed ) {
			return false;
		}
		
		// 4A: Only show if a source offload plugin is detected
		$source_plugin_installed = file_exists( WP_PLUGIN_DIR . '/amazon-s3-and-cloudfront/wordpress-s3.php' ) || 
		                            file_exists( WP_PLUGIN_DIR . '/offload-media-cloud-storage/start.php' );
		
		if ( ! $source_plugin_installed ) {
			return false;
		}
		
		// 5C: Check if Clockwork Offloader has offloaded files — if yes, don't show popup
		if ( $stats['total_offloaded'] > 0 ) {
			return false;
		}
		
		// 1B: If Clockwork Offloader is not configured AND no files are offloaded
		// (We already checked no files above, so now check if not configured)
		$is_configured = ! empty( $credentials['access_key'] ) && 
		                 ! empty( $credentials['secret_key'] ) && 
		                 ! empty( $credentials['bucket'] ) && 
		                 ! empty( $credentials['region'] );
		
		// Show if not configured (we already know no files are offloaded from check above)
		if ( ! $is_configured ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * AJAX: Dismiss migrator plugin notice
	 */
	public function ajax_dismiss_migrator_notice() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'clockwork_offloader_dismiss_migrator_notice', true );
		
		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'clockwork-offloader' ) ) );
	}
	
	/**
	 * AJAX: Dismiss migration popup
	 */
	public function ajax_dismiss_migration_popup() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$dont_show_again = isset( $_POST['dont_show_again'] ) && $_POST['dont_show_again'] === 'true';
		
		if ( $dont_show_again ) {
			$user_id = get_current_user_id();
			update_user_meta( $user_id, 'clockwork_offloader_migration_popup_dismissed', true );
		}
		
		wp_send_json_success( array(
			'message' => __( 'Popup dismissed.', 'clockwork-offloader' ),
		) );
	}
	
	/**
	 * AJAX: Force all media to use S3 URLs
	 * Enables the rewrite_urls setting globally
	 */
	public function ajax_force_s3_urls() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Get current settings
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Get statistics before change
		$tracker = new Clockwork_Offloader_Tracker();
		$stats = $tracker->get_statistics( true ); // Force refresh
		
		// Enable rewrite_urls setting
		$settings['rewrite_urls'] = true;
		
		// Update settings
		Clockwork_Offloader_Settings_Helper::update_settings( $settings );
		
		// Calculate percentage
		$total_attachments = $stats['total_attachments'];
		$offloaded_count = $stats['offloaded_attachments'];
		$percentage = $total_attachments > 0 ? round( ( $offloaded_count / $total_attachments ) * 100 ) : 0;
		
		wp_send_json_success( array(
			'message' => __( 'All media URLs will now use S3 URLs.', 'clockwork-offloader' ),
			'rewrite_urls_enabled' => true,
			'total_attachments' => $total_attachments,
			'offloaded_count' => $offloaded_count,
			'percentage' => $percentage,
		) );
	}
	
	/**
	 * AJAX: Switch back to local URLs
	 * Disables the rewrite_urls setting globally
	 */
	public function ajax_switch_back_local() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! Clockwork_Offloader_Settings_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		// Get current settings
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Get statistics before change
		$tracker = new Clockwork_Offloader_Tracker();
		$stats = $tracker->get_statistics( true ); // Force refresh
		
		// Disable rewrite_urls setting
		$settings['rewrite_urls'] = false;
		
		// Update settings
		Clockwork_Offloader_Settings_Helper::update_settings( $settings );
		
		// Calculate percentage (will be 0% since we're switching to local)
		$total_attachments = $stats['total_attachments'];
		$offloaded_count = $stats['offloaded_attachments'];
		$percentage = 0; // Local URLs = 0% using S3
		
		wp_send_json_success( array(
			'message' => __( 'All media URLs will now use local URLs.', 'clockwork-offloader' ),
			'rewrite_urls_enabled' => false,
			'total_attachments' => $total_attachments,
			'offloaded_count' => $offloaded_count,
			'percentage' => $percentage,
		) );
	}
	
	/**
	 * AJAX: Verify URLs are pointing to expected location
	 */
	public function ajax_verify_urls() {
		check_ajax_referer( 'clockwork_offloader_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'clockwork-offloader' ) ) );
		}
		
		$expected_location = isset( $_POST['expected_location'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_location'] ) ) : 's3';
		
		// Get current settings
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$rewrite_enabled = ! empty( $settings['rewrite_urls'] );
		
		// Get S3 service for URL info
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		$bucket = ! empty( $credentials['bucket'] ) ? $credentials['bucket'] : '';
		$region = ! empty( $credentials['region'] ) ? $credentials['region'] : '';
		
		// Build expected S3 URL pattern
		$s3_url_pattern = '';
		if ( $bucket && $region ) {
			$provider       = ! empty( $credentials['provider'] ) ? $credentials['provider'] : 'aws';
			$s3_url_pattern = Clockwork_Offloader_S3_Service::build_public_url( $bucket, '', $region, $provider );
		}
		
		// Get site URL for local comparison
		$site_url = site_url();
		$upload_dir = wp_upload_dir();
		$local_url_pattern = $upload_dir['baseurl'];
		
		// Get sample attachments to check
		$tracker = new Clockwork_Offloader_Tracker();
		$sample_urls = array();
		$all_correct = true;
		$checked_count = 0;
		$correct_count = 0;
		
		// Get a few offloaded attachments to sample
		global $wpdb;
		$table_name = $wpdb->prefix . 'clockwork_offloads';
		$table_name_escaped = esc_sql( $table_name );
		
		$sample_attachments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT attachment_id FROM `{$table_name_escaped}` WHERE size_name = '' LIMIT %d",
				5
			)
		);
		
		foreach ( $sample_attachments as $attachment ) {
			$attachment_id = $attachment->attachment_id;
			$url = wp_get_attachment_url( $attachment_id );
			
			if ( ! $url ) {
				continue;
			}
			
			$checked_count++;
			$is_s3 = ( $s3_url_pattern && strpos( $url, $s3_url_pattern ) === 0 ) || 
			         ( strpos( $url, 's3.' ) !== false && strpos( $url, 'amazonaws.com' ) !== false ) ||
			         ( strpos( $url, 'digitaloceanspaces.com' ) !== false );
			$is_local = strpos( $url, $local_url_pattern ) === 0 || strpos( $url, $site_url ) === 0;
			
			if ( $expected_location === 's3' ) {
				$is_correct = $is_s3;
				$sample_urls[] = array(
					'url' => $url,
					'is_s3' => $is_s3,
					'is_correct' => $is_correct,
				);
			} else {
				$is_correct = $is_local;
				$sample_urls[] = array(
					'url' => $url,
					'is_local' => $is_local,
					'is_correct' => $is_correct,
				);
			}
			
			if ( $is_correct ) {
				$correct_count++;
			} else {
				$all_correct = false;
			}
		}
		
		// Build response message
		$message = '';
		if ( $expected_location === 's3' ) {
			if ( ! $rewrite_enabled ) {
				$message = __( 'Note: URL rewriting is currently disabled in settings. Enable it to serve media from S3.', 'clockwork-offloader' );
				$all_correct = false;
			} elseif ( $checked_count === 0 ) {
				$message = __( 'No offloaded media found to verify.', 'clockwork-offloader' );
			} elseif ( $all_correct ) {
				$message = sprintf( __( 'Verified %d URLs - all pointing to S3.', 'clockwork-offloader' ), $checked_count );
			} else {
				$message = sprintf( __( '%d of %d URLs are pointing to S3.', 'clockwork-offloader' ), $correct_count, $checked_count );
			}
		} else {
			if ( $rewrite_enabled ) {
				$message = __( 'Note: URL rewriting is currently enabled. Disable it to serve media from your server.', 'clockwork-offloader' );
				$all_correct = false;
			} elseif ( $checked_count === 0 ) {
				$message = __( 'No offloaded media found to verify.', 'clockwork-offloader' );
			} elseif ( $all_correct ) {
				$message = sprintf( __( 'Verified %d URLs - all pointing to local server.', 'clockwork-offloader' ), $checked_count );
			} else {
				$message = sprintf( __( '%d of %d URLs are pointing to local server.', 'clockwork-offloader' ), $correct_count, $checked_count );
			}
		}
		
		wp_send_json_success( array(
			'all_correct' => $all_correct,
			'message' => $message,
			'sample_urls' => $sample_urls,
			'rewrite_enabled' => $rewrite_enabled,
			'checked_count' => $checked_count,
			'correct_count' => $correct_count,
		) );
	}
	
	/**
	 * Convert memory limit string to bytes
	 * Helper function for timeout prevention
	 *
	 * @param string $value Memory limit string (e.g., "256M", "512M")
	 * @return int Bytes
	 */
	private function convert_memory_to_bytes( $value ) {
		$value = trim( $value );
		$last = strtolower( $value[ strlen( $value ) - 1 ] );
		$value = (int) $value;
		
		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// Fall through
			case 'm':
				$value *= 1024;
				// Fall through
			case 'k':
				$value *= 1024;
		}
		
		return $value;
	}
	
	/**
	 * Render upgrade page
	 */
	public function render_upgrade_page() {
		$upgrade_url = 'https://clockworkplugins.com/plugins/clockwork-offloader';
		?>
		<div class="clockwork-offloader-upgrade-page">
			<?php
			if ( file_exists( CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/partials/pro-upgrade-ad.php' ) ) {
				include CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'admin/views/partials/pro-upgrade-ad.php';
			}
			?>
			
			<div class="clockwork-offloader-upgrade-features" style="margin-top: 24px;">
				<div class="clockwork-offloader-upgrade-feature">
					<h3><span class="dashicons dashicons-cloud" style="color: var(--cwk-primary);"></span> <?php esc_html_e( 'CloudFront CDN & Assets Pull', 'clockwork-offloader' ); ?></h3>
					<p><?php esc_html_e( 'Deliver media and static assets (CSS, JS, fonts) from worldwide CloudFront edge locations with automatic cache invalidation.', 'clockwork-offloader' ); ?></p>
				</div>
				
				<div class="clockwork-offloader-upgrade-feature">
					<h3><span class="dashicons dashicons-lock" style="color: var(--cwk-primary);"></span> <?php esc_html_e( 'Private Media & Expiring Signed URLs', 'clockwork-offloader' ); ?></h3>
					<p><?php esc_html_e( 'Protect downloadable digital goods for WooCommerce and Easy Digital Downloads with secure, time-limited pre-signed S3 links.', 'clockwork-offloader' ); ?></p>
				</div>
				
				<div class="clockwork-offloader-upgrade-feature">
					<h3><span class="dashicons dashicons-update" style="color: var(--cwk-primary);"></span> <?php esc_html_e( 'Bulk Queue & Background Processing', 'clockwork-offloader' ); ?></h3>
					<p><?php esc_html_e( 'Offload existing media libraries in bulk, download files back to local server on demand, and handle 600,000+ items without timeouts.', 'clockwork-offloader' ); ?></p>
				</div>
				
				<div class="clockwork-offloader-upgrade-feature">
					<h3><span class="dashicons dashicons-migrate" style="color: var(--cwk-primary);"></span> <?php esc_html_e( 'Zero-Downtime Migration Tools', 'clockwork-offloader' ); ?></h3>
					<p><?php esc_html_e( 'Switch seamlessly from other offload plugins with 1-click migration. Preserves your existing S3 bucket, paths, and URLs.', 'clockwork-offloader' ); ?></p>
				</div>

				<div class="clockwork-offloader-upgrade-feature">
					<h3><span class="dashicons dashicons-admin-multisite" style="color: var(--cwk-primary);"></span> <?php esc_html_e( 'WordPress Multisite Network Support', 'clockwork-offloader' ); ?></h3>
					<p><?php esc_html_e( 'Single-click network-wide activation with automated per-subsite isolation under sites/{id}/ and global settings inheritance.', 'clockwork-offloader' ); ?></p>
				</div>

				<div class="clockwork-offloader-upgrade-feature">
					<h3><span class="dashicons dashicons-terminal" style="color: var(--cwk-primary);"></span> <?php esc_html_e( 'Full WP-CLI Command Suite', 'clockwork-offloader' ); ?></h3>
					<p><?php esc_html_e( 'Script, automate, and inspect large fleet migrations from the terminal with wp clockwork-offloader commands.', 'clockwork-offloader' ); ?></p>
				</div>
			</div>
			
			<div class="clockwork-offloader-upgrade-cta" style="text-align: center; margin-top: 30px; padding: 24px; background: #fff; border: 1px solid #e3deef; border-radius: 12px;">
				<h3 style="margin: 0 0 8px 0; font-size: 18px; color: #212025;"><?php esc_html_e( 'Ready to unlock Pro features?', 'clockwork-offloader' ); ?></h3>
				<p style="margin: 0 0 16px 0; font-size: 13px; color: #5b5566;">
					<?php esc_html_e( 'Get Clockwork Offloader Pro today starting at just $50/yr with our locked-in 50% launch discount and 14-day money-back guarantee.', 'clockwork-offloader' ); ?>
				</p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="button clockwork-btn-upgrade">
					<span><?php esc_html_e( 'View Pricing &amp; Upgrade to Pro (50% Off) &rarr;', 'clockwork-offloader' ); ?></span>
				</a>
			</div>
		</div>
		<?php
	}
}

