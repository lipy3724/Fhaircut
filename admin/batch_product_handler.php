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
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action == 'batch_add_product') {
        // 处理批量上传产品
        $product_count = isset($_POST['product_count']) ? intval($_POST['product_count']) : 0;
        $success_count = 0;
        $error_count = 0;
        $messages = [];
        
        // 循环处理每个产品
        for ($i = 1; $i <= $product_count; $i++) {
            if (!isset($_POST['products'][$i]['title']) || empty($_POST['products'][$i]['title'])) {
                continue; // 跳过空表单
            }
            
            $custom_id = isset($_POST['products'][$i]['custom_id']) ? intval($_POST['products'][$i]['custom_id']) : 0;
            $title = trim($_POST['products'][$i]['title']);
            $subtitle = isset($_POST['products'][$i]['subtitle']) ? trim($_POST['products'][$i]['subtitle']) : '';
            $price = isset($_POST['products'][$i]['price']) ? floatval($_POST['products'][$i]['price']) : 0;
            $photo_pack_price = isset($_POST['products'][$i]['photo_pack_price']) ? floatval($_POST['products'][$i]['photo_pack_price']) : 0;
            $category_ids = isset($_POST['products'][$i]['category_ids']) ? $_POST['products'][$i]['category_ids'] : [];
            $show_on_homepage = isset($_POST['products'][$i]['show_on_homepage']) ? intval($_POST['products'][$i]['show_on_homepage']) : 0;
            
            // 验证必填字段
            $product_errors = [];
            if (empty($title)) {
                $product_errors[] = "产品 #$i: 标题不能为空";
            }
            if ($price <= 0) {
                $product_errors[] = "产品 #$i: 价格必须大于0";
            }
            if (empty($category_ids)) {
                $product_errors[] = "产品 #$i: 必须选择分类";
            }
            
            // 验证自定义ID
            if ($custom_id > 0) {
                // 检查ID是否已存在
                $check_id_sql = "SELECT id FROM products WHERE id = ?";
                if ($stmt = mysqli_prepare($conn, $check_id_sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $custom_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_store_result($stmt);
                    
                    if (mysqli_stmt_num_rows($stmt) > 0) {
                        $product_errors[] = "产品 #$i: ID $custom_id 已存在，请选择其他ID";
                    }
                    
                    mysqli_stmt_close($stmt);
                }
            }
            
            // 如果有错误，记录并继续下一个
            if (!empty($product_errors)) {
                $error_count++;
                $messages = array_merge($messages, $product_errors);
                continue;
            }
            
            // 处理文件上传
            $image_paths = [
                'image' => '',
                'image2' => '',
                'image3' => '',
                'image4' => '',
                'member_image1' => '',
                'member_image2' => '',
                'member_image3' => '',
                'member_image4' => '',
                'member_image5' => '',
                'member_image6' => ''
            ];
            
            // 处理游客图片上传（带水印）
            // 检查是否有游客图片上传
            if (isset($_FILES['products']['name'][$i]['guest_images']) && is_array($_FILES['products']['name'][$i]['guest_images'])) {
                $upload_dir = '../uploads/products/';
                
                // 检查并创建目录
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // 游客图片对应的数据库字段
                $guest_image_fields = ['image', 'image2', 'image3', 'image4'];
                
                // 处理每个上传的文件
                $file_count = count($_FILES['products']['name'][$i]['guest_images']);
                $file_count = min($file_count, 4); // 限制最多处理4张图片
                
                for ($j = 0; $j < $file_count; $j++) {
                    // 检查文件是否成功上传
                    if ($_FILES['products']['error'][$i]['guest_images'][$j] == 0) {
                        $file_name = time() . '_' . mt_rand(1000, 9999) . '_' . $_FILES['products']['name'][$i]['guest_images'][$j];
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['products']['tmp_name'][$i]['guest_images'][$j], $file_path)) {
                            // 添加水印
                            // 这里可以添加水印处理代码
                            
                            // 保存路径到对应字段
                            if ($j < count($guest_image_fields)) {
                                $image_paths[$guest_image_fields[$j]] = 'uploads/products/' . $file_name;
                            }
                        }
                    }
                }
            }
            
            // 处理会员图片上传（无水印）
            // 检查是否有会员图片上传
            if (isset($_FILES['products']['name'][$i]['member_images']) && is_array($_FILES['products']['name'][$i]['member_images'])) {
                $upload_dir = '../uploads/products/members/';
                
                // 检查并创建目录
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // 会员图片对应的数据库字段
                $member_image_fields = ['member_image1', 'member_image2', 'member_image3', 'member_image4', 'member_image5', 'member_image6'];
                
                // 处理每个上传的文件
                $file_count = count($_FILES['products']['name'][$i]['member_images']);
                $file_count = min($file_count, 6); // 限制最多处理6张图片
                
                for ($j = 0; $j < $file_count; $j++) {
                    // 检查文件是否成功上传
                    if ($_FILES['products']['error'][$i]['member_images'][$j] == 0) {
                        $file_name = time() . '_' . mt_rand(1000, 9999) . '_' . $_FILES['products']['name'][$i]['member_images'][$j];
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['products']['tmp_name'][$i]['member_images'][$j], $file_path)) {
                            // 保存路径到对应字段
                            if ($j < count($member_image_fields)) {
                                $image_paths[$member_image_fields[$j]] = 'uploads/products/members/' . $file_name;
                            }
                        }
                    }
                }
            }
            
            // 保存产品到数据库
            if ($custom_id > 0) {
                // 使用自定义ID
                $sql = "INSERT INTO products (id, title, subtitle, price, photo_pack_price, show_on_homepage, ";
                $sql .= "image, image2, image3, image4, ";
                $sql .= "member_image1, member_image2, member_image3, member_image4, member_image5, member_image6) ";
                $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "issddiissssssssss", 
                        $custom_id, $title, $subtitle, $price, $photo_pack_price, $show_on_homepage,
                        $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'],
                        $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                        $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6']
                    );
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $product_id = $custom_id;
                        
                        // 处理产品类别关系
                        if (!empty($category_ids)) {
                            $insert_cat_sql = "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)"; 
                            if ($insert_cat_stmt = mysqli_prepare($conn, $insert_cat_sql)) {
                                $cat_success_count = 0;
                                foreach ($category_ids as $cat_id) {
                                    $cat_id = intval($cat_id);
                                    if ($cat_id > 0) { // 确保类别ID有效
                                        mysqli_stmt_bind_param($insert_cat_stmt, "ii", $product_id, $cat_id);
                                        if (mysqli_stmt_execute($insert_cat_stmt)) {
                                            $cat_success_count++;
                                        } else {
                                            error_log("插入类别关系失败: " . mysqli_error($conn) . " - 产品ID: $product_id, 类别ID: $cat_id");
                                        }
                                    }
                                }
                                mysqli_stmt_close($insert_cat_stmt);
                                error_log("添加产品类别关系成功，产品ID: $product_id, 共添加 $cat_success_count 个类别");
                                
                                // 更新产品表中的category_id字段（保留第一个类别作为主类别）
                                if ($cat_success_count > 0 && isset($category_ids[0])) {
                                    $main_category = intval($category_ids[0]);
                                    $update_cat_sql = "UPDATE products SET category_id = ? WHERE id = ?";
                                    if ($update_cat_stmt = mysqli_prepare($conn, $update_cat_sql)) {
                                        mysqli_stmt_bind_param($update_cat_stmt, "ii", $main_category, $product_id);
                                        mysqli_stmt_execute($update_cat_stmt);
                                        mysqli_stmt_close($update_cat_stmt);
                                        error_log("更新产品主类别成功，产品ID: $product_id, 主类别ID: $main_category");
                                        
                                        // 调试信息 - 记录所有类别ID
                                        $cat_ids_debug = implode(", ", $category_ids);
                                        error_log("产品ID: $product_id 的所有类别: $cat_ids_debug");
                                    }
                                }
                            }
                        }
                        
                        $success_count++;
                        $messages[] = "产品 #$i: 添加成功，ID: $product_id";
                    } else {
                        $error_count++;
                        $messages[] = "产品 #$i: 添加失败: " . mysqli_stmt_error($stmt);
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $error_count++;
                    $messages[] = "产品 #$i: 准备SQL语句失败: " . mysqli_error($conn);
                }
            } else {
                // 自动分配ID
                $sql = "INSERT INTO products (title, subtitle, price, photo_pack_price, show_on_homepage, ";
                $sql .= "image, image2, image3, image4, ";
                $sql .= "member_image1, member_image2, member_image3, member_image4, member_image5, member_image6) ";
                $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssddisssssssssss", 
                        $title, $subtitle, $price, $photo_pack_price, $show_on_homepage,
                        $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'],
                        $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                        $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6']
                    );
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $product_id = mysqli_insert_id($conn);
                        
                        // 处理产品类别关系
                        if (!empty($category_ids)) {
                            $insert_cat_sql = "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)"; 
                            if ($insert_cat_stmt = mysqli_prepare($conn, $insert_cat_sql)) {
                                $cat_success_count = 0;
                                foreach ($category_ids as $cat_id) {
                                    $cat_id = intval($cat_id);
                                    if ($cat_id > 0) { // 确保类别ID有效
                                        mysqli_stmt_bind_param($insert_cat_stmt, "ii", $product_id, $cat_id);
                                        if (mysqli_stmt_execute($insert_cat_stmt)) {
                                            $cat_success_count++;
                                        } else {
                                            error_log("插入类别关系失败: " . mysqli_error($conn) . " - 产品ID: $product_id, 类别ID: $cat_id");
                                        }
                                    }
                                }
                                mysqli_stmt_close($insert_cat_stmt);
                                error_log("添加产品类别关系成功，产品ID: $product_id, 共添加 $cat_success_count 个类别");
                                
                                // 更新产品表中的category_id字段（保留第一个类别作为主类别）
                                if ($cat_success_count > 0 && isset($category_ids[0])) {
                                    $main_category = intval($category_ids[0]);
                                    $update_cat_sql = "UPDATE products SET category_id = ? WHERE id = ?";
                                    if ($update_cat_stmt = mysqli_prepare($conn, $update_cat_sql)) {
                                        mysqli_stmt_bind_param($update_cat_stmt, "ii", $main_category, $product_id);
                                        mysqli_stmt_execute($update_cat_stmt);
                                        mysqli_stmt_close($update_cat_stmt);
                                        error_log("更新产品主类别成功，产品ID: $product_id, 主类别ID: $main_category");
                                        
                                        // 调试信息 - 记录所有类别ID
                                        $cat_ids_debug = implode(", ", $category_ids);
                                        error_log("产品ID: $product_id 的所有类别: $cat_ids_debug");
                                    }
                                }
                            }
                        }
                        
                        $success_count++;
                        $messages[] = "产品 #$i: 添加成功，ID: $product_id";
                    } else {
                        $error_count++;
                        $messages[] = "产品 #$i: 添加失败: " . mysqli_stmt_error($stmt);
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $error_count++;
                    $messages[] = "产品 #$i: 准备SQL语句失败: " . mysqli_error($conn);
                }
            }
        }
        
        // 设置响应消息
        if ($success_count > 0) {
            $success_message = "成功添加 $success_count 个产品";
            if ($error_count > 0) {
                $success_message .= "，$error_count 个产品添加失败";
            }
        } else if ($error_count > 0) {
            $error_message = "所有产品添加失败";
        } else {
            $error_message = "没有提交有效的产品数据";
        }
        
        // 重定向回产品列表页面
        $_SESSION['success_message'] = $success_message ?? '';
        $_SESSION['error_message'] = $error_message ?? '';
        $_SESSION['messages'] = $messages;
        
        header('Location: admin.php?page=products');
        exit;
    }
    
} else {
    // 非POST请求返回错误
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '请使用POST请求']);
}
?>
