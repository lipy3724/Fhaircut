<?php
// 开启输出缓冲，解决header已发送的问题
ob_start();

// 启用错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 创建hair表（如果不存在）
$create_table_sql = "CREATE TABLE IF NOT EXISTS hair (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT '头发标题',
    description TEXT COMMENT '头发简介',
    length DECIMAL(5,2) DEFAULT 0.00 COMMENT '长度(cm)',
    weight DECIMAL(8,2) DEFAULT 0.00 COMMENT '重量(g)',
    value DECIMAL(10,2) DEFAULT 0.00 COMMENT '价值($)',
    image VARCHAR(255) COMMENT '主图片',
    image2 VARCHAR(255) COMMENT '图片2',
    image3 VARCHAR(255) COMMENT '图片3',
    image4 VARCHAR(255) COMMENT '图片4',
    image5 VARCHAR(255) COMMENT '图片5',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='头发信息表'";

if (!mysqli_query($conn, $create_table_sql)) {
    // 表创建失败，但不阻止程序继续执行
    // echo "Warning: " . mysqli_error($conn);
}


// 处理批量删除操作
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batch_delete_hair') {
    if (isset($_POST['hair_ids']) && is_array($_POST['hair_ids']) && !empty($_POST['hair_ids'])) {
        $hair_ids = array_map('intval', $_POST['hair_ids']);
        $deleted_count = 0;
        $error_count = 0;
        
        foreach ($hair_ids as $hair_id) {
            // 先获取头发信息，以便删除相关文件
            $sql = "SELECT * FROM hair WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $hair_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $hair_data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                if ($hair_data) {
                    // 删除相关图片文件
                    $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
                    foreach ($image_fields as $field) {
                        if (!empty($hair_data[$field])) {
                            $file_path = "../" . $hair_data[$field];
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    }
                    
                    // 删除数据库记录
                    $delete_sql = "DELETE FROM hair WHERE id = ?";
                    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                        mysqli_stmt_bind_param($delete_stmt, "i", $hair_id);
                        if (mysqli_stmt_execute($delete_stmt)) {
                            $deleted_count++;
                        } else {
                            $error_count++;
                        }
                        mysqli_stmt_close($delete_stmt);
                    }
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($deleted_count > 0) {
            $success_message = "成功删除 {$deleted_count} 个头发记录！";
        }
        
        if ($error_count > 0) {
            $errors[] = "删除过程中有 {$error_count} 个头发记录删除失败！";
        }
        
        // 重定向到列表页面
        header("Location: admin.php?page=hair");
        exit;
    } else {
        $errors[] = "请选择要删除的头发记录！";
    }
}


// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // 先获取头发信息，以便删除相关文件
    $sql = "SELECT * FROM hair WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hair_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($hair_data) {
            // 删除相关图片文件
            $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
            foreach ($image_fields as $field) {
                if (!empty($hair_data[$field])) {
                    $file_path = "../" . $hair_data[$field];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            
            // 删除数据库记录
            $delete_sql = "DELETE FROM hair WHERE id = ?";
            if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                mysqli_stmt_bind_param($delete_stmt, "i", $id);
                if (mysqli_stmt_execute($delete_stmt)) {
                    $success_message = "头发记录删除成功！";
                } else {
                    $errors[] = "删除头发记录时出错：" . mysqli_error($conn);
                }
                mysqli_stmt_close($delete_stmt);
            }
        }
    }
}

// 处理编辑/添加操作
$edit_mode = false;
$hair_data = [];
$errors = [];
$success_message = '';

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $id = intval($_GET['id']);
    
    // 获取头发数据
    $sql = "SELECT * FROM hair WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hair_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$hair_data) {
            $errors[] = "找不到指定的头发记录！";
            $edit_mode = false;
        }
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $length = floatval($_POST['length'] ?? 0);
    $weight = floatval($_POST['weight'] ?? 0);
    $value = floatval($_POST['value'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    // 基本验证
    if (empty($title)) {
        $errors[] = "头发标题不能为空！";
    }
    
    if (empty($errors)) {
        // 处理文件上传
        $upload_dir = "./uploads/hair/";
        $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
        $uploaded_images = [];
        
        // 确保上传目录存在
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // 处理多文件上传
        if (isset($_FILES['hair_images']) && !empty($_FILES['hair_images']['name'][0])) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_files = 5;
            
            $file_count = count($_FILES['hair_images']['name']);
            $file_count = min($file_count, $max_files); // 限制最多5张图片
            
            for ($i = 0; $i < $file_count; $i++) {
                // 检查文件是否成功上传
                if ($_FILES['hair_images']['error'][$i] == UPLOAD_ERR_OK) {
                    $file_name = $_FILES['hair_images']['name'][$i];
                    $file_tmp = $_FILES['hair_images']['tmp_name'][$i];
                    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // 验证文件类型
                    if (!in_array($file_extension, $allowed_extensions)) {
                        $errors[] = "文件 {$file_name} 格式不支持，请上传 jpg, jpeg, png, gif 或 webp 格式的图片！";
                        continue;
                    }
                    
                    // 生成唯一文件名
                    $unique_name = uniqid() . '_' . $i . '.' . $file_extension;
                    $upload_path = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $uploaded_images[$image_fields[$i]] = "uploads/hair/" . $unique_name;
                    } else {
                        $errors[] = "上传文件 {$file_name} 失败！";
                    }
                } else if ($_FILES['hair_images']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                    $errors[] = "图片" . ($i + 1) . "上传失败！";
                }
            }
        }
        
        if (empty($errors)) {
            if ($action == 'edit' && isset($_POST['hair_id'])) {
                // 编辑现有头发
                $hair_id = intval($_POST['hair_id']);
                
                // 构建更新SQL
                $update_fields = ['title = ?', 'description = ?', 'length = ?', 'weight = ?', 'value = ?'];
                $update_values = [$title, $description, $length, $weight, $value];
                $update_types = 'ssddd';
                
                // 添加图片字段
                foreach ($image_fields as $field) {
                    if (isset($uploaded_images[$field])) {
                        $update_fields[] = "{$field} = ?";
                        $update_values[] = $uploaded_images[$field];
                        $update_types .= 's';
                        
                        // 删除旧图片
                        if (!empty($hair_data[$field])) {
                            $old_file = "../" . $hair_data[$field];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                }
                
                $update_values[] = $hair_id;
                $update_types .= 'i';
                
                $sql = "UPDATE hair SET " . implode(', ', $update_fields) . " WHERE id = ?";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, $update_types, ...$update_values);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "头发信息更新成功！";
                        header("Location: admin.php?page=hair");
                        exit;
                    } else {
                        $errors[] = "更新头发信息时出错：" . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                // 添加新头发
                $insert_fields = ['title', 'description', 'length', 'weight', 'value'];
                $insert_placeholders = ['?', '?', '?', '?', '?'];
                $insert_values = [$title, $description, $length, $weight, $value];
                $insert_types = 'ssddd';
                
                // 添加图片字段
                foreach ($image_fields as $field) {
                    if (isset($uploaded_images[$field])) {
                        $insert_fields[] = $field;
                        $insert_placeholders[] = '?';
                        $insert_values[] = $uploaded_images[$field];
                        $insert_types .= 's';
                    }
                }
                
                $sql = "INSERT INTO hair (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, $insert_types, ...$insert_values);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "头发添加成功！";
                        header("Location: admin.php?page=hair");
                        exit;
                    } else {
                        $errors[] = "添加头发时出错：" . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// 处理批量上传
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batch_upload'])) {
    $batch_errors = [];
    $batch_success_count = 0;
    
    if (isset($_FILES['batch_files']) && is_array($_FILES['batch_files']['name'])) {
        $upload_dir = "./uploads/hair/";
        
        // 确保上传目录存在
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_count = count($_FILES['batch_files']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['batch_files']['error'][$i] == UPLOAD_ERR_OK) {
                $file_name = $_FILES['batch_files']['name'][$i];
                $file_tmp = $_FILES['batch_files']['tmp_name'][$i];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $unique_name = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // 从文件名提取标题（去掉扩展名）
                        $title = pathinfo($file_name, PATHINFO_FILENAME);
                        
                        // 插入数据库
                        $sql = "INSERT INTO hair (title, image) VALUES (?, ?)";
                        if ($stmt = mysqli_prepare($conn, $sql)) {
                            $image_path = "uploads/hair/" . $unique_name;
                            mysqli_stmt_bind_param($stmt, "ss", $title, $image_path);
                            if (mysqli_stmt_execute($stmt)) {
                                $batch_success_count++;
                            } else {
                                $batch_errors[] = "保存文件 {$file_name} 到数据库时出错";
                            }
                            mysqli_stmt_close($stmt);
                        }
                    } else {
                        $batch_errors[] = "上传文件 {$file_name} 失败";
                    }
                } else {
                    $batch_errors[] = "文件 {$file_name} 格式不支持";
                }
            }
        }
        
        if ($batch_success_count > 0) {
            $success_message = "成功批量上传 {$batch_success_count} 个头发！";
        }
        
        if (!empty($batch_errors)) {
            $errors = array_merge($errors, $batch_errors);
        }
    } else {
        $errors[] = "请选择要上传的文件！";
    }
}

