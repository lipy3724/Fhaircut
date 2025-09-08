<?php
// 修复Gmail认证问题的脚本

// 引入必要的文件
require_once 'db_config.php';
require_once 'env.php';

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 显示页面头部
echo "<!DOCTYPE html>
<html>
<head>
    <title>修复Gmail认证问题</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .step { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Gmail认证问题诊断与修复</h1>";

// 步骤1：检查环境变量
echo "<div class='step'>
    <h2>步骤1：检查环境变量</h2>";

$gmail_username = env('GMAIL_USERNAME', '');
$gmail_password = env('GMAIL_PASSWORD', '');

if (empty($gmail_username)) {
    echo "<p class='error'>错误：GMAIL_USERNAME 未设置或为空</p>";
} else {
    echo "<p class='success'>GMAIL_USERNAME 已设置: " . htmlspecialchars($gmail_username) . "</p>";
}

if (empty($gmail_password)) {
    echo "<p class='error'>错误：GMAIL_PASSWORD 未设置或为空</p>";
} else {
    echo "<p class='success'>GMAIL_PASSWORD 已设置 (长度: " . strlen($gmail_password) . " 字符)</p>";
    
    // 检查密码格式
    if (strlen($gmail_password) != 16) {
        echo "<p class='warning'>警告：Gmail应用密码通常是16个字符，当前密码长度不符</p>";
    }
}

echo "</div>";

// 步骤2：测试SMTP连接
echo "<div class='step'>
    <h2>步骤2：测试SMTP连接</h2>";

// 引入PHPMailer库
require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';

try {
    // 创建PHPMailer实例
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // 服务器设置
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $gmail_username;
    $mail->Password = $gmail_password;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // 设置超时
    $mail->Timeout = 30;
    
    // 尝试连接
    echo "<p>尝试连接到SMTP服务器...</p>";
    
    // 捕获SMTP调试输出
    ob_start();
    $mail->SMTPDebug = 2;
    $result = $mail->smtpConnect();
    $debug_output = ob_get_clean();
    
    if ($result) {
        echo "<p class='success'>SMTP连接成功!</p>";
        $mail->smtpClose();
    } else {
        echo "<p class='error'>SMTP连接失败!</p>";
        echo "<pre>" . htmlspecialchars($debug_output) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    if (isset($mail) && $mail->SMTPDebug > 0) {
        echo "<pre>" . htmlspecialchars($debug_output ?? '') . "</pre>";
    }
}

echo "</div>";

// 步骤3：检查常见问题
echo "<div class='step'>
    <h2>步骤3：常见问题检查</h2>
    <ul>
        <li>确保您的Gmail账户已启用<strong>两步验证</strong></li>
        <li>确保您使用的是<strong>应用密码</strong>而不是普通Gmail密码</li>
        <li>检查您的Gmail账户是否允许<strong>安全性较低的应用</strong>访问</li>
        <li>确认您的服务器IP未被Google标记为垃圾邮件发送者</li>
        <li>检查您的Gmail账户是否有发送限制或暂时被锁定</li>
    </ul>
</div>";

// 步骤4：解决方案
echo "<div class='step'>
    <h2>步骤4：解决方案</h2>
    <ol>
        <li>生成新的Gmail应用密码 (参见 <a href='gmail_app_password_guide.txt' target='_blank'>gmail_app_password_guide.txt</a>)</li>
        <li>更新.env文件中的GMAIL_PASSWORD值</li>
        <li>确保密码中没有额外的空格或特殊字符</li>
        <li>如果问题仍然存在，尝试使用其他邮件服务提供商</li>
    </ol>
</div>";

// 页面底部
echo "</div>
</body>
</html>";
?>
