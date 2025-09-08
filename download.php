<?php
/**
 * 文件下载处理脚本
 * 处理带签名的下载链接请求，验证签名并提供文件下载
 */

// 引入必要的文件
require_once 'db_connect.php';
require_once 'url_signer.php';

// 初始化URL签名器
$url_signer = new UrlSigner();

// 获取完整的请求URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$uri = $_SERVER['REQUEST_URI'];
$full_url = $protocol . $host . $uri;

// 验证URL签名
if (!$url_signer->validateSignedUrl($full_url)) {
    header('HTTP/1.1 403 Forbidden');
    echo '链接已过期或无效。请返回购买页面重新获取下载链接。';
    exit;
}

// 获取原始URL（不含签名参数）
$original_url = $url_signer->getOriginalUrl($full_url);
$parsed_url = parse_url($original_url);
$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

// 从URL中提取文件路径
// 假设URL格式为: vip.fhaircut.com/uploads/videos/filename.mp4 或 vip.fhaircut.com/uploads/photos/filename.zip
$file_path = '';

if (strpos($path, '/uploads/videos/') !== false) {
    // 处理视频文件
    $file_name = basename($path);
    $file_path = __DIR__ . '/uploads/videos/' . $file_name;
} elseif (strpos($path, '/uploads/photos/') !== false) {
    // 处理照片文件
    $file_name = basename($path);
    $file_path = __DIR__ . '/uploads/photos/' . $file_name;
} else {
    // 不支持的文件类型
    header('HTTP/1.1 404 Not Found');
    echo '请求的文件类型不受支持。';
    exit;
}

// 检查文件是否存在
if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    echo '请求的文件不存在。';
    exit;
}

// 获取文件信息
$file_size = filesize($file_path);
$file_type = mime_content_type($file_path);

// 设置适当的头信息
header('Content-Description: File Transfer');
header('Content-Type: ' . $file_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $file_size);

// 清空输出缓冲区
ob_clean();
flush();

// 读取文件并输出
readfile($file_path);
exit;
?>
