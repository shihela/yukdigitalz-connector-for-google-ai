<?php
/**
 * Plugin Name:       YukDigitalz Connector for Google AI
 * Plugin URI:        https://yukdigitalz.com/yukdigitalz-connector-for-google-ai
 * Description:       Dedicated Google AI Studio & Gemini API connector for WordPress: Switch Gemini models dynamically, prevent 503/429 high demand errors with auto-failover, rotate API keys, and track detailed usage analytics cleanly.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Tested up to:      7.0
 * Requires PHP:      7.4

 * Author:            shihela
 * Author URI:        https://yukdigitalz.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yukdigitalz-connector-for-google-ai
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants.
define( 'YUKDICONFO_VERSION', '1.0.0' );
define( 'YUKDICONFO_PLUGIN_FILE', __FILE__ );
define( 'YUKDICONFO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YUKDICONFO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YUKDICONFO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader inclusion.
 */
require_once YUKDICONFO_PLUGIN_DIR . 'includes/class-autoloader.php';

// Register autoloader for YukDigitalz\AIConnectorGoogle namespace.
\YukDigitalz\AIConnectorGoogle\Autoloader::register();

/**
 * Public Helper Functions and Global APIs.
 */
require_once YUKDICONFO_PLUGIN_DIR . 'includes/Api/class-public-helpers.php';

/**
 * Main Plugin Initialization Singleton.
 */
final class Yukdiconfo_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Yukdiconfo_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Yukdiconfo_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Initialize Compatibility Bridge for 3rd party AI plugins calling Google API.
		\YukDigitalz\AIConnectorGoogle\Engine\Compatibility_Bridge::get_instance();

		// Initialize REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Initialize Admin Interface if in WP-Admin.
		if ( is_admin() ) {
			\YukDigitalz\AIConnectorGoogle\Admin\Admin_Menu::get_instance();
			\YukDigitalz\AIConnectorGoogle\Admin\Ajax_Handler::get_instance();
		}

		// Load plugin textdomain for translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$rest_controller = new \YukDigitalz\AIConnectorGoogle\Api\Rest_Controller();
		$rest_controller->register_routes();
	}

	/**
	 * Load translation textdomain.
	 */
	public function load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		load_plugin_textdomain(
			'yukdigitalz-connector-for-google-ai',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}


	/**
	 * Activation hook callback.
	 */
	public static function activate() {
		// Set default settings if not already present.
		$settings = \YukDigitalz\AIConnectorGoogle\Settings::get_instance();
		$settings->ensure_defaults();

		// Create database table for telemetry logs.
		\YukDigitalz\AIConnectorGoogle\Telemetry_Logger::create_table();

		// Flush rewrite rules for REST endpoints if needed.
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook callback.
	 */
	public static function deactivate() {
		// Clean up any transient or scheduled tasks.
		flush_rewrite_rules();
	}
}

// Register lifecycle hooks.
register_activation_hook( __FILE__, array( 'Yukdiconfo_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Yukdiconfo_Plugin', 'deactivate' ) );

// Run plugin.
add_action( 'plugins_loaded', array( 'Yukdiconfo_Plugin', 'get_instance' ) );

