# AI Bulk ChatGPT Silo — Hồ sơ phát triển và đặc tả tổng hợp

> Cập nhật: 13/10/2026. Tổng hợp từ các tệp PHP/ZIP hiện có trong phiên làm việc và yêu cầu phát triển trong cuộc trò chuyện; bổ sung phân chia Free/Pro, bảng gói từ aibulksilo.com tiêu chuẩn bảo mật (mục 12), phân tích mã nguồn v6.1.1 (mục 13) và thiết kế bản làm mới v7.0.0 (mục 14). Tài liệu không thay thế kiểm thử thực tế trên WordPress.

## 1. Tổng quan

- **Tên plugin:** AI Bulk ChatGPT Silo; **tác giả trong header:** Fame Media; **text domain:** `ai-bulk-chatgpt-silo`.
- **Mục tiêu:** tự động hóa viết bài SEO hàng loạt bằng OpenAI cho WordPress, hình ảnh, silo/internal link, SEO metadata và quản lý hạn mức license.
- **Phiên bản phân phối:** **Free** (1 domain, 1 bài/ngày) và **Pro** (hạn mức theo gói mua tại aibulksilo.com) — xem mục 7.
- **Mã nguồn sớm nhất tìm được:** `ai-bulk-chatgpt-silo_v4.2_overwrite.php` (v4.2). Không có đầy đủ mã nguồn của các bản v1.x–v4.1 trong tài liệu hiện có, vì vậy không thể khẳng định lịch sử chức năng của các phiên bản đó.
- **Mã nguồn đọc trực tiếp được:** v6.1.1 tại `docs/ai-bulk-chatgpt-silo/` (xem mục 13). Bản làm mới v7.0.0 thiết kế ở mục 14.
- **Các gói ZIP có thể đối chiếu:** v5.0.0–v6.2.0, bao gồm nhánh v6.1.1 caption/ALT.
- **Lưu ý nhánh phiên bản:** ZIP v6.2.0 được xây dựng từ nhánh v6.1.0, trong khi tính năng caption/ALT được thêm riêng ở v6.1.1; không nên mặc định v6.2.0 đã bao gồm caption/ALT.

## 2. Lịch sử phiên bản và thay đổi

| Phiên bản | Gói mã nguồn | Nội dung phát triển | Số file PHP |
|---|---|---|---:|
| 5.0.0 | `ai-bulk-chatgpt-silo-v5.0.0-merged.zip` | Hợp nhất tạo bài hàng loạt, xuất bản, tạo ảnh nội dung sau H2 thứ hai, ảnh đại diện, WP-Cron queue, silo, license, đa ngôn ngữ. | 1 |
| 5.1.0 | `ai-bulk-chatgpt-silo-v5.1.0-bulk-image-scan.zip` | Bổ sung quét bài thiếu ảnh và hàng đợi tạo ảnh hàng loạt. | 1 |
| 5.1.1 | `ai-bulk-chatgpt-silo-v5.1.1-fatal-fix.zip` | Bản sửa lỗi fatal trong nhánh quét/tạo ảnh. | 1 |
| 5.2.0 | `ai-bulk-chatgpt-silo-v5.2.0-confirm-images.zip` | Bổ sung bước xác nhận tạo ảnh sau khi viết. | 1 |
| 5.2.1 | `ai-bulk-chatgpt-silo-v5.2.1-inline-image-fix.zip` | Sửa xử lý ảnh chèn trong nội dung. | 1 |
| 5.3.0 | `ai-bulk-chatgpt-silo-v5.3.0-h2-h3-images.zip` | Tạo ảnh theo các đề mục H2/H3. | 1 |
| 5.4.0 | `ai-bulk-chatgpt-silo-v5.4.0-image-count-settings.zip` | Cho phép chọn số lượng ảnh theo đề mục. | 1 |
| 5.4.1 | `ai-bulk-chatgpt-silo-v5.4.1-pending-fix.zip` | Sửa phục hồi tác vụ Pending của hàng đợi. | 1 |
| 5.5.0 | `ai-bulk-chatgpt-silo-v5.5.0-rankmath-yoast.zip` | Bổ sung đồng bộ metadata Rank Math và Yoast. | 1 |
| 5.5.1 | `ai-bulk-chatgpt-silo-v5.5.1-three-images-fix.zip` | Sửa hành vi tạo ba ảnh. | 1 |
| 5.6.0 | `ai-bulk-chatgpt-silo-v5.6.0-image-rankmath-repair.zip` | Sửa tạo ảnh và đồng bộ Rank Math. | 1 |
| 5.7.0 | `ai-bulk-chatgpt-silo-v5.7.0-visual-seo-fix.zip` | Điều chỉnh SEO hình ảnh, prompt tránh chữ trong ảnh và cơ chế đồng bộ metadata. | 1 |
| 5.8.0 | `ai-bulk-chatgpt-silo-v5.8.0-rankmath-seo-title-url.zip` | Tối ưu Rank Math SEO Title, Focus Keyword và slug URL. | 1 |
| 5.9.0 | `ai-bulk-chatgpt-silo-v5.9.0-language-fix.zip` | Lưu ngôn ngữ cho từng mục hàng đợi; bổ sung kiểm tra ngôn ngữ nội dung. | 1 |
| 5.9.1 | `ai-bulk-chatgpt-silo-v5.9.1-english-speed-fix.zip` | Điều chỉnh retry và tốc độ xử lý bài tiếng Anh. | 1 |
| 6.0.0 | `ai-bulk-chatgpt-silo-v6.0.0-new-models.zip` | Thêm lựa chọn model và nhánh Responses API / Chat Completions. | 1 |
| 6.0.1 | `ai-bulk-chatgpt-silo-v6.0.1-english-performance.zip` | Cải thiện xử lý hiệu năng và chẩn đoán tạo nội dung tiếng Anh. | 1 |
| 6.0.2 | `ai-bulk-chatgpt-silo-v6.0.2-silo-language-fix.zip` | Nhãn liên kết silo theo tiếng Việt, Anh, Trung. | 1 |
| 6.1.0 | `ai-bulk-chatgpt-silo-v6.1.0-three-sections.zip` | Tổ chức giao diện thành AI Bulk Silo, Cài đặt tạo hình ảnh, Quét ảnh. | 1 |
| 6.1.1 | `ai-bulk-chatgpt-silo-v6.1.1-image-caption-alt.zip` | Thêm figure/figcaption, thuộc tính ALT và lưu caption/ALT vào Media. | 1 |
| 6.2.0 | `ai-bulk-chatgpt-silo-v6.2.0-suite.zip` | Nhánh suite với 8 module SEO, queue, keyword clustering, links, refresh, image SEO, GSC, license/update; không mặc định có thay đổi caption của v6.1.1. | 2 |

