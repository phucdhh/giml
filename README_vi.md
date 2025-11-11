# Giml - Google Images Links (Tiếng Việt)

## Giới thiệu

Giml là một ứng dụng web nhỏ dùng PHP giúp tạo mã HTML để nhúng ảnh từ Google Drive hoặc video từ Gan Jing World vào bài viết (ví dụ: course trong Moodle hoặc forum post). Ứng dụng hỗ trợ lấy URL thực của video (kể cả HLS `.m3u8`) bằng Puppeteer và tạo mã nhúng tương thích cho trình duyệt.

## Tính năng chính

- Chuyển link Google Drive (`/file/d/...`) thành ảnh có thể nhúng bằng thẻ `<img>`.
- Trích xuất link video thực từ Gan Jing World bằng Puppeteer (headless Chrome) và trả về:
   - Mã nhúng player cho stream HLS (`.m3u8`) sử dụng `hls.js`.
   - Hoặc thẻ `<video>` trực tiếp nếu là file video.
- Tự động xóa file tạm:
   - Ảnh: sau 30 phút nếu không truy cập.
   - Video: sau 1 ngày nếu không truy cập.
- Ghi log hoạt động vào `giml.log` để debug.

## Cấu trúc dự án

- `index.php` — File chính (giao diện + logic PHP).
- `fetch_video_url.js` — Script Node.js sử dụng Puppeteer để lấy link video.
- `package.json` / `package-lock.json` — Dependencies Node.js (Puppeteer).
- `images/` — Lưu trữ ảnh tải về từ Google Drive (tự tạo).
- `videos-gjw/` — Lưu trữ video tải về (tự tạo).
- `giml.log` — File log (tự tạo).
- `README_vi.md`, `README_en.md` — Tài liệu dự án.

## Yêu cầu hệ thống

- PHP 7.x trở lên (với extension `cURL` và `fileinfo`).
- Node.js (>= 16) và npm để cài Puppeteer.
- Web server (Apache, Nginx) để chạy `index.php`.
- VPS/Server cần có đủ RAM/CPU để chạy Chromium headless (ít nhất 512 MB RAM cho quy trình Chromium hoạt động ổn định).

## Cài đặt (trên server/VPS)

1. Đặt mã nguồn vào thư mục web root, ví dụ `/var/www/html/giml` hoặc theo cấu trúc hosting của bạn.
2. Cài Node.js và npm nếu chưa có.
3. Trong thư mục dự án, chạy:

```bash
npm install
# Nếu Puppeteer báo thiếu browser, chạy:
npx puppeteer browsers install chrome
```

4. Kiểm tra quyền ghi cho thư mục `images/` và `videos-gjw/` (hoặc để ứng dụng tạo tự động):

```bash
chown -R www-data:www-data /path/to/giml
chmod -R 755 /path/to/giml/images /path/to/giml/videos-gjw
```

5. Nếu chạy trên CentOS/AlmaLinux, có thể cần cài thêm font hoặc gói phụ thuộc cho Chromium.

## Cách sử dụng

1. Truy cập trang `index.php` trên trình duyệt.
2. Dán link Google Drive hoặc Gan Jing World vào form.
3. Nhấn "Tạo mã HTML để nhúng".
4. Sao chép mã HTML xuất ra và dán vào nội dung của Moodle (course page, forum post, HTML block, etc.).

- Nếu kết quả trả về là HLS (`.m3u8`), mã mặc định sử dụng `hls.js` để phát trên trình duyệt hiện đại. Ví dụ mã nhúng:

```html
<video id=\"video\" controls style=\"max-width:100%;\"></video>
<script src=\"https://cdn.jsdelivr.net/npm/hls.js@latest\"></script>
<script>
   var video = document.getElementById('video');
   var videoSrc = 'URL_MASTER_M3U8';
   if (Hls.isSupported()) {
      var hls = new Hls();
      hls.loadSource(videoSrc);
      hls.attachMedia(video);
   } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = videoSrc; // Safari native
   }
</script>
```

## Lưu ý vận hành & Troubleshooting

- Puppeteer/Chromium tiêu tốn tài nguyên: một instance có thể dùng vài trăm MB RAM và CPU cao khi load trang. Nếu VPS yếu, cân nhắc nâng cấp hoặc giới hạn concurrency (chỉ cho phép 1 request Puppeteer một lúc).
- Nếu Puppeteer báo lỗi thiếu Chrome, chạy `npx puppeteer browsers install chrome` hoặc thiết lập `PUPPETEER_EXECUTABLE_PATH` để chỉ đường dẫn Chrome đã cài.
- Nếu output từ Puppeteer chứa log, PHP dùng `2>&1` sẽ hợp nhất stderr/stdout — hiện code đã điều chỉnh để chỉ in URL ra stdout.
- Nếu không lấy được video: kiểm tra `giml.log` để biết chi tiết (timeout, navigation error, hoặc trang cần JS chưa load). Có thể điều chỉnh `waitUntil` hoặc timeout trong `fetch_video_url.js`.

## Bảo mật

- Dữ liệu tải về tạm thời có thể lưu trên server; tránh lưu file nhạy cảm công khai.
- Không cho phép upload file qua form (ứng dụng chỉ lấy link).

## Phát triển

- Các thay đổi về Puppeteer (thời gian chờ, args Chrome) nằm trong `fetch_video_url.js`.
- Nếu muốn thêm hỗ trợ site khác, chỉnh `index.php` và/hoặc `fetch_video_url.js` để trích xuất URL phù hợp.

## Liên hệ

Người phát triển: Nguyễn Đăng Minh Phúc
Trang liên hệ: https://ganjingworld.com/@ndmphuc/

---
Cập nhật: 2025-11-12