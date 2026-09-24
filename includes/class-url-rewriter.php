<?php
/**
 * URL Rewriter
 *
 * Handles rewriting media URLs to point to S3
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clockwork_Offloader_URL_Rewriter class
 */
class Clockwork_Offloader_URL_Rewriter {
	
	/**
	 * Instance of this class
	 *
	 * @var Clockwork_Offloader_URL_Rewriter
	 */
	private static $instance = null;
	
	/**
	 * Get instance of this class
	 *
	 * @return Clockwork_Offloader_URL_Rewriter
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
		$this->init_hooks();
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Always add hooks, but check settings inside the filter
		// Rewrite attachment URLs
		// Use high priority (5) for image_downsize to run before WordPress checks for local files
		add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'rewrite_image_src' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ), 10, 5 );
		add_filter( 'image_downsize', array( $this, 'rewrite_image_downsize' ), 5, 3 );
		add_filter( 'wp_get_original_image_url', array( $this, 'rewrite_original_image_url' ), 10, 2 );

		// Hard-coded upload URLs saved inside post content (block editor, classic editor,
		// page builders) never pass through the attachment filters above, so they'd 404 the
		// moment local files are deleted. Rewrite them at render time.
		add_filter( 'the_content', array( $this, 'rewrite_content' ), 99 );
		add_filter( 'the_excerpt', array( $this, 'rewrite_content' ), 99 );
		add_filter( 'widget_text_content', array( $this, 'rewrite_content' ), 99 );
		add_filter( 'widget_block_content', array( $this, 'rewrite_content' ), 99 );

		// Page builders (Beaver Builder, Elementor) compiled layout CSS filters.
		// Rewrites background images and other assets embedded in generated CSS files.
		add_filter( 'fl_builder_render_css', array( $this, 'rewrite_content' ), 99 );
		add_filter( 'fl_builder_global_css_string', array( $this, 'rewrite_content' ), 99 );
		add_filter( 'elementor/css-file/post/parse', array( $this, 'rewrite_content' ), 99 );
		add_filter( 'elementor/css-file/global/parse', array( $this, 'rewrite_content' ), 99 );
	}

	/**
	 * Rewrite the full-resolution original behind a -scaled image.
	 *
	 * @param string $url           Original image URL
	 * @param int    $attachment_id Attachment ID
	 * @return string
	 */
	public function rewrite_original_image_url( $url, $attachment_id ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		if ( empty( $settings['rewrite_urls'] ) ) {
			return $url;
		}

		$tracker = new Clockwork_Offloader_Tracker();
		$record = $tracker->get_offload_record( $attachment_id, 'original_image' );

		return $record ? $tracker->get_record_url( $record ) : $url;
	}

	/**
	 * Replace local upload URLs found in a block of HTML with their offloaded equivalents.
	 *
	 * Only URLs under this site's uploads base URL are considered, and only ones whose
	 * local path has an 'offloaded' record are replaced, so nothing changes for files that
	 * were never offloaded. One tracker query per distinct set of URLs.
	 *
	 * @param string $content HTML
	 * @return string
	 */
	public function rewrite_content( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		if ( empty( $settings['rewrite_urls'] ) ) {
			return $content;
		}

		$upload_dir = wp_upload_dir( null, false );
		$base_url = $upload_dir['baseurl'];
		$base_dir = wp_normalize_path( $upload_dir['basedir'] );
		if ( empty( $base_url ) || false === stripos( $content, $base_url ) ) {
			return $content;
		}

		// Match the base URL (either scheme, or scheme-relative) plus a path up to the
		// next quote/space/bracket — good enough for src, srcset, href and inline CSS url().
		$scheme_less = preg_replace( '#^https?:#i', '', $base_url );
		$pattern = '#(?:https?:)?' . preg_quote( $scheme_less, '#' ) . '/[^\s"\'<>()\\\\,]+#i';
		if ( ! preg_match_all( $pattern, $content, $matches ) ) {
			return $content;
		}

		$urls = array_unique( $matches[0] );
		$path_for_url = array();
		foreach ( $urls as $url ) {
			$clean = preg_replace( '#[?\#].*$#', '', $url );
			$relative = preg_replace( '#^(?:https?:)?' . preg_quote( $scheme_less, '#' ) . '/#i', '', $clean );
			$path_for_url[ $url ] = $base_dir . '/' . rawurldecode( $relative );
		}

		static $cache = array();
		$tracker = new Clockwork_Offloader_Tracker();
		$missing = array_diff( array_values( $path_for_url ), array_keys( $cache ) );
		if ( ! empty( $missing ) ) {
			$records = $tracker->get_records_by_paths( $missing );
			foreach ( $missing as $path ) {
				$cache[ $path ] = isset( $records[ $path ] ) ? $tracker->get_record_url( $records[ $path ] ) : false;
			}
		}

		$replacements = array();
		foreach ( $path_for_url as $url => $path ) {
			if ( ! empty( $cache[ $path ] ) ) {
				$replacements[ $url ] = $cache[ $path ];
			}
		}

		if ( empty( $replacements ) ) {
			return $content;
		}

		// Longest URLs first so a size variant is never clobbered by its original's prefix.
		uksort( $replacements, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );

		return strtr( $content, $replacements );
	}
	