### Giai đoạn trước v5.0.0

- Có tệp `ai-bulk-chatgpt-silo_v4.2_overwrite.php` và ZIP tương ứng. Đây là bản cũ nhất có thể quan sát trong thư mục hiện tại.
- Có các gói liên quan `fame-ai-inline-image-v1.2.0(1).zip` và `wp-openai-featured-image-v2.0.0 (1).zip`; không đủ bằng chứng để coi đó là các phiên bản đầu tiên của cùng plugin AI Bulk ChatGPT Silo.

## 3. Chức năng lõi: tạo bài viết

1. Nhập danh sách từ khóa, chọn ngôn ngữ, giọng văn, kiểu bài, độ dài và mật độ từ khóa.
2. Gọi OpenAI để tạo nội dung; có nhánh dùng Chat Completions và Responses API ở các phiên bản sau.
3. Xử lý nội dung AI: loại code fence, loại H1 trong thân bài, tạo excerpt, slug, chuẩn hóa HTML.
4. Tạo bài WordPress theo trạng thái được chọn; hỗ trợ gắn chuyên mục, ảnh đại diện và metadata SEO.
5. Lưu trạng thái công việc vào `abcgs_queue`; WP-Cron tiếp tục xử lý theo công đoạn.
6. Có chức năng phân tích website để cung cấp ngữ cảnh cho prompt và cơ chế tránh sai ngôn ngữ (từ v5.9.0).

**Các tùy chọn được trao đổi trong quá trình phát triển:** `post_length`, `keyword_density`, `publish/draft`, `category_id`, `language`, `tone`, `article_style`. Cần kiểm tra giao diện từng phiên bản trước khi xác nhận mọi tùy chọn đều hiện diện cùng lúc.

## 4. Hình ảnh AI và Media

- Sinh ảnh nội dung và ảnh đại diện từ prompt riêng; cấu hình model, kích thước, chất lượng, định dạng, bật/tắt tự động.
- Tạo ảnh theo H2/H3; chọn giới hạn số ảnh (trang tạo ảnh sau viết có cấu hình giới hạn, giá trị mặc định từng bản có thể khác).
- Cho phép quét bài thiếu ảnh, chọn bài và chạy hàng đợi tạo ảnh; có thao tác sửa tác vụ Pending.
- Có xử lý nén ảnh theo giới hạn dung lượng trong mã nguồn; hiệu quả thực tế phụ thuộc thư viện xử lý ảnh và định dạng.
- v6.1.1: bổ sung `abcgs_image_text`, `abcgs_image_figure`, `abcgs_set_attachment_text`; tạo cấu trúc `figure > img[alt] + figcaption`, đồng bộ ALT/caption vào attachment.
- Caption dưới ảnh nội dung có thể hiển thị trực tiếp; caption ảnh đại diện ngoài giao diện còn tùy theme. Ảnh cũ không tự được cập nhật nếu chưa chạy xử lý riêng.

## 5. SEO, Silo và liên kết nội bộ

- Đồng bộ các trường SEO cho Rank Math/Yoast; bao gồm Focus Keyword, SEO Title và Meta Description theo logic từng phiên bản.
- Tạo slug từ từ khóa; chèn liên kết nội bộ silo vào bài có liên quan.
- Từ v6.0.2, nhãn silo đa ngôn ngữ: **Liên kết nội bộ cùng chủ đề** / **Related internal links** / **相关内部链接**.
- v6.2.0 thêm trang Rank Math SEO Optimizer, Keyword Clustering & Topic Silo và AI Internal Link Manager. Các module này là bản triển khai nền tảng, chưa đồng nghĩa phân cụm bằng AI/SERP hoặc tự động liên kết tối ưu hoàn chỉnh.

## 6. Hàng đợi, cron và hiệu năng

- Dữ liệu hàng đợi bài: option `abcgs_queue`; hook `abcgs_process_queue_event`.
- Hàng đợi ảnh: option `abcgs_image_bulk_queue`; hook `abcgs_process_image_bulk_queue`.
- WP-Cron phụ thuộc điều kiện kích hoạt cron của WordPress; website ít truy cập có thể chậm.
- v5.9.1 điều chỉnh timeout/retry để giảm độ trễ tiếng Anh.
- v6.2.0 bổ sung Queue Manager, khóa bằng transient và giao diện thao tác retry/tạm dừng; khóa transient kiểu đọc-rồi-ghi chưa bảo đảm tính nguyên tử trong mọi môi trường.

## 7. License và hạn mức

- Có khóa license, xác thực từ máy chủ và lời gọi tiêu thụ quota sau khi tạo bài.

### 7.1. Hai phiên bản: Free và Pro

| Phiên bản | Số domain | Hạn mức bài viết | Cách kích hoạt |
|---|---|---|---|
| **Free** | 1 domain | 1 bài/ngày | Không cần mua gói |
| **Pro** | Theo gói | Theo số bài của gói đã mua (bảng 7.2) | Nhập Serial Key nhận sau khi thanh toán |

> Trạng thái: phân chia Free/Pro là **yêu cầu sản phẩm** (13/10/2026). Chưa đối chiếu được trong mã nguồn hiện có rằng giới hạn Free (1 domain, 1 bài/ngày) đã được triển khai.

### 7.2. Bảng gói Pro (theo [aibulksilo.com](https://aibulksilo.com/), truy cập 13/10/2026)

| Nhóm | Gói | Giá | Số bài |
|---|---|---:|---:|
| Cá nhân | A 30 | 290.000 VND | 30 |
| Cá nhân | A 50 | 390.000 VND | 50 |
| Cá nhân | A 80 | 490.000 VND | 80 |
| Doanh nghiệp | A 500 | 2.500.000 VND | 500 |
| Doanh nghiệp | A 800 | 3.500.000 VND | 800 |
| Doanh nghiệp | A 1.000 | 4.500.000 VND | 1.000 |

