<?php
/**
 * 数据库配置示例文件
 * 复制此文件为 db_config.php 并修改相应的配置值
 */

// 加载环境变量
require_once __DIR__ . '/env.php';

// 数据库连接配置
$db_server = env('DB_SERVER', 'localhost');
$db_username = env('DB_USERNAME', 'root');
$db_password = env('DB_PASSWORD', '');
$db_name = env('DB_NAME', 'jianfa_db');

// 尝试连接到MySQL数据库
$conn = mysqli_connect($db_server, $db_username, $db_password, $db_name);

// 检查连接
if (!$conn) {
    // 如果连接失败，尝试初始化数据库
    require_once __DIR__ . '/db_init.php';
    
    // 再次尝试连接
    $conn = mysqli_connect($db_server, $db_username, $db_password, $db_name);
    
    // 如果仍然连接失败，则终止
    if (!$conn) {
        die("Failed to connect to database: " . mysqli_connect_error());
    }
}

// 设置字符集
mysqli_set_charset($conn, "utf8mb4");
?>