// 处理批量添加头发
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batch_add_hair') {
    $batch_errors = [];
    $batch_success_count = 0;
    
    if (isset($_POST['hairs']) && is_array($_POST['hairs'])) {
        $upload_dir = "./uploads/hair/";
        
        // 确保上传目录存在
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($_POST['hairs'] as $index => $hair_data) {
            $hair_errors = [];
            
            // 验证必填字段
            if (empty($hair_data['title'])) {
                $hair_errors[] = "头发标题不能为空";
            }
            
            // 处理图片上传
            $uploaded_images = [];
            if (isset($_FILES['hairs']) && isset($_FILES['hairs']['name'][$index]['hair_images'])) {
                $images = $_FILES['hairs']['name'][$index]['hair_images'];
                $tmp_names = $_FILES['hairs']['tmp_name'][$index]['hair_images'];
                $errors_arr = $_FILES['hairs']['error'][$index]['hair_images'];
                
                if (is_array($images)) {
                    for ($i = 0; $i < count($images); $i++) {
                        if ($errors_arr[$i] == UPLOAD_ERR_OK && !empty($images[$i])) {
                            $file_name = $images[$i];
                            $file_tmp = $tmp_names[$i];
                            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            
                            if (in_array($file_extension, $allowed_extensions)) {
                                $unique_name = uniqid() . '.' . $file_extension;
                                $upload_path = $upload_dir . $unique_name;
                                
                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    $uploaded_images[] = "uploads/hair/" . $unique_name;
                                } else {
                                    $hair_errors[] = "上传图片失败: " . $file_name;
                                }
                            } else {
                                $hair_errors[] = "不支持的图片格式: " . $file_name;
                            }
                        }
                    }
                }
            }
            
            // 如果没有上传图片，添加错误
            if (empty($uploaded_images)) {
                $hair_errors[] = "至少需要上传一张图片";
            }
            
            // 如果没有错误，插入数据库
            if (empty($hair_errors)) {
                $title = mysqli_real_escape_string($conn, $hair_data['title']);
                $description = mysqli_real_escape_string($conn, $hair_data['description'] ?? '');
                $length = floatval($hair_data['length'] ?? 0.00);
                $weight = floatval($hair_data['weight'] ?? 0.00);
                $value = floatval($hair_data['value'] ?? 0.00);
                
                // 使用第一张图片作为主图片
                $main_image = $uploaded_images[0];
                $image2 = isset($uploaded_images[1]) ? $uploaded_images[1] : null;
                $image3 = isset($uploaded_images[2]) ? $uploaded_images[2] : null;
                $image4 = isset($uploaded_images[3]) ? $uploaded_images[3] : null;
                $image5 = isset($uploaded_images[4]) ? $uploaded_images[4] : null;
                
                $sql = "INSERT INTO hair (title, description, length, weight, value, image, image2, image3, image4, image5) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssdddsssss", $title, $description, $length, $weight, $value, $main_image, $image2, $image3, $image4, $image5);
                    if (mysqli_stmt_execute($stmt)) {
                        $batch_success_count++;
                    } else {
                        $batch_errors[] = "保存头发 '{$title}' 到数据库时出错：" . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $batch_errors[] = "准备SQL语句时出错：" . mysqli_error($conn);
                }
            } else {
                $batch_errors = array_merge($batch_errors, array_map(function($err) use ($hair_data) {
                    return "头发 '{$hair_data['title']}': " . $err;
                }, $hair_errors));
            }
        }
        
        // 设置成功消息
        if ($batch_success_count > 0) {
            $success_message = "成功批量添加 {$batch_success_count} 个头发！";
            if (empty($batch_errors)) {
                header("Location: admin.php?page=hair");
                exit;
            }
        }
        
        // 合并错误消息
        if (!empty($batch_errors)) {
            $errors = array_merge($errors, $batch_errors);
        }
    } else {
        $errors[] = "没有有效的头发数据！";
    }
}

// 获取所有头发
$hair_list = [];

// 分页设置
$items_per_page_options = [10, 25, 50, 100];
$default_items_per_page = 10;

// 获取用户选择的每页显示数量
$items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : $default_items_per_page;

// 确保每页显示数量是有效选项
if (!in_array($items_per_page, $items_per_page_options)) {
    $items_per_page = $default_items_per_page;
}

// 获取当前页码
$current_page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// 处理搜索功能
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// 构建SQL查询
$sql = "SELECT * FROM hair WHERE 1=1";

// 添加搜索条件
if (!empty($search_term)) {
    if (is_numeric($search_term)) {
        $sql .= " AND (id = " . intval($search_term) . " OR title LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%')";
    } else {
        $sql .= " AND title LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%'";
    }
}

// 获取总记录数
$count_sql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_table";
$count_result = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_result && $count_row = mysqli_fetch_assoc($count_result)) {
    $total_records = $count_row['total'];
}

// 计算总页数
$total_pages = ceil($total_records / $items_per_page);

// 确保当前页码不超过总页数
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// 计算LIMIT子句的偏移量
$offset = ($current_page - 1) * $items_per_page;

// 添加排序和分页
$sql .= " ORDER BY id DESC LIMIT " . $offset . ", " . $items_per_page;

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hair_list[] = $row;
    }
    mysqli_free_result($result);
}
?>