- **Quy trình mua:** chọn gói → chuyển khoản Momo qua QR → gửi ảnh chụp thanh toán qua Zalo 0834-309-319 → nhận Serial Key.
- Các tính năng tạo bài (ngôn ngữ, chuyên mục, trạng thái đăng, độ dài, mật độ từ khóa, tone, kiểu bài, phân tích lĩnh vực website) được website liệt kê là chung cho mọi gói.
- **Website chưa ghi rõ:** thời hạn gói (tháng/năm/trọn đời), số domain được dùng cho mỗi gói Pro, giới hạn bài theo ngày của Pro, cách nâng cấp/gia hạn. Cần chốt các điểm này trước khi lập trình kiểm tra license.
- Gói **100 bài** từng xuất hiện trong `license-admin-100-posts-update.zip` nhưng **không có** trên bảng giá website hiện tại; cần xác nhận còn bán hay đã ngừng.

### 7.3. Hạ tầng license

- Có các tệp máy chủ `license-admin.php`, `license-api.php`, `licenses.json`. Không nên nhúng `licenses.json` chứa dữ liệu thực vào bản plugin phát hành công khai.
- v6.2.0 thêm giao diện License & Auto Update, manifest cập nhật qua HTTPS; cần bảo vệ nguồn cập nhật và kiểm chứng gói trước khi dùng sản xuất.

## 8. Tám module mở rộng trong nhánh v6.2.0

| Module | Phạm vi đã mô tả/triển khai | Giới hạn hiện tại |
|---|---|---|
| Rank Math SEO Auto Optimizer | Quét/đồng bộ trường SEO của một tập bài gần đây | Có nguy cơ ghi đè chỉnh sửa thủ công |
| Queue Manager | Xem queue, pause/resume, retry | Cần khóa nguyên tử và retry theo công đoạn |
| Keyword Clustering & Topic Silo | Gom từ khóa bằng quy tắc cụm từ | Chưa phân tích search intent/SERP đầy đủ |
| AI Internal Link Manager | Đề xuất/chèn liên kết liên quan | Chưa tối ưu theo ngữ nghĩa/ngôn ngữ đầy đủ |
| AI Content Refresh | Tạo bản nháp để cập nhật bài cũ | Chưa tự động gọi AI viết lại hoàn chỉnh |
| AI Image SEO Automation | Bổ sung ALT còn thiếu | Chưa nén/chuyển WebP toàn website hoàn chỉnh |
| Google Search Console | Truy vấn dữ liệu với OAuth access token | Chưa có đăng nhập và tự làm mới token |
| License & Auto Update | Quota và kiểm tra manifest HTTPS | Chưa hoàn thiện xác thực nguồn/bản cập nhật |

## 9. Cấu trúc giao diện quản trị

### Nhánh v6.1.0 và v6.1.1

1. **AI Bulk Silo:** cấu hình chung, danh sách từ khóa, tạo bài và quản lý tiến trình.
2. **Cài đặt tạo hình ảnh:** model, prompt, kích thước/chất lượng, chế độ tạo ảnh.
3. **Quét ảnh:** phát hiện bài thiếu ảnh, chọn và tạo ảnh hàng loạt.

### Nhánh v6.2.0

Giữ ba nhóm quản trị trên và bổ sung các trang riêng cho 8 module trong mục 8.

## 10. Kiến trúc mã nguồn và điểm tích hợp

- **Lớp lõi:** `AI_Bulk_ChatGPT_Silo`.
- **Lớp mở rộng v6.2.0:** `ABCGS_Suite_620` trong `abcgs-suite.php`.
- **Option chính:** `abcgs_settings`, `abcgs_queue`, `abcgs_silo_log`, `abcgs_license`, `abcgs_image_bulk_queue`, `abcgs_image_scan_results`.
- **Option suite:** `abcgs_suite_settings`.
- **Cron:** `abcgs_process_queue_event`, `abcgs_process_image_bulk_queue`.
- **Metadata SEO:** ví dụ `rank_math_focus_keyword`, `_yoast_wpseo_focuskw`; các trường SEO khác cần đối chiếu với plugin SEO đang cài.
- **API bên ngoài:** OpenAI cho văn bản/ảnh, máy chủ license, Google Search Console trong suite.
- **Tệp đóng gói:** thư mục gốc `ai-bulk-chatgpt-silo/` trong ZIP, chứa file plugin PHP và file mở rộng nếu có.

## 11. Các lỗi và rủi ro đã ghi nhận khi rà soát mã

| Mức độ | Hạng mục | Mô tả |
|---|---|---|
| Cao | Queue | Có thể chạy đồng thời; khóa transient đọc/ghi riêng không nguyên tử; cần tránh bài trùng và phục hồi cron |
| Cao | License | Lưu bài trước khi tiêu thụ quota; API license lỗi có thể dẫn tới sai lệch hạn mức |
| Cao | SEO | Tự động đồng bộ metadata có thể ghi đè dữ liệu người dùng chỉnh tay |
| Cao | Update | Chỉ dùng HTTPS chưa đủ bảo đảm toàn vẹn gói ZIP và tin cậy máy chủ |
| Trung bình | Retry | Đưa bài lỗi về giai đoạn `content` có thể không đúng giai đoạn đang lỗi |
| Trung bình | Internal Link | Có khả năng chèn chuỗi `\n` theo nghĩa đen thay vì xuống dòng |
| Trung bình | Ngôn ngữ | Kiểm tra ngôn ngữ có thể kích hoạt viết lại, tăng độ trễ và chi phí |
| Trung bình | Ảnh | Retry ảnh lỗi chưa hoàn chỉnh; quét giới hạn bài ở một số module |
| Trung bình | GSC | Access token trong WordPress Options cần kiểm soát quyền và vòng đời token |
| Trung bình | Tác giả bài | WP-Cron có thể không có user đăng nhập, `get_current_user_id()` trả về 0 |

**Phạm vi đánh giá:** những vấn đề trên là phát hiện/nhận định từ kiểm tra tĩnh và các lượt rà soát trước. Chưa có báo cáo kiểm thử tích hợp WordPress thực tế, tải lớn, kiểm thử bảo mật độc lập hoặc đối soát máy chủ license.

## 12. Bảo mật plugin WordPress

> Đây là **tiêu chuẩn bảo mật bắt buộc** cho các bản phát hành từ nay. Chưa rà soát lại toàn bộ mã nguồn v5.0.0–v6.2.0 theo danh sách này, nên chưa được coi các mục dưới đây là đã đạt.

