<?php
/**
 * 数据库备份脚本
 * 创建完整的MySQL数据库备份文件
 */

// 数据库连接配置
$host = "localhost";
$username = "root";
$password = "";
$database = "jianfa_db";

// 备份文件配置
$backup_dir = __DIR__ . '/database_backups/';
$backup_filename = 'jianfa_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
$backup_path = $backup_dir . $backup_filename;

// 创建备份目录（如果不存在）
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        die("无法创建备份目录: $backup_dir\n");
    }
}

echo "=== 数据库备份工具 ===\n";
echo "数据库: $database\n";
echo "备份文件: $backup_path\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 连接数据库
    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    
    echo "✓ 数据库连接成功\n";
    
    // 设置字符集
    $conn->set_charset("utf8mb4");
    
    // 开始备份
    $backup_content = "-- ========================================\n";
    $backup_content .= "-- 数据库备份文件\n";
    $backup_content .= "-- 数据库: $database\n";
    $backup_content .= "-- 备份时间: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- 生成工具: 剪发网站数据库备份脚本\n";
    $backup_content .= "-- ========================================\n\n";
    
    $backup_content .= "SET NAMES utf8mb4;\n";
    $backup_content .= "SET time_zone = '+00:00';\n";
    $backup_content .= "SET foreign_key_checks = 0;\n";
    $backup_content .= "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n\n";
    
    // 获取所有表
    $tables_result = $conn->query("SHOW TABLES");
    if (!$tables_result) {
        throw new Exception("无法获取表列表: " . $conn->error);
    }
    
    $tables = [];
    while ($row = $tables_result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo "✓ 发现 " . count($tables) . " 个表\n";
    
    // 备份每个表
    foreach ($tables as $table) {
        echo "备份表: $table ... ";
        
        // 获取表结构
        $create_table_result = $conn->query("SHOW CREATE TABLE `$table`");
        if (!$create_table_result) {
            echo "失败 (无法获取表结构)\n";
            continue;
        }
        
        $create_table_row = $create_table_result->fetch_array();
        $backup_content .= "-- --------------------------------------------------------\n";
        $backup_content .= "-- 表的结构 `$table`\n";
        $backup_content .= "-- --------------------------------------------------------\n\n";
        $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup_content .= $create_table_row[1] . ";\n\n";
        
        // 获取表数据
        $data_result = $conn->query("SELECT * FROM `$table`");
        if (!$data_result) {
            echo "警告 (无法获取数据)\n";
            continue;
        }
        
        $row_count = $data_result->num_rows;
        if ($row_count > 0) {
            $backup_content .= "-- --------------------------------------------------------\n";
            $backup_content .= "-- 转存表中的数据 `$table`\n";
            $backup_content .= "-- --------------------------------------------------------\n\n";
            
            // 获取字段信息
            $fields_result = $conn->query("SHOW COLUMNS FROM `$table`");
            $fields = [];
            while ($field = $fields_result->fetch_assoc()) {
                $fields[] = "`" . $field['Field'] . "`";
            }
            
            $insert_statement = "INSERT INTO `$table` (" . implode(', ', $fields) . ") VALUES\n";
            $backup_content .= $insert_statement;
            
            $row_num = 0;
            while ($row = $data_result->fetch_array(MYSQLI_NUM)) {
                $row_num++;
                
                // 处理每个字段的值
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                
                $value_string = "(" . implode(', ', $values) . ")";
                
                if ($row_num < $row_count) {
                    $value_string .= ",";
                } else {
                    $value_string .= ";";
                }
                
                $backup_content .= $value_string . "\n";
            }
            
            $backup_content .= "\n";
        }
        
        echo "完成 ($row_count 行)\n";
    }
    
    // 结束备份
    $backup_content .= "-- ========================================\n";
    $backup_content .= "-- 备份完成\n";
    $backup_content .= "-- 结束时间: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- ========================================\n";
    
    // 写入备份文件
    if (file_put_contents($backup_path, $backup_content) === false) {
        throw new Exception("无法写入备份文件: $backup_path");
    }
    
    $backup_size = filesize($backup_path);
    $backup_size_mb = round($backup_size / 1024 / 1024, 2);
    
    echo "\n✓ 备份完成！\n";
    echo "备份文件: $backup_path\n";
    echo "文件大小: $backup_size_mb MB\n";
    echo "完成时间: " . date('Y-m-d H:i:s') . "\n";
    
    // 创建备份信息文件
    $info_file = $backup_dir . 'backup_info.txt';
    $info_content = "最新备份信息\n";
    $info_content .= "================\n";
    $info_content .= "备份文件: $backup_filename\n";
    $info_content .= "备份时间: " . date('Y-m-d H:i:s') . "\n";
    $info_content .= "数据库: $database\n";
    $info_content .= "表数量: " . count($tables) . "\n";
    $info_content .= "文件大小: $backup_size_mb MB\n";
    $info_content .= "备份路径: $backup_path\n";
    
    file_put_contents($info_file, $info_content);
    
    // 显示表统计信息
    echo "\n=== 备份统计 ===\n";
    foreach ($tables as $table) {
        $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $count_result->fetch_assoc()['count'];
        echo "$table: $count 行\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "\n❌ 备份失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 数据库备份成功完成！\n";
?>