<div class="admin-content">
    <h2>头发管理</h2>
    
    <?php if (isset($success_message) && !empty($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
        <p><?php echo $error; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($edit_mode || isset($_GET['action']) && ($_GET['action'] == 'add' || $_GET['action'] == 'batch_add')): ?>
    <div class="admin-form-container" style="width: 100%; padding: 0 20px;">
        <h3><?php 
            if ($edit_mode) {
                echo '编辑头发';
            } elseif (isset($_GET['action']) && $_GET['action'] == 'batch_add') {
                echo '批量上传头发';
            } else {
                echo '添加新头发';
            }
        ?></h3>
        
        <?php if (isset($_GET['action']) && $_GET['action'] == 'batch_add'): ?>
        <!-- 批量上传头发表单 -->
        <style>
            .form-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 20px; 
            }
            .form-grid .span-2 { grid-column: span 2; }
            @media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }
            
            .hair-form {
                background-color: #f8f9fa;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 8px;
                border-left: 4px solid #e75480;
                width: 100%;
            }
            
            .form-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }

            /* 批量上传表单样式 - 与单个表单保持一致 */
            .hair-form .form-group {
                margin-bottom: 15px;
            }

            .hair-form .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }

            .hair-form .form-group input,
            .hair-form .form-group select,
            .hair-form .form-group textarea {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
            }

            .hair-form .form-group textarea {
                resize: vertical;
            }

            .hair-form .form-text {
                font-size: 12px;
                color: #6c757d;
                margin-top: 5px;
                display: block;
            }

            .hair-form .format-hint {
                font-size: 12px;
                color: #6c757d;
                margin-top: 5px;
                font-style: italic;
            }
            
            .form-header h4 {
                margin: 0;
                color: #e75480;
                font-size: 18px;
            }
            
            .remove-form-button {
                background-color: #dc3545;
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                transition: background-color 0.2s;
            }
            
            .remove-form-button:hover {
                background-color: #c82333;
            }
            
            .batch-controls {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .form-count {
                font-weight: bold;
                color: #e75480;
            }
        </style>
        <div class="batch-upload-container">
            <p>在此页面您可以一次添加多个头发。表单与单个头发添加相同，可以根据需要添加多个头发。</p>
            <div class="batch-controls">
                <button type="button" id="add-hair-form" class="add-button">添加一个头发表单</button>
                <button type="button" id="batch-save-all" class="btn btn-primary" style="margin-left: 15px;">批量保存所有头发</button>
                                  <span class="form-count">当前头发数量: <span id="hair-count">1</span> / 10</span>
            </div>
        </div>
        <form method="post" enctype="multipart/form-data" id="batch-hair-form">
            <input type="hidden" name="action" value="batch_add_hair">
            <input type="hidden" name="hair_count" id="hair-count-input" value="1">
            <div id="hair-forms-container">
                <!-- 初始表单将在这里动态添加 -->
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">批量保存所有头发</button>
                <a href="admin.php?page=hair" class="btn btn-secondary">取消</a>
            </div>
        </form>
        <?php else: ?>
        <!-- 单个头发表单 -->
        <form action="" method="post" enctype="multipart/form-data" class="admin-form">
            <?php if ($edit_mode): ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="hair_id" value="<?php echo $hair_data['id']; ?>">
            <?php endif; ?>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="title">头发标题 *：</label>
                    <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($hair_data['title'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="length">长度 (cm)：</label>
                    <input type="number" name="length" id="length" step="0.01" min="0" value="<?php echo $hair_data['length'] ?? '0.00'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="weight">重量 (g)：</label>
                    <input type="number" name="weight" id="weight" step="0.01" min="0" value="<?php echo $hair_data['weight'] ?? '0.00'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="value">价值 ($)：</label>
                    <input type="number" name="value" id="value" step="0.01" min="0" value="<?php echo $hair_data['value'] ?? '0.00'; ?>">
                </div>
                
            </div>
            
            <div class="form-group">
                <label for="description">头发简介：</label>
                <textarea name="description" id="description" rows="4"><?php echo htmlspecialchars($hair_data['description'] ?? ''); ?></textarea>
            </div>
            
            <!-- 图片上传区域 -->
            <div class="form-group">
                <label for="hair_images">头发图片 *：</label>
                    <input type="file" id="hair_images" name="hair_images[]" accept="image/*" <?php echo $edit_mode ? '' : 'required'; ?> multiple onchange="previewImages(this, 'hair-image-preview')">
                    <div class="format-hint">允许的格式：JPG, JPEG, PNG, GIF, WEBP。可一次上传多张图片（最多5张）</div>
                    <div id="hair-image-preview"></div>
                </div>
                
                <?php if ($edit_mode): ?>
                <!-- 当前图片显示 -->
                <div class="current-images-section">
                    <h5>当前图片：</h5>
                    <div class="current-images-grid">
                        <?php 
                        $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
                        $image_labels = ['主图片', '图片2', '图片3', '图片4', '图片5'];
                        
                        for ($i = 0; $i < count($image_fields); $i++): 
                            $field = $image_fields[$i];
                            $label = $image_labels[$i];
                            
                            if (!empty($hair_data[$field])): 
                        ?>
                        <div class="current-image-item">
                            <img src="../<?php echo htmlspecialchars($hair_data[$field]); ?>" alt="<?php echo $label; ?>" class="current-image-thumb">
                            <div class="current-image-label"><?php echo $label; ?></div>
                        </div>
                        <?php 
                            endif;
                        endfor; 
                        ?>
                    </div>
                    <div class="help-text">选择新图片将替换所有当前图片</div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? '更新头发' : '添加头发'; ?></button>
                <a href="admin.php?page=hair" class="btn btn-secondary">取消</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- 头发列表 -->
    <div class="page-header">
        <div class="action-buttons">
            <a href="admin.php?page=hair&action=add" class="add-button">添加头发</a>
            <a href="admin.php?page=hair&action=batch_add" class="add-button">批量上传头发</a>
        </div>
        
        <!-- 批量删除按钮 - 固定显示在右上角 -->
        <div class="batch-delete-container">
            <button type="button" class="batch-delete-btn" onclick="showBatchDeleteConfirm()" title="批量删除选中的头发">
                <i class="fas fa-trash"></i> 批量删除
            </button>
        </div>
    </div>
    
    <!-- 添加搜索表单 -->
    <div class="search-container">
        <form action="admin.php" method="get" class="search-form">
            <input type="hidden" name="page" value="hair">
            <input type="hidden" name="items_per_page" value="<?php echo $items_per_page; ?>">
            <div class="search-inputs">
                <input type="text" name="search" placeholder="搜索ID或名称..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="search-button">搜索</button>
                <?php if (!empty($search_term)): ?>
                <a href="admin.php?page=hair&items_per_page=<?php echo $items_per_page; ?>" class="reset-button">重置</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
      
      <div class="table-container">
         <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40px;">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                </th>
                <th>ID</th>
                <th>图片</th>
                <th>名称</th>
                <th>价格</th>
                <th>长度</th>
                <th>重量</th>
                <th>添加日期</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($hair_list)): ?>
            <tr>
                <td colspan="8" class="no-data">暂无头发数据</td>
            </tr>
            <?php else: ?>
            <?php foreach ($hair_list as $hair): ?>
            <tr>
                <td>
                    <input type="checkbox" class="hair-checkbox" value="<?php echo $hair['id']; ?>" onchange="updateBatchDeleteButtonState()">
                </td>
                <td><?php echo $hair['id']; ?></td>
                <td>
                    <?php if (!empty($hair['image'])): ?>
                    <img src="../<?php echo htmlspecialchars($hair['image']); ?>" alt="头发图片" style="width: 50px; height: 50px; object-fit: cover;">
                    <?php else: ?>
                    <span class="no-image">无图片</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($hair['title']); ?></td>
                <td>$<?php echo number_format($hair['value'], 2); ?></td>
                <td><?php echo !empty($hair['length']) ? htmlspecialchars($hair['length']) : '未设置'; ?></td>
                <td><?php echo !empty($hair['weight']) ? htmlspecialchars($hair['weight']) : '未设置'; ?></td>
                <td><?php echo date('Y-m-d', strtotime($hair['created_at'])); ?></td>
                <td class="actions">
                    <a href="admin.php?page=hair&action=edit&id=<?php echo $hair['id']; ?>" class="edit-button">编辑</a>
                    <a href="admin.php?page=hair&action=delete&id=<?php echo $hair['id']; ?>" 
                       onclick="return confirm('确定要删除这个头发记录吗？');" class="delete-button">删除</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
      </div>
    
    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-container">
        <div class="pagination-wrapper">
            <div class="pagination-left"></div>
            
            <div class="pagination-center">
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="admin.php?page=hair&page_num=1&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="page-link">首页</a>
                        <a href="admin.php?page=hair&page_num=<?php echo $current_page - 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="page-link">上一页</a>
                    <?php endif; ?>
                    
                    <?php
                    // 显示页码
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    
                    // 如果没有数据或只有一页，只显示页码1
                    if ($total_pages <= 1) {
                        echo '<span class="page-link current">1</span>';
                    } else {
                        // 有多页数据时，正常显示页码
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="page-link current">' . $i . '</span>';
                            } else {
                                echo '<a href="admin.php?page=hair&page_num=' . $i . '&items_per_page=' . $items_per_page . '&search=' . urlencode($search_term) . '" class="page-link">' . $i . '</a>';
                            }
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="admin.php?page=hair&page_num=<?php echo $current_page + 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="page-link">下一页</a>
                        <a href="admin.php?page=hair&page_num=<?php echo $total_pages; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="page-link">末页</a>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-info">
                    显示 <?php echo $offset + 1; ?> - <?php echo min($offset + $items_per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
                </div>
            </div>
            
            <div class="items-per-page">
                <span>每页显示：</span>
                <select id="items-per-page-select" onchange="changeItemsPerPage(this.value)">
                    <?php foreach ($items_per_page_options as $option): ?>
                    <option value="<?php echo $option; ?>" <?php echo ($option == $items_per_page) ? 'selected' : ''; ?>><?php echo $option; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.admin-content {
    width: 100%;
    margin: 0;
    padding: 0 20px;
    box-sizing: border-box;
}

/* 全宽优化 */
.data-table {
    width: 100%;
    min-width: 1000px; /* 确保表格有最小宽度 */
}

/* 表格容器滚动优化 */
.table-container {
    width: 100%;
    overflow-x: auto;
    margin-bottom: 20px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.action-buttons {
    margin-bottom: 20px;
}

.add-button {
    background-color: #e75480;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.add-button:hover {
    background-color: #d64072;
}

/* 页面头部样式 */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px 0;
    border-bottom: 2px solid #e9ecef;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.batch-delete-container {
    position: relative;
}

/* 添加搜索表单样式 */
.search-container {
    margin-bottom: 20px;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}

.search-form {
    display: flex;
    flex-direction: column;
}

.search-inputs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-inputs input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-inputs select {
    min-width: 150px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-button {
    background-color: #e75480;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.search-button:hover {
    background-color: #d64072;
}

.reset-button {
    background-color: #ffecf0;
    color: #e75480;
    border: 1px solid #f7a4b9;
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
}

.reset-button:hover {
    background-color: #ffccd5;
}

/* 批量删除按钮样式 */
.batch-delete-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    position: relative;
    overflow: hidden;
}

.batch-delete-btn:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.batch-delete-btn:hover:before {
    left: 100%;
}

.batch-delete-btn:hover {
    background: linear-gradient(135deg, #c82333, #a71e2a);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
}

.batch-delete-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
}

.batch-delete-btn.disabled {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    cursor: not-allowed;
    box-shadow: 0 2px 4px rgba(108, 117, 125, 0.2);
}

.batch-delete-btn.disabled:hover {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    transform: none;
    box-shadow: 0 2px 4px rgba(108, 117, 125, 0.2);
}

/* 表格基础样式 - 与产品管理保持一致 */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    background-color: #fff;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.data-table th {
    background-color: #ffccd5;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #f7a4b9;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table tbody tr:hover {
    background-color: #fff5f7;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.no-data {
    text-align: center;
    color: #6c757d;
    font-style: italic;
}

.no-image {
    color: #6c757d;
    font-style: italic;
    font-size: 12px;
}

.format-hint {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
    font-style: italic;
}


.actions {
    white-space: nowrap;
    min-width: 140px;
    text-align: center;
}

/* 按钮样式 - 与产品管理保持一致，放大按钮 */
.edit-button {
    background-color: #ffccd5 !important;
    color: #333 !important;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 8px;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
    font-weight: 500;
}

.edit-button:hover {
    background-color: #f7a4b9 !important;
}

.delete-button {
    background-color: #ff8da1;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
    font-weight: 500;
}

.delete-button:hover {
    background-color: #ff7c93;
}


/* 无数据提示样式 */
.no-data {
    text-align: center;
    color: #999;
    padding: 20px;
    font-style: italic;
}

/* 图片样式优化 */
.data-table img {
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* 操作列样式 */
.actions {
    white-space: nowrap;
}

/* 表格响应式调整 */
@media (max-width: 768px) {
    .data-table {
        font-size: 12px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
    }
}

.admin-form-container {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.admin-form {
    width: 100%;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
}

.form-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
    display: block;
}

.images-section {
    margin-bottom: 20px;
}

.images-section h4 {
    margin-bottom: 15px;
    color: #333;
}

.images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 10px;
}

.image-upload-item {
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
}

.image-upload-item label {
    margin-bottom: 8px;
    font-weight: bold;
    color: #333;
}

.current-image {
    margin-top: 10px;
    text-align: center;
}

.current-image img {
    border: 1px solid #ddd;
    border-radius: 4px;
}

.current-image small {
    display: block;
    margin-top: 5px;
    color: #6c757d;
}

/* 图片预览样式 */
#hair-image-preview {
    margin-top: 15px;
}

.image-preview-item {
    display: inline-block;
    position: relative;
    margin: 10px 10px 10px 0;
    border: 2px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background: #f9f9f9;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.image-preview-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.preview-image {
    width: 120px;
    height: 120px;
    object-fit: cover;
    display: block;
}

.preview-filename {
    padding: 8px;
    font-size: 12px;
    color: #666;
    background: #fff;
    text-align: center;
    border-top: 1px solid #eee;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.remove-preview-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 24px;
    height: 24px;
    border: none;
    background: rgba(255, 0, 0, 0.7);
    color: white;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
}

.remove-preview-btn:hover {
    background: rgba(255, 0, 0, 0.9);
}

.preview-warning {
    color: #856404;
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
    font-size: 14px;
}

/* 当前图片网格样式 */
.current-images-section {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.current-images-section h5 {
    margin-bottom: 15px;
    color: #495057;
    font-size: 16px;
}

.current-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 10px;
}

.current-image-item {
    text-align: center;
    background: white;
    border-radius: 8px;
    padding: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.current-image-thumb {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
    border: 2px solid #dee2e6;
}

.current-image-label {
    margin-top: 8px;
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

.form-actions {
    margin-top: 20px;
    margin-bottom: 30px;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}

/* 分页控件样式 */
.pagination-container {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.pagination-left {
    flex: 1;
}

.pagination-center {
    flex: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.pagination {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 5px;
}

.page-link {
    display: inline-block;
    padding: 8px 12px;
    background-color: #ffecf0;
    color: #e75480;
    text-decoration: none;
    border-radius: 4px;
    border: 1px solid #f7a4b9;
    transition: all 0.2s ease;
}

.page-link:hover {
    background-color: #e75480;
    color: white;
    border-color: #e75480;
}

.page-link.current {
    background-color: #e75480;
    color: white;
    border-color: #e75480;
    font-weight: bold;
}

.page-link.active {
    background-color: #e75480;
    color: white;
    border-color: #e75480;
    font-weight: bold;
}

.page-ellipsis {
    padding: 8px 12px;
    color: #6c757d;
}

.items-per-page {
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: flex-end;
    flex: 1;
}

.items-per-page span {
    color: #4A4A4A;
    white-space: nowrap;
}

.items-per-page select {
    padding: 6px 10px;
    border: 1px solid #f7a4b9;
    border-radius: 4px;
    background-color: #ffecf0;
    color: #333;
    cursor: pointer;
    transition: all 0.2s ease;
    appearance: auto;
    min-width: 60px;
    text-align: center;
}

.items-per-page select:hover {
    background-color: #ffccd5;
    border-color: #e75480;
}

.items-per-page select:focus {
    outline: none;
    border-color: #e75480;
    box-shadow: 0 0 0 2px rgba(231, 84, 128, 0.25);
}

.pagination-info {
    color: #6c757d;
    font-size: 14px;
    text-align: center;
}

/* 批量上传样式增强 */
.batch-upload-container {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 5px;
    border: 1px solid #e0e0e0;
}

.batch-upload-container p {
    margin-bottom: 15px;
    color: #666;
}

  .add-button {
      background-color: #e91e63;
      color: white;
      border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}

  .add-button:hover {
      background-color: #c2185b;
  }

/* 批量删除相关样式 */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.batch-delete-btn {
    position: relative;
    padding: 10px 20px;
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.batch-delete-btn:hover {
    background: linear-gradient(135deg, #c82333, #a71e2a);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.4);
}

.batch-delete-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
}

.batch-delete-btn.disabled {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    cursor: not-allowed;
    box-shadow: 0 2px 4px rgba(108, 117, 125, 0.3);
}

.batch-delete-btn.disabled:hover {
    transform: none;
    background: linear-gradient(135deg, #6c757d, #5a6268);
    box-shadow: 0 2px 4px rgba(108, 117, 125, 0.3);
}

.btn-text, .btn-count {
    display: inline-block;
}

/* 批量删除确认对话框样式 */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 0;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.8) translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.show .modal-content {
    transform: scale(1) translateY(0);
}

.modal-header {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    padding: 20px;
    border-radius: 12px 12px 0 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-header .warning-icon {
    font-size: 24px;
    color: #fff3cd;
}

.modal-body {
    padding: 25px;
    line-height: 1.6;
}

.selected-items-list {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
}

.selected-items-list h4 {
    margin: 0 0 10px 0;
    color: #495057;
    font-size: 16px;
}

.items-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.items-list li {
    padding: 8px 12px;
    margin: 4px 0;
    background: white;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    font-size: 14px;
    color: #495057;
}

.warning-text {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    font-weight: 500;
}

.confirmation-input {
    margin: 20px 0;
}

.confirmation-input label {
    display: block;
    margin-bottom: 8px;
    color: #495057;
    font-weight: 600;
}

.confirmation-input input {
    width: 100%;
    padding: 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.confirmation-input input:focus {
    outline: none;
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.modal-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    min-width: 100px;
}

.modal-btn-cancel {
    background: #6c757d;
    color: white;
}

.modal-btn-cancel:hover {
    background: #5a6268;
}

.modal-btn-delete {
    background: #dc3545;
    color: white;
}

.modal-btn-delete:hover {
    background: #c82333;
}

.modal-btn-delete:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

/* 表头复选框列样式 - 与产品管理保持一致 */
.data-table th:first-child {
    text-align: center;
    padding: 12px 8px;
}

.data-table td:first-child {
    text-align: center;
    padding: 12px 8px;
}

/* 复选框样式优化 */
.hair-checkbox, #selectAll {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #007bff;
}

.hair-checkbox:checked, #selectAll:checked {
    background-color: #007bff;
    border-color: #007bff;
}

/* 选中行高亮 */
.data-table tbody tr:has(.hair-checkbox:checked) {
    background-color: #f8f9fa;
    border-left: 3px solid #007bff;
}

.data-table tbody tr:has(.hair-checkbox:checked):hover {
    background-color: #e9ecef;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .images-grid {
        grid-template-columns: 1fr;
    }
    
    .batch-controls {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .form-count {
        margin-left: 0;
    }
    
    .form-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .hair-form {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .remove-form-button {
        padding: 5px 10px;
        font-size: 11px;
    }
    
    .pagination-container {
        flex-direction: column;
        align-items: center;
    }
    
    .pagination-wrapper {
        flex-direction: column;
        gap: 15px;
        align-items: center;
        width: 100%;
    }
    
    .pagination-left {
        display: none;
    }
    
    .pagination-center {
        flex: none;
        width: 100%;
        align-items: center;
    }
    
    .items-per-page {
        justify-content: center;
        flex: none;
    }
    
    .data-table {
        font-size: 12px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .action-buttons {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .modal-header {
        padding: 15px;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        padding: 15px 20px;
        flex-direction: column;
    }
    
    .modal-btn {
        width: 100%;
        margin: 5px 0;
    }
}
</style>

<script>
function changeItemsPerPage(value) {
    const url = new URL(window.location);
    url.searchParams.set('items_per_page', value);
    url.searchParams.set('page_num', 1); // 重置到第一页
    window.location.href = url.toString();
}

// 批量删除相关JavaScript函数
function toggleSelectAll(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.hair-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateBatchDeleteButtonState();
}

function updateBatchDeleteButtonState() {
    const checkboxes = document.querySelectorAll('.hair-checkbox');
    const checkedBoxes = document.querySelectorAll('.hair-checkbox:checked');
    const selectAllCheckbox = document.getElementById('selectAll');
    const batchDeleteBtn = document.querySelector('.batch-delete-btn');
    
    // 如果批量删除按钮不存在（如在批量添加页面），直接返回
    if (!batchDeleteBtn) return;
    
    const btnText = batchDeleteBtn.querySelector('.btn-text');
    const btnCount = batchDeleteBtn.querySelector('.btn-count');
    
    // 更新全选复选框状态
    if (selectAllCheckbox) {
        if (checkedBoxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length === checkboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
    
    // 更新批量删除按钮状态
    if (checkedBoxes.length === 0) {
        batchDeleteBtn.classList.add('disabled');
        batchDeleteBtn.title = '请先选择要删除的头发记录';
        btnText.textContent = '批量删除';
        btnCount.style.display = 'none';
    } else {
        batchDeleteBtn.classList.remove('disabled');
        batchDeleteBtn.title = `删除选中的 ${checkedBoxes.length} 个头发记录`;
        btnText.textContent = '批量删除';
        btnCount.textContent = ` (${checkedBoxes.length})`;
        btnCount.style.display = 'inline';
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.hair-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }
    updateBatchDeleteButtonState();
}

// 显示批量删除确认对话框
function showBatchDeleteConfirm() {
    const checkedBoxes = document.querySelectorAll('.hair-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        // 创建自定义提示对话框
        const alertModal = document.createElement('div');
        alertModal.className = 'modal-overlay show';
        alertModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <span class="warning-icon">⚠️</span>
                    <h3>提示</h3>
                </div>
                <div class="modal-body">
                    <p>请先选择要删除的头发记录！</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal(this)">确定</button>
                </div>
            </div>
        `;
        document.body.appendChild(alertModal);
        
        // 3秒后自动关闭
        setTimeout(() => {
            if (alertModal.parentNode) {
                alertModal.remove();
            }
        }, 3000);
        
        return;
    }
    
    // 获取选中的头发信息
    const selectedHairs = [];
    checkedBoxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const id = checkbox.value;
        const title = row.cells[3].textContent.trim(); // 标题在第4列（索引3）
        selectedHairs.push({ id: id, title: title });
    });
    
    // 创建确认对话框
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'batch-delete-modal';
    
    // 构建头发列表HTML
    let hairListHTML = '';
    const displayCount = Math.min(selectedHairs.length, 10);
    
    for (let i = 0; i < displayCount; i++) {
        hairListHTML += `<li>ID: ${selectedHairs[i].id} - ${selectedHairs[i].title}</li>`;
    }
    
    if (selectedHairs.length > 10) {
        hairListHTML += `<li style="color: #6c757d; font-style: italic;">... 还有 ${selectedHairs.length - 10} 个头发记录</li>`;
    }
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <span class="warning-icon">⚠️</span>
                <h3>确认批量删除</h3>
            </div>
            <div class="modal-body">
                <p>您即将删除以下 <strong>${selectedHairs.length}</strong> 个头发记录：</p>
                
                <div class="selected-items-list">
                    <h4>选中的头发记录：</h4>
                    <ul class="items-list">
                        ${hairListHTML}
                    </ul>
                </div>
                
                <div class="warning-text">
                    <strong>⚠️ 危险操作警告</strong><br>
                    此操作将永久删除选中的头发记录及其相关图片文件，且无法撤销！
                </div>
                
                <div class="confirmation-input">
                    <label for="confirmation-text">请输入 <strong>"确认删除"</strong> 来确认此操作：</label>
                    <input type="text" id="confirmation-text" placeholder="请输入：确认删除" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal(this)">取消</button>
                <button type="button" class="modal-btn modal-btn-delete" id="confirm-delete-btn" disabled onclick="executeBatchDelete()">确认删除</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // 显示模态框
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    // 绑定确认输入事件
    const confirmationInput = modal.querySelector('#confirmation-text');
    const confirmDeleteBtn = modal.querySelector('#confirm-delete-btn');
    
    confirmationInput.addEventListener('input', function() {
        if (this.value.trim() === '确认删除') {
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.style.backgroundColor = '#dc3545';
        } else {
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.style.backgroundColor = '#6c757d';
        }
    });
    
    // 自动聚焦到输入框
    setTimeout(() => {
        confirmationInput.focus();
    }, 300);
    
    // 点击背景关闭
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal.querySelector('.modal-btn-cancel'));
        }
    });
    
    // ESC键关闭
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('batch-delete-modal')) {
            closeModal(modal.querySelector('.modal-btn-cancel'));
        }
    });
}

// 执行批量删除
function executeBatchDelete() {
    const checkedBoxes = document.querySelectorAll('.hair-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        alert('没有选中的头发记录！');
        return;
    }
    
    // 显示加载提示
    const modal = document.getElementById('batch-delete-modal');
    const modalBody = modal.querySelector('.modal-body');
    modalBody.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div style="font-size: 18px; margin-bottom: 20px;">🔄</div>
            <p>正在删除头发记录，请稍候...</p>
        </div>
    `;
    
    // 创建表单并提交
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    // 添加action字段
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'batch_delete_hair';
    form.appendChild(actionInput);
    
    // 添加选中的头发ID
    checkedBoxes.forEach(checkbox => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'hair_ids[]';
        hiddenInput.value = checkbox.value;
        form.appendChild(hiddenInput);
    });
    
    document.body.appendChild(form);
    form.submit();
}

// 关闭模态框
function closeModal(button) {
    const modal = button.closest('.modal-overlay');
    modal.classList.remove('show');
    setTimeout(() => {
        if (modal.parentNode) {
            modal.remove();
        }
    }, 300);
}

// 图片预览功能
function previewImages(input, previewContainerId) {
    const previewContainer = document.getElementById(previewContainerId);
    previewContainer.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        const maxFiles = Math.min(input.files.length, 5); // 最多显示5张图片
        
        for (let i = 0; i < maxFiles; i++) {
            const file = input.files[i];
            
            // 验证文件类型
            if (!file.type.match('image.*')) {
                continue;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewDiv = document.createElement('div');
                previewDiv.className = 'image-preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-image';
                
                const fileName = document.createElement('div');
                fileName.className = 'preview-filename';
                fileName.textContent = file.name;
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-preview-btn';
                removeBtn.innerHTML = '×';
                removeBtn.onclick = function() {
                    previewDiv.remove();
                    // 如果没有预览图片了，清空input
                    if (previewContainer.children.length === 0) {
                        input.value = '';
                    }
                };
                
                previewDiv.appendChild(img);
                previewDiv.appendChild(fileName);
                previewDiv.appendChild(removeBtn);
                previewContainer.appendChild(previewDiv);
            };
            
            reader.readAsDataURL(file);
        }
        
        if (input.files.length > 5) {
            const warningDiv = document.createElement('div');
            warningDiv.className = 'preview-warning';
            warningDiv.textContent = '注意：最多只能上传5张图片，超出部分将被忽略。';
            previewContainer.appendChild(warningDiv);
        }
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 为所有头发复选框添加事件监听
    const hairCheckboxes = document.querySelectorAll('.hair-checkbox');
    hairCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBatchDeleteButtonState);
    });
    
    // 初始化批量删除按钮状态
    updateBatchDeleteButtonState();
    
    // 批量头发表单管理
    const addHairFormButton = document.getElementById('add-hair-form');
    const batchSaveAllButton = document.getElementById('batch-save-all');
    const hairFormsContainer = document.getElementById('hair-forms-container');
    const hairCountElement = document.getElementById('hair-count');
    const hairCountInput = document.getElementById('hair-count-input');
    
    if (addHairFormButton && hairFormsContainer) {
        let hairCount = 1;
        
        // 初始化添加第一个表单
        if (hairFormsContainer.children.length === 0) {
            addHairForm();
        }
        
        // 添加表单按钮点击事件
        addHairFormButton.addEventListener('click', function() {
            addHairForm();
        });
        
        // 批量保存所有头发按钮点击事件
        if (batchSaveAllButton) {
            batchSaveAllButton.addEventListener('click', function() {
                // 触发表单提交，调用底部的批量保存功能
                const batchForm = document.getElementById('batch-hair-form');
                if (batchForm) {
                    // 检查是否有必填字段未填写
                    const requiredFields = batchForm.querySelectorAll('input[required], textarea[required], select[required]');
                    let hasEmptyRequired = false;
                    let firstEmptyField = null;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            hasEmptyRequired = true;
                            if (!firstEmptyField) {
                                firstEmptyField = field;
                            }
                            field.style.borderColor = '#dc3545';
                        } else {
                            field.style.borderColor = '';
                        }
                    });
                    
                    if (hasEmptyRequired) {
                        alert('请填写所有必填字段！');
                        if (firstEmptyField) {
                            firstEmptyField.focus();
                            firstEmptyField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        return;
                    }
                    
                    // 确认保存 - 使用实际表单数量
                    const currentCount = hairFormsContainer.querySelectorAll('.hair-form').length;
                    if (confirm(`确定要保存所有 ${currentCount} 个头发吗？`)) {
                        batchForm.submit();
                    }
                } else {
                    alert('未找到批量保存表单！');
                }
            });
        }
        
        // 添加头发表单函数
        function addHairForm() {
            // 检查表单数量限制
            const currentCount = hairFormsContainer.querySelectorAll('.hair-form').length;
            if (currentCount >= 10) {
                alert('最多只能同时创建10个头发表单！');
                return;
            }
            
            const template = document.getElementById('hair-form-template');
            if (!template) return;
            
            const templateContent = template.innerHTML;
            const newForm = document.createElement('div');
            newForm.innerHTML = templateContent.replace(/{index}/g, hairCount);
            // 插入到容器的最前面
            if (hairFormsContainer.firstChild) {
                hairFormsContainer.insertBefore(newForm, hairFormsContainer.firstChild);
            } else {
                hairFormsContainer.appendChild(newForm);
            }
            
            // 绑定删除按钮事件
            const removeButton = newForm.querySelector(`.remove-form-button[data-index="${hairCount}"]`);
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    const index = this.getAttribute('data-index');
                    const formToRemove = document.getElementById(`hair-form-${index}`);
                    if (formToRemove && hairFormsContainer.querySelectorAll('.hair-form').length > 1) {
                        formToRemove.remove();
                        updateHairCount();
                    } else {
                        alert('至少需要保留一个头发表单');
                    }
                });
            }
            
            // 绑定图片预览事件
            const imageInput = newForm.querySelector(`input[name="hairs[${hairCount}][hair_images][]"]`);
            if (imageInput) {
                imageInput.addEventListener('change', function() {
                    previewImages(this, `hair_images_preview_${hairCount}`);
                });
            }
            
            // 更新计数
            hairCount++;
            updateHairCount();
        }
        
        // 更新头发计数
        function updateHairCount() {
            const currentCount = hairFormsContainer.querySelectorAll('.hair-form').length;
            if (hairCountElement) {
                hairCountElement.textContent = currentCount;
            }
            if (hairCountInput) {
                hairCountInput.value = currentCount;
            }
            
            // 根据数量限制更新添加按钮状态
            if (addHairFormButton) {
                if (currentCount >= 10) {
                    addHairFormButton.disabled = true;
                    addHairFormButton.textContent = '已达到最大数量限制 (10个)';
                    addHairFormButton.style.backgroundColor = '#ccc';
                    addHairFormButton.style.cursor = 'not-allowed';
                } else {
                    addHairFormButton.disabled = false;
                    addHairFormButton.textContent = '添加一个头发表单';
                    addHairFormButton.style.backgroundColor = '';
                    addHairFormButton.style.cursor = '';
                }
            }
        }
    }
    
    // 为底部的批量保存按钮添加确认弹窗
    const batchForm = document.getElementById('batch-hair-form');
    if (batchForm) {
        batchForm.addEventListener('submit', function(e) {
            e.preventDefault(); // 阻止默认提交
            
            // 获取当前实际表单数量
            const hairFormsContainer = document.getElementById('hair-forms-container');
            const currentCount = hairFormsContainer ? hairFormsContainer.querySelectorAll('.hair-form').length : 0;
            
            // 显示确认弹窗
            if (confirm(`确定要保存所有 ${currentCount} 个头发吗？`)) {
                // 确认后恢复默认提交行为
                this.removeEventListener('submit', arguments.callee);
                this.submit();
            }
        });
    }
});
</script>

<!-- 头发表单模板 -->
<template id="hair-form-template">
    <div class="hair-form" id="hair-form-{index}">
        <div class="form-header">
            <h4>头发 #{index}</h4>
            <button type="button" class="remove-form-button" data-index="{index}">删除此头发</button>
        </div>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="title_{index}">头发标题 *：</label>
                <input type="text" name="hairs[{index}][title]" id="title_{index}" required>
            </div>
            
            <div class="form-group">
                <label for="length_{index}">长度 (cm)：</label>
                <input type="number" name="hairs[{index}][length]" id="length_{index}" step="0.01" min="0" value="0.00">
            </div>
            
            <div class="form-group">
                <label for="weight_{index}">重量 (g)：</label>
                <input type="number" name="hairs[{index}][weight]" id="weight_{index}" step="0.01" min="0" value="0.00">
            </div>
            
            <div class="form-group">
                <label for="value_{index}">价值 ($)：</label>
                <input type="number" name="hairs[{index}][value]" id="value_{index}" step="0.01" min="0" value="0.00">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description_{index}">头发简介：</label>
            <textarea name="hairs[{index}][description]" id="description_{index}" rows="4"></textarea>
        </div>
        
        <div class="form-group">
            <label for="hair_images_{index}">头发图片 *：</label>
            <input type="file" name="hairs[{index}][hair_images][]" id="hair_images_{index}" accept="image/*" multiple required onchange="previewImages(this, 'hair_images_preview_{index}')">
            <div class="format-hint">允许的格式：JPG, JPEG, PNG, GIF, WEBP。可一次上传多张图片（最多5张）</div>
            <div id="hair_images_preview_{index}"></div>
        </div>
    </div>
</template>


<?php
// 开启输出缓冲，解决header已发送的问题
ob_start();

// 启用错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 创建hair表（如果不存在）
$create_table_sql = "CREATE TABLE IF NOT EXISTS hair (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT '头发标题',
    description TEXT COMMENT '头发简介',
    length DECIMAL(5,2) DEFAULT 0.00 COMMENT '长度(cm)',
    weight DECIMAL(8,2) DEFAULT 0.00 COMMENT '重量(g)',
    value DECIMAL(10,2) DEFAULT 0.00 COMMENT '价值($)',
    image VARCHAR(255) COMMENT '主图片',
    image2 VARCHAR(255) COMMENT '图片2',
    image3 VARCHAR(255) COMMENT '图片3',
    image4 VARCHAR(255) COMMENT '图片4',
    image5 VARCHAR(255) COMMENT '图片5',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='头发信息表'";

if (!mysqli_query($conn, $create_table_sql)) {
    // 表创建失败，但不阻止程序继续执行
    // echo "Warning: " . mysqli_error($conn);
}


// 处理批量删除操作
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batch_delete_hair') {
    if (isset($_POST['hair_ids']) && is_array($_POST['hair_ids']) && !empty($_POST['hair_ids'])) {
        $hair_ids = array_map('intval', $_POST['hair_ids']);
        $deleted_count = 0;
        $error_count = 0;
        
        foreach ($hair_ids as $hair_id) {
            // 先获取头发信息，以便删除相关文件
            $sql = "SELECT * FROM hair WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $hair_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $hair_data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                if ($hair_data) {
                    // 删除相关图片文件
                    $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
                    foreach ($image_fields as $field) {
                        if (!empty($hair_data[$field])) {
                            $file_path = "../" . $hair_data[$field];
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    }
                    
                    // 删除数据库记录
                    $delete_sql = "DELETE FROM hair WHERE id = ?";
                    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                        mysqli_stmt_bind_param($delete_stmt, "i", $hair_id);
                        if (mysqli_stmt_execute($delete_stmt)) {
                            $deleted_count++;
                        } else {
                            $error_count++;
                        }
                        mysqli_stmt_close($delete_stmt);
                    }
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($deleted_count > 0) {
            $success_message = "成功删除 {$deleted_count} 个头发记录！";
        }
        
        if ($error_count > 0) {
            $errors[] = "删除过程中有 {$error_count} 个头发记录删除失败！";
        }
        
        // 重定向到列表页面
        header("Location: admin.php?page=hair");
        exit;
    } else {
        $errors[] = "请选择要删除的头发记录！";
    }
}


// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // 先获取头发信息，以便删除相关文件
    $sql = "SELECT * FROM hair WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hair_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($hair_data) {
            // 删除相关图片文件
            $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
            foreach ($image_fields as $field) {
                if (!empty($hair_data[$field])) {
                    $file_path = "../" . $hair_data[$field];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            
            // 删除数据库记录
            $delete_sql = "DELETE FROM hair WHERE id = ?";
            if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                mysqli_stmt_bind_param($delete_stmt, "i", $id);
                if (mysqli_stmt_execute($delete_stmt)) {
                    $success_message = "头发记录删除成功！";
                } else {
                    $errors[] = "删除头发记录时出错：" . mysqli_error($conn);
                }
                mysqli_stmt_close($delete_stmt);
            }
        }
    }
}

