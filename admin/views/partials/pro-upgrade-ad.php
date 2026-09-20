<?php
/**
 * Pro Upgrade Small Ad Partial
 *
 * Tasteful promotional callout displayed in Clockwork Offloader Lite.
 * Suppressed automatically if Clockwork Offloader Pro is active.
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Clockwork_Offloader_Pro' ) ) {
	return;
}

$upgrade_url = 'https://clockworkplugins.com/plugins/clockwork-offloader';
?>
<div class="clockwork-pro-ad-card">
	<div class="clockwork-pro-ad-header">
		<div class="clockwork-pro-ad-title-wrap">
			<div class="clockwork-pro-ad-badge">
				<span class="dashicons dashicons-star-filled"></span>
				<span><?php esc_html_e( 'PRO EDITION', 'clockwork-offloader' ); ?></span>
			</div>
			<h3 class="clockwork-pro-ad-title">
				<?php esc_html_e( 'Supercharge Media Delivery with Clockwork Offloader Pro', 'clockwork-offloader' ); ?>
			</h3>
		</div>
		<div class="clockwork-pro-ad-discount">
			<span class="clockwork-pro-ad-discount-pill"><?php esc_html_e( '50% OFF LAUNCH SPECIAL', 'clockwork-offloader' ); ?></span>
		</div>
	</div>

	<div class="clockwork-pro-ad-body">
		<p class="clockwork-pro-ad-desc">
			<?php esc_html_e( 'Accelerate your site with CloudFront CDN edge delivery, Assets Pull (cache CSS/JS globally), WooCommerce & EDD Private Media signed URLs, bulk background offloading queue, and developer WP-CLI tools.', 'clockwork-offloader' ); ?>
		</p>

		<div class="clockwork-pro-ad-features">
			<div class="clockwork-pro-ad-feature">
				<span class="dashicons dashicons-yes-alt"></span>
				<span><strong><?php esc_html_e( 'Unlimited Media Items', 'clockwork-offloader' ); ?></strong> (<?php esc_html_e( 'No attachment caps or overages', 'clockwork-offloader' ); ?>)</span>
			</div>
			<div class="clockwork-pro-ad-feature">
				<span class="dashicons dashicons-yes-alt"></span>
				<span><strong><?php esc_html_e( 'CloudFront CDN & Assets Pull', 'clockwork-offloader' ); ?></strong> (<?php esc_html_e( 'Global edge caching for media + CSS/JS', 'clockwork-offloader' ); ?>)</span>
			</div>
			<div class="clockwork-pro-ad-feature">
				<span class="dashicons dashicons-yes-alt"></span>
				<span><strong><?php esc_html_e( 'WooCommerce Private Media', 'clockwork-offloader' ); ?></strong> (<?php esc_html_e( 'Secure expiring download links', 'clockwork-offloader' ); ?>)</span>
			</div>
			<div class="clockwork-pro-ad-feature">
				<span class="dashicons dashicons-yes-alt"></span>
				<span><strong><?php esc_html_e( 'Bulk Queue & WP-CLI', 'clockwork-offloader' ); ?></strong> (<?php esc_html_e( 'Effortless library-wide offload & migration', 'clockwork-offloader' ); ?>)</span>
			</div>
		</div>

		<div class="clockwork-pro-ad-footer">
			<div class="clockwork-pro-ad-price">
				<span class="clockwork-pro-ad-price-old">$100</span>
				<span class="clockwork-pro-ad-price-new">$50</span>
				<span class="clockwork-pro-ad-price-period">/ year</span>
				<span class="clockwork-pro-ad-price-guarantee">&bull; <?php esc_html_e( 'Renews at 50% off for life', 'clockwork-offloader' ); ?></span>
			</div>
			<div class="clockwork-pro-ad-actions">
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="button clockwork-btn-upgrade">
					<span><?php esc_html_e( 'Get Clockwork Offloader Pro', 'clockwork-offloader' ); ?></span>
					<span class="dashicons dashicons-arrow-right-alt"></span>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'clockwork-offloader', 'tab' => 'upgrade' ), admin_url( 'options-general.php' ) ) ); ?>" class="clockwork-pro-ad-link">
					<?php esc_html_e( 'Compare All Features &rarr;', 'clockwork-offloader' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
