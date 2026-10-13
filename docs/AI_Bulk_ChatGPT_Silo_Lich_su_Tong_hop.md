# AI Bulk ChatGPT Silo — Hồ sơ phát triển và đặc tả tổng hợp

> Cập nhật: 13/10/2026. Tổng hợp từ các tệp PHP/ZIP hiện có trong phiên làm việc và yêu cầu phát triển trong cuộc trò chuyện; bổ sung phân chia Free/Pro và bảng gói từ aibulksilo.com. Tài liệu không thay thế kiểm thử thực tế trên WordPress.

## 1. Tổng quan

- **Tên plugin:** AI Bulk ChatGPT Silo; **tác giả trong header:** Fame Media; **text domain:** `ai-bulk-chatgpt-silo`.
- **Mục tiêu:** tự động hóa viết bài SEO hàng loạt bằng OpenAI cho WordPress, hình ảnh, silo/internal link, SEO metadata và quản lý hạn mức license.
- **Phiên bản phân phối:** **Free** (1 domain, 1 bài/ngày) và **Pro** (hạn mức theo gói mua tại aibulksilo.com) — xem mục 7.
- **Mã nguồn sớm nhất tìm được:** `ai-bulk-chatgpt-silo_v4.2_overwrite.php` (v4.2). Không có đầy đủ mã nguồn của các bản v1.x–v4.1 trong tài liệu hiện có, vì vậy không thể khẳng định lịch sử chức năng của các phiên bản đó.
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

## 12. Khuyến nghị phát triển tiếp

1. Hợp nhất v6.1.1 caption/ALT vào nhánh suite sau khi so sánh mã, không ghi đè mù.
2. Xây dựng khóa queue nguyên tử, idempotency key cho mỗi từ khóa/bài và cơ chế retry theo giai đoạn.
3. Thêm đối soát quota với máy chủ license, nhật ký giao dịch và trạng thái lỗi có thể khôi phục.
4. Triển khai chế độ Free: ràng buộc 1 domain, giới hạn 1 bài/ngày (kiểm tra phía máy chủ license, không chỉ phía plugin, để tránh bị sửa mã vượt giới hạn); tự chuyển sang hạn mức Pro khi nhập Serial Key hợp lệ.
5. Chỉ tối ưu Rank Math trên trường trống hoặc theo lựa chọn người dùng; có preview/diff trước khi cập nhật.
6. Xây dựng OAuth chuẩn cho GSC, xử lý refresh token và phân quyền lưu thông tin xác thực.
7. Xác minh manifest cập nhật và chữ ký/hash ZIP; lưu bản backup/rollback.
8. Viết test cho: tạo bài, tạo ảnh, caption/ALT, đa ngôn ngữ, cron, license, SEO và migration phiên bản.

## 13. Danh sách mã nguồn đã đối chiếu

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

## 14. Quy tắc bảo trì và phát hành

- Luôn lấy đúng bản ZIP làm nguồn trước khi chỉnh sửa, kiểm tra các nhánh thay đổi độc lập.
- Nâng `Version` trong header, đóng gói đúng tên thư mục plugin, kiểm tra `php -l` cho tất cả PHP.
- Không ghi API key, token GSC, dữ liệu license thật hoặc mật khẩu vào mã nguồn công khai.
- Chạy staging và sao lưu trước khi thay plugin đang dùng.
- Ghi changelog và xác định rõ tính năng **đã có trong mã**, **đã kiểm thử**, **mới đề xuất**.

---

*Tài liệu này phản ánh những phiên bản và mã nguồn thực sự có thể kiểm tra trong phiên làm việc; không khẳng định đã khôi phục được mọi phiên bản đầu tiên của dự án.*
