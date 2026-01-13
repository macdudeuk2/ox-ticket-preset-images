<?php
/**
 * Plugin Name: OX Ticket Preset Images
 * Plugin URI: https://example.com
 * Description: Adds featured image support to Event Tickets Plus presets for WooCommerce tickets.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: OX
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ox-ticket-preset-images
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'OX_TICKET_PRESET_IMAGES_VERSION', '1.0.0' );
define( 'OX_TICKET_PRESET_IMAGES_FILE', __FILE__ );
define( 'OX_TICKET_PRESET_IMAGES_DIR', plugin_dir_path( __FILE__ ) );
define( 'OX_TICKET_PRESET_IMAGES_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
final class OX_Ticket_Preset_Images {

	/**
	 * Plugin instance.
	 *
	 * @var OX_Ticket_Preset_Images|null
	 */
	private static ?OX_Ticket_Preset_Images $instance = null;

	/**
	 * Admin handler instance.
	 *
	 * @var OX_Ticket_Preset_Images_Admin|null
	 */
	private ?OX_Ticket_Preset_Images_Admin $admin = null;

	/**
	 * Ticket handler instance.
	 *
	 * @var OX_Ticket_Preset_Images_Ticket|null
	 */
	private ?OX_Ticket_Preset_Images_Ticket $ticket = null;

	/**
	 * Get plugin instance.
	 *
	 * @return OX_Ticket_Preset_Images
	 */
	public static function instance(): OX_Ticket_Preset_Images {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies(): void {
		require_once OX_TICKET_PRESET_IMAGES_DIR . 'includes/class-ox-ticket-preset-images-admin.php';
		require_once OX_TICKET_PRESET_IMAGES_DIR . 'includes/class-ox-ticket-preset-images-ticket.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// Check dependencies before initializing.
		add_action( 'plugins_loaded', [ $this, 'check_dependencies' ], 20 );

		// Register activation/deactivation hooks.
		register_activation_hook( OX_TICKET_PRESET_IMAGES_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( OX_TICKET_PRESET_IMAGES_FILE, [ $this, 'deactivate' ] );
	}

	/**
	 * Check if required plugins are active.
	 */
	public function check_dependencies(): void {
		// Check for Event Tickets Plus.
		if ( ! class_exists( 'Tribe__Tickets_Plus__Main' ) ) {
			add_action( 'admin_notices', [ $this, 'missing_tickets_plus_notice' ] );
			return;
		}

		// Check for WooCommerce.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'missing_woocommerce_notice' ] );
			return;
		}

		// All dependencies met - initialize the plugin.
		$this->init();
	}

	/**
	 * Initialize plugin functionality.
	 */
	private function init(): void {
		$this->admin  = new OX_Ticket_Preset_Images_Admin();
		$this->ticket = new OX_Ticket_Preset_Images_Ticket();
	}

	/**
	 * Plugin activation.
	 */
	public function activate(): void {
		// Flush rewrite rules if needed.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate(): void {
		// Clean up if needed.
		flush_rewrite_rules();
	}

	/**
	 * Admin notice for missing Event Tickets Plus.
	 */
	public function missing_tickets_plus_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'OX Ticket Preset Images', 'ox-ticket-preset-images' ); ?>:</strong>
				<?php esc_html_e( 'This plugin requires Event Tickets Plus to be installed and activated.', 'ox-ticket-preset-images' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Admin notice for missing WooCommerce.
	 */
	public function missing_woocommerce_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'OX Ticket Preset Images', 'ox-ticket-preset-images' ); ?>:</strong>
				<?php esc_html_e( 'This plugin requires WooCommerce to be installed and activated.', 'ox-ticket-preset-images' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get the stored image ID for a preset.
	 *
	 * @param int $preset_id The preset ID.
	 * @return int The attachment ID, or 0 if not set.
	 */
	public static function get_preset_image( int $preset_id ): int {
		return (int) get_option( "ox_preset_featured_image_{$preset_id}", 0 );
	}

	/**
	 * Set the image ID for a preset.
	 *
	 * @param int $preset_id     The preset ID.
	 * @param int $attachment_id The attachment ID.
	 * @return bool Whether the option was updated.
	 */
	public static function set_preset_image( int $preset_id, int $attachment_id ): bool {
		if ( $attachment_id > 0 ) {
			return update_option( "ox_preset_featured_image_{$preset_id}", $attachment_id, false );
		}
		return delete_option( "ox_preset_featured_image_{$preset_id}" );
	}

	/**
	 * Delete the image option for a preset.
	 *
	 * @param int $preset_id The preset ID.
	 * @return bool Whether the option was deleted.
	 */
	public static function delete_preset_image( int $preset_id ): bool {
		return delete_option( "ox_preset_featured_image_{$preset_id}" );
	}
}

/**
 * Initialize the plugin.
 *
 * @return OX_Ticket_Preset_Images
 */
function ox_ticket_preset_images(): OX_Ticket_Preset_Images {
	return OX_Ticket_Preset_Images::instance();
}

// Initialize the plugin.
ox_ticket_preset_images();