	/**
	 * Rewrite attachment URL
	 *
	 * @param string $url Original URL
	 * @param int    $attachment_id Attachment ID
	 * @return string Rewritten URL
	 */
	public function rewrite_attachment_url( $url, $attachment_id ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Check if URL rewriting is enabled
		if ( empty( $settings['rewrite_urls'] ) ) {
			return $url;
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Check if file is offloaded - if it is, ALWAYS use S3 URL
		if ( ! $tracker->is_offloaded( $attachment_id ) ) {
			return $url;
		}
		
		// Get S3 URL
		$s3_url = $tracker->get_s3_url( $attachment_id );
		if ( ! $s3_url ) {
			$s3_url = $this->get_s3_url_fallback( $attachment_id, '' );
		}
		
		// If file is on S3, ALWAYS use S3 URL - no conditions, no exceptions
		if ( $s3_url ) {
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			
			// Use CDN domain if set
			if ( ! empty( $settings['cdn_domain'] ) ) {
				$s3_url = $this->replace_s3_domain_with_cdn( $s3_url, $settings['cdn_domain'] );
			}
			
			// ALWAYS use S3 URL if file is offloaded
			return $s3_url;
		}
		
		return $url;
	}
	
	/**
	 * Replace S3 domain with CDN domain
	 *
	 * @param string $s3_url S3 URL
	 * @param string $cdn_domain CDN domain
	 * @return string CDN URL
	 */
	private function replace_s3_domain_with_cdn( $s3_url, $cdn_domain ) {
		// Parse S3 URL to get the path
		$parsed = wp_parse_url( $s3_url );
		if ( ! $parsed || empty( $parsed['path'] ) ) {
			return $s3_url;
		}
		
		// Remove leading slash from path
		$path = ltrim( $parsed['path'], '/' );
		
		// Build CDN URL
		$cdn_domain = rtrim( $cdn_domain, '/' );
		return $cdn_domain . '/' . $path;
	}
	
	/**
	 * Rewrite image src
	 *
	 * @param array|false  $image Image data array or false
	 * @param int          $attachment_id Attachment ID
	 * @param string|array $size Image size
	 * @param bool         $icon Whether to use icon
	 * @return array|false Modified image data or false
	 */
	public function rewrite_image_src( $image, $attachment_id, $size, $icon ) {
		if ( ! $image || $icon ) {
			return $image;
		}
		
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Check if URL rewriting is enabled
		if ( empty( $settings['rewrite_urls'] ) ) {
			return $image;
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Check if file is offloaded - if it is, ALWAYS use S3 URL
		if ( ! $tracker->is_offloaded( $attachment_id ) ) {
			return $image;
		}
		
		// Get size name
		$size_name = '';
		if ( is_string( $size ) ) {
			$size_name = $size;
		} elseif ( is_array( $size ) ) {
			// For custom sizes, try to find matching size name
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( $metadata && ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $name => $size_data ) {
					if ( $size_data['width'] == $size[0] && $size_data['height'] == $size[1] ) {
						$size_name = $name;
						break;
					}
				}
			}
		}
		
		// Prefer the specific size URL; fall back to original if size was not recorded
		$s3_url = $tracker->get_s3_url( $attachment_id, $size_name );
		if ( ! $s3_url ) {
			$s3_url = $this->get_s3_url_fallback( $attachment_id, $size_name );
		}
		
		// If file is on S3, ALWAYS use S3 URL - no conditions, no exceptions
		if ( $s3_url ) {
			$settings = Clockwork_Offloader_Settings_Helper::get_settings();
			
			// Use CDN domain if set
			if ( ! empty( $settings['cdn_domain'] ) ) {
				$s3_url = $this->replace_s3_domain_with_cdn( $s3_url, $settings['cdn_domain'] );
			}
			
			// ALWAYS use S3 URL if file is offloaded
			$image[0] = $s3_url;
		}
		
		return $image;
	}
	
	/**
	 * Rewrite srcset URLs
	 *
	 * @param array  $sources Source data
	 * @param array  $size_array Size array
	 * @param string $image_src Image source URL
	 * @param array  $image_meta Image metadata
	 * @param int    $attachment_id Attachment ID
	 * @return array Modified sources
	 */
	public function rewrite_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( empty( $sources ) || ! is_array( $sources ) ) {
			return $sources;
		}
		
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Check if URL rewriting is enabled
		if ( empty( $settings['rewrite_urls'] ) ) {
			return $sources;
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Check if file is offloaded - if it is, ALWAYS use S3 URL
		if ( ! $tracker->is_offloaded( $attachment_id ) ) {
			return $sources;
		}
		
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// If file is on S3, ALWAYS use S3 URL - no conditions, no exceptions
		
		foreach ( $sources as $width => $source ) {
			// Find the size name for this width
			$size_name = '';
			if ( ! empty( $image_meta['sizes'] ) ) {
				foreach ( $image_meta['sizes'] as $name => $size_data ) {
					if ( isset( $size_data['width'] ) && $size_data['width'] == $width ) {
						$size_name = $name;
						break;
					}
				}
			}
			
			// Prefer size-specific URL; fall back to original if missing
			$s3_url = $tracker->get_s3_url( $attachment_id, $size_name );
			if ( ! $s3_url ) {
				$s3_url = $this->get_s3_url_fallback( $attachment_id, $size_name );
			}
			
			if ( $s3_url ) {
				// Use CDN domain if set
				if ( ! empty( $settings['cdn_domain'] ) ) {
					$s3_url = $this->replace_s3_domain_with_cdn( $s3_url, $settings['cdn_domain'] );
				}
				$sources[ $width ]['url'] = $s3_url;
			}
		}
		
		return $sources;
	}
	
	/**
	 * Force downsized image URLs to use CDN when offloaded and local file is missing
	 *
	 * @param bool|array    $downsize Pre-filter value or array( url, width, height, is_intermediate ).
	 * @param int           $id       Attachment ID.
	 * @param string|array  $size     Requested size.
	 * @return bool|array
	 */
	public function rewrite_image_downsize( $downsize, $id, $size ) {
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Check if URL rewriting is enabled
		if ( empty( $settings['rewrite_urls'] ) ) {
			return $downsize;
		}
		
		$tracker = new Clockwork_Offloader_Tracker();
		
		// Only act if offloaded - if it is, ALWAYS use S3 URL
		if ( ! $tracker->is_offloaded( $id ) ) {
			return $downsize;
		}
		
		// If file is on S3, ALWAYS use S3 URL - no conditions, no exceptions
		
		// Determine size name and dimensions from metadata
		$metadata = wp_get_attachment_metadata( $id );
		$size_name = '';
		$width  = 0;
		$height = 0;
		$is_intermediate = false;
		
		if ( is_string( $size ) ) {
			$size_name = $size;
		} elseif ( is_array( $size ) ) {
			// Match custom width/height to a named size if possible
			if ( $metadata && ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $name => $size_data ) {
					if ( isset( $size_data['width'], $size_data['height'] ) && $size_data['width'] == $size[0] && $size_data['height'] == $size[1] ) {
						$size_name = $name;
						break;
					}
				}
			}
		}
		
		if ( $metadata ) {
			if ( $size_name && ! empty( $metadata['sizes'][ $size_name ] ) ) {
				$width  = isset( $metadata['sizes'][ $size_name ]['width'] ) ? (int) $metadata['sizes'][ $size_name ]['width'] : 0;
				$height = isset( $metadata['sizes'][ $size_name ]['height'] ) ? (int) $metadata['sizes'][ $size_name ]['height'] : 0;
				$is_intermediate = true;
			} else {
				$width  = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
				$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;
			}
		} elseif ( is_array( $size ) && count( $size ) >= 2 ) {
			// No metadata: use the requested size so thumbnails render consistently
			$width  = (int) $size[0];
			$height = (int) $size[1];
			$is_intermediate = true;
		}
		
		// Prefer size-specific URL; fall back to original if missing
		$s3_url = $tracker->get_s3_url( $id, $size_name );
		if ( ! $s3_url ) {
			$s3_url = $this->get_s3_url_fallback( $id, $size_name );
		}
		
		// If we still don't have an S3 URL, something is wrong - but we should still try to return something
		// to prevent broken images. Use the original S3 URL as last resort.
		if ( ! $s3_url ) {
			// Last resort: get original S3 URL directly from database
			$original_offload = $tracker->get_offload_record( $id, '' );
			if ( $original_offload ) {
				$s3_key = $original_offload->s3_key;
				if ( ! empty( $settings['cdn_domain'] ) ) {
					$cdn_domain = rtrim( $settings['cdn_domain'], '/' );
					$s3_url = $cdn_domain . '/' . $s3_key;
				} else {
					// Use same credential logic as S3 service to get correct region and provider
					// This ensures wp-config.php constants are checked first, then database settings
					require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-s3-service.php';
					$s3_service = new Clockwork_Offloader_S3_Service();
					$credentials = $s3_service->get_credentials();
					$region = ! empty( $credentials['region'] ) ? $credentials['region'] : 'us-east-1';
					$provider = ! empty( $credentials['provider'] ) ? $credentials['provider'] : 'aws';
					
					$s3_url = Clockwork_Offloader_S3_Service::build_public_url( $original_offload->bucket, $s3_key, $region, $provider );
				}
			}
		}
		
		// If we still don't have an S3 URL, something is seriously wrong
		// But we're in a branch where file is offloaded, so we MUST return S3 URL
		// Don't return $downsize here - that would cause broken images
		if ( ! $s3_url ) {
			// This should never happen if file is truly offloaded, but if it does, return false
			// to let WordPress try (though it will likely fail)
			return false;
		}
		
		// Swap to CDN domain if configured
		if ( ! empty( $settings['cdn_domain'] ) ) {
			$s3_url = $this->replace_s3_domain_with_cdn( $s3_url, $settings['cdn_domain'] );
		}
		
		// Always override to CDN URL in this branch
		// This ensures thumbnails work even when local files are missing
		return array( $s3_url, $width, $height, $is_intermediate );
	}
	