### 12.1. Phân quyền và chống CSRF

- Mọi trang quản trị, thao tác AJAX (`wp_ajax_*`) và REST route phải kiểm tra `current_user_can()`: `manage_options` cho cấu hình, API key, license, GSC; `edit_posts` (hoặc quyền riêng) cho tạo bài/tạo ảnh.
- Không đăng ký `wp_ajax_nopriv_*` cho các tác vụ tạo bài, tạo ảnh, quét, license.
- Mọi form và AJAX phải có nonce: `wp_nonce_field()` / `check_admin_referer()` / `check_ajax_referer()`. REST route phải khai báo `permission_callback`, không được dùng `__return_true` cho route có ghi dữ liệu.
- Thao tác hàng loạt (xóa queue, retry, ghi đè SEO, chèn link) cần xác nhận trước khi chạy và ghi nhật ký người thực hiện.

### 12.2. Kiểm tra đầu vào và escape đầu ra

- Làm sạch đầu vào theo kiểu dữ liệu: `sanitize_text_field`, `sanitize_textarea_field`, `absint`, `esc_url_raw`, `sanitize_key`; giới hạn giá trị theo whitelist cho `language`, `tone`, `article_style`, `post_status`, model, kích thước ảnh.
- Giới hạn số lượng từ khóa mỗi lần gửi và độ dài mỗi từ khóa để tránh làm phình `abcgs_queue`.
- Escape mọi đầu ra trong trang quản trị: `esc_html`, `esc_attr`, `esc_url`, `esc_textarea`; dùng `wp_json_encode` khi đưa dữ liệu vào JavaScript.
- Nội dung AI trả về coi là **không tin cậy**: lọc bằng `wp_kses_post()` trước khi lưu, loại `<script>`, `<iframe>`, `on*=`, `javascript:` (phòng prompt injection chèn mã độc vào bài).
- Truy vấn SQL trực tiếp phải dùng `$wpdb->prepare()`.

### 12.3. Bảo vệ API key và token

- OpenAI API key, license key, token GSC: không hiển thị lại đầy đủ trên giao diện (chỉ hiện dạng `sk-****abcd`), không đưa ra HTML/JS phía trình duyệt, không ghi vào log.
- Cho phép khai báo key bằng hằng số trong `wp-config.php` (ví dụ `ABCGS_OPENAI_KEY`) để không lưu key trong database.
- Option chứa bí mật đặt `autoload = no`. Có thể mã hóa giá trị trước khi lưu bằng khóa dẫn xuất từ `AUTH_KEY`/`SECURE_AUTH_KEY` (giảm rủi ro khi lộ bản dump database, không thay thế bảo vệ máy chủ).
- Khi gỡ plugin (`uninstall.php`): xóa key, token, license và các option/cron của plugin.

### 12.4. Gọi API ngoài và chống SSRF

- Chỉ dùng `wp_remote_get/post` với `sslverify = true` và timeout hợp lý; không tắt kiểm tra SSL.
- Chức năng phân tích website và tải ảnh từ URL chỉ được gọi tới domain của chính site hoặc host trong whitelist (OpenAI, máy chủ license, Google); dùng `wp_safe_remote_get()` để chặn IP nội bộ/localhost.
- Không dùng dữ liệu người dùng để tạo URL gọi API mà không kiểm tra.

### 12.5. Hình ảnh và Media

- Ảnh AI trả về (base64 hoặc URL) phải được kiểm tra MIME thật bằng `wp_check_filetype_and_ext()` / `getimagesize()`, chỉ chấp nhận JPEG/PNG/WebP, giới hạn dung lượng.
- Tên file sinh từ `sanitize_file_name()`; lưu qua `wp_upload_bits()` / `media_handle_sideload()`, không ghi file thủ công ra ngoài thư mục uploads.
- ALT/caption lấy từ AI phải được làm sạch trước khi ghi vào attachment.

### 12.6. Bảo mật license và chống vượt giới hạn Free

- **Máy chủ license là nơi quyết định hạn mức**, không phải plugin. Plugin chỉ hiển thị quota; mỗi lần tạo bài phải xin "phiếu" (reserve) từ máy chủ trước, xác nhận (commit) sau khi lưu bài, hoàn lại khi lỗi. Cách này cũng xử lý rủi ro "lưu bài trước khi tiêu thụ quota" ở mục 11.
- **Free:** máy chủ ghi nhận domain đã đăng ký và đếm số bài theo ngày (theo múi giờ máy chủ); cùng một domain không được tạo nhiều tài khoản Free. Chuẩn hóa domain (bỏ `www.`, chữ thường, bỏ cổng) trước khi so sánh.
- **Pro:** Serial Key gắn với domain theo số lượng gói cho phép; có chức năng hủy kích hoạt để chuyển domain.
- Phản hồi từ máy chủ license phải được **ký số** (khuyến nghị Ed25519 / `sodium_crypto_sign_verify_detached`, khóa công khai nhúng trong plugin) kèm `timestamp` và `nonce` để chống giả mạo và phát lại. Chỉ HTTPS là chưa đủ.
- Cache trạng thái license có thời hạn ngắn; nếu máy chủ không phản hồi thì **từ chối tạo bài** (fail-closed) thay vì cho tạo không giới hạn.
- Máy chủ license: giới hạn tần suất gọi (rate limit) theo IP/key, không bao giờ trả danh sách key, bảo vệ `license-admin.php` bằng đăng nhập + 2FA, chuyển `licenses.json` sang database và đặt ngoài thư mục web công khai.
- Chấp nhận rằng plugin PHP có thể bị sửa mã: mục tiêu là để việc vượt giới hạn **không đem lại lợi ích**, vì quota thật chỉ cấp ở máy chủ.

### 12.7. Cập nhật tự động

- Manifest cập nhật và gói ZIP phải được ký; plugin kiểm tra chữ ký + SHA-256 trước khi cài đặt.
- Chỉ tải gói từ domain cố định của nhà phát hành; không cho người dùng thay URL manifest bằng giao diện.
- Ghi nhớ phiên bản trước để có thể rollback.

### 12.8. Queue, cron và chống lạm dụng

