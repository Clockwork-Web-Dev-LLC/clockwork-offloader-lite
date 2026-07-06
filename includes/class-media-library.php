<?php
/**
 * Media Library Integration
 *
 * Adds offload status indicators to the media library
 *
 * @package Clockwork_Offloader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clockwork_Offloader_Media_Library class
 */
class Clockwork_Offloader_Media_Library {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Check if media library status display is enabled
		$settings = Clockwork_Offloader_Settings_Helper::get_settings();
		$show_status = isset( $settings['show_media_library_status'] ) ? $settings['show_media_library_status'] : true; // Default to true
		
		if ( ! $show_status ) {
			return; // Don't add any hooks if disabled
		}
		
		// Add column to list view
		add_filter( 'manage_media_columns', array( $this, 'add_offload_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_offload_column' ), 10, 2 );
		
		// Add indicator to grid view
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_library_scripts' ) );
		add_action( 'print_media_templates', array( $this, 'add_grid_view_template' ) );
		
		// Add controls to attachment details
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_fields' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_attachment_details_scripts' ) );
		
		// Add meta box for attachment edit page (right sidebar)
		add_action( 'add_meta_boxes', array( $this, 'add_attachment_meta_box' ) );
	}
	
	/**
	 * Add offload status column to media library
	 *
	 * @param array $columns Existing columns
	 * @return array Modified columns
	 */
	public function add_offload_column( $columns ) {
		$columns['clockwork_offload_status'] = __( 'CDN Status', 'clockwork-offloader' );
		return $columns;
	}
	
	/**
	 * Render offload status column content
	 *
	 * @param string $column_name Column name
	 * @param int    $attachment_id Attachment ID
	 */
	public function render_offload_column( $column_name, $attachment_id ) {
		if ( 'clockwork_offload_status' !== $column_name ) {
			return;
		}
		
		$status = $this->get_offload_status( $attachment_id );
		$this->render_status_indicator( $status );
	}
	
	/**
	 * Get offload status for an attachment
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array Status information
	 */
	public function get_offload_status( $attachment_id ) {
		$tracker = new Clockwork_Offloader_Tracker();
		$is_offloaded = $tracker->is_offloaded( $attachment_id );
		
		// Check if file exists on server
		$file_path = get_attached_file( $attachment_id );
		$file_exists = false;
		
		if ( $file_path ) {
			// Check if original file exists
			$file_exists = file_exists( $file_path );
			
			// If original doesn't exist, check if any image sizes exist
			if ( ! $file_exists ) {
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( $metadata && ! empty( $metadata['sizes'] ) ) {
					$file_dir = dirname( $file_path );
					foreach ( $metadata['sizes'] as $size_data ) {
						$size_file = $file_dir . '/' . $size_data['file'];
						if ( file_exists( $size_file ) ) {
							$file_exists = true;
							break;
						}
					}
				}
			}
		}
		
		// Determine CDN status
		$cdn_on_cdn = $is_offloaded;
		$cdn_color = $cdn_on_cdn ? '#00a32a' : '#646970'; // Green if on CDN, grey if not
		
		// Determine server status
		$server_on_server = $file_exists;
		$server_color = $server_on_server ? '#00a32a' : '#646970'; // Green if on server, grey if not
		
		// Build label
		$parts = array();
		if ( $cdn_on_cdn ) {
			$parts[] = __( 'On CDN', 'clockwork-offloader' );
		} else {
			$parts[] = __( 'Not on CDN', 'clockwork-offloader' );
		}
		if ( $server_on_server ) {
			$parts[] = __( 'On Server', 'clockwork-offloader' );
		} else {
			$parts[] = __( 'Not on Server', 'clockwork-offloader' );
		}
		$label = implode( ' • ', $parts );
		
		return array(
			'cdn_on_cdn' => $cdn_on_cdn,
			'cdn_color' => $cdn_color,
			'server_on_server' => $server_on_server,
			'server_color' => $server_color,
			'label' => $label,
		);
	}
	
	/**
	 * Render status indicator
	 *
	 * @param array $status Status information
	 */
	private function render_status_indicator( $status ) {
		$label = $status['label'];
		$cdn_color = $status['cdn_color'];
		$server_color = $status['server_color'];
		$cdn_on_cdn = $status['cdn_on_cdn'];
		$server_on_server = $status['server_on_server'];
		
		$cdn_label = $cdn_on_cdn ? __( 'On CDN', 'clockwork-offloader' ) : __( 'Not on CDN', 'clockwork-offloader' );
		$server_label = $server_on_server ? __( 'On Server', 'clockwork-offloader' ) : __( 'Not on Server', 'clockwork-offloader' );
		
		printf(
			'<span class="clockwork-offload-status-indicator" title="%s">
				<i class="fa-solid fa-cloud" style="color: %s;" aria-hidden="true" title="%s"></i>
				<i class="fa-solid fa-computer" style="color: %s; margin-left: 5px;" aria-hidden="true" title="%s"></i>
				<span class="screen-reader-text">%s</span>
			</span>',
			esc_attr( $label ),
			esc_attr( $cdn_color ),
			esc_attr( $cdn_label ),
			esc_attr( $server_color ),
			esc_attr( $server_label ),
			esc_html( $label )
		);
	}
	
	/**
	 * Enqueue scripts for media library grid view
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_media_library_scripts( $hook ) {
		if ( 'upload.php' !== $hook ) {
			return;
		}
		
		// Enqueue Font Awesome
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
			array(),
			'6.5.1'
		);
		
		wp_enqueue_style(
			'clockwork-offloader-media-library',
			CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/css/media-library.css',
			array( 'font-awesome' ),
			CLOCKWORK_OFFLOADER_VERSION
		);
		
		wp_enqueue_script(
			'clockwork-offloader-media-library',
			CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/js/media-library.js',
			array( 'media-grid', 'media' ),
			CLOCKWORK_OFFLOADER_VERSION,
			true
		);
		
		// We'll load statuses on-demand via AJAX to avoid performance issues
		// For now, just pass empty array - statuses will be loaded via AJAX when needed
		$statuses = array();
		
		wp_localize_script(
			'clockwork-offloader-media-library',
			'clockworkOffloaderMedia',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'clockwork_offloader_media_nonce' ),
				'statuses' => $statuses,
				'strings' => array(
					'notOffloaded' => __( 'Not on CDN', 'clockwork-offloader' ),
					'offloadedAndLocal' => __( 'On CDN & Server', 'clockwork-offloader' ),
					'cdnOnly' => __( 'CDN Only', 'clockwork-offloader' ),
					'onCdn' => __( 'On CDN', 'clockwork-offloader' ),
					'notOnCdn' => __( 'Not on CDN', 'clockwork-offloader' ),
					'onServer' => __( 'On Server', 'clockwork-offloader' ),
					'notOnServer' => __( 'Not on Server', 'clockwork-offloader' ),
				),
			)
		);
	}
	
	/**
	 * Add template for grid view indicator
	 */
	public function add_grid_view_template() {
		?>
		<script type="text/html" id="tmpl-clockwork-offload-indicator">
			<# if ( data.status ) { #>
				<span class="clockwork-offload-grid-indicator" title="{{ data.status.label }}">
					<i class="fa-solid fa-cloud" style="color: {{ data.status.cdn_color }};" title="{{ data.status.cdn_on_cdn ? clockworkOffloaderMedia.strings.onCdn : clockworkOffloaderMedia.strings.notOnCdn }}"></i>
					<i class="fa-solid fa-computer" style="color: {{ data.status.server_color }};" title="{{ data.status.server_on_server ? clockworkOffloaderMedia.strings.onServer : clockworkOffloaderMedia.strings.notOnServer }}"></i>
				</span>
			<# } #>
		</script>
		<?php
	}
	
	/**
	 * Add attachment fields for CDN controls
	 *
	 * @param array  $form_fields Existing form fields
	 * @param object $post Attachment post object
	 * @return array Modified form fields
	 */
	public function add_attachment_fields( $form_fields, $post ) {
		// Don't add fields to the left side on post edit page - we use meta box instead
		global $pagenow;
		if ( 'post.php' === $pagenow ) {
			return $form_fields;
		}
		
		$attachment_id = $post->ID;
		$status = $this->get_offload_status( $attachment_id );
		
		$controls_html = '<div class="clockwork-offloader-attachment-controls">';
		
		// Status display
		$controls_html .= '<div class="clockwork-status-display" style="margin-bottom: 15px; padding: 10px; background: #f0f0f1; border-radius: 3px;">';
		$controls_html .= '<strong>' . __( 'CDN Status:', 'clockwork-offloader' ) . '</strong> ';
		$controls_html .= '<span class="clockwork-status-icons" style="margin-left: 10px;">';
		$controls_html .= '<i class="fa-solid fa-cloud" style="color: ' . esc_attr( $status['cdn_color'] ) . '; margin: 0 5px; font-size: 18px;" title="' . esc_attr( $status['cdn_on_cdn'] ? __( 'On CDN', 'clockwork-offloader' ) : __( 'Not on CDN', 'clockwork-offloader' ) ) . '"></i>';
		$controls_html .= '<i class="fa-solid fa-computer" style="color: ' . esc_attr( $status['server_color'] ) . '; margin: 0 5px; font-size: 18px;" title="' . esc_attr( $status['server_on_server'] ? __( 'On Server', 'clockwork-offloader' ) : __( 'Not on Server', 'clockwork-offloader' ) ) . '"></i>';
		$controls_html .= '</span>';
		$controls_html .= '</div>';
		
		// Action buttons
		$controls_html .= '<div class="clockwork-action-buttons" style="display: flex; flex-wrap: wrap; gap: 8px;">';
		
		if ( ! $status['cdn_on_cdn'] ) {
			// Not on CDN - show upload button
			$controls_html .= '<button type="button" class="button button-primary clockwork-action-btn" data-action="offload" data-attachment-id="' . esc_attr( $attachment_id ) . '">';
			$controls_html .= '<i class="fa-solid fa-cloud-arrow-up" style="margin-right: 5px;"></i>';
			$controls_html .= __( 'Upload to CDN', 'clockwork-offloader' );
			$controls_html .= '</button>';
		} else {
			// On CDN - show delete button
			$controls_html .= '<button type="button" class="button button-secondary clockwork-action-btn" data-action="delete-from-cdn" data-attachment-id="' . esc_attr( $attachment_id ) . '" data-on-server="' . ( $status['server_on_server'] ? '1' : '0' ) . '">';
			$controls_html .= '<i class="fa-solid fa-trash" style="margin-right: 5px;"></i>';
			$controls_html .= __( 'Delete from CDN', 'clockwork-offloader' );
			$controls_html .= '</button>';
		}
		
		// Only show download button if file is on CDN but NOT on server
		if ( $status['cdn_on_cdn'] && ! $status['server_on_server'] ) {
			$controls_html .= '<button type="button" class="button button-secondary clockwork-action-btn" data-action="restore" data-attachment-id="' . esc_attr( $attachment_id ) . '">';
			$controls_html .= '<i class="fa-solid fa-download" style="margin-right: 5px;"></i>';
			$controls_html .= __( 'Download from CDN to Server', 'clockwork-offloader' );
			$controls_html .= '</button>';
		}
		
		if ( $status['cdn_on_cdn'] ) {
			// Show S3 URL
			$tracker = new Clockwork_Offloader_Tracker();
			$s3_url = $tracker->get_s3_url( $attachment_id );
			if ( $s3_url ) {
				$controls_html .= '<div style="width: 100%; margin-top: 10px;">';
				$controls_html .= '<label style="display: block; margin-bottom: 5px;"><strong>' . __( 'CDN URL:', 'clockwork-offloader' ) . '</strong></label>';
				$controls_html .= '<input type="text" class="widefat" readonly value="' . esc_attr( $s3_url ) . '" id="clockwork-s3-url-' . esc_attr( $attachment_id ) . '" />';
				$controls_html .= '<button type="button" class="button button-small copy-s3-url" data-url="' . esc_attr( $s3_url ) . '" style="margin-top: 5px;">';
				$controls_html .= __( 'Copy CDN URL', 'clockwork-offloader' );
				$controls_html .= '</button>';
				$controls_html .= '</div>';
			}
		}
		
		$controls_html .= '</div>';
		$controls_html .= '<div class="clockwork-action-spinner" style="display: none; margin-top: 10px;"><span class="spinner is-active"></span></div>';
		$controls_html .= '</div>';
		
		$form_fields['clockwork_offloader_controls'] = array(
			'label' => __( 'CDN Controls', 'clockwork-offloader' ),
			'input' => 'html',
			'html' => $controls_html,
		);
		
		return $form_fields;
	}
	
	/**
	 * Add meta box for attachment edit page
	 *
	 * @param string $post_type Post type
	 */
	public function add_attachment_meta_box( $post_type ) {
		if ( 'attachment' === $post_type ) {
			add_meta_box(
				'clockwork_offloader_controls',
				__( 'CDN Controls', 'clockwork-offloader' ),
				array( $this, 'render_attachment_meta_box' ),
				$post_type,
				'side', // Right sidebar
				'high'
			);
		}
	}
	
	/**
	 * Render meta box content for attachment edit page
	 *
	 * @param WP_Post $post Post object
	 */
	public function render_attachment_meta_box( $post ) {
		$attachment_id = $post->ID;
		$status = $this->get_offload_status( $attachment_id );
		
		?>
		<div class="clockwork-offloader-attachment-controls">
			<!-- Status display -->
			<div class="clockwork-status-display" style="margin-bottom: 15px; padding: 10px; background: #f0f0f1; border-radius: 3px;">
				<strong><?php esc_html_e( 'CDN Status:', 'clockwork-offloader' ); ?></strong>
				<div style="margin-top: 8px;">
					<span class="clockwork-status-icons" style="margin-left: 10px;">
						<i class="fa-solid fa-cloud" style="color: <?php echo esc_attr( $status['cdn_color'] ); ?>; margin: 0 5px; font-size: 18px;" title="<?php echo esc_attr( $status['cdn_on_cdn'] ? __( 'On CDN', 'clockwork-offloader' ) : __( 'Not on CDN', 'clockwork-offloader' ) ); ?>"></i>
						<i class="fa-solid fa-computer" style="color: <?php echo esc_attr( $status['server_color'] ); ?>; margin: 0 5px; font-size: 18px;" title="<?php echo esc_attr( $status['server_on_server'] ? __( 'On Server', 'clockwork-offloader' ) : __( 'Not on Server', 'clockwork-offloader' ) ); ?>"></i>
					</span>
				</div>
			</div>
			
			<!-- Action buttons -->
			<div class="clockwork-action-buttons" style="display: flex; flex-direction: column; gap: 8px;">
				<?php if ( ! $status['cdn_on_cdn'] ) : ?>
					<!-- Not on CDN - show upload button -->
					<button type="button" class="button button-primary clockwork-action-btn" data-action="offload" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>" style="width: 100%;">
						<i class="fa-solid fa-cloud-arrow-up" style="margin-right: 5px;"></i>
						<?php esc_html_e( 'Upload to CDN', 'clockwork-offloader' ); ?>
					</button>
				<?php else : ?>
					<!-- On CDN - show delete button -->
					<button type="button" class="button button-secondary clockwork-action-btn" data-action="delete-from-cdn" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>" data-on-server="<?php echo $status['server_on_server'] ? '1' : '0'; ?>" style="width: 100%;">
						<i class="fa-solid fa-trash" style="margin-right: 5px;"></i>
						<?php esc_html_e( 'Delete from CDN', 'clockwork-offloader' ); ?>
					</button>
				<?php endif; ?>
				
				<?php if ( $status['cdn_on_cdn'] && ! $status['server_on_server'] ) : ?>
					<!-- Only show download button if file is on CDN but NOT on server -->
					<button type="button" class="button button-secondary clockwork-action-btn" data-action="restore" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>" style="width: 100%;">
						<i class="fa-solid fa-download" style="margin-right: 5px;"></i>
						<?php esc_html_e( 'Download from CDN to Server', 'clockwork-offloader' ); ?>
					</button>
				<?php endif; ?>
			</div>
			
			<?php if ( $status['cdn_on_cdn'] ) : ?>
				<?php
				// Show S3 URL
				$tracker = new Clockwork_Offloader_Tracker();
				$s3_url = $tracker->get_s3_url( $attachment_id );
				if ( $s3_url ) :
					?>
					<div style="width: 100%; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
						<label style="display: block; margin-bottom: 5px;"><strong><?php esc_html_e( 'CDN URL:', 'clockwork-offloader' ); ?></strong></label>
						<input type="text" class="widefat" readonly value="<?php echo esc_attr( $s3_url ); ?>" id="clockwork-s3-url-<?php echo esc_attr( $attachment_id ); ?>" style="margin-bottom: 5px;" />
						<button type="button" class="button button-small copy-s3-url" data-url="<?php echo esc_attr( $s3_url ); ?>" style="width: 100%;">
							<?php esc_html_e( 'Copy CDN URL', 'clockwork-offloader' ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			
			<div class="clockwork-action-spinner" style="display: none; margin-top: 10px; text-align: center;">
				<span class="spinner is-active"></span>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Enqueue scripts for attachment details page
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_attachment_details_scripts( $hook ) {
		// Only on media library pages and attachment edit pages
		if ( 'upload.php' !== $hook && 'post.php' !== $hook ) {
			return;
		}
		
		// Only enqueue on attachment edit pages or media library
		global $post;
		if ( 'post.php' === $hook && ( ! $post || 'attachment' !== $post->post_type ) ) {
			return;
		}
		
		// Enqueue Font Awesome if not already enqueued
		if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
			wp_enqueue_style(
				'font-awesome',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
				array(),
				'6.5.1'
			);
		}
		
		wp_enqueue_script(
			'clockwork-offloader-attachment-details',
			CLOCKWORK_OFFLOADER_PLUGIN_URL . 'admin/js/attachment-details.js',
			array( 'jquery', 'media' ),
			CLOCKWORK_OFFLOADER_VERSION,
			true
		);
		
		wp_localize_script(
			'clockwork-offloader-attachment-details',
			'clockworkOffloaderAttachment',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'clockwork_offloader_attachment_nonce' ),
				'strings' => array(
					'offloadSuccess' => __( 'File uploaded to CDN successfully.', 'clockwork-offloader' ),
					'offloadError' => __( 'Failed to upload file to CDN.', 'clockwork-offloader' ),
					'deleteSuccess' => __( 'File deleted from CDN successfully.', 'clockwork-offloader' ),
					'deleteError' => __( 'Failed to delete file from CDN.', 'clockwork-offloader' ),
					'restoreSuccess' => __( 'File downloaded from CDN successfully.', 'clockwork-offloader' ),
					'restoreError' => __( 'Failed to download file from CDN.', 'clockwork-offloader' ),
					'confirmDelete' => __( 'Are you sure you want to delete this file from CDN?', 'clockwork-offloader' ),
					'copyUrl' => __( 'Copy CDN URL', 'clockwork-offloader' ),
				),
			)
		);
	}
}

