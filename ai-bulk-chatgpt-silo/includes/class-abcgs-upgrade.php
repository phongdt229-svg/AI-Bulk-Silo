<?php
/**
 * Nâng cấp dữ liệu từ v6.x lên v7.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Upgrade {

	const OPTION = 'abcgs_db_version';

	public static function maybe_run() {
		$version = (string) get_option( self::OPTION, '' );
		if ( version_compare( $version, ABCGS_VERSION, '>=' ) ) {
			return;
		}
		if ( ! ABCGS_Lock::acquire( 'upgrade', 120 ) ) {
			return;
		}
		try {
			if ( version_compare( $version, '7.0.0', '<' ) ) {
				self::to_700();
			}
			update_option( self::OPTION, ABCGS_VERSION, true );
		} finally {
			ABCGS_Lock::release( 'upgrade' );
		}
	}

	/**
	 * Đặt lại option ở chế độ không tự nạp.
	 */
	private static function no_autoload( $option ) {
		$value = get_option( $option, null );
		if ( null === $value ) {
			return;
		}
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( $option, false );
			return;
		}
		delete_option( $option );
		add_option( $option, $value, '', 'no' );
	}

	private static function to_700() {
		// 1. API key: abcgs_settings → kho bí mật mã hóa.
		$settings = get_option( ABCGS_Settings::OPTION, array() );
		if ( is_array( $settings ) && isset( $settings['api_key'] ) ) {
			if ( '' !== trim( (string) $settings['api_key'] ) && '' === ABCGS_Secrets::get( 'api_key' ) ) {
				ABCGS_Secrets::set( 'api_key', $settings['api_key'] );
			}
			unset( $settings['api_key'], $settings['inline_images'], $settings['featured_images'] );
			update_option( ABCGS_Settings::OPTION, $settings, false );
		}

		// 2. License v6 (serial + email, không có edition) → Pro.
		$license = get_option( ABCGS_License::OPTION, array() );
		if ( is_array( $license ) && array_key_exists( 'serial', $license ) ) {
			if ( '' !== trim( (string) $license['serial'] ) ) {
				ABCGS_Secrets::set( 'serial', $license['serial'] );
				$license['edition'] = 'pro';
			}
			unset( $license['serial'] );
			$license['status'] = 'unknown';
			update_option( ABCGS_License::OPTION, $license, false );
		}

		// 3. Queue bài v6: gán id, batch, tác giả; công đoạn ảnh cũ coi như xong.
		$queue = get_option( ABCGS_Queue::OPTION, array() );
		if ( is_array( $queue ) && $queue ) {
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			);
			$author = $admins ? intval( $admins[0] ) : 0;
			foreach ( $queue as $i => $item ) {
				$queue[ $i ] = wp_parse_args(
					(array) $item,
					array(
						'id'             => 'q' . wp_generate_password( 12, false, false ),
						'batch_id'       => 'legacy',
						'keyword'        => '',
						'language'       => 'vi',
						'author_id'      => $author,
						'post_id'        => 0,
						'status'         => 'pending',
						'stage'          => 'content',
						'content_status' => '',
						'next_attempt'   => 0,
						'draft'          => null,
						'created_at'     => time(),
					)
				);
				if ( in_array( $queue[ $i ]['stage'], array( 'inline', 'featured' ), true ) || ! empty( $queue[ $i ]['post_id'] ) ) {
					$queue[ $i ]['stage']  = 'done';
					$queue[ $i ]['status'] = $queue[ $i ]['post_id'] ? 'done' : $queue[ $i ]['status'];
				}
			}
			update_option( ABCGS_Queue::OPTION, array_values( $queue ), false );

			$active = false;
			foreach ( $queue as $item ) {
				if ( in_array( $item['status'], ABCGS_Queue::ACTIVE, true ) ) {
					$active = true;
					break;
				}
			}
			$batches = ABCGS_Queue::batches();
			if ( ! isset( $batches['legacy'] ) ) {
				// Đợt cũ đã xong thì v6 đã gắn silo, không gắn lại; đang chạy dở thì gắn khi xong.
				$batches['legacy'] = array(
					'settings'   => array(),
					'author_id'  => $author,
					'created_at' => time(),
					'finalized'  => ! $active,
					'message'    => 'Đợt từ phiên bản cũ.',
				);
				update_option( ABCGS_Queue::BATCHES, $batches, false );
			}
		}

		// 4. Queue ảnh v6: gán id; tác vụ kiểu "inline" cũ không còn hỗ trợ.
		$tasks = get_option( ABCGS_Image_Queue::OPTION, array() );
		if ( is_array( $tasks ) && $tasks ) {
			foreach ( $tasks as $i => $t ) {
				if ( empty( $t['id'] ) ) {
					$tasks[ $i ]['id'] = 't' . wp_generate_password( 12, false, false );
				}
				if ( 'inline' === ( $t['type'] ?? '' ) && 'pending' === ( $t['status'] ?? '' ) ) {
					$tasks[ $i ]['status']  = 'error';
					$tasks[ $i ]['message'] = 'Tác vụ kiểu cũ không còn hỗ trợ. Hãy quét lại và xếp ảnh mới.';
				}
			}
			update_option( ABCGS_Image_Queue::OPTION, array_values( $tasks ), false );
		}

		// 5. Kết quả quét cũ có lưu tiêu đề: bỏ để giảm dung lượng.
		$scan = get_option( ABCGS_Image_Queue::SCAN, null );
		if ( is_array( $scan ) ) {
			foreach ( $scan as $i => $r ) {
				unset( $scan[ $i ]['title'] );
			}
			update_option( ABCGS_Image_Queue::SCAN, $scan, false );
		}

		foreach ( array(
			ABCGS_Settings::OPTION,
			ABCGS_License::OPTION,
			ABCGS_Queue::OPTION,
			ABCGS_Image_Queue::OPTION,
			ABCGS_Image_Queue::SCAN,
			'abcgs_silo_log',
			'abcgs_recent_written_ids',
			'abcgs_image_decision_pending',
			'abcgs_heading_image_limit',
		) as $option ) {
			self::no_autoload( $option );
		}

		// Đợt cũ không có lịch silo/ảnh mới.
		ABCGS_Queue::schedule_next();
		ABCGS_Image_Queue::schedule();
	}
}
