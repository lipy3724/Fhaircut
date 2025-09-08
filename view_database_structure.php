<?php
/**
 * 数据库表结构查看脚本
 * 用于查看项目中所有数据表的结构信息
 */

// 包含数据库配置文件
require_once "db_config.php";

// 设置字符编码
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库表结构查看器</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #007bff;
            margin-top: 30px;
            border-left: 4px solid #007bff;
            padding-left: 10px;
        }
        .table-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #e3f2fd;
        }
        .primary-key {
            background-color: #fff3cd !important;
            font-weight: bold;
        }
        .foreign-key {
            background-color: #d1ecf1 !important;
        }
        .nullable {
            color: #6c757d;
            font-style: italic;
        }
        .not-null {
            color: #28a745;
            font-weight: bold;
        }
        .auto-increment {
            background-color: #d4edda;
        }
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .legend {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .legend h3 {
            margin-top: 0;
            color: #495057;
        }
        .legend-item {
            display: inline-block;
            margin: 5px 10px 5px 0;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
        }
        .nav {
            background-color: #343a40;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            margin-right: 15px;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .nav a:hover {
            background-color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>数据库表结构查看器</h1>
        
        <?php
        try {
            // 获取数据库名称
            $database_name = "";
            $result = mysqli_query($conn, "SELECT DATABASE() as db_name");
            if ($result && $row = mysqli_fetch_assoc($result)) {
                $database_name = $row['db_name'];
            }
            
            echo "<div class='success'>成功连接到数据库: <strong>$database_name</strong></div>";
            
            // 获取所有表名
            $tables_query = "SHOW TABLES";
            $tables_result = mysqli_query($conn, $tables_query);
            
            if (!$tables_result) {
                throw new Exception("无法获取表列表: " . mysqli_error($conn));
            }
            
            $tables = [];
            while ($row = mysqli_fetch_array($tables_result)) {
                $tables[] = $row[0];
            }
            
            if (empty($tables)) {
                echo "<div class='error'>数据库中没有找到任何表。</div>";
            } else {
                echo "<div class='success'>找到 " . count($tables) . " 个数据表</div>";
                
                // 创建导航菜单
                echo "<div class='nav'>";
                echo "<strong>快速导航:</strong> ";
                foreach ($tables as $table) {
                    echo "<a href='#table_$table'>$table</a>";
                }
                echo "</div>";
                
                // 图例说明
                echo "<div class='legend'>";
                echo "<h3>图例说明</h3>";
                echo "<span class='legend-item primary-key'>主键</span>";
                echo "<span class='legend-item foreign-key'>可能的外键</span>";
                echo "<span class='legend-item auto-increment'>自增字段</span>";
                echo "<span class='legend-item not-null'>非空</span>";
                echo "<span class='legend-item nullable'>可为空</span>";
                echo "</div>";
                
                // 显示每个表的结构
                foreach ($tables as $table) {
                    echo "<h2 id='table_$table'>表: $table</h2>";
                    
                    // 获取表的基本信息
                    $table_info_query = "SHOW TABLE STATUS LIKE '$table'";
                    $table_info_result = mysqli_query($conn, $table_info_query);
                    $table_info = mysqli_fetch_assoc($table_info_result);
                    
                    if ($table_info) {
                        echo "<div class='table-info'>";
                        echo "<strong>引擎:</strong> " . ($table_info['Engine'] ?? 'N/A') . " | ";
                        echo "<strong>字符集:</strong> " . ($table_info['Collation'] ?? 'N/A') . " | ";
                        echo "<strong>行数:</strong> " . ($table_info['Rows'] ?? 'N/A') . " | ";
                        echo "<strong>数据大小:</strong> " . formatBytes($table_info['Data_length'] ?? 0) . " | ";
                        echo "<strong>创建时间:</strong> " . ($table_info['Create_time'] ?? 'N/A');
                        if ($table_info['Comment']) {
                            echo "<br><strong>注释:</strong> " . $table_info['Comment'];
                        }
                        echo "</div>";
                    }
                    
                    // 获取表结构
                    $structure_query = "DESCRIBE $table";
                    $structure_result = mysqli_query($conn, $structure_query);
                    
                    if (!$structure_result) {
                        echo "<div class='error'>无法获取表 $table 的结构: " . mysqli_error($conn) . "</div>";
                        continue;
                    }
                    
                    echo "<table>";
                    echo "<tr>";
                    echo "<th>字段名</th>";
                    echo "<th>数据类型</th>";
                    echo "<th>是否为空</th>";
                    echo "<th>键类型</th>";
                    echo "<th>默认值</th>";
                    echo "<th>其他属性</th>";
                    echo "</tr>";
                    
                    while ($field = mysqli_fetch_assoc($structure_result)) {
                        $row_class = "";
                        if ($field['Key'] == 'PRI') {
                            $row_class = "primary-key";
                        } elseif (strpos($field['Field'], '_id') !== false && $field['Field'] != 'id') {
                            $row_class = "foreign-key";
                        } elseif (strpos($field['Extra'], 'auto_increment') !== false) {
                            $row_class = "auto-increment";
                        }
                        
                        echo "<tr class='$row_class'>";
                        echo "<td><strong>" . htmlspecialchars($field['Field']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($field['Type']) . "</td>";
                        
                        $null_class = ($field['Null'] == 'YES') ? 'nullable' : 'not-null';
                        echo "<td class='$null_class'>" . ($field['Null'] == 'YES' ? '可为空' : '非空') . "</td>";
                        
                        $key_info = '';
                        switch ($field['Key']) {
                            case 'PRI':
                                $key_info = '主键';
                                break;
                            case 'UNI':
                                $key_info = '唯一键';
                                break;
                            case 'MUL':
                                $key_info = '索引';
                                break;
                            default:
                                $key_info = '-';
                        }
                        echo "<td>" . $key_info . "</td>";
                        
                        echo "<td>" . ($field['Default'] !== null ? htmlspecialchars($field['Default']) : '<em>NULL</em>') . "</td>";
                        echo "<td>" . ($field['Extra'] ? htmlspecialchars($field['Extra']) : '-') . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    // 获取索引信息
                    $index_query = "SHOW INDEX FROM $table";
                    $index_result = mysqli_query($conn, $index_query);
                    
                    if ($index_result && mysqli_num_rows($index_result) > 0) {
                        echo "<h4>索引信息</h4>";
                        echo "<table>";
                        echo "<tr><th>索引名</th><th>字段</th><th>索引类型</th><th>是否唯一</th></tr>";
                        
                        while ($index = mysqli_fetch_assoc($index_result)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($index['Index_type']) . "</td>";
                            echo "<td>" . ($index['Non_unique'] == 0 ? '是' : '否') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>错误: " . htmlspecialchars($e->getMessage()) . "</div>";
        } finally {
            if (isset($conn)) {
                mysqli_close($conn);
            }
        }
        
        /**
         * 格式化字节大小
         */
        function formatBytes($size, $precision = 2) {
            if ($size == 0) return '0 B';
            
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            $base = log($size, 1024);
            return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
        }
        ?>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #6c757d;">
            <p>数据库结构查看完成 - 生成时间: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
