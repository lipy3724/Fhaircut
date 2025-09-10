<?php
/**
 * 数据库恢复脚本
 * 从备份文件恢复MySQL数据库
 */

// 数据库连接配置
$host = "localhost";
$username = "root";
$password = "";
$database = "jianfa_db";

// 备份目录
$backup_dir = __DIR__ . '/database_backups/';

echo "=== 数据库恢复工具 ===\n";

// 检查是否有命令行参数指定备份文件
if ($argc > 1) {
    $backup_file = $argv[1];
    if (!file_exists($backup_file)) {
        $backup_file = $backup_dir . $backup_file;
    }
} else {
    // 显示可用的备份文件
    echo "可用的备份文件:\n";
    echo "================\n";
    
    $backup_files = glob($backup_dir . "*.sql");
    if (empty($backup_files)) {
        die("❌ 没有找到备份文件！\n");
    }
    
    // 按修改时间排序（最新的在前）
    usort($backup_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach ($backup_files as $index => $file) {
        $basename = basename($file);
        $size = round(filesize($file) / 1024 / 1024, 2);
        $time = date('Y-m-d H:i:s', filemtime($file));
        echo sprintf("%d. %s (%.2f MB, %s)\n", $index + 1, $basename, $size, $time);
    }
    
    echo "\n请选择要恢复的备份文件编号 (1-" . count($backup_files) . "): ";
    $choice = (int)trim(fgets(STDIN));
    
    if ($choice < 1 || $choice > count($backup_files)) {
        die("❌ 无效的选择！\n");
    }
    
    $backup_file = $backup_files[$choice - 1];
}

if (!file_exists($backup_file)) {
    die("❌ 备份文件不存在: $backup_file\n");
}

echo "\n选择的备份文件: " . basename($backup_file) . "\n";
echo "文件大小: " . round(filesize($backup_file) / 1024 / 1024, 2) . " MB\n";
echo "文件时间: " . date('Y-m-d H:i:s', filemtime($backup_file)) . "\n\n";

echo "⚠️  警告：此操作将完全覆盖当前数据库的所有数据！\n";
echo "确认要继续吗？(输入 'yes' 确认): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 'yes') {
    die("❌ 恢复操作已取消。\n");
}

try {
    // 连接数据库
    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    
    echo "✓ 数据库连接成功\n";
    
    // 设置字符集
    $conn->set_charset("utf8mb4");
    
    // 禁用外键检查
    $conn->query("SET foreign_key_checks = 0");
    
    // 读取备份文件
    echo "正在读取备份文件...\n";
    $sql_content = file_get_contents($backup_file);
    
    if ($sql_content === false) {
        throw new Exception("无法读取备份文件");
    }
    
    echo "✓ 备份文件读取成功\n";
    
    // 分割SQL语句
    $sql_statements = explode(";\n", $sql_content);
    
    echo "正在执行SQL语句...\n";
    $success_count = 0;
    $error_count = 0;
    
    foreach ($sql_statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // 跳过空行和注释
        }
        
        if (!$conn->query($statement . ";")) {
            $error_count++;
            echo "⚠️  SQL执行错误: " . $conn->error . "\n";
            echo "语句: " . substr($statement, 0, 100) . "...\n";
        } else {
            $success_count++;
        }
    }
    
    // 重新启用外键检查
    $conn->query("SET foreign_key_checks = 1");
    
    echo "\n=== 恢复完成 ===\n";
    echo "成功执行: $success_count 条SQL语句\n";
    echo "执行失败: $error_count 条SQL语句\n";
    
    if ($error_count == 0) {
        echo "🎉 数据库恢复成功完成！\n";
    } else {
        echo "⚠️  数据库恢复完成，但有 $error_count 个错误。\n";
    }
    
    // 显示恢复后的表统计
    echo "\n=== 恢复后的数据统计 ===\n";
    $tables_result = $conn->query("SHOW TABLES");
    while ($table_row = $tables_result->fetch_array()) {
        $table = $table_row[0];
        $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $count_result->fetch_assoc()['count'];
        echo "$table: $count 行\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "\n❌ 恢复失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ 数据库恢复操作完成！\n";
?>