- Khóa queue nguyên tử (ví dụ `add_option` trả về false nếu đã tồn tại, hoặc `GET_LOCK` MySQL) để không chạy song song làm tạo bài trùng và tiêu thụ quota hai lần.
- Lưu `author_id` của người tạo vào từng mục queue, không dùng `get_current_user_id()` trong cron (trả về 0).
- Giới hạn số tác vụ mỗi lượt cron và số lần retry để tránh tốn chi phí OpenAI vô hạn khi lỗi lặp lại.

### 12.9. Nhật ký và xử lý lỗi

- Ghi nhật ký các sự kiện: kích hoạt/hủy license, thay đổi API key, thao tác hàng loạt, lỗi gọi API. Không ghi giá trị bí mật vào log.
- Thông báo lỗi ra giao diện ngắn gọn; không hiển thị nguyên văn phản hồi API hoặc stack trace.
- Chặn truy cập file trực tiếp: mỗi file PHP bắt đầu bằng `defined( 'ABSPATH' ) || exit;`; thêm `index.php` rỗng trong các thư mục con.

### 12.10. Checklist trước khi phát hành

- [ ] `php -l` toàn bộ file; chạy PHPCS với bộ quy tắc `WordPress-Extra` + `WordPress.Security`.
- [ ] Kiểm tra mọi handler AJAX/REST có capability + nonce.
- [ ] Không còn API key, token, `licenses.json`, file `.env` hay dữ liệu khách hàng trong ZIP.
- [ ] Thử vượt giới hạn Free: đổi ngày hệ thống, sửa option `abcgs_license`, gọi lại API license với phản hồi giả; máy chủ phải từ chối.
- [ ] Thử nội dung AI chứa `<script>` / link `javascript:`; phải bị lọc.
- [ ] Gỡ plugin trên staging; xác nhận dữ liệu bí mật đã bị xóa.

## 13. Phân tích mã nguồn v6.1.1 (đọc trực tiếp)

> Nguồn: `docs/ai-bulk-chatgpt-silo/ai-bulk-chatgpt-silo.php`, header `Version: 6.1.1`, 1 file, 2.413 dòng. Các số dòng bên dưới thuộc file này. Phần này là kết quả đọc mã tĩnh, chưa chạy thử trên WordPress.

### 13.1. Thông tin chung

- Lớp duy nhất `AI_Bulk_ChatGPT_Silo`, khởi tạo ở cuối file.
- Máy chủ license cố định: `https://famemedia.vn/licenses/license-api.php` (dòng 29).
- Menu (quyền `manage_options`): **AI Bulk Silo**, **Cài đặt tạo hình ảnh**, **Quét ảnh** và trang ẩn **Tạo ảnh sau khi viết** (`abcgs-after-writing-images`).
- Giá trị mặc định: model `gpt-6-luna` (nhưng nhiều chỗ fallback `gpt-4.1-mini`), 1.200 từ, mật độ 5%, `vi`, tone `neutral`, kiểu `general_guide`, ảnh `gpt-image-1` 1536x1024 `medium` `webp`, nén ảnh dưới 1 MB và thu về rộng 1.200px.
- Model GPT-5/GPT-6 gọi **Responses API** (`reasoning.effort = low`), các model khác gọi **Chat Completions** (`temperature 0.7`).
- Ngôn ngữ bài: `vi`, `en`, `zh`. Tone: `neutral`, `friendly`, `formal`, `expert`, `promotional`, `journalistic`, `storytelling`. Kiểu bài: `general_guide`, `news_article`, `product_description`, `product_review`, `comparison`, `how_to`, `listicle`.
- Quy tắc nội dung cứng trong prompt: tiêu đề đúng 12 từ và khác từ khóa; không dùng dấu `-`; không nêu giá; không kể chuyện cá nhân; không cam kết; không FAQ; in đậm mọi lần xuất hiện từ khóa chính; chỉ dùng `h2, h3, p, ul, li`.

### 13.2. Luồng thực tế

1. **Bắt đầu:** form trang chính gửi đồng thời API key, cấu hình bài, cấu hình SEO, license và danh sách từ khóa. Plugin gọi `validate` lên máy chủ license, lưu toàn bộ cấu hình, **ghi đè** queue cũ bằng queue mới, hẹn cron sau 5 giây.
2. **Mỗi lượt cron xử lý 1 bài:** dựng prompt → gọi OpenAI → kiểm tra ngôn ngữ, sai thì gọi lại 1 lần → bỏ code fence → lấy tiêu đề → đổi `h1` thành `h2` → lọc HTML bằng `wp_kses` → excerpt 40 từ → slug theo từ khóa → `wp_insert_post` → ghi meta SEO → gọi `consume` lên máy chủ license.
3. **Khi hết bài Pending:** lưu danh sách bài vừa viết, hiện thông báo hỏi có tạo ảnh không, rồi gắn liên kết silo.
4. **Silo:** từ khóa dòng 1 là bài trụ cột. Bài trụ cột link sang bài 2; mỗi bài con link sang bài kế tiếp và về bài trụ cột. Khối `<div class="silo-links">` được thay thế nếu chạy lại.
5. **Ảnh:** chỉ tạo khi người dùng xác nhận. Người dùng chọn N ảnh mỗi bài; plugin chèn ảnh sau N thẻ H2/H3 đầu tiên (đánh dấu `<!-- ABCGS_HEADING_IMAGE_n -->`), dạng `figure > img[alt] + figcaption`, và tạo ảnh đại diện cho bài chưa có. Queue ảnh chạy bằng cron hoặc vòng lặp AJAX trên trình duyệt.
6. Hai ô “Tự động tạo ảnh” ở trang Cài đặt ảnh **không còn tác dụng**: luồng viết bài đặt thẳng công đoạn `done`, nên công đoạn `inline`/`featured` trong queue bài không bao giờ chạy.

### 13.3. Hợp đồng license API đang dùng

Plugin gửi `POST` dạng form tới `license-api.php`:

| Trường | Giá trị |
|---|---|
| `action` | `validate` hoặc `consume` |
| `serial`, `email` | Serial Key và email mua hàng |
| `domain` | Host của `site_url()`, đã bỏ `www.`, bỏ cổng, chữ thường |
| `site_url` | `site_url()` đầy đủ |
| `count` | Chỉ với `consume`, mặc định 1 |

Phản hồi JSON thành công: `{"status":"ok","plan":"...","max_posts":N,"used_posts":N}`. Thất bại: `status` khác `ok`, kèm `code`, `message` và có thể kèm `expected_domain`, `received_domain`, `expected_email`, `received_email`.

