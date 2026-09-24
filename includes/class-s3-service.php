<?php
/**
 * S3 Service
 *
 * Handles S3 uploads and downloads using AWS SDK v3
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clockwork_Offloader_S3_Service class
 */
class Clockwork_Offloader_S3_Service {
	
	/**
	 * S3 client instance
	 *
	 * @var Aws\S3\S3Client|null
	 */
	private $s3_client = null;
	
	/**
	 * Get provider (AWS or Digital Ocean Spaces)
	 *
	 * @return string Provider name ('aws' or 'digitalocean')
	 */
	public function get_provider() {
		// Check for constant first (with backward compatibility)
		if ( defined( 'CLOCKWORK_OFFLOADER_PROVIDER' ) ) {
			$provider = CLOCKWORK_OFFLOADER_PROVIDER;
			if ( in_array( $provider, array( 'aws', 'digitalocean', 'cloudflare_r2', 'wasabi', 'backblaze', 'minio', 'custom' ), true ) ) {
				return $provider;
			}
		} elseif ( defined( 'CLOUDBOUND_OFFLOADER_PROVIDER' ) ) {
			// Backward compatibility
			$provider = CLOUDBOUND_OFFLOADER_PROVIDER;
			if ( in_array( $provider, array( 'aws', 'digitalocean', 'cloudflare_r2', 'wasabi', 'backblaze', 'minio', 'custom' ), true ) ) {
				return $provider;
			}
		}
		
		// Get from database settings (with multisite support)
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'aws';
		
		// Validate and default to 'aws' if invalid
		if ( ! in_array( $provider, array( 'aws', 'digitalocean', 'cloudflare_r2', 'wasabi', 'backblaze', 'minio', 'custom' ), true ) ) {
			return 'aws';
		}
		
		return $provider;
	}
	
