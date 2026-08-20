<?php
namespace YukDigitalz\AIConnectorGoogle\Providers;

use YukDigitalz\AIConnectorGoogle\Settings;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gemini_Provider
 *
 * Google Gemini API Connector implementation.
 */
class Gemini_Provider implements Provider_Interface {

	/**
	 * Base API URL for Google Generative Language API.
	 */
	const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Get Provider ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'gemini';
	}

	/**
	 * Get Provider Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Google Gemini';
	}

	/**
	 * Get active API Key.
	 *
	 * @param string|null $override_key
	 * @return string
	 */
	private function get_api_key( $override_key = null ) {
		if ( ! empty( $override_key ) ) {
			return $override_key;
		}

		$settings = Settings::get_instance();
		return $settings->get_gemini_api_key();
	}

	/**
	 * Clean model ID string (strip 'models/' prefix if present).
	 *
	 * @param string $model
	 * @return string
	 */
	public static function clean_model_id( $model ) {
		$model = trim( (string) $model );
		if ( strpos( $model, 'models/' ) === 0 ) {
			return substr( $model, 7 );
		}
		return $model;
	}

	/**
	 * Send generation request to Gemini model.
	 *
	 * @param string $prompt
	 * @param string $model
	 * @param array  $options
	 * @return array|WP_Error
	 */
	public function generate( $prompt, $model, array $options = array() ) {
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'yukdiconfo_missing_key',
				__( 'Google Gemini API Key is not configured. Please add your API key in Settings or wp-config.php.', 'yukdigitalz-connector-for-google-ai' ),
				array( 'status_code' => 401 )
			);
		}

		$cleaned_model = self::clean_model_id( $model );
		if ( empty( $cleaned_model ) ) {
			$cleaned_model = 'gemini-3.7-flash';
		}



		$settings = Settings::get_instance();
		$timeout = isset( $options['timeout'] ) ? intval( $options['timeout'] ) : intval( $settings->get( 'request_timeout_sec', 30 ) );
		$temperature = isset( $options['temperature'] ) ? floatval( $options['temperature'] ) : floatval( $settings->get( 'default_temperature', 0.7 ) );
		$top_p = isset( $options['top_p'] ) ? floatval( $options['top_p'] ) : floatval( $settings->get( 'default_top_p', 0.95 ) );
		$max_tokens = isset( $options['max_tokens'] ) ? intval( $options['max_tokens'] ) : intval( $settings->get( 'default_max_tokens', 4096 ) );
		$system_instruction = isset( $options['system_instruction'] ) ? (string) $options['system_instruction'] : (string) $settings->get( 'system_instruction', '' );

		// Construct payload (preserve raw_payload from 3rd party plugins if available).
		if ( isset( $options['raw_payload'] ) && is_array( $options['raw_payload'] ) && ! empty( $options['raw_payload'] ) ) {
			$payload = $options['raw_payload'];
		} else {
			$payload = array(
				'contents' => array(
					array(
						'role'  => 'user',
						'parts' => array(
							array(
								'text' => (string) $prompt,
							),
						),
					),
				),
				'generationConfig' => array(
					'temperature'     => $temperature,
					'topP'            => $top_p,
					'maxOutputTokens' => $max_tokens,
				),
			);

			// Add system instruction if present.
			if ( ! empty( trim( $system_instruction ) ) ) {
				$payload['system_instruction'] = array(
					'parts' => array(
						array(
							'text' => trim( $system_instruction ),
						),
					),
				);
			}
		}


		$endpoint_url = add_query_arg(
			array( 'key' => $api_key ),
			self::API_BASE_URL . '/models/' . rawurlencode( $cleaned_model ) . ':generateContent'
		);

		$start_time = microtime( true );

		\YukDigitalz\AIConnectorGoogle\Engine\Compatibility_Bridge::set_internal_call( true );

		$version_constant = YUKDICONFO_VERSION;

		$response = wp_remote_post(
			$endpoint_url,
			array(
				'timeout'     => $timeout,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'WordPress/YukDigitalz-AI-Connectors/' . $version_constant,
				),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
				'sslverify'   => true,
			)
		);

		\YukDigitalz\AIConnectorGoogle\Engine\Compatibility_Bridge::set_internal_call( false );

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'yukdiconfo_http_error',
				$response->get_error_message(),
				array(
					'status_code' => 0,
					'latency_ms'  => $latency_ms,
					'model'       => $cleaned_model,
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error_message = __( 'Unknown error from Gemini API', 'yukdigitalz-connector-for-google-ai' );
			$error_status = 'ERROR';

			if ( isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			}
			if ( isset( $data['error']['status'] ) ) {
				$error_status = $data['error']['status'];
			}

			return new WP_Error(
				'yukdiconfo_api_error_' . $status_code,
				$error_message,
				array(
					'status_code'  => $status_code,
					'api_status'   => $error_status,
					'latency_ms'   => $latency_ms,
					'model'        => $cleaned_model,
					'raw_response' => $data,
				)
			);
		}

		// Parse successful text response.
		$generated_text = '';
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$generated_text = $data['candidates'][0]['content']['parts'][0]['text'];
		}

		// Token usage metadata.
		$prompt_tokens = isset( $data['usageMetadata']['promptTokenCount'] ) ? intval( $data['usageMetadata']['promptTokenCount'] ) : 0;
		$response_tokens = isset( $data['usageMetadata']['candidatesTokenCount'] ) ? intval( $data['usageMetadata']['candidatesTokenCount'] ) : 0;
		$total_tokens = isset( $data['usageMetadata']['totalTokenCount'] ) ? intval( $data['usageMetadata']['totalTokenCount'] ) : ( $prompt_tokens + $response_tokens );

		return array(
			'success'     => true,
			'text'        => $generated_text,
			'model'       => $cleaned_model,
			'provider'    => 'gemini',
			'status_code' => 200,
			'latency_ms'  => $latency_ms,
			'usage'       => array(
				'prompt_tokens'   => $prompt_tokens,
				'response_tokens' => $response_tokens,
				'total_tokens'    => $total_tokens,
			),
			'raw'         => $data,
		);
	}

	/**
	 * Fetch available models dynamically from Google Gemini API.
	 *
	 * @param string|null $api_key
	 * @return array|WP_Error
	 */
	public function fetch_available_models( $api_key = null ) {
		$key = $this->get_api_key( $api_key );

		if ( empty( $key ) ) {
			return new WP_Error( 'yukdiconfo_missing_key', __( 'Cannot fetch models: Gemini API key is missing.', 'yukdigitalz-connector-for-google-ai' ) );
		}

		$url = add_query_arg(
			array(
				'key'      => $key,
				'pageSize' => 1000,
			),
			self::API_BASE_URL . '/models'
		);

		\YukDigitalz\AIConnectorGoogle\Engine\Compatibility_Bridge::set_internal_call( true );

		$version_constant = YUKDICONFO_VERSION;

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 20,
				'headers'   => array(
					'User-Agent' => 'WordPress/YukDigitalz-AI-Connectors/' . $version_constant,
				),
				'sslverify' => true,
			)
		);

		\YukDigitalz\AIConnectorGoogle\Engine\Compatibility_Bridge::set_internal_call( false );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Failed to fetch models from Gemini API', 'yukdigitalz-connector-for-google-ai' );
			return new WP_Error( 'yukdiconfo_fetch_failed', $msg, array( 'status_code' => $status_code ) );
		}

		$models_list = array();
		if ( isset( $data['models'] ) && is_array( $data['models'] ) ) {
			foreach ( $data['models'] as $m ) {
				$methods = isset( $m['supportedGenerationMethods'] ) ? (array) $m['supportedGenerationMethods'] : array();
				if ( ! in_array( 'generateContent', $methods, true ) ) {
					continue;
				}

				$raw_id = isset( $m['baseModelId'] ) && ! empty( $m['baseModelId'] ) ? $m['baseModelId'] : ( isset( $m['name'] ) ? $m['name'] : '' );
				$id = self::clean_model_id( $raw_id );
				if ( empty( $id ) ) {
					continue;
				}

				$display_name = isset( $m['displayName'] ) ? $m['displayName'] : $id;
				$description = isset( $m['description'] ) ? $m['description'] : '';

				$speed = 'Fast';
				if ( strpos( $id, '3.7' ) !== false || strpos( $id, 'flash' ) !== false ) {
					$speed = 'Ultra Fast';
				}

				$tag = 'Google Gemini - Live';
				if ( strpos( $id, '3.7' ) !== false ) {
					$tag = 'Google - Latest Stable (Recommended)';
				} elseif ( strpos( $id, '-preview' ) !== false ) {
					$tag = 'Google - Preview';
				}

				$models_list[ $id ] = array(
					'id'           => $id,
					'name'         => $display_name,
					'description'  => $description,
					'tag'          => $tag,
					'speed'        => $speed,
					'category'     => 'Google Gemini',
					'input_limit'  => isset( $m['inputTokenLimit'] ) ? $m['inputTokenLimit'] : 0,
					'output_limit' => isset( $m['outputTokenLimit'] ) ? $m['outputTokenLimit'] : 0,
				);
			}
		}

		// Sort models.
		uasort(
			$models_list,
			function ( $a, $b ) {
				$aId = $a['id'];
				$bId = $b['id'];

				if ( strpos( $aId, '-exp' ) !== false && strpos( $bId, '-exp' ) === false ) return 1;
				if ( strpos( $bId, '-exp' ) !== false && strpos( $aId, '-exp' ) === false ) return -1;

				if ( strpos( $aId, '-preview' ) !== false && strpos( $bId, '-preview' ) === false ) return 1;
				if ( strpos( $bId, '-preview' ) !== false && strpos( $aId, '-preview' ) === false ) return -1;

				if ( strpos( $aId, 'gemini-' ) === 0 && strpos( $bId, 'gemini-' ) !== 0 ) return -1;
				if ( strpos( $bId, 'gemini-' ) === 0 && strpos( $aId, 'gemini-' ) !== 0 ) return 1;

				$aMatch = preg_match( '/^gemini-([0-9.]+)(-[a-z0-9-]+)?$/', $aId, $aMatches );
				$bMatch = preg_match( '/^gemini-([0-9.]+)(-[a-z0-9-]+)?$/', $bId, $bMatches );

				if ( $aMatch && ! $bMatch ) return -1;
				if ( $bMatch && ! $aMatch ) return 1;

				if ( $aMatch && $bMatch ) {
					$aVersion = $aMatches[1];
					$bVersion = $bMatches[1];
					if ( version_compare( $aVersion, $bVersion, '>' ) ) return -1;
					if ( version_compare( $bVersion, $aVersion, '>' ) ) return 1;

					$aSuffix = isset( $aMatches[2] ) ? $aMatches[2] : '';
					$bSuffix = isset( $bMatches[2] ) ? $bMatches[2] : '';

					if ( '-flash' === $aSuffix && '-flash' !== $bSuffix ) return -1;
					if ( '-flash' === $bSuffix && '-flash' !== $aSuffix ) return 1;
					if ( '-pro' === $aSuffix && '-pro' !== $bSuffix ) return -1;
					if ( '-pro' === $bSuffix && '-pro' !== $aSuffix ) return 1;
				}

				return strcmp( $aId, $bId );
			}
		);

		return $models_list;
	}

	/**
	 * Test API connection and measure latency.
	 *
	 * @param string|null $api_key
	 * @return array
	 */
	public function test_connection( $api_key = null ) {
		$key = $this->get_api_key( $api_key );

		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Kunci API Google belum terdeteksi. Hubungkan di menu WordPress Connectors atau definisikan GOOGLE_API_KEY di wp-config.php.', 'yukdigitalz-connector-for-google-ai' ),
				'latency' => 0,
			);
		}

		$models = $this->fetch_available_models( $key );
		if ( is_wp_error( $models ) ) {
			return array(
				'success' => false,
				'message' => $models->get_error_message(),
				'latency' => 0,
			);
		}

		$settings     = Settings::get_instance();
		$target_model = $settings->get_effective_model();
		if ( empty( $target_model ) ) {
			$target_model = 'gemini-3.7-flash';
		}

		$test_result = $this->generate(
			'Respond with the single word: READY',
			$target_model,
			array(
				'api_key'    => $key,
				'max_tokens' => 10,
				'timeout'    => 10,
			)
		);

		if ( is_wp_error( $test_result ) ) {
			$test_result = $this->generate(
				'Respond with the single word: READY',
				'gemini-3.7-flash',
				array(
					'api_key'    => $key,
					'max_tokens' => 10,
					'timeout'    => 10,
				)
			);
		}

		if ( is_wp_error( $test_result ) ) {
			return array(
				'success'      => false,
				'message'      => $test_result->get_error_message(),
				'latency'      => 0,
				'models_found' => is_array( $models ) ? count( $models ) : 0,
			);
		}

		return array(
			'success'      => true,
			'message'      => __( 'Koneksi Google Gemini API Valid & Terhubung!', 'yukdigitalz-connector-for-google-ai' ),
			'latency'      => isset( $test_result['latency_ms'] ) ? $test_result['latency_ms'] : 0,
			'model_used'   => isset( $test_result['model'] ) ? $test_result['model'] : $target_model,
			'models_found' => is_array( $models ) ? count( $models ) : 0,
		);
	}
}
