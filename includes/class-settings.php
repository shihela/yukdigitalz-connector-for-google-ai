<?php
namespace YukDigitalz\AIConnectorGoogle;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * Manages plugin options, defaults, presets, and wp-config overrides for Google AI / Gemini.
 */
class Settings {

	/**
	 * Option key in wp_options.
	 */
	const OPTION_KEY = 'yukdiconfo_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Settings|null
	 */
	private static $instance = null;

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Settings
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
		$this->load_settings();
	}

	/**
	 * Load settings from database or fallback to defaults.
	 */
	private function load_settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( empty( $stored ) ) {
			$stored = get_option( 'yukdigitalz_ai_google_settings', array() );
		}
		$defaults = $this->get_default_settings();
		$this->settings = wp_parse_args( $stored, $defaults );
		if ( empty( $stored['enable_third_party_interceptor'] ) ) {
			$this->settings['enable_third_party_interceptor'] = true;
		}
		if ( empty( $stored['enable_circuit_breaker'] ) ) {
			$this->settings['enable_circuit_breaker'] = true;
		}

	}


	/**
	 * Get default plugin settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			// Google Gemini API Keys (Encrypted in DB or defined in wp-config.php).
			'default_provider'     => 'gemini',
			'gemini_api_key'       => '',
			'gemini_backup_keys'   => array(),

			// Primary Model selection.
			'primary_model'        => 'gemini-3.7-flash',
			'custom_model_id'      => '',

			// Failover & Routing settings (Gemini Model Failover & Key Rotation).
			'enable_failover'      => true,
			'fallback_models'      => array(
				'gemini-2.5-flash',
				'gemini-2.0-flash',
				'gemini-1.5-flash',
				'gemini-1.5-pro',
			),

			'enable_auto_retry'    => true,
			'max_retries_per_model'=> 2,
			'retry_delay_ms'       => 800,
			'failover_on_503'      => true,
			'failover_on_429'      => true,
			'failover_on_500'      => true,
			'failover_on_timeout'  => true,
			'enable_third_party_interceptor' => true,



			// Circuit Breaker Transient Protection (Cooldown on 503/429).
			'enable_circuit_breaker' => true,
			'cooldown_duration_sec'  => 120,

			// Request Defaults.
			'request_timeout_sec'  => 30,
			'default_temperature'  => 0.7,
			'default_top_p'        => 0.95,
			'default_max_tokens'   => 4096,
			'system_instruction'   => 'You are a helpful, accurate, and concise AI assistant powered by Google Gemini.',

			// Telemetry & Logging.
			'enable_telemetry'     => true,
			'log_retention_days'   => 30,
			'max_log_rows'         => 1000,

			// Cached dynamically fetched models from Google AI Studio.
			'cached_dynamic_models'=> array(),
			'models_last_fetched'  => 0,
		);
	}

	/**
	 * Set model transient cooldown (Circuit Breaker).
	 *
	 * @param string $model_id
	 * @param int $duration_sec
	 */
	public static function set_model_cooldown( $model_id, $duration_sec = 120 ) {
		if ( empty( $model_id ) ) {
			return;
		}
		$transient_key = 'yukdiconfo_cd_m_' . md5( trim( (string) $model_id ) );
		set_transient( $transient_key, time() + intval( $duration_sec ), intval( $duration_sec ) );
	}

	/**
	 * Check if a model is currently cooling down due to recent 503/429 errors.
	 *
	 * @param string $model_id
	 * @return bool
	 */
	public static function is_model_on_cooldown( $model_id ) {
		if ( empty( $model_id ) ) {
			return false;
		}
		$transient_key = 'yukdiconfo_cd_m_' . md5( trim( (string) $model_id ) );
		return (bool) get_transient( $transient_key );
	}

	/**
	 * Set API key transient cooldown (Circuit Breaker).
	 *
	 * @param string $api_key
	 * @param int $duration_sec
	 */
	public static function set_key_cooldown( $api_key, $duration_sec = 120 ) {
		if ( empty( $api_key ) ) {
			return;
		}
		$transient_key = 'yukdiconfo_cd_k_' . md5( trim( (string) $api_key ) );
		set_transient( $transient_key, time() + intval( $duration_sec ), intval( $duration_sec ) );
	}

	/**
	 * Check if an API key is currently cooling down.
	 *
	 * @param string $api_key
	 * @return bool
	 */
	public static function is_key_on_cooldown( $api_key ) {
		if ( empty( $api_key ) ) {
			return false;
		}
		$transient_key = 'yukdiconfo_cd_k_' . md5( trim( (string) $api_key ) );
		return (bool) get_transient( $transient_key );
	}



	/**
	 * Predefined list of known latest Google Gemini models.
	 *
	 * @return array
	 */
	public static function get_known_all_models() {
		return array(
			'gemini-3.7-flash' => array(
				'name'        => 'Gemini 3.7 Flash',
				'provider'    => 'gemini',
				'tag'         => 'Google - Latest Stable (Recommended)',
				'description' => 'Our latest and most capable Flash model, built for complex coding and agentic workflows.',
				'speed'       => 'Ultra Fast',
				'category'    => 'Google Gemini',
			),
			'gemini-3.6-flash' => array(
				'name'        => 'Gemini 3.6 Flash',
				'provider'    => 'gemini',
				'tag'         => 'Google - Stable',
				'description' => 'Balancing speed and multimodal capabilities across general tasks.',
				'speed'       => 'Ultra Fast',
				'category'    => 'Google Gemini',
			),
			'gemini-3.5-flash' => array(
				'name'        => 'Gemini 3.5 Flash',
				'provider'    => 'gemini',
				'tag'         => 'Google - Stable',
				'description' => 'Foundational Flash model with baseline speed and high reliability.',
				'speed'       => 'Fast',
				'category'    => 'Google Gemini',
			),
			'gemini-3.5-flash-lite' => array(
				'name'        => 'Gemini 3.5 Flash-Lite',
				'provider'    => 'gemini',
				'tag'         => 'Google - Cost Optimized',
				'description' => 'Fastest, most cost-effective model for high throughput.',
				'speed'       => 'Lightning',
				'category'    => 'Google Gemini',
			),
			'gemini-2.5-flash' => array(
				'name'        => 'Gemini 2.5 Flash',
				'provider'    => 'gemini',
				'tag'         => 'Google - Workhorse',
				'description' => 'Reliable multimodal model for daily high-volume AI text tasks.',
				'speed'       => 'Ultra Fast',
				'category'    => 'Google Gemini',
			),
			'gemini-2.5-pro' => array(
				'name'        => 'Gemini 2.5 Pro',
				'provider'    => 'gemini',
				'tag'         => 'Google - Deep Reasoning',
				'description' => 'Advanced reasoning model for complex coding, math, and deep analysis.',
				'speed'       => 'Standard',
				'category'    => 'Google Gemini',
			),
			'gemini-2.0-flash' => array(
				'name'        => 'Gemini 2.0 Flash',
				'provider'    => 'gemini',
				'tag'         => 'Google - High Performance',
				'description' => 'Next-gen Flash model optimized for low latency and multimodal understanding.',
				'speed'       => 'Ultra Fast',
				'category'    => 'Google Gemini',
			),
			'gemini-1.5-flash' => array(
				'name'        => 'Gemini 1.5 Flash',
				'provider'    => 'gemini',
				'tag'         => 'Google - Long Context',
				'description' => 'Fast and lightweight model supporting up to 1M token context window.',
				'speed'       => 'Fast',
				'category'    => 'Google Gemini',
			),
			'gemini-1.5-pro' => array(
				'name'        => 'Gemini 1.5 Pro',
				'provider'    => 'gemini',
				'tag'         => 'Google - 2M Context',
				'description' => 'Massive context window model capable of processing large documents & audio.',
				'speed'       => 'Standard',
				'category'    => 'Google Gemini',
			),
		);
	}

	/**
	 * Get connected status for WordPress Connectors Google Provider.
	 *
	 * @return array
	 */
	public static function get_wp_connectors_status() {
		$gemini_key = self::get_instance()->get_gemini_api_key();

		return array(
			'google' => array(
				'name'      => 'Google (Gemini API)',
				'connected' => ! empty( $gemini_key ),
				'provider'  => 'gemini',
			),
		);
	}

	public function get_all() {
		if ( null === $this->settings ) {
			$this->load_settings();
		}
		return $this->settings;
	}

	public function get( $key, $default = null ) {
		$all = $this->get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	public function update( array $new_settings ) {
		$current = $this->get_all();
		$merged = wp_parse_args( $new_settings, $current );
		$this->settings = $merged;
		$saved = update_option( self::OPTION_KEY, $merged );
		$this->sync_connectors_options( $merged );
		return $saved;
	}

	/**
	 * Sync settings to WordPress Connectors options for drop-in compatibility.
	 *
	 * @param array|null $settings_data
	 */
	public function sync_connectors_options( $settings_data = null ) {
		$data = is_array( $settings_data ) ? $settings_data : $this->get_all();
		update_option( 'yukdiconfo_connectors_settings', $data );
		update_option( 'yukdigitalz_ai_connectors_settings', $data );
	}

	/**
	 * Helper to look up API keys from WordPress Connectors (options-connectors.php) stored options.
	 *
	 * @param string $provider Default 'google'.
	 * @return string
	 */
	/**
	 * Flag to prevent recursion loop during WP Connectors option lookup.
	 *
	 * @var bool
	 */
	private static $is_looking_up_wp_connectors = false;

	public static function get_key_from_wp_connectors( $provider = 'google' ) {
		if ( self::$is_looking_up_wp_connectors ) {
			return '';
		}

		self::$is_looking_up_wp_connectors = true;
		$provider = strtolower( $provider );

		$filtered_key = apply_filters( 'connectors_get_credential', null, 'google' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( ! empty( $filtered_key ) && is_string( $filtered_key ) ) {
			self::$is_looking_up_wp_connectors = false;
			return $filtered_key;
		}
		$filtered_key = apply_filters( 'connectors_google_api_key', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( ! empty( $filtered_key ) && is_string( $filtered_key ) ) {
			self::$is_looking_up_wp_connectors = false;
			return $filtered_key;
		}

		$possible_keys = array(
			'connectors',
			'options_connectors',
			'connectors_options',
			'connectors_credentials',
			'connectors_settings',
			'google_api_key',
			'gemini_api_key',
			'connectors_google',
			'connectors_gemini',
			'ai_provider_for_google',
		);

		foreach ( $possible_keys as $opt_name ) {
			$val = get_option( $opt_name, null );
			if ( ! empty( $val ) ) {
				$extracted = self::parse_key_from_raw_val( $val );
				if ( ! empty( $extracted ) ) {
					self::$is_looking_up_wp_connectors = false;
					return $extracted;
				}
			}

			if ( is_multisite() ) {
				$site_val = get_site_option( $opt_name, null );
				if ( ! empty( $site_val ) ) {
					$extracted = self::parse_key_from_raw_val( $site_val );
					if ( ! empty( $extracted ) ) {
						self::$is_looking_up_wp_connectors = false;
						return $extracted;
					}
				}
			}
		}

		// Direct database discovery fallback in wp_options
		global $wpdb;
		if ( isset( $wpdb->options ) ) {
			$like_pattern = '%' . $wpdb->esc_like( 'google' ) . '%';
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 20", // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like_pattern
				),
				ARRAY_A
			);

			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$extracted = self::parse_key_from_raw_val( maybe_unserialize( $row['option_value'] ) );
					if ( ! empty( $extracted ) ) {
						self::$is_looking_up_wp_connectors = false;
						return $extracted;
					}
				}
			}
		}

		self::$is_looking_up_wp_connectors = false;
		return '';
	}


	/**
	 * Parse raw option value (string, JSON, or array) to extract valid Google AI key.
	 *
	 * @param mixed $val
	 * @return string
	 */
	private static function parse_key_from_raw_val( $val ) {
		if ( is_string( $val ) ) {
			$json = json_decode( $val, true );
			if ( is_array( $json ) ) {
				$found = self::extract_key_from_array( $json );
				if ( ! empty( $found ) ) return $found;
			}

			if ( strpos( $val, 'AIzaSy' ) === 0 ) {
				return trim( $val );
			}
		} elseif ( is_array( $val ) ) {
			$found = self::extract_key_from_array( $val );
			if ( ! empty( $found ) ) return $found;
		}

		return '';
	}

	/**
	 * Recursively extract API Key from nested arrays.
	 *
	 * @param array $arr
	 * @return string
	 */
	private static function extract_key_from_array( array $arr ) {
		foreach ( $arr as $k => $v ) {
			$k_lower = strtolower( (string) $k );

			if ( is_array( $v ) ) {
				if ( strpos( $k_lower, 'google' ) !== false || strpos( $k_lower, 'gemini' ) !== false ) {
					if ( isset( $v['api_key'] ) && is_string( $v['api_key'] ) ) return $v['api_key'];
					if ( isset( $v['apiKey'] ) && is_string( $v['apiKey'] ) ) return $v['apiKey'];
					if ( isset( $v['key'] ) && is_string( $v['key'] ) ) return $v['key'];
				}

				$nested = self::extract_key_from_array( $v );
				if ( ! empty( $nested ) ) return $nested;
			} elseif ( is_string( $v ) ) {
				if ( strpos( $v, 'AIzaSy' ) === 0 ) {
					return $v;
				}
			}
		}

		return '';
	}

	// Gemini Key (wp-config -> env -> plugin setting -> WordPress Connectors)
	public function get_gemini_api_key() {
		if ( defined( 'YUKDICONFO_API_KEY' ) && ! empty( YUKDICONFO_API_KEY ) ) {
			return YUKDICONFO_API_KEY;
		}
		if ( defined( 'GOOGLE_API_KEY' ) && ! empty( GOOGLE_API_KEY ) ) {
			return GOOGLE_API_KEY;
		}
		if ( defined( 'GEMINI_API_KEY' ) && ! empty( GEMINI_API_KEY ) ) {
			return GEMINI_API_KEY;
		}
		$env_key = getenv( 'GOOGLE_API_KEY' );
		if ( ! empty( $env_key ) ) {
			return sanitize_text_field( $env_key );
		}
		if ( isset( $_ENV['GOOGLE_API_KEY'] ) && ! empty( $_ENV['GOOGLE_API_KEY'] ) ) {
			return sanitize_text_field( wp_unslash( $_ENV['GOOGLE_API_KEY'] ) );
		}
		if ( isset( $_SERVER['GOOGLE_API_KEY'] ) && ! empty( $_SERVER['GOOGLE_API_KEY'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['GOOGLE_API_KEY'] ) );
		}

		$enc = $this->get( 'gemini_api_key', '' );
		if ( ! empty( $enc ) ) {
			return Security::decrypt( $enc );
		}
		return self::get_key_from_wp_connectors( 'google' );
	}

	public function get_all_gemini_api_keys() {
		$keys = array();
		$primary = $this->get_gemini_api_key();
		if ( ! empty( $primary ) ) {
			$keys[] = $primary;
		}
		$backup_keys = $this->get( 'gemini_backup_keys', array() );
		if ( is_array( $backup_keys ) ) {
			foreach ( $backup_keys as $enc_key ) {
				$decrypted = Security::decrypt( $enc_key );
				if ( ! empty( $decrypted ) && ! in_array( $decrypted, $keys, true ) ) {
					$keys[] = $decrypted;
				}
			}
		}
		return $keys;
	}

	/**
	 * Check if API key is defined in wp-config.php constant.
	 *
	 * @return bool
	 */
	public function is_api_key_hardcoded() {
		return ( defined( 'YUKDICONFO_API_KEY' ) && ! empty( YUKDICONFO_API_KEY ) ) ||
		       ( defined( 'GOOGLE_API_KEY' ) && ! empty( GOOGLE_API_KEY ) ) ||
		       ( defined( 'GEMINI_API_KEY' ) && ! empty( GEMINI_API_KEY ) );
	}





	public function get_effective_model() {
		$custom = trim( (string) $this->get( 'custom_model_id', '' ) );
		if ( ! empty( $custom ) ) {
			return $custom;
		}
		$primary = (string) $this->get( 'primary_model', 'gemini-3.7-flash' );
		return ! empty( $primary ) ? $primary : 'gemini-3.7-flash';
	}



	/**
	 * Get cached dynamic models fetched from Google AI Studio.
	 *
	 * @return array
	 */
	public function get_dynamic_models() {
		$models = $this->get( 'cached_dynamic_models', array() );
		return is_array( $models ) ? $models : array();
	}

	/**
	 * Get masked primary or specified API key.
	 *
	 * @param string|null $key Optional key to mask.
	 * @return string Masked key string.
	 */
	public function get_masked_key( $key = null ) {
		if ( null === $key ) {
			$key = $this->get_gemini_api_key();
		}
		return Security::mask_api_key( $key );
	}

	public function ensure_defaults() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, $this->get_default_settings() );
		}
		$this->sync_connectors_options();
	}
}

