<?php
/**
 * Lite Restrictions
 *
 * Feature gating for Lite version - checks if Pro is active
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clockwork_Offloader_Lite_Restrictions class
 */
class Clockwork_Offloader_Lite_Restrictions {
	
	/**
	 * Check if Pro plugin is active
	 *
	 * @return bool True if Pro is active, false otherwise
	 */
	public static function is_pro_active() {
		return class_exists( 'Clockwork_Offloader_Pro' );
	}
	
	/**
	 * Check if bulk offload is allowed
	 *
	 * @return bool True if bulk offload is allowed, false otherwise
	 */
	public static function can_bulk_offload() {
		return self::is_pro_active();
	}
	
	/**
	 * Check if queue system is available
	 *
	 * @return bool True if queue system is available, false otherwise
	 */
	public static function can_use_queue() {
		return self::is_pro_active();
	}
	
	/**
	 * Check if development mode is available
	 *
	 * @return bool True if development mode is available, false otherwise
	 */
	public static function can_use_development_mode() {
		return self::is_pro_active();
	}
	
	/**
	 * Check if migration tools are available
	 *
	 * @return bool True if migration tools are available, false otherwise
	 */
	public static function can_migrate() {
		return self::is_pro_active();
	}
	
	/**
	 * Show upgrade notice
	 *
	 * @param string $feature_name Name of the feature requiring Pro
	 * @return void
	 */
	public static function show_upgrade_notice( $feature_name = '' ) {
		if ( self::is_pro_active() ) {
			return; // Don't show notice if Pro is active
		}
		
		$message = ! empty( $feature_name ) 
			? sprintf( __( '%s is a Pro feature. Upgrade to Clockwork Offloader Pro to unlock this feature.', 'clockwork-offloader' ), esc_html( $feature_name ) )
			: __( 'This is a Pro feature. Upgrade to Clockwork Offloader Pro to unlock this feature.', 'clockwork-offloader' );
		
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Clockwork Offloader Pro Required', 'clockwork-offloader' ); ?></strong><br>
				<?php echo esc_html( $message ); ?>
				<a href="https://aaronreimann.com/clockwork-offloader-pro" target="_blank" class="button button-primary" style="margin-left: 10px;">
					<?php esc_html_e( 'Upgrade to Pro', 'clockwork-offloader' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Get upgrade URL
	 *
	 * @return string Upgrade URL
	 */
	public static function get_upgrade_url() {
		return 'https://aaronreimann.com/clockwork-offloader-pro';
	}
}

