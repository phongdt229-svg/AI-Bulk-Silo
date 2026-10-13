<?php
/**
 * Cài đặt chung: API key, model, hồ sơ website.
 *
 * @var array  $s
 * @var string $key_masked
 * @var bool   $key_const
 * @var bool   $key_broken
 */

defined( 'ABSPATH' ) || exit;

$models     = ABCGS_Settings::models();
$known      = array_key_exists( $s['model'], $models );
$post_url   = admin_url( 'admin-post.php' );
?>
<div class="wrap">
	<h1>Cài đặt chung</h1>

	<form method="post" action="<?php echo esc_url( $post_url ); ?>">
		<input type="hidden" name="action" value="abcgs_save_general">
		<?php wp_nonce_field( 'abcgs_save_general' ); ?>

		<h2>OpenAI</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="abcgs_api_key">OpenAI API key</label></th>
				<td>
					<?php if ( $key_const ) : ?>
						<p>Đang dùng key khai báo bằng hằng số <code>ABCGS_OPENAI_API_KEY</code> trong <code>wp-config.php</code> (<?php echo esc_html( $key_masked ); ?>).</p>
					<?php else : ?>
						<input type="password" name="abcgs_api_key" id="abcgs_api_key" value="" class="regular-text" autocomplete="new-password"
							placeholder="<?php echo esc_attr( $key_masked ? $key_masked : 'sk-...' ); ?>">
						<p class="description">
							<?php if ( $key_masked ) : ?>
								Đã lưu key <?php echo esc_html( $key_masked ); ?>. Để trống để giữ key hiện tại.
								<label style="margin-left:8px"><input type="checkbox" name="abcgs_delete_key" value="1"> Xóa key</label>
							<?php else : ?>
								Dán API key OpenAI. Key được mã hóa khi lưu và không hiển thị lại.
							<?php endif; ?>
							<br>Khuyến nghị: khai báo <code>define( 'ABCGS_OPENAI_API_KEY', 'sk-...' );</code> trong <code>wp-config.php</code> để không lưu key trong database.
						</p>
						<?php if ( $key_broken ) : ?>
							<p style="color:#b32d2e">Không giải mã được key đã lưu (salt trong wp-config.php đã thay đổi). Vui lòng nhập lại key.</p>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_model_select">Model ChatGPT</label></th>
				<td>
					<select name="abcgs_model_select" id="abcgs_model_select">
						<?php foreach ( $models as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $known ? $s['model'] : '', $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
						<option value="custom" <?php selected( ! $known ); ?>>Model khác (nhập tay)</option>
					</select>
					<p><input type="text" name="abcgs_model_custom" value="<?php echo esc_attr( $known ? '' : $s['model'] ); ?>" class="regular-text" placeholder="Model tùy chỉnh, VD: gpt-4.1-mini"></p>
					<p class="description">GPT-5/GPT-6 dùng Responses API; model khác dùng Chat Completions.</p>
				</td>
			</tr>
		</table>

		<h2>Hồ sơ lĩnh vực website (context cho AI)</h2>
		<p class="description">AI dựa vào hồ sơ này để viết đúng lĩnh vực website. Có thể tự phân tích hoặc chỉnh tay.</p>
		<textarea name="abcgs_site_profile" rows="5" class="large-text" style="max-width:800px"><?php echo esc_textarea( $s['site_profile'] ); ?></textarea>

		<?php submit_button( 'Lưu cài đặt chung' ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( $post_url ); ?>">
		<input type="hidden" name="action" value="abcgs_analyze_site">
		<?php wp_nonce_field( 'abcgs_analyze_site' ); ?>
		<p><button type="submit" class="button">Tự động phân tích bài viết trên website</button>
		<span class="description">Ghi đè ô hồ sơ ở trên bằng chuyên mục và thẻ phổ biến của 30 bài gần nhất.</span></p>
	</form>
</div>
