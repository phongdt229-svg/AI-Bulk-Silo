<?php
/**
 * Tạo ảnh sau khi viết xong một đợt bài.
 *
 * @var int[] $ids
 * @var int   $limit
 * @var array $tasks
 * @var array $summary
 */

defined( 'ABSPATH' ) || exit;

$post_url = admin_url( 'admin-post.php' );
$errors   = array_slice(
	array_values(
		array_filter(
			$tasks,
			function ( $t ) {
				return 'error' === ( $t['status'] ?? '' );
			}
		)
	),
	-10
);
?>
<div class="wrap">
	<h1>Tạo ảnh sau khi hoàn thành bài viết</h1>
	<p>Việc tạo ảnh chỉ bắt đầu khi bạn xác nhận. Plugin tạo tối đa một ảnh cho mỗi thẻ H2/H3 theo thứ tự xuất hiện, chạy lần lượt qua WP-Cron.</p>

	<?php require ABCGS_DIR . 'admin/views/partials/image-worker.php'; ?>

	<?php foreach ( $errors as $err ) : ?>
		<div class="notice notice-error inline"><p>Bài #<?php echo intval( $err['post_id'] ?? 0 ); ?>: <?php echo esc_html( $err['message'] ?? 'Lỗi không xác định' ); ?></p></div>
	<?php endforeach; ?>

	<?php if ( $tasks ) : ?>
		<details style="margin:12px 0"><summary>Chi tiết hàng đợi hình ảnh</summary>
			<table class="widefat striped">
				<thead><tr><th>Bài</th><th>Loại ảnh</th><th>Vị trí</th><th>Trạng thái</th><th>Thông báo</th></tr></thead>
				<tbody>
				<?php foreach ( $tasks as $task ) : ?>
					<tr>
						<td><?php echo intval( $task['post_id'] ?? 0 ); ?></td>
						<td><?php echo esc_html( 'featured' === ( $task['type'] ?? '' ) ? 'Ảnh đại diện' : 'Ảnh trong bài' ); ?></td>
						<td><?php echo isset( $task['heading_index'] ) ? intval( $task['heading_index'] ) + 1 : '—'; ?></td>
						<td><?php echo esc_html( $task['status'] ?? '' ); ?></td>
						<td><?php echo esc_html( $task['message'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</details>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $post_url ); ?>">
		<input type="hidden" name="action" value="abcgs_after_images">
		<?php wp_nonce_field( 'abcgs_after_images' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="abcgs_heading_image_limit">Số ảnh nội dung / bài</label></th>
				<td><input type="number" name="abcgs_heading_image_limit" id="abcgs_heading_image_limit" min="0" max="100" step="1" value="<?php echo esc_attr( $limit ); ?>" style="width:100px">
				<p class="description">0 = không tạo ảnh nội dung. Ví dụ 3: tạo tối đa 3 ảnh tại 3 thẻ H2/H3 đầu tiên. Bài có ít mục hơn thì tạo ít hơn. Thiết lập được lưu cho lần sau.</p></td>
			</tr>
			<tr>
				<th><label for="abcgs_custom_image_request">Yêu cầu riêng cho ảnh</label></th>
				<td><textarea name="abcgs_custom_image_request" id="abcgs_custom_image_request" rows="4" class="large-text" placeholder="Ví dụ: ảnh phong cách thực tế, bố cục ngang 3:2, tập trung đúng nội dung từng mục..."></textarea>
				<p class="description">Được thêm vào prompt của từng ảnh trong bài.</p></td>
			</tr>
			<tr>
				<th>Ảnh đại diện</th>
				<td><label><input type="checkbox" name="abcgs_after_featured" value="1" checked> Tạo ảnh đại diện cho bài chưa có</label></td>
			</tr>
		</table>

		<h2>Chọn bài viết</h2>
		<?php if ( $ids ) : ?>
			<p><label><input type="checkbox" id="abcgs_all_written" checked> Chọn tất cả</label></p>
			<table class="widefat striped">
				<thead><tr><th style="width:50px">Chọn</th><th>Bài viết</th><th>Ảnh đại diện</th></tr></thead>
				<tbody>
				<?php foreach ( $ids as $pid ) : ?>
					<?php
					if ( ! get_post( $pid ) ) {
						continue;
					}
					?>
					<tr>
						<td><input type="checkbox" class="abcgs-written-check" name="abcgs_written_ids[]" value="<?php echo intval( $pid ); ?>" checked></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></td>
						<td><?php echo has_post_thumbnail( $pid ) ? 'Đã có' : 'Chưa có'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p>Chưa có đợt bài nào vừa hoàn thành.</p>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" name="op" value="start" class="button button-primary button-large">Có – bắt đầu tạo ảnh theo cài đặt</button>
			<button type="submit" name="op" value="decline" class="button">Không tạo ảnh</button>
		</p>

		<h2>Đồng bộ SEO cho bài đã chọn</h2>
		<p>
			<label><input type="checkbox" name="abcgs_seo_overwrite" value="1"> Ghi đè cả trường đã có (mặc định chỉ điền ô trống)</label><br>
			<button type="submit" name="op" value="sync_seo" class="button">Đồng bộ từ khóa Rank Math / Yoast</button>
		</p>
	</form>
</div>
<script>
(function () {
	var all = document.getElementById('abcgs_all_written');
	if (!all) { return; }
	all.addEventListener('change', function () {
		document.querySelectorAll('.abcgs-written-check').forEach(function (el) { el.checked = all.checked; });
	});
})();
</script>
