<?php
/**
 * Queue tạo ảnh và quét bài thiếu ảnh. Tạo ảnh không trừ hạn mức license
 * (giống v6.1.1); chi phí ảnh tính vào OpenAI API key của người dùng.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Image_Queue {

	const OPTION    = 'abcgs_image_bulk_queue';
	const SCAN      = 'abcgs_image_scan_results';
	const HOOK      = 'abcgs_process_image_bulk_queue';
	const SCAN_MAX  = 1000;

	public static function tasks() {
		$q = get_option( self::OPTION, array() );
		return is_array( $q ) ? array_values( $q ) : array();
	}

	public static function summary() {
		$sum = array(
			'pending'    => 0,
			'done'       => 0,
			'errors'     => 0,
			'last_error' => '',
		);
		foreach ( self::tasks() as $t ) {
			$st = $t['status'] ?? '';
			if ( 'pending' === $st ) {
				$sum['pending']++;
			} elseif ( 'done' === $st ) {
				$sum['done']++;
			} elseif ( 'error' === $st ) {
				$sum['errors']++;
				$sum['last_error'] = (string) ( $t['message'] ?? '' );
			}
		}
		return $sum;
	}

	private static function new_task( $post_id, $type, array $extra = array() ) {
		return array_merge(
			array(
				'id'         => 't' . wp_generate_password( 12, false, false ),
				'post_id'    => intval( $post_id ),
				'type'       => $type,
				'status'     => 'pending',
				'message'    => 'Đang chờ',
				'created_at' => time(),
			),
			$extra
		);
	}

	/**
	 * Xếp ảnh cho N thẻ H2/H3 đầu tiên của mỗi bài. Vị trí đã có ảnh (marker)
	 * hoặc đang chờ trong queue được tính vào N.
	 *
	 * @return array [ số tác vụ thêm, cảnh báo[] ]
	 */
	public static function enqueue_headings( array $post_ids, $limit, $request = '' ) {
		$warnings = array();
		$added    = ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) use ( $post_ids, $limit, $request, &$warnings ) {
				$added = 0;
				foreach ( $post_ids as $pid ) {
					$content = (string) get_post_field( 'post_content', $pid );
					$heads   = ABCGS_Images::headings( $content );
					$total   = count( $heads );
					if ( $total < $limit ) {
						$warnings[] = 'Bài #' . $pid . ' chỉ có ' . $total . ' thẻ H2/H3, không đủ ' . $limit . ' vị trí ảnh.';
					}
					$chosen = 0;
					for ( $n = 0; $n < $total && $chosen < $limit; $n++ ) {
						if ( false !== strpos( $content, ABCGS_Images::heading_marker( $n ) ) ) {
							$chosen++;
							continue;
						}
						$queued = false;
						foreach ( $q as $t ) {
							if ( intval( $t['post_id'] ?? 0 ) === $pid && 'heading_inline' === ( $t['type'] ?? '' )
								&& intval( $t['heading_index'] ?? -1 ) === $n && 'pending' === ( $t['status'] ?? '' ) ) {
								$queued = true;
								break;
							}
						}
						$chosen++;
						if ( $queued ) {
							continue;
						}
						$q[] = self::new_task(
							$pid,
							'heading_inline',
							array(
								'heading_index'  => $n,
								'custom_request' => $request,
							)
						);
						$added++;
					}
				}
				return $added;
			}
		);
		self::schedule();
		return array( intval( $added ), $warnings );
	}

	public static function enqueue_featured( array $post_ids ) {
		$added = ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) use ( $post_ids ) {
				$added = 0;
				foreach ( $post_ids as $pid ) {
					if ( has_post_thumbnail( $pid ) ) {
						continue;
					}
					foreach ( $q as $t ) {
						if ( intval( $t['post_id'] ?? 0 ) === $pid && 'featured' === ( $t['type'] ?? '' ) && 'pending' === ( $t['status'] ?? '' ) ) {
							continue 2;
						}
					}
					$q[] = self::new_task( $pid, 'featured' );
					$added++;
				}
				return $added;
			}
		);
		self::schedule();
		return intval( $added );
	}

	/**
	 * Xử lý 1 tác vụ. Trả về false nếu worker khác đang chạy.
	 */
	public static function process() {
		if ( ! ABCGS_Lock::acquire( 'image_queue', 10 * MINUTE_IN_SECONDS ) ) {
			return false;
		}
		try {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 );
			}
			$task = null;
			foreach ( ABCGS_Lock::fresh_option( self::OPTION, array() ) as $t ) {
				if ( 'pending' === ( $t['status'] ?? '' ) ) {
					$task = $t;
					break;
				}
			}
			if ( $task ) {
				$result = self::run_task( $task );
				$id     = $task['id'] ?? '';
				ABCGS_Lock::mutate(
					self::OPTION,
					function ( &$q ) use ( $id, $task, $result ) {
						foreach ( $q as $i => $t ) {
							if ( ( $t['id'] ?? '' ) === $id || ( '' === $id && $t === $task ) ) {
								$q[ $i ]['status']      = is_wp_error( $result ) ? 'error' : 'done';
								$q[ $i ]['message']     = is_wp_error( $result ) ? 'Lỗi: ' . $result->get_error_message() : 'Hoàn tất';
								$q[ $i ]['finished_at'] = time();
								break;
							}
						}
					}
				);
				self::refresh_scan( intval( $task['post_id'] ?? 0 ) );
			}
		} finally {
			ABCGS_Lock::release( 'image_queue' );
			self::schedule();
		}
		return true;
	}

	private static function run_task( array $task ) {
		$pid = intval( $task['post_id'] ?? 0 );
		if ( ! $pid || ! get_post( $pid ) ) {
			return new WP_Error( 'abcgs_post_missing', 'Không tìm thấy bài viết.' );
		}
		$s       = ABCGS_Settings::get();
		$keyword = (string) get_post_meta( $pid, '_abcgs_main_keyword', true );
		if ( '' === $keyword ) {
			$keyword = wp_strip_all_tags( get_the_title( $pid ) );
		}
		switch ( $task['type'] ?? '' ) {
			case 'heading_inline':
				return ABCGS_Images::heading_image( $pid, $keyword, $s, intval( $task['heading_index'] ?? 0 ), (string) ( $task['custom_request'] ?? '' ) );
			case 'featured':
				return ABCGS_Images::featured_image( $pid, $keyword, $s );
		}
		return new WP_Error( 'abcgs_bad_task', 'Loại tác vụ không còn được hỗ trợ. Hãy quét lại và xếp ảnh mới.' );
	}

	public static function schedule() {
		$pending = false;
		foreach ( self::tasks() as $t ) {
			if ( 'pending' === ( $t['status'] ?? '' ) ) {
				$pending = true;
				break;
			}
		}
		if ( $pending && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::HOOK );
		}
	}

	public static function clear_finished() {
		return intval(
			ABCGS_Lock::mutate(
				self::OPTION,
				function ( &$q ) {
					$before = count( $q );
					$q      = array_values(
						array_filter(
							$q,
							function ( $t ) {
								return 'pending' === ( $t['status'] ?? '' );
							}
						)
					);
					return $before - count( $q );
				}
			)
		);
	}

	/* ---------- Quét ảnh ---------- */

	private static function inspect( $pid ) {
		$content = (string) get_post_field( 'post_content', $pid );
		return array(
			'post_id'          => intval( $pid ),
			'missing_inline'   => ! preg_match( '/<img\b|<!--\s*wp:image\b/i', $content ),
			'missing_featured' => ! has_post_thumbnail( $pid ),
		);
	}

	/**
	 * Quét tối đa SCAN_MAX bài mới nhất, chỉ lưu ID và cờ.
	 */
	public static function scan() {
		$ids     = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page' => self::SCAN_MAX,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$results = array();
		foreach ( $ids as $pid ) {
			$results[] = self::inspect( $pid );
		}
		update_option( self::SCAN, $results, false );
		return count( $results );
	}

	public static function scan_results() {
		$r = get_option( self::SCAN, array() );
		return is_array( $r ) ? $r : array();
	}

	private static function refresh_scan( $pid ) {
		if ( ! $pid ) {
			return;
		}
		$results = self::scan_results();
		foreach ( $results as $i => $r ) {
			if ( intval( $r['post_id'] ?? 0 ) === $pid ) {
				$results[ $i ] = get_post( $pid ) ? self::inspect( $pid ) : $r;
				update_option( self::SCAN, $results, false );
				return;
			}
		}
	}
}
