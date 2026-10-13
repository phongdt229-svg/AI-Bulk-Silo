<?php
/**
 * Quét bài thiếu ảnh và xếp ảnh hàng loạt.
 *
 * @var array $results
 * @var array $summary
 * @var int   $max
 */

defined( 'ABSPATH' ) || exit;

$post_url = admin_url( 'admin-post.php' );
$results  = array_values(
	array_filter(
		$results,
		function ( $r ) {
			return get_post( intval( $r['post_id'] ?? 0 ) );
		}
	)
);
$missing_inline   = count( array_filter( array_column( $results, 'missing_inline' ) ) );
$missing_featured = count( array_filter( array_column( $results, 'missing_featured' ) ) );
?>
<div class="wrap">
	<h1>Quét &amp; xử lý hình ảnh hàng loạt</h1>
	<p>Quét tối đa <?php echo intval( $max ); ?> bài mới nhất (đã đăng, nháp, chờ duyệt, hẹn giờ) để tìm bài chưa có ảnh trong nội dung hoặc chưa có ảnh đại diện.</p>

	<?php require ABCGS_DIR . 'admin/views/partials/image-worker.php'; ?>

	<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="margin:16px 0">
		<input type="hidden" name="action" value="abcgs_image_tools">
		<?php wp_nonce_field( 'abcgs_image_tools' ); ?>
		<button class="button button-primary" name="op" value="scan">Quét lại bài viết</button>
	</form>

	<?php if ( ! $results ) : ?>
		<div class="notice notice-warning inline"><p>Chưa có kết quả quét. Bấm “Quét lại bài viết”.</p></div>
	<?php else : ?>
		<p>
			<strong>Tổng bài đã quét:</strong> <?php echo count( $results ); ?> &nbsp;|&nbsp;
			<strong>Thiếu ảnh trong bài:</strong> <?php echo intval( $missing_inline ); ?> &nbsp;|&nbsp;
			<strong>Thiếu ảnh đại diện:</strong> <?php echo intval( $missing_featured ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="abcgs_image_tools">
			<?php wp_nonce_field( 'abcgs_image_tools' ); ?>
			<p>
				<button type="button" class="button" data-abcgs-select="inline">Chọn tất cả thiếu ảnh trong bài</button>
				<button type="button" class="button" data-abcgs-select="featured">Chọn tất cả thiếu ảnh đại diện</button>
				<button type="button" class="button" data-abcgs-select="">Bỏ chọn</button>
			</p>
			<table class="widefat striped">
				<thead><tr><th style="width:40px">Chọn</th><th>Bài viết</th><th>Trạng thái</th><th>Ảnh trong bài</th><th>Ảnh đại diện</th></tr></thead>
				<tbody>
				<?php foreach ( $results as $r ) : ?>
					<?php $pid = intval( $r['post_id'] ); ?>
					<tr data-inline="<?php echo empty( $r['missing_inline'] ) ? '0' : '1'; ?>" data-featured="<?php echo empty( $r['missing_featured'] ) ? '0' : '1'; ?>">
						<td><input type="checkbox" class="abcgs-post-check" name="post_ids[]" value="<?php echo intval( $pid ); ?>"></td>
						<td>
							<strong><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></strong>
							<br><code>ID: <?php echo intval( $pid ); ?></code> · <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener">Xem</a>
						</td>
						<td><?php echo esc_html( get_post_status( $pid ) ); ?></td>
						<td><?php echo empty( $r['missing_inline'] ) ? '<span style="color:#008a20">Đã có</span>' : '<span style="color:#b32d2e;font-weight:600">Chưa có</span>'; ?></td>
						<td><?php echo empty( $r['missing_featured'] ) ? '<span style="color:#008a20">Đã có</span>' : '<span style="color:#b32d2e;font-weight:600">Chưa có</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top:15px">
				<button class="button button-primary" name="op" value="inline">Bước 1: Tạo &amp; chèn ảnh trong bài đã chọn (<?php echo intval( max( 1, ABCGS_Settings::heading_image_limit() ) ); ?> ảnh/bài)</button>
				<button class="button button-primary" name="op" value="featured">Bước 2: Tạo ảnh đại diện cho bài đã chọn</button>
			</p>
		</form>
		<script>
		document.querySelectorAll('[data-abcgs-select]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var type = btn.getAttribute('data-abcgs-select');
				document.querySelectorAll('.abcgs-post-check').forEach(function (c) { c.checked = false; });
				if (!type) { return; }
				document.querySelectorAll('tr[data-' + type + '="1"] .abcgs-post-check').forEach(function (c) { c.checked = true; });
			});
		});
		</script>
	<?php endif; ?>
</div>
