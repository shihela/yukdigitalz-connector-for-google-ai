<?php
namespace YukDigitalz\AIConnectorGoogle\Engine;

use YukDigitalz\AIConnectorGoogle\Settings;
use YukDigitalz\AIConnectorGoogle\Telemetry_Logger;
use YukDigitalz\AIConnectorGoogle\Providers\Provider_Factory;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Failover_Router
 *
 * Google AI Gemini Failover Router.
 * Automatically recovers from 503, 429, timeouts, and outages across Google Gemini models and API key rotation.
 */
class Failover_Router {

	/**
	 * Execute generation request through the failover & retry engine.
	 *
	 * @param string $prompt Prompt string.
	 * @param array  $custom_options Optional parameters overriding global settings.
	 * @return array Standardized result array or WP_Error.
	 */
	public static function execute( $prompt, array $custom_options = array() ) {
		$settings = Settings::get_instance();

		// Determine model execution hierarchy.
		$requested_model = isset( $custom_options['model'] ) && ! empty( $custom_options['model'] )
			? sanitize_text_field( $custom_options['model'] )
			: $settings->get_effective_model();

		$models_to_try = array( $requested_model );

		// Append fallback models if failover is enabled.
		$is_failover_enabled = $settings->get( 'enable_failover', true );
		if ( $is_failover_enabled ) {
			$configured_fallbacks = $settings->get( 'fallback_models', array( 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro' ) );
			if ( empty( $configured_fallbacks ) || ! is_array( $configured_fallbacks ) ) {
				$configured_fallbacks = array( 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro' );
			}

			foreach ( $configured_fallbacks as $fb_model ) {
				$fb_model = trim( sanitize_text_field( $fb_model ) );
				if ( ! empty( $fb_model ) && ! in_array( $fb_model, $models_to_try, true ) ) {
					$models_to_try[] = $fb_model;
				}
			}
		}


		$enable_auto_retry       = (bool) $settings->get( 'enable_auto_retry', true );
		$max_retries             = $enable_auto_retry ? max( 1, intval( $settings->get( 'max_retries_per_model', 2 ) ) ) : 1;
		$base_delay_ms           = max( 100, intval( $settings->get( 'retry_delay_ms', 800 ) ) );
		$failover_on_503         = (bool) $settings->get( 'failover_on_503', true );
		$failover_on_429         = (bool) $settings->get( 'failover_on_429', true );
		$failover_on_500         = (bool) $settings->get( 'failover_on_500', true );
		$failover_on_timeout     = (bool) $settings->get( 'failover_on_timeout', true );

		$enable_circuit_breaker = (bool) $settings->get( 'enable_circuit_breaker', true );
		$cooldown_duration_sec  = max( 10, intval( $settings->get( 'cooldown_duration_sec', 120 ) ) );

		// Multi-key rotation support.
		$all_api_keys = $settings->get_all_gemini_api_keys();
		if ( empty( $all_api_keys ) ) {
			$all_api_keys = array( '' );
		}

		$failover_trail       = array();
		$overall_start_time   = microtime( true );
		$last_error           = null;
		$successful_result    = null;
		$resolved_model       = $requested_model;
		$resolved_provider    = 'gemini';
		$total_attempts       = 0;

		// Loop through model hierarchy and API key rotation.
		foreach ( $models_to_try as $model_index => $current_model ) {
			$provider_id = 'gemini';
			$provider    = Provider_Factory::get( $provider_id );
			if ( ! $provider ) {
				continue;
			}

			// Circuit Breaker Check: Skip model if it's currently on transient cooldown
			if ( $enable_circuit_breaker && Settings::is_model_on_cooldown( $current_model ) && count( $models_to_try ) > 1 ) {
				$failover_trail[] = array(
					'provider'    => $provider_id,
					'model'       => $current_model,
					'status_code' => 503,
					'error'       => 'Skipped: Model currently in Transient Cooldown (Circuit Breaker Active)',
					'latency_ms'  => 0,
					'status'      => 'SKIPPED_COOLDOWN',
				);
				continue;
			}

			foreach ( $all_api_keys as $key_index => $api_key_candidate ) {
				// Circuit Breaker Check: Skip API key candidate if it's currently on transient cooldown
				if ( $enable_circuit_breaker && ! empty( $api_key_candidate ) && Settings::is_key_on_cooldown( $api_key_candidate ) && count( $all_api_keys ) > 1 ) {
					$failover_trail[] = array(
						'provider'    => $provider_id,
						'model'       => $current_model,
						'key_index'   => $key_index,
						'status_code' => 429,
						'error'       => 'Skipped: API Key currently in Transient Cooldown (Circuit Breaker Active)',
						'latency_ms'  => 0,
						'status'      => 'SKIPPED_KEY_COOLDOWN',
					);
					continue;
				}

				$model_attempt = 0;

				while ( $model_attempt < $max_retries ) {
					$model_attempt++;
					$total_attempts++;

					$opt_override          = $custom_options;
					$opt_override['model'] = $current_model; // Fix 1: Ensure current fallback model is passed to provider.

					if ( ! empty( $api_key_candidate ) ) {
						$opt_override['api_key'] = $api_key_candidate;
					}

					$attempt_start = microtime( true );
					$result = $provider->generate( $prompt, $current_model, $opt_override );
					$attempt_latency = (int) round( ( microtime( true ) - $attempt_start ) * 1000 );

					if ( ! is_wp_error( $result ) && ! empty( $result['success'] ) ) {
						$resolved_model = $current_model;
						$resolved_provider = $provider_id;
						$successful_result = $result;

						$failover_trail[] = array(
							'provider'    => $provider_id,
							'model'       => $current_model,
							'key_index'   => $key_index,
							'attempt'     => $model_attempt,
							'status_code' => 200,
							'latency_ms'  => $attempt_latency,
							'status'      => 'SUCCESS',
						);

						break 3; // Break all loops on SUCCESS.
					}

					$status_code = 0;
					$error_msg = 'Unknown error';

					if ( is_wp_error( $result ) ) {
						$error_msg = $result->get_error_message();
						$err_data = $result->get_error_data();
						if ( is_array( $err_data ) && isset( $err_data['status_code'] ) ) {
							$status_code = intval( $err_data['status_code'] );
						}
					}

					$last_error = $result;

					$failover_trail[] = array(
						'provider'    => $provider_id,
						'model'       => $current_model,
						'key_index'   => $key_index,
						'attempt'     => $model_attempt,
						'status_code' => $status_code,
						'error'       => $error_msg,
						'latency_ms'  => $attempt_latency,
						'status'      => 'FAILED',
					);

					$is_503_high_demand = ( 503 === $status_code || stripos( $error_msg, 'high demand' ) !== false || stripos( $error_msg, 'UNAVAILABLE' ) !== false || stripos( $error_msg, 'overloaded' ) !== false );
					$is_429_quota       = ( 429 === $status_code || stripos( $error_msg, 'quota' ) !== false || stripos( $error_msg, 'RESOURCE_EXHAUSTED' ) !== false || stripos( $error_msg, 'rate limit' ) !== false );
					$is_500_server_err  = ( $status_code >= 500 && $status_code < 600 && ! $is_503_high_demand );
					$is_timeout         = ( 0 === $status_code || stripos( $error_msg, 'timed out' ) !== false || stripos( $error_msg, 'cURL error 28' ) !== false );

					// Fix 2: Trigger Circuit Breaker Transient Cooldown on 503 capacity errors only (Do NOT penalize keys with 120s cooldown on temporary 429 rate limits).
					if ( $enable_circuit_breaker ) {
						if ( $is_503_high_demand ) {
							Settings::set_model_cooldown( $current_model, $cooldown_duration_sec );
						}
					}


					// 1. If 503 High Demand / Overloaded: Switch directly to next fallback model!
					if ( $is_503_high_demand && $failover_on_503 ) {
						break 2; // Switch to next fallback model immediately!
					}

					// 2. If 429 Quota Exceeded: Try next API key. If last API key, switch directly to next fallback model!
					if ( $is_429_quota && $failover_on_429 ) {
						if ( $key_index === count( $all_api_keys ) - 1 ) {
							break 2; // Switch to next fallback model immediately!
						}
						break 1; // Try next API Key candidate
					}

					// 3. If 500 Server Error or Timeout: Retry attempt up to max_retries. If still fails on last key, switch to next fallback model!
					if ( ( $is_500_server_err && $failover_on_500 ) || ( $is_timeout && $failover_on_timeout ) ) {
						if ( $model_attempt >= $max_retries ) {
							if ( $key_index === count( $all_api_keys ) - 1 ) {
								break 2; // Switch to next fallback model immediately!
							}
							break 1; // Try next API key
						}
					} else {
						// Any other unhandled error on current model: switch to next fallback model if last key
						if ( $key_index === count( $all_api_keys ) - 1 ) {
							break 2;
						}
						break 1;
					}


					if ( $model_attempt < $max_retries ) {
						$jitter = wp_rand( 50, 200 );
						$delay_us = ( ( $base_delay_ms * $model_attempt ) + $jitter ) * 1000;
						usleep( $delay_us );
					}


				} // End while.
			} // End foreach keys.
		} // End foreach models.


		$total_latency = (int) round( ( microtime( true ) - $overall_start_time ) * 1000 );
		$is_failover = ( $resolved_model !== $requested_model ) || ( count( $failover_trail ) > 1 );

		$log_entry = array(
			'provider'          => $resolved_provider,
			'requested_model'   => $requested_model,
			'resolved_model'    => $resolved_model,
			'status_code'       => $successful_result ? 200 : ( is_wp_error( $last_error ) && is_array( $last_error->get_error_data() ) && isset( $last_error->get_error_data()['status_code'] ) ? intval( $last_error->get_error_data()['status_code'] ) : 500 ),
			'is_success'        => ! empty( $successful_result ),
			'is_failover'       => $is_failover,
			'failover_attempts' => $total_attempts,
			'failover_trail'    => $failover_trail,
			'latency_ms'        => $total_latency,
			'prompt_tokens'     => $successful_result && isset( $successful_result['usage']['prompt_tokens'] ) ? $successful_result['usage']['prompt_tokens'] : 0,
			'response_tokens'   => $successful_result && isset( $successful_result['usage']['response_tokens'] ) ? $successful_result['usage']['response_tokens'] : 0,
			'error_message'     => is_wp_error( $last_error ) ? $last_error->get_error_message() : '',
			'request_preview'   => is_string( $prompt ) ? $prompt : wp_json_encode( $prompt ),
			'response_preview'  => $successful_result && isset( $successful_result['text'] ) ? $successful_result['text'] : '',
			'client_source'     => isset( $custom_options['client_source'] ) ? sanitize_text_field( $custom_options['client_source'] ) : 'wordpress',
		);

		Telemetry_Logger::log( $log_entry );

		if ( $successful_result ) {
			$successful_result['is_failover']       = $is_failover;
			$successful_result['requested_model']   = $requested_model;
			$successful_result['resolved_model']    = $resolved_model;
			$successful_result['resolved_provider'] = $resolved_provider;
			$successful_result['failover_trail']    = $failover_trail;
			$successful_result['total_latency_ms']  = $total_latency;
			$successful_result['total_attempts']    = $total_attempts;

			do_action( 'yukdiconfo_after_generate', $successful_result, $prompt, $custom_options );
			return $successful_result;
		}

		do_action( 'yukdiconfo_generate_failed', $last_error, $prompt, $custom_options );
		return $last_error ? $last_error : new WP_Error( 'yukdiconfo_unknown_fail', __( 'All Google Gemini models and API key failover attempts failed.', 'yukdigitalz-connector-for-google-ai' ) );
	}
}
