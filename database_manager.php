<?php
/**
 * 数据库管理工具
 * 提供备份、恢复、查看等功能的统一界面
 */

// 数据库连接配置
$host = "localhost";
$username = "root";
$password = "";
$database = "jianfa_db";

$backup_dir = __DIR__ . '/database_backups/';

function showMenu() {
    echo "\n=== 数据库管理工具 ===\n";
    echo "1. 创建数据库备份\n";
    echo "2. 查看现有备份\n";
    echo "3. 恢复数据库\n";
    echo "4. 查看数据库状态\n";
    echo "5. 删除旧备份\n";
    echo "0. 退出\n";
    echo "请选择操作 (0-5): ";
}

function createBackup() {
    echo "\n=== 创建数据库备份 ===\n";
    $result = shell_exec("/Applications/XAMPP/xamppfiles/bin/php " . __DIR__ . "/backup_database.php");
    echo $result;
}

function listBackups() {
    global $backup_dir;
    
    echo "\n=== 现有备份文件 ===\n";
    
    $backup_files = glob($backup_dir . "*.sql");
    if (empty($backup_files)) {
        echo "没有找到备份文件。\n";
        return;
    }
    
    // 按修改时间排序（最新的在前）
    usort($backup_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $total_size = 0;
    foreach ($backup_files as $index => $file) {
        $basename = basename($file);
        $size = filesize($file);
        $size_mb = round($size / 1024 / 1024, 2);
        $time = date('Y-m-d H:i:s', filemtime($file));
        
        echo sprintf("%d. %s\n", $index + 1, $basename);
        echo sprintf("   大小: %.2f MB\n", $size_mb);
        echo sprintf("   时间: %s\n", $time);
        echo sprintf("   路径: %s\n\n", $file);
        
        $total_size += $size;
    }
    
    echo "总计: " . count($backup_files) . " 个备份文件\n";
    echo "总大小: " . round($total_size / 1024 / 1024, 2) . " MB\n";
}

function restoreDatabase() {
    echo "\n=== 恢复数据库 ===\n";
    echo "注意: 这将启动交互式恢复程序\n";
    echo "按任意键继续...";
    fgets(STDIN);
    
    $cmd = "/Applications/XAMPP/xamppfiles/bin/php " . __DIR__ . "/restore_database.php";
    system($cmd);
}

function showDatabaseStatus() {
    global $host, $username, $password, $database;
    
    echo "\n=== 数据库状态 ===\n";
    
    try {
        $conn = new mysqli($host, $username, $password, $database);
        if ($conn->connect_error) {
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }
        
        echo "✓ 数据库连接: 成功\n";
        echo "数据库名称: $database\n";
        echo "服务器: $host\n";
        
        // 获取数据库版本
        $version_result = $conn->query("SELECT VERSION() as version");
        $version = $version_result->fetch_assoc()['version'];
        echo "MySQL版本: $version\n";
        
        // 获取表信息
        $tables_result = $conn->query("SHOW TABLES");
        $table_count = $tables_result->num_rows;
        echo "表数量: $table_count\n\n";
        
        echo "=== 表详情 ===\n";
        printf("%-25s %-10s %-15s\n", "表名", "行数", "大小(KB)");
        echo str_repeat("-", 50) . "\n";
        
        $tables_result = $conn->query("SHOW TABLES");
        $total_rows = 0;
        $total_size = 0;
        
        while ($table_row = $tables_result->fetch_array()) {
            $table = $table_row[0];
            
            // 获取行数
            $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_result->fetch_assoc()['count'];
            $total_rows += $count;
            
            // 获取表大小
            $size_result = $conn->query("SELECT 
                ROUND(((data_length + index_length) / 1024), 2) AS 'size_kb'
                FROM information_schema.TABLES 
                WHERE table_schema = '$database' AND table_name = '$table'");
            $size_kb = $size_result->fetch_assoc()['size_kb'] ?? 0;
            $total_size += $size_kb;
            
            printf("%-25s %-10d %-15.2f\n", $table, $count, $size_kb);
        }
        
        echo str_repeat("-", 50) . "\n";
        printf("%-25s %-10d %-15.2f\n", "总计", $total_rows, $total_size);
        
        $conn->close();
        
    } catch (Exception $e) {
        echo "❌ 错误: " . $e->getMessage() . "\n";
    }
}

function deleteOldBackups() {
    global $backup_dir;
    
    echo "\n=== 删除旧备份 ===\n";
    
    $backup_files = glob($backup_dir . "*.sql");
    if (empty($backup_files)) {
        echo "没有找到备份文件。\n";
        return;
    }
    
    // 按修改时间排序（最旧的在前）
    usort($backup_files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    echo "发现 " . count($backup_files) . " 个备份文件\n";
    echo "建议保留最新的 3-5 个备份文件\n\n";
    
    if (count($backup_files) <= 3) {
        echo "备份文件数量较少，建议不要删除。\n";
        return;
    }
    
    // 显示可删除的文件（保留最新的3个）
    $files_to_delete = array_slice($backup_files, 0, -3);
    
    if (empty($files_to_delete)) {
        echo "没有需要删除的旧备份。\n";
        return;
    }
    
    echo "以下备份文件可以删除:\n";
    foreach ($files_to_delete as $index => $file) {
        $basename = basename($file);
        $time = date('Y-m-d H:i:s', filemtime($file));
        echo sprintf("%d. %s (%s)\n", $index + 1, $basename, $time);
    }
    
    echo "\n确认删除这些文件吗？(输入 'yes' 确认): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'yes') {
        echo "删除操作已取消。\n";
        return;
    }
    
    $deleted_count = 0;
    foreach ($files_to_delete as $file) {
        if (unlink($file)) {
            $deleted_count++;
            echo "✓ 已删除: " . basename($file) . "\n";
        } else {
            echo "❌ 删除失败: " . basename($file) . "\n";
        }
    }
    
    echo "\n删除完成！已删除 $deleted_count 个文件。\n";
}

// 主程序
while (true) {
    showMenu();
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            createBackup();
            break;
        case '2':
            listBackups();
            break;
        case '3':
            restoreDatabase();
            break;
        case '4':
            showDatabaseStatus();
            break;
        case '5':
            deleteOldBackups();
            break;
        case '0':
            echo "再见！\n";
            exit(0);
        default:
            echo "无效的选择，请重新输入。\n";
    }
    
    echo "\n按任意键继续...";
    fgets(STDIN);
}
?>
