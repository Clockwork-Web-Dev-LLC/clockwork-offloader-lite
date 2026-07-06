<?php
/**
 * Settings View
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'clockwork-offloader' ) );
}

// Check if network mode is enabled (multisite only)
$is_network_mode = false;
$settings_source = 'site';
if ( is_multisite() && class_exists( 'Clockwork_Offloader_Settings_Helper' ) ) {
	$is_network_mode = Clockwork_Offloader_Settings_Helper::is_network_mode_enabled();
	$settings_source = Clockwork_Offloader_Settings_Helper::get_settings_source();
}
?>

	<?php if ( is_multisite() ) : ?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'Settings Mode:', 'clockwork-offloader' ); ?></strong>
				<?php if ( $is_network_mode ) : ?>
					<span style="color: green;"><?php esc_html_e( 'Network Mode', 'clockwork-offloader' ); ?></span>
					- <?php esc_html_e( 'Network-wide settings are active. These site settings will be used as a fallback or when network mode is disabled.', 'clockwork-offloader' ); ?>
					<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=clockwork-offloader' ) ); ?>"><?php esc_html_e( 'Manage network settings', 'clockwork-offloader' ); ?></a>
				<?php else : ?>
					<span style="color: blue;"><?php esc_html_e( 'Site Mode', 'clockwork-offloader' ); ?></span>
					- <?php esc_html_e( 'This site uses its own settings.', 'clockwork-offloader' ); ?>
					<?php if ( current_user_can( 'manage_network_options' ) ) : ?>
						<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=clockwork-offloader' ) ); ?>"><?php esc_html_e( 'Enable network mode', 'clockwork-offloader' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</p>
			<?php if ( $settings_source === 'wp-config' ) : ?>
				<p>
					<strong><?php esc_html_e( 'Note:', 'clockwork-offloader' ); ?></strong>
					<?php esc_html_e( 'Settings are loaded from wp-config.php constants, which take priority over network and site settings.', 'clockwork-offloader' ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	
	<?php settings_errors( 'clockwork_offloader_settings' ); ?>
	
	<form method="post" action="options.php">
		<?php
		settings_fields( 'clockwork_offloader_settings' );
		do_settings_sections( 'clockwork-offloader-settings' );
		?>
		
		<p class="submit">
			<?php submit_button( __( 'Save Changes', 'clockwork-offloader' ), 'primary', 'submit', false ); ?>
		</p>
	</form>
