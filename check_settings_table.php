<?php
// 包含数据库配置文件
require_once "db_config.php";

// 检查settings表是否存在
$check_table_sql = "SHOW TABLES LIKE 'settings'";
$table_exists = mysqli_query($conn, $check_table_sql);

if ($table_exists && mysqli_num_rows($table_exists) > 0) {
    echo "settings表已存在。\n";
    
    // 检查表结构
    $check_structure_sql = "DESCRIBE settings";
    $structure_result = mysqli_query($conn, $check_structure_sql);
    
    if ($structure_result) {
        echo "表结构如下：\n";
        while ($row = mysqli_fetch_assoc($structure_result)) {
            echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Key'] . "\n";
        }
    }
    
    // 检查已有设置
    $settings_sql = "SELECT setting_key, setting_value FROM settings";
    $settings_result = mysqli_query($conn, $settings_sql);
    
    if ($settings_result && mysqli_num_rows($settings_result) > 0) {
        echo "\n当前设置：\n";
        while ($row = mysqli_fetch_assoc($settings_result)) {
            echo $row['setting_key'] . ": " . $row['setting_value'] . "\n";
        }
    } else {
        echo "\n当前没有设置记录。\n";
    }
} else {
    echo "settings表不存在，正在创建...\n";
    
    // 创建settings表
    $create_table_sql = "CREATE TABLE settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    )";
    
    if (mysqli_query($conn, $create_table_sql)) {
        echo "settings表创建成功！\n";
    } else {
        echo "创建表失败: " . mysqli_error($conn) . "\n";
    }
}

mysqli_close($conn);
?>
