<?php
/**
 * Cấu hình plugin: giá trị mặc định, whitelist và đọc/ghi option.
 * API key và serial KHÔNG nằm ở đây mà ở ABCGS_Secrets.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Settings {

	const OPTION = 'abcgs_settings';

	public static function defaults() {
		return array(
			'model'            => 'gpt-6-luna',
			'category_id'      => 0,
			'keyword_density'  => 5,
			'post_status'      => 'publish',
			'post_length'      => 1200,
			'language'         => 'vi',
			'tone'             => 'neutral',
			'article_style'    => 'general_guide',
			'site_profile'     => '',
			'image_model'      => 'gpt-image-1',
			'image_size'       => '1536x1024',
			'image_quality'    => 'medium',
			'image_format'     => 'webp',
			'inline_prompt'    => '',
			'featured_prompt'  => '',
			'seo_plugin'       => 'auto',
			'seo_focus_mode'   => 'keyword',
			'seo_custom_focus' => '',
		);
	}

	public static function models() {
		return array(
			'gpt-6-luna'    => 'GPT-6 Luna – nhanh, tiết kiệm (khuyên dùng viết hàng loạt)',
			'gpt-6.1-sol'   => 'GPT-6.1 Sol – cân bằng chất lượng và chi phí',
			'gpt-6-astra'   => 'GPT-6 Astra – chất lượng cao nhất, chi phí cao',
			'gpt-6-sol'     => 'GPT-6 Sol – phiên bản Sol trước',
			'gpt-5.6-sol'   => 'GPT-5.6 Sol',
			'gpt-5.6-terra' => 'GPT-5.6 Terra',
			'gpt-5.6-luna'  => 'GPT-5.6 Luna',
			'gpt-5.5'       => 'GPT-5.5',
			'gpt-5.4'       => 'GPT-5.4',
			'gpt-5.4-mini'  => 'GPT-5.4 mini',
			'gpt-4.1-mini'  => 'gpt-4.1-mini (nhanh, tiết kiệm)',
			'gpt-4.1'       => 'gpt-4.1 (chất lượng cao)',
			'gpt-4o-mini'   => 'gpt-4o-mini (tối ưu chi phí)',
			'gpt-4o'        => 'gpt-4o (đa năng cao cấp)',
			'o3-mini'       => 'o3-mini (lý luận mạnh)',
			'gpt-3.5-turbo' => 'gpt-3.5-turbo (đời cũ, giá rẻ)',
		);
	}

	public static function languages() {
		return array(
			'vi' => 'Tiếng Việt',
			'en' => 'Tiếng Anh',
			'zh' => 'Tiếng Trung (giản thể)',
		);
	}

	public static function tones() {
		return array(
			'neutral'      => 'Trung lập',
			'friendly'     => 'Thân thiện',
			'formal'       => 'Trang trọng',
			'expert'       => 'Chuyên gia',
			'promotional'  => 'Quảng cáo nhẹ',
			'journalistic' => 'Báo chí',
			'storytelling' => 'Kể chuyện',
		);
	}

	public static function article_styles() {
		return array(
			'general_guide'       => 'Giới thiệu / hướng dẫn tổng quan',
			'news_article'        => 'Tin tức / cập nhật',
			'product_description' => 'Mô tả sản phẩm',
			'product_review'      => 'Đánh giá sản phẩm',
			'comparison'          => 'So sánh',
			'how_to'              => 'How-to',
			'listicle'            => 'Bài danh sách (Top X)',
		);
	}

	public static function seo_plugins() {
		return array(
			'auto'      => 'Tự phát hiện Rank Math / Yoast',
			'rank_math' => 'Rank Math SEO',
			'yoast'     => 'Yoast SEO',
			'both'      => 'Ghi cả hai',
			'none'      => 'Không điền',
		);
	}

	public static function image_sizes() {
		return array( '1536x1024', '1024x1024', '1024x1536' );
	}

	public static function image_qualities() {
		return array( 'low', 'medium', 'high' );
	}

	public static function image_formats() {
		return array( 'webp', 'jpeg', 'png' );
	}

	/**
	 * Cấu hình hiện tại, đã gộp với mặc định.
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		unset( $saved['api_key'] );
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Ghi đè một phần cấu hình. Chỉ nhận khóa có trong defaults().
	 */
	public static function update( array $values ) {
		$current = self::get();
		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, self::defaults() ) ) {
				$current[ $key ] = $value;
			}
		}
		update_option( self::OPTION, $current, false );
		return $current;
	}

	public static function pick( $value, array $allowed, $fallback ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Làm sạch tham số viết bài lấy từ form trang chính.
	 */
	public static function sanitize_writing( array $input ) {
		$d   = self::defaults();
		$out = array();

		$out['category_id']     = absint( $input['category_id'] ?? 0 );
		$out['post_status']     = self::pick( $input['post_status'] ?? '', array( 'publish', 'draft' ), 'publish' );
		$out['post_length']     = min( 6000, max( 200, intval( $input['post_length'] ?? $d['post_length'] ) ) );
		$density                = floatval( $input['keyword_density'] ?? $d['keyword_density'] );
		$out['keyword_density'] = ( $density > 0 && $density <= 20 ) ? round( $density, 1 ) : 3;
		$out['language']        = self::pick( $input['language'] ?? '', array_keys( self::languages() ), 'vi' );
		$out['tone']            = self::pick( $input['tone'] ?? '', array_keys( self::tones() ), 'neutral' );
		$out['article_style']   = self::pick( $input['article_style'] ?? '', array_keys( self::article_styles() ), 'general_guide' );
		$out['seo_plugin']      = self::pick( $input['seo_plugin'] ?? '', array_keys( self::seo_plugins() ), 'auto' );
		$out['seo_focus_mode']  = ( ( $input['seo_focus_mode'] ?? '' ) === 'custom' ) ? 'custom' : 'keyword';
		$out['seo_custom_focus'] = mb_substr( sanitize_text_field( $input['seo_custom_focus'] ?? '' ), 0, 200 );

		return $out;
	}

	/**
	 * Tên model: chỉ cho phép ký tự an toàn khi người dùng nhập tay.
	 */
	public static function sanitize_model( $select, $custom ) {
		$select = sanitize_text_field( (string) $select );
		if ( 'custom' !== $select ) {
			return array_key_exists( $select, self::models() ) ? $select : self::defaults()['model'];
		}
		$custom = preg_replace( '/[^A-Za-z0-9._:\-]/', '', (string) $custom );
		return '' !== $custom ? mb_substr( $custom, 0, 80 ) : self::defaults()['model'];
	}

	public static function sanitize_images( array $input ) {
		$model = preg_replace( '/[^A-Za-z0-9._:\-]/', '', (string) ( $input['image_model'] ?? '' ) );
		return array(
			'image_model'     => '' !== $model ? mb_substr( $model, 0, 80 ) : 'gpt-image-1',
			'image_size'      => self::pick( $input['image_size'] ?? '', self::image_sizes(), '1536x1024' ),
			'image_quality'   => self::pick( $input['image_quality'] ?? '', self::image_qualities(), 'medium' ),
			'image_format'    => self::pick( $input['image_format'] ?? '', self::image_formats(), 'webp' ),
			'inline_prompt'   => mb_substr( sanitize_textarea_field( $input['inline_prompt'] ?? '' ), 0, 4000 ),
			'featured_prompt' => mb_substr( sanitize_textarea_field( $input['featured_prompt'] ?? '' ), 0, 4000 ),
		);
	}

	public static function heading_image_limit() {
		return max( 0, min( 100, intval( get_option( 'abcgs_heading_image_limit', 3 ) ) ) );
	}
}
