<?php
/**
 * PHPUnit Bootstrap for Clockwork Offloader Lite
 *
 * Configures the test environment, autoloading, and mocks for WordPress functions.
 */

// Autoload dependencies
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants if not defined
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}
if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}

// Plugin specific constants
if ( ! defined( 'CLOCKWORK_OFFLOADER_VERSION' ) ) {
	define( 'CLOCKWORK_OFFLOADER_VERSION', '1.1.0' );
}
if ( ! defined( 'CLOCKWORK_OFFLOADER_PLUGIN_DIR' ) ) {
	define( 'CLOCKWORK_OFFLOADER_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'CLOCKWORK_OFFLOADER_PLUGIN_URL' ) ) {
	define( 'CLOCKWORK_OFFLOADER_PLUGIN_URL', 'https://example.com/wp-content/plugins/clockwork-offloader-lite/' );
}
if ( ! defined( 'CLOCKWORK_OFFLOADER_BASENAME' ) ) {
	define( 'CLOCKWORK_OFFLOADER_BASENAME', 'clockwork-offloader-lite/clockwork-offloader.php' );
}

// WordPress Error class stub
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		protected $errors = array();
		protected $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( empty( $code ) ) {
				return;
			}
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return empty( $codes ) ? '' : $codes[0];
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
		}

		public function get_error_messages( $code = '' ) {
			return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : array();
		}

		public function get_error_data( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
		}

		public function add( $code, $message, $data = '' ) {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	function esc_sql( $data ) {
		return is_array( $data ) ? array_map( 'esc_sql', $data ) : addslashes( (string) $data );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}
		return $path;
	}
}
