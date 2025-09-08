<?php
// 加载环境变量
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db_config.php';

// 显示错误信息
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 输出PHP版本
echo "PHP版本: " . phpversion() . "<br>";

// 检查数据库连接
if ($conn) {
    // 获取MySQL版本
    $result = mysqli_query($conn, "SELECT VERSION() as version");
    $row = mysqli_fetch_assoc($result);
    echo "MySQL版本: " . $row['version'] . "<br>";
    
    // 获取数据库连接信息
    echo "数据库主机: " . mysqli_get_host_info($conn) . "<br>";
    echo "数据库名称: " . $db_name . "<br>";
    echo "数据库服务器: " . $db_server . "<br>";
    
    // 检查数据库连接字符集
    $result = mysqli_query($conn, "SHOW VARIABLES LIKE 'character_set_database'");
    $row = mysqli_fetch_assoc($result);
    echo "数据库字符集: " . $row['Value'] . "<br>";
    
    // 检查数据库表
    $result = mysqli_query($conn, "SHOW TABLES");
    echo "数据库表:<br>";
    while ($row = mysqli_fetch_row($result)) {
        echo "- " . $row[0] . "<br>";
    }
} else {
    echo "数据库连接失败: " . mysqli_connect_error();
}
?>
