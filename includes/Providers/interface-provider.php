<?php
namespace YukDigitalz\AIConnectorGoogle\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Interface Provider_Interface
 *
 * Contract for all AI Provider integrations.
 */
interface Provider_Interface {

	/**
	 * Get Provider ID slug.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get Provider Display Name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Send generation request to the AI model.
	 *
	 * @param string $prompt User prompt text or structured parts.
	 * @param string $model Model identifier string.
	 * @param array  $options Generation options (temperature, top_p, max_tokens, system_instruction, etc).
	 * @return array Standardized result array or WP_Error.
	 */
	public function generate( $prompt, $model, array $options = array() );

	/**
	 * Fetch available models dynamically from provider API.
	 *
	 * @param string|null $api_key Optional API key to use.
	 * @return array|WP_Error Array of model definitions or WP_Error.
	 */
	public function fetch_available_models( $api_key = null );

	/**
	 * Test provider API connectivity and latency.
	 *
	 * @param string|null $api_key Optional API key to test.
	 * @return array Test result with status, latency_ms, message.
	 */
	public function test_connection( $api_key = null );
}
