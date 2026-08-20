<?php
namespace YukDigitalz\AIConnectorGoogle\Admin;

use YukDigitalz\AIConnectorGoogle\Settings;
use YukDigitalz\AIConnectorGoogle\Security;
use YukDigitalz\AIConnectorGoogle\Telemetry_Logger;
use YukDigitalz\AIConnectorGoogle\Providers\Provider_Factory;
use YukDigitalz\AIConnectorGoogle\Engine\Failover_Router;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax_Handler
 *
 * Handles AJAX actions for YukDigitalz Connector for Google AI dashboard.
 */
class Ajax_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var Ajax_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Ajax_Handler
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
	 * Register AJAX hooks.
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_yukdiconfo_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_yukdiconfo_test_key', array( $this, 'ajax_test_key' ) );
		add_action( 'wp_ajax_yukdiconfo_fetch_models', array( $this, 'ajax_fetch_models' ) );
		add_action( 'wp_ajax_yukdiconfo_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_yukdiconfo_playground_generate', array( $this, 'ajax_playground_generate' ) );
	}

	private function verify_security() {
		if ( ! Security::verify_admin( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied: Insufficient permissions.', 'yukdigitalz-connector-for-google-ai' ) ), 403 );
		}

		$nonce_valid = check_ajax_referer( 'yukdiconfo_admin_nonce', 'nonce', false );

		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security session (Expired nonce). Please refresh your admin page.', 'yukdigitalz-connector-for-google-ai' ) ), 403 );
		}
	}

	public function ajax_save_settings() {
		check_ajax_referer( 'yukdiconfo_admin_nonce', 'nonce' );
		$this->verify_security();

		$settings  = Settings::get_instance();
		$updated   = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		$form_type = isset( $_POST['form_type'] ) ? sanitize_key( wp_unslash( $_POST['form_type'] ) ) : '';

		// 1. Google Gemini Key
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['gemini_api_key'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$raw = trim( sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) ) );
			if ( ! empty( $raw ) && strpos( $raw, '••••' ) === false ) {
				$updated['gemini_api_key'] = Security::encrypt( $raw );
			} elseif ( empty( $raw ) ) {
				$updated['gemini_api_key'] = '';
			}
		}

		// Backup keys
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['gemini_backup_keys'] ) && is_array( $_POST['gemini_backup_keys'] ) ) {
			$existing_backups  = $settings->get( 'gemini_backup_keys', array() );
			$encrypted_backups = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$raw_backups       = array_map( 'sanitize_text_field', wp_unslash( $_POST['gemini_backup_keys'] ) );
			foreach ( $raw_backups as $idx => $bk ) {
				$raw = trim( $bk );
				if ( ! empty( $raw ) ) {
					if ( strpos( $raw, '••••' ) !== false && isset( $existing_backups[ $idx ] ) ) {
						$encrypted_backups[] = $existing_backups[ $idx ];
					} else {
						$encrypted_backups[] = Security::encrypt( $raw );
					}
				}
			}
			$updated['gemini_backup_keys'] = $encrypted_backups;
		}

		// Model Settings
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['primary_model'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['primary_model'] = sanitize_text_field( wp_unslash( $_POST['primary_model'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['custom_model_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['custom_model_id'] = sanitize_text_field( wp_unslash( $_POST['custom_model_id'] ) );
		}

		// Fallback Hierarchy
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['fallback_models'] ) && is_array( $_POST['fallback_models'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$fallbacks                  = array_map( 'sanitize_text_field', wp_unslash( $_POST['fallback_models'] ) );
			$updated['fallback_models'] = array_values( array_unique( array_filter( $fallbacks ) ) );
		}

		// Switches (Only process if failover form submitted)
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( 'failover' === $form_type || isset( $_POST['failover_submitted'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['enable_failover']                = ! empty( $_POST['enable_failover'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['enable_auto_retry']              = ! empty( $_POST['enable_auto_retry'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['failover_on_503']                = ! empty( $_POST['failover_on_503'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['failover_on_429']                = ! empty( $_POST['failover_on_429'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['failover_on_500']                = ! empty( $_POST['failover_on_500'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['failover_on_timeout']            = ! empty( $_POST['failover_on_timeout'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['enable_third_party_interceptor'] = isset( $_POST['enable_third_party_interceptor'] ) ? ! empty( $_POST['enable_third_party_interceptor'] ) : $settings->get( 'enable_third_party_interceptor', true );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['enable_circuit_breaker']         = isset( $_POST['enable_circuit_breaker'] ) ? ! empty( $_POST['enable_circuit_breaker'] ) : $settings->get( 'enable_circuit_breaker', true );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['cooldown_duration_sec'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['cooldown_duration_sec'] = max( 10, min( 1800, intval( wp_unslash( $_POST['cooldown_duration_sec'] ) ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['max_retries_per_model'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['max_retries_per_model'] = max( 1, min( 5, intval( wp_unslash( $_POST['max_retries_per_model'] ) ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['retry_delay_ms'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['retry_delay_ms'] = max( 100, min( 5000, intval( wp_unslash( $_POST['retry_delay_ms'] ) ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['request_timeout_sec'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['request_timeout_sec'] = max( 5, min( 120, intval( wp_unslash( $_POST['request_timeout_sec'] ) ) ) );
		}

		// Generation parameters
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['default_temperature'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['default_temperature'] = max( 0.0, min( 2.0, floatval( wp_unslash( $_POST['default_temperature'] ) ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['default_top_p'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['default_top_p'] = max( 0.0, min( 1.0, floatval( wp_unslash( $_POST['default_top_p'] ) ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['default_max_tokens'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['default_max_tokens'] = max( 128, min( 32768, intval( wp_unslash( $_POST['default_max_tokens'] ) ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		if ( isset( $_POST['system_instruction'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
			$updated['system_instruction'] = sanitize_textarea_field( wp_unslash( $_POST['system_instruction'] ) );
		}

		$settings->update( $updated );

		wp_send_json_success(
			array(
				'message'  => __( 'Gemini AI settings saved successfully.', 'yukdigitalz-connector-for-google-ai' ),
				'settings' => $settings->get_all(),
			)
		);
	}

	public function ajax_test_key() {
		check_ajax_referer( 'yukdiconfo_admin_nonce', 'nonce' );
		$this->verify_security();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		$provider_name = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'gemini';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		$api_key       = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $api_key ) || strpos( $api_key, '••••' ) !== false ) {
			$api_key = Settings::get_instance()->get_gemini_api_key();
		}

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid Google AI Studio API key before testing.', 'yukdigitalz-connector-for-google-ai' ) ) );
		}

		$start_time = microtime( true );
		$provider   = Provider_Factory::get_provider( $provider_name );

		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported AI provider.', 'yukdigitalz-connector-for-google-ai' ) ) );
		}

		$test_model = Settings::get_instance()->get_effective_model();
		$options    = array(
			'api_key'       => $api_key,
			'model'         => $test_model,
			'max_tokens'    => 20,
			'client_source' => 'admin_key_test',
		);

		$result  = $provider->generate_text( 'Respond with "OK" to verify connectivity.', $options );
		$latency = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: error code, 2: error message */
						__( 'API Error [%1$s]: %2$s', 'yukdigitalz-connector-for-google-ai' ),
						esc_html( $result->get_error_code() ),
						esc_html( $result->get_error_message() )
					),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Connection test successful! Google Gemini API is online and healthy.', 'yukdigitalz-connector-for-google-ai' ),
				'latency'    => $latency,
				'model_used' => $test_model,
			)
		);
	}

	public function ajax_fetch_models() {
		check_ajax_referer( 'yukdiconfo_admin_nonce', 'nonce' );
		$this->verify_security();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		$provider_name = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'gemini';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		$api_key       = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $api_key ) || strpos( $api_key, '••••' ) !== false ) {
			$api_key = Settings::get_instance()->get_gemini_api_key();
		}

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Primary API Key is required to fetch dynamic models from Google AI Studio.', 'yukdigitalz-connector-for-google-ai' ) ) );
		}

		$provider = Provider_Factory::get_provider( $provider_name );
		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported AI provider.', 'yukdigitalz-connector-for-google-ai' ) ) );
		}

		$fetched = $provider->fetch_available_models( $api_key );
		if ( is_wp_error( $fetched ) ) {
			wp_send_json_error( array( 'message' => $fetched->get_error_message() ) );
		}

		$settings = Settings::get_instance();
		$settings->update(
			array(
				'cached_dynamic_models' => $fetched,
				'models_last_fetched'   => time(),
			)
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of models */
					__( 'Successfully fetched %d active models directly from Google AI Studio API.', 'yukdigitalz-connector-for-google-ai' ),
					count( $fetched )
				),
				'models'  => $fetched,
			)
		);
	}

	public function ajax_clear_logs() {
		check_ajax_referer( 'yukdiconfo_admin_nonce', 'nonce' );
		$this->verify_security();

		if ( Telemetry_Logger::clear_logs() ) {
			wp_send_json_success( array( 'message' => __( 'Telemetry audit logs cleared successfully.', 'yukdigitalz-connector-for-google-ai' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear telemetry logs or table was empty.', 'yukdigitalz-connector-for-google-ai' ) ) );
		}
	}

	public function ajax_playground_generate() {
		check_ajax_referer( 'yukdiconfo_admin_nonce', 'nonce' );
		$this->verify_security();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via check_ajax_referer.
		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';

		if ( empty( $prompt ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a test prompt for the Gemini Playground.', 'yukdigitalz-connector-for-google-ai' ) ) );
		}

		$options = array(
			'client_source' => 'live_playground',
		);

		$result = Failover_Router::execute( $prompt, $options );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$stats = Telemetry_Logger::get_stats();

		wp_send_json_success(
			array(
				'text'              => isset( $result['text'] ) ? $result['text'] : '',
				'resolved_model'    => isset( $result['resolved_model'] ) ? $result['resolved_model'] : '',
				'resolved_provider' => isset( $result['resolved_provider'] ) ? $result['resolved_provider'] : 'gemini',
				'is_failover'       => ! empty( $result['is_failover'] ),
				'total_latency_ms'  => isset( $result['total_latency_ms'] ) ? $result['total_latency_ms'] : 0,
				'usage'             => isset( $result['usage'] ) ? $result['usage'] : array(),
				'stats'             => $stats,
			)
		);
	}
}
