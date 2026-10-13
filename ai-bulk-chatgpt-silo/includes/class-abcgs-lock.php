<?php
/**
 * Khóa nguyên tử dựa trên khóa chính của bảng options.
 *
 * INSERT IGNORE chỉ thành công cho một tiến trình; add_option() của WordPress
 * dùng ON DUPLICATE KEY UPDATE nên không dùng được làm khóa.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Lock {

	const PREFIX = 'abcgs_lock_';

	public static function acquire( $name, $ttl = 300 ) {
		global $wpdb;
		$option = self::PREFIX . $name;
		$now    = time();

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$option,
				(string) ( $now + $ttl )
			)
		);
		if ( $inserted ) {
			self::flush_cache( $option );
			return true;
		}

		// Khóa cũ đã quá hạn (tiến trình trước bị dừng giữa chừng): xóa có điều kiện rồi thử lại.
		$expires = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		if ( null !== $expires && intval( $expires ) < $now ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
					$option,
					$expires
				)
			);
			if ( $deleted ) {
				return self::acquire( $name, $ttl );
			}
		}
		return false;
	}

	/**
	 * Chờ tối đa $wait giây để lấy khóa ngắn (dùng cho thao tác đọc-sửa-ghi).
	 */
	public static function acquire_wait( $name, $ttl = 30, $wait = 10 ) {
		$deadline = microtime( true ) + $wait;
		do {
			if ( self::acquire( $name, $ttl ) ) {
				return true;
			}
			usleep( 150000 );
		} while ( microtime( true ) < $deadline );
		return false;
	}

	public static function release( $name ) {
		global $wpdb;
		$option = self::PREFIX . $name;
		$wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );
		self::flush_cache( $option );
	}

	public static function is_locked( $name ) {
		global $wpdb;
		$expires = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::PREFIX . $name ) );
		return null !== $expires && intval( $expires ) >= time();
	}

	/**
	 * Đọc-sửa-ghi một option mảng dưới khóa ngắn, để worker và thao tác quản
	 * trị không ghi đè thay đổi của nhau. $fn nhận mảng theo tham chiếu.
	 *
	 * @return mixed|false Giá trị $fn trả về, false nếu không lấy được khóa.
	 */
	public static function mutate( $option, callable $fn ) {
		$lock = 'w_' . $option;
		if ( ! self::acquire_wait( $lock, 30, 10 ) ) {
			return false;
		}
		try {
			$value = self::fresh_option( $option, array() );
			$value = is_array( $value ) ? $value : array();
			$result = $fn( $value );
			update_option( $option, $value, false );
			return null === $result ? true : $result;
		} finally {
			self::release( $lock );
		}
	}

	/**
	 * Đọc option bỏ qua object cache, dùng khi đang giữ khóa.
	 */
	public static function fresh_option( $option, $default = array() ) {
		self::flush_cache( $option );
		return get_option( $option, $default );
	}

	private static function flush_cache( $option ) {
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}
}
