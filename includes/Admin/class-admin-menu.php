<?php
namespace YukDigitalz\AIConnectorGoogle\Admin;

use YukDigitalz\AIConnectorGoogle\Settings;
use YukDigitalz\AIConnectorGoogle\Security;
use YukDigitalz\AIConnectorGoogle\Telemetry_Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Menu
 *
 * Registers the administration dashboard and enqueues assets for YukDigitalz Connector for Google AI.
 */
class Admin_Menu {

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Singleton instance.
	 *
	 * @var Admin_Menu|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Admin_Menu
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
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register Admin Menu.
	 */
	public function register_menu_page() {
		$this->hook_suffix = add_menu_page(
			__( 'YukDigitalz Connector for Google AI', 'yukdigitalz-connector-for-google-ai' ),
			__( 'YukDigitalz Connector AI', 'yukdigitalz-connector-for-google-ai' ),
			'manage_options',
			'yukdiconfo',
			array( $this, 'render_admin_page' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="4"/></svg>' ),
			30
		);
	}

	/**
	 * Enqueue Admin CSS and JS assets.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$plugin_url = YUKDICONFO_PLUGIN_URL;
		$version    = YUKDICONFO_VERSION;


		// CSS
		wp_enqueue_style(
			'yukdiconfo-admin-style',
			$plugin_url . 'assets/css/admin-style.css',
			array(),
			$version
		);

		// JS
		wp_enqueue_script(
			'yukdiconfo-admin-script',
			$plugin_url . 'assets/js/admin-script.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			$version,
			true
		);



		$settings = Settings::get_instance();

		// Localize script data.
		wp_localize_script(
			'yukdiconfo-admin-script',
			'yukdiconfoAdminData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'yukdiconfo_admin_nonce' ),
				'restUrl'   => esc_url_raw( rest_url( 'yukdiconfo/v1/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'strings'   => array(
					'saving'        => __( 'Saving settings...', 'yukdigitalz-connector-for-google-ai' ),
					'saved'         => __( 'Settings saved successfully!', 'yukdigitalz-connector-for-google-ai' ),
					'testing'       => __( 'Testing Google Gemini API connectivity...', 'yukdigitalz-connector-for-google-ai' ),
					'fetching'      => __( 'Fetching latest models from Google AI Studio...', 'yukdigitalz-connector-for-google-ai' ),
					'modelsFetched' => __( 'Models updated directly from Google AI Studio!', 'yukdigitalz-connector-for-google-ai' ),
					'copied'        => __( 'Copied to clipboard!', 'yukdigitalz-connector-for-google-ai' ),
					'confirmClear'  => __( 'Are you sure you want to clear all telemetry audit logs?', 'yukdigitalz-connector-for-google-ai' ),
				),
			)
		);

	}

	/**
	 * Render the main plugin settings dashboard.
	 */
	public function render_admin_page() {
		if ( ! Security::verify_admin() ) {
			wp_die( esc_html__( 'Anda tidak memiliki hak akses untuk membuka halaman ini.', 'yukdigitalz-connector-for-google-ai' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended



		$valid_tabs = array( 'overview', 'providers', 'failover', 'logs' );

		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'overview';
		}

		$settings   = Settings::get_instance();
		$stats      = Telemetry_Logger::get_stats();
		$plugin_dir = YUKDICONFO_PLUGIN_DIR;

		?>
		<div class="wrap yuk-ai-wrap">
			<?php
			include $plugin_dir . 'includes/Admin/views/header.php';
			?>
			<div class="yuk-ai-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'providers':
						include $plugin_dir . 'includes/Admin/views/tab-providers.php';
						break;
					case 'failover':
						include $plugin_dir . 'includes/Admin/views/tab-failover.php';
						break;
					case 'logs':
						include $plugin_dir . 'includes/Admin/views/tab-logs.php';
						break;
					case 'overview':
					default:
						include $plugin_dir . 'includes/Admin/views/tab-overview.php';
						break;
				}
				?>
			</div>
		</div>
		<?php

	}
}
