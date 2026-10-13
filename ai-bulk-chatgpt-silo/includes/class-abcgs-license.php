<?php
/**
 * License Free/Pro.
 *
 * Máy chủ license là nơi duy nhất quyết định hạn mức. Option abcgs_license chỉ
 * lưu bản sao để hiển thị, không dùng để cho phép viết bài.
 *
 * Hợp đồng API: tài liệu mục 13.3 (v1) và 14.6 (v2: edition, plugin_version,
 * idempotency_key, nonce; phản hồi daily_limit, used_today, reset_at).
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_License {

	const OPTION  = 'abcgs_license';
	const API_URL = 'https://famemedia.vn/licenses/license-api.php';

	const FREE_DAILY_LIMIT = 1;

	/**
	 * Bản sao trạng thái license để hiển thị.
	 */
	public static function state() {
		$state = get_option( self::OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		unset( $state['serial'] );
		return wp_parse_args(
			$state,
			array(
				'edition'     => '',
				'email'       => '',
				'status'      => 'none',
				'plan'        => '',
				'max_posts'   => 0,
				'used_posts'  => 0,
				'daily_limit' => 0,
				'used_today'  => 0,
				'reset_at'    => 0,
				'checked_at'  => 0,
				'message'     => '',
			)
		);
	}

	public static function is_configured() {
		$s = self::state();
		if ( 'free' === $s['edition'] ) {
			return '' !== $s['email'];
		}
		if ( 'pro' === $s['edition'] ) {
			return '' !== $s['email'] && '' !== ABCGS_Secrets::get( 'serial' );
		}
		return false;
	}

	public static function save_config( $edition, $email, $serial = null ) {
		$state            = self::state();
		$state['edition'] = 'pro' === $edition ? 'pro' : 'free';
		$state['email']   = sanitize_email( $email );
		$state['status']  = 'unknown';
		$state['message'] = '';
		update_option( self::OPTION, $state, false );
		if ( null !== $serial ) {
			ABCGS_Secrets::set( 'serial', $serial );
		}
	}

	public static function remove() {
		delete_option( self::OPTION );
		ABCGS_Secrets::set( 'serial', '' );
	}

	/**
	 * Domain chuẩn hóa: bỏ www., bỏ cổng, chữ thường (giống v6.1.1).
	 */
	public static function domain() {
		$site = site_url();
		$host = wp_parse_url( $site, PHP_URL_HOST );
		$host = $host ? $host : $site;
		return strtolower( preg_replace( '/^www\./i', '', preg_replace( '/:\d+$/', '', (string) $host ) ) );
	}

	/**
	 * Hỏi máy chủ còn được viết bài không.
	 *
	 * @return array {
	 *   can_write: bool,
	 *   retry_at:  int   Thời điểm nên thử lại (0 = không tự thử lại),
	 *   transient: bool  Lỗi tạm thời (mạng, máy chủ), nên thử lại sau,
	 *   message:   string
	 * }
	 */
	public static function check() {
		if ( ! self::is_configured() ) {
			return self::verdict( false, 0, false, 'Chưa cấu hình license. Vào trang License để chọn Free hoặc Pro.' );
		}
		$res = self::request( 'validate' );
		return self::interpret( $res, 'validate' );
	}

	/**
	 * Trừ 1 bài trên máy chủ. $idempotency_key giúp máy chủ không trừ hai lần
	 * khi plugin gửi lại do mất phản hồi.
	 */
	public static function consume( $idempotency_key ) {
		if ( ! self::is_configured() ) {
			return self::verdict( false, 0, false, 'Chưa cấu hình license.' );
		}
		$res = self::request(
			'consume',
			array(
				'count'           => 1,
				'idempotency_key' => (string) $idempotency_key,
			)
		);
		return self::interpret( $res, 'consume' );
	}

	private static function verdict( $can_write, $retry_at, $transient, $message ) {
		return array(
			'can_write' => (bool) $can_write,
			'retry_at'  => intval( $retry_at ),
			'transient' => (bool) $transient,
			'message'   => (string) $message,
		);
	}

	private static function request( $action, array $extra = array() ) {
		$state = self::state();
		$nonce = wp_generate_password( 20, false, false );
		$body  = array_merge(
			array(
				'action'         => $action,
				'edition'        => $state['edition'],
				'serial'         => 'pro' === $state['edition'] ? ABCGS_Secrets::get( 'serial' ) : '',
				'email'          => $state['email'],
				'domain'         => self::domain(),
				'site_url'       => site_url(),
				'plugin_version' => ABCGS_VERSION,
				'nonce'          => $nonce,
			),
			$extra
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout'   => 25,
				'sslverify' => true,
				'body'      => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'network' => true,
				'message' => 'Không kết nối được máy chủ license: ' . $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		if ( $code >= 500 || 429 === $code ) {
			return array(
				'network' => true,
				'message' => 'Máy chủ license đang lỗi (HTTP ' . $code . ').',
			);
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array(
				'network' => true,
				'message' => 'Máy chủ license trả dữ liệu không hợp lệ (HTTP ' . $code . ').',
			);
		}

		if ( ! self::verify_signature( $response, $raw, $data, $nonce ) ) {
			return array(
				'network' => true,
				'message' => 'Chữ ký phản hồi license không hợp lệ.',
			);
		}

		return array(
			'network' => false,
			'data'    => $data,
		);
	}

	/**
	 * Kiểm chữ ký Ed25519 của phản hồi khi khai báo ABCGS_LICENSE_PUBLIC_KEY
	 * (base64) trong wp-config.php. Máy chủ gửi chữ ký thân phản hồi ở header
	 * X-ABCGS-Signature và trả lại nonce + ts trong JSON.
	 */
	private static function verify_signature( $response, $raw, array $data, $nonce ) {
		if ( ! defined( 'ABCGS_LICENSE_PUBLIC_KEY' ) || '' === (string) ABCGS_LICENSE_PUBLIC_KEY ) {
			return true;
		}
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) && file_exists( ABSPATH . WPINC . '/sodium_compat/autoload.php' ) ) {
			require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
		}
		$public = base64_decode( (string) ABCGS_LICENSE_PUBLIC_KEY, true );
		$sig    = base64_decode( (string) wp_remote_retrieve_header( $response, 'x-abcgs-signature' ), true );
		if ( ! $public || ! $sig || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}
		try {
			if ( ! sodium_crypto_sign_verify_detached( $sig, $raw, $public ) ) {
				return false;
			}
		} catch ( Exception $e ) {
			return false;
		}
		return hash_equals( $nonce, (string) ( $data['nonce'] ?? '' ) ) && abs( time() - intval( $data['ts'] ?? 0 ) ) <= 300;
	}

	private static function interpret( array $res, $action ) {
		$state               = self::state();
		$state['checked_at'] = time();

		if ( ! empty( $res['network'] ) ) {
			$state['message'] = $res['message'];
			update_option( self::OPTION, $state, false );
			return self::verdict( false, time() + 10 * MINUTE_IN_SECONDS, true, $res['message'] );
		}

		$data    = $res['data'];
		$ok      = 'ok' === ( $data['status'] ?? '' );
		$code    = sanitize_key( (string) ( $data['code'] ?? '' ) );
		$code    = strtoupper( $code );
		// Chỉ lấy message; không bao giờ hiển thị expected_email / expected_domain.
		$message = sanitize_text_field( (string) ( $data['message'] ?? '' ) );

		foreach ( array( 'max_posts', 'used_posts', 'daily_limit', 'used_today', 'reset_at' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$state[ $field ] = intval( $data[ $field ] );
			}
		}
		if ( isset( $data['plan'] ) ) {
			$state['plan'] = sanitize_text_field( (string) $data['plan'] );
		}
		$is_free = 'free' === $state['edition'];

		if ( ! $ok ) {
			if ( in_array( $code, array( 'DAILY_LIMIT', 'QUOTA_EXCEEDED' ), true ) ) {
				$state['status'] = 'valid';
			} else {
				$state['status'] = 'invalid';
			}
			$state['message'] = $message ? $message : 'License bị từ chối' . ( $code ? ' [' . $code . ']' : '' ) . '.';
			update_option( self::OPTION, $state, false );

			if ( 'DAILY_LIMIT' === $code || ( $is_free && 'QUOTA_EXCEEDED' === $code ) ) {
				return self::verdict( false, self::next_reset( $state ), false, 'Bản Free đã dùng hết ' . self::FREE_DAILY_LIMIT . ' bài hôm nay.' );
			}
			if ( 'QUOTA_EXCEEDED' === $code ) {
				return self::verdict( false, 0, false, 'Đã dùng hết số bài của gói. Mua thêm hoặc gia hạn tại aibulksilo.com.' );
			}
			return self::verdict( false, 0, false, $state['message'] );
		}

		$state['status']  = 'valid';
		$state['message'] = '';
		update_option( self::OPTION, $state, false );

		if ( 'consume' === $action ) {
			return self::verdict( true, 0, false, '' );
		}

		if ( $is_free ) {
			$limit = $state['daily_limit'] > 0 ? $state['daily_limit'] : self::FREE_DAILY_LIMIT;
			if ( $state['used_today'] >= $limit ) {
				return self::verdict( false, self::next_reset( $state ), false, 'Bản Free đã dùng hết ' . $limit . ' bài hôm nay.' );
			}
			return self::verdict( true, 0, false, '' );
		}

		if ( $state['max_posts'] <= 0 || $state['used_posts'] >= $state['max_posts'] ) {
			return self::verdict( false, 0, false, 'Đã dùng hết số bài của gói. Mua thêm hoặc gia hạn tại aibulksilo.com.' );
		}
		return self::verdict( true, 0, false, '' );
	}

	/**
	 * Thời điểm hết hạn mức ngày: theo máy chủ nếu có, không thì 00:05 ngày mai giờ site.
	 */
	private static function next_reset( array $state ) {
		if ( ! empty( $state['reset_at'] ) && $state['reset_at'] > time() ) {
			return intval( $state['reset_at'] );
		}
		$tomorrow = new DateTime( 'tomorrow 00:05', wp_timezone() );
		return $tomorrow->getTimestamp();
	}

	/**
	 * Dòng mô tả ngắn cho giao diện.
	 */
	public static function summary() {
		$s = self::state();
		if ( 'free' === $s['edition'] ) {
			$limit = $s['daily_limit'] > 0 ? $s['daily_limit'] : self::FREE_DAILY_LIMIT;
			return sprintf( 'Free · %d/%d bài hôm nay · 1 domain (%s)', $s['used_today'], $limit, self::domain() );
		}
		if ( 'pro' === $s['edition'] ) {
			return sprintf( 'Pro %s · đã dùng %d/%d bài · còn %d', $s['plan'], $s['used_posts'], $s['max_posts'], max( 0, $s['max_posts'] - $s['used_posts'] ) );
		}
		return 'Chưa cấu hình license';
	}
}
