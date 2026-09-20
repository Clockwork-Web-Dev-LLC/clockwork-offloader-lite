<?php
/**
 * Offload Tracker
 *
 * Handles database tracking of offloaded files
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clockwork_Offloader_Tracker class
 */
class Clockwork_Offloader_Tracker {

	/**
	 * Schema version. Bump this and add an upgrade step in maybe_upgrade_table()
	 * whenever create_table()'s CREATE TABLE SQL changes in a way existing installs
	 * need migrating for (new column, new constraint, etc.).
	 */
	const DB_VERSION = '1.1';

	/**
	 * Get the table name
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'clockwork_offloads';
	}
	
	/**
	 * Create the database table
	 */
	public static function create_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'clockwork_offloads';
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			bucket VARCHAR(255) NOT NULL,
			s3_key VARCHAR(512) NOT NULL,
			original_path VARCHAR(512) NOT NULL,
			file_size BIGINT(20) UNSIGNED,
			offload_date DATETIME NOT NULL,
			status ENUM('offloaded', 'restored', 'deleted') DEFAULT 'offloaded',
			size_name VARCHAR(100) DEFAULT '',
			PRIMARY KEY (id),
			UNIQUE KEY attachment_size (attachment_id, size_name),
			INDEX attachment_id (attachment_id),
			INDEX status (status),
			INDEX size_name (size_name),
			INDEX attachment_status (attachment_id, status),
			INDEX attachment_size_status (attachment_id, size_name, status),
			INDEX status_offload_date (status, offload_date)
		) $charset_collate;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Migrate existing installs to the current schema.
	 *
	 * Runs on every page load (cheap: one option read) rather than only on plugin
	 * activation, because WordPress does NOT fire the activation hook when a plugin's
	 * files are updated in place (git pull, zip overwrite, etc.) without an explicit
	 * deactivate/reactivate — an existing site would otherwise be silently stuck on the
	 * old schema, including installs already carrying the duplicate rows this schema
	 * version's unique key is meant to prevent going forward.
	 */
	public static function maybe_upgrade_table() {
		if ( get_option( 'clockwork_offloader_db_version' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'clockwork_offloads';

		$table_name_escaped = esc_sql( $table_name );
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_escaped}'" ) === $table_name;

		if ( $table_exists ) {
			// dbDelta silently refuses to add a UNIQUE KEY if rows already violate it, so any
			// duplicate (attachment_id, size_name) rows left over from the pre-1.1 check-then-insert
			// race must be cleared first. Keep the highest id (most recent write) per pair.
			$wpdb->query(
				"DELETE t1 FROM $table_name t1
				INNER JOIN $table_name t2
				ON t1.attachment_id = t2.attachment_id
				AND t1.size_name = t2.size_name
				AND t1.id < t2.id"
			);
		}

		// Re-run dbDelta: creates the table on a fresh install, or adds the missing unique
		// key (now safe, since duplicates were just cleared) on an existing one.
		self::create_table();

		update_option( 'clockwork_offloader_db_version', self::DB_VERSION );
	}

	/**
	 * Check if table exists, create if it doesn't
	 */
	private function ensure_table_exists() {
		global $wpdb;
		$table_name = $this->get_table_name();
		// Use esc_sql for table name in SHOW TABLES query (safe - table name comes from $wpdb->prefix)
		$table_name_escaped = esc_sql( $table_name );
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_escaped}'" ) === $table_name;
		
		if ( ! $table_exists ) {
			self::create_table();
		}
	}
	
	/**
	 * Record an offload in the database
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $bucket S3 bucket name
	 * @param string $s3_key S3 key/path
	 * @param string $original_path Original local file path
	 * @param int    $file_size File size in bytes
	 * @param string $size_name Image size name (optional)
	 * @return int|false Insert ID or false on failure
	 */
	public function record_offload( $attachment_id, $bucket, $s3_key, $original_path, $file_size = null, $size_name = '' ) {
		// Ensure table exists before trying to record
		$this->ensure_table_exists();
		global $wpdb;
		
		$table_name = $this->get_table_name();

		// Atomic upsert keyed on the (attachment_id, size_name) unique constraint.
		// Avoids the race condition of a separate SELECT-then-INSERT/UPDATE (two concurrent
		// offload calls for the same attachment+size could previously both pass the "does it
		// exist" check and insert duplicate rows), and correctly re-activates a row that was
		// previously 'restored' or 'deleted' instead of inserting a second row for it.
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO $table_name (attachment_id, bucket, s3_key, original_path, file_size, offload_date, status, size_name)
			VALUES (%d, %s, %s, %s, %d, %s, 'offloaded', %s)
			ON DUPLICATE KEY UPDATE
				bucket = VALUES(bucket),
				s3_key = VALUES(s3_key),
				original_path = VALUES(original_path),
				file_size = VALUES(file_size),
				offload_date = VALUES(offload_date),
				status = 'offloaded'",
			$attachment_id,
			$bucket,
			$s3_key,
			$original_path,
			$file_size,
			current_time( 'mysql' ),
			$size_name
		) );

		// Clear cache on successful write
		if ( $result !== false ) {
			$this->clear_statistics_cache();
		}

		return $result;
	}
	
	/**
	 * Check if an attachment (or specific size) is offloaded
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $size_name Image size name (optional, empty for original)
	 * @return bool
	 */
	public function is_offloaded( $attachment_id, $size_name = '' ) {
		// Ensure table exists before trying to query
		$this->ensure_table_exists();
		global $wpdb;
		
		$table_name = $this->get_table_name();
		
		// Optimize: Use EXISTS with LIMIT 1 for better performance (stops at first match)
		// Uses composite index attachment_size_status
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM $table_name WHERE attachment_id = %d AND size_name = %s AND status = 'offloaded' LIMIT 1",
			$attachment_id,
			$size_name
		) );
		
		return $exists === '1';
	}
	
	/**
	 * Get offload record for an attachment
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $size_name Image size name (optional)
	 * @return object|null
	 */
	public function get_offload_record( $attachment_id, $size_name = '' ) {
		// Ensure table exists before trying to query
		$this->ensure_table_exists();
		global $wpdb;
		
		$table_name = $this->get_table_name();
		
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE attachment_id = %d AND size_name = %s AND status = 'offloaded' LIMIT 1",
			$attachment_id,
			$size_name
		) );
	}
	
	/**
	 * Get all offload records for an attachment
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array
	 */
	/**
	 * Get all offload records for an attachment
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array Array of offload records
	 */
	public function get_attachment_offloads( $attachment_id ) {
		// Ensure table exists before trying to query
		$this->ensure_table_exists();
		global $wpdb;
		
		$table_name = $this->get_table_name();
		
		// Get all records for this attachment, regardless of status
		// This ensures we can delete files even if status is 'restored' or 'deleted'
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE attachment_id = %d ORDER BY size_name",
			$attachment_id
		) );
	}
	
	/**
	 * Look up offloaded records by their local file paths, in one query.
	 *
	 * Used by the content rewriter: a hard-coded image URL in post_content is mapped back
	 * to the local path it was served from, then matched against original_path here.
	 *
	 * @param string[] $paths Absolute local paths
	 * @return array<string, object> original_path => record (only 'offloaded' rows)
	 */
	public function get_records_by_paths( array $paths ) {
		$paths = array_values( array_unique( array_filter( $paths ) ) );
		if ( empty( $paths ) ) {
			return array();
		}

		$this->ensure_table_exists();
		global $wpdb;

		$table_name = $this->get_table_name();
		$records = array();

		foreach ( array_chunk( $paths, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table_name WHERE status = 'offloaded' AND original_path IN ($placeholders)",
				$chunk
			) );
			foreach ( (array) $rows as $row ) {
				$records[ $row->original_path ] = $row;
			}
		}

		return $records;
	}

	/**
	 * Build the public URL for a stored record (CDN domain if set, else provider URL).
	 *
	 * @param object $record Row from the offloads table
	 * @return string
	 */
	public function get_record_url( $record ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();

		if ( ! empty( $settings['cdn_domain'] ) ) {
			return rtrim( $settings['cdn_domain'], '/' ) . '/' . $record->s3_key;
		}

		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-s3-service.php';
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		$region = ! empty( $credentials['region'] ) ? $credentials['region'] : 'us-east-1';
		$provider = ! empty( $credentials['provider'] ) ? $credentials['provider'] : 'aws';

		return Clockwork_Offloader_S3_Service::build_public_url( $record->bucket, $record->s3_key, $region, $provider );
	}

	/**
	 * Get S3 URL for an attachment
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $size_name Image size name (optional)
	 * @return string|false S3 URL or false if not offloaded
	 */
	public function get_s3_url( $attachment_id, $size_name = '' ) {
		$record = $this->get_offload_record( $attachment_id, $size_name );
		
		if ( ! $record ) {
			return false;
		}
		
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Use CDN domain if set, otherwise use S3 URL
		if ( ! empty( $settings['cdn_domain'] ) ) {
			$cdn_domain = rtrim( $settings['cdn_domain'], '/' );
			return $cdn_domain . '/' . $record->s3_key;
		}
		
		// Generate S3 URL - use same credential logic as S3 service to get correct region and provider
		// This ensures wp-config.php constants are checked first, then database settings
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-s3-service.php';
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		$region = ! empty( $credentials['region'] ) ? $credentials['region'] : 'us-east-1';
		$provider = ! empty( $credentials['provider'] ) ? $credentials['provider'] : 'aws';
		
		// Path-style for dotted AWS buckets, virtual-hosted otherwise (see build_public_url).
		return Clockwork_Offloader_S3_Service::build_public_url( $record->bucket, $record->s3_key, $region, $provider );
	}
	
	/**
	 * Update offload status
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $status New status
	 * @param string $size_name Image size name (optional)
	 * @return bool
	 */
	public function update_status( $attachment_id, $status, $size_name = '' ) {
		global $wpdb;
		
		$table_name = $this->get_table_name();
		
		return $wpdb->update(
			$table_name,
			array( 'status' => $status ),
			array(
				'attachment_id' => $attachment_id,
				'size_name' => $size_name,
			),
			array( '%s' ),
			array( '%d', '%s' )
		) !== false;
	}
	
	/**
	 * Delete offload record
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $size_name Image size name (optional)
	 * @return bool
	 */
	public function delete_record( $attachment_id, $size_name = '' ) {
		global $wpdb;
		
		$table_name = $this->get_table_name();
		
		$result = $wpdb->delete(
			$table_name,
			array(
				'attachment_id' => $attachment_id,
				'size_name' => $size_name,
			),
			array( '%d', '%s' )
		) !== false;
		
		// Clear statistics cache when records are deleted
		if ( $result ) {
			$this->clear_statistics_cache();
		}
		
		return $result;
	}
	
	/**
	 * Get statistics (with caching for performance)
	 *
	 * @param bool $force_refresh Force refresh of cached stats
	 * @return array
	 */
	public function get_statistics( $force_refresh = false ) {
		// Ensure table exists before trying to query
		$this->ensure_table_exists();
		
		// Cache statistics for 5 minutes to avoid expensive queries on large datasets
		$cache_key = 'clockwork_offloader_stats';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}
		
		global $wpdb;
		
		$table_name = $this->get_table_name();
		
		$stats = array(
			'total_offloaded' => 0,
			'total_size' => 0,
			'total_attachments' => 0,
			'offloaded_attachments' => 0,
		);
		
		// Get total attachments in WordPress (all attachments, not just offloaded)
		// Table name is safe (from $wpdb->posts), but escape for WordPress standards
		$posts_table_escaped = esc_sql( $wpdb->posts );
		
		$stats['total_attachments'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$posts_table_escaped}` WHERE post_type = %s AND post_status = %s",
				'attachment',
				'inherit'
			)
		);
		
		// Optimize: Use a single query with conditional aggregation for better performance
		// This uses the status index efficiently
		// IMPORTANT: Only count records for attachments that still exist in WordPress
		// This prevents orphaned records from inflating statistics
		// Security: Table name is from $wpdb->prefix (safe), status is hardcoded (safe), but use esc_sql for defense in depth
		$table_name_safe = esc_sql( $table_name );
		$posts_table = $wpdb->posts;
		$posts_table_safe = esc_sql( $posts_table );
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(o.id) as total_offloaded,
					COALESCE(SUM(o.file_size), 0) as total_size,
					COUNT(DISTINCT o.attachment_id) as offloaded_attachments
				FROM `{$table_name_safe}` o
				INNER JOIN `{$posts_table_safe}` p ON o.attachment_id = p.ID
				WHERE o.status = %s
				AND p.post_type = 'attachment'
				AND p.post_status = 'inherit'",
				'offloaded'
			),
			ARRAY_A
		);
		
		if ( $result ) {
			$stats['total_offloaded'] = (int) $result['total_offloaded'];
			$stats['total_size'] = (int) $result['total_size'];
			$stats['offloaded_attachments'] = (int) $result['offloaded_attachments'];
		}
		
		// Cache for 5 minutes
		set_transient( $cache_key, $stats, 300 );
		
		return $stats;
	}
	
	/**
	 * Clear statistics cache (call after bulk operations)
	 */
	public function clear_statistics_cache() {
		delete_transient( 'clockwork_offloader_stats' );
	}
	
	/**
	 * Clean up orphaned offload records (for attachments that no longer exist)
	 * This helps keep statistics accurate
	 *
	 * @return int Number of records deleted
	 */
	public function cleanup_orphaned_records() {
		global $wpdb;
		
		$table_name = $this->get_table_name();
		$posts_table = $wpdb->posts;
		
		// Delete records for attachments that no longer exist in WordPress
		// Table names are safe (from $wpdb->prefix and $wpdb->posts), but use esc_sql for defense in depth
		$table_name_safe = esc_sql( $table_name );
		$posts_table_safe = esc_sql( $posts_table );
		$deleted = $wpdb->query(
			"DELETE o FROM `{$table_name_safe}` o
			LEFT JOIN `{$posts_table_safe}` p ON o.attachment_id = p.ID
			WHERE p.ID IS NULL"
		);
		
		if ( $deleted > 0 ) {
			$this->clear_statistics_cache();
		}
		
		return (int) $deleted;
	}
}

