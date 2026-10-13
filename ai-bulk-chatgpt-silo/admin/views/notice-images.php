<?php
/**
 * Thông báo hỏi tạo ảnh sau khi xong một đợt bài.
 *
 * @var int $count
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-info">
	<p><strong>AI Bulk Silo:</strong> Đã hoàn thành viết <?php echo intval( $count ); ?> bài. Bạn có muốn tạo hình ảnh cho các bài này không?</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 10px">
		<input type="hidden" name="action" value="abcgs_after_images">
		<?php wp_nonce_field( 'abcgs_after_images' ); ?>
		<a class="button button-primary" href="<?php echo esc_url( ABCGS_Admin::url( ABCGS_Admin::PAGE_AFTER ) ); ?>">Có – cài đặt số ảnh và yêu cầu riêng</a>
		<button type="submit" class="button" name="op" value="decline">Không, bỏ qua</button>
	</form>
</div>
