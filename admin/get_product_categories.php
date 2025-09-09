<?php
// 加载数据库配置
require_once '../db_config.php';

// 设置响应头为JSON
header('Content-Type: application/json');

// 添加错误处理
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查是否有产品ID参数
if (isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    
    // 添加调试日志
    error_log("获取产品类别，产品ID: $product_id");
    
    // 获取产品的所有类别
    $sql = "SELECT category_id FROM product_categories WHERE product_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = intval($row['category_id']);
        }
        
        // 如果没有找到类别关系，尝试从产品表中获取旧的category_id
        if (empty($categories)) {
            $old_cat_sql = "SELECT category_id FROM products WHERE id = ?";
            if ($old_stmt = mysqli_prepare($conn, $old_cat_sql)) {
                mysqli_stmt_bind_param($old_stmt, "i", $product_id);
                mysqli_stmt_execute($old_stmt);
                $old_result = mysqli_stmt_get_result($old_stmt);
                
                if ($old_row = mysqli_fetch_assoc($old_result)) {
                    if (!empty($old_row['category_id'])) {
                        $categories[] = intval($old_row['category_id']);
                    }
                }
                
                mysqli_stmt_close($old_stmt);
            }
        }
        
        // 记录找到的类别
        error_log("产品ID $product_id 的类别: " . implode(", ", $categories));
        
        echo json_encode([
            'success' => true,
            'categories' => $categories,
            'product_id' => $product_id
        ]);
        
        mysqli_stmt_close($stmt);
    } else {
        error_log("获取产品类别失败: " . mysqli_error($conn));
        echo json_encode([
            'success' => false,
            'message' => "查询失败: " . mysqli_error($conn),
            'product_id' => $product_id
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => "未提供产品ID"
    ]);
}

// 关闭数据库连接
mysqli_close($conn);
?>
