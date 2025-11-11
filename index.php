<?php
// index.php
define('IMAGE_DIR', __DIR__ . '/images/');
define('IMAGE_URL', 'https://www.truyenthong.edu.vn/apps/giml/images/');
define('GJW_VIDEO_URL', 'https://www.truyenthong.edu.vn/apps/giml/videos-gjw/');
define('LOG_FILE', __DIR__ . '/giml.log');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Hàm ghi log
function write_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

// Tạo thư mục images nếu chưa tồn tại
if (!is_dir(IMAGE_DIR)) {
    mkdir(IMAGE_DIR, 0755, true);
    write_log("Created images directory: " . IMAGE_DIR);
}

// Define directory for Gan Jing World videos
define('VIDEO_DIR', __DIR__ . '/videos-gjw/');

// Create videos directory if not exists
if (!is_dir(VIDEO_DIR)) {
    mkdir(VIDEO_DIR, 0755, true);
    write_log("Created videos directory: " . VIDEO_DIR);
}

// Xử lý khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['drive_link'])) {
    $user_link = trim($_POST['drive_link']);
    write_log("Received user link: $user_link");

    if (strpos($user_link, 'drive.google.com') !== false) {
        // Handle Google Drive image link
        // Trích xuất ID từ link Google Drive
        if (preg_match('/https:\/\/drive\.google\.com\/file\/d\/(.+?)\/view/', $user_link, $matches)) {
            $file_id = $matches[1];
            $direct_link = "https://drive.google.com/uc?export=download&id=$file_id";
            write_log("Extracted file ID: $file_id, Direct link: $direct_link");
            
            // Tạo tên file dựa trên ID và định dạng mặc định
            $ext = '.jpg'; // Mặc định
            $file_name = $file_id . $ext;
            $file_path = IMAGE_DIR . $file_name;
            
            // Tải file từ Google Drive bằng cURL
            $ch = curl_init($direct_link);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $image_content = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($image_content !== false && $http_code == 200) {
                write_log("Successfully fetched image content, HTTP code: $http_code");
                
                // Kiểm tra định dạng file
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($finfo, $image_content);
                finfo_close($finfo);
                write_log("Detected MIME type: $mime");
                
                $mime_to_ext = [
                    'image/jpeg' => '.jpg',
                    'image/png' => '.png',
                    'image/gif' => '.gif'
                ];
                
                if (isset($mime_to_ext[$mime])) {
                    $ext = $mime_to_ext[$mime];
                    $file_name = $file_id . $ext;
                    $file_path = IMAGE_DIR . $file_name;
                    
                    // Lưu file
                    file_put_contents($file_path, $image_content);
                    write_log("Saved image to: $file_path");
                    
                    // Lưu thời gian truy cập đầu tiên
                    file_put_contents($file_path . '.access', time());
                    write_log("Created access file for: $file_path");
                    
                    // Tạo mã HTML với file_id
                    $image_url = IMAGE_URL . 'image.php?file=' . urlencode($file_id);
                    $html_code = "<img src=\"$image_url\" alt=\"Image from Google Drive\" style=\"max-width:100%;height:auto;\">";
                    write_log("Generated HTML code: $html_code");
                } else {
                    $error = "Định dạng file không được hỗ trợ. Chỉ hỗ trợ JPG, PNG, GIF.";
                    write_log("Error: Unsupported file format: $mime");
                }
            } else {
                $error = "Không thể tải ảnh từ link Google Drive.";
                write_log("Error: Failed to fetch image, HTTP code: $http_code, cURL error: $curl_error");
            }
        } 
    } elseif (strpos($user_link, 'ganjingworld.com') !== false) {
        // Handle Gan Jing World video link
        // Gọi Puppeteer để lấy link video thực
        $escaped_link = escapeshellarg($user_link);
        $node_cmd = "node " . __DIR__ . "/fetch_video_url.js $escaped_link 2>&1";
        write_log("Running Puppeteer: $node_cmd");
        $puppeteer_output = shell_exec($node_cmd);
        write_log("Puppeteer output: $puppeteer_output");
        $real_video_url = trim($puppeteer_output);
        if (filter_var($real_video_url, FILTER_VALIDATE_URL) && strpos($real_video_url, 'http') === 0) {
            // Kiểm tra nếu link là video stream (.m3u8, .mp4, etc.)
            if (preg_match('/\.(m3u8|mp4|avi|mov|wmv|flv|webm)$/i', $real_video_url)) {
                if (preg_match('/\.m3u8$/i', $real_video_url)) {
                    // Tạo mã nhúng video HLS với hls.js
                    $html_code = "<video id=\"video\" controls style=\"max-width:100%;\"></video>\n<script src=\"https://cdn.jsdelivr.net/npm/hls.js@latest\"></script>\n<script>\nvar video = document.getElementById('video');\nvar videoSrc = '$real_video_url';\nif (Hls.isSupported()) {\nvar hls = new Hls();\nhls.loadSource(videoSrc);\nhls.attachMedia(video);\n} else if (video.canPlayType('application/vnd.apple.mpegurl')) {\nvideo.src = videoSrc;\n}\n</script>";
                    write_log("Generated HLS video embed code: $html_code");
                } else {
                    // Tạo mã nhúng video trực tiếp
                    $html_code = "<video src=\"$real_video_url\" controls style=\"max-width:100%;\"></video>";
                    write_log("Generated direct video embed code: $html_code");
                }
            } else {
                // Nếu không phải link video trực tiếp, thử tải về như cũ
                $video_path = handle_gjw_video($real_video_url);
                if ($video_path) {
                    $video_filename = basename($video_path);
                    $video_url = GJW_VIDEO_URL . $video_filename;
                    $html_code = "<a href=\"$video_url\" target=\"_blank\">Download Video</a>";
                    write_log("Generated video link: $html_code");
                } else {
                    $error = "Không thể tải video từ link Gan Jing World (bước tải file).";
                }
            }
        } else {
            $error = "Không thể lấy link video thực từ Gan Jing World.";
        }
    } else {
        $error = "Link không hợp lệ. Vui lòng nhập link từ Google Drive hoặc Gan Jing World.";
        write_log("Error: Invalid link provided: $user_link");
    }
}