License hiện tại gắn **1 serial + 1 email + 1 domain**, hạn mức tính theo **tổng số bài** (không theo ngày). Mã nguồn không có chế độ Free.

### 13.4. Lỗi xác nhận trong mã

| # | Mức | Dòng | Lỗi |
|---|---|---|---|
| 1 | Cao | 978–999, 1946–1959 | Lưu và đăng bài trước, trừ quota sau. Nếu gọi `consume` lỗi mạng/HTTP, plugin bỏ qua im lặng: bài vẫn đăng mà không bị trừ quota. |
| 2 | Cao | 902–905, 929 | Trong cron, hạn mức kiểm tra bằng option `abcgs_license` lưu trên site. Sửa option này trong database là vượt giới hạn. |
| 3 | Cao | 875 | `process_queue` không có khóa. Cron và nút “Xử lý ngay 1 bài” chạy cùng lúc có thể tạo bài trùng và trừ quota hai lần. |
| 4 | Cao | 41, 523–529 | Hook `save_post_post` ghi lại SEO Title/Description/Focus Keyword **mỗi lần lưu bài**, ghi đè chỉnh sửa tay; cũng chạy mỗi khi plugin chèn ảnh hoặc silo. |
| 5 | Cao | 1884–1891 | Plugin in ra `expected_email`/`expected_domain` từ máy chủ. Nghĩa là máy chủ license đang trả email của chủ license cho người nhập sai, làm lộ thông tin khách hàng. |
| 6 | TB | 757–758 | Bấm tạo bài lần mới sẽ ghi đè queue đang chạy, mất trạng thái các bài dở dang. |
| 7 | TB | 240 | API key được in lại vào thuộc tính `value` của ô mật khẩu, nằm trong HTML trang quản trị. |
| 8 | TB | 719 | Cấu hình chỉ được lưu khi bấm tạo bài (bắt buộc có từ khóa và license hợp lệ); không có nút lưu riêng. |
| 9 | TB | 983 | `post_author = get_current_user_id()` chạy trong cron trả về 0, bài không có tác giả. |
| 10 | TB | 971–974, 1409–1431 | Tiêu đề lấy từ dòng đầu của nội dung nhưng dòng đó không bị xóa, nên tiêu đề có thể lặp ở đầu bài (cần đối chiếu bài thực tế). |
| 11 | TB | 1173 | Chuỗi `"\\n"` trong nháy kép làm prompt tiếng Việt chứa ký tự `\n` theo nghĩa đen. |
| 12 | TB | 1080–1090, 1467 | Nếu bài trụ cột (dòng 1) lỗi, bài con đầu tiên bị coi là trụ cột. |
| 13 | TB | 502–507 | Lựa chọn “Rank Math / Yoast / Tự phát hiện” không có tác dụng: luôn ghi cả hai, trừ khi chọn “Không điền”. |
| 14 | TB | 2323–2324 | Khóa queue ảnh bằng transient đọc-rồi-ghi, không nguyên tử. |
| 15 | TB | 912 | Bài lỗi chuyển `error` và không có nút thử lại. |
| 16 | Thấp | 32–33 | `set_time_limit(300)` chạy trong constructor, ảnh hưởng mọi request WordPress. |
| 17 | Thấp | 66 | `add_submenu_page(null, …)` gây cảnh báo deprecated trên PHP 8.1+. |
| 18 | Thấp | 1757 | Ảnh trả về dạng URL được tải bằng `wp_remote_get` (không chặn IP nội bộ) và không kiểm tra MIME thật. |
| 19 | Thấp | 2258 | Quét ảnh tải toàn bộ bài (`posts_per_page = -1`) và lưu tiêu đề vào option. |
| 20 | Thấp | 169–179 | Nội dung thông báo lấy từ tham số URL, có thể bị giả mạo câu chữ (đã escape, không gây XSS). |
| 21 | Thấp | — | Không có `uninstall.php`, không khai báo `Requires PHP`/`Requires at least`; option queue và settings tự nạp (autoload) mọi request. |
| 22 | Thấp | 25, 1555, 1027–1053 | Mã chết: `INLINE_MARKER`, `get_second_h2_context()`, công đoạn `inline`/`featured` của queue bài. |

## 14. Thiết kế bản làm mới v7.0.0

### 14.1. Phạm vi

- **Có:** giữ toàn bộ chức năng v6.1.1 (mục 13), thêm Free/Pro (mục 7), sửa 22 lỗi ở mục 13.4, áp dụng tiêu chuẩn bảo mật mục 12.
- **Không có trong v7.0.0:** 8 module của nhánh v6.2.0 (không có mã nguồn để đối chiếu). Có thể bổ sung ở bản sau.
- **Tương thích ngược:** giữ nguyên tên option, cron hook, meta bài viết, marker ảnh và class CSS `silo-links`. Cài đè lên v6.1.1 không mất dữ liệu; API key cũ được chuyển sang kho bí mật mã hóa khi nâng cấp.
- Yêu cầu: PHP 7.4+, WordPress 5.8+.
- Mã nguồn: thư mục `ai-bulk-chatgpt-silo/` ở gốc repo. Bản v6.1.1 giữ nguyên trong `docs/` để đối chiếu.

### 14.2. Cấu trúc mã

```
ai-bulk-chatgpt-silo/
├── ai-bulk-chatgpt-silo.php        Header, hằng số, nạp file, hook kích hoạt
├── uninstall.php                   Xóa option, khóa, cron, bí mật khi gỡ
├── includes/
│   ├── class-abcgs-plugin.php      Đăng ký hook, menu, thông báo, nâng cấp dữ liệu
│   ├── class-abcgs-settings.php    Giá trị mặc định, whitelist, đọc/ghi cấu hình
│   ├── class-abcgs-secrets.php     Lưu API key/serial mã hóa (sodium), hỗ trợ hằng số wp-config
│   ├── class-abcgs-lock.php        Khóa nguyên tử bằng add_option + hạn khóa
│   ├── class-abcgs-license.php     Free/Pro, gọi máy chủ license, fail-closed
│   ├── class-abcgs-openai.php      Gọi Responses/Chat Completions và Images API
│   ├── class-abcgs-prompts.php     Prompt bài viết và prompt ảnh
│   ├── class-abcgs-content.php     Xử lý HTML AI: tiêu đề, lọc, excerpt, kiểm tra ngôn ngữ
│   ├── class-abcgs-seo.php         Ghi meta Rank Math/Yoast, chỉ điền ô trống
│   ├── class-abcgs-silo.php        Gắn liên kết silo
│   ├── class-abcgs-images.php      Tạo ảnh, kiểm tra MIME, nén, chèn figure
│   ├── class-abcgs-queue.php       Queue bài viết (cron, retry, hủy)
│   └── class-abcgs-image-queue.php Queue ảnh, quét ảnh
└── admin/views/                    Giao diện các trang quản trị
```

