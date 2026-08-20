<?php
namespace YukDigitalz\AIConnectorGoogle;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 *
 * PSR-4 compliant autoloader for YukDigitalz\AIConnectorGoogle namespace.
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class_name Full class name with namespace.
	 */
	public static function autoload( $class_name ) {
		$prefix = 'YukDigitalz\\AIConnectorGoogle\\';

		// Check if class uses this namespace.
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		// Get relative class name.
		$relative_class = substr( $class_name, $len );

		// Convert namespace separators to directory separators.
		$parts = explode( '\\', $relative_class );
		$class_file_name = array_pop( $parts );
		$slug = strtolower( str_replace( '_', '-', $class_file_name ) );

		// Candidate filenames
		$candidates = array(
			'class-' . $slug . '.php',
			'interface-' . $slug . '.php',
		);

		// If ends with -interface, check stripped version e.g. interface-provider.php
		if ( substr( $slug, -10 ) === '-interface' ) {
			$stripped = substr( $slug, 0, -10 );
			$candidates[] = 'interface-' . $stripped . '.php';
			$candidates[] = 'class-' . $stripped . '.php';
		}

		// Build path.
		$sub_dir = '';
		if ( ! empty( $parts ) ) {
			$sub_dir = implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR;
		}

		$base_dir = YUKDICONFO_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR;

		foreach ( $candidates as $candidate ) {
			$file = $base_dir . $sub_dir . $candidate;
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}

