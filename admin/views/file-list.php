<?php
/**
 * File List View (Lite)
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

// Get filter
$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';
$allowed_filters = array( 'all', 'offloaded', 'not-offloaded' );
if ( ! in_array( $filter, $allowed_filters, true ) ) {
	$filter = 'all';
}

// Get search query
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

// Get pagination
$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page = 20;

// Build query args
$query_args = array(
	'post_type'      => 'attachment',
	'post_status'    => 'inherit',
	'posts_per_page' => $per_page,
	'paged'          => $paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
);

// Add search
if ( ! empty( $search ) ) {
	$query_args['s'] = $search;
}

// Add mime type filter (images only for now)
$query_args['post_mime_type'] = 'image';

$tracker = new Clockwork_Offloader_Tracker();

// Count totals for filters
$total_attachments = wp_count_posts( 'attachment' )->inherit ?? 0;
$offloaded_count = 0;
$not_offloaded_count = 0;

// Get filtered attachment IDs if filter is set
$filtered_ids = null;
if ( 'offloaded' === $filter || 'not-offloaded' === $filter ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'clockwork_offloads';
	
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
		if ( 'offloaded' === $filter ) {
			// Get all offloaded attachment IDs
			$offloaded_ids = $wpdb->get_col( "SELECT DISTINCT attachment_id FROM " . esc_sql( $table_name ) );
			$filtered_ids = ! empty( $offloaded_ids ) ? array_map( 'absint', $offloaded_ids ) : array( 0 ); // Use 0 if empty to show nothing
		} else {
			// Get all attachment IDs, then exclude offloaded ones
			$all_ids = $wpdb->get_col( $wpdb->prepare( 
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s",
				'attachment',
				'inherit',
				'image/%'
			) );
			$offloaded_ids = $wpdb->get_col( "SELECT DISTINCT attachment_id FROM " . esc_sql( $table_name ) );
			$filtered_ids = array_diff( array_map( 'absint', $all_ids ), array_map( 'absint', $offloaded_ids ) );
			if ( empty( $filtered_ids ) ) {
				$filtered_ids = array( 0 ); // Use 0 if empty to show nothing
			}
		}
		
		// Add filtered IDs to query
		if ( ! empty( $filtered_ids ) ) {
			$query_args['post__in'] = $filtered_ids;
		}
	}
	
	// Count totals
	$offloaded_count = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT attachment_id) FROM " . esc_sql( $table_name ) );
	$not_offloaded_count = max( 0, $total_attachments - $offloaded_count );
} else {
	// Count totals for "all" filter
	global $wpdb;
	$table_name = $wpdb->prefix . 'clockwork_offloads';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
		$offloaded_count = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT attachment_id) FROM " . esc_sql( $table_name ) );
		$not_offloaded_count = max( 0, $total_attachments - $offloaded_count );
	}
}

$attachments_query = new WP_Query( $query_args );

// Build base URL for filter links
$base_url = add_query_arg( array(
	'page' => 'clockwork-offloader',
	'tab'  => 'file-status',
), admin_url( 'options-general.php' ) );

if ( ! empty( $search ) ) {
	$base_url = add_query_arg( 's', $search, $base_url );
}
?>

<div class="clockwork-file-list">
	<div class="notice notice-info" style="margin: 20px 0;">
		<p>
			<strong><?php esc_html_e( 'Clockwork Offloader Pro Required', 'clockwork-offloader' ); ?></strong><br />
			<?php esc_html_e( 'File management is a Pro feature. Upgrade to Clockwork Offloader Pro to offload, restore, and manage your media files.', 'clockwork-offloader' ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'upgrade', admin_url( 'options-general.php?page=clockwork-offloader' ) ) ); ?>" class="button button-primary" style="margin-left: 10px;">
				<?php esc_html_e( 'Upgrade to Pro', 'clockwork-offloader' ); ?>
			</a>
		</p>
	</div>
	
	<h2><?php esc_html_e( 'Media Files', 'clockwork-offloader' ); ?></h2>
	
	<div class="bulk-offload-filters">
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( $base_url ); ?>" class="<?php echo 'all' === $filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'clockwork-offloader' ); ?>
					<span class="count">(<?php echo esc_html( number_format_i18n( $total_attachments ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'filter', 'offloaded', $base_url ) ); ?>" class="<?php echo 'offloaded' === $filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Offloaded', 'clockwork-offloader' ); ?>
					<span class="count">(<?php echo esc_html( number_format_i18n( $offloaded_count ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'filter', 'not-offloaded', $base_url ) ); ?>" class="<?php echo 'not-offloaded' === $filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Not Offloaded', 'clockwork-offloader' ); ?>
					<span class="count">(<?php echo esc_html( number_format_i18n( $not_offloaded_count ) ); ?>)</span>
				</a>
			</li>
		</ul>
		
		<form method="get" action="" class="search-form" style="float: right; margin-top: 5px;">
			<input type="hidden" name="page" value="clockwork-offloader" />
			<input type="hidden" name="tab" value="file-status" />
			<?php if ( ! empty( $filter ) && 'all' !== $filter ) : ?>
				<input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>" />
			<?php endif; ?>
			<label class="screen-reader-text" for="media-search-input"><?php esc_html_e( 'Search Media', 'clockwork-offloader' ); ?></label>
			<input type="search" id="media-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search media...', 'clockwork-offloader' ); ?>" />
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'clockwork-offloader' ); ?>" />
		</form>
	</div>
	
	<div class="clockwork-file-list-disabled" style="opacity: 0.6; pointer-events: none;">
	<?php if ( $attachments_query->have_posts() ) : ?>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col" class="column-thumbnail"><?php esc_html_e( 'Thumbnail', 'clockwork-offloader' ); ?></th>
					<th scope="col" class="column-title"><?php esc_html_e( 'File', 'clockwork-offloader' ); ?></th>
					<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'clockwork-offloader' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'clockwork-offloader' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php while ( $attachments_query->have_posts() ) : $attachments_query->the_post(); ?>
					<?php
					$attachment_id = get_the_ID();
					$is_offloaded = $tracker->is_offloaded( $attachment_id );
					
					$file_url = wp_get_attachment_url( $attachment_id );
					$file_name = basename( $file_url );
					$thumbnail = wp_get_attachment_image( $attachment_id, array( 60, 60 ), true, array( 'style' => 'max-width: 60px; height: auto;' ) );
					?>
					<tr>
						<td class="column-thumbnail">
							<?php echo $thumbnail ? wp_kses_post( $thumbnail ) : '<span class="dashicons dashicons-media-default"></span>'; ?>
						</td>
						<td class="column-title">
							<strong><?php echo esc_html( $file_name ); ?></strong>
							<br />
							<small style="color: #646970;">ID: <?php echo esc_html( $attachment_id ); ?></small>
						</td>
						<td class="column-status">
							<?php if ( $is_offloaded ) : ?>
								<span style="color: #00a32a;">
									<span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Offloaded', 'clockwork-offloader' ); ?>
								</span>
							<?php else : ?>
								<span style="color: #646970;">
									<span class="dashicons dashicons-minus" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Not Offloaded', 'clockwork-offloader' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<?php if ( $is_offloaded ) : ?>
								<button type="button" class="button button-small clockwork-restore-btn" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>" disabled>
									<?php esc_html_e( 'Restore', 'clockwork-offloader' ); ?>
								</button>
							<?php else : ?>
								<button type="button" class="button button-primary button-small clockwork-offload-btn" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>" disabled>
									<?php esc_html_e( 'Offload', 'clockwork-offloader' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>
		
		<?php
		// Pagination
		$pagination_args = array(
			'total'   => $attachments_query->max_num_pages,
			'current' => $paged,
			'format'  => '?page=clockwork-offloader&tab=file-status&paged=%#%',
		);
		if ( ! empty( $filter ) && 'all' !== $filter ) {
			$pagination_args['format'] .= '&filter=' . $filter;
		}
		if ( ! empty( $search ) ) {
			$pagination_args['format'] .= '&s=' . urlencode( $search );
		}
		echo wp_kses_post( paginate_links( $pagination_args ) );
		?>
		
	<?php else : ?>
		<p><?php esc_html_e( 'No media files found.', 'clockwork-offloader' ); ?></p>
	<?php endif; ?>
	</div> <!-- End clockwork-file-list-disabled -->
	
	<?php wp_reset_postdata(); ?>
</div> <!-- End clockwork-file-list -->

<!-- JavaScript handlers removed for Lite version - buttons are disabled -->
<!--
<script type="text/javascript">
jQuery(document).ready(function($) {
	// Offload button
	$('.clockwork-offload-btn').on('click', function() {
		var $btn = $(this);
		var attachmentId = $btn.data('attachment-id');
		var $spinner = $('.clockwork-action-spinner[data-attachment-id="' + attachmentId + '"]');
		var $row = $btn.closest('tr');
		
		$btn.prop('disabled', true);
		$spinner.css('visibility', 'visible');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'clockwork_offloader_offload_from_server',
				nonce: '<?php echo esc_js( wp_create_nonce( 'clockwork_offloader_nonce' ) ); ?>',
				attachment_id: attachmentId
			},
			success: function(response) {
				$btn.prop('disabled', false);
				$spinner.css('visibility', 'hidden');
				
				if (response.success) {
					// Update status
					$row.find('.column-status').html(
						'<span style="color: #00a32a;">' +
						'<span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span> ' +
						'<?php echo esc_js( __( 'Offloaded', 'clockwork-offloader' ) ); ?>' +
						'</span>'
					);
					// Change button to Restore
					$btn.replaceWith(
						'<button type="button" class="button button-small clockwork-restore-btn" data-attachment-id="' + attachmentId + '">' +
						'<?php echo esc_js( __( 'Restore', 'clockwork-offloader' ) ); ?>' +
						'</button>'
					);
				} else {
					alert(response.data.message || '<?php echo esc_js( __( 'Error offloading file.', 'clockwork-offloader' ) ); ?>');
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				$spinner.css('visibility', 'hidden');
				alert('<?php echo esc_js( __( 'An error occurred.', 'clockwork-offloader' ) ); ?>');
			}
		});
	});
	
	// Restore button
	$('.clockwork-restore-btn').on('click', function() {
		var $btn = $(this);
		var attachmentId = $btn.data('attachment-id');
		var $spinner = $('.clockwork-action-spinner[data-attachment-id="' + attachmentId + '"]');
		var $row = $btn.closest('tr');
		
		$btn.prop('disabled', true);
		$spinner.css('visibility', 'visible');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'clockwork_offloader_restore',
				nonce: '<?php echo esc_js( wp_create_nonce( 'clockwork_offloader_nonce' ) ); ?>',
				attachment_id: attachmentId
			},
			success: function(response) {
				$btn.prop('disabled', false);
				$spinner.css('visibility', 'hidden');
				
				if (response.success) {
					// Update status
					$row.find('.column-status').html(
						'<span style="color: #646970;">' +
						'<span class="dashicons dashicons-minus" style="vertical-align: middle;"></span> ' +
						'<?php echo esc_js( __( 'Not Offloaded', 'clockwork-offloader' ) ); ?>' +
						'</span>'
					);
					// Change button to Offload
					$btn.replaceWith(
						'<button type="button" class="button button-primary button-small clockwork-offload-btn" data-attachment-id="' + attachmentId + '">' +
						'<?php echo esc_js( __( 'Offload', 'clockwork-offloader' ) ); ?>' +
						'</button>'
					);
				} else {
					alert(response.data.message || '<?php echo esc_js( __( 'Error restoring file.', 'clockwork-offloader' ) ); ?>');
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				$spinner.css('visibility', 'hidden');
				alert('<?php echo esc_js( __( 'An error occurred.', 'clockwork-offloader' ) ); ?>');
			}
		});
	});
});
</script>
-->

