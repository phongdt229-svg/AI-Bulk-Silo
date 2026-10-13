<?php
/**
 * Khối trạng thái queue ảnh + nút xử lý liên tục bằng AJAX khi WP-Cron chậm.
 * Dùng chung cho trang Tạo ảnh sau khi viết và Quét ảnh.
 *
 * @var array $summary
 */

defined( 'ABSPATH' ) || exit;

$back = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="notice notice-info inline" style="margin:12px 0">
	<p>
		Queue ảnh — chờ: <strong><?php echo intval( $summary['pending'] ); ?></strong>,
		xong: <strong><?php echo intval( $summary['done'] ); ?></strong>,
		lỗi: <strong><?php echo intval( $summary['errors'] ); ?></strong>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 10px">
		<input type="hidden" name="action" value="abcgs_image_tools">
		<input type="hidden" name="abcgs_back" value="<?php echo esc_attr( $back ); ?>">
		<?php wp_nonce_field( 'abcgs_image_tools' ); ?>
		<?php if ( $summary['pending'] ) : ?>
			<button type="button" id="abcgs-run-images" class="button button-primary">Xử lý liên tục tất cả ảnh đang chờ</button>
			<button type="submit" name="op" value="run" class="button">Xử lý ngay 1 ảnh</button>
		<?php endif; ?>
		<?php if ( $summary['done'] || $summary['errors'] ) : ?>
			<button type="submit" name="op" value="clear" class="button">Dọn tác vụ đã xong / lỗi</button>
		<?php endif; ?>
		<span id="abcgs-image-progress" style="margin-left:8px"></span>
	</form>
</div>
<?php if ( $summary['pending'] ) : ?>
<script>
(function () {
	var btn = document.getElementById('abcgs-run-images');
	var out = document.getElementById('abcgs-image-progress');
	if (!btn) { return; }
	var nonce = <?php echo wp_json_encode( wp_create_nonce( 'abcgs_image_worker' ) ); ?>;
	var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var wait = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
	btn.addEventListener('click', async function () {
		btn.disabled = true;
		var remaining = 1;
		while (remaining > 0) {
			try {
				var body = new URLSearchParams({ action: 'abcgs_process_next_image', nonce: nonce });
				var res = await fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' });
				var json = await res.json();
				if (!json.success) { throw new Error((json.data && json.data.message) || 'AJAX thất bại'); }
				var d = json.data;
				remaining = Number(d.pending) || 0;
				out.textContent = 'Xong: ' + d.done + ' | Chờ: ' + remaining + ' | Lỗi: ' + d.errors
					+ (d.busy ? ' | Đang có tiến trình khác xử lý, chờ...' : '')
					+ (d.last_error ? ' | Lỗi gần nhất: ' + d.last_error : '');
				if (d.busy) { await wait(3000); }
			} catch (e) {
				out.textContent = 'Dừng: ' + e.message;
				break;
			}
		}
		btn.disabled = false;
		if (remaining === 0) { location.reload(); }
	});
})();
</script>
<?php endif; ?>
