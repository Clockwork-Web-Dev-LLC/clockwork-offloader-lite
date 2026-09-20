<?php
/**
 * Settings Helper
 *
 * Manages settings retrieval with multisite support
 * Supports both per-site and network-wide configuration
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clockwork_Offloader_Settings_Helper class
 */
class Clockwork_Offloader_Settings_Helper {

	/**
	 * Per-request settings cache, keyed by blog ID (settings can differ per site when
	 * network mode is off). get_settings() is called from every attachment-URL filter, so
	 * without this each image on a page cost an option read plus the wp-config.php scan.
	 *
	 * @var array<int, array>
	 */
	private static $settings_cache = array();

	/**
	 * Per-request cache of get_wp_config_credentials(). null = not yet computed.
	 *
	 * @var array|false|null
	 */
	private static $wp_config_creds_cache = null;

	/**
	 * Default settings shared by get_settings() and the network settings form.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'provider' => 'aws',
			'auto_offload' => false,
			'delete_after_upload' => false,
			'rewrite_urls' => false,
			's3_region' => 'us-east-1',
			's3_base_path' => '',
			'cdn_domain' => '',
			'queue_batch_size' => 10,
			'enable_throttle' => false,
			'throttle_rate' => 100,
			'show_media_library_status' => true,
			'development_mode' => false,
			'force_multisite_subsites' => true,
		);
	}

	/**
	 * Drop the per-request caches. Called after any settings write.
	 */
	public static function clear_cache() {
		self::$settings_cache = array();
		self::$wp_config_creds_cache = null;
	}

	/**
	 * Save settings to wherever get_settings() currently reads them from: the network
	 * option when network mode is on, otherwise the current site's option.
	 *
	 * @param array $settings Full settings array to save
	 * @return bool
	 */
	public static function update_settings( $settings ) {
		if ( is_multisite() && self::is_network_mode_enabled() ) {
			return self::update_network_settings( $settings );
		}

		return self::update_site_settings( $settings );
	}

	/**
	 * Whether the current user may change offloader configuration or run destructive
	 * operations (delete local files, remove objects from the bucket, drop tables).
	 *
	 * On a single site this is manage_options. On a multisite running in network mode the
	 * bucket and credentials are shared by every site, so a subsite administrator (who has
	 * manage_options on their own site) must not be able to wipe objects or read the secret
	 * key — only network admins may.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		if ( is_multisite() && self::is_network_mode_enabled() ) {
			return current_user_can( 'manage_network_options' );
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Get settings with multisite support
	 * Priority: wp-config constants (for provider only) > network settings > site settings > defaults
	 * Note: Region, bucket, and base_path are ALWAYS from database, never from wp-config
	 *
	 * @return array Settings array
	 */
	public static function get_settings() {
		$blog_id = is_multisite() ? get_current_blog_id() : 0;
		if ( isset( self::$settings_cache[ $blog_id ] ) ) {
			return self::$settings_cache[ $blog_id ];
		}

		// Run migration check on first load
		self::migrate_old_constants_to_database();

		// Default settings
		$defaults = self::get_defaults();

		// Get database settings
		$db_settings = array();
		if ( ! is_multisite() ) {
			$db_settings = get_option( 'clockwork_offloader_settings', array() );
		} else {
			$network_mode = self::is_network_mode_enabled();
			if ( $network_mode ) {
				$db_settings = self::get_network_settings();
			} else {
				$main_site_id = get_main_site_id();
				$is_main_site = ( get_current_blog_id() === (int) $main_site_id );

				if ( $is_main_site ) {
					$db_settings = self::get_site_settings();
				} else {
					$main_settings = get_blog_option( $main_site_id, 'clockwork_offloader_settings', array() );
					$force_multisite = isset( $main_settings['force_multisite_subsites'] )
						? ! empty( $main_settings['force_multisite_subsites'] )
						: true;

					$subsite_settings = self::get_site_settings();

					if ( $force_multisite || empty( $subsite_settings['s3_bucket'] ) ) {
						$db_settings = ! empty( $main_settings['s3_bucket'] ) ? $main_settings : $subsite_settings;
					} else {
						$db_settings = $subsite_settings;
					}
				}
			}
		}
		
		// Merge database settings with defaults
		$settings = wp_parse_args( $db_settings, $defaults );
		
		// Get provider from wp-config if available (but allow database override).
		// Check emptiness against the raw database settings, not the post-wp_parse_args()
		// merged $settings array — wp_parse_args() always backfills a non-empty 'aws'
		// default, which would make this override unreachable if checked against $settings.
		$wp_config_creds = self::get_wp_config_credentials();
		if ( $wp_config_creds && isset( $wp_config_creds['provider'] ) && empty( $db_settings['provider'] ) ) {
			$settings['provider'] = $wp_config_creds['provider'];
		}
		
		// Region, bucket, and base_path are ALWAYS from database, never from wp-config

		/**
		 * Filter the effective settings for the current site.
		 *
		 * @param array $settings Merged settings
		 * @param int   $blog_id  Current blog ID (0 on single site)
		 */
		$settings = apply_filters( 'clockwork_offloader_settings', $settings, $blog_id );

		self::$settings_cache[ $blog_id ] = $settings;

		return $settings;
	}
	