// 处理编辑/添加操作
$edit_mode = false;
$hair_data = [];
$errors = [];
$success_message = '';

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $id = intval($_GET['id']);
    
    // 获取头发数据
    $sql = "SELECT * FROM hair WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hair_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$hair_data) {
            $errors[] = "找不到指定的头发记录！";
            $edit_mode = false;
        }
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $length = floatval($_POST['length'] ?? 0);
    $weight = floatval($_POST['weight'] ?? 0);
    $value = floatval($_POST['value'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    // 基本验证
    if (empty($title)) {
        $errors[] = "头发标题不能为空！";
    }
    
    if (empty($errors)) {
        // 处理文件上传
        $upload_dir = "./uploads/hair/";
        $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
        $uploaded_images = [];
        
        // 确保上传目录存在
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // 处理多文件上传
        if (isset($_FILES['hair_images']) && !empty($_FILES['hair_images']['name'][0])) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_files = 5;
            
            $file_count = count($_FILES['hair_images']['name']);
            $file_count = min($file_count, $max_files); // 限制最多5张图片
            
            for ($i = 0; $i < $file_count; $i++) {
                // 检查文件是否成功上传
                if ($_FILES['hair_images']['error'][$i] == UPLOAD_ERR_OK) {
                    $file_name = $_FILES['hair_images']['name'][$i];
                    $file_tmp = $_FILES['hair_images']['tmp_name'][$i];
                    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // 验证文件类型
                    if (!in_array($file_extension, $allowed_extensions)) {
                        $errors[] = "文件 {$file_name} 格式不支持，请上传 jpg, jpeg, png, gif 或 webp 格式的图片！";
                        continue;
                    }
                    
                    // 生成唯一文件名
                    $unique_name = uniqid() . '_' . $i . '.' . $file_extension;
                    $upload_path = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $uploaded_images[$image_fields[$i]] = "uploads/hair/" . $unique_name;
                    } else {
                        $errors[] = "上传文件 {$file_name} 失败！";
                    }
                } else if ($_FILES['hair_images']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                    $errors[] = "图片" . ($i + 1) . "上传失败！";
                }
            }
        }
        
        if (empty($errors)) {
            if ($action == 'edit' && isset($_POST['hair_id'])) {
                // 编辑现有头发
                $hair_id = intval($_POST['hair_id']);
                
                // 构建更新SQL
                $update_fields = ['title = ?', 'description = ?', 'length = ?', 'weight = ?', 'value = ?'];
                $update_values = [$title, $description, $length, $weight, $value];
                $update_types = 'ssddd';
                
                // 添加图片字段
                foreach ($image_fields as $field) {
                    if (isset($uploaded_images[$field])) {
                        $update_fields[] = "{$field} = ?";
                        $update_values[] = $uploaded_images[$field];
                        $update_types .= 's';
                        
                        // 删除旧图片
                        if (!empty($hair_data[$field])) {
                            $old_file = "../" . $hair_data[$field];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                }
                
                $update_values[] = $hair_id;
                $update_types .= 'i';
                
                $sql = "UPDATE hair SET " . implode(', ', $update_fields) . " WHERE id = ?";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, $update_types, ...$update_values);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "头发信息更新成功！";
                        header("Location: admin.php?page=hair");
                        exit;
                    } else {
                        $errors[] = "更新头发信息时出错：" . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                // 添加新头发
                $insert_fields = ['title', 'description', 'length', 'weight', 'value'];
                $insert_placeholders = ['?', '?', '?', '?', '?'];
                $insert_values = [$title, $description, $length, $weight, $value];
                $insert_types = 'ssddd';
                
                // 添加图片字段
                foreach ($image_fields as $field) {
                    if (isset($uploaded_images[$field])) {
                        $insert_fields[] = $field;
                        $insert_placeholders[] = '?';
                        $insert_values[] = $uploaded_images[$field];
                        $insert_types .= 's';
                    }
                }
                
                $sql = "INSERT INTO hair (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, $insert_types, ...$insert_values);
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "头发添加成功！";
                        header("Location: admin.php?page=hair");
                        exit;
                    } else {
                        $errors[] = "添加头发时出错：" . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// 处理批量上传
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batch_upload'])) {
    $batch_errors = [];
    $batch_success_count = 0;
    
    if (isset($_FILES['batch_files']) && is_array($_FILES['batch_files']['name'])) {
        $upload_dir = "./uploads/hair/";
        
        // 确保上传目录存在
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_count = count($_FILES['batch_files']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['batch_files']['error'][$i] == UPLOAD_ERR_OK) {
                $file_name = $_FILES['batch_files']['name'][$i];
                $file_tmp = $_FILES['batch_files']['tmp_name'][$i];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $unique_name = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // 从文件名提取标题（去掉扩展名）
                        $title = pathinfo($file_name, PATHINFO_FILENAME);
                        
                        // 插入数据库
                        $sql = "INSERT INTO hair (title, image) VALUES (?, ?)";
                        if ($stmt = mysqli_prepare($conn, $sql)) {
                            $image_path = "uploads/hair/" . $unique_name;
                            mysqli_stmt_bind_param($stmt, "ss", $title, $image_path);
                            if (mysqli_stmt_execute($stmt)) {
                                $batch_success_count++;
                            } else {
                                $batch_errors[] = "保存文件 {$file_name} 到数据库时出错";
                            }
                            mysqli_stmt_close($stmt);
                        }
                    } else {
                        $batch_errors[] = "上传文件 {$file_name} 失败";
                    }
                } else {
                    $batch_errors[] = "文件 {$file_name} 格式不支持";
                }
            }
        }
        
        if ($batch_success_count > 0) {
            $success_message = "成功批量上传 {$batch_success_count} 个头发！";
        }
        
        if (!empty($batch_errors)) {
            $errors = array_merge($errors, $batch_errors);
        }
    } else {
        $errors[] = "请选择要上传的文件！";
    }
}

