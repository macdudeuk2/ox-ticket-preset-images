<?php
/**
 * Ticket handler for applying preset images.
 *
 * Handles setting the WooCommerce product featured image when a ticket is created from a preset.
 *
 * @package OX_Ticket_Preset_Images
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OX_Ticket_Preset_Images_Ticket
 *
 * Handles applying preset images to newly created tickets.
 */
class OX_Ticket_Preset_Images_Ticket {

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
		// Hook into ticket save to apply the featured image.
		add_action( 'event_tickets_after_save_ticket', [ $this, 'maybe_apply_preset_image' ], 20, 4 );

		// Enqueue script to add hidden field when preset is applied.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_preset_application_script' ] );
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
	 * Enqueue script that adds hidden field when a preset is applied.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_preset_application_script( string $hook ): void {
		if ( ! $this->is_ticket_edit_screen() ) {
			return;
		}

		// Get all preset images for the localization.
		$preset_images = $this->get_all_preset_images();

		wp_enqueue_script(
			'ox-ticket-preset-images-apply',
			OX_TICKET_PRESET_IMAGES_URL . 'assets/js/apply-preset.js',
			[ 'jquery' ],
			OX_TICKET_PRESET_IMAGES_VERSION,
			true
		);

		wp_localize_script(
			'ox-ticket-preset-images-apply',
			'oxPresetImages',
			[
				'presets' => $preset_images,
				'nonce'   => wp_create_nonce( 'ox_ticket_preset_images' ),
			]
		);
	}

	/**
	 * Get all preset images as an array keyed by preset ID.
	 *
	 * @return array<int, int> Array of preset_id => image_id.
	 */
	private function get_all_preset_images(): array {
		global $wpdb;

		$images = [];

		// Get all our preset image options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$options = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'ox_preset_featured_image_%'"
		);

		if ( $options ) {
			foreach ( $options as $option ) {
				// Extract preset ID from option name.
				$preset_id = (int) str_replace( 'ox_preset_featured_image_', '', $option->option_name );
				if ( $preset_id > 0 ) {
					$images[ $preset_id ] = (int) $option->option_value;
				}
			}
		}

		return $images;
	}

	/**
	 * Maybe apply preset image to a newly created ticket.
	 *
	 * This hook fires after any ticket is saved (WooCommerce, EDD, RSVP, etc.)
	 *
	 * @param int                           $post_id  The event post ID.
	 * @param Tribe__Tickets__Ticket_Object $ticket   The ticket object.
	 * @param array                         $raw_data The raw ticket data.
	 * @param string                        $class    The commerce class name.
	 */
	public function maybe_apply_preset_image( int $post_id, $ticket, array $raw_data, string $class ): void {
		// Only process WooCommerce tickets.
		if ( false === strpos( $class, 'WooCommerce' ) ) {
			return;
		}

		// Check if we have a preset image ID in the raw data.
		$image_id = 0;

		// Check our custom hidden field.
		if ( ! empty( $raw_data['ox_preset_image_id'] ) ) {
			$image_id = absint( $raw_data['ox_preset_image_id'] );
		}

		// Also check tribe-ticket array (fallback).
		if ( ! $image_id && ! empty( $raw_data['tribe-ticket']['ox_preset_image_id'] ) ) {
			$image_id = absint( $raw_data['tribe-ticket']['ox_preset_image_id'] );
		}

		// If no image ID found, nothing to do.
		if ( ! $image_id ) {
			return;
		}

		// Verify the attachment exists.
		if ( ! wp_attachment_is_image( $image_id ) ) {
			return;
		}

		// Get the ticket ID (WooCommerce product ID).
		$ticket_id = $ticket->ID;

		if ( ! $ticket_id ) {
			return;
		}

		// Set the featured image on the WooCommerce product.
		$this->set_product_image( $ticket_id, $image_id );
	}

	/**
	 * Set the featured image on a WooCommerce product.
	 *
	 * @param int $product_id    The WooCommerce product ID.
	 * @param int $attachment_id The attachment ID.
	 * @return bool Whether the image was set successfully.
	 */
	private function set_product_image( int $product_id, int $attachment_id ): bool {
		// Try using WooCommerce's product API first.
		$product = wc_get_product( $product_id );

		if ( $product ) {
			$product->set_image_id( $attachment_id );
			$product->save();

			/**
			 * Fires after a preset image is applied to a ticket product.
			 *
			 * @param int $product_id    The WooCommerce product ID.
			 * @param int $attachment_id The attachment ID that was set.
			 */
			do_action( 'ox_ticket_preset_image_applied', $product_id, $attachment_id );

			return true;
		}

		// Fallback to WordPress core function.
		$result = set_post_thumbnail( $product_id, $attachment_id );

		if ( $result ) {
			/** This action is documented above. */
			do_action( 'ox_ticket_preset_image_applied', $product_id, $attachment_id );
		}

		return (bool) $result;
	}
}
