<?php
// image.php
define('IMAGE_DIR', __DIR__ . '/');
define('LOG_FILE', __DIR__ . '/../giml.log');

// Hàm ghi log
function write_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

if (isset($_GET['file'])) {
    $file_id = basename($_GET['file']);
    write_log("Processing image request for file_id: $file_id");
    
    // Tìm file ảnh hiện có với các định dạng hỗ trợ
    $possible_extensions = ['jpg', 'png', 'gif'];
    $file_path = null;
    foreach ($possible_extensions as $ext) {
        $test_path = IMAGE_DIR . $file_id . '.' . $ext;
        if (file_exists($test_path)) {
            $file_path = $test_path;
            write_log("Found existing image: $file_path");
            break;
        }
    }
    
    // Nếu không tìm thấy file, tải lại từ Google Drive
    if (!$file_path) {
        $direct_link = "https://drive.google.com/uc?export=download&id=$file_id";
        write_log("Image not found, attempting to fetch from: $direct_link");
        
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
                $file_path = IMAGE_DIR . $file_id . $ext;
                
                // Lưu file
                file_put_contents($file_path, $image_content);
                write_log("Saved image to: $file_path");
                
                // Lưu thời gian truy cập
                file_put_contents($file_path . '.access', time());
                write_log("Created access file for: $file_path");
            } else {
                write_log("Error: Unsupported file format: $mime");
                header('HTTP/1.1 400 Bad Request');
                echo 'Unsupported file format';
                exit;
            }
        } else {
            write_log("Error: Failed to fetch image, HTTP code: $http_code, cURL error: $curl_error");
            header('HTTP/1.1 404 Not Found');
            echo 'Unable to fetch image from Google Drive';
            exit;
        }
    }
    
    // Cập nhật thời gian truy cập
    file_put_contents($file_path . '.access', time());
    write_log("Updated access time for: $file_path");
    
    // Gửi ảnh về trình duyệt
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=1800');
    readfile($file_path);
    write_log("Served image: $file_path");
    exit;
}

// Trả về 404 nếu không có tham số file
write_log("Error: No file parameter provided");
header('HTTP/1.1 404 Not Found');
echo 'Image not found';
?>
