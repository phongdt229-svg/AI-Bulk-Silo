<?php
/**
 * Kho bí mật: OpenAI API key và Serial Key.
 *
 * - Ưu tiên hằng số trong wp-config.php (ABCGS_OPENAI_API_KEY, ABCGS_LICENSE_SERIAL).
 * - Giá trị lưu DB được mã hóa bằng khóa dẫn xuất từ salt của WordPress,
 *   option không autoload. Mã hóa chỉ giảm rủi ro khi lộ bản dump database,
 *   không thay thế bảo vệ máy chủ.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Secrets {

	const OPTION = 'abcgs_secrets';

	private static $constants = array(
		'api_key' => 'ABCGS_OPENAI_API_KEY',
		'serial'  => 'ABCGS_LICENSE_SERIAL',
	);

	public static function from_constant( $name ) {
		$const = self::$constants[ $name ] ?? '';
		return ( $const && defined( $const ) && '' !== (string) constant( $const ) );
	}

	public static function get( $name ) {
		if ( self::from_constant( $name ) ) {
			return trim( (string) constant( self::$constants[ $name ] ) );
		}
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) || empty( $all[ $name ] ) ) {
			return '';
		}
		$plain = self::decrypt( (string) $all[ $name ] );
		return false === $plain ? '' : $plain;
	}

	public static function set( $name, $value ) {
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			unset( $all[ $name ] );
		} else {
			$all[ $name ] = self::encrypt( $value );
		}
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Lưu được nhưng không giải mã được (salt trong wp-config đã đổi).
	 */
	public static function is_unreadable( $name ) {
		if ( self::from_constant( $name ) ) {
			return false;
		}
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) && ! empty( $all[ $name ] ) && false === self::decrypt( (string) $all[ $name ] );
	}

	public static function mask( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		$tail = mb_substr( $value, -4 );
		$head = 0 === strpos( $value, 'sk-' ) ? 'sk-' : '';
		return $head . '••••' . $tail;
	}

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|abcgs-secrets', true );
	}

	private static function load_sodium() {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) && file_exists( ABSPATH . WPINC . '/sodium_compat/autoload.php' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}
		return function_exists( 'sodium_crypto_secretbox' );
	}

	private static function encrypt( $plain ) {
		$key = self::key();
		if ( self::load_sodium() ) {
			$nonce = random_bytes( 24 );
			return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
		}
		if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $cipher ) {
				return 'o1:' . base64_encode( $iv . $tag . $cipher );
			}
		}
		return 'p0:' . base64_encode( $plain );
	}

	private static function decrypt( $stored ) {
		$prefix = substr( $stored, 0, 3 );
		$raw    = base64_decode( substr( $stored, 3 ), true );
		if ( false === $raw ) {
			return false;
		}
		$key = self::key();
		if ( 's1:' === $prefix ) {
			if ( ! self::load_sodium() || strlen( $raw ) < 25 ) {
				return false;
			}
			$plain = sodium_crypto_secretbox_open( substr( $raw, 24 ), substr( $raw, 0, 24 ), $key );
			return false === $plain ? false : $plain;
		}
		if ( 'o1:' === $prefix ) {
			if ( strlen( $raw ) < 29 || ! function_exists( 'openssl_decrypt' ) ) {
				return false;
			}
			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $plain ? false : $plain;
		}
		if ( 'p0:' === $prefix ) {
			return $raw;
		}
		return false;
	}
}