	/**
	 * Get network-wide settings
	 *
	 * @return array Network settings array
	 */
	public static function get_network_settings() {
		if ( ! is_multisite() ) {
			return array();
		}
		
		return get_site_option( 'clockwork_offloader_network_settings', array() );
	}
	
	/**
	 * Get site-specific settings
	 *
	 * @return array Site settings array
	 */
	public static function get_site_settings() {
		return get_option( 'clockwork_offloader_settings', array() );
	}
	
	/**
	 * Check if network-wide mode is enabled
	 *
	 * @return bool True if network mode is enabled
	 */
	public static function is_network_mode_enabled() {
		if ( ! is_multisite() ) {
			return false;
		}
		
		return (bool) get_site_option( 'clockwork_offloader_network_mode', false );
	}
	
	/**
	 * Check if the current site is a multisite subsite inheriting settings from the main site.
	 *
	 * @return bool True if inheriting settings from the main site
	 */
	public static function is_inherited_from_main() {
		if ( ! is_multisite() ) {
			return false;
		}

		$main_site_id = get_main_site_id();
		if ( get_current_blog_id() === (int) $main_site_id ) {
			return false;
		}

		if ( self::is_network_mode_enabled() ) {
			return false;
		}

		$main_settings = get_blog_option( $main_site_id, 'clockwork_offloader_settings', array() );
		$force_multisite = isset( $main_settings['force_multisite_subsites'] )
			? ! empty( $main_settings['force_multisite_subsites'] )
			: true;

		$subsite_settings = self::get_site_settings();

		return $force_multisite || empty( $subsite_settings['s3_bucket'] );
	}
	
	/**
	 * Enable or disable network-wide mode
	 *
	 * @param bool $enable True to enable, false to disable
	 * @return bool True on success, false on failure
	 */
	public static function set_network_mode( $enable ) {
		if ( ! is_multisite() ) {
			return false;
		}
		
		self::clear_cache();

		return update_site_option( 'clockwork_offloader_network_mode', (bool) $enable );
	}
	
	/**
	 * Update network-wide settings
	 *
	 * @param array $settings Settings array to save
	 * @return bool True on success, false on failure
	 */
	public static function update_network_settings( $settings ) {
		if ( ! is_multisite() ) {
			return false;
		}
		
		self::clear_cache();

		return update_site_option( 'clockwork_offloader_network_settings', $settings );
	}
	
	/**
	 * Update site-specific settings
	 *
	 * @param array $settings Settings array to save
	 * @return bool True on success, false on failure
	 */
	public static function update_site_settings( $settings ) {
		self::clear_cache();

		return update_option( 'clockwork_offloader_settings', $settings );
	}

	/**
	 * Get credentials from wp-config.php (serialized constant)
	 *
	 * Result is cached for the request: constants can't change mid-request, and the
	 * last-resort branch below reads wp-config.php from disk, which must not happen once
	 * per attachment URL.
	 *
	 * @return array|false Array with provider, access-key-id, secret-access-key, or false if not defined
	 */
	public static function get_wp_config_credentials() {
		if ( null === self::$wp_config_creds_cache ) {
			self::$wp_config_creds_cache = self::read_wp_config_credentials();
		}

		return self::$wp_config_creds_cache;
	}

