<?php
/**
 * Ghi meta SEO cho Rank Math / Yoast.
 *
 * Mặc định chỉ điền ô đang trống để không ghi đè chỉnh sửa tay. Không còn hook
 * save_post ghi lại meta mỗi lần lưu bài như v6.1.1.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_SEO {

	public static function focus( $keyword, array $s ) {
		$keyword = trim( sanitize_text_field( (string) $keyword ) );
		$custom  = trim( sanitize_text_field( (string) ( $s['seo_custom_focus'] ?? '' ) ) );
		if ( 'custom' === ( $s['seo_focus_mode'] ?? 'keyword' ) && '' !== $custom ) {
			return $custom;
		}
		return $keyword;
	}

	/**
	 * Đặt cụm từ khóa ở đầu tiêu đề nếu tiêu đề chưa chứa (kiểm tra của Rank Math).
	 */
	public static function optimized_title( $title, $focus ) {
		$title = trim( wp_strip_all_tags( (string) $title ) );
		$focus = trim( wp_strip_all_tags( (string) $focus ) );
		if ( '' === $focus ) {
			return $title;
		}
		if ( '' === $title ) {
			return $focus;
		}
		return false !== mb_stripos( $title, $focus ) ? $title : $focus . ' – ' . $title;
	}

	/**
	 * Plugin SEO sẽ được ghi.
	 */
	public static function targets( array $s ) {
		switch ( $s['seo_plugin'] ?? 'auto' ) {
			case 'none':
				return array();
			case 'rank_math':
				return array( 'rank_math' );
			case 'yoast':
				return array( 'yoast' );
			case 'both':
				return array( 'rank_math', 'yoast' );
		}
		$found = array();
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$found[] = 'rank_math';
		}
		if ( defined( 'WPSEO_VERSION' ) ) {
			$found[] = 'yoast';
		}
		// Chưa cài plugin SEO nào: ghi cả hai để dùng được khi cài sau.
		return $found ? $found : array( 'rank_math', 'yoast' );
	}

	/**
	 * @return int Số trường đã ghi.
	 */
	public static function apply( $post_id, $keyword, array $s, $overwrite = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type ) {
			return 0;
		}
		$focus = self::focus( $keyword, $s );
		if ( '' === $focus ) {
			return 0;
		}

		$title       = self::optimized_title( $post->post_title, $focus );
		$description = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
		if ( '' === $description ) {
			$description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 24, '' );
		}
		if ( false === mb_stripos( $description, $focus ) ) {
			$description = $focus . '. ' . $description;
		}
		$description = mb_substr( trim( $description ), 0, 155 );

		$fields = array();
		foreach ( self::targets( $s ) as $target ) {
			if ( 'rank_math' === $target ) {
				$fields['rank_math_focus_keyword'] = $focus;
				$fields['rank_math_title']         = $title . ' %sep% %sitename%';
				$fields['rank_math_description']   = $description;
			} else {
				$fields['_yoast_wpseo_focuskw']  = $focus;
				$fields['_yoast_wpseo_title']    = $title . ' %%sep%% %%sitename%%';
				$fields['_yoast_wpseo_metadesc'] = $description;
			}
		}

		$written = 0;
		foreach ( $fields as $key => $value ) {
			if ( ! $overwrite && '' !== (string) get_post_meta( $post_id, $key, true ) ) {
				continue;
			}
			update_post_meta( $post_id, $key, $value );
			$written++;
		}
		if ( $fields ) {
			update_post_meta( $post_id, '_abcgs_seo_focus_saved', $focus );
		}
		return $written;
	}
}