	/**
	 * Build an S3/CDN URL when a size record is missing, falling back to the original object.
	 *
	 * If a size-specific object was never uploaded (common when local sizes are deleted),
	 * we use the original S3 key so thumbnails still render from CDN while retaining the
	 * requested width/height in markup.
	 */
	private function get_s3_url_fallback( $attachment_id, $size_name = '' ) {
		$tracker  = new Clockwork_Offloader_Tracker();
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		
		// Use original record as the source of truth
		$original = $tracker->get_offload_record( $attachment_id, '' );
		if ( ! $original ) {
			return false;
		}
		
		// Always fall back to the original object's key to avoid 404s when sized objects
		// were never uploaded. Markup will still carry the requested dimensions so the
		// browser renders at the expected size.
		$s3_key = $original->s3_key;
		
		// Assemble URL with CDN if configured
		if ( ! empty( $settings['cdn_domain'] ) ) {
			$cdn_domain = rtrim( $settings['cdn_domain'], '/' );
			return $cdn_domain . '/' . $s3_key;
		}
		
		// Use same credential logic as S3 service to get correct region and provider
		// This ensures wp-config.php constants are checked first, then database settings
		require_once CLOCKWORK_OFFLOADER_PLUGIN_DIR . 'includes/class-s3-service.php';
		$s3_service = new Clockwork_Offloader_S3_Service();
		$credentials = $s3_service->get_credentials();
		$region = ! empty( $credentials['region'] ) ? $credentials['region'] : 'us-east-1';
		$provider = ! empty( $credentials['provider'] ) ? $credentials['provider'] : 'aws';

		return Clockwork_Offloader_S3_Service::build_public_url( $original->bucket, $s3_key, $region, $provider );
	}
}

