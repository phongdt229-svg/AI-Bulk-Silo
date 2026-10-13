<?php
/**
 * Gỡ plugin: xóa cấu hình, bí mật, queue, khóa và lịch cron.
 * Giữ nguyên bài viết, ảnh và meta bài đã tạo.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$abcgs_options = array(
	'abcgs_settings',
	'abcgs_secrets',
	'abcgs_license',
	'abcgs_queue',
	'abcgs_batches',
	'abcgs_silo_log',
	'abcgs_image_bulk_queue',
	'abcgs_image_scan_results',
	'abcgs_recent_written_ids',
	'abcgs_image_decision_pending',
	'abcgs_heading_image_limit',
	'abcgs_db_version',
);
foreach ( $abcgs_options as $abcgs_option ) {
	delete_option( $abcgs_option );
}

global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'abcgs_lock_' ) . '%' ) );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_abcgs_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_abcgs_' ) . '%'
	)
);

foreach ( array( 'abcgs_process_queue_event', 'abcgs_process_image_bulk_queue', 'abcgs_watchdog' ) as $abcgs_hook ) {
	wp_clear_scheduled_hook( $abcgs_hook );
}
