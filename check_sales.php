<?php
require_once __DIR__ . '/db_config.php';

// 查询所有产品的销量数据
$sql = "SELECT id, title, sales FROM products ORDER BY sales DESC";
$result = mysqli_query($conn, $sql);

echo "<h2>产品销量数据检查</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>产品名</th><th>销量</th></tr>";

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . $row['sales'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3'>没有找到产品数据</td></tr>";
}

echo "</table>";

// 检查示例产品的所有字段
$product_id = 2; // 检查ID为2的产品作为示例
echo "<h2>产品ID $product_id 的完整数据</h2>";
$sql = "SELECT * FROM products WHERE id = $product_id";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo "<table border='1' cellpadding='5'>";
    foreach ($row as $field => $value) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($field) . "</strong></td>";
        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "未找到ID为 $product_id 的产品";
}

// 测试更新销量
echo "<h2>更新销量测试</h2>";
// 随机选择一个产品
$update_id = rand(1, 10); 
$new_sales = rand(10, 100);

// 更新销量
$update_sql = "UPDATE products SET sales = $new_sales WHERE id = $update_id";
if (mysqli_query($conn, $update_sql)) {
    echo "已将产品ID $update_id 的销量更新为 $new_sales";
    
    // 验证更新
    $verify_sql = "SELECT id, title, sales FROM products WHERE id = $update_id";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if ($verify_result && $row = mysqli_fetch_assoc($verify_result)) {
        echo "<p>验证结果: 产品 " . htmlspecialchars($row['title']) . " (ID: " . $row['id'] . ") 的销量现在是 " . $row['sales'] . "</p>";
    }
} else {
    echo "更新失败: " . mysqli_error($conn);
}
?> 