<?php
/*
Plugin Name: AI Bulk ChatGPT Silo
Description: Viết bài hàng loạt, xác nhận tạo ảnh, chọn số lượng ảnh theo H2/H3, ảnh đại diện, queue nền, silo và license.
Version: 6.1.1
Author: Fame Media
Text Domain: ai-bulk-chatgpt-silo
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Bulk_ChatGPT_Silo {

    const OPTION_SETTINGS = 'abcgs_settings';
    const OPTION_QUEUE    = 'abcgs_queue';
    const OPTION_SILO_LOG = 'abcgs_silo_log';
    const OPTION_LICENSE  = 'abcgs_license';
    const OPTION_IMAGE_QUEUE = 'abcgs_image_bulk_queue';
    const OPTION_SCAN_RESULTS = 'abcgs_image_scan_results';

    const CRON_HOOK       = 'abcgs_process_queue_event';
    const IMAGE_CRON_HOOK = 'abcgs_process_image_bulk_queue';
    const INLINE_MARKER   = '<!-- ABCGS_INLINE_IMAGE -->';
    const TARGET_IMAGE_BYTES = 1048576;

    // ✅ License API URL cố định theo yêu cầu
    const LICENSE_API_URL = 'https://famemedia.vn/licenses/license-api.php';

    public function __construct() {
        @set_time_limit( 300 );
        @ini_set( 'max_execution_time', 300 );

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );
        add_action('admin_init', array($this, 'repair_pending_content_queue'));
        add_action('admin_init',array($this,'repair_pending_image_queue'));
        add_action('admin_notices', array($this,'pending_queue_notice'));
        add_action( self::IMAGE_CRON_HOOK, array( $this, 'process_image_bulk_queue' ) );
        add_action('save_post_post',array($this,'persist_seo_focus_on_save'),999,3);
        add_action('wp_ajax_abcgs_process_next_image',array($this,'ajax_process_next_image'));
        add_action('admin_notices', array($this, 'completion_image_notice'));
        add_action('admin_post_abcgs_decline_images', array($this,'decline_images'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'AI Bulk ChatGPT Silo',
            'AI Bulk Silo',
            'manage_options',
            'abcgs-ai-bulk-silo',
            array( $this, 'render_admin_page' ),
            'dashicons-edit',
            27
        );
        add_submenu_page('abcgs-ai-bulk-silo', 'Cài đặt tạo hình ảnh', 'Cài đặt tạo hình ảnh', 'manage_options', 'abcgs-image-settings', array($this,'render_image_settings_page'));
        add_submenu_page(
            'abcgs-ai-bulk-silo',
            'Quét ảnh',
            'Quét ảnh',
            'manage_options',
            'abcgs-image-scan',
            array( $this, 'render_image_scan_page' )
        );
        add_submenu_page(null, 'Tạo ảnh sau khi viết', 'Tạo ảnh sau khi viết', 'manage_options', 'abcgs-after-writing-images', array($this,'render_after_writing_images'));
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['abcgs_start'] ) ) {
            $this->handle_start_queue();
        }

        if ( isset( $_POST['abcgs_analyze_site'] ) ) {
            $this->handle_analyze_site();
        }

        $settings = get_option( self::OPTION_SETTINGS, array(
            'api_key'         => '',
            'model'           => 'gpt-6-luna',
            'category_id'     => 0,
            'keyword_density' => 5,
            'post_status'     => 'publish',
            'post_length'     => 1200,
            'language'        => 'vi',
            'tone'            => 'neutral',
            'article_style'   => 'general_guide',
            'site_profile'    => '',
            'image_model'     => 'gpt-image-1',
            'image_size'      => '1536x1024',
            'image_quality'   => 'medium',
            'image_format'    => 'webp',
            'inline_images'   => 1,
            'featured_images'=> 1,
            'inline_prompt'   => '',
            'featured_prompt'=> '',
        ) );

        $api_key         = $settings['api_key'] ?? '';
        $model           = $settings['model'] ?? 'gpt-4.1-mini';
        $category_id     = intval( $settings['category_id'] ?? 0 );
        $keyword_density = floatval( $settings['keyword_density'] ?? 5 );
        $post_status     = $settings['post_status'] ?? 'publish';
        $post_length     = intval( $settings['post_length'] ?? 1200 );
        $language        = $settings['language'] ?? 'vi';
        $tone            = $settings['tone'] ?? 'neutral';
        $article_style   = $settings['article_style'] ?? 'general_guide';
        $site_profile    = $settings['site_profile'] ?? '';
        $image_model      = $settings['image_model'] ?? 'gpt-image-1';
        $image_size       = $settings['image_size'] ?? '1536x1024';
        $image_quality    = $settings['image_quality'] ?? 'medium';
        $image_format     = $settings['image_format'] ?? 'webp';
        $inline_images    = ! empty( $settings['inline_images'] );
        $featured_images  = ! empty( $settings['featured_images'] );
        $inline_prompt    = $settings['inline_prompt'] ?? '';
        $featured_prompt  = $settings['featured_prompt'] ?? '';
        $seo_plugin = $settings['seo_plugin'] ?? 'both';
        $seo_focus_mode = $settings['seo_focus_mode'] ?? 'keyword';
        $seo_custom_focus = $settings['seo_custom_focus'] ?? '';

        $available_models = array(
            'gpt-6-luna'   => 'GPT-6 Luna – nhanh, tiết kiệm (khuyên dùng viết hàng loạt)',
            'gpt-6.1-sol'  => 'GPT-6.1 Sol – cân bằng chất lượng và chi phí',
            'gpt-6-astra'  => 'GPT-6 Astra – chất lượng cao nhất, chi phí cao',
            'gpt-6-sol'    => 'GPT-6 Sol – phiên bản Sol trước',
            'gpt-5.6-sol'  => 'GPT-5.6 Sol',
            'gpt-5.6-terra' => 'GPT-5.6 Terra',
            'gpt-5.6-luna' => 'GPT-5.6 Luna',
            'gpt-5.5'      => 'GPT-5.5',
            'gpt-5.4'      => 'GPT-5.4',
            'gpt-5.4-mini' => 'GPT-5.4 mini',
            'gpt-4.1-mini'  => 'gpt-4.1-mini (nhanh, tiết kiệm)',
            'gpt-4.1'       => 'gpt-4.1 (chất lượng cao)',
            'gpt-4o-mini'   => 'gpt-4o-mini (tối ưu chi phí)',
            'gpt-4o'        => 'gpt-4o (đa năng cao cấp)',
            'o3-mini'       => 'o3-mini (lý luận mạnh)',
            'gpt-3.5-turbo' => 'gpt-3.5-turbo (đời cũ, giá rẻ)',
        );

        $model_is_known = array_key_exists( $model, $available_models );
        $custom_model   = $model_is_known ? '' : $model;

        $queue      = get_option( self::OPTION_QUEUE, array() );
        $queue_size = is_array( $queue ) ? count( $queue ) : 0;

        $silo_log = get_option( self::OPTION_SILO_LOG, '' );

        $license = get_option( self::OPTION_LICENSE, array(
            'serial'     => '',
            'email'      => '',
            'status'     => 'invalid',
            'max_posts'  => 0,
            'used_posts' => 0,
            'plan'       => '',
        ) );

        $max_posts  = intval( $license['max_posts'] ?? 0 );
        $used_posts = intval( $license['used_posts'] ?? 0 );
        $remain     = max( 0, $max_posts - $used_posts );
        ?>

        <div class="wrap">
            <h1>AI Bulk ChatGPT Silo</h1>

            <?php if ( isset( $_GET['abcgs_message'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html( wp_unslash( $_GET['abcgs_message'] ) ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['abcgs_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( wp_unslash( $_GET['abcgs_error'] ) ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'abcgs_start_queue', 'abcgs_nonce' ); ?>

                <h2>License / Serial key</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="abcgs_serial_key">Seri key</label></th>
                        <td>
                            <input type="text"
                                   name="abcgs_serial_key"
                                   id="abcgs_serial_key"
                                   value="<?php echo esc_attr( $license['serial'] ?? '' ); ?>"
                                   class="regular-text"
                                   placeholder="VD: FM-XXXX-XXXX-XXXX">
                            <br><br>
                            <label for="abcgs_license_email"><strong>Email license</strong></label><br>
                            <input type="email"
                                   name="abcgs_license_email"
                                   id="abcgs_license_email"
                                   value="<?php echo esc_attr( $license['email'] ?? '' ); ?>"
                                   class="regular-text"
                                   placeholder="Email dùng khi mua plugin">

                            <?php
                            $status_text  = 'Chưa kích hoạt';
                            $status_class = 'notice-warning';
                            if ( isset( $license['status'] ) && $license['status'] === 'valid' ) {
                                $status_text  = 'License hợp lệ';
                                $status_class = 'notice-success';
                            } elseif ( isset( $license['status'] ) && $license['status'] === 'invalid' && ! empty( $license['serial'] ) ) {
                                $status_text  = 'License không hợp lệ hoặc đã hết hạn';
                                $status_class = 'notice-error';
                            }
                            ?>
                            <div class="notice <?php echo esc_attr( $status_class ); ?>" style="margin-top:10px;padding:8px;">
                                <p>
                                    Trạng thái license: <strong><?php echo esc_html( $status_text ); ?></strong><br>
                                    Gói: <strong><?php echo esc_html( $license['plan'] ?? '' ); ?></strong><br>
                                    Email license: <strong><?php echo esc_html( $license['email'] ?? '' ); ?></strong><br>
                                    Số bài đã tạo (server): <strong><?php echo esc_html( $used_posts ); ?></strong><br>
                                    Giới hạn tối đa: <strong><?php echo esc_html( $max_posts ); ?></strong><br>
                                    Còn lại (server): <strong><?php echo esc_html( $remain ); ?></strong>
                                </p>
                            </div>
                            <p class="description">
                                License gắn với 1 email + domain + giới hạn số bài (30 / 50 / 80 / 500 / 800 / 1000).
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>Cấu hình API</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="abcgs_api_key">OpenAI API key</label></th>
                        <td>
                            <input type="password"
                                   name="abcgs_api_key"
                                   id="abcgs_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>"
                                   class="regular-text"
                                   placeholder="sk-...">
                            <p class="description">Dán API key OpenAI.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_model_select">Model ChatGPT</label></th>
                        <td>
                            <select name="abcgs_model_select" id="abcgs_model_select">
                                <?php foreach ( $available_models as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $model_is_known ? $model : '', $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom" <?php selected( ! $model_is_known ); ?>>Model khác (nhập tay)</option>
                            </select>
                            <p style="margin-top:8px;">
                                <label for="abcgs_model_custom"><strong>Model tùy chỉnh:</strong></label><br>
                                <input type="text"
                                       name="abcgs_model_custom"
                                       id="abcgs_model_custom"
                                       value="<?php echo esc_attr( $custom_model ); ?>"
                                       class="regular-text"
                                       placeholder="VD: gpt-4.1-mini">
                            </p>
                            <p class="description">Nếu chọn "Model khác" thì sẽ dùng model trong ô này.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_language">Ngôn ngữ bài viết</label></th>
                        <td>
                            <select name="abcgs_language" id="abcgs_language">
                                <option value="vi" <?php selected( $language, 'vi' ); ?>>Tiếng Việt</option>
                                <option value="en" <?php selected( $language, 'en' ); ?>>Tiếng Anh</option>
                                <option value="zh" <?php selected( $language, 'zh' ); ?>>Tiếng Trung (giản thể)</option>
                            </select>
                            <p class="description">Chọn ngôn ngữ bài viết được sinh ra.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_category_id">Chuyên mục</label></th>
                        <td>
                            <?php
                            wp_dropdown_categories( array(
                                'show_option_all' => '— Chọn chuyên mục —',
                                'hide_empty'      => false,
                                'name'            => 'abcgs_category_id',
                                'id'              => 'abcgs_category_id',
                                'selected'        => $category_id,
                                'taxonomy'        => 'category',
                            ) );
                            ?>
                            <p class="description">Tất cả bài viết sẽ đăng vào chuyên mục này.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_post_status">Trạng thái bài viết</label></th>
                        <td>
                            <select name="abcgs_post_status" id="abcgs_post_status">
                                <option value="publish" <?php selected( $post_status, 'publish' ); ?>>Đăng ngay (publish)</option>
                                <option value="draft" <?php selected( $post_status, 'draft' ); ?>>Lưu nháp (draft)</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>Tùy chọn nội dung</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="abcgs_post_length">Độ dài bài viết (số từ)</label></th>
                        <td>
                            <input type="number"
                                   name="abcgs_post_length"
                                   id="abcgs_post_length"
                                   value="<?php echo esc_attr( $post_length ); ?>"
                                   min="200"
                                   step="100">
                            <p class="description">VD: 800, 1200, 2000...</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_keyword_density">Mật độ từ khóa (%)</label></th>
                        <td>
                            <input type="number"
                                   name="abcgs_keyword_density"
                                   id="abcgs_keyword_density"
                                   value="<?php echo esc_attr( $keyword_density ); ?>"
                                   min="1"
                                   max="20"
                                   step="0.5">
                            <p class="description">VD: 3, 3.5, 5 (%).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_tone">Tone bài viết</label></th>
                        <td>
                            <select name="abcgs_tone" id="abcgs_tone">
                                <option value="neutral" <?php selected( $tone, 'neutral' ); ?>>Trung lập</option>
                                <option value="friendly" <?php selected( $tone, 'friendly' ); ?>>Thân thiện</option>
                                <option value="formal" <?php selected( $tone, 'formal' ); ?>>Trang trọng</option>
                                <option value="expert" <?php selected( $tone, 'expert' ); ?>>Chuyên gia</option>
                                <option value="promotional" <?php selected( $tone, 'promotional' ); ?>>Quảng cáo nhẹ</option>
                                <option value="journalistic" <?php selected( $tone, 'journalistic' ); ?>>Báo chí</option>
                                <option value="storytelling" <?php selected( $tone, 'storytelling' ); ?>>Kể chuyện</option>
                            </select>
                            <p class="description">Chọn giọng viết chính cho toàn bộ bài.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_article_style">Kiểu bài viết</label></th>
                        <td>
                            <select name="abcgs_article_style" id="abcgs_article_style">
                                <option value="general_guide" <?php selected( $article_style, 'general_guide' ); ?>>Giới thiệu / hướng dẫn tổng quan</option>
                                <option value="news_article" <?php selected( $article_style, 'news_article' ); ?>>Tin tức / cập nhật</option>
                                <option value="product_description" <?php selected( $article_style, 'product_description' ); ?>>Mô tả sản phẩm</option>
                                <option value="product_review" <?php selected( $article_style, 'product_review' ); ?>>Đánh giá sản phẩm</option>
                                <option value="comparison" <?php selected( $article_style, 'comparison' ); ?>>So sánh</option>
                                <option value="how_to" <?php selected( $article_style, 'how_to' ); ?>>How-to</option>
                                <option value="listicle" <?php selected( $article_style, 'listicle' ); ?>>Bài danh sách (Top X)</option>
                            </select>
                            <p class="description">Giúp AI viết đúng định dạng bài.</p>
                        </td>
                    </tr>
                </table>

                <h2>Hình ảnh AI</h2>
                <p>Các tùy chọn tạo ảnh đã được chuyển sang mục <a href="<?php echo esc_url(admin_url('admin.php?page=abcgs-image-settings')); ?>"><strong>Cài đặt tạo hình ảnh</strong></a>. Thiết lập ảnh được lưu độc lập, không cần khởi chạy hàng đợi bài viết.</p>

                <h2>Hồ sơ lĩnh vực website (context cho AI)</h2>
                <p><em>Plugin sẽ dựa vào hồ sơ này để viết đúng lĩnh vực website. Có thể tự phân tích hoặc chỉnh sửa bằng tay.</em></p>
                <textarea name="abcgs_site_profile" rows="5" style="width:100%;max-width:800px;"><?php echo esc_textarea( $site_profile ); ?></textarea>
                <p class="description">
                    Mô tả nhanh website tập trung vào lĩnh vực gì (VD: nhà hàng, du lịch, vay online...).<br>
                    Nếu muốn plugin tự phân tích các bài đã có, bấm nút bên dưới.
                </p>
                <p>
                    <button type="submit" name="abcgs_analyze_site" class="button">Tự động phân tích bài viết trên website</button>
                </p>

                <hr>

                <h2>Danh sách từ khóa</h2>
                <p>
                    Mỗi dòng là <strong>một từ khóa</strong>.<br>
                    Dòng 1 = <strong>silo page</strong> (bài trụ cột).<br>
                    Các dòng sau = bài silo con.<br>
                    Plugin sẽ tự tạo <strong>link nội bộ silo</strong> khi hoàn thành.
                </p>
                <textarea name="abcgs_keywords" rows="10" style="width:100%;max-width:800px;" placeholder="Ví dụ:
Đào Tạo SEO Fame Media
Khóa Học SEO Fame Media
Đào Tạo SEO TpHCM Fame Media
Khóa học SEO online Fame Media"></textarea>

                <p class="submit">
                    <h3>Cài đặt từ khóa SEO</h3>
                    <p><label>Plugin SEO: <select name="abcgs_seo_plugin">
                    <option value="auto" <?php selected($seo_plugin,'auto'); ?>>Tự phát hiện Rank Math / Yoast</option>
                    <option value="rank_math" <?php selected($seo_plugin,'rank_math'); ?>>Rank Math SEO</option>
                    <option value="yoast" <?php selected($seo_plugin,'yoast'); ?>>Yoast SEO</option>
                    <option value="both" <?php selected($seo_plugin,'both'); ?>>Ghi cả hai (khuyên dùng, tương thích Cron)</option>
                    <option value="none" <?php selected($seo_plugin,'none'); ?>>Không điền</option>
                    </select></label></p>
                    <p><label>Nguồn từ khóa chính: <select name="abcgs_seo_focus_mode">
                    <option value="keyword" <?php selected($seo_focus_mode,'keyword'); ?>>Từ khóa đang dùng để viết bài (khuyên dùng)</option>
                    <option value="custom" <?php selected($seo_focus_mode,'custom'); ?>>Từ khóa tùy chỉnh cho toàn bộ bài</option>
                    </select></label></p>
                    <p><label>Từ khóa tùy chỉnh: <input class="regular-text" type="text" name="abcgs_seo_custom_focus" value="<?php echo esc_attr($seo_custom_focus); ?>" placeholder="Chỉ dùng khi chọn tùy chỉnh"></label></p>
                    <p class="description">Đồng bộ Focus Keyword, SEO Title, Meta Description và tạo URL slug theo từ khóa SEO cho bài MỚI. Không tự đổi URL bài đã đăng để tránh mất liên kết. Điểm xanh còn phụ thuộc nội dung, liên kết, độ dài và các kiểm tra khác của Rank Math.</p>
                    <button type="submit" name="abcgs_start"  class="button button-primary button-large">
                        Tạo và đăng bài viết ngay (Queue nền)
                    </button>
                </p>
            </form>

            <hr>

            <h2>Trạng thái queue</h2>
            <p>
                Số mục trong queue: <strong><?php echo intval( $queue_size ); ?></strong><br>
                <?php
                $pending = 0;
                $done    = 0;
                if ( $queue_size > 0 ) {
                    foreach ( $queue as $item ) {
                        if ( isset( $item['status'] ) && $item['status'] === 'done' ) {
                            $done++;
                        } else {
                            $pending++;
                        }
                    }
                }
                ?>
                Còn đang xử lý: <strong><?php echo intval( $pending ); ?></strong><br>
                Đã xong: <strong><?php echo intval( $done ); ?></strong>
            </p>

            <?php if ( ! empty( $silo_log ) ) : ?>
                <p><strong>Trạng thái silo:</strong> <?php echo esc_html( $silo_log ); ?></p>
            <?php endif; ?>

            <?php if ( $queue_size > 0 ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Từ khóa</th>
                            <th>ID bài viết</th>
                            <th>Trạng thái</th>
                            <th>Công đoạn</th><th>Ảnh nội dung</th><th>Ảnh đại diện</th><th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    foreach ( $queue as $item ) :
                        ?>
                        <tr>
                            <td><?php echo intval( $i ); ?></td>
                            <td><?php echo esc_html( $item['keyword'] ?? '' ); ?></td>
                            <td><?php echo ! empty( $item['post_id'] ) ? intval( $item['post_id'] ) : '-'; ?></td>
                            <td><?php echo esc_html( $item['status'] ?? '' ); ?></td>
                            <td><?php echo esc_html( $item['stage'] ?? '' ); ?></td>
                            <td><?php echo !empty($item['inline_image_id']) ? '✓' : '—'; ?></td>
                            <td><?php echo !empty($item['featured_image_id']) ? '✓' : '—'; ?></td>
                            <td><?php echo esc_html( $item['content_status'] ?? '' ); ?></td>
                        </tr>
                        <?php
                        $i++;
                    endforeach;
                    ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>Chưa có queue nào hoặc đã xử lý xong.</p>
            <?php endif; ?>
        </div>

        <?php
    }

    private function seo_focus_for_post($keyword,$settings) {
        $keyword=trim(sanitize_text_field((string)$keyword));
        $custom=trim(sanitize_text_field((string)($settings['seo_custom_focus']??'')));
        if(($settings['seo_focus_mode']??'keyword')==='custom' && $custom!=='') return $custom;
        return $keyword;
    }

    private function seo_optimized_title($title,$focus) {
        $title=trim(wp_strip_all_tags((string)$title));
        $focus=trim(wp_strip_all_tags((string)$focus));
        if($focus==='') return $title;
        if($title==='') return $focus;
        if(mb_stripos($title,$focus)!==false) return $title;
        // Put the exact phrase at the start for Rank Math's title-keyword check.
        return $focus.' – '.$title;
    }

    private function set_seo_focus_keyword($post_id,$keyword,$settings) {
        $focus=$this->seo_focus_for_post($keyword,$settings);
        if($focus==='') return false;
        if(($settings['seo_plugin']??'both')==='none') return false;
        $post=get_post($post_id);
        if(!$post || $post->post_type!=='post') return false;
        // Rank Math reads this unprefixed post meta; Yoast reads its own underscored key.
        update_post_meta($post_id,'rank_math_focus_keyword',$focus);
        update_post_meta($post_id,'_yoast_wpseo_focuskw',$focus);
        $seo_title=$this->seo_optimized_title($post->post_title,$focus);
        // SEO title metadata does not rewrite an already published post title.
        update_post_meta($post_id,'rank_math_title',$seo_title.' %sep% %sitename%');
        update_post_meta($post_id,'_yoast_wpseo_title',$seo_title.' %%sep%% %%sitename%%');
        $description=trim(wp_strip_all_tags((string)$post->post_excerpt));
        if($description==='') $description=wp_trim_words(wp_strip_all_tags($post->post_content),24,'');
        if(mb_stripos($description,$focus)===false) $description=$focus.'. '.$description;
        $description=mb_substr(trim($description),0,155);
        update_post_meta($post_id,'rank_math_description',$description);
        update_post_meta($post_id,'_yoast_wpseo_metadesc',$description);
        update_post_meta($post_id,'_abcgs_seo_focus_saved',$focus);
        return get_post_meta($post_id,'rank_math_focus_keyword',true)===$focus
            && get_post_meta($post_id,'_yoast_wpseo_focuskw',true)===$focus;
    }

    public function persist_seo_focus_on_save($post_id,$post,$update) {
        if(wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_type!=='post') return;
        $keyword=(string)get_post_meta($post_id,'_abcgs_main_keyword',true);
        if($keyword==='') return;
        $settings=(array)get_option(self::OPTION_SETTINGS,array());
        $this->set_seo_focus_keyword($post_id,$keyword,$settings);
    }

    private function finalize_image_prompt($prompt,$kind='inline',$index=0) {
        $styles=array(
            'Cinematic documentary photograph, dynamic human interaction, natural environment, depth and atmospheric perspective.',
            'Lively editorial lifestyle photograph, candid action, interesting foreground and background layers, rich natural colors.',
            'Professional environmental photography, engaging real-world scene, dramatic but natural side lighting and vivid details.',
            'Immersive wide-angle photographic composition, purposeful action, energetic visual storytelling, varied camera angle.',
            'Beautiful authentic location photography, tactile textures, lively movement, nuanced lighting and realistic materials.'
        );
        $style=$styles[absint($index)%count($styles)];
        return trim($prompt)."\n\nMANDATORY FINAL VISUAL RULES (override any contradictory instructions above): "
            ."Generate a vivid, engaging, photorealistic image, not a poster or infographic. {$style} "
            ."NO TEXT OF ANY KIND anywhere in the image: no letters, no words, no numbers, no typography, "
            ."no signage, no captions, no labels, no subtitles, no logos, no watermarks, "
            ."no interface screens, no diagrams, no banners, no packaging text. "
            ."Avoid generic flat illustrations, repetitive stock imagery, empty compositions and static scenes. "
            ."If an object usually has printed writing, depict its blank/unmarked version. "
            ."Wide horizontal composition, coherent anatomy and natural colors.";
    }

    public function ajax_process_next_image() {
        check_ajax_referer('abcgs_image_worker','nonce');
        if(!current_user_can('manage_options')) wp_send_json_error(array('message'=>'Không có quyền.'),403);
        $this->process_image_bulk_queue();
        $queue=(array)get_option(self::OPTION_IMAGE_QUEUE,array());
        $pending=0;$errors=0;$done=0;$last='';
        foreach($queue as $task) {
            $status=$task['status']??'';
            if($status==='pending') $pending++;
            if($status==='error') {$errors++;$last=$task['message']??'';}
            if($status==='done') $done++;
        }
        wp_send_json_success(array('pending'=>$pending,'errors'=>$errors,'done'=>$done,'last_error'=>$last));
    }

    private function sync_recent_seo_keywords() {
        $settings=(array)get_option(self::OPTION_SETTINGS,array());
        $ids=(array)get_option('abcgs_recent_written_ids',array());
        $count=0;
        foreach($ids as $id) {
            $id=absint($id);
            if(!$id || !get_post($id)) continue;
            $keyword=(string)get_post_meta($id,'_abcgs_main_keyword',true);
            if($keyword==='') continue;
            $before=(string)get_post_meta($id,'rank_math_focus_keyword',true);
            $this->set_seo_focus_keyword($id,$keyword,$settings);
            if($before!==get_post_meta($id,'rank_math_focus_keyword',true)) $count++;
        }
        return $count;
    }

    private function handle_start_queue() {
        if ( ! isset( $_POST['abcgs_nonce'] ) || ! wp_verify_nonce( $_POST['abcgs_nonce'], 'abcgs_start_queue' ) ) {
            wp_die( 'Xác thực không hợp lệ.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Không có quyền.' );
        }

        $api_key          = isset( $_POST['abcgs_api_key'] ) ? sanitize_text_field( $_POST['abcgs_api_key'] ) : '';
        $model_select     = isset( $_POST['abcgs_model_select'] ) ? sanitize_text_field( $_POST['abcgs_model_select'] ) : 'gpt-4.1-mini';
        $model_custom_raw = isset( $_POST['abcgs_model_custom'] ) ? sanitize_text_field( $_POST['abcgs_model_custom'] ) : '';
        $category_id      = isset( $_POST['abcgs_category_id'] ) ? intval( $_POST['abcgs_category_id'] ) : 0;
        $post_status      = isset( $_POST['abcgs_post_status'] ) ? sanitize_text_field( $_POST['abcgs_post_status'] ) : 'publish';
        $post_length      = isset( $_POST['abcgs_post_length'] ) ? intval( $_POST['abcgs_post_length'] ) : 1200;
        $keyword_density  = isset( $_POST['abcgs_keyword_density'] ) ? floatval( $_POST['abcgs_keyword_density'] ) : 5;
        $language         = isset( $_POST['abcgs_language'] ) ? sanitize_text_field( $_POST['abcgs_language'] ) : 'vi';
        $tone             = isset( $_POST['abcgs_tone'] ) ? sanitize_text_field( $_POST['abcgs_tone'] ) : 'neutral';
        $article_style    = isset( $_POST['abcgs_article_style'] ) ? sanitize_text_field( $_POST['abcgs_article_style'] ) : 'general_guide';
        $site_profile     = isset( $_POST['abcgs_site_profile'] ) ? wp_kses_post( wp_unslash( $_POST['abcgs_site_profile'] ) ) : '';
        $saved_image_settings = (array) get_option(self::OPTION_SETTINGS,array());
        $image_model      = isset( $_POST['abcgs_image_model'] ) ? sanitize_text_field( $_POST['abcgs_image_model'] ) : ($saved_image_settings['image_model'] ?? 'gpt-image-1');
        $image_size       = isset( $_POST['abcgs_image_size'] ) ? sanitize_text_field( $_POST['abcgs_image_size'] ) : ($saved_image_settings['image_size'] ?? '1536x1024');
        $image_quality    = isset( $_POST['abcgs_image_quality'] ) ? sanitize_text_field( $_POST['abcgs_image_quality'] ) : ($saved_image_settings['image_quality'] ?? 'medium');
        $image_format     = isset( $_POST['abcgs_image_format'] ) ? sanitize_text_field( $_POST['abcgs_image_format'] ) : ($saved_image_settings['image_format'] ?? 'webp');
        $inline_images = !empty($saved_image_settings['inline_images']) ? 1 : 0;
        $featured_images = !empty($saved_image_settings['featured_images']) ? 1 : 0;
        $inline_prompt    = isset( $_POST['abcgs_inline_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['abcgs_inline_prompt'] ) ) : ($saved_image_settings['inline_prompt'] ?? '');
        $featured_prompt  = isset( $_POST['abcgs_featured_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['abcgs_featured_prompt'] ) ) : ($saved_image_settings['featured_prompt'] ?? '');

        $seo_plugin = isset($_POST['abcgs_seo_plugin']) ? sanitize_key(wp_unslash($_POST['abcgs_seo_plugin'])) : 'auto';
        if(!in_array($seo_plugin,array('auto','rank_math','yoast','both','none'),true)) $seo_plugin='auto';
        $seo_focus_mode = isset($_POST['abcgs_seo_focus_mode']) && $_POST['abcgs_seo_focus_mode']==='custom' ? 'custom' : 'keyword';
        $seo_custom_focus = isset($_POST['abcgs_seo_custom_focus']) ? sanitize_text_field(wp_unslash($_POST['abcgs_seo_custom_focus'])) : '';
        $serial_key       = isset( $_POST['abcgs_serial_key'] ) ? sanitize_text_field( $_POST['abcgs_serial_key'] ) : '';
        $license_email    = isset( $_POST['abcgs_license_email'] ) ? sanitize_email( $_POST['abcgs_license_email'] ) : '';

        if ( $model_select === 'custom' ) {
            $model = ! empty( $model_custom_raw ) ? $model_custom_raw : 'gpt-4.1-mini';
        } else {
            $model = $model_select;
        }

        if ( empty( $api_key ) || strlen( $api_key ) < 20 ) {
            $this->redirect_with_error( 'API key trống hoặc không hợp lệ.' );
        }

        if ( $category_id <= 0 ) {
            $this->redirect_with_error( 'Vui lòng chọn chuyên mục.' );
        }

        if ( $post_length < 200 ) {
            $post_length = 200;
        }

        if ( $keyword_density <= 0 ) {
            $keyword_density = 3;
        }

        if ( ! in_array( $post_status, array( 'publish', 'draft' ), true ) ) {
            $post_status = 'publish';
        }

        if ( ! in_array( $language, array( 'vi', 'en', 'zh' ), true ) ) {
            $language = 'vi';
        }

        if ( empty( $_POST['abcgs_keywords'] ) ) {
            $this->redirect_with_error( 'Vui lòng nhập danh sách từ khóa.' );
        }

        if ( empty( $serial_key ) ) {
            $this->redirect_with_error( 'Vui lòng nhập seri key.' );
        }

        if ( empty( $license_email ) ) {
            $this->redirect_with_error( 'Vui lòng nhập email license.' );
        }

        $raw_keywords = wp_unslash( $_POST['abcgs_keywords'] );
        $lines        = preg_split( '/\r\n|\r|\n/', $raw_keywords );
        $keywords     = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( ! empty( $line ) ) {
                $keywords[] = $line;
            }
        }

        if ( empty( $keywords ) ) {
            $this->redirect_with_error( 'Danh sách từ khóa không hợp lệ.' );
        }

        $license_info  = array();
        $license_error = '';

        if ( ! $this->validate_license_key( $serial_key, $license_email, $license_info, $license_error ) ) {
            update_option( self::OPTION_LICENSE, array(
                'serial'     => $serial_key,
                'email'      => $license_email,
                'status'     => 'invalid',
                'max_posts'  => 0,
                'used_posts' => 0,
                'plan'       => '',
            ) );
            $this->redirect_with_error( 'License không hợp lệ: ' . $license_error );
        }

        $used_posts = intval( $license_info['used_posts'] ?? 0 );
        $max_posts  = intval( $license_info['max_posts'] ?? 0 );
        $plan       = sanitize_text_field( $license_info['plan'] ?? '' );

        if ( $max_posts <= 0 ) {
            $this->redirect_with_error( 'License hợp lệ nhưng max_posts <= 0.' );
        }

        if ( $used_posts >= $max_posts ) {
            update_option( self::OPTION_LICENSE, array(
                'serial'     => $serial_key,
                'email'      => $license_email,
                'status'     => 'valid',
                'max_posts'  => $max_posts,
                'used_posts' => $used_posts,
                'plan'       => $plan,
            ) );
            $this->redirect_with_error( 'Bạn đã dùng hết số bài cho license này.' );
        }

        update_option( self::OPTION_LICENSE, array(
            'serial'     => $serial_key,
            'email'      => $license_email,
            'status'     => 'valid',
            'max_posts'  => $max_posts,
            'used_posts' => $used_posts,
            'plan'       => $plan,
        ) );

        update_option( self::OPTION_SETTINGS, array(
            'api_key'         => $api_key,
            'model'           => $model,
            'category_id'     => $category_id,
            'keyword_density' => $keyword_density,
            'post_status'     => $post_status,
            'post_length'     => $post_length,
            'language'        => $language,
            'tone'            => $tone,
            'article_style'   => $article_style,
            'site_profile'    => $site_profile,
            'image_model'      => $image_model,
            'image_size'       => in_array( $image_size, array('1536x1024','1024x1024','1024x1536'), true ) ? $image_size : '1536x1024',
            'image_quality'    => in_array( $image_quality, array('low','medium','high'), true ) ? $image_quality : 'medium',
            'image_format'     => in_array( $image_format, array('webp','jpeg','png'), true ) ? $image_format : 'webp',
            'inline_images'    => $inline_images,
            'featured_images'  => $featured_images,
            'inline_prompt'    => $inline_prompt,
            'featured_prompt'  => $featured_prompt,
            'seo_plugin'       => $seo_plugin,
            'seo_focus_mode'   => $seo_focus_mode,
            'seo_custom_focus' => $seo_custom_focus,
        ) );

        $queue = array();
        foreach ( $keywords as $kw ) {
            $queue[] = array(
                'keyword'        => $kw,
                'post_id'        => 0,
                'status'         => 'pending',
                'content_status' => '',
                'stage'          => 'content',
                'language'       => $language,
                'inline_image_id'=> 0,
                'featured_image_id'=> 0,
            );
        }

        update_option('abcgs_image_decision_pending', 0, false);
        update_option( self::OPTION_QUEUE, $queue );
        update_option( self::OPTION_SILO_LOG, '' );

        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_schedule_single_event(time()+5,self::CRON_HOOK);

        $this->redirect_with_message( 'Đã xếp hàng ' . count( $queue ) . ' từ khóa. Cron sẽ lần lượt tạo bài và link silo.' );
    }

    private function handle_analyze_site() {
        if ( ! isset( $_POST['abcgs_nonce'] ) || ! wp_verify_nonce( $_POST['abcgs_nonce'], 'abcgs_start_queue' ) ) {
            wp_die( 'Xác thực không hợp lệ.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Không có quyền.' );
        }

        $settings = get_option( self::OPTION_SETTINGS, array() );
        $site_profile = $this->build_site_profile_summary();

        $settings['site_profile'] = $site_profile;
        update_option( self::OPTION_SETTINGS, $settings );

        $this->redirect_with_message( 'Đã phân tích website và cập nhật hồ sơ lĩnh vực website.' );
    }

    private function build_site_profile_summary() {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        );

        $posts = get_posts( $args );
        if ( empty( $posts ) ) {
            return 'Website chưa có bài viết hoặc dữ liệu ít. Chủ đề chính: chưa xác định rõ.';
        }

        $cat_names = array();
        $tag_names = array();

        foreach ( $posts as $pid ) {
            $cats = get_the_category( $pid );
            if ( $cats && ! is_wp_error( $cats ) ) {
                foreach ( $cats as $c ) {
                    $cat_names[] = $c->name;
                }
            }

            $tags = get_the_terms( $pid, 'post_tag' );
            if ( $tags && ! is_wp_error( $tags ) ) {
                foreach ( $tags as $t ) {
                    $tag_names[] = $t->name;
                }
            }
        }

        $top_cats = array();
        if ( ! empty( $cat_names ) ) {
            $counts = array_count_values( $cat_names );
            arsort( $counts );
            $top_cats = array_slice( array_keys( $counts ), 0, 8 );
        }

        $top_tags = array();
        if ( ! empty( $tag_names ) ) {
            $counts = array_count_values( $tag_names );
            arsort( $counts );
            $top_tags = array_slice( array_keys( $counts ), 0, 12 );
        }

        $cat_part  = ! empty( $top_cats ) ? 'Website tập trung vào các chuyên mục: ' . implode( ', ', $top_cats ) . '.' : 'Không xác định rõ chuyên mục chính.';
        $tag_part  = ! empty( $top_tags ) ? ' Từ khóa nổi bật thường gặp: ' . implode( ', ', $top_tags ) . '.' : '';
        $extra     = ' Sử dụng thông tin này làm context để viết bài đúng lĩnh vực của website.';

        return $cat_part . $tag_part . $extra;
    }

    public function repair_pending_content_queue() {
        if (!current_user_can('manage_options')) return;
        $queue=get_option(self::OPTION_QUEUE,array());
        if (!is_array($queue) || !$queue) return;
        $pending=false;
        foreach($queue as $item) {
            if (($item['status']??'')==='pending') {$pending=true;break;}
        }
        if (!$pending) return;
        // WordPress Cron can be disabled or missed on low-traffic websites.
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time()+5,self::CRON_HOOK);
        }
        // Explicit retry button for sites with DISABLE_WP_CRON enabled.
        if (isset($_GET['abcgs_run_pending']) && $_GET['abcgs_run_pending']==='1') {
            check_admin_referer('abcgs_run_pending');
            if (function_exists('fastcgi_finish_request')) {
                // Do not detach here; run synchronously to surface progress.
            }
            $this->process_queue();
            wp_safe_redirect(remove_query_arg(array('abcgs_run_pending','_wpnonce')));
            exit;
        }
    }

    public function pending_queue_notice() {
        if (!current_user_can('manage_options')) return;
        $queue=get_option(self::OPTION_QUEUE,array());
        $count=0;
        foreach((array)$queue as $item) if(($item['status']??'')==='pending') $count++;
        if (!$count) return;
        $url=wp_nonce_url(add_query_arg('abcgs_run_pending','1',admin_url('admin.php?page=abcgs-ai-bulk-silo')),'abcgs_run_pending');
        echo '<div class="notice notice-warning"><p>AI Bulk Silo: còn '.intval($count).' bài Pending. Nếu WP-Cron không chạy, nhấn <a href="'.esc_url($url).'">Xử lý ngay 1 bài</a>. Mỗi lần nhấn xử lý tối đa 1 bài.</p></div>';
    }

    public function process_queue() {
        $queue = get_option( self::OPTION_QUEUE, array() );
        if ( empty( $queue ) || ! is_array( $queue ) ) return;

        $settings = get_option( self::OPTION_SETTINGS, array() );
        $api_key  = $settings['api_key'] ?? '';
        $model    = $settings['model'] ?? 'gpt-4.1-mini';
        if ( empty( $api_key ) || strlen( $api_key ) < 20 ) {
            foreach($queue as $index=>$item) {
                if (($item['status']??'')==='pending') {
                    $queue[$index]['content_status']='Lỗi: API key chưa được cấu hình hoặc không hợp lệ.';
                    $queue[$index]['status']='error';
                }
            }
            update_option(self::OPTION_QUEUE,$queue);
            return;
        }

        $category_id     = intval( $settings['category_id'] ?? 0 );
        $post_status     = $settings['post_status'] ?? 'publish';
        $post_length     = intval( $settings['post_length'] ?? 1200 );
        $keyword_density = floatval( $settings['keyword_density'] ?? 5 );
        $language        = $settings['language'] ?? 'vi';
        $tone            = $settings['tone'] ?? 'neutral';
        $article_style   = $settings['article_style'] ?? 'general_guide';
        $site_profile    = $settings['site_profile'] ?? '';

        $license = get_option( self::OPTION_LICENSE, array() );
        $serial_valid = ( ($license['status'] ?? '') === 'valid' );
        $max_posts  = intval( $license['max_posts'] ?? 0 );
        $used_posts = intval( $license['used_posts'] ?? 0 );

        // Mỗi lần cron chỉ xử lý tối đa 1 công đoạn nặng để giảm timeout.
        $processed = 0;

        foreach ( $queue as $index => $item ) {
            if ( $processed >= 1 ) break;
            if ( in_array(($item['status'] ?? ''),array('done','error'),true) ) continue;

            $keyword = trim( (string) ( $item['keyword'] ?? '' ) );
            $stage   = $item['stage'] ?? ( empty($item['post_id']) ? 'content' : 'inline' );

            if ( $keyword === '' ) {
                $queue[$index]['status'] = 'done';
                $queue[$index]['content_status'] = 'Bỏ qua: từ khóa rỗng.';
                continue;
            }

            if ( $stage === 'content' ) {
                if ( ! $serial_valid ) {
                    $queue[$index]['status'] = 'done';
                    $queue[$index]['content_status'] = 'Dừng: license không hợp lệ hoặc chưa kích hoạt.';
                    continue;
                }
                if ( $used_posts >= $max_posts ) {
                    $queue[$index]['status'] = 'done';
                    $queue[$index]['content_status'] = 'Dừng: đã hết số bài theo license.';
                    continue;
                }

                // Giữ nguyên ngôn ngữ đã chọn khi bắt đầu queue, kể cả khi cài đặt bị đổi sau đó.
                $item_language = $item['language'] ?? $language;
                if (!in_array($item_language,array('vi','en','zh'),true)) $item_language='vi';
                $prompt = $this->build_prompt_for_keyword(
                    $keyword, $post_length, $keyword_density, $item_language, $tone, $article_style, $site_profile
                );
                $queue[$index]['content_status'] = 'Đang gọi API (' . $model . ', ' . strtoupper($item_language) . ')...';
                update_option(self::OPTION_QUEUE,$queue);
                $started_at = microtime(true);
                $error = '';
                $content = $this->call_chatgpt( $api_key, $model, $prompt, $error );
                if ( ! $content ) {
                    $queue[$index]['status'] = 'error';
                    $queue[$index]['content_status'] = 'Lỗi API nội dung (' . round(microtime(true)-$started_at,1) . ' giây): ' . $error;
                    $processed++;
                    continue;
                }

                // Kiểm tra ngôn ngữ trước khi lưu bài; thử tạo lại một lần nếu AI trả sai ngôn ngữ.
                // English: chỉ thử lại khi có bằng chứng rất rõ ràng là nội dung tiếng Việt.
                if (!$this->abcgs_content_matches_language($content,$item_language,$keyword)) {
                    $retry_prompt = $prompt . "\n\nCRITICAL LANGUAGE CORRECTION: The entire article must be written in " .
                        ($item_language==='vi'?'natural Vietnamese with Vietnamese diacritics':($item_language==='en'?'natural English':'Simplified Chinese')) .
                        ". Rewrite ALL headings and paragraphs in that language. Keep the supplied keyword exactly as given. Output HTML only.";
                    $retry_error='';
                    $retry_content=$this->call_chatgpt($api_key,$model,$retry_prompt,$retry_error);
                    if ($retry_content && $this->abcgs_content_matches_language($retry_content,$item_language,$keyword)) {
                        $content=$retry_content;
                    } else {
                        $queue[$index]['status']='error';
                        $queue[$index]['content_status']='Lỗi: AI trả bài sai ngôn ngữ (' . $item_language . ') sau khi thử lại (' . round(microtime(true)-$started_at,1) . ' giây).';
                        $processed++;
                        continue;
                    }
                }
                $content = $this->strip_code_fences( $content );
                $post_title = $this->extract_title_from_content( $content, $keyword );
                $post_title = $this->seo_optimized_title($post_title,$this->seo_focus_for_post($keyword,$settings));
                $content = $this->remove_h1_tags( $content );
                $content = $this->sanitize_ai_html( $content );
                $excerpt = $this->generate_excerpt_from_html( $content, 40 );
                $slug    = $this->build_slug_from_keyword( $this->seo_focus_for_post($keyword,$settings) );

                $post_id = wp_insert_post( array(
                    'post_title'    => $post_title,
                    'post_name'     => $slug,
                    'post_content'  => $content,
                    'post_status'   => in_array( $post_status, array('publish','draft'), true ) ? $post_status : 'publish',
                    'post_author'   => get_current_user_id(),
                    'post_category' => $category_id > 0 ? array($category_id) : array(),
                    'post_excerpt'  => $excerpt,
                ), true );

                if ( is_wp_error($post_id) || ! $post_id ) {
                    $queue[$index]['status'] = 'error';
                    $queue[$index]['content_status'] = 'Lỗi: không tạo được bài viết.';
                    $processed++;
                    continue;
                }

                update_post_meta( $post_id, '_abcgs_main_keyword', $keyword );
                update_post_meta( $post_id, '_abcgs_article_language', $item_language );
                $this->set_seo_focus_keyword($post_id,$keyword,$settings);

                $license = $this->consume_remote_license_usage( $license, 1 );
                $used_posts = intval( $license['used_posts'] ?? $used_posts );
                $max_posts  = intval( $license['max_posts'] ?? $max_posts );
                update_option( self::OPTION_LICENSE, array(
                    'serial' => $license['serial'] ?? '',
                    'email' => $license['email'] ?? '',
                    'status' => 'valid',
                    'max_posts' => $max_posts,
                    'used_posts' => $used_posts,
                    'plan' => $license['plan'] ?? '',
                ) );

                $queue[$index]['post_id'] = $post_id;
                $queue[$index]['stage'] = 'done';
                $queue[$index]['content_status'] = 'OK: đã tạo bài (' . round(microtime(true)-$started_at,1) . ' giây; model: ' . $model . '). Chờ người dùng quyết định tạo ảnh.';
                if ( $queue[$index]['stage'] === 'done' ) $queue[$index]['status'] = 'done';
                $processed++;
                continue;
            }

            $post_id = intval( $item['post_id'] ?? 0 );
            if ( ! $post_id || ! get_post($post_id) ) {
                $queue[$index]['status'] = 'done';
                $queue[$index]['content_status'] = 'Lỗi: không tìm thấy bài để xử lý ảnh.';
                $processed++;
                continue;
            }

            if ( $stage === 'inline' ) {
                $result = $this->generate_inline_image_for_post( $post_id, $keyword, $settings );
                if ( is_wp_error($result) ) {
                    $queue[$index]['content_status'] = 'Lỗi ảnh nội dung: ' . $result->get_error_message();
                    $queue[$index]['status'] = 'done';
                } else {
                    $queue[$index]['inline_image_id'] = intval($result);
                    $queue[$index]['stage'] = !empty($settings['featured_images']) ? 'featured' : 'done';
                    $queue[$index]['content_status'] = 'OK: đã chèn ảnh nội dung.';
                    if ( $queue[$index]['stage'] === 'done' ) $queue[$index]['status'] = 'done';
                }
                $processed++;
                continue;
            }

            if ( $stage === 'featured' ) {
                $result = $this->generate_featured_image_for_post( $post_id, $keyword, $settings );
                if ( is_wp_error($result) ) {
                    $queue[$index]['content_status'] = 'Lỗi ảnh đại diện: ' . $result->get_error_message();
                } else {
                    $queue[$index]['featured_image_id'] = intval($result);
                    $queue[$index]['content_status'] = 'HOÀN TẤT: bài viết + ảnh nội dung + ảnh đại diện.';
                }
                $queue[$index]['stage'] = 'done';
                $queue[$index]['status'] = 'done';
                $processed++;
            }
        }

        update_option( self::OPTION_QUEUE, $queue );

        $pending_left = false;
        foreach ( $queue as $it ) {
            if ( ($it['status'] ?? '') === 'pending' ) { $pending_left = true; break; }
        }

        if ( $pending_left ) {
            if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
                wp_schedule_single_event( time() + 5, self::CRON_HOOK );
            }
            return;
        }

        if (!get_option('abcgs_image_decision_pending', false)) {
            $finished_ids = array();
            foreach ($queue as $q) if (!empty($q['post_id'])) $finished_ids[] = intval($q['post_id']);
            if ($finished_ids) {
                update_option('abcgs_recent_written_ids', array_values(array_unique($finished_ids)), false);
                update_option('abcgs_image_decision_pending', 1, false);
            }
        }
        // Sau khi viết xong, silo vẫn được liên kết; ảnh sẽ do người dùng chọn sau.

        $post_ids = array();
        $keywords = array();
        foreach ( $queue as $it ) {
            if ( ! empty($it['post_id']) ) {
                $post_ids[] = intval($it['post_id']);
                $keywords[] = $it['keyword'] ?? '';
            }
        }
        if ( count($post_ids) >= 2 ) {
            $silo_error = '';
            $ok = $this->apply_silo_internal_links( $post_ids, $keywords, $silo_error );
            update_option( self::OPTION_SILO_LOG, $ok ? 'OK: đã gắn link silo cho ' . count($post_ids) . ' bài.' : 'Lỗi khi gắn silo: ' . $silo_error );
        } else {
            update_option( self::OPTION_SILO_LOG, 'Không đủ bài để tạo silo (tối thiểu 2 bài).' );
        }
    }

    private function abcgs_content_matches_language($html,$language,$keyword='') {
        $plain=trim(wp_strip_all_tags((string)$html));
        if (mb_strlen($plain,'UTF-8') < 100) return true;
        // Từ khóa có thể là tiếng Việt ngay cả khi yêu cầu bài tiếng Anh.
        if ($keyword!=='') $plain=str_ireplace($keyword,' ',$plain);
        $sample=mb_substr($plain,0,4500,'UTF-8');
        if ($language==='zh') return (bool)preg_match('/[\\x{4E00}-\\x{9FFF}]/u',$sample);
        $vi_count=preg_match_all('/[ăâđêôơưĂÂĐÊÔƠƯàáảãạằắẳẵặầấẩẫậèéẻẽẹềếểễệìíỉĩịòóỏõọồốổỗộờớởỡợùúủũụừứửữựỳýỷỹỵ]/u',$sample);
        if ($language==='vi') return $vi_count>=3;
        if ($language==='en') {
            // Chỉ thử lại khi nội dung có dấu hiệu RÕ RỆT viết bằng tiếng Việt.
            // Không gọi API lần hai chỉ vì từ khóa/địa danh có dấu hoặc thiếu stopword.
            $english_words=preg_match_all('/\\b(the|and|with|for|your|this|that|from|are|can|how|what|why|which|you|will|should|have|has|is|in|of|to)\\b/i',$sample);
            return !($vi_count>=30 && $vi_count>($english_words*2));
        }
        return true;
    }

    private function build_prompt_for_keyword( $keyword, $post_length, $density, $language, $tone, $article_style, $site_profile ) {
        $post_length     = max( 200, intval( $post_length ) );
        $keyword_density = floatval( $density );

        $tone_text = '';
        switch ( $tone ) {
            case 'friendly':
                $tone_text = 'Thân thiện, gần gũi, dễ hiểu, nhưng vẫn chuyên nghiệp.';
                break;
            case 'formal':
                $tone_text = 'Trang trọng, chuyên nghiệp, hạn chế tiếng lóng.';
                break;
            case 'expert':
                $tone_text = 'Chuyên gia, phân tích sâu, có góc nhìn chuyên môn nhưng vẫn dễ hiểu.';
                break;
            case 'promotional':
                $tone_text = 'Quảng cáo nhẹ, nhấn mạnh lợi ích, thúc đẩy hành động, nhưng không hứa hẹn/cam kết.';
                break;
            case 'journalistic':
                $tone_text = 'Báo chí, khách quan, cấu trúc rõ ràng như bài tin.';
                break;
            case 'storytelling':
                $tone_text = 'Kể chuyện theo dòng tự nhiên, nhưng không kể chuyện cá nhân cụ thể.';
                break;
            default:
                $tone_text = 'Trung lập, dễ hiểu, chuyên nghiệp.';
                break;
        }

        $style_text = '';
        switch ( $article_style ) {
            case 'news_article':
                $style_text = 'Phong cách tin tức/cập nhật: mở bài, nội dung chính, kết luận.';
                break;
            case 'product_description':
                $style_text = 'Mô tả sản phẩm/dịch vụ: tính năng, lợi ích, đối tượng phù hợp. Không nêu giá, không cam kết.';
                break;
            case 'product_review':
                $style_text = 'Đánh giá/review: ưu điểm, hạn chế, khách quan, không cam kết.';
                break;
            case 'comparison':
                $style_text = 'So sánh: tiêu chí rõ ràng, điểm giống/khác, kết luận theo nhu cầu.';
                break;
            case 'how_to':
                $style_text = 'How-to: các bước rõ ràng, có tiêu đề con cho từng bước.';
                break;
            case 'listicle':
                $style_text = 'Listicle: dạng Top X, mỗi mục có giải thích ngắn gọn.';
                break;
            default:
                $style_text = 'Giới thiệu tổng quan: dạng blog chia sẻ kiến thức/kinh nghiệm.';
                break;
        }

        $context_part = '';
        if ( ! empty( $site_profile ) ) {
            $context_part = ($language==='en'
                ? "Website context (topic and audience): " . $site_profile . "\nUse this only as background; write the complete article in English.\n"
                : "Context về website (lĩnh vực, chủ đề chính): " . $site_profile . "\\nHãy giữ nội dung phù hợp với lĩnh vực và tệp khách hàng của website này.\\n");
        }

        // ✅ Yêu cầu bỏ thẻ H1: title sẽ là dòng đầu tiên dạng văn bản, plugin tự đặt post_title
        if ( $language === 'vi' ) {
            return "
Bạn là một copywriter SEO chuyên nghiệp. Hãy viết một bài viết bằng tiếng Việt về từ khóa chính \"{$keyword}\" theo các yêu cầu sau:

{$context_part}
Giọng viết (tone): {$tone_text}
Kiểu bài viết: {$style_text}

1. Tiêu đề:
- Tự tạo một tiêu đề chính KHÔNG trùng hoàn toàn với từ khóa chính.
- Tiêu đề có CHÍNH XÁC 12 từ (đếm theo khoảng trắng).

2. Độ dài:
- Nội dung có độ dài là {$post_length} từ (có thể chênh lệch nhẹ).

3. Quy định bắt buộc:
- KHÔNG dùng dấu gạch ngang '-' trong toàn bộ bài.
- KHÔNG nêu giá sản phẩm.
- KHÔNG kể câu chuyện cá nhân cụ thể.
- KHÔNG đưa ra bất kỳ cam kết hay đảm bảo nào.
- KHÔNG có mục Câu hỏi thường gặp (FAQ).

4. Từ khóa:
- Từ khóa chính: \"{$keyword}\".
- Mật độ từ khóa chính khoảng {$keyword_density}% trên toàn bài (ước lượng hợp lý).
- Dùng thêm từ khóa phụ, từ khóa liên quan, semantic keyword và từ khóa ngữ cảnh liên quan tới từ khóa chính.
- Mỗi lần xuất hiện từ khóa chính \"{$keyword}\", in đậm bằng HTML: <strong>{$keyword}</strong>.

5. Định dạng:
- Viết TOÀN BỘ tiêu đề, các thẻ H2/H3 và đoạn văn bằng tiếng Việt có dấu, tự nhiên, dễ đọc. Không tự chuyển sang tiếng Anh dù từ khóa hoặc thông tin website có tiếng Anh.
- Chỉ dùng các thẻ HTML cơ bản: <h2>, <h3>, <p>, <ul>, <li>.
- KHÔNG dùng <h1>.
- KHÔNG chèn script, iframe hay mã nhúng.

Trả về TOÀN BỘ nội dung bài viết dưới dạng HTML (không kèm ``` hoặc ```html):
";
        }

        if ( $language === 'zh' ) {
            return "
You are an SEO copywriter. Write an informational article in Simplified Chinese about the main keyword \"{$keyword}\" with the following rules:

{$context_part}
Tone of voice: {$tone_text}
Article style: {$style_text}

1. Title:
- Create a unique main title that is NOT exactly the same as the main keyword.
- The title must contain EXACTLY 12 segments separated by spaces.

2. Length:
- The article length should be around {$post_length} words (approximate is fine).

3. Strict rules:
- Do NOT use hyphen '-' in the article.
- Do NOT mention any product price.
- Do NOT tell personal stories.
- Do NOT make any promises or guarantees.
- Do NOT include any FAQ section.

4. Keywords:
- Main keyword: \"{$keyword}\".
- Keep the main keyword density around {$keyword_density}% (roughly).
- Use related keywords, semantic keywords, and latent keywords related to the main keyword.
- Every time the main keyword \"{$keyword}\" appears, wrap it with <strong>{$keyword}</strong>.

5. Formatting:
- Use only <h2>, <h3>, <p>, <ul>, <li>.
- Do NOT use <h1>.
- No scripts, no iframes, no embeds.
- Return FULL HTML only, no code fences.

Return FULL HTML content only:
";
        }

        return "
You are an SEO copywriter. Write the ENTIRE article, including every heading and paragraph, in fluent natural English (not Vietnamese) about the main keyword \"{$keyword}\" with the following rules:

{$context_part}
Tone of voice: {$tone_text}
Article style: {$style_text}

1. Title:
- Create a unique main title that is NOT exactly the same as the main keyword.
- The title must contain EXACTLY 12 words.

2. Length:
- The article length should be around {$post_length} words (approximate is fine).

3. Strict rules:
- Do NOT use hyphen '-' in the article.
- Do NOT mention any product price.
- Do NOT tell personal stories.
- Do NOT make any promises or guarantees.
- Do NOT include any FAQ section.

4. Keywords:
- Main keyword: \"{$keyword}\".
- Keep the main keyword density around {$keyword_density}% (roughly).
- Use related keywords, semantic keywords, and ghost keywords related to the main keyword.
- Every time the main keyword \"{$keyword}\" appears, wrap it with <strong>{$keyword}</strong>.

5. Formatting:
- Use only <h2>, <h3>, <p>, <ul>, <li>.
- Do NOT use <h1>.
- No scripts, no iframes, no embeds.
- Return FULL HTML only, no code fences.

Return FULL HTML content only:
";
    }

    private function call_chatgpt( $api_key, $model, $prompt, &$error_message = '' ) {
        $error_message = '';

        // GPT-5/6 dùng Responses API, tránh tham số temperature không được hỗ trợ.
        $use_responses = (bool) preg_match('/^gpt-(5|6)(?:[.\\-]|$)/i', $model);
        if ($use_responses) {
            $response = wp_remote_post(
                'https://api.openai.com/v1/responses',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ),
                    'body' => wp_json_encode(array(
                        'model' => $model,
                        'instructions' => 'You are a strict SEO content writer. Follow instructions exactly. Return only HTML without code fences.',
                        'input' => $prompt,
                        'reasoning' => array('effort' => 'low'),
                    )),
                    'timeout' => 120,
                )
            );
            if (is_wp_error($response)) {
                $error_message=$response->get_error_message();
                return false;
            }
            $code=wp_remote_retrieve_response_code($response);
            $body=wp_remote_retrieve_body($response);
            if ($code < 200 || $code >= 300) {
                $error_message='HTTP '.$code.' | '.substr($body,0,500);
                return false;
            }
            $data=json_decode($body,true);
            if (!is_array($data)) {
                $error_message='Phản hồi JSON không hợp lệ.';
                return false;
            }
            $parts=array();
            foreach (($data['output'] ?? array()) as $item) {
                if (($item['type'] ?? '') !== 'message') continue;
                foreach (($item['content'] ?? array()) as $part) {
                    if (($part['type'] ?? '') === 'output_text' && isset($part['text'])) $parts[]=$part['text'];
                }
            }
            $result=trim(implode("\n",$parts));
            if ($result==='') {
                $error_message='Responses API không trả về văn bản (status: '.($data['status'] ?? 'unknown').').';
                return false;
            }
            return $result;
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'model'    => $model,
                    'messages' => array(
                        array(
                            'role'    => 'system',
                            'content' => 'You are a strict SEO content writer. Follow instructions exactly. Return only HTML without code fences.',
                        ),
                        array(
                            'role'    => 'user',
                            'content' => $prompt,
                        ),
                    ),
                    'temperature' => 0.7,
                ) ),
                'timeout' => 90,
            )
        );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            $error_message = 'HTTP ' . $code . ' | ' . substr( $body, 0, 300 );
            return false;
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $error_message = 'JSON error: ' . json_last_error_msg();
            return false;
        }

        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            $error_message = 'API không trả về content.';
            return false;
        }

        return $data['choices'][0]['message']['content'];
    }

    private function strip_code_fences( $content ) {
        $content = trim( $content );
        // Bỏ ```...``` và ```html...```
        $content = preg_replace( '/^```[a-zA-Z0-9]*\s*/', '', $content );
        $content = preg_replace( '/\s*```$/', '', $content );
        return trim( $content );
    }

    private function remove_h1_tags( $content ) {
        // Nếu AI lỡ chèn <h1> thì chuyển thành <h2>
        $content = preg_replace( '/<\s*h1\b([^>]*)>/i', '<h2$1>', $content );
        $content = preg_replace( '/<\s*\/\s*h1\s*>/i', '</h2>', $content );
        return $content;
    }

    private function extract_title_from_content( $content, $fallback ) {
        // Ưu tiên: dòng đầu tiên (plain text) trước thẻ HTML
        $text = trim( $content );
        $text_no_tags = wp_strip_all_tags( $text );
        $lines = preg_split( '/\r\n|\r|\n/', $text_no_tags );
        if ( ! empty( $lines[0] ) ) {
            $maybe = trim( preg_replace( '/\s+/', ' ', $lines[0] ) );
            if ( mb_strlen( $maybe ) >= 10 ) {
                return $maybe;
            }
        }

        // Nếu không có, thử lấy H2 đầu tiên
        if ( preg_match( '/<h2[^>]*>(.*?)<\/h2>/is', $content, $m ) ) {
            $clean = wp_strip_all_tags( $m[1] );
            $clean = trim( preg_replace( '/\s+/', ' ', $clean ) );
            if ( ! empty( $clean ) ) {
                return $clean;
            }
        }

        return $fallback;
    }

    private function generate_excerpt_from_html( $html, $word_limit = 40 ) {
        $text = wp_strip_all_tags( $html );
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );
        if ( empty( $text ) ) {
            return '';
        }
        $words = explode( ' ', $text );
        if ( count( $words ) <= $word_limit ) {
            return $text;
        }
        return implode( ' ', array_slice( $words, 0, $word_limit ) ) . '...';
    }

    private function abcgs_silo_label_for_language( $language ) {
        $labels = array(
            'vi' => 'Liên kết nội bộ cùng chủ đề:',
            'en' => 'Related internal links:',
            'zh' => '相关内部链接：',
        );
        return $labels[$language] ?? $labels['vi'];
    }

    private function apply_silo_internal_links( $post_ids, $keywords, &$error_message = '' ) {
        $error_message = '';

        if ( empty( $post_ids ) || count( $post_ids ) < 2 ) {
            $error_message = 'Không đủ bài để tạo silo.';
            return false;
        }

        $count_posts    = count( $post_ids );
        $count_keywords = count( $keywords );
        $count          = min( $count_posts, $count_keywords );

        $silo_post_id = $post_ids[0];
        $silo_url     = get_permalink( $silo_post_id );
        $silo_anchor  = $keywords[0] ?? get_the_title( $silo_post_id );

        if ( ! $silo_url ) {
            $error_message = 'Không lấy được permalink silo page.';
            return false;
        }

        for ( $i = 0; $i < $count; $i++ ) {
            $post_id = $post_ids[ $i ];
            $content = get_post_field( 'post_content', $post_id );
            if ( empty( $content ) ) {
                continue;
            }

            $links = array();

            if ( $i === 0 ) {
                if ( $count > 1 ) {
                    $next_post_id = $post_ids[1];
                    $next_url     = get_permalink( $next_post_id );
                    $next_anchor  = $keywords[1] ?? get_the_title( $next_post_id );
                    if ( $next_url ) {
                        $links[] = sprintf( '<a href="%s">%s</a>', esc_url( $next_url ), esc_html( $next_anchor ) );
                    }
                }
            } else {
                if ( $i + 1 < $count ) {
                    $next_post_id = $post_ids[ $i + 1 ];
                    $next_url     = get_permalink( $next_post_id );
                    $next_anchor  = $keywords[ $i + 1 ] ?? get_the_title( $next_post_id );
                    if ( $next_url ) {
                        $links[] = sprintf( '<a href="%s">%s</a>', esc_url( $next_url ), esc_html( $next_anchor ) );
                    }
                }
                $links[] = sprintf( '<a href="%s">%s</a>', esc_url( $silo_url ), esc_html( $silo_anchor ) );
            }

            if ( empty( $links ) ) {
                continue;
            }

            // Ưu tiên ngôn ngữ đã lưu riêng cho từng bài; bài cũ dùng cài đặt ngôn ngữ hiện tại.
            $article_language = get_post_meta( $post_id, '_abcgs_article_language', true );
            if ( ! in_array( $article_language, array('vi','en','zh'), true ) ) {
                $current_settings = get_option( self::OPTION_SETTINGS, array() );
                $article_language = $current_settings['language'] ?? 'vi';
            }
            $silo_label = $this->abcgs_silo_label_for_language( $article_language );
            $links_html = '<div class="silo-links"><p><strong>' . esc_html( $silo_label ) . '</strong> ' .
                implode( ' | ', $links ) .
                '</p></div>';

            // Tránh thêm nhiều khối silo khi tác vụ được chạy lại.
            $new_content = preg_replace( '~\s*<div\s+class=["\']silo-links["\'][^>]*>.*?</div>~is', '', $content );
            $new_content .= "\n\n" . $links_html;

            $updated = wp_update_post( array(
                'ID'           => $post_id,
                'post_content' => $new_content,
            ), true );

            if ( is_wp_error( $updated ) ) {
                $error_message = 'Lỗi update bài ' . $post_id . ': ' . $updated->get_error_message();
            }
        }

        return true;
    }


    private function build_slug_from_keyword( $keyword ) {
        $slug = sanitize_title( trim( (string) $keyword ) );
        return $slug ?: 'bai-viet';
    }

    private function sanitize_ai_html( $html ) {
        $allowed = array(
            'h2'=>array(), 'h3'=>array(), 'h4'=>array(), 'p'=>array(),
            'ul'=>array(), 'ol'=>array(), 'li'=>array(), 'strong'=>array(),
            'em'=>array(), 'br'=>array(),
            'a'=>array('href'=>true,'title'=>true,'target'=>true,'rel'=>true),
            'blockquote'=>array(),
        );
        return trim( wp_kses( (string)$html, $allowed ) );
    }

    private function get_second_h2_context( $content ) {
        preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>/is', $content, $titles );
        preg_match_all( '/<h2\b[^>]*>.*?<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE );
        if ( count($matches[0]) < 2 ) return array('', '');
        $h2 = html_entity_decode( wp_strip_all_tags($titles[1][1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $start = $matches[0][1][1] + strlen($matches[0][1][0]);
        $end = isset($matches[0][2]) ? $matches[0][2][1] : strlen($content);
        $section = substr($content, $start, max(0, $end - $start));
        $section = html_entity_decode( wp_strip_all_tags( strip_shortcodes($section) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $section = trim( preg_replace('/\s+/u', ' ', $section) );
        return array( trim($h2), mb_substr($section, 0, 1800) );
    }

    private function default_inline_image_prompt( $title, $keyword, $h2, $section ) {
        return "Create one high quality editorial illustration for a WordPress article. "
            . "Main keyword: {$keyword}. Article title: {$title}. "
            . "The image will appear immediately after this H2 section: {$h2}. "
            . "Section context: {$section}. "
            . "Prioritize the specific meaning of this section. Make the scene realistic, relevant, visually clear and professional. "
            . "Landscape composition. No text, no watermark, no logo, no UI, no borders, no unrelated objects.";
    }

    private function default_featured_image_prompt( $title, $keyword, $summary ) {
        return "Create a distinct professional featured image for a WordPress article. "
            . "Main keyword: {$keyword}. Article title: {$title}. Article summary: {$summary}. "
            . "Represent the overall topic accurately. Use a different composition from an inline section illustration. "
            . "Strong focal subject, realistic editorial style, landscape composition, natural lighting. "
            . "No text, no watermark, no logo, no UI, no borders.";
    }

    private function build_image_prompt_from_template( $template, $vars, $fallback ) {
        if ( trim((string)$template) === '' ) return $fallback;
        return strtr( $template, $vars );
    }

    // ALT nằm trong thẻ img; caption hiển thị dưới ảnh qua figcaption.
    private function abcgs_image_text($post_id,$heading) {
        $language=get_post_meta($post_id,'_abcgs_article_language',true);
        if (!in_array($language,array('vi','en','zh'),true)) {
            $options=get_option(self::OPTION_SETTINGS,array());
            $language=$options['language'] ?? 'vi';
        }
        $heading=trim(wp_strip_all_tags((string)$heading));
        $heading=preg_replace('/\s+/u',' ',$heading);
        $heading=mb_substr($heading,0,150,'UTF-8');
        $prefix=$language==='en'?'Illustration: ':($language==='zh'?'配图：':'Hình minh họa: ');
        return array('alt'=>$heading,'caption'=>$prefix.$heading);
    }

    private function abcgs_image_figure($attachment_id,$url,$alt,$caption) {
        return '<figure class="wp-block-image abcgs-image-with-caption">'
            .'<img src="'.esc_url($url).'" alt="'.esc_attr($alt).'" class="wp-image-'.absint($attachment_id).'" loading="lazy"/>'
            .'<figcaption class="wp-element-caption">'.esc_html($caption).'</figcaption>'
            .'</figure>';
    }

    private function abcgs_set_attachment_text($attachment_id,$alt,$caption) {
        update_post_meta($attachment_id,'_wp_attachment_image_alt',sanitize_text_field($alt));
        wp_update_post(array('ID'=>absint($attachment_id),'post_excerpt'=>sanitize_text_field($caption)));
    }

    private function generate_inline_image_for_post( $post_id, $keyword, $settings, $image_index = 1, $custom_request = '' ) {
        $post = get_post($post_id);
        if (!$post) return new WP_Error('abcgs_post_missing','Không tìm thấy bài viết.');
        $image_index = max(1, intval($image_index));
        $marker = '<!-- ABCGS_INLINE_IMAGE_' . $image_index . ' -->';
        if (strpos($post->post_content,$marker)!==false) return true;

        $content = (string)$post->post_content;
        preg_match_all('/<h2\b[^>]*>.*?<\/h2>/is',$content,$headings,PREG_OFFSET_CAPTURE);
        $h2_count = count($headings[0]);
        $title = wp_strip_all_tags(get_the_title($post_id));
        $h2 = $title;
        $section = wp_trim_words(wp_strip_all_tags($content),120,'');
        $pos = strlen($content);
        if ($h2_count) {
            $idx = min($h2_count-1,$image_index);
            $match = $headings[0][$idx];
            $h2 = trim(wp_strip_all_tags($match[0]));
            $pos = $match[1] + strlen($match[0]);
            $next = isset($headings[0][$idx+1]) ? $headings[0][$idx+1][1] : strlen($content);
            $section = wp_trim_words(wp_strip_all_tags(substr($content,$pos,max(0,$next-$pos))),120,'');
        }

        $fallback = $this->default_inline_image_prompt($title,$keyword,$h2,$section);
        $prompt = $this->build_image_prompt_from_template(
            $settings['inline_prompt'] ?? '',
            array('{{title}}'=>$title,'{{keyword}}'=>$keyword,'{{h2_title}}'=>$h2,'{{section_content}}'=>$section),
            $fallback
        );
        if(trim($custom_request)!=='') $prompt .= "\nAdditional requirements: ".sanitize_textarea_field($custom_request);
        $prompt=$this->finalize_image_prompt($prompt,'inline',$image_index);
        $attach_id = $this->generate_image_attachment($post_id,$prompt,$title.' '.$h2,$settings,'inline-'.$image_index);
        if(is_wp_error($attach_id)) return $attach_id;
        $url=wp_get_attachment_url($attach_id);
        if(!$url) return new WP_Error('abcgs_image_url','Không lấy được URL ảnh đã tạo.');
        $image_text=$this->abcgs_image_text($post_id,$h2);
        $this->abcgs_set_attachment_text($attach_id,$image_text['alt'],$image_text['caption']);
        $block=$marker."\n".$this->abcgs_image_figure($attach_id,$url,$image_text['alt'],$image_text['caption']);
        $new_content=substr($content,0,$pos)."\n\n".$block."\n\n".substr($content,$pos);
        $updated=wp_update_post(array('ID'=>$post_id,'post_content'=>$new_content),true);
        if(is_wp_error($updated)) return $updated;
        update_post_meta($post_id,'_abcgs_inline_image_id_'.$image_index,intval($attach_id));
        return intval($attach_id);
    }


    private function generate_heading_image_for_post($post_id,$keyword,$settings,$heading_index,$custom_request='') {
        $post=get_post($post_id);
        if(!$post) return new WP_Error('abcgs_missing_post','Bài viết không tồn tại.');
        $content=(string)$post->post_content;
        $heading_index=absint($heading_index);
        $marker='<!-- ABCGS_HEADING_IMAGE_'.$heading_index.' -->';
        if(strpos($content,$marker)!==false) return true;
        preg_match_all('/<h([23])\b[^>]*>.*?<\/h\1>/is',$content,$headings,PREG_OFFSET_CAPTURE);
        if(!isset($headings[0][$heading_index])) return new WP_Error('abcgs_missing_heading','Không tìm thấy H2/H3 ở vị trí đã chọn.');
        $heading=$headings[0][$heading_index][0];
        $position=$headings[0][$heading_index][1]+strlen($heading);
        $next=isset($headings[0][$heading_index+1])?$headings[0][$heading_index+1][1]:strlen($content);
        $section=wp_trim_words(wp_strip_all_tags(substr($content,$position,max(0,$next-$position))),150,'');
        $heading_title=trim(wp_strip_all_tags($heading));
        $title=wp_strip_all_tags(get_the_title($post_id));
        $fallback=$this->default_inline_image_prompt($title,$keyword,$heading_title,$section);
        $prompt=$this->build_image_prompt_from_template(
            $settings['inline_prompt']??'',
            array('{{title}}'=>$title,'{{keyword}}'=>$keyword,'{{h2_title}}'=>$heading_title,'{{section_content}}'=>$section),
            $fallback
        );
        if(trim($custom_request)!=='') $prompt.="\nAdditional requirements: ".sanitize_textarea_field($custom_request);
        $prompt=$this->finalize_image_prompt($prompt,'heading',$heading_index);
        $attachment=$this->generate_image_attachment($post_id,$prompt,$title.' '.$heading_title,$settings,'heading-'.$heading_index);
        if(is_wp_error($attachment)) return $attachment;
        $url=wp_get_attachment_url($attachment);
        if(!$url) return new WP_Error('abcgs_image_url','Không lấy được URL ảnh.');
        $image_text=$this->abcgs_image_text($post_id,$heading_title);
        $this->abcgs_set_attachment_text($attachment,$image_text['alt'],$image_text['caption']);
        $block=$marker."\n".$this->abcgs_image_figure($attachment,$url,$image_text['alt'],$image_text['caption']);
        $new_content=substr($content,0,$position)."\n\n".$block."\n\n".substr($content,$position);
        $result=wp_update_post(array('ID'=>$post_id,'post_content'=>$new_content),true);
        if(is_wp_error($result)) return $result;
        update_post_meta($post_id,'_abcgs_heading_image_'.$heading_index,intval($attachment));
        return intval($attachment);
    }

    private function generate_featured_image_for_post( $post_id, $keyword, $settings ) {
        if ( has_post_thumbnail($post_id) ) return intval(get_post_thumbnail_id($post_id));
        $post = get_post($post_id);
        if ( ! $post ) return new WP_Error('abcgs_post_missing', 'Không tìm thấy bài viết.');

        $title = wp_strip_all_tags(get_the_title($post_id));
        $summary = wp_trim_words( wp_strip_all_tags($post->post_content), 90, '' );
        $fallback = $this->default_featured_image_prompt($title, $keyword, $summary);
        $prompt = $this->build_image_prompt_from_template(
            $settings['featured_prompt'] ?? '',
            array('{{title}}'=>$title,'{{keyword}}'=>$keyword,'{{summary}}'=>$summary),
            $fallback
        );

        $prompt=$this->finalize_image_prompt($prompt,'featured',7);
        $attach_id = $this->generate_image_attachment($post_id, $prompt, $title, $settings, 'featured');
        if ( is_wp_error($attach_id) ) return $attach_id;
        if ( ! set_post_thumbnail($post_id, $attach_id) ) {
            return new WP_Error('abcgs_thumbnail', 'Đã tạo ảnh nhưng không đặt được ảnh đại diện.');
        }
        update_post_meta($post_id, '_abcgs_featured_image_id', intval($attach_id));
        return intval($attach_id);
    }

    private function generate_image_attachment( $post_id, $prompt, $alt, $settings, $kind = 'image' ) {
        $api_key = trim((string)($settings['api_key'] ?? ''));
        if ( $api_key === '' ) return new WP_Error('abcgs_no_api_key', 'Chưa có OpenAI API key.');

        $format = in_array(($settings['image_format'] ?? 'webp'), array('webp','jpeg','png'), true) ? $settings['image_format'] : 'webp';
        $payload = array(
            'model' => $settings['image_model'] ?? 'gpt-image-1',
            'prompt' => $prompt,
            'size' => $settings['image_size'] ?? '1536x1024',
            'quality' => $settings['image_quality'] ?? 'medium',
            'n' => 1,
            'output_format' => $format,
        );
        if ( in_array($format, array('webp','jpeg'), true) ) $payload['output_compression'] = 82;

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
            'timeout' => 180,
            'headers' => array('Authorization'=>'Bearer '.$api_key,'Content-Type'=>'application/json'),
            'body' => wp_json_encode($payload),
            'data_format' => 'body',
        ));
        if ( is_wp_error($response) ) return new WP_Error('abcgs_image_http', 'Không kết nối được OpenAI: '.$response->get_error_message());

        $http = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);
        if ( $http < 200 || $http >= 300 ) {
            return new WP_Error('abcgs_image_api', sanitize_text_field($json['error']['message'] ?? ('OpenAI HTTP '.$http)));
        }

        $item = $json['data'][0] ?? array();
        $bytes = false;
        if ( ! empty($item['b64_json']) ) $bytes = base64_decode($item['b64_json'], true);
        elseif ( ! empty($item['url']) ) {
            $img = wp_remote_get(esc_url_raw($item['url']), array('timeout'=>120));
            if ( ! is_wp_error($img) && wp_remote_retrieve_response_code($img) === 200 ) $bytes = wp_remote_retrieve_body($img);
        }
        if ( ! $bytes || strlen($bytes) < 1000 ) return new WP_Error('abcgs_image_empty', 'API không trả về ảnh hợp lệ.');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $ext = $format === 'jpeg' ? 'jpg' : $format;
        $filename = sanitize_file_name(sanitize_title(get_the_title($post_id)) . '-ai-' . $kind . '-' . time() . '.' . $ext);
        $upload = wp_upload_bits($filename, null, $bytes);
        if ( ! empty($upload['error']) ) return new WP_Error('abcgs_upload', $upload['error']);

        $compressed = $this->compress_image_under_limit($upload['file'], $format, self::TARGET_IMAGE_BYTES);
        if ( is_wp_error($compressed) ) {
            @unlink($upload['file']);
            return $compressed;
        }

        $mime = wp_check_filetype($upload['file']);
        $attach_id = wp_insert_attachment(array(
            'post_mime_type' => $mime['type'] ?: ('image/' . ($format === 'jpeg' ? 'jpeg' : $format)),
            'post_title' => sanitize_text_field($alt),
            'post_status' => 'inherit',
        ), $upload['file'], $post_id, true);
        if ( is_wp_error($attach_id) ) return $attach_id;

        $metadata = wp_generate_attachment_metadata($attach_id, $upload['file']);
        if ( $metadata ) wp_update_attachment_metadata($attach_id, $metadata);
        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
        // Caption mặc định cho cả ảnh đại diện trong Thư viện Media.
        $text=$this->abcgs_image_text($post_id,$alt);
        wp_update_post(array('ID'=>$attach_id,'post_excerpt'=>sanitize_text_field($text['caption'])));
        return intval($attach_id);
    }

    private function compress_image_under_limit( $file, $format, $limit ) {
        if (!is_file($file)) return new WP_Error('abcgs_file_missing','Không tìm thấy file ảnh.');
        clearstatcache(true,$file);
        if (filesize($file)<$limit) return true;
        require_once ABSPATH.'wp-admin/includes/image.php';
        $ext = $format==='jpeg' ? 'jpg' : $format;
        $mime = $format==='jpeg' ? 'image/jpeg' : 'image/'.$format;
        foreach(array(85,75,65,55,45,35,25) as $quality) {
            $editor=wp_get_image_editor($file);
            if(is_wp_error($editor)) return $editor;
            $editor->set_quality($quality);
            $size=$editor->get_size();
            if(!empty($size['width']) && $size['width']>1200) $editor->resize(1200,null,false);
            $temp=dirname($file).'/.abcgs-'.wp_generate_password(10,false,false).'.'.$ext;
            $saved=$editor->save($temp,$mime);
            if(is_wp_error($saved)) continue;
            $candidate=$saved['path']??$temp;
            if(!is_file($candidate)) continue;
            clearstatcache(true,$candidate);
            $small=filesize($candidate)<$limit;
            if($small && @copy($candidate,$file)) {
                @unlink($candidate);
                return true;
            }
            @unlink($candidate);
        }
        return new WP_Error('abcgs_too_large','Không thể nén ảnh xuống dưới 1 MB. Kiểm tra GD/Imagick và quyền ghi uploads.');
    }

    private function validate_license_key( $serial_key, $license_email, &$license_info, &$error_message = '' ) {
        $error_message = '';
        $license_info  = array();

        $serial_key    = trim( $serial_key );
        $license_email = trim( $license_email );

        if ( empty( $serial_key ) ) {
            $error_message = 'Seri key trống.';
            return false;
        }
        if ( empty( $license_email ) ) {
            $error_message = 'Email license trống.';
            return false;
        }

        $site_url = site_url();
$host = parse_url( $site_url, PHP_URL_HOST );
if ( ! $host ) {
    $host = $site_url;
}
// Chuẩn hoá domain: bỏ www, bỏ port, lowercase
$domain = strtolower( preg_replace( '/^www\./i', '', preg_replace( '/:\\d+$/', '', (string) $host ) ) );

$response = wp_remote_post(
            self::LICENSE_API_URL,
            array(
                'timeout' => 25,
                'body'    => array(
                    'action'   => 'validate',
                    'serial'   => $serial_key,
                    'email'    => $license_email,
                    'domain'   => $domain,
                    'site_url' => $site_url,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $error_message = 'Không kết nối được license server: ' . $response->get_error_message();
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            $error_message = 'License server HTTP ' . $code . ' | ' . substr( $body, 0, 200 );
            return false;
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $error_message = 'Không parse được JSON: ' . json_last_error_msg();
            return false;
        }

        
if ( empty( $data['status'] ) || $data['status'] !== 'ok' ) {
    $code_text = ! empty( $data['code'] ) ? sanitize_text_field( $data['code'] ) : 'LICENSE_INVALID';
    $msg       = ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : 'License không hợp lệ.';

    // Thêm thông tin domain/email để debug nhanh (nếu server có trả về)
    $extra = '';
    if ( ! empty( $data['expected_domain'] ) || ! empty( $data['received_domain'] ) ) {
        $extra .= ' | expected_domain=' . (string) ( $data['expected_domain'] ?? '' ) . ' | received_domain=' . (string) ( $data['received_domain'] ?? '' );
    }
    if ( ! empty( $data['expected_email'] ) || ! empty( $data['received_email'] ) ) {
        $extra .= ' | expected_email=' . (string) ( $data['expected_email'] ?? '' ) . ' | received_email=' . (string) ( $data['received_email'] ?? '' );
    }

    $error_message = 'License bị từ chối [' . $code_text . ']: ' . $msg . $extra;
    return false;
}

        $max_posts  = isset( $data['max_posts'] ) ? intval( $data['max_posts'] ) : 0;
        $used_posts = isset( $data['used_posts'] ) ? intval( $data['used_posts'] ) : 0;
        $plan       = isset( $data['plan'] ) ? sanitize_text_field( $data['plan'] ) : '';

        if ( $max_posts <= 0 ) {
            $error_message = 'License hợp lệ nhưng max_posts <= 0.';
            return false;
        }

        $license_info = array(
            'max_posts'  => $max_posts,
            'used_posts' => $used_posts,
            'plan'       => $plan,
        );

        return true;
    }

    private function consume_remote_license_usage( $license, $count = 1 ) {
        $serial = $license['serial'] ?? '';
        $email  = $license['email'] ?? '';

        if ( empty( $serial ) || empty( $email ) ) {
            return $license;
        }

        $site_url = site_url();
$host = parse_url( $site_url, PHP_URL_HOST );
if ( ! $host ) {
    $host = $site_url;
}
// Chuẩn hoá domain: bỏ www, bỏ port, lowercase
$domain = strtolower( preg_replace( '/^www\./i', '', preg_replace( '/:\\d+$/', '', (string) $host ) ) );

$response = wp_remote_post(
            self::LICENSE_API_URL,
            array(
                'timeout' => 25,
                'body'    => array(
                    'action'   => 'consume',
                    'serial'   => $serial,
                    'email'    => $email,
                    'domain'   => $domain,
                    'site_url' => $site_url,
                    'count'    => max( 1, intval( $count ) ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $license;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code < 200 || $code >= 300 ) {
            return $license;
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return $license;
        }

        if ( isset( $data['status'] ) && $data['status'] === 'ok' ) {
            $license['used_posts'] = intval( $data['used_posts'] ?? ( $license['used_posts'] ?? 0 ) );
            $license['max_posts']  = intval( $data['max_posts']  ?? ( $license['max_posts']  ?? 0 ) );
            $license['plan']       = sanitize_text_field( $data['plan'] ?? ( $license['plan'] ?? '' ) );
        }

        return $license;
    }



    public function decline_images() {
        if(!current_user_can('manage_options')) wp_die('Không có quyền.');
        check_admin_referer('abcgs_decline_images');
        update_option('abcgs_image_decision_pending',0,false);
        wp_safe_redirect(admin_url('admin.php?page=abcgs-ai-bulk-silo'));
        exit;
    }

    public function completion_image_notice() {
        if (!current_user_can('manage_options') || !get_option('abcgs_image_decision_pending',false)) return;
        $ids=(array)get_option('abcgs_recent_written_ids',array());
        $url=admin_url('admin.php?page=abcgs-after-writing-images');
        echo '<div class="notice notice-info"><p><strong>AI Bulk Silo:</strong> Đã hoàn thành viết '.intval(count($ids)).' bài. Bạn có muốn tạo hình ảnh cho các bài này không?</p><p><a class="button button-primary" href="'.esc_url($url).'">Có – cài đặt số ảnh và yêu cầu riêng</a> <a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=abcgs_decline_images'),'abcgs_decline_images')).'">Không, bỏ qua</a></p></div>';
    }

    public function render_image_settings_page() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['abcgs_save_image_settings'])) {
            check_admin_referer('abcgs_save_image_settings');
            $settings=(array)get_option(self::OPTION_SETTINGS,array());
            $settings['image_model']=sanitize_text_field(wp_unslash($_POST['abcgs_image_model'] ?? 'gpt-image-1'));
            $size=sanitize_text_field(wp_unslash($_POST['abcgs_image_size'] ?? '1536x1024'));
            $quality=sanitize_text_field(wp_unslash($_POST['abcgs_image_quality'] ?? 'medium'));
            $format=sanitize_text_field(wp_unslash($_POST['abcgs_image_format'] ?? 'webp'));
            $settings['image_size']=in_array($size,array('1536x1024','1024x1024','1024x1536'),true)?$size:'1536x1024';
            $settings['image_quality']=in_array($quality,array('low','medium','high'),true)?$quality:'medium';
            $settings['image_format']=in_array($format,array('webp','jpeg','png'),true)?$format:'webp';
            $settings['inline_images']=!empty($_POST['abcgs_inline_images'])?1:0;
            $settings['featured_images']=!empty($_POST['abcgs_featured_images'])?1:0;
            $settings['inline_prompt']=sanitize_textarea_field(wp_unslash($_POST['abcgs_inline_prompt'] ?? ''));
            $settings['featured_prompt']=sanitize_textarea_field(wp_unslash($_POST['abcgs_featured_prompt'] ?? ''));
            update_option(self::OPTION_SETTINGS,$settings);
            echo '<div class="notice notice-success"><p>Đã lưu cài đặt hình ảnh. Không ảnh hưởng hàng đợi bài viết.</p></div>';
        }
        $settings=(array)get_option(self::OPTION_SETTINGS,array());
        $image_model=$settings['image_model'] ?? 'gpt-image-1';
        $image_size=$settings['image_size'] ?? '1536x1024';
        $image_quality=$settings['image_quality'] ?? 'medium';
        $image_format=$settings['image_format'] ?? 'webp';
        $inline_images=!empty($settings['inline_images']);
        $featured_images=!empty($settings['featured_images']);
        $inline_prompt=$settings['inline_prompt'] ?? '';
        $featured_prompt=$settings['featured_prompt'] ?? '';
        ?>
        <div class="wrap"><h1>Cài đặt tạo hình ảnh</h1>
        <p>Thiết lập dùng chung cho ảnh trong bài, ảnh đại diện và công cụ quét ảnh.</p>
        <form method="post"><?php wp_nonce_field('abcgs_save_image_settings'); ?>
                <h2>Cấu hình hình ảnh AI</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="abcgs_image_model">Model tạo ảnh</label></th>
                        <td><input type="text" name="abcgs_image_model" id="abcgs_image_model" value="<?php echo esc_attr( $image_model ); ?>" class="regular-text" placeholder="gpt-image-1">
                        <p class="description">Dùng model ảnh mà tài khoản OpenAI của bạn được cấp quyền.</p></td>
                    </tr>
                    <tr>
                        <th>Kích thước / chất lượng</th>
                        <td>
                            <select name="abcgs_image_size">
                                <?php foreach ( array('1536x1024','1024x1024','1024x1536') as $v ) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected($image_size,$v); ?>><?php echo esc_html($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="abcgs_image_quality">
                                <?php foreach ( array('low','medium','high') as $v ) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected($image_quality,$v); ?>><?php echo esc_html($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="abcgs_image_format">
                                <?php foreach ( array('webp','jpeg','png') as $v ) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected($image_format,$v); ?>><?php echo esc_html(strtoupper($v)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Tự động tạo ảnh</th>
                        <td>
                            <label><input type="checkbox" name="abcgs_inline_images" value="1" <?php checked($inline_images); ?>> Tạo ảnh nội dung sau khi bài đã được tạo, chèn sau H2 thứ 2</label><br>
                            <label><input type="checkbox" name="abcgs_featured_images" value="1" <?php checked($featured_images); ?>> Sau khi ảnh nội dung hoàn tất, tạo ảnh đại diện riêng</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_inline_prompt">Prompt ảnh nội dung</label></th>
                        <td><textarea name="abcgs_inline_prompt" id="abcgs_inline_prompt" rows="6" class="large-text code"><?php echo esc_textarea($inline_prompt); ?></textarea>
                        <p class="description">Để trống để dùng prompt tự động theo tiêu đề, H2 thứ 2 và nội dung của mục đó. Biến: {{title}}, {{keyword}}, {{h2_title}}, {{section_content}}.</p></td>
                    </tr>
                    <tr>
                        <th><label for="abcgs_featured_prompt">Prompt ảnh đại diện</label></th>
                        <td><textarea name="abcgs_featured_prompt" id="abcgs_featured_prompt" rows="6" class="large-text code"><?php echo esc_textarea($featured_prompt); ?></textarea>
                        <p class="description">Để trống để dùng prompt tự động theo tiêu đề, từ khóa chính và tóm tắt bài. Biến: {{title}}, {{keyword}}, {{summary}}.</p></td>
                    </tr>
                </table>

        <p><button class="button button-primary" name="abcgs_save_image_settings" value="1" type="submit">Lưu cài đặt hình ảnh</button></p>
        </form>
        <hr><h2>Tạo ảnh cho bài vừa viết</h2>
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=abcgs-after-writing-images')); ?>">Chọn số ảnh H2/H3 và tạo ảnh cho bài vừa viết</a></p>
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=abcgs-image-scan')); ?>">Quét ảnh bài viết cũ</a></p>
        </div>
        <?php
    }

    public function render_after_writing_images() {
        if(!current_user_can('manage_options')) return;
        if(isset($_POST['abcgs_start_after_images'])) $this->handle_after_writing_images();
        if(isset($_POST['abcgs_sync_seo'])) {
            check_admin_referer('abcgs_after_images');
            $synced=$this->sync_recent_seo_keywords();
            echo '<div class="notice notice-success"><p>Đã đồng bộ SEO cho danh sách bài gần nhất. Cập nhật Rank Math: '.intval($synced).' bài.</p></div>';
        }
        if(isset($_POST['abcgs_decline_after_images'])) {
            check_admin_referer('abcgs_after_images');
            update_option('abcgs_image_decision_pending',0,false);
            echo '<div class="notice notice-success"><p>Đã bỏ qua tạo ảnh. Bài viết được giữ nguyên.</p></div>';
        }
        $ids=(array)get_option('abcgs_recent_written_ids',array());
        $saved_limit=max(0,min(100,intval(get_option('abcgs_heading_image_limit',3))));
        $queue=(array)get_option(self::OPTION_IMAGE_QUEUE,array());
        $pending=0;
        foreach($queue as $task) if(($task['status']??'')==='pending') $pending++;
        $errors=array_slice(array_values(array_filter($queue,function($t){return ($t['status']??'')==='error';})), -10);
        ?>
        <div class="wrap"><h1>Tạo ảnh sau khi hoàn thành bài viết</h1>
        <p>Việc tạo ảnh chỉ bắt đầu khi bạn xác nhận. Bạn chọn số ảnh tối đa trong mỗi bài. Plugin ưu tiên các thẻ H2/H3 theo thứ tự xuất hiện và tạo tối đa một ảnh cho mỗi mục; hệ thống chạy qua WP-Cron.</p>
        <?php if($pending): ?><div class="notice notice-info"><p>Đang chờ xử lý <?php echo intval($pending); ?> ảnh. <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('abcgs_run_image','1',admin_url('admin.php?page=abcgs-after-writing-images')),'abcgs_run_image')); ?>">Xử lý ngay 1 ảnh</a> nếu WP-Cron bị chậm.</p></div><?php endif; ?>
        <?php if($pending): ?>
        <p><button type="button" id="abcgs-run-images" class="button button-primary">Tiếp tục xử lý tất cả ảnh đang Pending</button> <span id="abcgs-image-progress"></span></p>
        <script>
        (function(){
            var btn=document.getElementById('abcgs-run-images');if(!btn)return;
            var output=document.getElementById('abcgs-image-progress');var running=false;
            btn.addEventListener('click',async function(){
                if(running)return;running=true;btn.disabled=true;
                var remaining=9999;
                while(running && remaining>0){
                    try {
                        var body=new URLSearchParams({action:'abcgs_process_next_image',nonce:<?php echo wp_json_encode(wp_create_nonce('abcgs_image_worker')); ?>});
                        var res=await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',body:body,credentials:'same-origin'});
                        var json=await res.json();if(!json.success)throw Error(json.data&&json.data.message||'AJAX thất bại');
                        remaining=Number(json.data.pending)||0;
                        output.textContent='Đã xong: '+json.data.done+' | Chờ: '+remaining+' | Lỗi: '+json.data.errors+(json.data.last_error?' | '+json.data.last_error:'');
                    }catch(e){output.textContent='Dừng: '+e.message;break;}
                }
                running=false;btn.disabled=false;
                if(remaining===0)location.reload();
            });
        })();
        </script>
        <?php endif; ?>
        <?php foreach($errors as $err): ?><div class="notice notice-error"><p>Bài #<?php echo intval($err['post_id']??0); ?>: <?php echo esc_html($err['message']??'Lỗi không xác định'); ?></p></div><?php endforeach; ?>
        <details><summary>Chi tiết hàng đợi hình ảnh</summary><table class="widefat striped"><thead><tr><th>Bài</th><th>Loại ảnh</th><th>Vị trí</th><th>Trạng thái</th><th>Thông báo</th></tr></thead><tbody>
        <?php foreach(array_slice($queue,-100) as $task): ?><tr><td><?php echo intval($task['post_id']??0); ?></td><td><?php echo esc_html($task['type']??''); ?></td><td><?php echo isset($task['heading_index'])?intval($task['heading_index'])+1:'—'; ?></td><td><?php echo esc_html($task['status']??''); ?></td><td><?php echo esc_html($task['message']??''); ?></td></tr><?php endforeach; ?></tbody></table></details>
        <form method="post"><?php wp_nonce_field('abcgs_after_images'); ?>
            <table class="form-table">
                <tr><th>Số lượng ảnh nội dung / bài</th><td><input type="number" name="abcgs_heading_image_limit" min="0" max="100" step="1" value="<?php echo esc_attr($saved_limit); ?>" required style="width:100px"> <p class="description">0 = không tạo ảnh nội dung. Ví dụ chọn 3: tạo tối đa 3 ảnh tại 3 thẻ H2/H3 đầu tiên chưa có ảnh do plugin tạo. Nếu bài có ít hơn 3 mục thì tạo ít hơn. Thiết lập được lưu cho lần sau.</p></td></tr>
                <tr><th>Yêu cầu riêng cho ảnh</th><td><textarea name="abcgs_custom_image_request" rows="6" class="large-text" placeholder="Ví dụ: ảnh phong cách thực tế, không chữ, bố cục ngang 3:2, tập trung đúng nội dung từng mục..."></textarea><p class="description">Yêu cầu được thêm vào prompt tạo từng ảnh trong bài.</p></td></tr>
                <tr><th>Ảnh đại diện</th><td><label><input type="checkbox" name="abcgs_after_featured" value="1" checked> Tạo ảnh đại diện cho bài chưa có ảnh đại diện, sau khi xử lý ảnh trong bài</label></td></tr>
            </table>
            <h2>Chọn bài viết cần tạo ảnh</h2>
            <p><label><input type="checkbox" id="abcgs_all_written" checked> Chọn tất cả</label></p>
            <table class="widefat striped"><thead><tr><th>Chọn</th><th>Bài viết</th><th>Ảnh đại diện</th></tr></thead><tbody>
            <?php foreach($ids as $pid): $post=get_post(intval($pid)); if(!$post) continue; ?>
            <tr><td><input type="checkbox" class="abcgs-written-check" name="abcgs_written_ids[]" value="<?php echo intval($pid); ?>" checked></td><td><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>"><?php echo esc_html(get_the_title($pid)); ?></a></td><td><?php echo has_post_thumbnail($pid)?'Đã có':'Chưa có'; ?></td></tr>
            <?php endforeach; ?></tbody></table>
            <p><button type="submit" name="abcgs_start_after_images" value="1" class="button button-primary button-large">Có – bắt đầu tạo ảnh theo cài đặt</button>
            <button type="submit" name="abcgs_decline_after_images" value="1" class="button">Không tạo ảnh</button>
            <button type="submit" name="abcgs_sync_seo" value="1" class="button">Đồng bộ từ khóa Rank Math / Yoast cho bài đã viết</button></p>
        </form>
        <script>document.getElementById('abcgs_all_written').addEventListener('change',function(){document.querySelectorAll('.abcgs-written-check').forEach(function(e){e.checked=this.checked;},this);});</script>
        </div>
        <?php
    }

    private function handle_after_writing_images() {
        check_admin_referer('abcgs_after_images');
        if(!current_user_can('manage_options')) wp_die('Không có quyền.');
        $allowed=array_map('intval',(array)get_option('abcgs_recent_written_ids',array()));
        $selected=isset($_POST['abcgs_written_ids'])?array_map('intval',(array)wp_unslash($_POST['abcgs_written_ids'])):array();
        $selected=array_values(array_intersect($allowed,$selected));
        $limit=isset($_POST['abcgs_heading_image_limit'])?max(0,min(100,intval(wp_unslash($_POST['abcgs_heading_image_limit'])))):3;
        update_option('abcgs_heading_image_limit',$limit,false);
        $request=isset($_POST['abcgs_custom_image_request'])?sanitize_textarea_field(wp_unslash($_POST['abcgs_custom_image_request'])):'';
        $featured=!empty($_POST['abcgs_after_featured']);
        $queue=(array)get_option(self::OPTION_IMAGE_QUEUE,array());
        $added=0;
        foreach($selected as $pid) {
            if(!get_post($pid)) continue;
            $post_content=(string)get_post_field('post_content',$pid);
            preg_match_all('/<h([23])\\b[^>]*>.*?<\\/h\\1>/is',$post_content,$found);
            $total=count($found[0]);
            if($total<$limit) echo '<div class="notice notice-warning"><p>Bài #'.intval($pid).' chỉ có '.intval($total).' thẻ H2/H3, nên không thể tạo đủ '.intval($limit).' ảnh theo vị trí tiêu đề.</p></div>';
            $chosen=0;
            for($n=0;$n<$total && $chosen<$limit;$n++){
                $marker='<!-- ABCGS_HEADING_IMAGE_'.$n.' -->';
                if(strpos($post_content,$marker)!==false) {$chosen++;continue;}
                $duplicate=false;
                foreach($queue as $task) {
                    if(intval($task['post_id']??0)===$pid && ($task['type']??'')==='heading_inline' && intval($task['heading_index']??-1)===$n && ($task['status']??'')==='pending') {$duplicate=true;break;}
                }
                if($duplicate) {$chosen++;continue;}
                $queue[]=array('post_id'=>$pid,'type'=>'heading_inline','heading_index'=>$n,'custom_request'=>$request,'status'=>'pending','created_at'=>time());
                $added++;
                $chosen++;
            }
            if($featured && !has_post_thumbnail($pid)){
                $duplicate=false;
                foreach($queue as $task) if(intval($task['post_id']??0)===$pid && ($task['type']??'')==='featured' && ($task['status']??'')==='pending') $duplicate=true;
                if(!$duplicate){$queue[]=array('post_id'=>$pid,'type'=>'featured','status'=>'pending','created_at'=>time());$added++;}
            }
        }
        update_option(self::OPTION_IMAGE_QUEUE,$queue,false);
        update_option('abcgs_image_decision_pending',0,false);
        if($added) { wp_clear_scheduled_hook(self::IMAGE_CRON_HOOK); wp_schedule_single_event(time()+5,self::IMAGE_CRON_HOOK); }
        echo '<div class="notice notice-success"><p>Đã xếp hàng '.intval($added).' tác vụ ảnh. Hệ thống sẽ xử lý lần lượt.</p></div>';
    }

    public function render_image_scan_page() {
        if ( ! current_user_can('manage_options') ) return;

        if ( isset($_POST['abcgs_scan_images']) ) $this->handle_scan_images();
        if ( isset($_POST['abcgs_bulk_inline']) ) $this->handle_bulk_image_action('inline');
        if ( isset($_POST['abcgs_bulk_featured']) ) $this->handle_bulk_image_action('featured');

        $results = get_option(self::OPTION_SCAN_RESULTS, array());
        $queue = get_option(self::OPTION_IMAGE_QUEUE, array());
        $pending = 0;
        foreach ((array)$queue as $q) if (($q['status'] ?? '') !== 'done') $pending++;
        ?>
        <div class="wrap">
            <h1>Quét & xử lý hình ảnh hàng loạt</h1>
            <p>Quét các bài viết đã đăng/nháp để tìm bài chưa có ảnh trong nội dung hoặc chưa có ảnh đại diện. Chọn bài rồi chạy từng bước độc lập.</p>
            <?php if ( isset($_GET['abcgs_message']) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['abcgs_message'])); ?></p></div><?php endif; ?>
            <?php if ( isset($_GET['abcgs_error']) ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['abcgs_error'])); ?></p></div><?php endif; ?>
            <?php if ($pending) : ?><div class="notice notice-info"><p>Queue hình ảnh đang còn <strong><?php echo intval($pending); ?></strong> công việc. WP-Cron sẽ xử lý lần lượt để hạn chế timeout.</p></div><?php endif; ?>

            <form method="post" style="margin:16px 0;">
                <?php wp_nonce_field('abcgs_image_tools','abcgs_image_nonce'); ?>
                <button class="button button-primary" name="abcgs_scan_images" value="1">Quét lại bài viết</button>
            </form>

            <?php if ( empty($results) ) : ?>
                <div class="notice notice-warning inline"><p>Chưa có kết quả quét. Bấm “Quét lại bài viết”.</p></div>
            <?php else :
                $missing_inline = array_filter($results, function($r){ return !empty($r['missing_inline']); });
                $missing_featured = array_filter($results, function($r){ return !empty($r['missing_featured']); });
            ?>
            <p><strong>Tổng bài đã quét:</strong> <?php echo count($results); ?> &nbsp; | &nbsp; <strong>Thiếu ảnh trong bài:</strong> <?php echo count($missing_inline); ?> &nbsp; | &nbsp; <strong>Thiếu ảnh đại diện:</strong> <?php echo count($missing_featured); ?></p>

            <form method="post">
                <?php wp_nonce_field('abcgs_image_tools','abcgs_image_nonce'); ?>
                <p>
                    <button type="button" class="button" onclick="abcgsSelect('inline')">Chọn tất cả thiếu ảnh trong bài</button>
                    <button type="button" class="button" onclick="abcgsSelect('featured')">Chọn tất cả thiếu ảnh đại diện</button>
                    <button type="button" class="button" onclick="abcgsClear()">Bỏ chọn</button>
                </p>
                <table class="widefat striped">
                    <thead><tr><th style="width:40px">Chọn</th><th>Bài viết</th><th>Trạng thái</th><th>Ảnh trong bài</th><th>Ảnh đại diện</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    <?php foreach($results as $r): $pid=intval($r['post_id']); ?>
                        <tr data-inline="<?php echo !empty($r['missing_inline'])?'1':'0'; ?>" data-featured="<?php echo !empty($r['missing_featured'])?'1':'0'; ?>">
                            <td><input type="checkbox" class="abcgs-post-check" name="post_ids[]" value="<?php echo $pid; ?>"></td>
                            <td><strong><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>"><?php echo esc_html($r['title']); ?></a></strong><br><code>ID: <?php echo $pid; ?></code></td>
                            <td><?php echo esc_html(get_post_status($pid)); ?></td>
                            <td><?php echo !empty($r['missing_inline']) ? '<span style="color:#b32d2e;font-weight:600">Chưa có</span>' : '<span style="color:#008a20">Đã có</span>'; ?></td>
                            <td><?php echo !empty($r['missing_featured']) ? '<span style="color:#b32d2e;font-weight:600">Chưa có</span>' : '<span style="color:#008a20">Đã có</span>'; ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank">Xem bài</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:15px">
                    <button class="button button-primary" name="abcgs_bulk_inline" value="1">Bước 1: Tạo & chèn ảnh trong bài đã chọn</button>
                    <button class="button button-primary" name="abcgs_bulk_featured" value="1">Bước 2: Tạo ảnh đại diện cho bài đã chọn</button>
                </p>
            </form>
            <script>
            function abcgsSelect(type){document.querySelectorAll('tr[data-'+type+'="1"] .abcgs-post-check').forEach(function(x){x.checked=true;});}
            function abcgsClear(){document.querySelectorAll('.abcgs-post-check').forEach(function(x){x.checked=false;});}
            </script>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handle_scan_images() {
        $this->verify_image_tools_request();
        $ids = get_posts(array('post_type'=>'post','post_status'=>array('publish','draft','pending','future'),'posts_per_page'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'DESC','no_found_rows'=>true));
        $results=array();
        foreach($ids as $pid){
            $content=(string)get_post_field('post_content',$pid);
            $has_inline = (bool) preg_match('/<img\b|<!--\s*wp:image\b/i',$content);
            $results[]=array('post_id'=>intval($pid),'title'=>get_the_title($pid),'missing_inline'=>!$has_inline,'missing_featured'=>!has_post_thumbnail($pid));
        }
        update_option(self::OPTION_SCAN_RESULTS,$results,false);
        $this->redirect_image_tools('Đã quét '.count($results).' bài viết.');
    }

    private function handle_bulk_image_action( $type ) {
        $this->verify_image_tools_request();
        $ids = isset($_POST['post_ids']) ? array_values(array_unique(array_filter(array_map('intval',(array)$_POST['post_ids'])))) : array();
        if(empty($ids)) $this->redirect_image_tools('Vui lòng chọn ít nhất 1 bài viết.', true);
        $queue=get_option(self::OPTION_IMAGE_QUEUE,array());
        if(!is_array($queue)) $queue=array();
        $added=0;
        foreach($ids as $pid){
            if(!get_post($pid)) continue;
            if($type==='inline') {
                $limit=max(0,min(100,intval(get_option('abcgs_heading_image_limit',3))));
                $content=(string)get_post_field('post_content',$pid);
                preg_match_all('/<h([23])\b[^>]*>.*?<\/h\1>/is',$content,$heads);
                $existing=0;
                $scheduled=0;
                foreach($heads[0] as $n=>$heading) {
                    if($existing+$scheduled >= $limit) break;
                    if(strpos($content,'<!-- ABCGS_HEADING_IMAGE_'.$n.' -->')!==false) {$existing++;continue;}
                    $duplicate=false;
                    foreach($queue as $q) if(intval($q['post_id']??0)===$pid && ($q['type']??'')==='heading_inline' && intval($q['heading_index']??-1)===$n && in_array(($q['status']??''),array('pending','done'),true)) {$duplicate=true;break;}
                    if($duplicate) {$existing++;continue;}
                    $queue[]=array('post_id'=>$pid,'type'=>'heading_inline','heading_index'=>$n,'status'=>'pending','message'=>'Đang chờ','created_at'=>time());
                    $scheduled++;$added++;
                }
                continue;
            }
            $duplicate=false;
            foreach($queue as $q){ if(intval($q['post_id']??0)===$pid && ($q['type']??'')===$type && ($q['status']??'')!=='done'){ $duplicate=true; break; } }
            if($duplicate) continue;
            $queue[]=array('post_id'=>$pid,'type'=>$type,'status'=>'pending','message'=>'Đang chờ','created_at'=>time());
            $added++;
        }
        update_option(self::OPTION_IMAGE_QUEUE,$queue,false);
        if($added) { wp_clear_scheduled_hook(self::IMAGE_CRON_HOOK); wp_schedule_single_event(time()+5,self::IMAGE_CRON_HOOK); }
        $label=$type==='inline'?'ảnh trong bài':'ảnh đại diện';
        $this->redirect_image_tools('Đã xếp hàng '.$added.' tác vụ '.$label.'. Hệ thống sẽ xử lý lần lượt.');
    }

    public function repair_pending_image_queue() {
        if(!current_user_can('manage_options')) return;
        $queue=(array)get_option(self::OPTION_IMAGE_QUEUE,array());
        $pending=false;
        foreach($queue as $task) if(($task['status']??'')==='pending') {$pending=true;break;}
        if(!$pending) return;
        if(!wp_next_scheduled(self::IMAGE_CRON_HOOK)) wp_schedule_single_event(time()+5,self::IMAGE_CRON_HOOK);
        if(isset($_GET['abcgs_run_image']) && $_GET['abcgs_run_image']==='1') {
            check_admin_referer('abcgs_run_image');
            $this->process_image_bulk_queue();
            wp_safe_redirect(remove_query_arg(array('abcgs_run_image','_wpnonce')));
            exit;
        }
    }

    public function process_image_bulk_queue() {
        if(get_transient('abcgs_image_worker_lock')) return;
        set_transient('abcgs_image_worker_lock',1,180);
        try { $this->process_image_bulk_queue_unlocked(); }
        finally { delete_transient('abcgs_image_worker_lock'); }
    }

    private function process_image_bulk_queue_unlocked() {
        $queue=get_option(self::OPTION_IMAGE_QUEUE,array());
        if(empty($queue)||!is_array($queue)) return;
        $settings=get_option(self::OPTION_SETTINGS,array());
        $did=false;
        foreach($queue as $i=>$task){
            if(($task['status']??'')!=='pending') continue;
            $pid=intval($task['post_id']??0);
            $type=$task['type']??'';
            if(!$pid || !get_post($pid)) {
                $result=new WP_Error('abcgs_post_missing','Không tìm thấy bài viết.');
            } else {
                $keyword=(string)get_post_meta($pid,'_abcgs_main_keyword',true);
                if($keyword==='') $keyword=get_the_title($pid);
                if($type==='inline') $result=$this->generate_inline_image_for_post($pid,$keyword,$settings,intval($task['image_index']??1),(string)($task['custom_request']??''));
                elseif($type==='heading_inline') $result=$this->generate_heading_image_for_post($pid,$keyword,$settings,intval($task['heading_index']??0),(string)($task['custom_request']??''));
                elseif($type==='featured') $result=$this->generate_featured_image_for_post($pid,$keyword,$settings);
                else $result=new WP_Error('abcgs_bad_task','Loại tác vụ không hợp lệ.');
            }
            $queue[$i]['status']=is_wp_error($result)?'error':'done';
            $queue[$i]['message']=is_wp_error($result)?('Lỗi: '.$result->get_error_message()):'Hoàn tất';
            $queue[$i]['finished_at']=time();
            $did=true;
            break;
        }
        update_option(self::OPTION_IMAGE_QUEUE,$queue,false);
        if($did) $this->refresh_image_scan_results();
        $pending=false;
        foreach($queue as $task) if(($task['status']??'')==='pending') {$pending=true;break;}
        if($pending) {
            // Cron hiện tại đã bị WP-Cron lấy ra khỏi danh sách khi callback chạy.
            // Xóa lịch cũ để tránh event mắc kẹt và lên lịch tác vụ kế tiếp.
            wp_clear_scheduled_hook(self::IMAGE_CRON_HOOK);
            wp_schedule_single_event(time()+10,self::IMAGE_CRON_HOOK);
        }
    }

    private function refresh_image_scan_results() {
        $results=get_option(self::OPTION_SCAN_RESULTS,array());
        if(!is_array($results)) return;
        foreach($results as &$r){
            $pid=intval($r['post_id']??0); if(!$pid||!get_post($pid)) continue;
            $content=(string)get_post_field('post_content',$pid);
            $r['missing_inline']=!preg_match('/<img\b|<!--\s*wp:image\b/i',$content);
            $r['missing_featured']=!has_post_thumbnail($pid);
        }
        unset($r); update_option(self::OPTION_SCAN_RESULTS,$results,false);
    }

    private function verify_image_tools_request() {
        if(!current_user_can('manage_options')) wp_die('Không có quyền.');
        if(!isset($_POST['abcgs_image_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['abcgs_image_nonce'])),'abcgs_image_tools')) wp_die('Xác thực không hợp lệ.');
    }

    private function redirect_image_tools( $msg, $error=false ) {
        $args=array('page'=>'abcgs-image-scan', $error?'abcgs_error':'abcgs_message'=>$msg);
        wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit;
    }

    private function redirect_with_message( $msg ) {
        $url = add_query_arg(
            array(
                'page'          => 'abcgs-ai-bulk-silo',
                'abcgs_message' => rawurlencode( $msg ),
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    private function redirect_with_error( $msg ) {
        $url = add_query_arg(
            array(
                'page'        => 'abcgs-ai-bulk-silo',
                'abcgs_error' => rawurlencode( $msg ),
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }
}

new AI_Bulk_ChatGPT_Silo();
