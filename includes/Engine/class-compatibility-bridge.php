<?php
namespace YukDigitalz\AIConnectorGoogle\Engine;

use YukDigitalz\AIConnectorGoogle\Settings;
use YukDigitalz\AIConnectorGoogle\Telemetry_Logger;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Compatibility_Bridge
 *
 * Provides drop-in compatibility for 3rd-party WordPress AI plugins (like Connectors, WP AI Client) calling Google Gemini API.
 * Intercepts outgoing Google Gemini API calls and routes them through the Failover Engine.
 */
class Compatibility_Bridge {

	/**
	 * Singleton instance.
	 *
	 * @var Compatibility_Bridge|null
	 */
	private static $instance = null;

	/**
	 * Flag to prevent recursion when our own engine makes HTTP calls.
	 *
	 * @var bool
	 */
	private static $is_internal_call = false;

	/**
	 * Flag to prevent recursion during option filter injection.
	 *
	 * @var bool
	 */
	private static $is_injecting_option = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Compatibility_Bridge
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
	 * Initialize compatibility hooks and filters.
	 */
	private function init_hooks() {
		// 1. Intercept outgoing HTTP calls to Google Gemini API from other plugins.
		add_filter( 'pre_http_request', array( $this, 'intercept_third_party_gemini_requests' ), 10, 3 );

		// 2. Passively monitor and log any Google AI network calls across WordPress.
		add_action( 'http_api_debug', array( $this, 'monitor_all_ai_http_traffic' ), 10, 5 );
		add_filter( 'http_response', array( $this, 'filter_http_response_telemetry' ), 10, 3 );

		// 3. Generic AI client filters for 3rd party plugins to hook into.
		add_filter( 'wp_ai_client_generate', array( $this, 'handle_plugin_filter_call' ), 10, 2 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_filter( 'connectors_ai_generate', array( $this, 'handle_plugin_filter_call' ), 10, 2 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_filter( 'yukdiconfo_generate', array( $this, 'handle_plugin_filter_call' ), 10, 2 );

		// 4. Companion Model & Provider Override: Provide active model & provider registration to Core WP AI Client & Connectors.
		add_filter( 'wp_ai_providers', array( $this, 'filter_available_providers' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_filter( 'wp_ai_client_providers', array( $this, 'filter_available_providers' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_filter( 'wp_ai_client_default_model', array( $this, 'override_connectors_model' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_filter( 'connectors_active_model', array( $this, 'override_connectors_model' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_filter( 'connectors_google_model', array( $this, 'override_connectors_model' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}





	/**
	 * Inject active Google Gemini provider into connectors_settings option.
	 *
	 * @param mixed $value
	 * @return array
	 */
	public function inject_connectors_settings( $value ) {
		if ( self::$is_injecting_option ) {
			return is_array( $value ) ? $value : array();
		}

		self::$is_injecting_option = true;

		$settings  = Settings::get_instance();
		$api_key   = $settings->get_gemini_api_key();
		$effective = $settings->get_effective_model();

		$config = array(
			'active_provider' => 'google',
			'active_model'    => $effective,
			'default_model'   => $effective,
			'providers'       => array(
				'google' => array(
					'active'  => true,
					'api_key' => $api_key,
					'model'   => $effective,
					'models'  => yukdiconfo_get_all_models(),
				),
			),
			'google'          => array(
				'api_key' => $api_key,
				'model'   => $effective,
			),
		);

		self::$is_injecting_option = false;
		return is_array( $value ) ? array_merge( $value, $config ) : $config;
	}

	/**
	 * Inject active Google Gemini provider into connectors_providers option.
	 *
	 * @param mixed $value
	 * @return array
	 */
	public function inject_connectors_providers( $value ) {
		if ( self::$is_injecting_option ) {
			return is_array( $value ) ? $value : array();
		}

		self::$is_injecting_option = true;

		$providers = is_array( $value ) ? $value : array();
		$providers['google'] = array(
			'name'     => 'Google Gemini AI',
			'slug'     => 'google',
			'active'   => true,
			'models'   => yukdiconfo_get_all_models(),
			'provider' => 'google',
		);

		self::$is_injecting_option = false;
		return $providers;
	}

	/**
	 * Inject active Google Gemini provider into connectors_google_settings option.
	 *
	 * @param mixed $value
	 * @return array
	 */
	public function inject_connectors_google_settings( $value ) {
		if ( self::$is_injecting_option ) {
			return is_array( $value ) ? $value : array();
		}

		self::$is_injecting_option = true;

		$settings = Settings::get_instance();
		$api_key  = $settings->get_gemini_api_key();

		$result = array(
			'api_key' => $api_key,
			'model'   => $settings->get_effective_model(),
			'active'  => true,
		);

		self::$is_injecting_option = false;
		return $result;
	}

	/**
	 * Inject active Google Gemini API Key into connectors_google_api_key option.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function inject_connectors_google_api_key( $value ) {
		if ( self::$is_injecting_option ) {
			return (string) $value;
		}

		self::$is_injecting_option = true;

		$settings = Settings::get_instance();
		$key      = $settings->get_gemini_api_key();

		self::$is_injecting_option = false;
		return ! empty( $key ) ? $key : (string) $value;
	}

	/**
	 * Inject active Google Gemini credentials into connectors_credentials option.
	 *
	 * @param mixed $value
	 * @return array
	 */
	public function inject_connectors_credentials( $value ) {
		if ( self::$is_injecting_option ) {
			return is_array( $value ) ? $value : array();
		}

		self::$is_injecting_option = true;
		$settings  = Settings::get_instance();
		$api_key   = $settings->get_gemini_api_key();
		$effective = $settings->get_effective_model();

		$creds = is_array( $value ) ? $value : array();
		$creds['google'] = array(
			'api_key' => $api_key,
			'model'   => $effective,
			'active'  => true,
		);

		self::$is_injecting_option = false;
		return $creds;
	}

	/**
	 * Inject active provider slug ('google').
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function inject_connectors_active_provider( $value ) {
		return 'google';
	}

	/**
	 * Inject active Google Gemini model ID into 3rd party model option getters.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function inject_connectors_active_model( $value ) {
		$settings  = Settings::get_instance();
		$effective = $settings->get_effective_model();
		return ! empty( $effective ) ? $effective : ( is_string( $value ) ? $value : 'gemini-3.7-flash' );
	}



	/**
	 * Filter available AI providers list.
	 *
	 * @param mixed $providers
	 * @return array
	 */
	public function filter_available_providers( $providers = array() ) {
		$list = is_array( $providers ) ? $providers : array();
		$list['google'] = 'Google Gemini AI Engine';
		return $list;
	}

	/**
	 * Dynamically override requested model with active model chosen in YukDigitalz AI Connector - Google AI.
	 *
	 * @param string $model
	 * @return string
	 */
	public function override_connectors_model( $model ) {
		$settings = Settings::get_instance();
		$effective = $settings->get_effective_model();
		return ! empty( $effective ) ? $effective : $model;
	}

	/**
	 * Passively record telemetry via http_response filter.
	 *
	 * @param array $response
	 * @param array $parsed_args
	 * @param string $url
	 * @return array
	 */
	public function filter_http_response_telemetry( $response, $parsed_args, $url ) {
		$this->monitor_all_ai_http_traffic( $response, 'response', 'WP_Http', $parsed_args, $url );
		return $response;
	}

	/**
	 * Passively record telemetry for Google AI API requests across WordPress.
	 *
	 * @param array|WP_Error $response
	 * @param string         $context
	 * @param string         $class
	 * @param array          $parsed_args
	 * @param string         $url
	 */
	public function monitor_all_ai_http_traffic( $response, $context, $class, $parsed_args, $url ) {
		if ( self::$is_internal_call || ! is_string( $url ) ) {
			return;
		}

		$is_google = strpos( $url, 'googleapis.com' ) !== false && ( strpos( $url, 'generative' ) !== false || strpos( $url, 'gemini' ) !== false || strpos( $url, 'models/' ) !== false );

		if ( ! $is_google ) {
			return;
		}

		// Don't duplicate if already logged by internal failover router.
		if ( isset( $parsed_args['headers']['User-Agent'] ) && strpos( $parsed_args['headers']['User-Agent'], 'yukdigitalz-connector-for-google-ai' ) !== false ) {
			return;
		}

		$model = 'auto-detected';
		if ( preg_match( '#/models/([^:]+)#', $url, $m ) ) {
			$model = $m[1];
		}

		$status_code = is_array( $response ) && isset( $response['response']['code'] ) ? intval( $response['response']['code'] ) : ( is_wp_error( $response ) ? 500 : 200 );
		$is_success  = $status_code >= 200 && $status_code < 300;

		Telemetry_Logger::log(
			array(
				'provider'        => 'gemini',
				'requested_model' => $model,
				'resolved_model'  => $model,
				'status_code'     => $status_code,
				'is_success'      => $is_success,
				'is_failover'     => 0,
				'latency_ms'      => 0,
				'prompt_tokens'   => 0,
				'response_tokens' => 0,
				'client_source'   => 'passive_network_monitor',
			)
		);
	}

	/**
	 * Set internal call flag.
	 *
	 * @param bool $status
	 */
	public static function set_internal_call( $status ) {
		self::$is_internal_call = (bool) $status;
	}

	/**
	 * Intercept raw HTTP requests to Google Generative Language API from other plugins.
	 *
	 * @param false|array|WP_Error $preempt
	 * @param array                $parsed_args
	 * @param string               $url
	 * @return false|array|WP_Error
	 */
	public function intercept_third_party_gemini_requests( $preempt, $parsed_args, $url ) {
		try {
			if ( self::$is_internal_call ) {
				return $preempt;
			}

			$is_gemini_url = ( strpos( $url, 'googleapis.com' ) !== false ) || ( strpos( $url, 'generativelanguage' ) !== false ) || ( strpos( $url, 'gemini' ) !== false );
			if ( ! is_string( $url ) || ! $is_gemini_url ) {
				return $preempt;
			}

			if ( isset( $parsed_args['headers']['User-Agent'] ) && strpos( $parsed_args['headers']['User-Agent'], 'yukdigitalz-connector-for-google-ai' ) !== false ) {
				return $preempt;
			}

			$settings = Settings::get_instance();
			$interceptor_enabled = $settings->get( 'enable_third_party_interceptor', true );
			if ( false === $interceptor_enabled || '0' === (string) $interceptor_enabled ) {
				return $preempt;
			}


			// Do not intercept GET requests (such as API key validation or model listing calls).
			$http_method = isset( $parsed_args['method'] ) ? strtoupper( (string) $parsed_args['method'] ) : 'GET';
			if ( 'GET' === $http_method ) {
				return $preempt;
			}

			// Do not intercept if request URL is not a text generation call
			if ( strpos( $url, 'generateContent' ) === false && strpos( $url, 'streamGenerateContent' ) === false ) {
				return $preempt;
			}

			$body = isset( $parsed_args['body'] ) ? $parsed_args['body'] : '';
			if ( is_string( $body ) ) {
				$json_body = json_decode( $body, true );
			} elseif ( is_array( $body ) ) {
				$json_body = $body;
			} else {
				$json_body = array();
			}

			$prompt = '';
			if ( isset( $json_body['contents'] ) && is_array( $json_body['contents'] ) ) {
				foreach ( $json_body['contents'] as $content_item ) {
					if ( isset( $content_item['parts'] ) && is_array( $content_item['parts'] ) ) {
						foreach ( $content_item['parts'] as $part ) {
							if ( is_array( $part ) && isset( $part['text'] ) ) {
								$prompt .= $part['text'] . ' ';
							} elseif ( is_string( $part ) ) {
								$prompt .= $part . ' ';
							}
						}
					}
				}
			} elseif ( isset( $json_body['prompt'] ) ) {
				$prompt = is_string( $json_body['prompt'] ) ? $json_body['prompt'] : wp_json_encode( $json_body['prompt'] );
			} elseif ( isset( $json_body['messages'] ) && is_array( $json_body['messages'] ) ) {
				foreach ( $json_body['messages'] as $msg ) {
					if ( isset( $msg['content'] ) && is_string( $msg['content'] ) ) {
						$prompt .= $msg['content'] . ' ';
					}
				}
			}
			$prompt = trim( $prompt );

			if ( empty( $prompt ) ) {
				$prompt = ! empty( $body ) ? ( is_string( $body ) ? $body : wp_json_encode( $body ) ) : 'AI generation request';
			}

			$requested_model = '';
			if ( preg_match( '#/models/([^:]+)#', $url, $matches ) ) {
				$requested_model = $matches[1];
			}

			$effective_model = $settings->get_effective_model();
			$options = array(
				'client_source' => 'third_party_intercepted',
				'model'         => ! empty( $effective_model ) ? $effective_model : $requested_model,
				'timeout'       => 8,
			);

			if ( is_array( $json_body ) && ! empty( $json_body ) ) {
				$options['raw_payload'] = $json_body;
			}

			// Always respect API key passed in third-party request URL

			if ( preg_match( '#[?&]key=([^&]+)#', $url, $key_matches ) ) {
				$incoming_key = sanitize_text_field( $key_matches[1] );
				if ( ! empty( $incoming_key ) ) {
					$options['api_key'] = $incoming_key;
				}
			}


			if ( isset( $json_body['generationConfig']['temperature'] ) ) {

				$options['temperature'] = floatval( $json_body['generationConfig']['temperature'] );
			}
			if ( isset( $json_body['generationConfig']['maxOutputTokens'] ) ) {
				$options['max_tokens'] = intval( $json_body['generationConfig']['maxOutputTokens'] );
			}

			$result = Failover_Router::execute( $prompt, $options );

			if ( is_wp_error( $result ) || empty( $result['success'] ) ) {
				$err_msg = is_wp_error( $result ) ? $result->get_error_message() : 'Failover exhausted across all models.';
				return array(
					'response' => array(
						'code'    => 503,
						'message' => 'Service Unavailable',
					),
					'headers'  => array(
						'content-type' => 'application/json; charset=UTF-8',
					),
					'body'     => wp_json_encode( array( 'error' => array( 'code' => 503, 'message' => $err_msg, 'status' => 'UNAVAILABLE' ) ) ),
					'cookies'  => array(),
				);
			}


			$simulated_body = isset( $result['raw'] ) && is_array( $result['raw'] ) ? wp_json_encode( $result['raw'] ) : wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content' => array(
								'parts' => array(
									array( 'text' => isset( $result['text'] ) ? $result['text'] : '' ),
								),
								'role' => 'model',
							),
							'finishReason' => 'STOP',
						),
					),
					'usageMetadata' => array(
						'promptTokenCount'     => isset( $result['usage']['prompt_tokens'] ) ? intval( $result['usage']['prompt_tokens'] ) : 0,
						'candidatesTokenCount' => isset( $result['usage']['response_tokens'] ) ? intval( $result['usage']['response_tokens'] ) : 0,
						'totalTokenCount'      => isset( $result['usage']['total_tokens'] ) ? intval( $result['usage']['total_tokens'] ) : 0,
					),
				)
			);

			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'headers'  => array(
					'content-type' => 'application/json; charset=UTF-8',
				),
				'body'     => $simulated_body,
				'cookies'  => array(),
			);
		} catch ( \Throwable $e ) {
			return $preempt;
		}
	}

	/**
	 * Handle direct filter calls from 3rd party plugins.
	 *
	 * @param mixed $default_or_result
	 * @param string|array $args
	 * @return string|array|WP_Error
	 */
	public function handle_plugin_filter_call( $default_or_result, $args = array() ) {
		try {
			$prompt = '';
			$options = array();

			if ( is_string( $args ) ) {
				$prompt = $args;
			} elseif ( is_array( $args ) ) {
				$prompt = isset( $args['prompt'] ) ? $args['prompt'] : ( isset( $args['content'] ) ? $args['content'] : '' );
				$options = $args;
			}

			if ( empty( $prompt ) ) {
				return $default_or_result;
			}

			$result = Failover_Router::execute( $prompt, $options );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return isset( $result['text'] ) ? $result['text'] : $result;
		} catch ( \Throwable $e ) {
			return $default_or_result;
		}
	}
}
