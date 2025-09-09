<?php
// 确保没有输出缓冲
ob_start();

// 加载数据库配置
require_once '../db_config.php';

// 清除之前的输出
ob_clean();

// 设置响应头为JSON
header('Content-Type: application/json');

// 添加错误处理
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 记录请求信息到日志
$log_message = "接收到更新产品类别请求: " . json_encode($_POST);
error_log($log_message);

// 检查是否有POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    // 记录更详细的请求信息
    error_log("产品ID: " . $product_id);
    error_log("POST数据: " . print_r($_POST, true));
    
    if ($product_id > 0) {
        // 处理产品类别关系
        if (isset($_POST['category_ids']) && !empty($_POST['category_ids'])) {
            // 记录类别信息
            error_log("类别数据: " . print_r($_POST['category_ids'], true));
            // 先删除旧的类别关系
            $delete_sql = "DELETE FROM product_categories WHERE product_id = ?"; 
            if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                mysqli_stmt_bind_param($delete_stmt, "i", $product_id);
                mysqli_stmt_execute($delete_stmt);
                mysqli_stmt_close($delete_stmt);
                
                // 插入新的类别关系
                $insert_sql = "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)"; 
                if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                    $success_count = 0;
                    foreach ($_POST['category_ids'] as $cat_id) {
                        $cat_id = intval($cat_id);
                        if ($cat_id > 0) { // 确保类别ID有效
                            mysqli_stmt_bind_param($insert_stmt, "ii", $product_id, $cat_id);
                            if (mysqli_stmt_execute($insert_stmt)) {
                                $success_count++;
                            } else {
                                error_log("插入类别关系失败: " . mysqli_error($conn) . " - 产品ID: $product_id, 类别ID: $cat_id");
                            }
                        }
                    }
                    mysqli_stmt_close($insert_stmt);
                    
                    // 同时更新产品表中的category_id字段（保留第一个类别作为主类别）
                    if ($success_count > 0 && isset($_POST['category_ids'][0])) {
                        $main_category = intval($_POST['category_ids'][0]);
                        $update_sql = "UPDATE products SET category_id = ? WHERE id = ?";
                        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                            mysqli_stmt_bind_param($update_stmt, "ii", $main_category, $product_id);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "成功更新产品类别关系，共 $success_count 个类别",
                        'product_id' => $product_id,
                        'categories' => array_map('intval', $_POST['category_ids'])
                    ]);
                    exit;
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => "插入类别关系失败: " . mysqli_error($conn),
                        'product_id' => $product_id
                    ]);
                    exit;
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => "删除旧类别关系失败: " . mysqli_error($conn),
                    'product_id' => $product_id
                ]);
                exit;
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => "未提供类别信息或类别数组为空",
                'post_data' => $_POST
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => "无效的产品ID: " . (isset($_POST['product_id']) ? $_POST['product_id'] : '未提供')
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => "请使用POST方法提交请求"
    ]);
    exit;
}
?>
