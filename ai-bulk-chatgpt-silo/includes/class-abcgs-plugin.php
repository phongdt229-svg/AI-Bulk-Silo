<?php
/**
 * Khởi động plugin: cron, nâng cấp dữ liệu, giao diện quản trị.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Plugin {

	const WATCHDOG = 'abcgs_watchdog';

	public static function boot() {
		ABCGS_Upgrade::maybe_run();

		add_action( ABCGS_Queue::HOOK, array( 'ABCGS_Queue', 'process' ) );
		add_action( ABCGS_Image_Queue::HOOK, array( 'ABCGS_Image_Queue', 'process' ) );
		add_action( self::WATCHDOG, array( __CLASS__, 'watchdog' ) );

		if ( is_admin() ) {
			// Cài đè file (không kích hoạt lại) thì activate() không chạy.
			if ( ! wp_next_scheduled( self::WATCHDOG ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::WATCHDOG );
			}
			ABCGS_Admin::init();
		}
	}

	/**
	 * Mỗi giờ: hẹn lại cron nếu còn việc mà lịch bị mất.
	 */
	public static function watchdog() {
		ABCGS_Queue::ensure_scheduled();
		ABCGS_Image_Queue::schedule();
	}

	public static function activate() {
		ABCGS_Upgrade::maybe_run();
		if ( ! wp_next_scheduled( self::WATCHDOG ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::WATCHDOG );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( ABCGS_Queue::HOOK );
		wp_clear_scheduled_hook( ABCGS_Image_Queue::HOOK );
		wp_clear_scheduled_hook( self::WATCHDOG );
		ABCGS_Lock::release( 'queue' );
		ABCGS_Lock::release( 'image_queue' );
	}
}