// 处理批量添加头发
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batch_add_hair') {
    $batch_errors = [];
    $batch_success_count = 0;
    
    if (isset($_POST['hairs']) && is_array($_POST['hairs'])) {
        $upload_dir = "./uploads/hair/";
        
        // 确保上传目录存在
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($_POST['hairs'] as $index => $hair_data) {
            $hair_errors = [];
            
            // 验证必填字段
            if (empty($hair_data['title'])) {
                $hair_errors[] = "头发标题不能为空";
            }
            
            // 处理图片上传
            $uploaded_images = [];
            if (isset($_FILES['hairs']) && isset($_FILES['hairs']['name'][$index]['hair_images'])) {
                $images = $_FILES['hairs']['name'][$index]['hair_images'];
                $tmp_names = $_FILES['hairs']['tmp_name'][$index]['hair_images'];
                $errors_arr = $_FILES['hairs']['error'][$index]['hair_images'];
                
                if (is_array($images)) {
                    for ($i = 0; $i < count($images); $i++) {
                        if ($errors_arr[$i] == UPLOAD_ERR_OK && !empty($images[$i])) {
                            $file_name = $images[$i];
                            $file_tmp = $tmp_names[$i];
                            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            
                            if (in_array($file_extension, $allowed_extensions)) {
                                $unique_name = uniqid() . '.' . $file_extension;
                                $upload_path = $upload_dir . $unique_name;
                                
                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    $uploaded_images[] = "uploads/hair/" . $unique_name;
                                } else {
                                    $hair_errors[] = "上传图片失败: " . $file_name;
                                }
                            } else {
                                $hair_errors[] = "不支持的图片格式: " . $file_name;
                            }
                        }
                    }
                }
            }
            
            // 如果没有上传图片，添加错误
            if (empty($uploaded_images)) {
                $hair_errors[] = "至少需要上传一张图片";
            }
            
            // 如果没有错误，插入数据库
            if (empty($hair_errors)) {
                $title = mysqli_real_escape_string($conn, $hair_data['title']);
                $description = mysqli_real_escape_string($conn, $hair_data['description'] ?? '');
                $length = floatval($hair_data['length'] ?? 0.00);
                $weight = floatval($hair_data['weight'] ?? 0.00);
                $value = floatval($hair_data['value'] ?? 0.00);
                
                // 使用第一张图片作为主图片
                $main_image = $uploaded_images[0];
                $image2 = isset($uploaded_images[1]) ? $uploaded_images[1] : null;
                $image3 = isset($uploaded_images[2]) ? $uploaded_images[2] : null;
                $image4 = isset($uploaded_images[3]) ? $uploaded_images[3] : null;
                $image5 = isset($uploaded_images[4]) ? $uploaded_images[4] : null;
                
                $sql = "INSERT INTO hair (title, description, length, weight, value, image, image2, image3, image4, image5) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssdddsssss", $title, $description, $length, $weight, $value, $main_image, $image2, $image3, $image4, $image5);
                    if (mysqli_stmt_execute($stmt)) {
                        $batch_success_count++;
                    } else {
                        $batch_errors[] = "保存头发 '{$title}' 到数据库时出错：" . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $batch_errors[] = "准备SQL语句时出错：" . mysqli_error($conn);
                }
            } else {
                $batch_errors = array_merge($batch_errors, array_map(function($err) use ($hair_data) {
                    return "头发 '{$hair_data['title']}': " . $err;
                }, $hair_errors));
            }
        }
        
        // 设置成功消息
        if ($batch_success_count > 0) {
            $success_message = "成功批量添加 {$batch_success_count} 个头发！";
            if (empty($batch_errors)) {
                header("Location: admin.php?page=hair");
                exit;
            }
        }
        
        // 合并错误消息
        if (!empty($batch_errors)) {
            $errors = array_merge($errors, $batch_errors);
        }
    } else {
        $errors[] = "没有有效的头发数据！";
    }
}