	/**
	 * Get S3 credentials from wp-config.php or database
	 *
	 * @return array Array with 'access_key', 'secret_key', 'region', 'bucket', 'account_id', 'source', 'provider'
	 */
	public function get_credentials() {
		// Get provider
		$provider = $this->get_provider();
		
		// Check for new serialized wp-config.php constant first (CLOCKWORK_OFFLOADER_SETTINGS, with backward compatibility)
		$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
		$access_key = null;
		$secret_key = null;
		$source = 'database';
		
		if ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) ) {
			// New serialized constant format
			$access_key = $wp_config_creds['access-key-id'];
			$secret_key = $wp_config_creds['secret-access-key'];
			$provider = ! empty( $wp_config_creds['provider'] ) ? $wp_config_creds['provider'] : $provider;
			$source = 'wp-config';
		} else {
			// Fallback to old separate constants for backward compatibility
			if ( defined( 'CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOCKWORK_OFFLOADER_AWS_SECRET_KEY' ) ) {
				$access_key = CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY;
				$secret_key = CLOCKWORK_OFFLOADER_AWS_SECRET_KEY;
			} elseif ( defined( 'CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY' ) ) {
				// Backward compatibility
				$access_key = CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY;
				$secret_key = CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY;
		$source = 'wp-config';
			}
		}
		
		// Get settings (with multisite support) - always get region and bucket from database
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$region = ! empty( $settings['s3_region'] ) ? $settings['s3_region'] : ( $provider === 'digitalocean' ? 'nyc3' : 'us-east-1' );
		$bucket = ! empty( $settings['s3_bucket'] ) ? $settings['s3_bucket'] : '';
		
		// Fall back to database for credentials if wp-config.php constants not defined
		if ( empty( $access_key ) || empty( $secret_key ) ) {
			$access_key = ! empty( $settings['s3_access_key'] ) ? $settings['s3_access_key'] : '';
			$secret_key = ! empty( $settings['s3_secret_key'] ) ? $settings['s3_secret_key'] : '';
			$source = 'database';
		}
		
		$custom_endpoint = '';
		if ( ! empty( $wp_config_creds['endpoint'] ) ) {
			$custom_endpoint = $wp_config_creds['endpoint'];
		} elseif ( defined( 'CLOCKWORK_OFFLOADER_ENDPOINT' ) ) {
			$custom_endpoint = CLOCKWORK_OFFLOADER_ENDPOINT;
		} elseif ( ! empty( $settings['s3_custom_endpoint'] ) ) {
			$custom_endpoint = $settings['s3_custom_endpoint'];
		}

		if ( empty( $custom_endpoint ) ) {
			if ( $provider === 'digitalocean' ) {
				$custom_endpoint = 'https://' . $region . '.digitaloceanspaces.com';
			} elseif ( $provider === 'wasabi' ) {
				$custom_endpoint = 'https://s3.' . $region . '.wasabisys.com';
			} elseif ( $provider === 'backblaze' ) {
				$custom_endpoint = 'https://s3.' . $region . '.backblazeb2.com';
			}
		}

		return array(
			'access_key' => $access_key,
			'secret_key' => $secret_key,
			'region' => $region,
			'bucket' => $bucket,
			'source' => $source,
			'provider' => $provider,
			'endpoint' => $custom_endpoint,
		);
	}
	
	/**
	 * Check if credentials are defined in wp-config.php
	 *
	 * @return bool
	 */
	public function is_using_wp_config() {
		// Check for new serialized constant
		if ( defined( 'CLOCKWORK_OFFLOADER_SETTINGS' ) || defined( 'CLOUDBOUND_OFFLOADER_SETTINGS' ) ) {
			$wp_config_creds = Clockwork_Offloader_Settings_Helper::get_wp_config_credentials();
			return ( $wp_config_creds && ! empty( $wp_config_creds['access-key-id'] ) && ! empty( $wp_config_creds['secret-access-key'] ) );
		}
		
		// Fallback: Check old separate constants
		return ( ( defined( 'CLOCKWORK_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOCKWORK_OFFLOADER_AWS_SECRET_KEY' ) ) ||
		         ( defined( 'CLOUDBOUND_OFFLOADER_AWS_ACCESS_KEY' ) && defined( 'CLOUDBOUND_OFFLOADER_AWS_SECRET_KEY' ) ) );
	}
	
	/**
	 * Get S3 client instance
	 *
	 * @return Aws\S3\S3Client|WP_Error
	 */
	public function get_client() {
		if ( null !== $this->s3_client ) {
			return $this->s3_client;
		}
		
		// Check if AWS SDK is available
		if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
			return new WP_Error( 'aws_sdk_missing', __( 'AWS SDK is not installed. Please run composer install.', 'clockwork-offloader' ) );
		}
		
		$credentials = $this->get_credentials();
		
		if ( empty( $credentials['access_key'] ) || empty( $credentials['secret_key'] ) ) {
			return new WP_Error( 's3_not_configured', __( 'S3 credentials are not configured.', 'clockwork-offloader' ) );
		}
		
		try {
			$config = array(
				'version' => 'latest',
				'region' => $credentials['region'],
				'credentials' => array(
					'key' => $credentials['access_key'],
					'secret' => $credentials['secret_key'],
				),
			);
			
			// Configure endpoint for custom endpoint, non-AWS providers, or Digital Ocean Spaces
			if ( ! empty( $credentials['endpoint'] ) ) {
				$config['endpoint'] = $credentials['endpoint'];
				$config['use_path_style_endpoint'] = true;
			} elseif ( $credentials['provider'] === 'digitalocean' ) {
				$config['endpoint'] = 'https://' . $credentials['region'] . '.digitaloceanspaces.com';
				$config['use_path_style_endpoint'] = true;
			}
			
			$this->s3_client = new Aws\S3\S3Client( $config );
			
			return $this->s3_client;
		} catch ( Exception $e ) {
			return new WP_Error( 's3_client_error', $e->getMessage() );
		}
	}
	
	/**
	 * Upload a file to S3
	 *
	 * @param string $file_path Local file path
	 * @param int    $attachment_id Attachment ID
	 * @param string $size_name Image size name (optional)
	 * @return array|WP_Error Array with bucket and s3_key, or WP_Error on failure
	 */
	public function upload_file( $file_path, $attachment_id, $size_name = '', $custom_key = null ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File does not exist.', 'clockwork-offloader' ) );
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$credentials = $this->get_credentials();
		$bucket = $credentials['bucket'];

		if ( empty( $bucket ) ) {
			return new WP_Error( 'bucket_not_set', __( 'S3 bucket is not configured.', 'clockwork-offloader' ) );
		}

		// Generate S3 key — or use the caller-supplied key verbatim (e.g. when uploading a
		// file that doesn't live under the uploads dir, where generate_s3_key()'s relative-path
		// logic can't produce a sensible key).
		$s3_key = $custom_key !== null ? $custom_key : $this->generate_s3_key( $file_path, $attachment_id, $size_name );
		
		// Get file info
		$file_info = wp_check_filetype( $file_path );
		$content_type = ! empty( $file_info['type'] ) ? $file_info['type'] : 'application/octet-stream';
		
		try {
			// Upload file
			// Note: ACL is not included as modern S3 buckets often have Object Ownership
			// set to "Bucket owner enforced" which disables ACLs. Public access should
			// be controlled via bucket policies instead.
			$upload_params = array(
				'Bucket' => $bucket,
				'Key' => $s3_key,
				'SourceFile' => $file_path,
				'ContentType' => $content_type,
			);
			
			$result = $client->putObject( $upload_params );
			
			// Construct URL based on provider
			$url = $this->construct_file_url( $bucket, $s3_key, $credentials['region'], $credentials['provider'], $credentials['endpoint'] ?? '' );
			
			return array(
				'bucket' => $bucket,
				's3_key' => $s3_key,
				'url' => $url,
			);
		} catch ( Exception $e ) {
			// If the error is about ACLs, try again without ACL
			if ( strpos( $e->getMessage(), 'AccessControlListNotSupported' ) !== false || 
			     strpos( $e->getMessage(), 'does not allow ACLs' ) !== false ) {
				try {
					// Retry without ACL
					$upload_params = array(
						'Bucket' => $bucket,
						'Key' => $s3_key,
						'SourceFile' => $file_path,
						'ContentType' => $content_type,
					);
					
					$result = $client->putObject( $upload_params );
					
					// Construct URL based on provider
					$url = $this->construct_file_url( $bucket, $s3_key, $credentials['region'], $credentials['provider'], $credentials['endpoint'] ?? '' );
					
					return array(
						'bucket' => $bucket,
						's3_key' => $s3_key,
						'url' => $url,
					);
				} catch ( Exception $retry_e ) {
					return new WP_Error( 'upload_failed', $retry_e->getMessage() );
				}
			}
			
			return new WP_Error( 'upload_failed', $e->getMessage() );
		}
	}
	
	/**
	 * Download a file from S3
	 *
	 * @param string $s3_key S3 key/path
	 * @param string $local_path Local file path to save to
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function download_file( $s3_key, $local_path ) {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		
		$credentials = $this->get_credentials();
		$bucket = $credentials['bucket'];
		
		if ( empty( $bucket ) ) {
			return new WP_Error( 'bucket_not_set', __( 'S3 bucket is not configured.', 'clockwork-offloader' ) );
		}
		
		// Create directory if it doesn't exist
		$dir = dirname( $local_path );
		if ( ! file_exists( $dir ) ) {
			$mkdir_result = wp_mkdir_p( $dir );
			if ( ! $mkdir_result ) {
				/* translators: %s: Directory path */
				return new WP_Error( 'directory_creation_failed', sprintf( __( 'Failed to create directory: %s', 'clockwork-offloader' ), $dir ) );
			}
		}
		
		// Check if directory is writable
		if ( ! wp_is_writable( $dir ) ) {
			/* translators: %s: Directory path */
			return new WP_Error( 'directory_not_writable', sprintf( __( 'Directory is not writable: %s', 'clockwork-offloader' ), $dir ) );
		}
		
		try {
			$result = $client->getObject( array(
				'Bucket' => $bucket,
				'Key' => $s3_key,
				'SaveAs' => $local_path,
			) );
			
			// Verify file was actually downloaded
			if ( ! file_exists( $local_path ) ) {
				return new WP_Error( 'download_verification_failed', __( 'File download completed but file does not exist locally.', 'clockwork-offloader' ) );
			}
			
			return true;
		} catch ( Exception $e ) {
			/* translators: %s: Error message from S3 */
			return new WP_Error( 'download_failed', sprintf( __( 'S3 download failed: %s', 'clockwork-offloader' ), $e->getMessage() ) );
		}
	}
	
	/**
	 * Delete a file from S3
	 *
	 * @param string $s3_key S3 key/path
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_file( $s3_key ) {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		
		$credentials = $this->get_credentials();
		$bucket = $credentials['bucket'];
		
		if ( empty( $bucket ) ) {
			return new WP_Error( 'bucket_not_set', __( 'S3 bucket is not configured.', 'clockwork-offloader' ) );
		}
		
		try {
			$result = $client->deleteObject( array(
				'Bucket' => $bucket,
				'Key' => $s3_key,
			) );
			
			// Check the result status code
			$status_code = $result->get( '@metadata' )['statusCode'] ?? null;
			
			if ( $status_code === 204 || $status_code === 200 ) {
				// Fire action for CloudFront invalidation (Pro feature)
				do_action( 'clockwork_offloader_file_deleted_from_s3', $s3_key, $bucket );
				return true;
			} else {
				/* translators: %d: HTTP status code */
				return new WP_Error( 'delete_failed', sprintf( __( 'Unexpected response code: %d', 'clockwork-offloader' ), $status_code ) );
			}
		} catch ( Exception $e ) {
			$error_message = $e->getMessage();
			
			// Provide more specific error messages
			if ( strpos( $error_message, '403' ) !== false || strpos( $error_message, 'Forbidden' ) !== false || strpos( $error_message, 'AccessDenied' ) !== false ) {
				return new WP_Error( 'delete_permission_denied', __( 'Permission denied. Your AWS credentials do not have delete permissions for this bucket.', 'clockwork-offloader' ) );
			} elseif ( strpos( $error_message, '404' ) !== false || strpos( $error_message, 'NoSuchKey' ) !== false ) {
				return new WP_Error( 'delete_file_not_found', __( 'File not found in S3. It may have already been deleted.', 'clockwork-offloader' ) );
			}
			
			/* translators: %s: Error message from S3 */
			return new WP_Error( 'delete_failed', sprintf( __( 'S3 delete failed: %s', 'clockwork-offloader' ), $error_message ) );
		}
	}

	/**
	 * Create a presigned expiring URL for private S3 access.
	 *
	 * @param string      $s3_key             Object key in S3.
	 * @param int         $expires_in_seconds TTL in seconds (default 900 / 15 minutes).
	 * @param string|null $bucket             Optional bucket override.
	 * @return string|WP_Error Presigned URL or WP_Error.
	 */
	public function create_presigned_url( $s3_key, $expires_in_seconds = 900, $bucket = null ) {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		if ( empty( $bucket ) ) {
			$credentials = $this->get_credentials();
			$bucket = $credentials['bucket'];
		}

		if ( empty( $bucket ) || empty( $s3_key ) ) {
			return new WP_Error( 'invalid_presigned_params', __( 'Bucket and S3 key are required.', 'clockwork-offloader' ) );
		}

		try {
			$cmd = $client->getCommand( 'GetObject', array(
				'Bucket' => $bucket,
				'Key'    => ltrim( (string) $s3_key, '/' ),
			) );

			$request = $client->createPresignedRequest( $cmd, '+' . (int) $expires_in_seconds . ' seconds' );
			return (string) $request->getUri();
		} catch ( Exception $e ) {
			return new WP_Error( 'presigned_url_error', $e->getMessage() );
		}
	}
	
	/**
	 * List objects in S3 with a prefix
	 *
	 * @param string $prefix Prefix to filter objects
	 * @return array|WP_Error Array of object keys, or WP_Error on failure
	 */
	public function list_objects( $prefix = '' ) {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		
		$credentials = $this->get_credentials();
		$bucket = $credentials['bucket'];
		
		if ( empty( $bucket ) ) {
			return new WP_Error( 'bucket_not_set', __( 'S3 bucket is not configured.', 'clockwork-offloader' ) );
		}
		
		$objects = array();
		$continuation_token = null;
		
		try {
			do {
				$params = array(
					'Bucket' => $bucket,
				);
				
				if ( ! empty( $prefix ) ) {
					$params['Prefix'] = $prefix;
				}
				
				if ( $continuation_token ) {
					$params['ContinuationToken'] = $continuation_token;
				}
				
				$result = $client->listObjectsV2( $params );
				
				if ( isset( $result['Contents'] ) ) {
					foreach ( $result['Contents'] as $object ) {
						$objects[] = $object['Key'];
					}
				}
				
				$continuation_token = $result['NextContinuationToken'] ?? null;
			} while ( $continuation_token );
			
			return $objects;
		} catch ( Exception $e ) {
			/* translators: %s: Error message from S3 */
			return new WP_Error( 'list_failed', sprintf( __( 'Failed to list S3 objects: %s', 'clockwork-offloader' ), $e->getMessage() ) );
		}
	}
	
	/**
	 * Check if file exists in S3
	 *
	 * @param string $s3_key S3 key/path
	 * @return bool
	 */
	public function file_exists( $s3_key ) {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return false;
		}
		
		$credentials = $this->get_credentials();
		$bucket = $credentials['bucket'];
		
		if ( empty( $bucket ) ) {
			return false;
		}
		
		try {
			return $client->doesObjectExist( $bucket, $s3_key );
		} catch ( Exception $e ) {
			// doesObjectExist() already resolves a genuine 404/NoSuchKey to a clean `false`
			// return internally and only throws for other failures (network timeout, API
			// throttling, permissions). Catching those here and returning false too — the
			// existing behavior — means callers (e.g. the migration verifier) can't tell a
			// real "missing" from a transient error. Keep the boolean contract (callers don't
			// expect exceptions), but log distinctly so a transient blip isn't silently
			// indistinguishable from an actually-missing file in diagnostics.
			if ( function_exists( 'error_log' ) ) {
				error_log( sprintf( 'Clockwork Offloader: file_exists() check failed for S3 key "%s" due to a non-404 error: %s', $s3_key, $e->getMessage() ) );
			}
			return false;
		}
	}
	
	/**
	 * List all S3 buckets
	 *
	 * @return array|WP_Error Array of bucket names or WP_Error on failure
	 */
	public function list_buckets() {
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		
		try {
			$result = $client->listBuckets();
			$buckets = array();
			
			if ( isset( $result['Buckets'] ) && is_array( $result['Buckets'] ) ) {
				foreach ( $result['Buckets'] as $bucket ) {
					if ( isset( $bucket['Name'] ) ) {
						$buckets[] = $bucket['Name'];
					}
				}
			}
			
			return $buckets;
		} catch ( Exception $e ) {
			return new WP_Error( 'list_buckets_failed', $e->getMessage() );
		}
	}
	
	/**
	 * Construct file URL based on provider
	 *
	 * @param string $bucket Bucket/space name
	 * @param string $s3_key S3 key/path
	 * @param string $region Region
	 * @param string $provider Provider ('aws' or 'digitalocean')
	 * @return string File URL
	 */
	private function construct_file_url( $bucket, $s3_key, $region, $provider, $custom_endpoint = '' ) {
		return self::build_public_url( $bucket, $s3_key, $region, $provider, $custom_endpoint );
	}

	/**
	 * Build the public HTTPS URL for an object.
	 *
	 * This is the single place the URL shape is decided; every other class must
	 * call it rather than concatenating hostnames itself.
	 *
	 * AWS: bucket names that contain a dot (e.g. "example.org") cannot use the
	 * virtual-hosted form {bucket}.s3.{region}.amazonaws.com over HTTPS, because
	 * Amazon's wildcard certificate only covers a single label — browsers show
	 * ERR_CERT_COMMON_NAME_INVALID. Those buckets get the path-style form
	 * s3.{region}.amazonaws.com/{bucket}/{key} instead, which is what WP Offload
	 * Media does too. Dot-free buckets keep the virtual-hosted form.
	 *
	 * DigitalOcean Spaces names cannot contain dots, so they are always
	 * {space}.{region}.digitaloceanspaces.com/{key}.
	 *
	 * @param string $bucket   Bucket / Space name.
	 * @param string $s3_key   Object key (may be empty to get the base URL).
	 * @param string $region   Region code.
	 * @param string $provider 'aws' or 'digitalocean'.
	 * @return string URL without a trailing slash when $s3_key is empty.
	 */
	public static function build_public_url( $bucket, $s3_key, $region, $provider = 'aws', $custom_endpoint = '' ) {
		$s3_key = ltrim( (string) $s3_key, '/' );
		$region = $region ? $region : 'us-east-1';
		$suffix = $s3_key !== '' ? '/' . $s3_key : '';

		if ( ! empty( $custom_endpoint ) ) {
			$clean = rtrim( $custom_endpoint, '/' );
			if ( strpos( $clean, '/' . $bucket ) !== false || strpos( $clean, $bucket . '.' ) !== false ) {
				return $clean . $suffix;
			}
			return $clean . '/' . $bucket . $suffix;
		}

		if ( $provider === 'digitalocean' ) {
			return 'https://' . $bucket . '.' . $region . '.digitaloceanspaces.com' . $suffix;
		}

		if ( $provider === 'wasabi' ) {
			return 'https://s3.' . $region . '.wasabisys.com/' . $bucket . $suffix;
		}

		if ( $provider === 'backblaze' ) {
			return 'https://s3.' . $region . '.backblazeb2.com/' . $bucket . $suffix;
		}

		if ( self::bucket_requires_path_style( $bucket ) ) {
			return 'https://s3.' . $region . '.amazonaws.com/' . $bucket . $suffix;
		}

		return 'https://' . $bucket . '.s3.' . $region . '.amazonaws.com' . $suffix;
	}

	/**
	 * Whether an AWS bucket must be addressed path-style over HTTPS.
	 *
	 * @param string $bucket Bucket name.
	 * @return bool
	 */
	public static function bucket_requires_path_style( $bucket ) {
		return strpos( (string) $bucket, '.' ) !== false;
	}
	
	/**
	 * Check whether objects written to the bucket are publicly readable.
	 *
	 * Uploads a tiny marker object with the same (ACL-less) request the plugin
	 * uses for media, fetches its public URL anonymously, then deletes it.
	 * Buckets created since April 2023 default to "Bucket owner enforced"
	 * ownership, which disables ACLs, so public delivery needs a bucket policy;
	 * without one every offloaded file returns 403 even though uploads succeed.
	 *
	 * @param string $access_key Access key.
	 * @param string $secret_key Secret key.
	 * @param string $bucket     Bucket name.
	 * @param string $region     Region.
	 * @param string $provider   'aws' or 'digitalocean'.
	 * @return array {
	 *   @type bool|null $public       True if anonymously readable, false if not, null if the probe itself failed.
	 *   @type int       $status       HTTP status of the anonymous request (0 if none).
	 *   @type string    $url          Public URL that was probed.
	 *   @type string    $error        Error text when $public is null.
	 *   @type string    $policy_json  Suggested bucket policy (AWS only).
	 * }
	 */
	public function probe_public_read( $access_key, $secret_key, $bucket, $region, $provider = 'aws', $endpoint = '' ) {
		$result = array(
			'public'      => null,
			'status'      => 0,
			'url'         => '',
			'error'       => '',
			'policy_json' => '',
		);

		if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
			$result['error'] = __( 'AWS SDK is not installed.', 'clockwork-offloader' );
			return $result;
		}

		$config = array(
			'version'     => 'latest',
			'region'      => $region,
			'credentials' => array( 'key' => $access_key, 'secret' => $secret_key ),
		);
		if ( ! empty( $endpoint ) ) {
			$config['endpoint']                = $endpoint;
			$config['use_path_style_endpoint'] = true;
		} elseif ( $provider === 'digitalocean' ) {
			$config['endpoint']                = 'https://' . $region . '.digitaloceanspaces.com';
			$config['use_path_style_endpoint'] = true;
		} elseif ( $provider === 'wasabi' ) {
			$config['endpoint']                = 'https://s3.' . $region . '.wasabisys.com';
			$config['use_path_style_endpoint'] = true;
		} elseif ( $provider === 'backblaze' ) {
			$config['endpoint']                = 'https://s3.' . $region . '.backblazeb2.com';
			$config['use_path_style_endpoint'] = true;
		}

		$key           = 'clockwork-offloader-public-read-test-' . wp_generate_password( 12, false ) . '.txt';
		$result['url'] = self::build_public_url( $bucket, $key, $region, $provider, $endpoint );

		try {
			$client = new Aws\S3\S3Client( $config );
			$client->putObject( array(
				'Bucket'      => $bucket,
				'Key'         => $key,
				'Body'        => 'clockwork offloader public-read probe',
				'ContentType' => 'text/plain',
			) );

			$response = wp_remote_head( $result['url'], array( 'timeout' => 10, 'redirection' => 0 ) );
			if ( is_wp_error( $response ) ) {
				$result['error'] = $response->get_error_message();
			} else {
				$result['status'] = (int) wp_remote_retrieve_response_code( $response );
				$result['public'] = ( $result['status'] === 200 );
			}

			try {
				$client->deleteObject( array( 'Bucket' => $bucket, 'Key' => $key ) );
			} catch ( Exception $cleanup_exception ) {
				// Leaving a 40-byte text file behind is not worth failing the probe over.
			}
		} catch ( Exception $e ) {
			$result['error'] = $e->getMessage();
		}

		if ( $provider !== 'digitalocean' ) {
			$result['policy_json'] = wp_json_encode( array(
				'Version'   => '2012-10-17',
				'Statement' => array(
					array(
						'Sid'       => 'ClockworkOffloaderPublicRead',
						'Effect'    => 'Allow',
						'Principal' => '*',
						'Action'    => 's3:GetObject',
						'Resource'  => 'arn:aws:s3:::' . $bucket . '/*',
					),
				),
			), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		return $result;
	}

	/**
	 * Remember whether the configured access key may call ListBuckets.
	 *
	 * Bucket-scoped IAM users (no s3:ListAllMyBuckets) are denied ListBuckets even
	 * though they are otherwise valid, so the setup wizard uses this to decide
	 * whether "Browse existing buckets" can work at all.
	 *
	 * @param bool $can_list True if ListBuckets succeeded, false if it was denied.
	 */
	public static function remember_can_list_buckets( $can_list ) {
		set_transient( 'clockwork_offloader_can_list_buckets', $can_list ? 'yes' : 'no', DAY_IN_SECONDS );
	}

	/**
	 * Whether the access key is known to be allowed to call ListBuckets.
	 *
	 * Defaults to true when nothing has been recorded yet.
	 *
	 * @return bool
	 */
	public static function can_list_buckets() {
		return get_transient( 'clockwork_offloader_can_list_buckets' ) !== 'no';
	}

	/**
	 * Test S3 connection
	 *
	 * @param string $access_key Optional access key for testing (overrides stored credentials)
	 * @param string $secret_key Optional secret key for testing (overrides stored credentials)
	 * @param string $bucket Optional bucket name for testing (overrides stored credentials)
	 * @param string $region Optional region for testing (overrides stored credentials)
	 * @param string $provider Optional provider for testing (overrides stored provider)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function test_connection( $access_key = null, $secret_key = null, $bucket = null, $region = null, $provider = null, $account_id = null, $endpoint = null ) {
		// If credentials provided, create temporary client
		if ( $access_key !== null && $secret_key !== null ) {
			if ( empty( $access_key ) || empty( $secret_key ) ) {
				return new WP_Error( 'credentials_required', __( 'Access Key and Secret Key are required.', 'clockwork-offloader' ) );
			}
			
			// Check if AWS SDK is available
			if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
				return new WP_Error( 'aws_sdk_missing', __( 'AWS SDK is not installed. Please run composer install.', 'clockwork-offloader' ) );
			}
			
			// Get provider (use provided or default to stored)
			if ( $provider === null ) {
				$provider = $this->get_provider();
			}
			
			$test_region = $region ? $region : ( $provider === 'digitalocean' ? 'nyc3' : 'us-east-1' );
			
			try {
				$config = array(
					'version' => 'latest',
					'region' => $test_region,
					'credentials' => array(
						'key' => $access_key,
						'secret' => $secret_key,
					),
				);
				
				if ( ! empty( $endpoint ) ) {
					$config['endpoint'] = $endpoint;
					$config['use_path_style_endpoint'] = true;
				} elseif ( $provider === 'digitalocean' ) {
					$config['endpoint'] = 'https://' . $test_region . '.digitaloceanspaces.com';
					$config['use_path_style_endpoint'] = true;
				} elseif ( $provider === 'wasabi' ) {
					$config['endpoint'] = 'https://s3.' . $test_region . '.wasabisys.com';
					$config['use_path_style_endpoint'] = true;
				} elseif ( $provider === 'backblaze' ) {
					$config['endpoint'] = 'https://s3.' . $test_region . '.backblazeb2.com';
					$config['use_path_style_endpoint'] = true;
				}
				
				$test_client = new Aws\S3\S3Client( $config );
				
				// Test by listing buckets (doesn't require bucket parameter).
				// A bucket-scoped IAM policy (no s3:ListAllMyBuckets) returns
				// AccessDenied here even though the credentials are valid, so
				// treat that as "authenticated, not authorised to list" and fall
				// through to the bucket-level check below. Genuine credential
				// failures (InvalidAccessKeyId, SignatureDoesNotMatch) still throw.
				try {
					$test_client->listBuckets();
					self::remember_can_list_buckets( true );
				} catch ( \Aws\S3\Exception\S3Exception $list_exception ) {
					if ( $list_exception->getAwsErrorCode() !== 'AccessDenied' ) {
						throw $list_exception;
					}
					self::remember_can_list_buckets( false );
				}
				
				// If bucket provided, test bucket access
				if ( $bucket ) {
					$test_client->listObjects( array(
						'Bucket' => $bucket,
						'MaxKeys' => 1,
					) );
				}
				
				return true;
			} catch ( Exception $e ) {
				$error_message = $e->getMessage();
				
				// Handle permanent redirect (region mismatch)
				if ( stripos( $error_message, 'permanent redirect' ) !== false || 
					 stripos( $error_message, 'PermanentRedirect' ) !== false ) {
					// Try to extract region from exception if it's a PermanentRedirectException
					$correct_region = null;
					if ( class_exists( 'Aws\S3\Exception\PermanentRedirectException' ) && $e instanceof \Aws\S3\Exception\PermanentRedirectException ) {
						$result = $e->getResult();
						if ( $result && isset( $result['@metadata']['headers']['x-amz-bucket-region'] ) ) {
							$correct_region = $result['@metadata']['headers']['x-amz-bucket-region'];
						}
					}
					
					if ( $correct_region ) {
						$suggested_message = sprintf( 
							/* translators: 1: Actual bucket region, 2: Selected region, 3: Actual bucket region */
							__( 'The bucket is in region "%1$s", not "%2$s". Please select "%3$s" from the region dropdown.', 'clockwork-offloader' ),
							$correct_region,
							$test_region,
							$correct_region
						);
					} else {
						$suggested_message = __( 'The bucket appears to be in a different region than the one selected. Please try selecting a different region. Common regions to try: us-east-2, us-west-1, us-west-2, eu-west-1, or check your AWS S3 console for the bucket\'s actual region.', 'clockwork-offloader' );
					}
					
					return new WP_Error( 
						'region_mismatch', 
						$suggested_message,
						array( 'correct_region' => $correct_region )
					);
				}
				
				// Provide more helpful error messages for common SSL/certificate issues
				if ( stripos( $error_message, 'certificate' ) !== false || 
					 stripos( $error_message, 'SSL' ) !== false ||
					 stripos( $error_message, 'TLS' ) !== false ||
					 stripos( $error_message, 'handshake' ) !== false ) {
					return new WP_Error( 
						'connection_failed', 
						__( 'SSL certificate validation failed. This is usually a server configuration issue. Please check your server\'s SSL certificates and ensure they are valid and trusted.', 'clockwork-offloader' ) . ' ' . $error_message
					);
				}
				
				return new WP_Error( 'connection_failed', $error_message );
			}
		}
		
		// Use stored credentials
		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		
		$credentials = $this->get_credentials();
		$bucket = $credentials['bucket'];
		
		if ( empty( $bucket ) ) {
			return new WP_Error( 'bucket_not_set', __( 'S3 bucket is not configured.', 'clockwork-offloader' ) );
		}
		
		try {
			// Try to list objects (limited to 1) to test connection
			$client->listObjects( array(
				'Bucket' => $bucket,
				'MaxKeys' => 1,
			) );
			
			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'connection_failed', $e->getMessage() );
		}
	}
	
	/**
	 * Generate S3 key for a file
	 *
	 * @param string $file_path Local file path
	 * @param int    $attachment_id Attachment ID
	 * @param string $size_name Image size name (optional)
	 * @return string S3 key
	 */
	private function generate_s3_key( $file_path, $attachment_id, $size_name = '' ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$base_path = ! empty( $settings['s3_base_path'] ) ? trim( $settings['s3_base_path'], '/' ) : '';

		// Path relative to THIS site's uploads directory (e.g. 2024/01/photo.jpg)
		$upload_dir = wp_upload_dir();
		$relative_path = str_replace( wp_normalize_path( $upload_dir['basedir'] ), '', wp_normalize_path( $file_path ) );
		$relative_path = ltrim( $relative_path, '/' );

		// Multisite: a subsite's basedir is already .../uploads/sites/N (or blogs.dir/N/files
		// on old networks), so the relative path above has lost the site segment and two
		// sites uploading 2024/01/photo.jpg would share one object. Put the site back so keys
		// mirror the network's on-disk layout: sites/N/2024/01/photo.jpg.
		if ( is_multisite() && ! is_main_site() ) {
			$relative_path = 'sites/' . get_current_blog_id() . '/' . $relative_path;
		}

		// If base path is set, prepend it
		if ( ! empty( $base_path ) ) {
			$relative_path = $base_path . '/' . $relative_path;
		}

		/**
		 * Filter the object key a local file is stored under.
		 *
		 * @param string $relative_path Key (no leading slash)
		 * @param string $file_path     Absolute local path
		 * @param int    $attachment_id Attachment ID
		 * @param string $size_name     Image size name ('' for the original)
		 */
		return apply_filters( 'clockwork_offloader_s3_key', $relative_path, $file_path, $attachment_id, $size_name );
	}
}