	/**
	 * Uncached implementation of get_wp_config_credentials().
	 *
	 * @return array|false
	 */
	private static function read_wp_config_credentials() {
		// Check for new serialized constant first (with backward compatibility for old constant)
		if ( defined( 'CLOCKWORK_OFFLOADER_SETTINGS' ) ) {
			$settings = CLOCKWORK_OFFLOADER_SETTINGS;
			
			// Unserialize if it's a string
			if ( is_string( $settings ) ) {
				$unserialized = @unserialize( $settings );
				if ( $unserialized !== false && is_array( $unserialized ) ) {
					$settings = $unserialized;
				} else {
					// Try to continue with other methods
					$settings = null;
				}
			}
			
			// Extract credentials
			if ( is_array( $settings ) ) {
				$credentials = array();
				if ( isset( $settings['provider'] ) ) {
					$credentials['provider'] = $settings['provider'];
				}
				if ( isset( $settings['access-key-id'] ) ) {
					$credentials['access-key-id'] = $settings['access-key-id'];
				}
				if ( isset( $settings['secret-access-key'] ) ) {
					$credentials['secret-access-key'] = $settings['secret-access-key'];
				}
				
				// Return if we have at least the keys
				if ( ! empty( $credentials['access-key-id'] ) && ! empty( $credentials['secret-access-key'] ) ) {
					return $credentials;
				}
			}
		}
		
		if ( defined( 'CLOUDBOUND_OFFLOADER_SETTINGS' ) ) {
			// Backward compatibility: support old constant name
			$settings = CLOUDBOUND_OFFLOADER_SETTINGS;
			
			// Unserialize if it's a string
			if ( is_string( $settings ) ) {
				$unserialized = @unserialize( $settings );
				if ( $unserialized !== false && is_array( $unserialized ) ) {
					$settings = $unserialized;
				} else {
					return false;
				}
			}
			
			// Extract credentials
			if ( is_array( $settings ) ) {
				$credentials = array();
				if ( isset( $settings['provider'] ) ) {
					$credentials['provider'] = $settings['provider'];
				}
				if ( isset( $settings['access-key-id'] ) ) {
					$credentials['access-key-id'] = $settings['access-key-id'];
				}
				if ( isset( $settings['secret-access-key'] ) ) {
					$credentials['secret-access-key'] = $settings['secret-access-key'];
				}
				
				// Return if we have at least the keys
				if ( ! empty( $credentials['access-key-id'] ) && ! empty( $credentials['secret-access-key'] ) ) {
					return $credentials;
				}
			}
		}
		
		// Fallback: Check for old separate constants (for backward compatibility)
		if ( defined( 'CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOCKWORK_OFFLOADER_AWS_SECRET_KEY' ) ) {
			$credentials = array(
				'access-key-id' => CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY,
				'secret-access-key' => CLOCKWORK_OFFLOADER_AWS_SECRET_KEY,
			);
			
			// Get provider from constant or default to 'aws'
			if ( defined( 'CLOCKWORK_OFFLOADER_PROVIDER' ) ) {
				$credentials['provider'] = CLOCKWORK_OFFLOADER_PROVIDER;
			} else {
				$credentials['provider'] = 'aws';
			}
			
			return $credentials;
		} elseif ( defined( 'CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY' ) ) {
			// Backward compatibility: support old constant names
			$credentials = array(
				'access-key-id' => CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY,
				'secret-access-key' => CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY,
			);
			
			if ( defined( 'CLOUDBOUND_OFFLOADER_PROVIDER' ) ) {
				$credentials['provider'] = CLOUDBOUND_OFFLOADER_PROVIDER;
			} else {
				$credentials['provider'] = 'aws';
			}
			
			return $credentials;
		} elseif ( defined( 'AS3CF_SETTINGS' ) ) {
			// Support WP Offload Media's constant for migration
			$as3cf_settings = AS3CF_SETTINGS;
			if ( is_string( $as3cf_settings ) ) {
				$as3cf_settings = @unserialize( $as3cf_settings );
			}
			if ( is_array( $as3cf_settings ) && ! empty( $as3cf_settings['access-key-id'] ) && ! empty( $as3cf_settings['secret-access-key'] ) ) {
				return array(
					'provider' => isset( $as3cf_settings['provider'] ) ? $as3cf_settings['provider'] : 'aws',
					'access-key-id' => $as3cf_settings['access-key-id'],
					'secret-access-key' => $as3cf_settings['secret-access-key'],
				);
			}
		}
		
		// Fallback: Try reading wp-config.php directly to find CLOCKWORK_OFFLOADER_SETTINGS or CLOUDBOUND_OFFLOADER_SETTINGS
		// This handles cases where constants might not be loaded yet
		$possible_paths = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
			dirname( dirname( ABSPATH ) ) . '/wp-config.php',
		);
		
