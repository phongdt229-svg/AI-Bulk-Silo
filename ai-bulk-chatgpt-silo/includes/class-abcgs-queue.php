<?php
/**
 * Queue viết bài.
 *
 * Công đoạn mỗi mục: content (hỏi quota + viết) → consume (trừ quota trên máy
 * chủ) → insert (đăng bài) → done. Bài chỉ được đăng sau khi máy chủ license
 * xác nhận trừ quota (fail-closed). Nội dung đã viết được giữ trong mục queue
 * để thử lại mà không tốn phí OpenAI lần hai.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Queue {

	const OPTION  = 'abcgs_queue';
	const BATCHES = 'abcgs_batches';
	const HOOK    = 'abcgs_process_queue_event';

	const MAX_KEYWORDS_PER_BATCH = 200;

	const ACTIVE = array( 'pending', 'waiting_quota' );

	public static function items() {
		$q = get_option( self::OPTION, array() );
		return is_array( $q ) ? array_values( $q ) : array();
	}

	public static function batches() {
		$b = get_option( self::BATCHES, array() );
		return is_array( $b ) ? $b : array();
	}

	public static function status_labels() {
		return array(
			'pending'       => 'Đang chờ',
			'waiting_quota' => 'Chờ hạn mức',
			'error'         => 'Lỗi',
			'done'          => 'Xong',
			'cancelled'     => 'Đã hủy',
		);
	}

	public static function counts() {
		$c = array_fill_keys( array_keys( self::status_labels() ), 0 );
		foreach ( self::items() as $item ) {
			$st = $item['status'] ?? 'pending';
			if ( isset( $c[ $st ] ) ) {
				$c[ $st ]++;
			}
		}
		return $c;
	}

	/**
	 * Thêm một đợt từ khóa vào queue (không ghi đè đợt đang chạy).
	 */
	public static function enqueue( array $keywords, array $settings, $author_id ) {
		$batch_id = 'b' . time() . wp_generate_password( 4, false, false );
		$now      = time();

		$batches              = self::batches();
		$batches[ $batch_id ] = array(
			'settings'   => $settings,
			'author_id'  => intval( $author_id ),
			'created_at' => $now,
			'finalized'  => false,
			'message'    => '',
		);
		update_option( self::BATCHES, $batches, false );

		$new = array();
		foreach ( $keywords as $kw ) {
			$new[] = array(
				'id'             => 'q' . wp_generate_password( 12, false, false ),
				'batch_id'       => $batch_id,
				'keyword'        => $kw,
				'language'       => $settings['language'],
				'author_id'      => intval( $author_id ),
				'post_id'        => 0,
				'status'         => 'pending',
				'stage'          => 'content',
				'content_status' => '',
				'next_attempt'   => 0,
				'draft'          => null,
				'created_at'     => $now,
			);
		}

		$ok = ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) use ( $new ) {
				$q = array_merge( array_values( $q ), $new );
			}
		);
		if ( $ok ) {
			self::schedule_next();
		}
		return $ok ? count( $new ) : 0;
	}

	/**
	 * Cập nhật một mục theo id. Không ghi nếu người dùng đã hủy mục đó.
	 *
	 * @return bool
	 */
	private static function update_item( $id, array $changes ) {
		return (bool) ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) use ( $id, $changes ) {
				foreach ( $q as $i => $item ) {
					if ( ( $item['id'] ?? '' ) !== $id ) {
						continue;
					}
					if ( 'cancelled' === ( $item['status'] ?? '' ) ) {
						return 'cancelled';
					}
					$q[ $i ] = array_merge( $item, $changes );
					return 'ok';
				}
				return 'missing';
			}
		) === 'ok';
	}

	/**
	 * Đưa mọi mục đang chờ sang trạng thái chờ hạn mức tới $until.
	 */
	private static function wait_all( $until, $message ) {
		ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) use ( $until, $message ) {
				foreach ( $q as $i => $item ) {
					if ( in_array( $item['status'] ?? '', self::ACTIVE, true ) ) {
						$q[ $i ]['status']         = 'waiting_quota';
						$q[ $i ]['next_attempt']   = $until;
						$q[ $i ]['content_status'] = $message;
					}
				}
			}
		);
	}

	/**
	 * Callback cron: xử lý 1 mục.
	 */
	public static function process() {
		if ( ! ABCGS_Lock::acquire( 'queue', 15 * MINUTE_IN_SECONDS ) ) {
			return;
		}
		try {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 );
			}
			self::process_one();
			self::finalize_batches();
		} finally {
			ABCGS_Lock::release( 'queue' );
			self::schedule_next();
		}
	}

	private static function next_item() {
		$now = time();
		foreach ( ABCGS_Lock::fresh_option( self::OPTION, array() ) as $item ) {
			if ( in_array( $item['status'] ?? '', self::ACTIVE, true ) && intval( $item['next_attempt'] ?? 0 ) <= $now ) {
				return $item;
			}
		}
		return null;
	}

	private static function process_one() {
		$item = self::next_item();
		if ( ! $item ) {
			return;
		}
		$batch = self::batches()[ $item['batch_id'] ?? '' ] ?? array();
		$s     = wp_parse_args( (array) ( $batch['settings'] ?? array() ), ABCGS_Settings::get() );

		$stage = $item['stage'] ?? 'content';
		if ( 'content' === $stage ) {
			$item = self::stage_content( $item, $s );
			if ( ! $item ) {
				return;
			}
			$stage = 'consume';
		}
		if ( 'consume' === $stage ) {
			$item = self::stage_consume( $item );
			if ( ! $item ) {
				return;
			}
			$stage = 'insert';
		}
		if ( 'insert' === $stage ) {
			self::stage_insert( $item, $s, $batch );
		}
	}

	private static function fail( $item, $message ) {
		self::update_item(
			$item['id'],
			array(
				'status'         => 'error',
				'content_status' => $message,
				'finished_at'    => time(),
			)
		);
		return null;
	}

	/**
	 * Hỏi quota rồi viết bài. Trả về mục đã có bản nháp, hoặc null nếu dừng.
	 */
	private static function stage_content( array $item, array $s ) {
		$keyword = trim( (string) $item['keyword'] );
		if ( '' === $keyword ) {
			return self::fail( $item, 'Bỏ qua: từ khóa rỗng.' );
		}
		$api_key = ABCGS_Secrets::get( 'api_key' );
		if ( strlen( $api_key ) < 20 ) {
			return self::fail( $item, 'Lỗi: chưa cấu hình OpenAI API key (trang Cài đặt chung).' );
		}

		$license = ABCGS_License::check();
		if ( ! $license['can_write'] ) {
			if ( $license['transient'] ) {
				self::update_item(
					$item['id'],
					array(
						'next_attempt'   => $license['retry_at'],
						'content_status' => $license['message'] . ' Thử lại lúc ' . wp_date( 'H:i d/m', $license['retry_at'] ) . '.',
					)
				);
			} elseif ( $license['retry_at'] ) {
				self::wait_all( $license['retry_at'], $license['message'] . ' Tự chạy tiếp lúc ' . wp_date( 'H:i d/m', $license['retry_at'] ) . '.' );
			} else {
				self::fail( $item, 'Dừng: ' . $license['message'] );
			}
			return null;
		}

		$language = array_key_exists( $item['language'] ?? '', ABCGS_Settings::languages() ) ? $item['language'] : 'vi';
		$model    = (string) $s['model'];
		$prompt   = ABCGS_Prompts::article( $keyword, $s, $language );

		if ( ! self::update_item(
			$item['id'],
			array(
				'status'         => 'pending',
				'content_status' => 'Đang gọi API (' . $model . ', ' . strtoupper( $language ) . ')...',
			)
		) ) {
			return null;
		}

		$started = microtime( true );
		$html    = ABCGS_OpenAI::text( $api_key, $model, $prompt );
		if ( is_wp_error( $html ) ) {
			return self::fail( $item, 'Lỗi API nội dung (' . round( microtime( true ) - $started, 1 ) . ' giây): ' . $html->get_error_message() );
		}

		if ( ! ABCGS_Content::matches_language( $html, $language, $keyword ) ) {
			$retry = ABCGS_OpenAI::text( $api_key, $model, $prompt . ABCGS_Prompts::language_correction( $language ) );
			if ( is_wp_error( $retry ) || ! ABCGS_Content::matches_language( $retry, $language, $keyword ) ) {
				return self::fail( $item, 'Lỗi: AI trả bài sai ngôn ngữ (' . $language . ') sau khi thử lại.' );
			}
			$html = $retry;
		}

		$html                 = ABCGS_Content::strip_code_fences( $html );
		list( $title, $html ) = ABCGS_Content::split_title( $html, $keyword );
		$html                 = ABCGS_Content::sanitize( ABCGS_Content::demote_h1( $html ) );
		$focus                = ABCGS_SEO::focus( $keyword, $s );

		$item['draft'] = array(
			'title'   => ABCGS_SEO::optimized_title( $title, $focus ),
			'content' => $html,
			'excerpt' => ABCGS_Content::excerpt( $html, 40 ),
			'slug'    => ABCGS_Content::slug( $focus ),
			'model'   => $model,
			'seconds' => round( microtime( true ) - $started, 1 ),
		);
		$item['stage']          = 'consume';
		$item['content_status'] = 'Đã viết xong, đang trừ hạn mức license...';

		if ( ! self::update_item(
			$item['id'],
			array(
				'draft'          => $item['draft'],
				'stage'          => 'consume',
				'content_status' => $item['content_status'],
			)
		) ) {
			return null;
		}
		return $item;
	}

	/**
	 * Trừ quota trên máy chủ trước khi đăng.
	 */
	private static function stage_consume( array $item ) {
		if ( empty( $item['draft']['content'] ) ) {
			self::update_item( $item['id'], array( 'stage' => 'content' ) );
			return null;
		}
		$res = ABCGS_License::consume( $item['id'] );
		if ( ! $res['can_write'] ) {
			$saved = 'Bài đã viết xong và được giữ lại, chưa đăng vì chưa trừ được hạn mức: ' . $res['message'];
			if ( $res['transient'] ) {
				self::update_item(
					$item['id'],
					array(
						'next_attempt'   => $res['retry_at'],
						'content_status' => $saved . ' Thử lại lúc ' . wp_date( 'H:i d/m', $res['retry_at'] ) . '.',
					)
				);
			} elseif ( $res['retry_at'] ) {
				self::wait_all( $res['retry_at'], 'Chờ hạn mức ngày mới, tự chạy tiếp lúc ' . wp_date( 'H:i d/m', $res['retry_at'] ) . '.' );
			} else {
				self::fail( $item, $saved );
			}
			return null;
		}

		$item['stage'] = 'insert';
		if ( ! self::update_item(
			$item['id'],
			array(
				'stage'          => 'insert',
				'consumed_at'    => time(),
				'content_status' => 'Đã trừ hạn mức, đang đăng bài...',
			)
		) ) {
			return null;
		}
		return $item;
	}

	private static function author_for( array $item, array $batch ) {
		foreach ( array( $item['author_id'] ?? 0, $batch['author_id'] ?? 0 ) as $uid ) {
			$uid = intval( $uid );
			if ( $uid && user_can( $uid, 'edit_posts' ) ) {
				return $uid;
			}
		}
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		return $admins ? intval( $admins[0] ) : 0;
	}

	private static function stage_insert( array $item, array $s, array $batch ) {
		$draft = (array) ( $item['draft'] ?? array() );
		if ( empty( $draft['content'] ) ) {
			return self::fail( $item, 'Lỗi: mất nội dung bài đã viết (đã trừ hạn mức). Liên hệ hỗ trợ để hoàn quota.' );
		}

		// Không đăng trùng nếu lần trước đã đăng nhưng chưa kịp ghi trạng thái.
		$existing = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'meta_key'       => '_abcgs_queue_item_id',
				'meta_value'     => $item['id'],
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( $existing ) {
			$post_id = intval( $existing[0] );
		} else {
			$category = absint( $s['category_id'] );
			$post_id  = wp_insert_post(
				array(
					'post_title'    => $draft['title'],
					'post_name'     => $draft['slug'],
					'post_content'  => $draft['content'],
					'post_excerpt'  => $draft['excerpt'],
					'post_status'   => 'draft' === $s['post_status'] ? 'draft' : 'publish',
					'post_author'   => self::author_for( $item, $batch ),
					'post_category' => $category ? array( $category ) : array(),
					'meta_input'    => array(
						'_abcgs_queue_item_id'    => $item['id'],
						'_abcgs_batch_id'         => $item['batch_id'],
						'_abcgs_main_keyword'     => $item['keyword'],
						'_abcgs_article_language' => $item['language'],
					),
				),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$msg = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'không rõ nguyên nhân';
				return self::fail( $item, 'Lỗi đăng bài (đã trừ hạn mức, bấm Thử lại để đăng lại): ' . $msg );
			}
		}

		ABCGS_SEO::apply( $post_id, $item['keyword'], $s );

		self::update_item(
			$item['id'],
			array(
				'post_id'        => intval( $post_id ),
				'stage'          => 'done',
				'status'         => 'done',
				'draft'          => null,
				'finished_at'    => time(),
				'content_status' => 'OK: đã tạo bài (' . ( $draft['seconds'] ?? '?' ) . ' giây; model: ' . ( $draft['model'] ?? '' ) . ').',
			)
		);
	}

	/**
	 * Đợt nào đã xong hết thì gắn silo và mở bước hỏi tạo ảnh.
	 */
	private static function finalize_batches() {
		$items   = self::items();
		$batches = self::batches();
		$changed = false;

		foreach ( $batches as $batch_id => $batch ) {
			if ( ! empty( $batch['finalized'] ) ) {
				continue;
			}
			$mine = array_values(
				array_filter(
					$items,
					function ( $it ) use ( $batch_id ) {
						return ( $it['batch_id'] ?? '' ) === $batch_id;
					}
				)
			);
			if ( ! $mine ) {
				continue;
			}
			foreach ( $mine as $it ) {
				if ( in_array( $it['status'] ?? '', self::ACTIVE, true ) ) {
					continue 2;
				}
			}

			$ids     = array();
			$anchors = array();
			foreach ( $mine as $it ) {
				if ( 'done' === ( $it['status'] ?? '' ) && ! empty( $it['post_id'] ) ) {
					$ids[]     = intval( $it['post_id'] );
					$anchors[] = (string) $it['keyword'];
				}
			}

			if ( 'done' !== ( $mine[0]['status'] ?? '' ) ) {
				$message = 'Bài trụ cột "' . $mine[0]['keyword'] . '" chưa tạo được nên bỏ qua liên kết silo. Thử lại bài trụ cột để gắn silo.';
			} elseif ( count( $ids ) < 2 ) {
				$message = 'Không đủ bài để tạo silo (tối thiểu 2 bài).';
			} else {
				$res     = ABCGS_Silo::apply( $ids, $anchors );
				$message = is_wp_error( $res ) ? 'Lỗi khi gắn silo: ' . $res->get_error_message() : 'OK: đã gắn link silo cho ' . count( $ids ) . ' bài.';
			}
			update_option( 'abcgs_silo_log', $message, false );

			if ( $ids ) {
				update_option( 'abcgs_recent_written_ids', $ids, false );
				update_option( 'abcgs_image_decision_pending', 1, false );
			}

			$batches[ $batch_id ]['finalized'] = true;
			$batches[ $batch_id ]['message']   = $message;
			$changed                           = true;
		}

		if ( $changed ) {
			update_option( self::BATCHES, $batches, false );
		}
	}

	/**
	 * Hẹn cron cho mục sớm nhất cần chạy.
	 */
	public static function schedule_next() {
		$next = null;
		$now  = time();
		foreach ( self::items() as $item ) {
			if ( ! in_array( $item['status'] ?? '', self::ACTIVE, true ) ) {
				continue;
			}
			$at   = max( $now + 5, intval( $item['next_attempt'] ?? 0 ) );
			$next = null === $next ? $at : min( $next, $at );
		}
		if ( null === $next ) {
			return;
		}
		$scheduled = wp_next_scheduled( self::HOOK );
		if ( ! $scheduled || $scheduled > $next ) {
			wp_clear_scheduled_hook( self::HOOK );
			wp_schedule_single_event( $next, self::HOOK );
		}
	}

	/* ---------- Thao tác quản trị ---------- */

	public static function retry( $id ) {
		$batch = '';
		$ok    = ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) use ( $id, &$batch ) {
				foreach ( $q as $i => $item ) {
					if ( ( $item['id'] ?? '' ) === $id && in_array( $item['status'] ?? '', array( 'error', 'cancelled', 'waiting_quota' ), true ) ) {
						$q[ $i ]['status']         = 'pending';
						$q[ $i ]['next_attempt']   = 0;
						$q[ $i ]['content_status'] = 'Chờ thử lại.';
						$batch                     = $item['batch_id'] ?? '';
						return 'ok';
					}
				}
				return 'missing';
			}
		);
		if ( 'ok' !== $ok ) {
			return false;
		}
		self::reopen_batch( $batch );
		self::schedule_next();
		return true;
	}

	private static function reopen_batch( $batch_id ) {
		$batches = self::batches();
		if ( isset( $batches[ $batch_id ] ) ) {
			$batches[ $batch_id ]['finalized'] = false;
			update_option( self::BATCHES, $batches, false );
		}
	}

	public static function cancel( $id ) {
		return 'ok' === ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) use ( $id ) {
				foreach ( $q as $i => $item ) {
					if ( ( $item['id'] ?? '' ) === $id && in_array( $item['status'] ?? '', array( 'pending', 'waiting_quota', 'error' ), true ) ) {
						$q[ $i ]['status']         = 'cancelled';
						$q[ $i ]['content_status'] = 'Đã hủy bởi người dùng.';
						$q[ $i ]['finished_at']    = time();
						return 'ok';
					}
				}
				return 'missing';
			}
		);
	}

	public static function cancel_all() {
		return ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) {
				$n = 0;
				foreach ( $q as $i => $item ) {
					if ( in_array( $item['status'] ?? '', self::ACTIVE, true ) ) {
						$q[ $i ]['status']         = 'cancelled';
						$q[ $i ]['content_status'] = 'Đã hủy bởi người dùng.';
						$n++;
					}
				}
				return $n;
			}
		);
	}

	/**
	 * Xóa các mục đã xong/đã hủy và các đợt không còn mục nào.
	 */
	public static function clear_finished() {
		$removed = ABCGS_Lock::mutate(
			self::OPTION,
			function ( &$q ) {
				$before = count( $q );
				$q      = array_values(
					array_filter(
						$q,
						function ( $it ) {
							return ! in_array( $it['status'] ?? '', array( 'done', 'cancelled' ), true );
						}
					)
				);
				return $before - count( $q );
			}
		);
		$used    = array();
		foreach ( self::items() as $it ) {
			$used[ $it['batch_id'] ?? '' ] = true;
		}
		$batches = array_intersect_key( self::batches(), $used );
		update_option( self::BATCHES, $batches, false );
		return intval( $removed );
	}

	/**
	 * Hẹn lại cron nếu có việc mà cron bị mất (site ít truy cập, cron lỗi).
	 */
	public static function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule_next();
		}
	}
}