// Function to handle Gan Jing World video links
function handle_gjw_video($video_link) {
    if (preg_match('/https:\/\/www\.ganjingworld\.com\/(s|video)\/(.+)/', $video_link, $matches)) {
        $video_id = $matches[2];
        $direct_link = "https://www.ganjingworld.com/" . $matches[1] . "/$video_id";
        write_log("Extracted video ID: $video_id, Direct link: $direct_link");

        $file_name = $video_id . '.mp4';
        $file_path = VIDEO_DIR . $file_name;

        // Download video using cURL
        $ch = curl_init($direct_link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $video_content = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($video_content !== false && $http_code == 200) {
            write_log("Successfully fetched video content, HTTP code: $http_code");

            // Check MIME type before saving
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $video_content);
            finfo_close($finfo);
            write_log("Detected MIME type: $mime");

            if (strpos($mime, 'video/') !== 0) {
                write_log("Error: The downloaded content is not a video. MIME type: $mime");

                // Log the first 500 characters of the HTML content for debugging
                $html_preview = substr($video_content, 0, 500);
                write_log("HTML Preview: $html_preview");

                // Attempt to parse HTML to find video URL
                $dom = new DOMDocument();
                @$dom->loadHTML($video_content);
                $xpath = new DOMXPath($dom);
                $video_url_node = $xpath->query("//video/source/@src");

                if ($video_url_node->length > 0) {
                    $actual_video_url = $video_url_node->item(0)->nodeValue;
                    write_log("Extracted video URL from HTML: $actual_video_url");
                    return handle_gjw_video($actual_video_url);
                } else {
                    write_log("Error: Could not find video URL in HTML content.");
                }

                return null;
            }

            // Save video file
            file_put_contents($file_path, $video_content);
            write_log("Saved video to: $file_path");

            // Save access time
            file_put_contents($file_path . '.access', time());
            write_log("Created access file for: $file_path");

            return VIDEO_DIR . $file_name;
        } else {
            write_log("Error: Failed to fetch video, HTTP code: $http_code, cURL error: $curl_error");
            return null;
        }
    } else {
        write_log("Error: Invalid Gan Jing World video link: $video_link");
        return null;
    }
}

