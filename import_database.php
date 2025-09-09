<?php
// 数据库连接信息
$host = "localhost";
$username = "root"; // XAMPP 默认用户名
$password = ""; // XAMPP 默认密码为空
$database = "jianfa_db";

// 备份文件路径
$backup_file = "/Users/lipengyu/Documents/jianfa/jianfa1 2/jianfa_db_backup_20250908_101341.sql";

// 显示执行状态
echo "开始导入数据库...\n";
echo "数据库: $database\n";
echo "备份文件: $backup_file\n";

// 检查文件是否存在
if (!file_exists($backup_file)) {
    die("错误: 备份文件不存在!\n");
}

// 创建数据库连接
$conn = new mysqli($host, $username, $password);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error . "\n");
}

// 确保数据库存在
$conn->query("CREATE DATABASE IF NOT EXISTS `$database`");
$conn->select_db($database);

// 读取SQL文件内容
$sql = file_get_contents($backup_file);
if (!$sql) {
    die("错误: 无法读取备份文件!\n");
}

echo "正在导入数据，这可能需要一些时间...\n";

// 执行SQL
if ($conn->multi_query($sql)) {
    do {
        // 处理结果集
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    if ($conn->error) {
        echo "导入过程中出现错误: " . $conn->error . "\n";
    } else {
        echo "数据库导入成功!\n";
    }
} else {
    echo "导入失败: " . $conn->error . "\n";
}

$conn->close();
?>