// 获取所有头发
$hair_list = [];

// 分页设置
$items_per_page_options = [10, 25, 50, 100];
$default_items_per_page = 10;

// 获取用户选择的每页显示数量
$items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : $default_items_per_page;

// 确保每页显示数量是有效选项
if (!in_array($items_per_page, $items_per_page_options)) {
    $items_per_page = $default_items_per_page;
}

// 获取当前页码
$current_page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// 处理搜索功能
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// 构建SQL查询
$sql = "SELECT * FROM hair WHERE 1=1";

// 添加搜索条件
if (!empty($search_term)) {
    if (is_numeric($search_term)) {
        $sql .= " AND (id = " . intval($search_term) . " OR title LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%')";
    } else {
        $sql .= " AND title LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%'";
    }
}

// 获取总记录数
$count_sql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_table";
$count_result = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_result && $count_row = mysqli_fetch_assoc($count_result)) {
    $total_records = $count_row['total'];
}

// 计算总页数
$total_pages = ceil($total_records / $items_per_page);

// 确保当前页码不超过总页数
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// 计算LIMIT子句的偏移量
$offset = ($current_page - 1) * $items_per_page;

// 添加排序和分页
$sql .= " ORDER BY id DESC LIMIT " . $offset . ", " . $items_per_page;

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hair_list[] = $row;
    }
    mysqli_free_result($result);
}
?>