### 14.3. Trang quản trị

| Trang | Nội dung |
|---|---|
| **AI Bulk Silo** | Trạng thái license (Free/Pro, còn bao nhiêu bài), form viết bài (từ khóa, chuyên mục, trạng thái, ngôn ngữ, tone, kiểu bài, độ dài, mật độ, SEO), bảng queue có nút Thử lại / Hủy / Dọn mục đã xong |
| **Cài đặt chung** | OpenAI API key (chỉ hiện dạng che), model, hồ sơ lĩnh vực website + nút tự phân tích. Lưu độc lập, không cần chạy queue |
| **License** | Chọn Free (nhập email) hoặc Pro (nhập Serial Key + email), kiểm tra, xóa license khỏi site |
| **Cài đặt tạo hình ảnh** | Như v6.1.1, bỏ hai ô không còn tác dụng |
| **Quét ảnh** | Như v6.1.1, quét theo lô giới hạn |
| *(ẩn)* **Tạo ảnh sau khi viết** | Như v6.1.1 |

### 14.4. Luồng tạo bài v7

1. Bấm tạo bài **thêm** từ khóa vào queue (không ghi đè). Mỗi mục lưu `author_id`, ngôn ngữ, mã đợt (`batch_id`) để gắn silo đúng đợt.
2. Mỗi lượt cron lấy khóa nguyên tử, xử lý 1 mục, nhả khóa.
3. **Hỏi máy chủ license còn quota không** (trước khi tốn phí OpenAI).
   - Pro hết bài → mục chuyển `error` “Hết số bài của gói”.
   - Free đã dùng 1 bài hôm nay → mục chuyển `waiting_quota`, hẹn chạy lại lúc `reset_at` máy chủ trả về (không có thì sang ngày mới theo giờ site).
   - Máy chủ không phản hồi → mục chờ và thử lại sau 10 phút (**fail-closed**: không viết bài khi chưa xác nhận được quota).
4. Gọi OpenAI viết bài, kiểm tra ngôn ngữ, xử lý HTML.
5. **Gọi `consume` trước khi đăng.** Thành công mới `wp_insert_post`. Thất bại thì giữ nội dung đã viết trong mục queue để thử lại, không tốn phí OpenAI lần hai.
6. Ghi meta SEO (chỉ điền ô trống), lưu meta plugin.
7. Khi một đợt hoàn tất: gắn silo cho các bài của đợt đó (bài trụ cột lỗi thì bỏ qua silo và báo rõ), rồi hỏi tạo ảnh như v6.1.1.

### 14.5. Free và Pro trong plugin

| | Free | Pro |
|---|---|---|
| Thông tin nhập | Email | Serial Key + email |
| Hạn mức | Máy chủ đếm 1 bài/ngày/domain | Máy chủ đếm theo tổng số bài của gói |
| Ràng buộc domain | 1 email ↔ 1 domain; mỗi domain chỉ 1 đăng ký Free | Như hiện tại (serial gắn domain) |
| Tính năng | Như Pro, chỉ khác hạn mức (cần xác nhận) | Đầy đủ |
| Khi hết hạn mức | Chờ sang ngày mới, tự chạy tiếp | Dừng, báo mua hoặc gia hạn gói |

### 14.6. Hợp đồng license API v2 (tương thích ngược)

Plugin v7 giữ nguyên `validate`/`consume` và các trường của mục 13.3, thêm:

| Trường mới | Ý nghĩa |
|---|---|
| `edition` | `pro` (mặc định, như hiện tại) hoặc `free`. Với `free`, `serial` để trống |
| `plugin_version` | Phiên bản plugin, phục vụ thống kê và chặn bản quá cũ |

Phản hồi thêm (nếu có): `edition`, `daily_limit`, `used_today`, `reset_at` (Unix timestamp). Mã lỗi plugin xử lý riêng: `QUOTA_EXCEEDED`, `DAILY_LIMIT`, `FREE_DOMAIN_TAKEN`, `FREE_EMAIL_USED`.

**Việc máy chủ license phải làm** (ngoài phạm vi plugin, chưa có mã nguồn máy chủ):

