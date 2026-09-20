<?php
/**
 * Plugin Name: Clockwork Offloader Lite
 * Plugin URI: https://aaronreimann.com/clockwork-offloader
 * Description: Offload media files to Amazon S3 with optional URL rewriting and delete-after-upload. Upgrade to Pro for bulk tools, migration, and more.
 * Version: 1.1.1
 * Author: Aaron Reimann
 * Author URI: https://aaronreimann.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clockwork-offloader
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 8.2
 * Tested up to: 6.9
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'CLOCKWORK_OFFLOADER_VERSION', '1.1.1' );
define( 'CLOCKWORK_OFFLOADER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLOCKWORK_OFFLOADER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLOCKWORK_OFFLOADER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Clockwork_Offloader {
	
	/**
	 * Instance of this class
	 *
	 * @var Clockwork_Offloader
	 */
	private static $instance = null;
	
	/**
	 * Get instance of this class
	 *
	 * @return Clockwork_Offloader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}
	
	/**
	 * Load required files
	 */
	private function load_dependencies() {
		// Load Composer autoloader if it exists
		if ( file_exists( CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'vendor/autoload.php';
		}
		
		// Load plugin classes
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-settings-helper.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-offload-tracker.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-s3-service.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-url-rewriter.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-lite-restrictions.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-admin.php';
		// Media Library integration removed to improve performance
		// require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-media-library.php';
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Activation and deactivation hooks
		register_activation_hook( __FILE__, array( 'Clockwork_Offloader', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'Clockwork_Offloader', 'deactivate' ) );

		// Multisite: Create tables when new site is created. wp_initialize_site replaced the
		// deprecated wpmu_new_blog in WP 5.1; priority 200 runs after core has populated the
		// new site's tables.
		if ( is_multisite() ) {
			add_action( 'wp_initialize_site', array( 'Clockwork_Offloader', 'activate_new_site' ), 200 );
		}

		// Migrate R2/GCS settings to AWS (backward compatibility)
		add_action( 'admin_init', array( $this, 'migrate_unsupported_providers' ) );

		// Upgrade the offloads table schema for existing installs. Runs here (not just on
		// register_activation_hook) because WordPress never fires the activation hook when
		// plugin files are updated in place without an explicit deactivate/reactivate.
		add_action( 'admin_init', array( 'Clockwork_Offloader_Tracker', 'maybe_upgrade_table' ) );

		// Everything that depends on whether Pro is present is wired on plugins_loaded, after
		// every plugin file has been included. Doing it at include time made the result depend
		// on plugin load order (alphabetical by directory), so Pro's AJAX handlers and queue
		// cron silently never registered when Lite's directory sorted before Pro's.
		add_action( 'plugins_loaded', array( $this, 'init_runtime' ), 20 );

		// Initialize auto-offload hooks
		add_action( 'add_attachment', array( $this, 'handle_new_attachment' ), 10, 1 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'handle_attachment_metadata' ), 10, 2 );

		// Clear statistics cache when attachments are deleted
		add_action( 'delete_attachment', array( $this, 'handle_delete_attachment' ), 10, 1 );
	}

	/**
	 * Wire up admin, URL rewriting and (when Pro is active) queue processing.
	 * Runs on plugins_loaded priority 20 — see init_hooks().
	 */
	public function init_runtime() {
		// Initialize admin
		if ( is_admin() ) {
			new Clockwork_Offloader_Admin();
			// Media Library integration removed for performance
			// new Clockwork_Offloader_Media_Library();
		}

		// Initialize URL rewriter
		Clockwork_Offloader_URL_Rewriter::get_instance();

		// Initialize queue processing (Pro-only)
		if ( class_exists( 'Clockwork_Offloader_Pro' ) ) {
			$this->init_queue();
		}

		/**
		 * Fires once Lite (and Pro, if present) have finished wiring their hooks.
		 */
		do_action( 'clockwork_offloader_loaded', $this );
	}

	/**
	 * Plugin activation
	 *
	 * @param bool $network_wide True when network-activated on a multisite.
	 */
	public static function activate( $network_wide = false ) {
		// Run migration from old plugin name if needed
		self::migrate_from_cloudbound();

		// Load tracker class and settings helper
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-settings-helper.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-offload-tracker.php';

		if ( is_multisite() && $network_wide ) {
			// Tables are per-site ($wpdb->prefix), so create them on every existing site now
			// rather than lazily on each site's first admin visit.
			$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				self::activate_site();
				restore_current_blog();
			}
		} else {
			self::activate_site();
		}

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Per-site activation: create tables and default options for the current blog.
	 */
	private static function activate_site() {
		// Create tables for current site
		Clockwork_Offloader_Tracker::create_table();

		// The queue table belongs to Pro. Only Pro ships class-queue.php, so never require it
		// from here — just create the table if Pro is already loaded.
		if ( class_exists( 'Clockwork_Offloader_Queue' ) ) {
			Clockwork_Offloader_Queue::create_table();
		}

		// Track installation date for Lite auto-offload restriction
		if ( ! get_option( 'clockwork_offloader_installed_date' ) ) {
			add_option( 'clockwork_offloader_installed_date', time() );
		}
		
		// Set default site options (only if not already set from migration)
		// In multisite, each site gets its own settings unless network mode is enabled
		$existing_settings = get_option( 'clockwork_offloader_settings' );
		if ( ! $existing_settings ) {
			$defaults = array(
				'provider' => 'aws', // Default to AWS for backward compatibility
				'auto_offload' => false,
				'delete_after_upload' => false,
				'rewrite_urls' => false,
				's3_region' => 'us-east-1',
				's3_base_path' => '',
				'queue_batch_size' => 10,
				'enable_throttle' => false,
				'throttle_rate' => 100,
				'show_media_library_status' => true, // Default to ON
				'development_mode' => false,
			);
			
			add_option( 'clockwork_offloader_settings', $defaults );
		} else {
			// Ensure existing installations have provider set to 'aws' for backward compatibility
			$settings = $existing_settings;
			if ( ! isset( $settings['provider'] ) ) {
				$settings['provider'] = 'aws';
				update_option( 'clockwork_offloader_settings', $settings );
			}
		}
	}

	/**
	 * Create tables for a new site in multisite.
	 * Hooked to wp_initialize_site.
	 *
	 * @param WP_Site|int $site New site object (or a blog ID, for callers using the old hook).
	 */
	public static function activate_new_site( $site ) {
		if ( ! is_multisite() ) {
			return;
		}

		$blog_id = ( $site instanceof WP_Site ) ? (int) $site->blog_id : (int) $site;
		if ( ! $blog_id ) {
			return;
		}

		// Load required classes
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-settings-helper.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-offload-tracker.php';

		// Switch to new site and create tables
		switch_to_blog( $blog_id );
		self::activate_site();
		restore_current_blog();
	}
	
	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		// Clear any transients
		delete_transient( 'clockwork_offloader_s3_connection_test' );
	}
	
	/**
	 * Handle new attachment upload
	 *
	 * @param int $attachment_id Attachment ID
	 */
	public function handle_new_attachment( $attachment_id ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Only auto-offload if enabled
		if ( empty( $settings['auto_offload'] ) ) {
			return;
		}
		
		// Check if S3 is configured
		if ( ! $this->is_s3_configured() ) {
			return;
		}
		
		// Lite restriction: Only offload files uploaded after plugin installation
		// Pro can offload all files
		if ( ! class_exists( 'Clockwork_Offloader_Pro' ) ) {
			$installed_date = get_option( 'clockwork_offloader_installed_date' );
			if ( $installed_date ) {
				$attachment_date = get_post_time( 'U', false, $attachment_id );
				if ( $attachment_date && $attachment_date < $installed_date ) {
					// File was uploaded before plugin installation - don't auto-offload in Lite
					return;
				}
			}
		}
		
		// Offload the original file. Never delete it here: add_attachment fires inside
		// wp_insert_attachment(), BEFORE WordPress generates thumbnails / the -scaled copy from
		// this file. Local deletion (if enabled) happens in handle_attachment_metadata().
		$this->offload_attachment_file( $attachment_id, get_attached_file( $attachment_id ) );
	}

	/**
	 * Handle attachment metadata generation (image sizes)
	 *
	 * Runs after WordPress has generated every intermediate size, so this is the one place
	 * where it is safe to offload the whole set and, if delete-after-upload is on, remove
	 * the local copies.
	 *
	 * @param array $metadata Attachment metadata
	 * @param int   $attachment_id Attachment ID
	 * @return array
	 */
	public function handle_attachment_metadata( $metadata, $attachment_id ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();

		// Only auto-offload if enabled
		if ( empty( $settings['auto_offload'] ) ) {
			return $metadata;
		}

		// Check if S3 is configured
		if ( ! $this->is_s3_configured() ) {
			return $metadata;
		}

		$tracker = new Clockwork_Offloader_Tracker();
		$files = self::get_attachment_files( $attachment_id, $metadata );

		// Offload original (if add_attachment didn't already), every size, and the full-res
		// original behind a -scaled image.
		foreach ( $files as $size_name => $file_path ) {
			if ( file_exists( $file_path ) && ! $tracker->is_offloaded( $attachment_id, $size_name ) ) {
				$this->offload_attachment_file( $attachment_id, $file_path, $size_name );
			}
		}

		// Delete local copies only once everything above is confirmed offloaded.
		if ( ! empty( $settings['delete_after_upload'] ) ) {
			foreach ( $files as $size_name => $file_path ) {
				if ( file_exists( $file_path ) && $tracker->is_offloaded( $attachment_id, $size_name ) ) {
					self::delete_local_file( $file_path );
				}
			}
		}

		return $metadata;
	}

	/**
	 * Every local file that belongs to an attachment, keyed by the size_name the tracker
	 * uses for it: '' for the attached file, each intermediate size by name, and
	 * 'original_image' for the full-resolution original behind a -scaled image.
	 *
	 * @param int        $attachment_id Attachment ID
	 * @param array|null $metadata      Attachment metadata (fetched if null)
	 * @return array<string, string> size_name => absolute path
	 */
	public static function get_attachment_files( $attachment_id, $metadata = null ) {
		$files = array();

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return $files;
		}
		$files[''] = $file_path;

		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}
		if ( ! is_array( $metadata ) ) {
			return $files;
		}

		$file_dir = dirname( $file_path );

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! empty( $size_data['file'] ) ) {
					$files[ $size_name ] = $file_dir . '/' . $size_data['file'];
				}
			}
		}

		if ( ! empty( $metadata['original_image'] ) ) {
			$files['original_image'] = $file_dir . '/' . $metadata['original_image'];
		}

		return $files;
	}

	/**
	 * Delete a local file, but only if it lives inside this site's uploads directory.
	 *
	 * @param string $file_path Absolute path
	 * @return bool
	 */
	public static function delete_local_file( $file_path ) {
		$upload_dir = wp_upload_dir();
		$real_upload_dir = realpath( $upload_dir['basedir'] );
		$real_file_path = realpath( dirname( $file_path ) );

		if ( false === $real_upload_dir || false === $real_file_path || 0 !== strpos( $real_file_path, $real_upload_dir ) ) {
			return false;
		}

		return @unlink( $file_path );
	}
	
	/**
	 * Handle attachment deletion
	 *
	 * This hook fires when an attachment is deleted from the media library.
	 * It ensures that:
	 * 1. Files are deleted from S3
	 * 2. Database records are removed
	 * 3. Statistics cache is cleared
	 * 
	 * Note: wp_delete_attachment() already handles deleting local files from the server.
	 *
	 * @param int     $attachment_id Attachment ID
	 * @param WP_Post $post          Post object (optional, WordPress 5.5+)
	 */
	public function handle_delete_attachment( $attachment_id, $post = null ) {
		// Ensure we have a valid attachment ID
		if ( empty( $attachment_id ) || ! is_numeric( $attachment_id ) ) {
			return;
		}
		
		// Verify it's actually an attachment
		if ( $post && 'attachment' !== $post->post_type ) {
			return;
		}
		
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-offload-tracker.php';
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-s3-service.php';
		
		$tracker = new Clockwork_Offloader_Tracker();
		$s3_service = new Clockwork_Offloader_S3_Service();
		
		// Get all offload records for this attachment (all sizes)
		// This includes all image sizes that were offloaded
		$offloads = $tracker->get_attachment_offloads( $attachment_id );
		
		// Delete each file from S3
		if ( ! empty( $offloads ) ) {
			foreach ( $offloads as $offload ) {
				if ( empty( $offload->s3_key ) ) {
					continue;
				}
				
				$delete_result = $s3_service->delete_file( $offload->s3_key );
				
				if ( is_wp_error( $delete_result ) ) {
					// Log error for debugging (only if WP_DEBUG is enabled)
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'Clockwork Offloader: Failed to delete %s from S3: %s',
						$offload->s3_key,
						$delete_result->get_error_message()
					) );
				}
					// Continue trying to delete other files even if one fails
					continue;
				}
				
				// Delete database record if S3 deletion was successful
				$tracker->delete_record( $attachment_id, $offload->size_name );
			}
		}
		
		// Clear statistics cache when attachments are deleted
		$tracker->clear_statistics_cache();
	}
	
	/**
	 * Offload a single file for an attachment
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $file_path Local file path
	 * @param string $size_name Image size name (optional)
	 */
	private function offload_attachment_file( $attachment_id, $file_path, $size_name = '' ) {
		if ( ! file_exists( $file_path ) ) {
			return;
		}
		
		// Check if already offloaded
		$tracker = new Clockwork_Offloader_Tracker();
		if ( $tracker->is_offloaded( $attachment_id, $size_name ) ) {
			return;
		}
		
		$s3_service = new Clockwork_Offloader_S3_Service();
		
		// Upload to S3
		$result = $s3_service->upload_file( $file_path, $attachment_id, $size_name );
		
		if ( is_wp_error( $result ) ) {
			// Log error using WordPress debug log
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Clockwork Offloader: Failed to upload ' . esc_html( $file_path ) . ' - ' . esc_html( $result->get_error_message() ) );
			}
			return;
		}
		
		// Track in database
		$tracker->record_offload(
			$attachment_id,
			$result['bucket'],
			$result['s3_key'],
			$file_path,
			filesize( $file_path ),
			$size_name
		);

		// Local deletion is deliberately NOT done here — see handle_attachment_metadata().
	}
	
	/**
	 * Check if S3 is properly configured
	 *
	 * @return bool
	 */
	private function is_s3_configured() {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Check if credentials exist (either in wp-config or database)
		$has_credentials = false;
		$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
		
		if ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) ) {
			$has_credentials = true;
		} else {
			// Check database settings
			$has_credentials = ! empty( $settings['s3_access_key'] ) && ! empty( $settings['s3_secret_key'] );
		}
		
		// Bucket and region are always from database settings
		return $has_credentials &&
		       ! empty( $settings['s3_bucket'] ) &&
		       ! empty( $settings['s3_region'] );
	}
	
	/**
	 * Initialize queue processing
	 */
	private function init_queue() {
		// Add custom cron interval
		add_filter( 'cron_schedules', array( $this, 'add_queue_cron_interval' ) );
		
		// Register queue processing hook
		add_action( 'clockwork_offloader_process_queue', array( $this, 'process_queue_cron' ) );
	}
	
	/**
	 * Add custom cron interval for queue processing
	 *
	 * @param array $schedules Existing schedules
	 * @return array
	 */
	public function add_queue_cron_interval( $schedules ) {
		$schedules['clockwork_offloader_queue_interval'] = array(
			'interval' => 60, // Every minute
			'display' => __( 'Every Minute (Clockwork Offloader Queue)', 'clockwork-offloader' ),
		);
		
		return $schedules;
	}
	
	/**
	 * Process queue via cron
	 *
	 * Uses adaptive batch sizing for smooth processing of large queues (10,000+ items)
	 */
	public function process_queue_cron() {
		$queue = new Clockwork_Offloader_Queue();
		
		// Get queue statistics to determine optimal batch size
		$queue_stats = $queue->get_statistics();
		$pending_count = $queue_stats['pending'];
		
		// Adaptive batch sizing based on queue size
		// For very large queues, process more items per batch to make faster progress
		if ( $pending_count > 10000 ) {
			// Very large queue: process 50 items per batch
			$batch_size = 50;
		} elseif ( $pending_count > 5000 ) {
			// Large queue: process 30 items per batch
			$batch_size = 30;
		} elseif ( $pending_count > 1000 ) {
			// Medium queue: process 20 items per batch
			$batch_size = 20;
		} else {
			// Small queue: use settings or default
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			$batch_size = ! empty( $settings['queue_batch_size'] ) ? absint( $settings['queue_batch_size'] ) : 10;
		}
		
		// Process batch
		$stats = $queue->process_batch( $batch_size );
		
		// Update last processing time
		update_option( 'clockwork_offloader_last_queue_process', time() );
		
		// If no more items, unschedule
		$queue_stats = $queue->get_statistics();
		if ( $queue_stats['pending'] === 0 && $queue_stats['processing'] === 0 ) {
			$timestamp = wp_next_scheduled( 'clockwork_offloader_process_queue' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'clockwork_offloader_process_queue' );
			}
		}
	}
	
	/**
	 * Migrate data from old CloudBound Offloader plugin
	 */
	private static function migrate_from_cloudbound() {
		global $wpdb;
		
		// Migrate database tables
		$old_table_offloads = $wpdb->prefix . 'cloudbound_offloads';
		$new_table_offloads = $wpdb->prefix . 'clockwork_offloads';
		$old_table_queue = $wpdb->prefix . 'cloudbound_offload_queue';
		$new_table_queue = $wpdb->prefix . 'clockwork_offload_queue';
		
		// Check if old tables exist and new ones don't
		// Note: SHOW TABLES LIKE doesn't work with $wpdb->prepare(), so we use esc_sql() instead
		$old_table_offloads_escaped = esc_sql( $old_table_offloads );
		$new_table_offloads_escaped = esc_sql( $new_table_offloads );
		$old_table_queue_escaped = esc_sql( $old_table_queue );
		$new_table_queue_escaped = esc_sql( $new_table_queue );
		
		$old_offloads_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$old_table_offloads_escaped}'" ) === $old_table_offloads;
		$new_offloads_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$new_table_offloads_escaped}'" ) === $new_table_offloads;
		
		if ( $old_offloads_exists && ! $new_offloads_exists ) {
			$wpdb->query( "RENAME TABLE `{$old_table_offloads_escaped}` TO `{$new_table_offloads_escaped}`" );
		}
		
		$old_queue_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$old_table_queue_escaped}'" ) === $old_table_queue;
		$new_queue_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$new_table_queue_escaped}'" ) === $new_table_queue;
		
		if ( $old_queue_exists && ! $new_queue_exists ) {
			$wpdb->query( "RENAME TABLE `{$old_table_queue_escaped}` TO `{$new_table_queue_escaped}`" );
		}
		
		// Migrate options
		$old_settings = get_option( 'cloudbound_offloader_settings' );
		$current_settings = get_option( 'clockwork_offloader_settings' );
		if ( $old_settings !== false && $current_settings === false ) {
			update_option( 'clockwork_offloader_settings', $old_settings );
			delete_option( 'cloudbound_offloader_settings' );
		}
		
		$old_last_process = get_option( 'cloudbound_offloader_last_queue_process' );
		if ( $old_last_process !== false && get_option( 'clockwork_offloader_last_queue_process' ) === false ) {
			update_option( 'clockwork_offloader_last_queue_process', $old_last_process );
			delete_option( 'cloudbound_offloader_last_queue_process' );
		}
		
		// Migrate transients
		$old_transient = get_transient( 'cloudbound_offloader_s3_connection_test' );
		if ( $old_transient !== false ) {
			set_transient( 'clockwork_offloader_s3_connection_test', $old_transient, 3600 );
			delete_transient( 'cloudbound_offloader_s3_connection_test' );
		}
		
		$old_stats = get_transient( 'cloudbound_offloader_stats' );
		if ( $old_stats !== false ) {
			set_transient( 'clockwork_offloader_stats', $old_stats, 3600 );
			delete_transient( 'cloudbound_offloader_stats' );
		}
		
		// Migrate postmeta
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
			'_clockwork_dev_file',
			'_cloudbound_dev_file'
		) );
		
		// Migrate dev attachment IDs option
		$old_dev_ids = get_option( 'cloudbound_dev_attachment_ids' );
		if ( $old_dev_ids !== false && get_option( 'clockwork_dev_attachment_ids' ) === false ) {
			update_option( 'clockwork_dev_attachment_ids', $old_dev_ids );
			delete_option( 'cloudbound_dev_attachment_ids' );
		}
		
		// Migrate cron jobs
		$old_cron_hook = 'cloudbound_offloader_process_queue';
		$new_cron_hook = 'clockwork_offloader_process_queue';
		$crons = _get_cron_array();
		if ( $crons ) {
			foreach ( $crons as $timestamp => $cron ) {
				if ( isset( $cron[ $old_cron_hook ] ) ) {
					$cron[ $new_cron_hook ] = $cron[ $old_cron_hook ];
					unset( $cron[ $old_cron_hook ] );
					$crons[ $timestamp ] = $cron;
				}
			}
			_set_cron_array( $crons );
		}
	}
	
	/**
	 * Migrate unsupported providers (R2/GCS) to AWS
	 * Called on admin_init for backward compatibility
	 */
	public function migrate_unsupported_providers() {
		// Only run once per user
		$migration_done = get_user_meta( get_current_user_id(), 'clockwork_provider_migration_done', true );
		if ( $migration_done ) {
			return;
		}
		
		// Load settings helper
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-settings-helper.php';
		
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'aws';
		
		// Check if provider is unsupported (R2 or GCS)
		if ( $provider === 'cloudflare' || $provider === 'gcp' ) {
			// Store original provider for notice
			$original_provider = $provider;
			
			// Convert to AWS
			$settings['provider'] = 'aws';
			
			// Store original provider temporarily for notice
			$settings['_original_provider'] = $original_provider;
			
			// Remove R2 account ID if present
			if ( isset( $settings['r2_account_id'] ) ) {
				unset( $settings['r2_account_id'] );
			}
			
			// Save updated settings
			Clockwork_Offloader_Settings_Helper::update_settings( $settings );
			
			// Store original provider in transient for notice
			set_transient( 'clockwork_provider_migration_notice', $original_provider, DAY_IN_SECONDS );
			
			// Show admin notice
			add_action( 'admin_notices', array( $this, 'show_provider_migration_notice' ) );
			
			// Mark migration as done for this user
			update_user_meta( get_current_user_id(), 'clockwork_provider_migration_done', true );
		}
	}
	
	/**
	 * Show admin notice for provider migration
	 */
	public function show_provider_migration_notice() {
		$original_provider = get_transient( 'clockwork_provider_migration_notice' );
		if ( ! $original_provider ) {
			return;
		}
		
		$provider_name = ( $original_provider === 'cloudflare' ) ? 'Cloudflare R2' : 'Google Cloud Storage';
		
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Clockwork Offloader: Provider Migration', 'clockwork-offloader' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: Provider name (Cloudflare R2 or Google Cloud Storage) */
					esc_html__( 'Your storage provider has been automatically migrated from %s to AWS S3. Clockwork Offloader now only supports AWS S3 and DigitalOcean Spaces.', 'clockwork-offloader' ),
					esc_html( $provider_name )
				);
				?>
				<br>
				<?php esc_html_e( 'Please verify your AWS S3 credentials are correct in the settings.', 'clockwork-offloader' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Initialize the plugin
 */
function clockwork_offloader_init() {
	return Clockwork_Offloader::get_instance();
}

// Start the plugin
clockwork_offloader_init();

