<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use YukDigitalz\AIConnectorGoogle\Engine\Failover_Router;
use YukDigitalz\AIConnectorGoogle\Settings;
use YukDigitalz\AIConnectorGoogle\Providers\Provider_Factory;

/**
 * Universal Global Function to generate Google AI text with Gemini model auto-failover & key rotation.
 *
 * Example:
 * $response = yukdiconfo_generate( 'Tuliskan deskripsi produk yang menarik' );
 * if ( ! is_wp_error( $response ) ) {
 *     echo $response['text'];
 *     echo 'Model: ' . $response['resolved_model'];
 * }
 *
 * @param string $prompt Prompt string.
 * @param array  $options Optional custom generation parameters.
 * @return array|WP_Error Standardized result or WP_Error.
 */
function yukdiconfo_generate( $prompt, array $options = array() ) {
	$prompt = apply_filters( 'yukdiconfo_before_prompt', $prompt, $options );
	return Failover_Router::execute( $prompt, $options );
}

/**
 * Get active primary model ID.
 *
 * @return string e.g. 'gemini-3.7-flash'
 */
function yukdiconfo_get_active_model() {
	return Settings::get_instance()->get_effective_model();
}

/**
 * Get combined list of known presets + dynamically fetched Google Gemini models.
 *
 * @return array
 */
function yukdiconfo_get_all_models() {
	$known = Settings::get_known_all_models();
	$dynamic = Settings::get_instance()->get( 'cached_dynamic_models', array() );

	if ( is_array( $dynamic ) && ! empty( $dynamic ) ) {
		foreach ( $dynamic as $id => $item ) {
			if ( ! isset( $known[ $id ] ) ) {
				$known[ $id ] = array(
					'name'        => isset( $item['name'] ) ? $item['name'] : $id,
					'provider'    => 'gemini',
					'tag'         => 'Dynamic Google Model',
					'description' => isset( $item['description'] ) ? $item['description'] : '',
					'speed'       => 'Custom',
					'category'    => 'Google AI Studio',
				);
			}
		}
	}

	return apply_filters( 'yukdiconfo_all_models', $known );
}

/**
 * Helper to test connection to Google Gemini API.
 *
 * @param string|null $api_key Optional API key to test.
 * @return array
 */
function yukdiconfo_test_connection( $api_key = null ) {
	$provider = Provider_Factory::get( 'gemini' );
	if ( ! $provider ) {
		return array(
			'success' => false,
			'message' => __( 'Google Gemini Provider not found', 'yukdigitalz-connector-for-google-ai' ),
		);
	}

	return $provider->test_connection( $api_key );
}
