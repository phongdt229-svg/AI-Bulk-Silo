<?php
/**
 * License Free / Pro.
 *
 * @var array  $state
 * @var string $serial_masked
 * @var bool   $serial_const
 * @var string $domain
 */

defined( 'ABSPATH' ) || exit;

$edition = $state['edition'] ? $state['edition'] : 'free';
$status  = array(
	'none'    => 'Chưa cấu hình',
	'unknown' => 'Chưa kiểm tra',
	'valid'   => 'Hợp lệ',
	'invalid' => 'Không hợp lệ',
);
?>
<div class="wrap">
	<h1>License</h1>

	<table class="widefat striped" style="max-width:800px;margin:12px 0">
		<tbody>
			<tr><th style="width:200px">Domain</th><td><code><?php echo esc_html( $domain ); ?></code></td></tr>
			<tr><th>Phiên bản</th><td><?php echo esc_html( 'pro' === $state['edition'] ? 'Pro' : ( 'free' === $state['edition'] ? 'Free' : '—' ) ); ?></td></tr>
			<tr><th>Trạng thái</th><td><strong><?php echo esc_html( $status[ $state['status'] ] ?? $state['status'] ); ?></strong></td></tr>
			<tr><th>Hạn mức</th><td><?php echo esc_html( ABCGS_License::summary() ); ?></td></tr>
			<?php if ( $state['checked_at'] ) : ?>
				<tr><th>Kiểm tra lần cuối</th><td><?php echo esc_html( wp_date( 'H:i d/m/Y', $state['checked_at'] ) ); ?></td></tr>
			<?php endif; ?>
			<?php if ( $state['message'] ) : ?>
				<tr><th>Thông báo máy chủ</th><td><?php echo esc_html( $state['message'] ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="abcgs_save_license">
		<?php wp_nonce_field( 'abcgs_save_license' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th>Phiên bản</th>
				<td>
					<label><input type="radio" name="abcgs_edition" value="free" <?php checked( $edition, 'free' ); ?>> <strong>Free</strong> — 1 domain, 1 bài/ngày</label><br>
					<label><input type="radio" name="abcgs_edition" value="pro" <?php checked( $edition, 'pro' ); ?>> <strong>Pro</strong> — số bài theo gói đã mua</label>
					<p class="description">Mua gói Pro tại <a href="https://aibulksilo.com/" target="_blank" rel="noopener">aibulksilo.com</a>: A 30, A 50, A 80 (cá nhân); A 500, A 800, A 1.000 (doanh nghiệp).</p>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_license_email">Email</label></th>
				<td><input type="email" name="abcgs_license_email" id="abcgs_license_email" value="<?php echo esc_attr( $state['email'] ); ?>" class="regular-text" required>
				<p class="description">Free: email đăng ký dùng thử, gắn với 1 domain. Pro: email dùng khi mua gói.</p></td>
			</tr>
			<tr class="abcgs-pro-row">
				<th><label for="abcgs_serial_key">Serial Key (Pro)</label></th>
				<td>
					<?php if ( $serial_const ) : ?>
						<p>Đang dùng serial khai báo bằng hằng số <code>ABCGS_LICENSE_SERIAL</code> (<?php echo esc_html( $serial_masked ); ?>).</p>
					<?php else : ?>
						<input type="password" name="abcgs_serial_key" id="abcgs_serial_key" value="" class="regular-text" autocomplete="off"
							placeholder="<?php echo esc_attr( $serial_masked ? $serial_masked : 'FM-XXXX-XXXX-XXXX' ); ?>">
						<?php if ( $serial_masked ) : ?>
							<p class="description">Đã lưu serial <?php echo esc_html( $serial_masked ); ?>. Để trống để giữ serial hiện tại.</p>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">Lưu và kiểm tra license</button>
			<?php if ( $state['edition'] ) : ?>
				<button type="submit" class="button" name="abcgs_remove_license" value="1" formnovalidate>Xóa license khỏi website</button>
			<?php endif; ?>
		</p>
	</form>
</div>
<script>
(function () {
	var radios = document.querySelectorAll('input[name="abcgs_edition"]');
	var row = document.querySelector('.abcgs-pro-row');
	function sync() {
		var pro = document.querySelector('input[name="abcgs_edition"]:checked');
		row.hidden = !(pro && pro.value === 'pro');
	}
	radios.forEach(function (r) { r.addEventListener('change', sync); });
	sync();
})();
</script>
