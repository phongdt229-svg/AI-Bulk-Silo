<?php
/**
 * Cài đặt tạo hình ảnh.
 *
 * @var array $s
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1>Cài đặt tạo hình ảnh</h1>
	<p>Thiết lập dùng chung cho ảnh trong bài, ảnh đại diện và công cụ quét ảnh. Ảnh chỉ được tạo khi bạn xác nhận sau mỗi đợt viết bài hoặc từ trang Quét ảnh.</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="abcgs_save_images">
		<?php wp_nonce_field( 'abcgs_save_images' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="abcgs_image_model">Model tạo ảnh</label></th>
				<td><input type="text" name="abcgs_image_model" id="abcgs_image_model" value="<?php echo esc_attr( $s['image_model'] ); ?>" class="regular-text" placeholder="gpt-image-1">
				<p class="description">Dùng model ảnh mà tài khoản OpenAI của bạn được cấp quyền.</p></td>
			</tr>
			<tr>
				<th>Kích thước / chất lượng / định dạng</th>
				<td>
					<select name="abcgs_image_size">
						<?php foreach ( ABCGS_Settings::image_sizes() as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['image_size'], $v ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="abcgs_image_quality">
						<?php foreach ( ABCGS_Settings::image_qualities() as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['image_quality'], $v ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="abcgs_image_format">
						<?php foreach ( ABCGS_Settings::image_formats() as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['image_format'], $v ); ?>><?php echo esc_html( strtoupper( $v ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Ảnh lớn hơn 1 MB sẽ được nén và thu về rộng 1.200px.</p>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_inline_prompt">Prompt ảnh nội dung</label></th>
				<td><textarea name="abcgs_inline_prompt" id="abcgs_inline_prompt" rows="6" class="large-text code"><?php echo esc_textarea( $s['inline_prompt'] ); ?></textarea>
				<p class="description">Để trống để dùng prompt tự động. Biến: {{title}}, {{keyword}}, {{h2_title}}, {{section_content}}.</p></td>
			</tr>
			<tr>
				<th><label for="abcgs_featured_prompt">Prompt ảnh đại diện</label></th>
				<td><textarea name="abcgs_featured_prompt" id="abcgs_featured_prompt" rows="6" class="large-text code"><?php echo esc_textarea( $s['featured_prompt'] ); ?></textarea>
				<p class="description">Để trống để dùng prompt tự động. Biến: {{title}}, {{keyword}}, {{summary}}.</p></td>
			</tr>
		</table>
		<?php submit_button( 'Lưu cài đặt hình ảnh' ); ?>
	</form>

	<hr>
	<p>
		<a class="button" href="<?php echo esc_url( ABCGS_Admin::url( ABCGS_Admin::PAGE_AFTER ) ); ?>">Tạo ảnh cho bài vừa viết</a>
		<a class="button" href="<?php echo esc_url( ABCGS_Admin::url( ABCGS_Admin::PAGE_SCAN ) ); ?>">Quét ảnh bài viết cũ</a>
	</p>
</div>
