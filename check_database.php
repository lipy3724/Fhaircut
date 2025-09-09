<?php
// 数据库连接信息
$host = "localhost";
$username = "root"; // XAMPP 默认用户名
$password = ""; // XAMPP 默认密码为空
$database = "jianfa_db";

// 创建数据库连接
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error . "\n");
}

echo "成功连接到数据库: $database\n\n";

// 获取所有表
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "数据库中的表:\n";
    echo "--------------------\n";
    $table_count = 0;
    
    while ($row = $result->fetch_array()) {
        echo $row[0] . "\n";
        $table_count++;
    }
    
    echo "--------------------\n";
    echo "总共 $table_count 个表\n";
} else {
    echo "无法获取表列表: " . $conn->error . "\n";
}

$conn->close();
?>
