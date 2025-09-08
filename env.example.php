<?php
/**
 * 环境配置示例文件
 * 复制此文件为 env.php 并修改相应的配置值
 */

// 数据库配置
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'your_database_password');
define('DB_NAME', 'jianfa_db');

// PayPal配置 (请替换为你的实际PayPal凭据)
define('PAYPAL_CLIENT_ID', 'your_paypal_client_id');
define('PAYPAL_CLIENT_SECRET', 'your_paypal_client_secret');
define('PAYPAL_SANDBOX', true);

// Apple Pay配置
define('APPLE_PAY_MERCHANT_ID', 'merchant.com.yourmerchantid');

// 服务器配置
define('APP_URL', 'http://localhost');
define('APP_PORT', 8082);

// 邮件配置 (如果使用Gmail SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('SMTP_ENCRYPTION', 'tls');

// 辅助函数，用于获取环境变量
function env($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}
?>
