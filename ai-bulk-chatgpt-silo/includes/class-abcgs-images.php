<?php
/**
 * Tạo ảnh AI, lưu Media, chèn figure + figcaption sau H2/H3, ảnh đại diện.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Images {

	const TARGET_BYTES = 1048576;

	private static $mimes = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);

	private static function language( $post_id ) {
		$language = get_post_meta( $post_id, '_abcgs_article_language', true );
		return array_key_exists( $language, ABCGS_Settings::languages() ) ? $language : ABCGS_Settings::get()['language'];
	}

	/**
	 * ALT nằm trong thẻ img; caption hiển thị dưới ảnh qua figcaption.
	 */
	public static function image_text( $post_id, $heading ) {
		$heading = mb_substr( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $heading ) ) ), 0, 150, 'UTF-8' );
		$lang    = self::language( $post_id );
		$prefix  = 'en' === $lang ? 'Illustration: ' : ( 'zh' === $lang ? '配图：' : 'Hình minh họa: ' );
		return array(
			'alt'     => $heading,
			'caption' => $prefix . $heading,
		);
	}

	private static function figure( $attachment_id, $url, $alt, $caption ) {
		return '<figure class="wp-block-image abcgs-image-with-caption">'
			. '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . absint( $attachment_id ) . '" loading="lazy"/>'
			. '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>'
			. '</figure>';
	}

	private static function set_attachment_text( $attachment_id, $alt, $caption ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		wp_update_post(
			array(
				'ID'           => absint( $attachment_id ),
				'post_excerpt' => sanitize_text_field( $caption ),
			)
		);
	}

	public static function headings( $content ) {
		preg_match_all( '/<h([23])\b[^>]*>.*?<\/h\1>/is', (string) $content, $m, PREG_OFFSET_CAPTURE );
		return $m[0];
	}

	public static function heading_marker( $index ) {
		return '<!-- ABCGS_HEADING_IMAGE_' . absint( $index ) . ' -->';
	}

	/**
	 * Tạo và chèn ảnh ngay sau thẻ H2/H3 thứ $index (đếm từ 0).
	 *
	 * @return int|true|WP_Error ID ảnh, true nếu đã có sẵn.
	 */
	public static function heading_image( $post_id, $keyword, array $s, $index, $custom = '' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'abcgs_missing_post', 'Bài viết không tồn tại.' );
		}
		$content = (string) $post->post_content;
		$index   = absint( $index );
		$marker  = self::heading_marker( $index );
		if ( false !== strpos( $content, $marker ) ) {
			return true;
		}
		$heads = self::headings( $content );
		if ( ! isset( $heads[ $index ] ) ) {
			return new WP_Error( 'abcgs_missing_heading', 'Không tìm thấy H2/H3 ở vị trí đã chọn.' );
		}

		$heading_html  = $heads[ $index ][0];
		$position      = $heads[ $index ][1] + strlen( $heading_html );
		$next          = isset( $heads[ $index + 1 ] ) ? $heads[ $index + 1 ][1] : strlen( $content );
		$section       = wp_trim_words( wp_strip_all_tags( substr( $content, $position, max( 0, $next - $position ) ) ), 150, '' );
		$heading_title = trim( wp_strip_all_tags( $heading_html ) );
		$title         = wp_strip_all_tags( get_the_title( $post_id ) );

		$prompt = ABCGS_Prompts::from_template(
			$s['inline_prompt'],
			array(
				'{{title}}'           => $title,
				'{{keyword}}'         => $keyword,
				'{{h2_title}}'        => $heading_title,
				'{{section_content}}' => $section,
			),
			ABCGS_Prompts::default_inline_image( $title, $keyword, $heading_title, $section )
		);
		if ( '' !== trim( $custom ) ) {
			$prompt .= "\nAdditional requirements: " . sanitize_textarea_field( $custom );
		}
		$prompt = ABCGS_Prompts::finalize_image( $prompt, $index );

		$attachment = self::create_attachment( $post_id, $prompt, $title . ' ' . $heading_title, $s, 'heading-' . $index );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		$url = wp_get_attachment_url( $attachment );
		if ( ! $url ) {
			return new WP_Error( 'abcgs_image_url', 'Không lấy được URL ảnh.' );
		}
		$text = self::image_text( $post_id, $heading_title );
		self::set_attachment_text( $attachment, $text['alt'], $text['caption'] );

		// Đọc lại nội dung: có thể đã đổi trong lúc chờ API ảnh.
		$fresh = (string) get_post_field( 'post_content', $post_id );
		$heads = self::headings( $fresh );
		if ( ! isset( $heads[ $index ] ) || false !== strpos( $fresh, $marker ) ) {
			return new WP_Error( 'abcgs_content_changed', 'Nội dung bài đã thay đổi trong lúc tạo ảnh; ảnh đã lưu vào Media nhưng chưa chèn.' );
		}
		$position = $heads[ $index ][1] + strlen( $heads[ $index ][0] );
		$block    = $marker . "\n" . self::figure( $attachment, $url, $text['alt'], $text['caption'] );
		$result   = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => substr( $fresh, 0, $position ) . "\n\n" . $block . "\n\n" . substr( $fresh, $position ),
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		update_post_meta( $post_id, '_abcgs_heading_image_' . $index, intval( $attachment ) );
		return intval( $attachment );
	}

	/**
	 * @return int|WP_Error
	 */
	public static function featured_image( $post_id, $keyword, array $s ) {
		if ( has_post_thumbnail( $post_id ) ) {
			return intval( get_post_thumbnail_id( $post_id ) );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'abcgs_missing_post', 'Không tìm thấy bài viết.' );
		}
		$title   = wp_strip_all_tags( get_the_title( $post_id ) );
		$summary = wp_trim_words( wp_strip_all_tags( $post->post_content ), 90, '' );
		$prompt  = ABCGS_Prompts::from_template(
			$s['featured_prompt'],
			array(
				'{{title}}'   => $title,
				'{{keyword}}' => $keyword,
				'{{summary}}' => $summary,
			),
			ABCGS_Prompts::default_featured_image( $title, $keyword, $summary )
		);
		$prompt = ABCGS_Prompts::finalize_image( $prompt, 7 );

		$attachment = self::create_attachment( $post_id, $prompt, $title, $s, 'featured' );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		$text = self::image_text( $post_id, $title );
		self::set_attachment_text( $attachment, $text['alt'], $text['caption'] );
		if ( ! set_post_thumbnail( $post_id, $attachment ) ) {
			return new WP_Error( 'abcgs_thumbnail', 'Đã tạo ảnh nhưng không đặt được ảnh đại diện.' );
		}
		update_post_meta( $post_id, '_abcgs_featured_image_id', intval( $attachment ) );
		return intval( $attachment );
	}

	/**
	 * @return int|WP_Error
	 */
	private static function create_attachment( $post_id, $prompt, $alt, array $s, $kind ) {
		$api_key = ABCGS_Secrets::get( 'api_key' );
		if ( '' === $api_key ) {
			return new WP_Error( 'abcgs_no_api_key', 'Chưa có OpenAI API key.' );
		}
		$bytes = ABCGS_OpenAI::image( $api_key, $s, $prompt );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}

		// Kiểm tra định dạng thật của dữ liệu, không tin phần mở rộng.
		$info = @getimagesizefromstring( $bytes );
		$mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
		if ( ! isset( self::$mimes[ $mime ] ) ) {
			return new WP_Error( 'abcgs_image_type', 'Dữ liệu trả về không phải ảnh JPEG/PNG/WebP hợp lệ.' );
		}
		if ( strlen( $bytes ) > 20 * MB_IN_BYTES ) {
			return new WP_Error( 'abcgs_image_big', 'Ảnh trả về quá lớn.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$ext      = self::$mimes[ $mime ];
		$filename = sanitize_file_name( sanitize_title( get_the_title( $post_id ) ) . '-ai-' . $kind . '-' . time() . '.' . $ext );
		$upload   = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'abcgs_upload', $upload['error'] );
		}

		$compressed = self::compress( $upload['file'], $mime, self::TARGET_BYTES );
		if ( is_wp_error( $compressed ) ) {
			wp_delete_file( $upload['file'] );
			return $compressed;
		}

		$attach_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => sanitize_text_field( $alt ),
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $attach_id ) ) {
			wp_delete_file( $upload['file'] );
			return $attach_id;
		}
		$meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		if ( $meta ) {
			wp_update_attachment_metadata( $attach_id, $meta );
		}
		return intval( $attach_id );
	}

	/**
	 * Nén dưới giới hạn dung lượng (giữ logic v6.1.1: thu về 1.200px, giảm chất lượng dần).
	 */
	private static function compress( $file, $mime, $limit ) {
		if ( ! is_file( $file ) ) {
			return new WP_Error( 'abcgs_file_missing', 'Không tìm thấy file ảnh.' );
		}
		clearstatcache( true, $file );
		if ( filesize( $file ) < $limit ) {
			return true;
		}
		$ext = self::$mimes[ $mime ];
		foreach ( array( 85, 75, 65, 55, 45, 35, 25 ) as $quality ) {
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				return $editor;
			}
			$editor->set_quality( $quality );
			$size = $editor->get_size();
			if ( ! empty( $size['width'] ) && $size['width'] > 1200 ) {
				$editor->resize( 1200, null, false );
			}
			$temp  = dirname( $file ) . '/.abcgs-' . wp_generate_password( 10, false, false ) . '.' . $ext;
			$saved = $editor->save( $temp, $mime );
			if ( is_wp_error( $saved ) ) {
				continue;
			}
			$candidate = $saved['path'] ?? $temp;
			if ( ! is_file( $candidate ) ) {
				continue;
			}
			clearstatcache( true, $candidate );
			if ( filesize( $candidate ) < $limit && @copy( $candidate, $file ) ) {
				wp_delete_file( $candidate );
				return true;
			}
			wp_delete_file( $candidate );
		}
		return new WP_Error( 'abcgs_too_large', 'Không thể nén ảnh xuống dưới 1 MB. Kiểm tra GD/Imagick và quyền ghi uploads.' );
	}
}
