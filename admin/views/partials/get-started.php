<?php
/**
 * "Get started" panel
 *
 * Shown on the dashboard once storage credentials exist but nothing is being
 * pushed yet: Auto Upload is off, or no file has been offloaded so far.
 * Connecting a bucket does nothing by itself, so this is where we say so.
 *
 * Expects: $stats (tracker statistics array). Optional: $credentials.
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! Clockwork_Offloader_Settings_Helper::is_configured() ) {
	return;
}

$gs_settings    = Clockwork_Offloader_Settings_Helper::get_settings();
$gs_credentials = isset( $credentials ) && is_array( $credentials ) ? $credentials : ( new Clockwork_Offloader_S3_Service() )->get_credentials();
$gs_auto_upload = ! empty( $gs_settings['auto_offload'] );
$gs_offloaded   = isset( $stats['total_offloaded'] ) ? (int) $stats['total_offloaded'] : 0;
$gs_attachments = isset( $stats['total_attachments'] ) ? (int) $stats['total_attachments'] : 0;

// Nothing to nudge about: pushing is on and files have landed.
if ( $gs_auto_upload && $gs_offloaded > 0 ) {
	return;
}

$gs_provider_key  = ! empty( $gs_credentials['provider'] ) ? $gs_credentials['provider'] : ( ! empty( $gs_settings['provider'] ) ? $gs_settings['provider'] : 'aws' );
$gs_provider_name = ( $gs_provider_key === 'digitalocean' ) ? __( 'DigitalOcean Spaces', 'clockwork-offloader' ) : __( 'Amazon S3', 'clockwork-offloader' );
$gs_bucket        = ! empty( $gs_credentials['bucket'] ) ? $gs_credentials['bucket'] : ( ! empty( $gs_settings['s3_bucket'] ) ? $gs_settings['s3_bucket'] : '' );
$gs_is_pro        = class_exists( 'Clockwork_Offloader_Lite_Restrictions' ) && Clockwork_Offloader_Lite_Restrictions::is_pro_active();
$gs_base_url      = admin_url( 'options-general.php?page=clockwork-offloader' );
$gs_settings_url  = add_query_arg( 'tab', 'settings', $gs_base_url ) . '#auto_offload';
$gs_files_url     = add_query_arg( 'tab', 'file-status', $gs_base_url );
$gs_bulk_url      = add_query_arg( 'tab', 'bulk', $gs_base_url );
$gs_upgrade_url   = ( ! $gs_is_pro && class_exists( 'Clockwork_Offloader_Lite_Restrictions' ) ) ? Clockwork_Offloader_Lite_Restrictions::get_upgrade_url() : '';
?>
<div class="clockwork-get-started" role="region" aria-label="<?php esc_attr_e( 'Get started', 'clockwork-offloader' ); ?>">
	<div class="clockwork-get-started-intro">
		<span class="clockwork-get-started-icon dashicons dashicons-cloud-upload"></span>
		<div>
			<h2>
				<?php
				if ( $gs_offloaded === 0 ) {
					esc_html_e( 'You\'re connected. Nothing has been pushed yet.', 'clockwork-offloader' );
				} else {
					esc_html_e( 'You\'re connected, but new uploads aren\'t being pushed.', 'clockwork-offloader' );
				}
				?>
			</h2>
			<p>
				<?php
				if ( $gs_bucket ) {
					printf(
						/* translators: 1: provider name, 2: bucket name */
						esc_html__( 'Clockwork Offloader can reach %1$s bucket %2$s, but connecting a bucket doesn\'t move any files on its own. Finish these steps to start offloading.', 'clockwork-offloader' ),
						esc_html( $gs_provider_name ),
						'<code>' . esc_html( $gs_bucket ) . '</code>'
					);
				} else {
					printf(
						/* translators: %s: provider name */
						esc_html__( 'Clockwork Offloader is connected to %s, but connecting doesn\'t move any files on its own. Finish these steps to start offloading.', 'clockwork-offloader' ),
						esc_html( $gs_provider_name )
					);
				}
				?>
			</p>
		</div>
	</div>

	<ol class="clockwork-get-started-steps">
		<li class="<?php echo $gs_auto_upload ? 'is-done' : ''; ?>">
			<span class="clockwork-get-started-num"><?php echo $gs_auto_upload ? '&#10003;' : '1'; ?></span>
			<div class="clockwork-get-started-step-body">
				<h3><?php esc_html_e( 'Turn on Auto Upload', 'clockwork-offloader' ); ?></h3>
				<?php if ( $gs_auto_upload ) : ?>
					<p><?php echo esc_html( sprintf( /* translators: %s: provider name */ __( 'On. Every new media upload is pushed to %s automatically.', 'clockwork-offloader' ), $gs_provider_name ) ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html( sprintf( /* translators: %s: provider name */ __( 'Off. Until this is on, new uploads stay on your server and never reach %s.', 'clockwork-offloader' ), $gs_provider_name ) ); ?></p>
					<a href="<?php echo esc_url( $gs_settings_url ); ?>" class="button button-primary"><?php esc_html_e( 'Enable Auto Upload', 'clockwork-offloader' ); ?></a>
				<?php endif; ?>
			</div>
		</li>

		<li class="<?php echo $gs_offloaded > 0 ? 'is-done' : ''; ?>">
			<span class="clockwork-get-started-num"><?php echo $gs_offloaded > 0 ? '&#10003;' : '2'; ?></span>
			<div class="clockwork-get-started-step-body">
				<h3><?php esc_html_e( 'Push your existing media', 'clockwork-offloader' ); ?></h3>
				<?php if ( $gs_offloaded > 0 ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: offloaded file count, 2: total attachment count */
							esc_html__( '%1$s files are already in your bucket out of %2$s attachments.', 'clockwork-offloader' ),
							esc_html( number_format_i18n( $gs_offloaded ) ),
							esc_html( number_format_i18n( $gs_attachments ) )
						);
						?>
					</p>
					<?php if ( $gs_is_pro ) : ?>
						<a href="<?php echo esc_url( $gs_bulk_url ); ?>" class="button"><?php esc_html_e( 'Open Bulk Offload', 'clockwork-offloader' ); ?></a>
					<?php endif; ?>
				<?php elseif ( $gs_is_pro ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: total attachment count */
							esc_html__( 'Your library has %s attachments on the server. Bulk Offload pushes them in the background and rewrites their URLs.', 'clockwork-offloader' ),
							esc_html( number_format_i18n( $gs_attachments ) )
						);
						?>
					</p>
					<a href="<?php echo esc_url( $gs_bulk_url ); ?>" class="button <?php echo $gs_auto_upload ? 'button-primary' : ''; ?>"><?php esc_html_e( 'Start Bulk Offload', 'clockwork-offloader' ); ?></a>
				<?php else : ?>
					<p>
						<?php
						printf(
							/* translators: %s: total attachment count */
							esc_html__( 'Your library has %s attachments on the server. Lite pushes new uploads only; Pro adds Bulk Offload for everything already there.', 'clockwork-offloader' ),
							esc_html( number_format_i18n( $gs_attachments ) )
						);
						?>
					</p>
					<?php if ( $gs_upgrade_url ) : ?>
						<a href="<?php echo esc_url( $gs_upgrade_url ); ?>" class="button" target="_blank" rel="noopener"><?php esc_html_e( 'See Pro', 'clockwork-offloader' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</li>

		<li>
			<span class="clockwork-get-started-num">3</span>
			<div class="clockwork-get-started-step-body">
				<h3><?php esc_html_e( 'Watch it work', 'clockwork-offloader' ); ?></h3>
				<p><?php esc_html_e( 'The Files tab lists each attachment and where it lives. Upload one image and confirm it shows as offloaded.', 'clockwork-offloader' ); ?></p>
				<a href="<?php echo esc_url( $gs_files_url ); ?>" class="button"><?php esc_html_e( 'Open Files', 'clockwork-offloader' ); ?></a>
			</div>
		</li>
	</ol>
</div>
