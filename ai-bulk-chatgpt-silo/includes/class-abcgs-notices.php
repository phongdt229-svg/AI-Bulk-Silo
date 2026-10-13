<?php
/**
 * Thông báo quản trị lưu tạm theo người dùng (không truyền qua URL).
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Notices {

	private static function key() {
		return 'abcgs_notice_' . get_current_user_id();
	}

	public static function add( $message, $type = 'success' ) {
		$type  = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';
		$items = get_transient( self::key() );
		$items = is_array( $items ) ? $items : array();
		$items[] = array(
			'type'    => $type,
			'message' => (string) $message,
		);
		set_transient( self::key(), array_slice( $items, -10 ), 300 );
	}

	public static function render() {
		$items = get_transient( self::key() );
		if ( ! is_array( $items ) || ! $items ) {
			return;
		}
		delete_transient( self::key() );
		foreach ( $items as $item ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $item['type'] ),
				esc_html( $item['message'] )
			);
		}
	}
}