1. Hỗ trợ `edition=free`: đăng ký email + domain, đếm bài theo ngày, trả `reset_at`.
2. **Ngừng trả** `expected_email`, `expected_domain` trong phản hồi lỗi (lỗi #5 mục 13.4).
3. `consume` phải nguyên tử (không trừ vượt `max_posts` khi có 2 yêu cầu đồng thời).
4. Giai đoạn 2: ký phản hồi (mục 12.6). Plugin v7 đã có chỗ kiểm chữ ký, bật khi khai báo hằng số `ABCGS_LICENSE_PUBLIC_KEY` trong `wp-config.php`.

Cho tới khi máy chủ hỗ trợ `edition=free`, chế độ Free sẽ báo lỗi từ máy chủ. Pro hoạt động ngay với máy chủ hiện tại.

### 14.7. Cách v7 xử lý 22 lỗi của mục 13.4

| Lỗi | Cách xử lý |
|---|---|
| 1, 2 | Hỏi quota trước, `consume` trước khi đăng, fail-closed; không dùng số liệu cục bộ để quyết định |
| 3, 14 | Khóa nguyên tử `add_option` có hạn khóa cho cả queue bài và queue ảnh |
| 4, 13 | Bỏ ghi đè khi lưu bài; chỉ điền ô SEO trống; tôn trọng lựa chọn Rank Math/Yoast/Tự phát hiện; nút đồng bộ có tùy chọn ghi đè |
| 5 | Không hiển thị email/domain mong đợi; yêu cầu máy chủ ngừng trả |
| 6 | Queue thêm vào thay vì ghi đè; có nút dọn mục đã xong |
| 7 | API key không bao giờ in ra HTML, chỉ hiện dạng `sk-…abcd`; để trống là giữ key cũ |
| 8 | Trang Cài đặt chung lưu độc lập |
| 9 | Lưu `author_id` lúc xếp hàng |
| 10 | Yêu cầu AI đặt tiêu đề trong `<h1>`, plugin tách ra làm tiêu đề và xóa khỏi nội dung |
| 11 | Sửa chuỗi xuống dòng |
| 12 | Silo theo `batch_id`, bỏ qua và báo lỗi khi bài trụ cột lỗi |
| 15 | Nút Thử lại theo đúng công đoạn lỗi |
| 16 | Chỉ nâng thời gian chạy trong worker |
| 17 | Trang ẩn đăng ký với parent rỗng thay vì `null` |
| 18 | `wp_safe_remote_get`, kiểm tra MIME bằng `getimagesizefromstring`, chỉ nhận JPEG/PNG/WebP |
| 19 | Quét tối đa 1.000 bài gần nhất mỗi lượt, chỉ lưu ID và cờ |
| 20 | Thông báo lưu tạm theo người dùng, không lấy từ URL |
| 21 | Có `uninstall.php`, khai báo yêu cầu phiên bản, option lớn tắt autoload |
| 22 | Bỏ mã chết |

### 14.8. Còn phụ thuộc bên ngoài

- Máy chủ license cập nhật theo 14.6 (cần mã nguồn `license-api.php` để làm).
- 5 câu hỏi về gói Pro ở mục 7.2.
- Bản Free có bị giới hạn tính năng ngoài hạn mức không (ví dụ khóa tạo ảnh)? v7.0.0 tạm cho Free dùng đủ tính năng.

## 15. Khuyến nghị phát triển tiếp

1. Hợp nhất v6.1.1 caption/ALT vào nhánh suite sau khi so sánh mã, không ghi đè mù.
2. Xây dựng khóa queue nguyên tử, idempotency key cho mỗi từ khóa/bài và cơ chế retry theo giai đoạn.
3. Thêm đối soát quota với máy chủ license, nhật ký giao dịch và trạng thái lỗi có thể khôi phục.
4. Triển khai chế độ Free: ràng buộc 1 domain, giới hạn 1 bài/ngày (kiểm tra phía máy chủ license, không chỉ phía plugin, để tránh bị sửa mã vượt giới hạn); tự chuyển sang hạn mức Pro khi nhập Serial Key hợp lệ.
5. Chỉ tối ưu Rank Math trên trường trống hoặc theo lựa chọn người dùng; có preview/diff trước khi cập nhật.
6. Xây dựng OAuth chuẩn cho GSC, xử lý refresh token và phân quyền lưu thông tin xác thực.
7. Xác minh manifest cập nhật và chữ ký/hash ZIP; lưu bản backup/rollback.
8. Viết test cho: tạo bài, tạo ảnh, caption/ALT, đa ngôn ngữ, cron, license, SEO và migration phiên bản.

## 16. Danh sách mã nguồn đã đối chiếu

| Tệp | Ghi chú |
|---|---|
| `ai-bulk-chatgpt-silo-v5.0.0-merged.zip` | Header 5.0.0, 1 file PHP, 28 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.1.0-bulk-image-scan.zip` | Header 5.1.0, 1 file PHP, 37 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.1.1-fatal-fix.zip` | Header 5.1.1, 1 file PHP, 37 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.2.0-confirm-images.zip` | Header 5.2.0, 1 file PHP, 41 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.2.1-inline-image-fix.zip` | Header 5.2.1, 1 file PHP, 41 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.3.0-h2-h3-images.zip` | Header 5.3.0, 1 file PHP, 42 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.4.0-image-count-settings.zip` | Header 5.4.0, 1 file PHP, 42 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.4.1-pending-fix.zip` | Header 5.4.1, 1 file PHP, 44 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.5.0-rankmath-yoast.zip` | Header 5.5.0, 1 file PHP, 45 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.5.1-three-images-fix.zip` | Header 5.5.1, 1 file PHP, 46 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.6.0-image-rankmath-repair.zip` | Header 5.6.0, 1 file PHP, 49 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.7.0-visual-seo-fix.zip` | Header 5.7.0, 1 file PHP, 51 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.8.0-rankmath-seo-title-url.zip` | Header 5.8.0, 1 file PHP, 53 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.9.0-language-fix.zip` | Header 5.9.0, 1 file PHP, 54 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v5.9.1-english-speed-fix.zip` | Header 5.9.1, 1 file PHP, 54 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v6.0.0-new-models.zip` | Header 6.0.0, 1 file PHP, 54 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v6.0.1-english-performance.zip` | Header 6.0.1, 1 file PHP, 54 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v6.0.2-silo-language-fix.zip` | Header 6.0.2, 1 file PHP, 55 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v6.1.0-three-sections.zip` | Header 6.1.0, 1 file PHP, 56 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v6.1.1-image-caption-alt.zip` | Header 6.1.1, 1 file PHP, 59 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo-v6.2.0-suite.zip` | Header 6.2.0, 2 file PHP, 68 khai báo hàm tìm thấy |
| `ai-bulk-chatgpt-silo_v4.2_overwrite.php` | Tệp PHP nhánh v4.2 |
| `license-admin.php` / `license-api.php` / `licenses.json` | Thành phần máy chủ license, tách biệt plugin WordPress |
| `license-admin-100-posts-update.zip` | Gói cập nhật thêm gói 100 bài |

## 17. Quy tắc bảo trì và phát hành

- Luôn lấy đúng bản ZIP làm nguồn trước khi chỉnh sửa, kiểm tra các nhánh thay đổi độc lập.
- Nâng `Version` trong header, đóng gói đúng tên thư mục plugin, kiểm tra `php -l` cho tất cả PHP.
- Không ghi API key, token GSC, dữ liệu license thật hoặc mật khẩu vào mã nguồn công khai.
- Chạy staging và sao lưu trước khi thay plugin đang dùng.
- Ghi changelog và xác định rõ tính năng **đã có trong mã**, **đã kiểm thử**, **mới đề xuất**.

---

*Tài liệu này phản ánh những phiên bản và mã nguồn thực sự có thể kiểm tra trong phiên làm việc; không khẳng định đã khôi phục được mọi phiên bản đầu tiên của dự án.*
