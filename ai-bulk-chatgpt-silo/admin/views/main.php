<?php
/**
 * Trang chính: trạng thái license, form viết bài, bảng queue.
 *
 * @var array  $s
 * @var array  $license
 * @var array  $items
 * @var array  $counts
 * @var string $silo_log
 * @var bool   $has_key
 * @var bool   $running
 */

defined( 'ABSPATH' ) || exit;

$labels   = ABCGS_Queue::status_labels();
$post_url = admin_url( 'admin-post.php' );
$active   = $counts['pending'] + $counts['waiting_quota'];
?>
<div class="wrap">
	<h1>AI Bulk ChatGPT Silo <small style="font-size:12px;color:#646970">v<?php echo esc_html( ABCGS_VERSION ); ?></small></h1>

	<div class="notice <?php echo 'valid' === $license['status'] ? 'notice-success' : 'notice-warning'; ?> inline" style="margin:12px 0">
		<p>
			<strong>License:</strong> <?php echo esc_html( ABCGS_License::summary() ); ?>
			<?php if ( $license['message'] ) : ?>
				<br><?php echo esc_html( $license['message'] ); ?>
			<?php endif; ?>
			<br><a href="<?php echo esc_url( ABCGS_Admin::url( ABCGS_Admin::PAGE_LICENSE ) ); ?>">Quản lý license</a>
			· <a href="https://aibulksilo.com/" target="_blank" rel="noopener">Xem các gói Pro</a>
		</p>
	</div>

	<?php if ( ! $has_key ) : ?>
		<div class="notice notice-error inline"><p>Chưa có OpenAI API key. <a href="<?php echo esc_url( ABCGS_Admin::url( ABCGS_Admin::PAGE_GENERAL ) ); ?>">Nhập ở trang Cài đặt chung</a>.</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $post_url ); ?>">
		<input type="hidden" name="action" value="abcgs_enqueue">
		<?php wp_nonce_field( 'abcgs_enqueue' ); ?>

		<h2>Danh sách từ khóa</h2>
		<p>
			Mỗi dòng là <strong>một từ khóa</strong>. Dòng 1 = <strong>bài trụ cột</strong> (silo page), các dòng sau = bài silo con.
			Plugin tự tạo <strong>link nội bộ silo</strong> khi đợt bài hoàn thành. Tối đa <?php echo intval( ABCGS_Queue::MAX_KEYWORDS_PER_BATCH ); ?> từ khóa mỗi lần.
		</p>
		<textarea name="abcgs_keywords" rows="10" class="large-text" style="max-width:800px" placeholder="Ví dụ:
