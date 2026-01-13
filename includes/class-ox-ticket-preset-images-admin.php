<?php
/**
 * Admin functionality for preset images.
 *
 * Handles the image field injection in the preset form and save operations.
 *
 * @package OX_Ticket_Preset_Images
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OX_Ticket_Preset_Images_Admin
 *
 * Handles admin-side functionality for preset images.
 */
class OX_Ticket_Preset_Images_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// Enqueue admin scripts on preset form page.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Inject image field HTML via admin_footer on preset form page.
		add_action( 'admin_footer', [ $this, 'render_image_field_template' ] );

		// Handle preset save - intercept before TEC's handler.
		add_action( 'admin_post_tec_tickets_save_preset', [ $this, 'handle_preset_save' ], 5 );

		// Process pending preset image after redirect from new preset creation.
		add_action( 'admin_init', [ $this, 'process_pending_preset_image' ], 5 );

		// Handle preset deletion - clean up our option.
		add_action( 'admin_init', [ $this, 'handle_preset_delete_cleanup' ], 6 );

		// Add field to "Save as Preset" modal.
		add_action( 'tec_tickets_plus_save_as_preset_modal_fields', [ $this, 'render_modal_image_field' ] );

		// AJAX handler for saving preset image from modal.
		add_action( 'wp_ajax_ox_save_preset_image', [ $this, 'ajax_save_preset_image' ] );

		// AJAX handler for getting ticket featured image.
		add_action( 'wp_ajax_ox_get_ticket_image', [ $this, 'ajax_get_ticket_image' ] );

		// Hook into TEC's "Save as Preset" AJAX to capture our image field.
		add_action( 'wp_ajax_tec_tickets_plus_save_as_preset', [ $this, 'intercept_save_as_preset_ajax' ], 5 );
	}

	/**
	 * Check if we're on the preset form page.
	 *
	 * @return bool
	 */
	private function is_preset_form_page(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		return 'tec-tickets-preset-form' === $page;
	}

	/**
	 * Check if we're on a ticketable post edit screen.
	 *
	 * @return bool
	 */
	private function is_ticket_edit_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		global $pagenow;
		if ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
			return false;
		}

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		$ticketable_post_types = (array) tribe_get_option( 'ticket-enabled-post-types', [] );
		return in_array( $post->post_type, $ticketable_post_types, true );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Load on preset form page.
		if ( $this->is_preset_form_page() ) {
			$this->enqueue_preset_form_assets();
			return;
		}

		// Load on ticket edit screens (for Save as Preset modal).
		if ( $this->is_ticket_edit_screen() ) {
			$this->enqueue_ticket_edit_assets();
		}
	}

	/**
	 * Enqueue assets for preset form page.
	 */
	private function enqueue_preset_form_assets(): void {
		// WordPress media library.
		wp_enqueue_media();

		// Our admin script.
		wp_enqueue_script(
			'ox-ticket-preset-images-admin',
			OX_TICKET_PRESET_IMAGES_URL . 'assets/js/admin.js',
			[ 'jquery', 'wp-util' ],
			OX_TICKET_PRESET_IMAGES_VERSION,
			true
		);

		// Get current preset ID and image.
		$preset_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$image_id  = $preset_id ? OX_Ticket_Preset_Images::get_preset_image( $preset_id ) : 0;
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		wp_localize_script(
			'ox-ticket-preset-images-admin',
			'oxTicketPresetImages',
			[
				'presetId'       => $preset_id,
				'imageId'        => $image_id,
				'imageUrl'       => $image_url,
				'nonce'          => wp_create_nonce( 'ox_ticket_preset_images' ),
				'i18n'           => [
					'selectImage'  => __( 'Select Featured Image', 'ox-ticket-preset-images' ),
					'removeImage'  => __( 'Remove Image', 'ox-ticket-preset-images' ),
					'useImage'     => __( 'Use this image', 'ox-ticket-preset-images' ),
					'fieldLabel'   => __( 'Featured Image', 'ox-ticket-preset-images' ),
					'fieldDesc'    => __( 'This image will be set as the WooCommerce product image when a ticket is created from this preset.', 'ox-ticket-preset-images' ),
				],
				'context'        => 'preset_form',
			]
		);

		// Our admin styles.
		wp_enqueue_style(
			'ox-ticket-preset-images-admin',
			OX_TICKET_PRESET_IMAGES_URL . 'assets/css/admin.css',
			[],
			OX_TICKET_PRESET_IMAGES_VERSION
		);
	}

	/**
	 * Enqueue assets for ticket edit screens.
	 */
	private function enqueue_ticket_edit_assets(): void {
		// WordPress media library.
		wp_enqueue_media();

		// Our admin script.
		wp_enqueue_script(
			'ox-ticket-preset-images-modal',
			OX_TICKET_PRESET_IMAGES_URL . 'assets/js/modal.js',
			[ 'jquery' ],
			OX_TICKET_PRESET_IMAGES_VERSION,
			true
		);

		wp_localize_script(
			'ox-ticket-preset-images-modal',
			'oxTicketPresetImages',
			[
				'nonce'   => wp_create_nonce( 'ox_ticket_preset_images' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => [
					'selectImage' => __( 'Select Featured Image', 'ox-ticket-preset-images' ),
					'removeImage' => __( 'Remove Image', 'ox-ticket-preset-images' ),
					'useImage'    => __( 'Use this image', 'ox-ticket-preset-images' ),
					'fieldLabel'  => __( 'Featured Image', 'ox-ticket-preset-images' ),
				],
				'context' => 'ticket_edit',
			]
		);

		// Our admin styles.
		wp_enqueue_style(
			'ox-ticket-preset-images-admin',
			OX_TICKET_PRESET_IMAGES_URL . 'assets/css/admin.css',
			[],
			OX_TICKET_PRESET_IMAGES_VERSION
		);
	}

	/**
	 * Render the image field template for JavaScript injection.
	 */
	public function render_image_field_template(): void {
		if ( ! $this->is_preset_form_page() ) {
			return;
		}
		?>
		<script type="text/html" id="tmpl-ox-preset-image-field">
			<div class="tec-tickets-plus-preset__field ox-preset-image-field">
				<label class="tec-tickets-plus-preset__field-label" for="ox-preset-featured-image">
					{{ data.i18n.fieldLabel }}
				</label>
				<div class="tec-tickets-plus-preset__field-wrapper">
					<div class="ox-preset-image-preview">
						<# if ( data.imageUrl ) { #>
							<img src="{{ data.imageUrl }}" alt="" />
						<# } #>
					</div>
					<input type="hidden" name="ox_preset_featured_image" id="ox-preset-featured-image" value="{{ data.imageId }}" />
					<div class="ox-preset-image-buttons">
						<button type="button" class="button ox-select-image">
							{{ data.i18n.selectImage }}
						</button>
						<button type="button" class="button ox-remove-image" <# if ( ! data.imageId ) { #>style="display:none;"<# } #>>
							{{ data.i18n.removeImage }}
						</button>
					</div>
					<p class="description">{{ data.i18n.fieldDesc }}</p>
				</div>
			</div>
		</script>
		<?php
	}

	/**
	 * Render image field for "Save as Preset" modal.
	 */
	public function render_modal_image_field(): void {
		?>
		<div class="tec-tickets-plus-preset-modal__field ox-preset-modal-image-field">
			<label for="ox-modal-preset-image" class="tec-tickets-plus-preset-modal__label">
				<?php esc_html_e( 'Featured Image', 'ox-ticket-preset-images' ); ?>
			</label>
			<div class="ox-preset-image-preview"></div>
			<input type="hidden" name="preset[featured_image_id]" id="ox-modal-preset-image" value="" />
			<div class="ox-preset-image-buttons">
				<button type="button" class="button ox-select-image">
					<?php esc_html_e( 'Select Image', 'ox-ticket-preset-images' ); ?>
				</button>
				<button type="button" class="button ox-remove-image" style="display:none;">
					<?php esc_html_e( 'Remove', 'ox-ticket-preset-images' ); ?>
				</button>
				<button type="button" class="button ox-use-ticket-image" style="display:none;">
					<?php esc_html_e( 'Use Ticket Image', 'ox-ticket-preset-images' ); ?>
				</button>
			</div>
			<p class="description">
				<?php esc_html_e( 'Optional: Set a featured image for tickets created from this preset.', 'ox-ticket-preset-images' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle preset save - save our image option.
	 *
	 * This runs at priority 5, before TEC's handler at priority 10.
	 * TEC's handler redirects and exits, so we must handle everything here.
	 */
	public function handle_preset_save(): void {
		// Verify nonce (TEC's nonce).
		if ( ! check_admin_referer( 'save_ticket_preset', 'ticket_preset_nonce' ) ) {
			return;
		}

		// Get image ID from POST.
		$image_id = isset( $_POST['ox_preset_featured_image'] ) ? absint( $_POST['ox_preset_featured_image'] ) : 0;

		// Get preset ID if editing existing preset.
		$preset_id = isset( $_POST['preset_id'] ) ? absint( $_POST['preset_id'] ) : 0;

		if ( $preset_id ) {
			// Editing existing preset - save immediately.
			OX_Ticket_Preset_Images::set_preset_image( $preset_id, $image_id );
		} else {
			// Creating new preset - store in user transient for processing after redirect.
			// TEC will redirect after creating the preset, so we save pending data.
			$user_id = get_current_user_id();
			set_transient( "ox_pending_preset_image_{$user_id}", $image_id, 120 );
		}
	}

	/**
	 * Process pending preset image after redirect from new preset creation.
	 *
	 * Called on admin_init to check if we have a pending image to save.
	 */
	public function process_pending_preset_image(): void {
		$user_id  = get_current_user_id();
		$image_id = get_transient( "ox_pending_preset_image_{$user_id}" );

		if ( false === $image_id ) {
			return;
		}

		// Delete the transient immediately to prevent duplicate processing.
		delete_transient( "ox_pending_preset_image_{$user_id}" );

		// Only process if we're on the presets page (after redirect from save).
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'tec-tickets-admin-tickets' !== $page || 'presets' !== $tab ) {
			return;
		}

		// Get the most recently created preset (should be the one we just created).
		global $wpdb;
		$table_name = $wpdb->prefix . 'tec_ticket_groups';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$latest_id = $wpdb->get_var( "SELECT MAX(id) FROM {$table_name}" );

		if ( $latest_id && $image_id ) {
			OX_Ticket_Preset_Images::set_preset_image( (int) $latest_id, (int) $image_id );
		}
	}

	/**
	 * Clean up image option when a preset is deleted.
	 */
	public function handle_preset_delete_cleanup(): void {
		if ( 'delete-preset' !== ( $_GET['action'] ?? '' ) ) {
			return;
		}

		$preset_id = isset( $_GET['preset_id'] ) ? absint( $_GET['preset_id'] ) : 0;
		
		if ( $preset_id ) {
			OX_Ticket_Preset_Images::delete_preset_image( $preset_id );
		}
	}

	/**
	 * AJAX handler for saving preset image from modal.
	 */
	public function ajax_save_preset_image(): void {
		check_ajax_referer( 'ox_ticket_preset_images', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ox-ticket-preset-images' ) ] );
		}

		$preset_id = isset( $_POST['preset_id'] ) ? absint( $_POST['preset_id'] ) : 0;
		$image_id  = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;

		if ( ! $preset_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid preset ID.', 'ox-ticket-preset-images' ) ] );
		}

		OX_Ticket_Preset_Images::set_preset_image( $preset_id, $image_id );

		wp_send_json_success( [ 'message' => __( 'Image saved.', 'ox-ticket-preset-images' ) ] );
	}

	/**
	 * AJAX handler for getting a ticket's featured image.
	 */
	public function ajax_get_ticket_image(): void {
		check_ajax_referer( 'ox_ticket_preset_images', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ox-ticket-preset-images' ) ] );
		}

		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;

		if ( ! $ticket_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid ticket ID.', 'ox-ticket-preset-images' ) ] );
		}

		$image_id  = (int) get_post_thumbnail_id( $ticket_id );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		wp_send_json_success( [
			'image_id'  => $image_id,
			'image_url' => $image_url,
		] );
	}

	/**
	 * Intercept TEC's "Save as Preset" AJAX to store the image for later.
	 *
	 * This runs at priority 5, before TEC's handler at priority 10.
	 * We store the image ID in a transient, then hook into a later action
	 * to save it with the correct preset ID.
	 */
	public function intercept_save_as_preset_ajax(): void {
		// Get the image ID from the preset data.
		$preset_data = isset( $_POST['preset'] ) ? $_POST['preset'] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$image_id    = isset( $preset_data['featured_image_id'] ) ? absint( $preset_data['featured_image_id'] ) : 0;

		if ( $image_id ) {
			// Store temporarily - we'll save it after TEC creates the preset.
			set_transient( 'ox_ajax_preset_image', $image_id, 60 );

			// Hook to run after TEC's AJAX handler completes.
			add_filter( 'wp_ajax_tec_tickets_plus_save_as_preset', [ $this, 'save_ajax_preset_image' ], 15 );
		}
	}

	/**
	 * Save the preset image after TEC's AJAX handler creates the preset.
	 *
	 * Note: This approach has a limitation - TEC's handler calls wp_send_json_success()
	 * which exits, so this filter won't actually run. We need a different approach.
	 * Instead, we'll use JavaScript to make a follow-up AJAX call.
	 */
	public function save_ajax_preset_image(): void {
		// This won't run because TEC exits in their handler.
		// The JavaScript in modal.js will handle saving via a separate AJAX call.
	}
}
