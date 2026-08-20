<?php
namespace YukDigitalz\AIConnectorGoogle\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Provider_Factory
 *
 * Resolves and instantiates AI Provider instances for YukDigitalz Connector for Google AI.
 */
class Provider_Factory {

	/**
	 * Cached instances.
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Get provider instance by ID.
	 *
	 * @param string $provider_id Default 'gemini'.
	 * @return Provider_Interface|null
	 */
	public static function get( $provider_id = 'gemini' ) {
		$provider_id = sanitize_key( $provider_id );

		if ( isset( self::$instances[ $provider_id ] ) ) {
			return self::$instances[ $provider_id ];
		}

		$instance = new Gemini_Provider();

		/**
		 * Filter to register custom external providers.
		 */
		$instance = apply_filters( 'yukdiconfo_provider_instance', $instance, $provider_id );

		if ( $instance instanceof Provider_Interface ) {
			self::$instances[ $provider_id ] = $instance;
			return $instance;
		}

		return null;
	}

	/**
	 * Automatically determine provider from model string.
	 *
	 * @param string $model
	 * @return string Provider ID
	 */
	public static function resolve_provider_for_model( $model ) {
		return 'gemini';
	}

	/**
	 * Get list of available providers for YukDigitalz Connector for Google AI.
	 *
	 * @return array
	 */
	public static function get_available_providers() {
		$providers = array(
			'gemini' => array(
				'name'        => 'Google Gemini',
				'description' => 'Google Gemini 3.7 / 3.6 / 3.5 / 2.5 series with ultra-low latency & deep reasoning',
				'active'      => true,
			),
		);

		return apply_filters( 'yukdiconfo_available_providers', $providers );
	}
}
