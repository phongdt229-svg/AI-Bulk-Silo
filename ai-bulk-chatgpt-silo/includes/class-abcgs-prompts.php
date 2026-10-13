<?php
/**
 * Prompt viết bài và prompt ảnh. Nội dung quy tắc giữ nguyên v6.1.1, chỉ khác:
 * tiêu đề nằm trong <h1> đầu tiên để plugin tách ra (sửa lỗi lặp tiêu đề).
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_Prompts {

	private static function tone_text( $tone ) {
		$map = array(
			'friendly'     => 'Thân thiện, gần gũi, dễ hiểu, nhưng vẫn chuyên nghiệp.',
			'formal'       => 'Trang trọng, chuyên nghiệp, hạn chế tiếng lóng.',
			'expert'       => 'Chuyên gia, phân tích sâu, có góc nhìn chuyên môn nhưng vẫn dễ hiểu.',
			'promotional'  => 'Quảng cáo nhẹ, nhấn mạnh lợi ích, thúc đẩy hành động, nhưng không hứa hẹn/cam kết.',
			'journalistic' => 'Báo chí, khách quan, cấu trúc rõ ràng như bài tin.',
			'storytelling' => 'Kể chuyện theo dòng tự nhiên, nhưng không kể chuyện cá nhân cụ thể.',
		);
		return $map[ $tone ] ?? 'Trung lập, dễ hiểu, chuyên nghiệp.';
	}

	private static function style_text( $style ) {
		$map = array(
			'news_article'        => 'Phong cách tin tức/cập nhật: mở bài, nội dung chính, kết luận.',
			'product_description' => 'Mô tả sản phẩm/dịch vụ: tính năng, lợi ích, đối tượng phù hợp. Không nêu giá, không cam kết.',
			'product_review'      => 'Đánh giá/review: ưu điểm, hạn chế, khách quan, không cam kết.',
			'comparison'          => 'So sánh: tiêu chí rõ ràng, điểm giống/khác, kết luận theo nhu cầu.',
			'how_to'              => 'How-to: các bước rõ ràng, có tiêu đề con cho từng bước.',
			'listicle'            => 'Listicle: dạng Top X, mỗi mục có giải thích ngắn gọn.',
		);
		return $map[ $style ] ?? 'Giới thiệu tổng quan: dạng blog chia sẻ kiến thức/kinh nghiệm.';
	}

	public static function article( $keyword, array $s, $language ) {
		$length  = max( 200, intval( $s['post_length'] ) );
		$density = floatval( $s['keyword_density'] );
		$tone    = self::tone_text( $s['tone'] );
		$style   = self::style_text( $s['article_style'] );
		$profile = trim( wp_strip_all_tags( (string) $s['site_profile'] ) );

		$context = '';
		if ( '' !== $profile ) {
			$context = 'vi' === $language
				? "Context về website (lĩnh vực, chủ đề chính): {$profile}\nHãy giữ nội dung phù hợp với lĩnh vực và tệp khách hàng của website này.\n"
				: "Website context (topic and audience): {$profile}\nUse this only as background; write the complete article in the requested language.\n";
		}

		if ( 'vi' === $language ) {
			return "Bạn là một copywriter SEO chuyên nghiệp. Hãy viết một bài viết bằng tiếng Việt về từ khóa chính \"{$keyword}\" theo các yêu cầu sau:

{$context}
Giọng viết (tone): {$tone}
Kiểu bài viết: {$style}

1. Tiêu đề:
- Tự tạo một tiêu đề chính KHÔNG trùng hoàn toàn với từ khóa chính.
- Tiêu đề có CHÍNH XÁC 12 từ (đếm theo khoảng trắng).
- Đặt tiêu đề ở đầu bài trong thẻ <h1>...</h1>. Đây là thẻ <h1> duy nhất của bài.

2. Độ dài:
- Nội dung có độ dài là {$length} từ (có thể chênh lệch nhẹ).

3. Quy định bắt buộc:
- KHÔNG dùng dấu gạch ngang '-' trong toàn bộ bài.
- KHÔNG nêu giá sản phẩm.
- KHÔNG kể câu chuyện cá nhân cụ thể.
- KHÔNG đưa ra bất kỳ cam kết hay đảm bảo nào.
- KHÔNG có mục Câu hỏi thường gặp (FAQ).

4. Từ khóa:
- Từ khóa chính: \"{$keyword}\".
- Mật độ từ khóa chính khoảng {$density}% trên toàn bài (ước lượng hợp lý).
- Dùng thêm từ khóa phụ, từ khóa liên quan, semantic keyword và từ khóa ngữ cảnh liên quan tới từ khóa chính.
- Mỗi lần xuất hiện từ khóa chính \"{$keyword}\", in đậm bằng HTML: <strong>{$keyword}</strong>.

5. Định dạng:
- Viết TOÀN BỘ tiêu đề, các thẻ H2/H3 và đoạn văn bằng tiếng Việt có dấu, tự nhiên, dễ đọc. Không tự chuyển sang tiếng Anh dù từ khóa hoặc thông tin website có tiếng Anh.
- Sau tiêu đề <h1>, chỉ dùng các thẻ HTML cơ bản: <h2>, <h3>, <p>, <ul>, <li>.
- KHÔNG chèn script, iframe hay mã nhúng.

Trả về TOÀN BỘ nội dung bài viết dưới dạng HTML (không kèm ``` hoặc ```html):
";
		}

		$lang_name = 'zh' === $language ? 'Simplified Chinese' : 'fluent natural English (not Vietnamese)';
		$title_len = 'zh' === $language ? 'EXACTLY 12 segments separated by spaces' : 'EXACTLY 12 words';
		$related   = 'zh' === $language ? 'latent keywords' : 'ghost keywords';

		return "You are an SEO copywriter. Write the ENTIRE article, including every heading and paragraph, in {$lang_name} about the main keyword \"{$keyword}\" with the following rules:

{$context}
Tone of voice: {$tone}
Article style: {$style}

1. Title:
- Create a unique main title that is NOT exactly the same as the main keyword.
- The title must contain {$title_len}.
- Put the title at the very beginning inside <h1>...</h1>. This is the only <h1> in the article.

2. Length:
- The article length should be around {$length} words (approximate is fine).

3. Strict rules:
- Do NOT use hyphen '-' in the article.
- Do NOT mention any product price.
- Do NOT tell personal stories.
- Do NOT make any promises or guarantees.
- Do NOT include any FAQ section.

4. Keywords:
- Main keyword: \"{$keyword}\".
- Keep the main keyword density around {$density}% (roughly).
- Use related keywords, semantic keywords, and {$related} related to the main keyword.
- Every time the main keyword \"{$keyword}\" appears, wrap it with <strong>{$keyword}</strong>.

5. Formatting:
- After the <h1> title, use only <h2>, <h3>, <p>, <ul>, <li>.
- No scripts, no iframes, no embeds.
- Return FULL HTML only, no code fences.

Return FULL HTML content only:
";
	}

	public static function language_correction( $language ) {
		$name = 'vi' === $language ? 'natural Vietnamese with Vietnamese diacritics' : ( 'en' === $language ? 'natural English' : 'Simplified Chinese' );
		return "\n\nCRITICAL LANGUAGE CORRECTION: The entire article must be written in {$name}. Rewrite ALL headings and paragraphs in that language. Keep the supplied keyword exactly as given. Output HTML only.";
	}

	public static function default_inline_image( $title, $keyword, $heading, $section ) {
		return 'Create one high quality editorial illustration for a WordPress article. '
			. "Main keyword: {$keyword}. Article title: {$title}. "
			. "The image will appear immediately after this section heading: {$heading}. "
			. "Section context: {$section}. "
			. 'Prioritize the specific meaning of this section. Make the scene realistic, relevant, visually clear and professional. '
			. 'Landscape composition. No text, no watermark, no logo, no UI, no borders, no unrelated objects.';
	}

	public static function default_featured_image( $title, $keyword, $summary ) {
		return 'Create a distinct professional featured image for a WordPress article. '
			. "Main keyword: {$keyword}. Article title: {$title}. Article summary: {$summary}. "
			. 'Represent the overall topic accurately. Use a different composition from an inline section illustration. '
			. 'Strong focal subject, realistic editorial style, landscape composition, natural lighting. '
			. 'No text, no watermark, no logo, no UI, no borders.';
	}

	public static function from_template( $template, array $vars, $fallback ) {
		return '' === trim( (string) $template ) ? $fallback : strtr( $template, $vars );
	}

	public static function finalize_image( $prompt, $index = 0 ) {
		$styles = array(
			'Cinematic documentary photograph, dynamic human interaction, natural environment, depth and atmospheric perspective.',
			'Lively editorial lifestyle photograph, candid action, interesting foreground and background layers, rich natural colors.',
			'Professional environmental photography, engaging real-world scene, dramatic but natural side lighting and vivid details.',
			'Immersive wide-angle photographic composition, purposeful action, energetic visual storytelling, varied camera angle.',
			'Beautiful authentic location photography, tactile textures, lively movement, nuanced lighting and realistic materials.',
		);
		$style = $styles[ absint( $index ) % count( $styles ) ];
		return trim( $prompt ) . "\n\nMANDATORY FINAL VISUAL RULES (override any contradictory instructions above): "
			. "Generate a vivid, engaging, photorealistic image, not a poster or infographic. {$style} "
			. 'NO TEXT OF ANY KIND anywhere in the image: no letters, no words, no numbers, no typography, '
			. 'no signage, no captions, no labels, no subtitles, no logos, no watermarks, '
			. 'no interface screens, no diagrams, no banners, no packaging text. '
			. 'Avoid generic flat illustrations, repetitive stock imagery, empty compositions and static scenes. '
			. 'If an object usually has printed writing, depict its blank/unmarked version. '
			. 'Wide horizontal composition, coherent anatomy and natural colors.';
	}
}
