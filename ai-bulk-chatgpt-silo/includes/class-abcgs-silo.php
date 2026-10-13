<?php
/**
 * Liên kết nội bộ silo cho một đợt bài.
 *
 * Bài đầu = trụ cột, link sang bài 2. Mỗi bài con link sang bài kế tiếp và về
 * bài trụ cột. Khối <div class="silo-links"> được thay thế nếu chạy lại.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Silo {

	public static function label( $language ) {
		$labels = array(
			'vi' => 'Liên kết nội bộ cùng chủ đề:',
			'en' => 'Related internal links:',
			'zh' => '相关内部链接：',
		);
		return $labels[ $language ] ?? $labels['vi'];
	}

	/**
	 * @param int[]    $post_ids Theo đúng thứ tự từ khóa, phần tử 0 là bài trụ cột.
	 * @param string[] $anchors  Anchor text tương ứng.
	 * @return true|WP_Error
	 */
	public static function apply( array $post_ids, array $anchors ) {
		$count = min( count( $post_ids ), count( $anchors ) );
		if ( $count < 2 ) {
			return new WP_Error( 'abcgs_silo_small', 'Không đủ bài để tạo silo (tối thiểu 2 bài).' );
		}
		$pillar_url = get_permalink( $post_ids[0] );
		if ( ! $pillar_url ) {
			return new WP_Error( 'abcgs_silo_pillar', 'Không lấy được đường dẫn bài trụ cột.' );
		}

		$errors = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$post_id = $post_ids[ $i ];
			$content = (string) get_post_field( 'post_content', $post_id );
			if ( '' === $content ) {
				continue;
			}

			$links = array();
			if ( $i + 1 < $count ) {
				$next = get_permalink( $post_ids[ $i + 1 ] );
				if ( $next ) {
					$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $next ), esc_html( $anchors[ $i + 1 ] ) );
				}
			}
			if ( $i > 0 ) {
				$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $pillar_url ), esc_html( $anchors[0] ) );
			}
			if ( ! $links ) {
				continue;
			}

			$language = get_post_meta( $post_id, '_abcgs_article_language', true );
			if ( ! array_key_exists( $language, ABCGS_Settings::languages() ) ) {
				$language = ABCGS_Settings::get()['language'];
			}
			$block = '<div class="silo-links"><p><strong>' . esc_html( self::label( $language ) ) . '</strong> '
				. implode( ' | ', $links ) . '</p></div>';

			$content = preg_replace( '~\s*<div\s+class=["\']silo-links["\'][^>]*>.*?</div>~is', '', $content );
			$updated = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content . "\n\n" . $block,
				),
				true
			);
			if ( is_wp_error( $updated ) ) {
				$errors[] = '#' . $post_id . ': ' . $updated->get_error_message();
			}
		}

		return $errors ? new WP_Error( 'abcgs_silo_update', 'Lỗi cập nhật bài ' . implode( '; ', $errors ) ) : true;
	}
}
