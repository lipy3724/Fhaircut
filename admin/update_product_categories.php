<?php
// 包含数据库连接文件
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// 检查管理员登录状态
if (!is_admin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST数据
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $category_ids = isset($_POST['category_ids']) ? $_POST['category_ids'] : [];
    
    // 验证产品ID
    if ($product_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '无效的产品ID']);
        exit;
    }
    
    // 开始事务
    mysqli_begin_transaction($conn);
    
    try {
        // 删除现有的产品类别关系
        $delete_sql = "DELETE FROM product_categories WHERE product_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $product_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
        
        // 插入新的产品类别关系
        $success_count = 0;
        
        if (!empty($category_ids)) {
            $insert_sql = "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            
            foreach ($category_ids as $category_id) {
                $category_id = intval($category_id);
                if ($category_id > 0) {
                    mysqli_stmt_bind_param($insert_stmt, "ii", $product_id, $category_id);
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $success_count++;
                    }
                }
            }
            
            mysqli_stmt_close($insert_stmt);
        }
        
        // 提交事务
        mysqli_commit($conn);
        
        // 返回成功响应
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => '产品类别更新成功',
            'updated_count' => $success_count
        ]);
        
    } catch (Exception $e) {
        // 回滚事务
        mysqli_rollback($conn);
        
        // 返回错误响应
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => '更新产品类别时发生错误: ' . $e->getMessage()
        ]);
    }
    
} else {
    // 非POST请求返回错误
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '请使用POST请求']);
}
?>