Đào Tạo SEO Fame Media
Khóa Học SEO Fame Media
Đào Tạo SEO TpHCM Fame Media"></textarea>

		<h2>Tùy chọn bài viết</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="abcgs_category_id">Chuyên mục</label></th>
				<td>
					<?php
					wp_dropdown_categories(
						array(
							'show_option_all' => '— Chọn chuyên mục —',
							'hide_empty'      => false,
							'name'            => 'abcgs_category_id',
							'id'              => 'abcgs_category_id',
							'selected'        => intval( $s['category_id'] ),
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_post_status">Trạng thái bài viết</label></th>
				<td>
					<select name="abcgs_post_status" id="abcgs_post_status">
						<option value="publish" <?php selected( $s['post_status'], 'publish' ); ?>>Đăng ngay (publish)</option>
						<option value="draft" <?php selected( $s['post_status'], 'draft' ); ?>>Lưu nháp (draft)</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_language">Ngôn ngữ</label></th>
				<td>
					<select name="abcgs_language" id="abcgs_language">
						<?php foreach ( ABCGS_Settings::languages() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['language'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_post_length">Độ dài (số từ)</label></th>
				<td><input type="number" name="abcgs_post_length" id="abcgs_post_length" value="<?php echo esc_attr( $s['post_length'] ); ?>" min="200" max="6000" step="100"></td>
			</tr>
			<tr>
				<th><label for="abcgs_keyword_density">Mật độ từ khóa (%)</label></th>
				<td><input type="number" name="abcgs_keyword_density" id="abcgs_keyword_density" value="<?php echo esc_attr( $s['keyword_density'] ); ?>" min="0.5" max="20" step="0.5"></td>
			</tr>
			<tr>
				<th><label for="abcgs_tone">Tone bài viết</label></th>
				<td>
					<select name="abcgs_tone" id="abcgs_tone">
						<?php foreach ( ABCGS_Settings::tones() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['tone'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_article_style">Kiểu bài viết</label></th>
				<td>
					<select name="abcgs_article_style" id="abcgs_article_style">
						<?php foreach ( ABCGS_Settings::article_styles() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['article_style'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<h2>Từ khóa SEO</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="abcgs_seo_plugin">Plugin SEO</label></th>
				<td>
					<select name="abcgs_seo_plugin" id="abcgs_seo_plugin">
						<?php foreach ( ABCGS_Settings::seo_plugins() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['seo_plugin'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="abcgs_seo_focus_mode">Nguồn từ khóa chính</label></th>
				<td>
					<select name="abcgs_seo_focus_mode" id="abcgs_seo_focus_mode">
						<option value="keyword" <?php selected( $s['seo_focus_mode'], 'keyword' ); ?>>Từ khóa đang dùng để viết bài (khuyên dùng)</option>
						<option value="custom" <?php selected( $s['seo_focus_mode'], 'custom' ); ?>>Từ khóa tùy chỉnh cho toàn bộ bài</option>
					</select>
					<p><input class="regular-text" type="text" name="abcgs_seo_custom_focus" value="<?php echo esc_attr( $s['seo_custom_focus'] ); ?>" placeholder="Từ khóa tùy chỉnh (chỉ dùng khi chọn tùy chỉnh)"></p>
					<p class="description">Điền Focus Keyword, SEO Title, Meta Description và URL slug cho bài MỚI. Chỉ điền ô đang trống, không ghi đè chỉnh sửa tay.</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary button-large">Tạo và đăng bài viết (Queue nền)</button>
		</p>
	</form>

	<hr>

	<h2>Trạng thái queue</h2>
	<p>
		<?php foreach ( $labels as $key => $label ) : ?>
			<?php echo esc_html( $label ); ?>: <strong><?php echo intval( $counts[ $key ] ); ?></strong> &nbsp;
		<?php endforeach; ?>
		<?php if ( $running ) : ?>
			<span class="spinner is-active" style="float:none;margin:0 4px"></span> Đang xử lý...
		<?php endif; ?>
	</p>
	<?php if ( '' !== $silo_log ) : ?>
		<p><strong>Silo:</strong> <?php echo esc_html( $silo_log ); ?></p>
	<?php endif; ?>

	<?php if ( $items ) : ?>
		<form method="post" action="<?php echo esc_url( $post_url ); ?>">
			<input type="hidden" name="action" value="abcgs_queue_action">
			<?php wp_nonce_field( 'abcgs_queue_action' ); ?>
			<p>
				<?php if ( $active ) : ?>
					<button class="button" name="op" value="run|">Xử lý ngay 1 bài</button>
					<button class="button" name="op" value="cancel_all|">Hủy tất cả mục đang chờ</button>
				<?php endif; ?>
				<button class="button" name="op" value="clear|">Dọn mục đã xong / đã hủy</button>
			</p>
			<table class="widefat striped">
				<thead>
					<tr><th>#</th><th>Từ khóa</th><th>Bài viết</th><th>Trạng thái</th><th>Ghi chú</th><th>Thao tác</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $items as $i => $item ) : ?>
					<?php
					$status  = $item['status'] ?? 'pending';
					$post_id = intval( $item['post_id'] ?? 0 );
					$id      = (string) ( $item['id'] ?? '' );
					?>
					<tr>
						<td><?php echo intval( $i + 1 ); ?></td>
						<td><?php echo esc_html( $item['keyword'] ?? '' ); ?></td>
						<td>
							<?php if ( $post_id && get_post( $post_id ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">#<?php echo intval( $post_id ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $labels[ $status ] ?? $status ); ?></td>
						<td><?php echo esc_html( $item['content_status'] ?? '' ); ?></td>
						<td>
							<?php if ( in_array( $status, array( 'error', 'cancelled', 'waiting_quota' ), true ) && empty( $post_id ) ) : ?>
								<button class="button button-small" name="op" value="<?php echo esc_attr( 'retry|' . $id ); ?>">Thử lại</button>
							<?php endif; ?>
							<?php if ( in_array( $status, array( 'pending', 'waiting_quota', 'error' ), true ) ) : ?>
								<button class="button button-small" name="op" value="<?php echo esc_attr( 'cancel|' . $id ); ?>">Hủy</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</form>
	<?php else : ?>
		<p>Queue đang trống.</p>
	<?php endif; ?>
</div>
