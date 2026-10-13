<?php
/**
 * Xử lý HTML do AI trả về. Nội dung AI là dữ liệu không tin cậy.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Content {

	public static function strip_code_fences( $html ) {
		$html = trim( (string) $html );
		$html = preg_replace( '/^```[a-zA-Z0-9]*\s*/', '', $html );
		$html = preg_replace( '/\s*```$/', '', $html );
		return trim( $html );
	}

	/**
	 * Tách tiêu đề khỏi nội dung.
	 *
	 * Thứ tự: <h1> đầu tiên → dòng chữ trần trước thẻ HTML đầu tiên → <h2> đầu tiên
	 * (trường hợp cuối giữ nguyên <h2> trong bài).
	 *
	 * @return array [ title, html ]
	 */
	public static function split_title( $html, $fallback ) {
		if ( preg_match( '/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			$title = self::clean_line( $m[1][0] );
			$html  = substr_replace( $html, '', $m[0][1], strlen( $m[0][0] ) );
			if ( '' !== $title ) {
				return array( $title, trim( $html ) );
			}
		}

		if ( preg_match( '/^\s*([^<\r\n]{10,300})\s*(?:\r?\n|<)/u', $html, $m ) ) {
			$title = self::clean_line( $m[1] );
			$html  = preg_replace( '/^\s*' . preg_quote( $m[1], '/' ) . '/u', '', $html, 1 );
			if ( '' !== $title ) {
				return array( $title, trim( $html ) );
			}
		}

		if ( preg_match( '/<h2\b[^>]*>(.*?)<\/h2>/is', $html, $m ) ) {
			$title = self::clean_line( $m[1] );
			if ( '' !== $title ) {
				return array( $title, $html );
			}
		}

		return array( $fallback, $html );
	}

	private static function clean_line( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ), " \t\n\r\0\x0B#*" );
		return mb_substr( $text, 0, 200 );
	}

	public static function demote_h1( $html ) {
		$html = preg_replace( '/<\s*h1\b([^>]*)>/i', '<h2$1>', $html );
		return preg_replace( '/<\s*\/\s*h1\s*>/i', '</h2>', $html );
	}

	/**
	 * Chỉ giữ thẻ định dạng cơ bản. wp_kses loại script, iframe, thuộc tính on*,
	 * và giao thức javascript: trong href.
	 */
	public static function sanitize( $html ) {
		$allowed = array(
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'strong'     => array(),
			'em'         => array(),
			'br'         => array(),
			'blockquote' => array(),
			'a'          => array(
				'href'  => true,
				'title' => true,
				'rel'   => true,
			),
		);
		return trim( wp_kses( (string) $html, $allowed, array( 'http', 'https' ) ) );
	}

	public static function excerpt( $html, $words = 40 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
		if ( '' === $text ) {
			return '';
		}
		$parts = explode( ' ', $text );
		return count( $parts ) <= $words ? $text : implode( ' ', array_slice( $parts, 0, $words ) ) . '...';
	}

	public static function slug( $keyword ) {
		$slug = sanitize_title( trim( (string) $keyword ) );
		return $slug ? $slug : 'bai-viet';
	}

	/**
	 * Kiểm tra ngôn ngữ (giữ logic v6.1.1).
	 * Tiếng Anh: chỉ coi là sai khi có dấu hiệu RÕ RỆT là tiếng Việt.
	 */
	public static function matches_language( $html, $language, $keyword = '' ) {
		$plain = trim( wp_strip_all_tags( (string) $html ) );
		if ( mb_strlen( $plain, 'UTF-8' ) < 100 ) {
			return true;
		}
		if ( '' !== $keyword ) {
			$plain = str_ireplace( $keyword, ' ', $plain );
		}
		$sample = mb_substr( $plain, 0, 4500, 'UTF-8' );
		if ( 'zh' === $language ) {
			return (bool) preg_match( '/[\x{4E00}-\x{9FFF}]/u', $sample );
		}
		$vi = preg_match_all( '/[ăâđêôơưĂÂĐÊÔƠƯàáảãạằắẳẵặầấẩẫậèéẻẽẹềếểễệìíỉĩịòóỏõọồốổỗộờớởỡợùúủũụừứửữựỳýỷỹỵ]/u', $sample );
		if ( 'vi' === $language ) {
			return $vi >= 3;
		}
		if ( 'en' === $language ) {
			$en = preg_match_all( '/\b(the|and|with|for|your|this|that|from|are|can|how|what|why|which|you|will|should|have|has|is|in|of|to)\b/i', $sample );
			return ! ( $vi >= 30 && $vi > ( $en * 2 ) );
		}
		return true;
	}
}
