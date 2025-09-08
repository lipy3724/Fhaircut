<?php
require_once __DIR__ . '/db_config.php';

// 获取所有产品ID
$sql = "SELECT id, title FROM products";
$result = mysqli_query($conn, $sql);

echo "<h1>更新产品销量数据</h1>";
echo "<p>正在为产品添加随机销量数据...</p>";

if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>产品ID</th><th>产品名</th><th>原销量</th><th>新销量</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $title = $row['title'];
        
        // 获取当前销量
        $check_sql = "SELECT sales FROM products WHERE id = $id";
        $check_result = mysqli_query($conn, $check_sql);
        $check_row = mysqli_fetch_assoc($check_result);
        $old_sales = $check_row['sales'];
        
        // 生成随机销量 (1-100之间)
        $new_sales = rand(1, 100);
        
        // 更新销量
        $update_sql = "UPDATE products SET sales = $new_sales WHERE id = $id";
        
        if (mysqli_query($conn, $update_sql)) {
            echo "<tr>";
            echo "<td>" . $id . "</td>";
            echo "<td>" . htmlspecialchars($title) . "</td>";
            echo "<td>" . $old_sales . "</td>";
            echo "<td>" . $new_sales . "</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td>" . $id . "</td>";
            echo "<td>" . htmlspecialchars($title) . "</td>";
            echo "<td colspan='2'>更新失败: " . mysqli_error($conn) . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    echo "<p>更新完成！<a href='main.php?sort=sales&order=desc'>查看按销量排序的产品</a></p>";
    
} else {
    echo "<p>未找到任何产品数据</p>";
}

mysqli_close($conn);
?> 