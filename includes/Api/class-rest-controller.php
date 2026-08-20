<?php
namespace YukDigitalz\AIConnectorGoogle\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use YukDigitalz\AIConnectorGoogle\Engine\Failover_Router;
use YukDigitalz\AIConnectorGoogle\Settings;
use YukDigitalz\AIConnectorGoogle\Telemetry_Logger;
use YukDigitalz\AIConnectorGoogle\Providers\Provider_Factory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest_Controller
 *
 * Exposes REST API endpoints for YukDigitalz Connector for Google AI.
 */
class Rest_Controller extends WP_REST_Controller {

	/**
	 * Primary Namespace for REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'yukdiconfo/v1';

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		$namespaces = array( $this->namespace, 'yukdigitalz-ai-google/v1', 'yuk-ai/v1' );

		foreach ( $namespaces as $ns ) {
			// POST /generate
			register_rest_route(
				$ns,
				'/generate',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'handle_generate' ),
						'permission_callback' => array( $this, 'permissions_check' ),
						'args'                => array(
							'prompt' => array(
								'required'          => true,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_textarea_field',
							),
							'model' => array(
								'required'          => false,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_text_field',
							),
							'temperature' => array(
								'required'          => false,
								'type'              => 'number',
							),
							'max_tokens' => array(
								'required'          => false,
								'type'              => 'integer',
							),
							'system_instruction' => array(
								'required'          => false,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_textarea_field',
							),
						),
					),
				)
			);

			// GET /models
			register_rest_route(
				$ns,
				'/models',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'handle_get_models' ),
						'permission_callback' => array( $this, 'permissions_check' ),
					),
				)
			);

			// GET /status
			register_rest_route(
				$ns,
				'/status',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'handle_get_status' ),
						'permission_callback' => array( $this, 'permissions_check' ),
					),
				)
			);
		}
	}

	/**
	 * Permission check: User must be logged in and have manage_options or edit_posts capability.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function permissions_check( $request ) {
		if ( is_user_logged_in() && ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) ) ) {
			return true;
		}

		$custom_auth = apply_filters( 'yukdiconfo_rest_permission', false, $request );
		if ( true === $custom_auth ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Authentication required to access the YukDigitalz Connector REST API.', 'yukdigitalz-connector-for-google-ai' ),
				array( 'status' => 401 )
			);
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to use the YukDigitalz Connector for Google AI REST API.', 'yukdigitalz-connector-for-google-ai' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Standard WP_REST_Controller permissions check for GET requests.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->permissions_check( $request );
	}

	/**
	 * Standard WP_REST_Controller permissions check for POST requests.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->permissions_check( $request );
	}

	/**
	 * Handle generation request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_generate( $request ) {
		$prompt = $request->get_param( 'prompt' );
		$options = array(
			'client_source' => 'rest_api',
		);

		if ( $request->has_param( 'model' ) ) {
			$options['model'] = $request->get_param( 'model' );
		}
		if ( $request->has_param( 'temperature' ) ) {
			$options['temperature'] = floatval( $request->get_param( 'temperature' ) );
		}
		if ( $request->has_param( 'max_tokens' ) ) {
			$options['max_tokens'] = intval( $request->get_param( 'max_tokens' ) );
		}
		if ( $request->has_param( 'system_instruction' ) ) {
			$options['system_instruction'] = $request->get_param( 'system_instruction' );
		}

		$result = Failover_Router::execute( $prompt, $options );

		if ( is_wp_error( $result ) ) {
			$status_code = 500;
			$data = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status_code'] ) && $data['status_code'] > 0 ) {
				$status_code = $data['status_code'];
			}

			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status_code )
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle retrieving available models.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_get_models( $request ) {
		$models = yukdiconfo_get_all_models();
		$active = yukdiconfo_get_active_model();

		return new WP_REST_Response(
			array(
				'active_model' => $active,
				'models'       => $models,
			),
			200
		);
	}

	/**
	 * Handle retrieving system status & stats.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_get_status( $request ) {
		$stats = Telemetry_Logger::get_stats();
		$settings = Settings::get_instance();

		return new WP_REST_Response(
			array(
				'status'       => 'operational',
				'active_model' => $settings->get_effective_model(),
				'has_key'      => ! empty( $settings->get_gemini_api_key() ),
				'key_source'   => $settings->is_api_key_hardcoded() ? 'wp-config.php' : 'database',
				'stats'        => $stats,
			),
			200
		);
	}
}