		foreach ( $possible_paths as $path ) {
			if ( file_exists( $path ) && is_readable( $path ) ) {
				$wp_config_content = @file_get_contents( $path );
				
				if ( $wp_config_content ) {
					// Check for CLOCKWORK_OFFLOADER_SETTINGS first, then CLOUDBOUND_OFFLOADER_SETTINGS for backward compatibility
					$array_content = null;
					if ( preg_match( "/define\s*\(\s*['\"]CLOCKWORK_OFFLOADER_SETTINGS['\"]\s*,\s*serialize\s*\(\s*array\s*\((.*?)\)\s*\)\s*\)/s", $wp_config_content, $matches ) ) {
						$array_content = $matches[1];
					} elseif ( preg_match( "/define\s*\(\s*['\"]CLOUDBOUND_OFFLOADER_SETTINGS['\"]\s*,\s*serialize\s*\(\s*array\s*\((.*?)\)\s*\)\s*\)/s", $wp_config_content, $matches ) ) {
						$array_content = $matches[1];
					}

					// Extract key-value pairs — applies to whichever constant name matched above
					if ( $array_content !== null && preg_match_all( "/(?:['\"])([^'\"]+)(?:['\"])\s*=>\s*(?:['\"])([^'\"]*)(?:['\"])/", $array_content, $kv_matches, PREG_SET_ORDER ) ) {
						$credentials = array();
						foreach ( $kv_matches as $kv_match ) {
							$key = $kv_match[1];
							$value = $kv_match[2];

							if ( $key === 'provider' ) {
								$credentials['provider'] = ( $value === 'do' ) ? 'digitalocean' : $value;
							} elseif ( $key === 'access-key-id' ) {
								$credentials['access-key-id'] = $value;
							} elseif ( $key === 'secret-access-key' ) {
								$credentials['secret-access-key'] = $value;
							}
						}

						if ( ! empty( $credentials['access-key-id'] ) && ! empty( $credentials['secret-access-key'] ) ) {
							if ( ! isset( $credentials['provider'] ) ) {
								$credentials['provider'] = 'aws';
							}
							return $credentials;
						}
					}
				}
				
				break; // Only check first readable file
			}
		}
		