// Xóa các file ảnh không được truy cập trong 30 phút
foreach (glob(IMAGE_DIR . '*.access') as $access_file) {
    $image_file = str_replace('.access', '', $access_file);
    $last_access = (int)file_get_contents($access_file);
    
    if (time() - $last_access > 1800) { // 30 phút
        @unlink($image_file);
        @unlink($access_file);
        write_log("Deleted expired image: $image_file");
    }
}

// Cleanup expired videos (1 day)
foreach (glob(VIDEO_DIR . '*.access') as $access_file) {
    $video_file = str_replace('.access', '', $access_file);
    $last_access = (int)file_get_contents($access_file);

    if (time() - $last_access > 86400) { // 1 day
        @unlink($video_file);
        @unlink($access_file);
        write_log("Deleted expired video: $video_file");
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giml - Google Images Links</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 15px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            margin-top: 10px;
        }
        .html-code {
            background-color: #f8f8f8;
            padding: 10px;
            margin-top: 10px;
            word-break: break-all;
        }
        .copy-button {
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        .copy-button:hover {
            background-color: #218838;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 0.9em;
            color: #555;
        }
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        function copyToClipboard() {
            const htmlCode = document.querySelector('.html-code').textContent;
            navigator.clipboard.writeText(htmlCode).then(() => {
                alert('Đã sao chép mã HTML!');
            }).catch(err => {
                console.error('Lỗi sao chép: ', err);
            });
        }
    </script>
</head>
<body>
    <h1>Giml - Google Images Links</h1>
    <p>Nhập link ảnh từ Google Drive hoặc video từ Gan Jing World để tạo mã HTML chèn vào bài viết. Xem <a href="https://www.ganjingworld.com/news/1hlg2es6scj2I5aBJ36THsI4q1fc1c" target="_blank">hướng dẫn</a>.</p>
    
    <form method="POST">
        <div class="form-group">
            <label for="drive_link">Nhập link ảnh từ Google Drive hoặc video từ Gan Jing World:</label>
            <input type="text" id="drive_link" name="drive_link" placeholder="https://drive.google.com/file/d/... hoặc https://www.ganjingworld.com/video/..." required>
        </div>
        <button type="submit" id="submitBtn">Tạo mã HTML để nhúng</button>
    </form>

    <div id="console-frame" style="display:none;margin-top:20px;padding:10px;background:#222;color:#eee;font-family:monospace;border-radius:5px;min-height:32px;"></div>

    <script>
    const form = document.querySelector('form');
    const consoleFrame = document.getElementById('console-frame');
    const submitBtn = document.getElementById('submitBtn');
    form.addEventListener('submit', function(e) {
        consoleFrame.style.display = 'block';
        consoleFrame.innerHTML =
            '<div>⏳ Đang kiểm tra link...</div>' +
            '<div id="step-puppeteer"></div>' +
            '<div id="step-download"></div>' +
            '<div id="step-html"></div>';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang xử lý...';
        // Cập nhật trạng thái từng bước (mô phỏng, vì PHP reload page)
        setTimeout(() => {
            document.getElementById('step-puppeteer').textContent = '⏳ Đang lấy link video thực (Puppeteer)...';
        }, 800);
        setTimeout(() => {
            document.getElementById('step-download').textContent = '⏳ Đang tải video về máy chủ...';
        }, 1800);
        setTimeout(() => {
            document.getElementById('step-html').textContent = '⏳ Đang tạo mã HTML kết quả...';
        }, 2600);
    });
    window.addEventListener('pageshow', function() {
        // Reset trạng thái khi quay lại trang
        submitBtn.disabled = false;
        submitBtn.textContent = 'Tạo mã HTML';
        consoleFrame.style.display = 'none';
    });
    </script>
    
    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (isset($html_code)): ?>
        <div class="form-group">
            <h3>Kết quả:</h3>
            <div class="html-code"><?php echo htmlspecialchars($html_code); ?></div>
            <button class="copy-button" onclick="copyToClipboard()">Sao chép</button>
            <p>Sao chép mã trên và dán vào bài viết của bạn. Nếu chỉ lấy liên kết trực tiếp, sao chép phần https://...</p>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        © 2025 Giml. All rights reserved. Developed by <a href="https://ganjingworld.com/@ndmphuc/5" target="_blank">Nguyễn Đăng Minh Phúc</a>.
    </div>
</body>
</html>
