<?php
namespace YukDigitalz\AIConnectorGoogle;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Security
 *
 * Handles data encryption, decryption, nonce checks, and sanitization.
 */
class Security {

	/**
	 * Encryption cipher method.
	 */
	const CIPHER = 'aes-256-cbc';

	/**
	 * Get encryption secret key derived from WordPress Salt.
	 *
	 * @return string 32-byte secret key.
	 */
	private static function get_secret_key() {
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'NONCE_SALT' ) ? NONCE_SALT : 'yukdiconfo_salt_2026' );
		return hash( 'sha256', $salt, true );
	}

	/**
	 * Encrypt sensitive plain text string (e.g. API key).
	 *
	 * @param string $plain_text Text to encrypt.
	 * @return string Base64 encoded IV and ciphertext.
	 */
	public static function encrypt( $plain_text ) {
		if ( empty( $plain_text ) || ! is_string( $plain_text ) ) {
			return '';
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			// Fallback to base64 if OpenSSL is not available.
			return 'b64:' . base64_encode( $plain_text );
		}

		$key = self::get_secret_key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv = openssl_random_pseudo_bytes( $iv_len );

		$ciphertext = openssl_encrypt( $plain_text, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return 'b64:' . base64_encode( $plain_text );
		}

		return 'enc:' . base64_encode( $iv . $ciphertext );
	}

	/**
	 * Decrypt encrypted text string.
	 *
	 * @param string $encrypted_text Encrypted text with prefix.
	 * @return string Decrypted plain text string.
	 */
	public static function decrypt( $encrypted_text ) {
		if ( empty( $encrypted_text ) || ! is_string( $encrypted_text ) ) {
			return '';
		}

		// Handle fallback base64 format.
		if ( strpos( $encrypted_text, 'b64:' ) === 0 ) {
			$encoded = substr( $encrypted_text, 4 );
			return base64_decode( $encoded );
		}

		// Handle AES-256 encrypted format.
		if ( strpos( $encrypted_text, 'enc:' ) === 0 ) {
			if ( ! extension_loaded( 'openssl' ) ) {
				return '';
			}

			$data = base64_decode( substr( $encrypted_text, 4 ) );
			$key = self::get_secret_key();
			$iv_len = openssl_cipher_iv_length( self::CIPHER );

			if ( strlen( $data ) <= $iv_len ) {
				return '';
			}

			$iv = substr( $data, 0, $iv_len );
			$ciphertext = substr( $data, $iv_len );

			$plain_text = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
			return false !== $plain_text ? $plain_text : '';
		}

		// If not encrypted with prefix (e.g. legacy plain text), return as is.
		return $encrypted_text;
	}

	/**
	 * Mask sensitive API Key for display in UI (e.g. AIzaSy...9xyz).
	 *
	 * @param string $api_key Plain or encrypted API Key.
	 * @return string Masked string.
	 */
	public static function mask_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		$plain = self::decrypt( $api_key );
		$length = strlen( $plain );

		if ( $length <= 8 ) {
			return '••••••••';
		}

		$start = substr( $plain, 0, 6 );
		$end = substr( $plain, -4 );
		return $start . '••••••••' . $end;
	}

	/**
	 * Verify user permissions for admin actions.
	 *
	 * @param string $capability Default 'manage_options'.
	 * @return bool
	 */
	public static function verify_admin( $capability = 'manage_options' ) {
		return is_user_logged_in() && current_user_can( $capability );
	}

	/**
	 * Verify CSRF Nonce.
	 *
	 * @param string $nonce Nonce token.
	 * @param string $action Action name.
	 * @return bool
	 */
	public static function verify_nonce( $nonce, $action = 'yukdiconfo_admin_nonce' ) {
		return (bool) wp_verify_nonce( $nonce, $action );
	}
}