		return false;
	}
	
	/**
	 * Migrate old constants to new serialized constant format
	 * This runs once on first load if old constants are detected
	 *
	 * @return bool True if migration occurred, false otherwise
	 */
	public static function migrate_old_constants_to_database() {
		// Check if migration already completed
		$migration_done = get_option( 'clockwork_offloader_migrated_old_constants', false );
		if ( $migration_done ) {
			return false;
		}
		
		$migrated = false;
		
		// Get settings directly from database to avoid infinite recursion
		// Don't call self::get_settings() here as it would cause recursion
		if ( is_multisite() && self::is_network_mode_enabled() ) {
			$settings = get_site_option( 'clockwork_offloader_network_settings', array() );
		} else {
			$settings = get_option( 'clockwork_offloader_settings', array() );
		}
		
		// Check for old separate constants (both new and old names for backward compatibility)
		$old_access_key = defined( 'CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY' ) ? CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY : ( defined( 'CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY' ) ? CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY : null );
		$old_secret_key = defined( 'CLOCKWORK_OFFLOADER_AWS_SECRET_KEY' ) ? CLOCKWORK_OFFLOADER_AWS_SECRET_KEY : ( defined( 'CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY' ) ? CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY : null );
		$old_region = defined( 'CLOCKWORK_OFFLOADER_AWS_REGION' ) ? CLOCKWORK_OFFLOADER_AWS_REGION : ( defined( 'CLOUDBOUND_OFFLOADER_AWS_REGION' ) ? CLOUDBOUND_OFFLOADER_AWS_REGION : null );
		$old_bucket = defined( 'CLOCKWORK_OFFLOADER_AWS_BUCKET' ) ? CLOCKWORK_OFFLOADER_AWS_BUCKET : ( defined( 'CLOUDBOUND_OFFLOADER_AWS_BUCKET' ) ? CLOUDBOUND_OFFLOADER_AWS_BUCKET : null );
		$old_provider = defined( 'CLOCKWORK_OFFLOADER_PROVIDER' ) ? CLOCKWORK_OFFLOADER_PROVIDER : ( defined( 'CLOUDBOUND_OFFLOADER_PROVIDER' ) ? CLOUDBOUND_OFFLOADER_PROVIDER : null );
		
		// Migrate region and bucket to database if they were in wp-config
		if ( $old_region && empty( $settings['s3_region'] ) ) {
			$settings['s3_region'] = $old_region;
			$migrated = true;
		}
		
		if ( $old_bucket && empty( $settings['s3_bucket'] ) ) {
			$settings['s3_bucket'] = $old_bucket;
			$migrated = true;
		}
		
		if ( $old_provider && empty( $settings['provider'] ) ) {
			$settings['provider'] = $old_provider;
			$migrated = true;
		}
		
		// Save migrated settings
		if ( $migrated ) {
			if ( is_multisite() && self::is_network_mode_enabled() ) {
				self::update_network_settings( $settings );
			} else {
				self::update_site_settings( $settings );
			}
			
			// Mark migration as complete
			update_option( 'clockwork_offloader_migrated_old_constants', true );
			
			// Show admin notice
			set_transient( 'clockwork_offloader_migration_notice', true, 3600 );
		}
		
		return $migrated;
	}
	
	/**
	 * Get settings source (for debugging/info)
	 *
	 * @return string Source: 'wp-config', 'network', 'site', or 'defaults'
	 */
	public static function get_settings_source() {
		// Check wp-config constants first (new serialized format, with backward compatibility)
		if ( defined( 'CLOCKWORK_OFFLOADER_SETTINGS' ) || defined( 'CLOUDBOUND_OFFLOADER_SETTINGS' ) ) {
			return 'wp-config';
		}
		
		// Fallback: Check old separate constants (both new and old names)
		if ( ( defined( 'CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOCKWORK_OFFLOADER_AWS_SECRET_KEY' ) ) ||
		     ( defined( 'CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY' ) ) ) {
			return 'wp-config';
		}
		
		if ( ! is_multisite() ) {
			$settings = get_option( 'clockwork_offloader_settings', array() );
			return ! empty( $settings ) ? 'site' : 'defaults';
		}
		
		$network_mode = self::is_network_mode_enabled();
		if ( $network_mode ) {
			$network_settings = self::get_network_settings();
			return ! empty( $network_settings ) ? 'network' : 'defaults';
		}
		
		$site_settings = self::get_site_settings();
		return ! empty( $site_settings ) ? 'site' : 'defaults';
	}
	
	/**
	 * Check if settings are configured (not just defaults)
	 *
	 * @return bool True if settings are configured
	 */
	public static function is_configured() {
		$settings = self::get_settings();
		
		// Check if credentials are set (either in wp-config or database)
		$has_credentials = false;
		
		// Check wp-config (new serialized format or old constants)
		$wp_config_creds = self::get_wp_config_credentials();
		if ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) ) {
			$has_credentials = true;
		} else {
			// Check database settings
			$has_credentials = ! empty( $settings['s3_access_key'] ) && ! empty( $settings['s3_secret_key'] );
		}
		
		return $has_credentials && ! empty( $settings['s3_bucket'] );
	}
}

