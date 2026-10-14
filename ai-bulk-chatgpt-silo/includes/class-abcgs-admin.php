<?php
/**
 * Trang quản trị và xử lý form.
 *
 * Mọi thao tác ghi đi qua admin-post.php (hoặc admin-ajax.php) và kiểm tra
 * quyền manage_options + nonce. Thông báo lưu theo người dùng, không qua URL.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Admin {

	const CAP = 'manage_options';

	const PAGE_MAIN     = 'abcgs-ai-bulk-silo';
	const PAGE_GENERAL  = 'abcgs-settings';
	const PAGE_LICENSE  = 'abcgs-license';
	const PAGE_IMAGES   = 'abcgs-image-settings';
	const PAGE_SCAN     = 'abcgs-image-scan';
	const PAGE_AFTER    = 'abcgs-after-writing-images';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		$posts = array(
			'abcgs_enqueue'       => 'handle_enqueue',
			'abcgs_queue_action'  => 'handle_queue_action',
			'abcgs_save_general'  => 'handle_save_general',
			'abcgs_analyze_site'  => 'handle_analyze_site',
			'abcgs_save_license'  => 'handle_save_license',
			'abcgs_save_images'   => 'handle_save_images',
			'abcgs_after_images'  => 'handle_after_images',
			'abcgs_image_tools'   => 'handle_image_tools',
		);
		foreach ( $posts as $action => $method ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}
		add_action( 'wp_ajax_abcgs_process_next_image', array( __CLASS__, 'ajax_process_next_image' ) );
		add_action( 'wp_ajax_abcgs_process_next_post', array( __CLASS__, 'ajax_process_next_post' ) );
	}

	public static function menu() {
		add_menu_page( 'AI Bulk ChatGPT Silo', 'AI Bulk Silo', self::CAP, self::PAGE_MAIN, array( __CLASS__, 'page_main' ), 'dashicons-edit', 27 );
		add_submenu_page( self::PAGE_MAIN, 'Viết bài hàng loạt', 'Viết bài', self::CAP, self::PAGE_MAIN, array( __CLASS__, 'page_main' ) );
		add_submenu_page( self::PAGE_MAIN, 'Cài đặt chung', 'Cài đặt chung', self::CAP, self::PAGE_GENERAL, array( __CLASS__, 'page_general' ) );
		add_submenu_page( self::PAGE_MAIN, 'License', 'License', self::CAP, self::PAGE_LICENSE, array( __CLASS__, 'page_license' ) );
		add_submenu_page( self::PAGE_MAIN, 'Cài đặt tạo hình ảnh', 'Cài đặt tạo hình ảnh', self::CAP, self::PAGE_IMAGES, array( __CLASS__, 'page_images' ) );
		add_submenu_page( self::PAGE_MAIN, 'Quét ảnh', 'Quét ảnh', self::CAP, self::PAGE_SCAN, array( __CLASS__, 'page_scan' ) );
		// Trang ẩn: parent rỗng (null gây cảnh báo deprecated trên PHP 8.1+).
		add_submenu_page( '', 'Tạo ảnh sau khi viết', 'Tạo ảnh sau khi viết', self::CAP, self::PAGE_AFTER, array( __CLASS__, 'page_after' ) );
	}

	private static function is_our_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 0 === strpos( $page, 'abcgs-' );
	}

	public static function url( $page ) {
		return admin_url( 'admin.php?page=' . $page );
	}

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Không có quyền.', 'ai-bulk-chatgpt-silo' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function back( $page ) {
		wp_safe_redirect( self::url( $page ) );
		exit;
	}

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
	}

	private static function view( $name, array $vars = array() ) {
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include ABCGS_DIR . 'admin/views/' . $name . '.php';
	}

	/* ---------- Thông báo ---------- */

	public static function notices() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( self::is_our_screen() ) {
			ABCGS_Notices::render();
			ABCGS_Queue::ensure_scheduled();
			ABCGS_Image_Queue::schedule();
		}
		if ( get_option( 'abcgs_image_decision_pending', false ) ) {
			$ids = (array) get_option( 'abcgs_recent_written_ids', array() );
			self::view( 'notice-images', array( 'count' => count( $ids ) ) );
		}
	}

	/* ---------- Trang ---------- */

	public static function page_main() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		self::view(
			'main',
			array(
				's'        => ABCGS_Settings::get(),
				'license'  => ABCGS_License::state(),
				'items'    => ABCGS_Queue::items(),
				'counts'   => ABCGS_Queue::counts(),
				'silo_log' => (string) get_option( 'abcgs_silo_log', '' ),
				'has_key'  => '' !== ABCGS_Secrets::get( 'api_key' ),
				'running'  => ABCGS_Lock::is_locked( 'queue' ),
			)
		);
	}

	public static function page_general() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		self::view(
			'general',
			array(
				's'           => ABCGS_Settings::get(),
				'key_masked'  => ABCGS_Secrets::mask( ABCGS_Secrets::get( 'api_key' ) ),
				'key_const'   => ABCGS_Secrets::from_constant( 'api_key' ),
				'key_broken'  => ABCGS_Secrets::is_unreadable( 'api_key' ),
			)
		);
	}

	public static function page_license() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		self::view(
			'license',
			array(
				'state'         => ABCGS_License::state(),
				'serial_masked' => ABCGS_Secrets::mask( ABCGS_Secrets::get( 'serial' ) ),
				'serial_const'  => ABCGS_Secrets::from_constant( 'serial' ),
				'domain'        => ABCGS_License::domain(),
			)
		);
	}

	public static function page_images() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		self::view( 'images', array( 's' => ABCGS_Settings::get() ) );
	}

	public static function page_after() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tasks = ABCGS_Image_Queue::tasks();
		self::view(
			'after-writing',
			array(
				'ids'     => array_map( 'intval', (array) get_option( 'abcgs_recent_written_ids', array() ) ),
				'limit'   => ABCGS_Settings::heading_image_limit(),
				'tasks'   => array_slice( $tasks, -100 ),
				'summary' => ABCGS_Image_Queue::summary(),
			)
		);
	}

	public static function page_scan() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		self::view(
			'scan',
			array(
				'results' => ABCGS_Image_Queue::scan_results(),
				'summary' => ABCGS_Image_Queue::summary(),
				'max'     => ABCGS_Image_Queue::SCAN_MAX,
			)
		);
	}

	/* ---------- Xử lý form ---------- */

	public static function handle_enqueue() {
		self::guard( 'abcgs_enqueue' );

		$writing = ABCGS_Settings::sanitize_writing(
			array(
				'category_id'      => self::post( 'abcgs_category_id' ),
				'post_status'      => self::post( 'abcgs_post_status' ),
				'post_length'      => self::post( 'abcgs_post_length' ),
				'keyword_density'  => self::post( 'abcgs_keyword_density' ),
				'language'         => self::post( 'abcgs_language' ),
				'tone'             => self::post( 'abcgs_tone' ),
				'article_style'    => self::post( 'abcgs_article_style' ),
				'seo_plugin'       => self::post( 'abcgs_seo_plugin' ),
				'seo_focus_mode'   => self::post( 'abcgs_seo_focus_mode' ),
				'seo_custom_focus' => self::post( 'abcgs_seo_custom_focus' ),
			)
		);
		// Lưu làm giá trị mặc định cho lần sau, kể cả khi bị từ chối bên dưới.
		$settings = ABCGS_Settings::update( $writing );

		$keywords = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) self::post( 'abcgs_keywords' ) ) as $line ) {
			$line = mb_substr( trim( sanitize_text_field( $line ) ), 0, 200 );
			if ( '' !== $line && ! in_array( $line, $keywords, true ) ) {
				$keywords[] = $line;
			}
		}

		if ( ! $keywords ) {
			ABCGS_Notices::add( 'Vui lòng nhập danh sách từ khóa.', 'error' );
			self::back( self::PAGE_MAIN );
		}
		if ( count( $keywords ) > ABCGS_Queue::MAX_KEYWORDS_PER_BATCH ) {
			ABCGS_Notices::add( 'Tối đa ' . ABCGS_Queue::MAX_KEYWORDS_PER_BATCH . ' từ khóa mỗi lần.', 'error' );
			self::back( self::PAGE_MAIN );
		}
		if ( $writing['category_id'] <= 0 || ! term_exists( $writing['category_id'], 'category' ) ) {
			ABCGS_Notices::add( 'Vui lòng chọn chuyên mục.', 'error' );
			self::back( self::PAGE_MAIN );
		}
		if ( strlen( ABCGS_Secrets::get( 'api_key' ) ) < 20 ) {
			ABCGS_Notices::add( 'Chưa có OpenAI API key. Nhập ở trang Cài đặt chung.', 'error' );
			self::back( self::PAGE_MAIN );
		}

		$check = ABCGS_License::check();
		if ( ! $check['can_write'] && ! $check['transient'] && ! $check['retry_at'] ) {
			ABCGS_Notices::add( 'Không thể bắt đầu: ' . $check['message'], 'error' );
			self::back( self::PAGE_MAIN );
		}

		$added = ABCGS_Queue::enqueue( $keywords, $settings, get_current_user_id() );
		if ( ! $added ) {
			ABCGS_Notices::add( 'Hệ thống đang bận, chưa thêm được từ khóa. Thử lại sau vài giây.', 'error' );
			self::back( self::PAGE_MAIN );
		}

		ABCGS_Notices::add( 'Đã xếp hàng ' . $added . ' từ khóa. Cron sẽ lần lượt tạo bài và gắn link silo khi xong đợt.' );
		if ( $check['retry_at'] ) {
			ABCGS_Notices::add( $check['message'] . ' Queue sẽ tự chạy lúc ' . wp_date( 'H:i d/m', $check['retry_at'] ) . '.', 'warning' );
		} elseif ( $check['transient'] ) {
			ABCGS_Notices::add( $check['message'] . ' Queue sẽ thử lại tự động.', 'warning' );
		}
		self::back( self::PAGE_MAIN );
	}

	public static function handle_queue_action() {
		self::guard( 'abcgs_queue_action' );
		$op = (string) self::post( 'op' );
		list( $verb, $id ) = array_pad( explode( '|', $op, 2 ), 2, '' );
		$id = preg_replace( '/[^A-Za-z0-9]/', '', $id );

		switch ( $verb ) {
			case 'retry':
				$ok = ABCGS_Queue::retry( $id );
				ABCGS_Notices::add( $ok ? 'Đã đưa mục vào hàng chờ thử lại.' : 'Không tìm thấy mục cần thử lại.', $ok ? 'success' : 'warning' );
				break;
			case 'cancel':
				$ok = ABCGS_Queue::cancel( $id );
				ABCGS_Notices::add( $ok ? 'Đã hủy mục.' : 'Không hủy được mục này.', $ok ? 'success' : 'warning' );
				break;
			case 'cancel_all':
				ABCGS_Notices::add( 'Đã hủy ' . intval( ABCGS_Queue::cancel_all() ) . ' mục đang chờ.' );
				break;
			case 'clear':
				ABCGS_Notices::add( 'Đã dọn ' . ABCGS_Queue::clear_finished() . ' mục đã xong/đã hủy.' );
				break;
			case 'unlock':
				// Chỉ mở khi có mục kẹt ở bước gọi API quá 5 phút: tiến trình giữ khóa đã chết.
				if ( ABCGS_Queue::stalled() ) {
					ABCGS_Lock::release( 'queue' );
					ABCGS_Notices::add( 'Đã mở khóa queue. Bấm "Viết liên tục" để chạy lại mục bị kẹt.' );
				} else {
					ABCGS_Notices::add( 'Queue không bị kẹt, chưa mở khóa.', 'info' );
				}
				break;
			case 'run':
				if ( ABCGS_Lock::is_locked( 'queue' ) ) {
					ABCGS_Notices::add( 'Queue đang được xử lý ở tiến trình khác.', 'info' );
				} else {
					ABCGS_Queue::process();
					ABCGS_Notices::add( 'Đã xử lý 1 lượt queue.' );
				}
				break;
		}
		self::back( self::PAGE_MAIN );
	}

	public static function handle_save_general() {
		self::guard( 'abcgs_save_general' );

		if ( ! ABCGS_Secrets::from_constant( 'api_key' ) ) {
			if ( self::post( 'abcgs_delete_key' ) ) {
				ABCGS_Secrets::set( 'api_key', '' );
				ABCGS_Notices::add( 'Đã xóa OpenAI API key.' );
			} else {
				$key = trim( (string) self::post( 'abcgs_api_key' ) );
				if ( '' !== $key ) {
					if ( strlen( $key ) < 20 || preg_match( '/\s/', $key ) ) {
						ABCGS_Notices::add( 'API key không hợp lệ, chưa lưu key mới.', 'error' );
					} else {
						ABCGS_Secrets::set( 'api_key', $key );
					}
				}
			}
		}

		ABCGS_Settings::update(
			array(
				'model'        => ABCGS_Settings::sanitize_model( self::post( 'abcgs_model_select' ), self::post( 'abcgs_model_custom' ) ),
				'site_profile' => mb_substr( sanitize_textarea_field( (string) self::post( 'abcgs_site_profile' ) ), 0, 3000 ),
			)
		);
		ABCGS_Notices::add( 'Đã lưu cài đặt chung.' );
		self::back( self::PAGE_GENERAL );
	}

	public static function handle_analyze_site() {
		self::guard( 'abcgs_analyze_site' );
		ABCGS_Settings::update( array( 'site_profile' => self::site_profile() ) );
		ABCGS_Notices::add( 'Đã phân tích website và cập nhật hồ sơ lĩnh vực.' );
		self::back( self::PAGE_GENERAL );
	}

	/**
	 * Tóm tắt chuyên mục và thẻ phổ biến từ 30 bài gần nhất (như v6.1.1).
	 */
	private static function site_profile() {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'fields'         => 'ids',
			)
		);
		if ( ! $posts ) {
			return 'Website chưa có bài viết hoặc dữ liệu ít. Chủ đề chính: chưa xác định rõ.';
		}
		$cats = array();
		$tags = array();
		foreach ( $posts as $pid ) {
			foreach ( (array) get_the_category( $pid ) as $c ) {
				$cats[] = $c->name;
			}
			$terms = get_the_terms( $pid, 'post_tag' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$tags[] = $t->name;
				}
			}
		}
		$top = function ( $names, $n ) {
			if ( ! $names ) {
				return array();
			}
			$counts = array_count_values( $names );
			arsort( $counts );
			return array_slice( array_keys( $counts ), 0, $n );
		};
		$top_cats = $top( $cats, 8 );
		$top_tags = $top( $tags, 12 );
		return ( $top_cats ? 'Website tập trung vào các chuyên mục: ' . implode( ', ', $top_cats ) . '.' : 'Không xác định rõ chuyên mục chính.' )
			. ( $top_tags ? ' Từ khóa nổi bật thường gặp: ' . implode( ', ', $top_tags ) . '.' : '' )
			. ' Sử dụng thông tin này làm context để viết bài đúng lĩnh vực của website.';
	}

	public static function handle_save_license() {
		self::guard( 'abcgs_save_license' );

		if ( self::post( 'abcgs_remove_license' ) ) {
			ABCGS_License::remove();
			ABCGS_Notices::add( 'Đã xóa license khỏi website này.' );
			self::back( self::PAGE_LICENSE );
		}

		$edition = 'pro' === self::post( 'abcgs_edition' ) ? 'pro' : 'free';
		$email   = sanitize_email( (string) self::post( 'abcgs_license_email' ) );
		if ( ! is_email( $email ) ) {
			ABCGS_Notices::add( 'Email không hợp lệ.', 'error' );
			self::back( self::PAGE_LICENSE );
		}

		$serial = null;
		if ( 'pro' === $edition && ! ABCGS_Secrets::from_constant( 'serial' ) ) {
			$input = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) self::post( 'abcgs_serial_key' ) );
			if ( '' !== $input ) {
				$serial = mb_substr( $input, 0, 100 );
			} elseif ( '' === ABCGS_Secrets::get( 'serial' ) ) {
				ABCGS_Notices::add( 'Bản Pro cần Serial Key.', 'error' );
				self::back( self::PAGE_LICENSE );
			}
		}
		ABCGS_License::save_config( $edition, $email, $serial );

		$check = ABCGS_License::check();
		if ( $check['can_write'] ) {
			ABCGS_Notices::add( 'License hợp lệ. ' . ABCGS_License::summary() );
		} elseif ( $check['retry_at'] ) {
			ABCGS_Notices::add( 'License hợp lệ. ' . $check['message'], 'warning' );
		} elseif ( $check['transient'] ) {
			ABCGS_Notices::add( 'Đã lưu nhưng chưa kiểm tra được: ' . $check['message'], 'warning' );
		} else {
			ABCGS_Notices::add( $check['message'], 'error' );
		}
		self::back( self::PAGE_LICENSE );
	}

	public static function handle_save_images() {
		self::guard( 'abcgs_save_images' );
		ABCGS_Settings::update(
			ABCGS_Settings::sanitize_images(
				array(
					'image_model'     => self::post( 'abcgs_image_model' ),
					'image_size'      => self::post( 'abcgs_image_size' ),
					'image_quality'   => self::post( 'abcgs_image_quality' ),
					'image_format'    => self::post( 'abcgs_image_format' ),
					'inline_prompt'   => self::post( 'abcgs_inline_prompt' ),
					'featured_prompt' => self::post( 'abcgs_featured_prompt' ),
				)
			)
		);
		ABCGS_Notices::add( 'Đã lưu cài đặt hình ảnh.' );
		self::back( self::PAGE_IMAGES );
	}

	public static function handle_after_images() {
		self::guard( 'abcgs_after_images' );
		$op = (string) self::post( 'op' );

		if ( 'decline' === $op ) {
			update_option( 'abcgs_image_decision_pending', 0, false );
			ABCGS_Notices::add( 'Đã bỏ qua tạo ảnh. Bài viết được giữ nguyên.' );
			self::back( self::PAGE_MAIN );
		}

		$allowed  = array_map( 'intval', (array) get_option( 'abcgs_recent_written_ids', array() ) );
		$selected = array_map( 'intval', (array) self::post( 'abcgs_written_ids', array() ) );
		$selected = array_values( array_filter( array_intersect( $selected, $allowed ), 'get_post' ) );

		if ( 'sync_seo' === $op ) {
			$s         = ABCGS_Settings::get();
			$overwrite = (bool) self::post( 'abcgs_seo_overwrite' );
			$fields    = 0;
			foreach ( $selected as $pid ) {
				$keyword = (string) get_post_meta( $pid, '_abcgs_main_keyword', true );
				if ( '' !== $keyword ) {
					$fields += ABCGS_SEO::apply( $pid, $keyword, $s, $overwrite );
				}
			}
			ABCGS_Notices::add( 'Đã đồng bộ SEO cho ' . count( $selected ) . ' bài, ghi ' . $fields . ' trường' . ( $overwrite ? ' (ghi đè).' : ' (chỉ ô trống).' ) );
			self::back( self::PAGE_AFTER );
		}

		if ( 'start' === $op ) {
			$limit = max( 0, min( 100, intval( self::post( 'abcgs_heading_image_limit', 3 ) ) ) );
			update_option( 'abcgs_heading_image_limit', $limit, false );
			$request = mb_substr( sanitize_textarea_field( (string) self::post( 'abcgs_custom_image_request' ) ), 0, 1000 );

			list( $added, $warnings ) = $limit ? ABCGS_Image_Queue::enqueue_headings( $selected, $limit, $request ) : array( 0, array() );
			if ( self::post( 'abcgs_after_featured' ) ) {
				$added += ABCGS_Image_Queue::enqueue_featured( $selected );
			}
			update_option( 'abcgs_image_decision_pending', 0, false );
			foreach ( $warnings as $w ) {
				ABCGS_Notices::add( $w, 'warning' );
			}
			ABCGS_Notices::add( 'Đã xếp hàng ' . $added . ' tác vụ ảnh. Hệ thống sẽ xử lý lần lượt.' );
		}
		self::back( self::PAGE_AFTER );
	}

	public static function handle_image_tools() {
		self::guard( 'abcgs_image_tools' );
		$op   = (string) self::post( 'op' );
		$back = self::PAGE_AFTER === self::post( 'abcgs_back' ) ? self::PAGE_AFTER : self::PAGE_SCAN;

		switch ( $op ) {
			case 'scan':
				ABCGS_Notices::add( 'Đã quét ' . ABCGS_Image_Queue::scan() . ' bài viết.' );
				break;
			case 'inline':
			case 'featured':
				$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) self::post( 'post_ids', array() ) ), 'get_post' ) ) );
				if ( ! $ids ) {
					ABCGS_Notices::add( 'Vui lòng chọn ít nhất 1 bài viết.', 'error' );
					break;
				}
				if ( 'inline' === $op ) {
					list( $added, $warnings ) = ABCGS_Image_Queue::enqueue_headings( $ids, max( 1, ABCGS_Settings::heading_image_limit() ) );
					foreach ( array_slice( $warnings, 0, 5 ) as $w ) {
						ABCGS_Notices::add( $w, 'warning' );
					}
					ABCGS_Notices::add( 'Đã xếp hàng ' . $added . ' tác vụ ảnh trong bài.' );
				} else {
					ABCGS_Notices::add( 'Đã xếp hàng ' . ABCGS_Image_Queue::enqueue_featured( $ids ) . ' tác vụ ảnh đại diện.' );
				}
				break;
			case 'run':
				ABCGS_Notices::add( ABCGS_Image_Queue::process() ? 'Đã xử lý 1 tác vụ ảnh.' : 'Queue ảnh đang được xử lý ở tiến trình khác.', 'info' );
				break;
			case 'clear':
				ABCGS_Notices::add( 'Đã dọn ' . ABCGS_Image_Queue::clear_finished() . ' tác vụ ảnh đã xong/lỗi.' );
				break;
		}
		self::back( $back );
	}

	/**
	 * Viết 1 bài ngay trong yêu cầu AJAX của trình duyệt. Dùng khi WP-Cron bị
	 * máy chủ web dừng giữa chừng (LiteSpeed không bật noabort, cron chập chờn).
	 */
	public static function ajax_process_next_post() {
		check_ajax_referer( 'abcgs_post_worker', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Không có quyền.' ), 403 );
		}
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
		$ran    = ABCGS_Queue::process( 0 );
		$counts = ABCGS_Queue::counts();
		$last   = '';
		foreach ( ABCGS_Queue::items() as $item ) {
			if ( 'error' === ( $item['status'] ?? '' ) ) {
				$last = (string) ( $item['content_status'] ?? '' );
			}
		}
		wp_send_json_success(
			array(
				'busy'       => ! $ran,
				'due'        => ABCGS_Queue::due_count(),
				'pending'    => $counts['pending'] + $counts['waiting_quota'],
				'done'       => $counts['done'],
				'errors'     => $counts['error'],
				'last_error' => $last,
			)
		);
	}

	public static function ajax_process_next_image() {
		check_ajax_referer( 'abcgs_image_worker', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Không có quyền.' ), 403 );
		}
		$ran           = ABCGS_Image_Queue::process();
		$data          = ABCGS_Image_Queue::summary();
		$data['busy']  = ! $ran;
		wp_send_json_success( $data );
	}
}
