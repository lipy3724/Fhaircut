<?php
// 开启输出缓冲，解决header已发送的问题
ob_start();

// 启用错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查products表是否有show_on_homepage字段，如果没有则添加
$check_column_sql = "SHOW COLUMNS FROM products LIKE 'show_on_homepage'";
$check_column_result = mysqli_query($conn, $check_column_sql);
if (mysqli_num_rows($check_column_result) == 0) {
    $add_column_sql = "ALTER TABLE products ADD COLUMN show_on_homepage BOOLEAN DEFAULT FALSE";
    mysqli_query($conn, $add_column_sql);
}

// 增量添加资源与元数据字段（如果不存在则自动添加）
$columnsToEnsure = [
    ['paid_video', "VARCHAR(255) NULL"],
    ['paid_video_size', "BIGINT DEFAULT 0"],
    ['paid_video_duration', "INT DEFAULT 0"],
    // 产品图片统计（可选）
    ['images_total_size', "BIGINT DEFAULT 0"],
    ['images_count', "INT DEFAULT 0"],
    ['images_formats', "VARCHAR(255) NULL"],
    // 付费图片打包统计
    ['paid_photos_zip', "VARCHAR(255) NULL"],
    ['paid_photos_total_size', "BIGINT DEFAULT 0"],
    ['paid_photos_count', "INT DEFAULT 0"],
    ['paid_photos_formats', "VARCHAR(255) NULL"],
    // 添加第6张图片字段
    ['image6', "VARCHAR(255) NULL"],
    // 添加图片包价格字段
    ['photo_pack_price', "DECIMAL(10,2) DEFAULT 0.00"],
];

foreach ($columnsToEnsure as [$col, $ddl]) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE '" . $col . "'");
    if (mysqli_num_rows($res) == 0) {
        mysqli_query($conn, "ALTER TABLE products ADD COLUMN `{$col}` {$ddl}");
    }
}

// 在文件开头，连接数据库后添加代码
if ($conn) {
    // 检查并添加缺失的字段
    $check_field_sql = "SHOW COLUMNS FROM products LIKE 'paid_photos_formats'";
    $check_field_result = mysqli_query($conn, $check_field_sql);
    
    if (mysqli_num_rows($check_field_result) == 0) {
        // 字段不存在，添加它
        $add_field_sql = "ALTER TABLE products ADD COLUMN paid_photos_formats VARCHAR(255) DEFAULT NULL AFTER paid_photos_count";
        if (mysqli_query($conn, $add_field_sql)) {
            error_log("Added missing field 'paid_photos_formats' to products table");
        } else {
            error_log("Failed to add field 'paid_photos_formats': " . mysqli_error($conn));
        }
    }
    
    // 检查并添加缺失的paid_photos_zip字段
    $check_zip_field_sql = "SHOW COLUMNS FROM products LIKE 'paid_photos_zip'";
    $check_zip_field_result = mysqli_query($conn, $check_zip_field_sql);
    
    if (mysqli_num_rows($check_zip_field_result) == 0) {
        // 字段不存在，添加它
        $add_zip_field_sql = "ALTER TABLE products ADD COLUMN paid_photos_zip VARCHAR(255) DEFAULT NULL AFTER paid_video_duration";
        if (mysqli_query($conn, $add_zip_field_sql)) {
            error_log("Added missing field 'paid_photos_zip' to products table");
        } else {
            error_log("Failed to add field 'paid_photos_zip': " . mysqli_error($conn));
        }
    }
    
    // 检查并添加缺失的paid_photos_total_size字段
    $check_size_field_sql = "SHOW COLUMNS FROM products LIKE 'paid_photos_total_size'";
    $check_size_field_result = mysqli_query($conn, $check_size_field_sql);
    
    if (mysqli_num_rows($check_size_field_result) == 0) {
        // 字段不存在，添加它
        $add_size_field_sql = "ALTER TABLE products ADD COLUMN paid_photos_total_size BIGINT DEFAULT 0 AFTER paid_photos_zip";
        if (mysqli_query($conn, $add_size_field_sql)) {
            error_log("Added missing field 'paid_photos_total_size' to products table");
        } else {
            error_log("Failed to add field 'paid_photos_total_size': " . mysqli_error($conn));
        }
    }
    
    // 检查并添加缺失的paid_photos_count字段
    $check_count_field_sql = "SHOW COLUMNS FROM products LIKE 'paid_photos_count'";
    $check_count_field_result = mysqli_query($conn, $check_count_field_sql);
    
    if (mysqli_num_rows($check_count_field_result) == 0) {
        // 字段不存在，添加它
        $add_count_field_sql = "ALTER TABLE products ADD COLUMN paid_photos_count INT DEFAULT 0 AFTER paid_photos_total_size";
        if (mysqli_query($conn, $add_count_field_sql)) {
            error_log("Added missing field 'paid_photos_count' to products table");
        } else {
            error_log("Failed to add field 'paid_photos_count': " . mysqli_error($conn));
        }
    }
}

// 处理产品表单提交

// 添加一个辅助函数来获取上传错误信息
function getUploadErrorMessage($error_code) {
    switch($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "上传的文件超过了php.ini中upload_max_filesize指令的限制";
        case UPLOAD_ERR_FORM_SIZE:
            return "上传的文件超过了HTML表单中MAX_FILE_SIZE指令的限制";
        case UPLOAD_ERR_PARTIAL:
            return "文件只有部分被上传";
        case UPLOAD_ERR_NO_FILE:
            return "没有文件被上传";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "找不到临时文件夹";
        case UPLOAD_ERR_CANT_WRITE:
            return "无法写入文件到磁盘";
        case UPLOAD_ERR_EXTENSION:
            return "PHP扩展停止了文件上传";
        default:
            return "未知上传错误: " . $error_code;
    }
}

// 添加一个辅助函数来获取文件的绝对路径
function getAbsolutePath($relativePath) {
    // 尝试多种方式获取绝对路径
    $paths = [
        realpath(dirname(__FILE__) . '/../' . $relativePath),
        dirname(__FILE__) . '/../' . $relativePath,
        (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($relativePath, '/') : null)
    ];
    
    foreach ($paths as $path) {
        if ($path && file_exists($path)) {
            return $path;
        }
    }
    
    // 如果所有方法都失败，返回最可能的路径
    return dirname(__FILE__) . '/../' . $relativePath;
}

// 添加一个函数来安全地删除文件
function safeDeleteFile($relativePath) {
    if (empty($relativePath)) {
        return [false, "路径为空"];
    }
    
    $absolutePath = getAbsolutePath($relativePath);
    
    if (!file_exists($absolutePath)) {
        return [false, "文件不存在: $absolutePath"];
    }
    
    if (!is_writable($absolutePath)) {
        // 尝试修改权限
        chmod($absolutePath, 0666);
        if (!is_writable($absolutePath)) {
            return [false, "文件不可写: $absolutePath"];
        }
    }
    
    if (unlink($absolutePath)) {
        return [true, "成功删除文件: $absolutePath"];
    } else {
        $error = error_get_last();
        return [false, "删除失败: " . ($error ? $error['message'] : '未知错误')];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // 添加调试信息
    error_log("POST data: " . print_r($_POST, true));
    
    // 处理批量上传产品
    if ($action == 'batch_add_product') {
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
            $category_id = isset($_POST['products'][$i]['category_id']) ? intval($_POST['products'][$i]['category_id']) : 0;
            $guest = isset($_POST['products'][$i]['guest']) ? intval($_POST['products'][$i]['guest']) : 0;
            $show_on_homepage = isset($_POST['products'][$i]['show_on_homepage']) ? intval($_POST['products'][$i]['show_on_homepage']) : 0;
            
            // 验证必填字段
            $product_errors = [];
            if (empty($title)) {
                $product_errors[] = "产品 #$i: 标题不能为空";
            }
            if ($price <= 0) {
                $product_errors[] = "产品 #$i: 价格必须大于0";
            }
            if ($category_id <= 0) {
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
            $paid_video_path = '';
            $paid_photos_zip_path = '';
            $paid_video_size = 0;
            $paid_video_duration = 0;
            $paid_photos_total_size = 0;
            $paid_photos_count = 0;
            $paid_photos_formats = '';
            
            // 处理游客图片上传（带水印）
            // 检查是否有游客图片上传
            if (isset($_FILES['products']['name'][$i]['guest_images']) && is_array($_FILES['products']['name'][$i]['guest_images'])) {
                $upload_dir = './uploads/products/';
                
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
                    $upload_file = $upload_dir . basename($file_name);
                    
                        if (move_uploaded_file($_FILES['products']['tmp_name'][$i]['guest_images'][$j], $upload_file)) {
                            $image_paths[$guest_image_fields[$j]] = 'uploads/products/' . $file_name;
                    } else {
                            $product_errors[] = "产品 #$i: 游客图片" . ($j + 1) . "上传失败";
                        }
                    }
                }
            }
            
            // 处理会员专属图片上传（无水印）
            // 检查是否有会员图片上传
            if (isset($_FILES['products']['name'][$i]['member_images']) && is_array($_FILES['products']['name'][$i]['member_images'])) {
                $upload_dir = './uploads/products/';
                
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
                    $upload_file = $upload_dir . basename($file_name);
                    
                        if (move_uploaded_file($_FILES['products']['tmp_name'][$i]['member_images'][$j], $upload_file)) {
                            $image_paths[$member_image_fields[$j]] = 'uploads/products/' . $file_name;
                    } else {
                            $product_errors[] = "产品 #$i: 会员图片" . ($j + 1) . "上传失败";
                        }
                    }
                }
            }
            
            // 处理付费视频上传
            if (isset($_FILES['products']['name'][$i]['paid_video']) && !empty($_FILES['products']['name'][$i]['paid_video'])) {
                $upload_dir = './uploads/videos/';
                
                // 检查并创建目录
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // 创建临时目录用于存放待压缩的视频
                $temp_dir = $upload_dir . 'temp_' . time() . '_' . $i . '/';
                if (!file_exists($temp_dir)) {
                    mkdir($temp_dir, 0777, true);
                }
                
                // 为视频生成临时文件名
                $temp_filename = $_FILES['products']['name'][$i]['paid_video'];
                $temp_path = $temp_dir . $temp_filename;
                
                if (move_uploaded_file($_FILES['products']['tmp_name'][$i]['paid_video'], $temp_path)) {
                    // 设置文件权限
                    chmod($temp_path, 0644);
                    
                    // 创建ZIP压缩包
                    $zip_filename = 'video_' . time() . '_' . mt_rand(1000, 9999) . '.zip';
                    $zip_path = $upload_dir . $zip_filename;
                    
                    // 创建ZIP文件
                    $zip = new ZipArchive();
                    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        $zip->addFile($temp_path, $temp_filename);
                        $zip->close();
                        
                        // 更新视频路径和大小
                        $paid_video_path = 'vip.fhaircut.com/uploads/videos/' . $zip_filename;
                        $paid_video_size = filesize($zip_path);
                        
                        // 设置文件权限
                        chmod($zip_path, 0644);
                        
                        // 清理临时文件
                        @unlink($temp_path);
                        @rmdir($temp_dir);
                    
                    // 获取视频时长 - 使用客户端提供的时长信息
                    $paid_video_duration = isset($_POST['products'][$i]['paid_video_duration']) ? 
                        intval($_POST['products'][$i]['paid_video_duration']) : 0;
                    } else {
                        $product_errors[] = "产品 #$i: 创建视频压缩包失败";
                        // 清理临时文件
                        @unlink($temp_path);
                        @rmdir($temp_dir);
                    }
                } else {
                    $product_errors[] = "产品 #$i: 视频上传失败";
                    // 清理临时目录
                    @rmdir($temp_dir);
                }
            }
            
            // 处理付费图片上传并自动压缩为zip
            if (isset($_FILES['products']['name'][$i]['paid_photos']) && is_array($_FILES['products']['name'][$i]['paid_photos']) && count($_FILES['products']['name'][$i]['paid_photos']) > 0) {
                $upload_dir = './uploads/photos/';
                $temp_dir = './uploads/temp/';
                
                // 检查并创建目录
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (!file_exists($temp_dir)) {
                    mkdir($temp_dir, 0777, true);
                }
                
                // 创建zip文件名
                $zip_file_name = time() . '_' . mt_rand(1000, 9999) . '_photos.zip';
                $zip_file_path = $upload_dir . $zip_file_name;
                
                // 创建ZIP对象
                $zip = new ZipArchive();
                if ($zip->open($zip_file_path, ZipArchive::CREATE) !== TRUE) {
                    $product_errors[] = "产品 #$i: 无法创建ZIP文件";
                    continue;
                }
                
                $file_count = count($_FILES['products']['name'][$i]['paid_photos']);
                        $formats = [];
                $total_size = 0;
                $success_count = 0;
                
                // 处理每个上传的文件
                for ($j = 0; $j < $file_count; $j++) {
                    // 检查文件是否成功上传
                    if ($_FILES['products']['error'][$i]['paid_photos'][$j] == 0) {
                        $temp_file_name = time() . '_' . mt_rand(1000, 9999) . '_' . $_FILES['products']['name'][$i]['paid_photos'][$j];
                        $temp_file_path = $temp_dir . $temp_file_name;
                        
                        // 先移动到临时目录
                        if (move_uploaded_file($_FILES['products']['tmp_name'][$i]['paid_photos'][$j], $temp_file_path)) {
                            // 获取文件类型
                            $ext = strtolower(pathinfo($_FILES['products']['name'][$i]['paid_photos'][$j], PATHINFO_EXTENSION));
                            if (!in_array($ext, $formats)) {
                                $formats[] = $ext;
                            }
                            
                            // 添加到ZIP文件
                            $zip->addFile($temp_file_path, basename($_FILES['products']['name'][$i]['paid_photos'][$j]));
                            $total_size += filesize($temp_file_path);
                            $success_count++;
                        } else {
                            $product_errors[] = "产品 #$i: 付费图片" . ($j + 1) . "上传失败";
                        }
                    }
                }
                
                // 关闭ZIP文件
                        $zip->close();
                
                // 删除临时文件
                foreach (glob($temp_dir . '*') as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                
                // 设置返回值
                if ($success_count > 0) {
                    $paid_photos_zip_path = 'vip.fhaircut.com/uploads/photos/' . $zip_file_name;
                    $paid_photos_total_size = $total_size;
                    $paid_photos_count = $success_count;
                    $paid_photos_formats = implode(',', $formats);
                } else {
                    // 如果没有成功上传的文件，删除空的zip文件
                    if (file_exists($zip_file_path)) {
                        unlink($zip_file_path);
                    }
                }
            }
            
            // 如果有错误，记录并继续下一个
            if (!empty($product_errors)) {
                $error_count++;
                $messages = array_merge($messages, $product_errors);
                continue;
            }
            
            // 计算图片统计信息
            $images_total_size = 0;
            $images_count = 0;
            $images_formats = [];
            
            foreach ($image_paths as $path) {
                if (!empty($path)) {
                    // 尝试多种路径获取方式
                    $abs_path = realpath(dirname(__FILE__) . '/../' . $path);
                    if (!$abs_path || !file_exists($abs_path)) {
                        $abs_path = dirname(__FILE__) . '/../' . $path;
                    }
                    if (!file_exists($abs_path)) {
                        $abs_path = './' . $path;
                    }
                    
                    if (file_exists($abs_path)) {
                        $images_total_size += filesize($abs_path);
                        $images_count++;
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        if (!in_array($ext, $images_formats)) {
                            $images_formats[] = $ext;
                        }
                    } else {
                        error_log("无法找到图片文件: " . $path);
                    }
                }
            }
            
            $images_formats_str = implode(',', $images_formats);
            
            // 保存产品到数据库
            if ($custom_id > 0) {
                // 使用自定义ID
                $sql = "INSERT INTO products (id, title, subtitle, price, photo_pack_price, category_id, guest, image, image2, image3, image4, member_image1, member_image2, member_image3, member_image4, member_image5, member_image6, show_on_homepage, images_total_size, images_count, images_formats, paid_video, paid_video_size, paid_video_duration, paid_photos_zip, paid_photos_total_size, paid_photos_count, paid_photos_formats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "issddiisissssssssiiissiisiss", 
                        $custom_id, $title, $subtitle, $price, $photo_pack_price, $category_id, $guest, 
                        $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'],
                        $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                        $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6'],
                        $show_on_homepage, $images_total_size, $images_count, $images_formats_str,
                        $paid_video_path, $paid_video_size, $paid_video_duration, 
                        $paid_photos_zip_path, $paid_photos_total_size, $paid_photos_count, $paid_photos_formats);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $messages[] = "产品 #$i: 保存失败 - " . mysqli_error($conn);
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $error_count++;
                    $messages[] = "产品 #$i: 准备SQL语句失败 - " . mysqli_error($conn);
                }
            } else {
                // 自动分配ID
                $sql = "INSERT INTO products (title, subtitle, price, photo_pack_price, category_id, guest, image, image2, image3, image4, member_image1, member_image2, member_image3, member_image4, member_image5, member_image6, show_on_homepage, images_total_size, images_count, images_formats, paid_video, paid_video_size, paid_video_duration, paid_photos_zip, paid_photos_total_size, paid_photos_count, paid_photos_formats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssddiisissssssssiiissiisiss", 
                        $title, $subtitle, $price, $photo_pack_price, $category_id, $guest, 
                        $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'],
                        $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                        $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6'],
                        $show_on_homepage, $images_total_size, $images_count, $images_formats_str,
                        $paid_video_path, $paid_video_size, $paid_video_duration, 
                        $paid_photos_zip_path, $paid_photos_total_size, $paid_photos_count, $paid_photos_formats);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $messages[] = "产品 #$i: 保存失败 - " . mysqli_error($conn);
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $error_count++;
                    $messages[] = "产品 #$i: 准备SQL语句失败 - " . mysqli_error($conn);
                }
            }
        }
        
        // 设置消息
        if ($success_count > 0) {
            $success_message = "成功添加 $success_count 个产品";
            if ($error_count > 0) {
                $success_message .= "，$error_count 个产品添加失败";
            }
            $_SESSION['success_message'] = $success_message;
        }
        
        if ($error_count > 0) {
            $_SESSION['error_messages'] = $messages;
        }
        
        // 重定向回产品列表
        header("Location: admin.php?page=products");
        exit;
    }
    
    // 添加或更新产品
    if ($action == 'add_product' || $action == 'edit_product') {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $custom_id = isset($_POST['custom_id']) ? intval($_POST['custom_id']) : 0;
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $price = floatval($_POST['price']);
        $photo_pack_price = floatval($_POST['photo_pack_price']);
        $category_id = intval($_POST['category_id']);
        $guest_visible = 1; // 默认设置为游客可见
        $show_on_homepage = isset($_POST['show_on_homepage']) ? 1 : 0;
        $errors = [];
        
        // 如果选择了展示到首页，检查首页产品数量
        if ($show_on_homepage) {
            // 获取当前产品的类别名称
            $categoryName = '';
            $cat_sql = "SELECT name FROM categories WHERE id = " . intval($category_id);
            $cat_result = mysqli_query($conn, $cat_sql);
            if ($cat_result && $cat_row = mysqli_fetch_assoc($cat_result)) {
                $categoryName = $cat_row['name'];
            }
            
            // 根据类别不同，检查不同区域的产品数量限制
            if ($categoryName === 'Hair sales') {
                // 检查New hair区域的产品数量（最多4个）
                $count_sql = "SELECT COUNT(*) as count FROM products p 
                             JOIN categories c ON p.category_id = c.id 
                             WHERE p.show_on_homepage = 1 AND c.name = 'Hair sales'";
                if ($action == 'edit_product') {
                    $count_sql .= " AND p.id != $product_id";
                }
                $count_result = mysqli_query($conn, $count_sql);
                $count_row = mysqli_fetch_assoc($count_result);
                
                if ($count_row['count'] >= 4) {
                    $errors[] = "首页New hair区域最多只能展示4个产品，请先取消其他Hair sales产品的首页展示";
                    $show_on_homepage = 0; // 重置为不展示
                }
            } else {
                // 检查精选产品区域的产品数量（最多4个）
                $count_sql = "SELECT COUNT(*) as count FROM products p 
                             JOIN categories c ON p.category_id = c.id 
                             WHERE p.show_on_homepage = 1 AND c.name != 'Hair sales'";
                if ($action == 'edit_product') {
                    $count_sql .= " AND p.id != $product_id";
                }
                $count_result = mysqli_query($conn, $count_sql);
                $count_row = mysqli_fetch_assoc($count_result);
                
                if ($count_row['count'] >= 4) {
                    $errors[] = "首页精选产品区域最多只能展示4个产品，请先取消其他非Hair sales产品的首页展示";
                    $show_on_homepage = 0; // 重置为不展示
                }
            }
        }
        
        // 验证输入
        if (empty($title)) {
            $errors[] = "产品名称不能为空";
        }

        if ($price <= 0) {
            $errors[] = "价格必须大于零";
        }

        if (empty($category_id)) {
            $errors[] = "请选择产品类别";
        }

        // 验证自定义ID
        if ($custom_id > 0) {
            // 检查ID是否已存在（编辑时排除当前产品）
            $check_id_sql = "SELECT id FROM products WHERE id = ?";
            if ($action == 'edit_product') {
                $check_id_sql .= " AND id != ?";
            }

            if ($stmt = mysqli_prepare($conn, $check_id_sql)) {
                if ($action == 'edit_product') {
                    mysqli_stmt_bind_param($stmt, "ii", $custom_id, $product_id);
                } else {
                    mysqli_stmt_bind_param($stmt, "i", $custom_id);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $errors[] = "产品ID $custom_id 已存在，请选择其他ID";
                }

                mysqli_stmt_close($stmt);
            }
        }
        
        // 处理图片上传
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
        $upload_success = false;
        
        // 调试信息
        $debug_info = [];
        $debug_info['FILES'] = isset($_FILES) ? $_FILES : 'No FILES';
        
        // 如果是编辑模式，先获取当前产品的数据，以便保留未更改的图片
        $product_data = [];
        if ($action == 'edit_product') {
            $get_product_sql = "SELECT id, title, subtitle, price, category_id, guest, image, image2, image3, image4, member_image1, member_image2, member_image3, member_image4, member_image5, member_image6, paid_video, paid_video_size, paid_video_duration, paid_photos_zip, paid_photos_total_size, paid_photos_count, paid_photos_formats FROM products WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $get_product_sql)) {
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    $product_data = $row;
                }
                
                mysqli_stmt_close($stmt);
            }
        }
        
        // 处理上传目录
        $current_dir = dirname(__FILE__); // 当前文件的目录
        $upload_dir = realpath($current_dir . '/../uploads/products/');
        
        if (!$upload_dir) {
            // 如果目录不存在，尝试创建它
            $upload_dir = realpath($current_dir . '/../') . '/uploads/products';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
        }
        
        // 确保路径以斜杠结尾
        $upload_dir = rtrim($upload_dir, '/') . '/';
        
        $debug_info['upload_dir_absolute'] = $upload_dir;
        $debug_info['dir_exists'] = file_exists($upload_dir);
        $debug_info['dir_writable'] = is_writable($upload_dir);
        
        // 处理付费视频上传和链接
        $paidVideoPath = isset($_POST['paid_video']) ? trim($_POST['paid_video']) : '';
        // 将分钟转换为秒
        $paidVideoDuration = isset($_POST['paid_video_duration']) ? round(floatval($_POST['paid_video_duration']) * 60) : 0;
        // 将MB转换为字节
        $paidVideoSize = isset($_POST['paid_video_size']) ? round(floatval($_POST['paid_video_size']) * 1024 * 1024) : 0;
        // 接收前端测得的时长（秒）
        $paidVideoDurationClient = isset($_POST['paid_video_duration_client']) ? intval($_POST['paid_video_duration_client']) : 0;
        // 优先用前端获取的时长
                        if ($paidVideoDurationClient > 0) {
                            $paidVideoDuration = $paidVideoDurationClient;
        }
        
        // 处理付费视频文件上传（支持多文件）
        if (isset($_FILES['paid_video_file']) && !empty($_FILES['paid_video_file']['name'])) {
            // 创建视频上传目录
            $videos_upload_dir = realpath($current_dir . '/../uploads/videos/');
            
            if (!$videos_upload_dir) {
                // 如果目录不存在，尝试创建
                $videos_upload_dir = realpath($current_dir . '/../') . '/uploads/videos';
                if (!file_exists($videos_upload_dir)) {
                    mkdir($videos_upload_dir, 0777, true);
                }
            }
            
            // 确保路径以斜杠结尾
            $videos_upload_dir = rtrim($videos_upload_dir, '/') . '/';
            
            // 允许的视频类型
            $allowed_video_types = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska', 'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/octet-stream'];
            $allowed_video_extensions = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'zip', 'rar', '7z'];
            
            // 最大允许的文件大小 (6GB)
            $max_video_size = 6 * 1024 * 1024 * 1024;
            
            // 准备压缩存储的文件名（根据产品ID）
            $video_zip_name = 'video_' . ($product_id ?? time());
            
            // 如果是单个文件上传
            if (!is_array($_FILES['paid_video_file']['name'])) {
                // 检查文件是否成功上传
                if ($_FILES['paid_video_file']['error'] == 0) {
                    // 检查文件类型
                    $file_extension = strtolower(pathinfo($_FILES['paid_video_file']['name'], PATHINFO_EXTENSION));
                    if (!in_array($file_extension, $allowed_video_extensions)) {
                        $errors[] = "只允许上传MP4、MOV、AVI、WEBM、MKV或压缩文件(ZIP、RAR、7Z)格式";
                    } else {
                        // 检查文件大小
                        if ($_FILES['paid_video_file']['size'] > $max_video_size) {
                            $errors[] = "视频文件大小不能超过6GB";
                        } else {
                            // 创建临时目录用于存放待压缩的视频
                            $temp_dir = $videos_upload_dir . 'temp_' . time() . '/';
                            if (!file_exists($temp_dir)) {
                                mkdir($temp_dir, 0777, true);
                            }
                            
                            // 为视频生成临时文件名
                            $temp_filename = basename($_FILES['paid_video_file']['name']);
                            $temp_path = $temp_dir . $temp_filename;
                            
                            // 尝试复制文件到临时目录
                            $copy_success = copy($_FILES['paid_video_file']['tmp_name'], $temp_path);
                            if (!$copy_success) {
                                $copy_success = move_uploaded_file($_FILES['paid_video_file']['tmp_name'], $temp_path);
                            }
                            
                            if ($copy_success) {
                                // 设置文件权限
                                chmod($temp_path, 0644);
                                
                                // 创建ZIP压缩包
                                $zip_filename = $video_zip_name . '.zip';
                                $zip_path = $videos_upload_dir . $zip_filename;
                                
                                // 创建ZIP文件
                                $zip = new ZipArchive();
                                if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                                    $zip->addFile($temp_path, $temp_filename);
                                    $zip->close();
                                    
                                    // 更新视频路径和大小
                                    $paidVideoPath = 'vip.fhaircut.com/uploads/videos/' . $zip_filename;
                                    $paidVideoSize = filesize($zip_path);
                                    
                                    // 设置文件权限
                                    chmod($zip_path, 0644);
                                    
                                    // 清理临时文件
                                    @unlink($temp_path);
                                    @rmdir($temp_dir);
                                } else {
                                    $errors[] = "创建视频压缩包失败，请稍后再试";
                                    // 清理临时文件
                                    @unlink($temp_path);
                                    @rmdir($temp_dir);
                                }
                                } else {
                                    $errors[] = "无法上传视频文件，请稍后再试";
                                    error_log("Failed to upload video file: " . error_get_last()['message']);
                                // 清理临时目录
                                @rmdir($temp_dir);
                            }
                        }
                    }
                } else if ($_FILES['paid_video_file']['error'] != UPLOAD_ERR_NO_FILE) {
                    $errors[] = "视频上传失败: " . upload_error_message($_FILES['paid_video_file']['error']);
                }
            } else {
                // 多文件上传处理
                $totalVideoSize = 0;
                $videoFiles = [];
                $videoCount = count($_FILES['paid_video_file']['name']);
                
                // 创建临时目录用于存放待压缩的视频
                $temp_dir = $videos_upload_dir . 'temp_' . time() . '/';
                if (!file_exists($temp_dir)) {
                    mkdir($temp_dir, 0777, true);
                }
                
                // 处理每个上传的视频文件
                for ($i = 0; $i < $videoCount; $i++) {
                    // 检查文件是否成功上传
                    if ($_FILES['paid_video_file']['error'][$i] == 0) {
                        // 检查文件类型
                        $file_extension = strtolower(pathinfo($_FILES['paid_video_file']['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($file_extension, $allowed_video_extensions)) {
                            $errors[] = "只允许上传MP4、MOV、AVI、WEBM、MKV或压缩文件(ZIP、RAR、7Z)格式 (视频" . ($i + 1) . ")";
                            continue;
                        }
                        
                        // 检查文件大小
                        if ($_FILES['paid_video_file']['size'][$i] > $max_video_size) {
                            $errors[] = "视频文件大小不能超过6GB (视频" . ($i + 1) . ")";
                            continue;
                        }
                        
                        // 为每个视频生成临时文件名
                        $temp_filename = $i . '_' . basename($_FILES['paid_video_file']['name'][$i]);
                        $temp_path = $temp_dir . $temp_filename;
                        
                        // 尝试复制文件到临时目录
                        if (copy($_FILES['paid_video_file']['tmp_name'][$i], $temp_path) || 
                            move_uploaded_file($_FILES['paid_video_file']['tmp_name'][$i], $temp_path)) {
                            
                            $videoFiles[] = $temp_path;
                            $totalVideoSize += $_FILES['paid_video_file']['size'][$i];
                            
                            // 设置文件权限
                            chmod($temp_path, 0644);
                        } else {
                            $errors[] = "视频上传失败，请重试 (视频" . ($i + 1) . ")";
                        }
                    } else if ($_FILES['paid_video_file']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                        $errors[] = "视频" . ($i + 1) . "上传失败: " . upload_error_message($_FILES['paid_video_file']['error'][$i]);
                    }
                }
                
                // 如果有成功上传的视频，创建ZIP压缩包
                if (!empty($videoFiles)) {
                    // 压缩包路径
                    $zip_filename = $video_zip_name . '.zip';
                    $zip_path = $videos_upload_dir . $zip_filename;
                    
                    // 创建ZIP文件
                    $zip = new ZipArchive();
                    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        foreach ($videoFiles as $file) {
                            $zip->addFile($file, basename($file));
                        }
                        $zip->close();
                        
                        // 更新视频路径和大小
                        $paidVideoPath = 'vip.fhaircut.com/uploads/videos/' . $zip_filename;
                        $paidVideoSize = filesize($zip_path);
                        
                        // 设置文件权限
                        chmod($zip_path, 0644);
                        
                        // 清理临时文件
                        foreach ($videoFiles as $file) {
                            @unlink($file);
                        }
                        @rmdir($temp_dir);
                    } else {
                        $errors[] = "创建视频压缩包失败，请稍后再试";
                    }
                }
            }
        }
        
        // 处理付费图片打包链接
        $paidPhotosZipPath = isset($_POST['paid_photos_zip']) ? trim($_POST['paid_photos_zip']) : '';
        $paidPhotosCount = isset($_POST['paid_photos_count_manual']) ? intval($_POST['paid_photos_count_manual']) : 0;
        // 将MB转换为字节
        $paidPhotosTotalSize = isset($_POST['paid_photos_total_size']) ? round(floatval($_POST['paid_photos_total_size']) * 1024 * 1024) : 0;
        $paidPhotosFormats = isset($_POST['paid_photos_formats_manual']) ? trim($_POST['paid_photos_formats_manual']) : '';

        // 保存手动输入的统计值供后续使用（这些值会在后面的逻辑中被正确处理）
        $manualCount = isset($_POST['paid_photos_count_manual']) ? intval($_POST['paid_photos_count_manual']) : 0;
        $manualFormats = isset($_POST['paid_photos_formats_manual']) ? trim($_POST['paid_photos_formats_manual']) : '';
        
        // 添加调试信息
        error_log("收到前端手动统计值 - 数量: " . $manualCount . ", 格式: " . $manualFormats);

        // 处理付费图片文件上传（支持多文件）
        if (isset($_FILES['paid_photos_file']) && !empty($_FILES['paid_photos_file']['name'])) {
            // 创建图片上传目录
            $photos_upload_dir = realpath($current_dir . '/../uploads/photos/');
            
            if (!$photos_upload_dir) {
                // 如果目录不存在，尝试创建
                $photos_upload_dir = realpath($current_dir . '/../') . '/uploads/photos';
                if (!file_exists($photos_upload_dir)) {
                    mkdir($photos_upload_dir, 0777, true);
                }
            }
            
            // 确保路径以斜杠结尾
            $photos_upload_dir = rtrim($photos_upload_dir, '/') . '/';
            
            // 允许的图片类型
            $allowed_photo_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/octet-stream'];
            $allowed_photo_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', '7z'];
            
            // 最大允许的文件大小 (6GB)
            $max_photo_size = 6 * 1024 * 1024 * 1024;
            
            // 准备压缩存储的文件名（根据产品ID）
            $photo_zip_name = 'photo_' . ($product_id ?? time());
            
            // 处理多文件上传
            $totalPhotoSize = 0;
            $photoFiles = [];
            $photoFormats = [];
            
            // 从前端接收统计信息（如果有）
            if (isset($_POST['paid_photos_count_manual']) && !empty($_POST['paid_photos_count_manual'])) {
                $paidPhotosCount = intval($_POST['paid_photos_count_manual']);
            }
            
            if (isset($_POST['paid_photos_formats_manual']) && !empty($_POST['paid_photos_formats_manual'])) {
                $paidPhotosFormats = $_POST['paid_photos_formats_manual'];
            }
            
            if (isset($_POST['paid_photos_total_size']) && !empty($_POST['paid_photos_total_size'])) {
                $paidPhotosTotalSize = intval($_POST['paid_photos_total_size']);
            }
            
            // 判断是否为多文件上传
            if (!is_array($_FILES['paid_photos_file']['name'])) {
                // 单文件上传处理
                if ($_FILES['paid_photos_file']['error'] == 0) {
                    // 检查文件类型
                    $file_extension = strtolower(pathinfo($_FILES['paid_photos_file']['name'], PATHINFO_EXTENSION));
                    if (!in_array($file_extension, $allowed_photo_extensions)) {
                        $errors[] = "只允许上传JPG、PNG、GIF、WEBP或压缩文件(ZIP、RAR、7Z)格式";
                    } else {
                        // 检查文件大小
                        if ($_FILES['paid_photos_file']['size'] > $max_photo_size) {
                            $errors[] = "图片文件大小不能超过6GB";
                        } else {
                            // 使用产品ID作为文件名
                            $new_photo_filename = $photo_zip_name . '.' . $file_extension;
                            $photo_upload_path = $photos_upload_dir . $new_photo_filename;
                            
                            // 尝试复制文件（如果同名文件存在则覆盖）
                            if (copy($_FILES['paid_photos_file']['tmp_name'], $photo_upload_path) || 
                                move_uploaded_file($_FILES['paid_photos_file']['tmp_name'], $photo_upload_path)) {
                                
                                // 更新图片路径
                                $paidPhotosZipPath = 'vip.fhaircut.com/uploads/photos/' . $new_photo_filename;
                                
                                // 如果前端没有提供统计信息，则使用服务器端计算的值
                                if (!isset($_POST['paid_photos_total_size']) || empty($_POST['paid_photos_total_size'])) {
                                    $paidPhotosTotalSize = $_FILES['paid_photos_file']['size'];
                                    $paidPhotosCount = 1;
                                    $paidPhotosFormats = $file_extension;
                                }
                                
                                // 设置文件权限
                                chmod($photo_upload_path, 0644);
                            } else {
                                $errors[] = "无法上传图片文件，请稍后再试";
                                error_log("Failed to upload photo file: " . error_get_last()['message']);
                            }
                        }
                    }
                } else if ($_FILES['paid_photos_file']['error'] != UPLOAD_ERR_NO_FILE) {
                    $errors[] = "图片上传失败: " . upload_error_message($_FILES['paid_photos_file']['error']);
                }
            } else {
                // 多文件上传处理
                $photoCount = count($_FILES['paid_photos_file']['name']);
                
                // 创建临时目录用于存放待压缩的图片
                $temp_dir = $photos_upload_dir . 'temp_' . time() . '/';
                if (!file_exists($temp_dir)) {
                    mkdir($temp_dir, 0777, true);
                }
                
                // 处理每个上传的图片文件
                for ($i = 0; $i < $photoCount; $i++) {
                    // 检查文件是否成功上传
                    if ($_FILES['paid_photos_file']['error'][$i] == 0) {
                        // 检查文件类型
                        $file_extension = strtolower(pathinfo($_FILES['paid_photos_file']['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($file_extension, $allowed_photo_extensions)) {
                            $errors[] = "只允许上传JPG、PNG、GIF、WEBP或压缩文件(ZIP、RAR、7Z)格式 (图片" . ($i + 1) . ")";
                            continue;
                        }
                        
                        // 检查文件大小
                        if ($_FILES['paid_photos_file']['size'][$i] > $max_photo_size) {
                            $errors[] = "图片文件大小不能超过6GB (图片" . ($i + 1) . ")";
                            continue;
                        }
                        
                        // 为每个图片生成临时文件名
                        $temp_filename = $i . '_' . basename($_FILES['paid_photos_file']['name'][$i]);
                        $temp_path = $temp_dir . $temp_filename;
                        
                        // 尝试复制文件到临时目录
                        if (copy($_FILES['paid_photos_file']['tmp_name'][$i], $temp_path) || 
                            move_uploaded_file($_FILES['paid_photos_file']['tmp_name'][$i], $temp_path)) {
                            
                            $photoFiles[] = $temp_path;
                            $totalPhotoSize += $_FILES['paid_photos_file']['size'][$i];
                            $photoFormats[$file_extension] = true;
                            
                            // 设置文件权限
                            chmod($temp_path, 0644);
                        } else {
                            $errors[] = "图片上传失败，请重试 (图片" . ($i + 1) . ")";
                        }
                    } else if ($_FILES['paid_photos_file']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                        $errors[] = "图片" . ($i + 1) . "上传失败: " . upload_error_message($_FILES['paid_photos_file']['error'][$i]);
                    }
                }
                
                // 如果有成功上传的图片，创建ZIP压缩包
                if (!empty($photoFiles)) {
                    // 压缩包路径
                    $zip_filename = $photo_zip_name . '.zip';
                    $zip_path = $photos_upload_dir . $zip_filename;
                    
                    // 创建ZIP文件
                    $zip = new ZipArchive();
                    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        foreach ($photoFiles as $file) {
                            $zip->addFile($file, basename($file));
                        }
                        $zip->close();
                        
                        // 更新图片路径
                        $paidPhotosZipPath = 'vip.fhaircut.com/uploads/photos/' . $zip_filename;
                        
                        // 如果前端没有提供统计信息，则使用服务器端计算的值
                        if (!isset($_POST['paid_photos_total_size']) || empty($_POST['paid_photos_total_size'])) {
                            $paidPhotosTotalSize = filesize($zip_path);
                            $paidPhotosCount = count($photoFiles);
                            $paidPhotosFormats = implode(', ', array_keys($photoFormats));
                        }
                        
                        // 设置文件权限
                        chmod($zip_path, 0644);
                        
                        // 清理临时文件
                        foreach ($photoFiles as $file) {
                            @unlink($file);
                        }
                        @rmdir($temp_dir);
                    } else {
                        $errors[] = "创建图片压缩包失败，请稍后再试";
                    }
                }
            }
            
            // 调试信息
            error_log("付费图片统计 - 数量: " . $paidPhotosCount . ", 格式: " . $paidPhotosFormats . ", 大小: " . $paidPhotosTotalSize);
        }

        // 初始化图片路径数组
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
        
        $upload_success = false;

        // 仅用于"本次表单选择的新图片"统计（用于后台显示）
        $uploadedImageBytes = 0;
        $uploadedImageCount = 0;
        $uploadedFormatSet = [];
        $hasNewImageUpload = false;

        // 用于"最终保存后的图片集合"的统计（用于写入数据库）
        $finalImageTotalBytes = 0;
        $finalImageCount = 0;
        $finalFormatSet = [];
        
        // 初始化格式统计数组（确保至少有一个格式）
        if (empty($paidPhotosFormats)) {
            $paidPhotosFormats = "jpg";
        }
        
        // 处理游客图片上传（多文件）
        if (isset($_FILES['product_image']) && !empty($_FILES['product_image']['name'][0])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 6 * 1024 * 1024 * 1024; // 6GB
            $max_files = 4; // 最多上传4张游客图片
            
            // 游客图片对应的数据库字段
            $visitor_db_fields = ['image', 'image2', 'image3', 'image4'];
            
            // 处理每个上传的文件
            $file_count = count($_FILES['product_image']['name']);
            $file_count = min($file_count, $max_files); // 限制最多处理4张图片
            
            for ($i = 0; $i < $file_count; $i++) {
                // 检查文件是否成功上传
                if ($_FILES['product_image']['error'][$i] == 0) {
                    // 检查文件类型
                    if (!in_array($_FILES['product_image']['type'][$i], $allowed_types)) {
                        $errors[] = "只允许上传JPG、PNG或GIF格式的图片 (游客图片" . ($i + 1) . ")";
                    continue;
                } 
                
                    // 检查文件大小
                    if ($_FILES['product_image']['size'][$i] > $max_size) {
                        $errors[] = "图片大小不能超过6GB (游客图片" . ($i + 1) . ")";
                    continue;
                }
                
                // 生成唯一文件名
                    $file_extension = pathinfo($_FILES['product_image']['name'][$i], PATHINFO_EXTENSION);
                $new_filename = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                    // 尝试复制文件
                    $copy_success = copy($_FILES['product_image']['tmp_name'][$i], $upload_path);
                
                if ($copy_success) {
                        $image_paths[$visitor_db_fields[$i]] = 'uploads/products/' . $new_filename;

                    // 本次新上传统计
                    $uploadedImageBytes += filesize($upload_path);
                    $uploadedImageCount++;
                    $uploadedFormatSet[strtolower($file_extension)] = true;
                    $hasNewImageUpload = true;
                    
                        if ($i == 0) { // 第一张图片为主图
                            $upload_success = true;
                    }
                    
                        // 设置文件权限
                    chmod($upload_path, 0644);
                } else {
                    // 如果复制失败，尝试移动
                        if (move_uploaded_file($_FILES['product_image']['tmp_name'][$i], $upload_path)) {
                            $image_paths[$visitor_db_fields[$i]] = 'uploads/products/' . $new_filename;

                        // 本次新上传统计
                        $uploadedImageBytes += filesize($upload_path);
                        $uploadedImageCount++;
                        $uploadedFormatSet[strtolower($file_extension)] = true;
                        $hasNewImageUpload = true;
                        
                        // 更新最终格式统计
                        $finalFormatSet[strtolower($file_extension)] = true;
                        
                            if ($i == 0) { // 第一张图片为主图
                                $upload_success = true;
                            }
                        } else {
                            $errors[] = "游客图片上传失败，请重试 (图片" . ($i + 1) . ")";
                        }
                    }
                } else if ($_FILES['product_image']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                    // 只有在有文件上传但失败时才显示错误
                    $errors[] = "游客图片" . ($i + 1) . "上传失败: " . getUploadErrorMessage($_FILES['product_image']['error'][$i]);
                }
            }
        }
        
        // 处理会员图片上传（多文件）
        if (isset($_FILES['member_images']) && !empty($_FILES['member_images']['name'][0])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 6 * 1024 * 1024 * 1024; // 6GB
            $max_files = 6; // 最多上传6张会员图片
            
            // 会员图片对应的数据库字段
            $member_db_fields = ['member_image1', 'member_image2', 'member_image3', 'member_image4', 'member_image5', 'member_image6'];
            
            // 处理每个上传的文件
            $file_count = count($_FILES['member_images']['name']);
            $file_count = min($file_count, $max_files); // 限制最多处理6张图片
            
            for ($i = 0; $i < $file_count; $i++) {
                // 检查文件是否成功上传
                if ($_FILES['member_images']['error'][$i] == 0) {
                    // 检查文件类型
                    if (!in_array($_FILES['member_images']['type'][$i], $allowed_types)) {
                        $errors[] = "只允许上传JPG、PNG或GIF格式的图片 (会员图片" . ($i + 1) . ")";
                        continue;
                    }
                    
                    // 检查文件大小
                    if ($_FILES['member_images']['size'][$i] > $max_size) {
                        $errors[] = "图片大小不能超过6GB (会员图片" . ($i + 1) . ")";
                        continue;
                    }
                    
                    // 生成唯一文件名
                    $file_extension = pathinfo($_FILES['member_images']['name'][$i], PATHINFO_EXTENSION);
                    $new_filename = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // 尝试复制文件
                    $copy_success = copy($_FILES['member_images']['tmp_name'][$i], $upload_path);
                    
                    if ($copy_success) {
                        $image_paths[$member_db_fields[$i]] = 'uploads/products/' . $new_filename;
                        
                        // 本次新上传统计
                        $uploadedImageBytes += filesize($upload_path);
                        $uploadedImageCount++;
                        $uploadedFormatSet[strtolower($file_extension)] = true;
                        $hasNewImageUpload = true;
                        
                        // 更新最终格式统计
                        $finalFormatSet[strtolower($file_extension)] = true;
                        
                        // 设置文件权限
                        chmod($upload_path, 0644);
                    } else {
                        // 如果复制失败，尝试移动
                        if (move_uploaded_file($_FILES['member_images']['tmp_name'][$i], $upload_path)) {
                            $image_paths[$member_db_fields[$i]] = 'uploads/products/' . $new_filename;
                            
                            // 本次新上传统计
                            $uploadedImageBytes += filesize($upload_path);
                            $uploadedImageCount++;
                            $uploadedFormatSet[strtolower($file_extension)] = true;
                            $hasNewImageUpload = true;
                        } else {
                            $errors[] = "会员图片上传失败，请重试 (图片" . ($i + 1) . ")";
                        }
                    }
                } else if ($_FILES['member_images']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                // 只有在有文件上传但失败时才显示错误
                    $errors[] = "会员图片" . ($i + 1) . "上传失败: " . getUploadErrorMessage($_FILES['member_images']['error'][$i]);
                }
            }
        }
        
        // 如果是编辑模式且没有上传新的主图，保留原来的图片
        if ($action == 'edit_product' && empty($image_paths['image'])) {
            $image_paths['image'] = $product_data['image'];
            $upload_success = true;
        }

        // 计算"最终保存集合"的图片统计：已存在图片 + 本次新图
        // 自动统计所有图片（游客图片和会员图片）的数量、总大小和格式
        {
            $existingCount = 0; 
            $existingBytes = 0; 
            $existingFormats = [];
            $source = $action == 'edit_product' ? $product_data : [];
            
            // 处理游客图片
            for ($i = 1; $i <= 4; $i++) {
                $field = 'image' . ($i > 1 ? $i : '');
                $path = '';
                if (!empty($image_paths[$field])) { // 新的或保留的最终路径
                    $path = $image_paths[$field];
                } elseif ($action == 'edit_product' && !empty($source[$field])) {
                    $path = $source[$field];
                }
                if (!empty($path)) {
                    $existingCount++;
                    // 尝试多种路径获取方式
                    $abs = realpath(dirname(__FILE__) . '/../' . $path);
                    if (!$abs || !file_exists($abs)) {
                        $abs = dirname(__FILE__) . '/../' . $path;
                    }
                    if (!file_exists($abs)) {
                        $abs = './' . $path;
                    }
                    
                    if (file_exists($abs)) {
                        $existingBytes += filesize($abs);
                        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                        if ($ext) { 
                            $existingFormats[$ext] = true;
                            $finalFormatSet[$ext] = true; // 直接添加到最终格式集合
                        }
                    } else {
                        error_log("无法找到游客图片文件(编辑模式): " . $path);
                    }
                }
            }
            
            // 处理会员图片
            for ($i = 1; $i <= 6; $i++) {
                $field = 'member_image' . $i;
                $path = '';
                if (!empty($image_paths[$field])) { // 新的或保留的最终路径
                    $path = $image_paths[$field];
                } elseif ($action == 'edit_product' && !empty($source[$field])) {
                    $path = $source[$field];
                }
                if (!empty($path)) {
                    $existingCount++;
                    // 尝试多种路径获取方式
                    $abs = realpath(dirname(__FILE__) . '/../' . $path);
                    if (!$abs || !file_exists($abs)) {
                        $abs = dirname(__FILE__) . '/../' . $path;
                    }
                    if (!file_exists($abs)) {
                        $abs = './' . $path;
                    }
                    
                    if (file_exists($abs)) {
                        $existingBytes += filesize($abs);
                        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                        if ($ext) { 
                            $existingFormats[$ext] = true;
                            $finalFormatSet[$ext] = true; // 直接添加到最终格式集合
                        }
                    } else {
                        error_log("无法找到会员图片文件(编辑模式): " . $path);
                    }
                }
            }
            
            $finalImageTotalBytes = $existingBytes;
            $finalImageCount = $existingCount;
            
            // 注意：这里不要重置$finalFormatSet，因为它已经在上传过程中被填充了
            // 合并已有格式和现有格式
            foreach ($existingFormats as $format => $value) {
                $finalFormatSet[$format] = true;
            }
            
            // 确保至少有一种格式
            if (empty($finalFormatSet)) {
                $finalFormatSet['jpg'] = true;
            }
            
            // 调试信息
            error_log("图片格式统计: " . print_r(array_keys($finalFormatSet), true));
            
            // 自动设置图片统计字段
            // 在编辑模式下，如果前端提供了实时统计值，优先使用前端的值
            if ($action == 'edit_product' && isset($_POST['paid_photos_count_manual']) && $_POST['paid_photos_count_manual'] !== '') {
                // 编辑模式：优先使用前端实时统计的值
                $paidPhotosCount = intval($_POST['paid_photos_count_manual']);
                $paidPhotosTotalSize = isset($_POST['paid_photos_total_size']) ? intval($_POST['paid_photos_total_size']) : $finalImageTotalBytes;
                $paidPhotosFormats = isset($_POST['paid_photos_formats_manual']) && $_POST['paid_photos_formats_manual'] !== '' ? 
                    $_POST['paid_photos_formats_manual'] : implode(', ', array_keys($finalFormatSet));
                error_log("编辑模式：使用前端实时统计值 - 数量: $paidPhotosCount, 大小: $paidPhotosTotalSize, 格式: $paidPhotosFormats");
            } else {
                // 新增模式或编辑模式无前端统计值：使用自动计算的值
            $paidPhotosCount = $finalImageCount;
            $paidPhotosTotalSize = $finalImageTotalBytes;
            $paidPhotosFormats = implode(', ', array_keys($finalFormatSet));
                error_log("使用自动计算统计值 - 数量: $paidPhotosCount, 大小: $paidPhotosTotalSize, 格式: $paidPhotosFormats");
            }
        }
        
        // 如果是编辑模式，处理图片删除和排序
        if ($action == 'edit_product') {
            // 处理图片删除
            // 游客图片
            for ($i = 1; $i <= 4; $i++) {
                $field = 'image' . ($i > 1 ? $i : '');
                $delete_field = 'delete_' . $field;
                
                // 确保product_data中的字段存在
                if (!isset($product_data[$field])) {
                    $product_data[$field] = '';
                }
                
                // 检查是否标记为删除
                if (isset($_POST[$delete_field]) && $_POST[$delete_field] == '1') {
                    // 如果标记为删除，清空图片路径
                    $image_paths[$field] = '';
                    
                    // 如果存在物理文件，删除它
                    if (!empty($product_data[$field])) {
                        $file_path = '../' . $product_data[$field];
                        if (file_exists($file_path)) {
                            @unlink($file_path);
                        }
                    }
                }
                // 如果没有标记为删除且没有上传新图片，保留原有图片
                elseif (empty($image_paths[$field])) {
                    $image_paths[$field] = $product_data[$field];
                }
            }
            
            // 会员图片
            for ($i = 1; $i <= 6; $i++) {
                $field = 'member_image' . $i;
                $delete_field = 'delete_' . $field;
                
                // 确保product_data中的字段存在
                if (!isset($product_data[$field])) {
                    $product_data[$field] = '';
                }
                
                // 检查是否标记为删除
                if (isset($_POST[$delete_field]) && $_POST[$delete_field] == '1') {
                    // 如果标记为删除，清空图片路径
                    $image_paths[$field] = '';
                    
                    // 如果存在物理文件，删除它
                    if (!empty($product_data[$field])) {
                        $file_path = '../' . $product_data[$field];
                        if (file_exists($file_path)) {
                            @unlink($file_path);
                        }
                    }
                }
                // 如果没有标记为删除且没有上传新图片，保留原有图片
                elseif (empty($image_paths[$field])) {
                    $image_paths[$field] = $product_data[$field];
                }
            }
            
            // 处理图片排序
            if (isset($_POST['swap_images']) && !empty($_POST['swap_images'])) {
                $swaps = explode(',', $_POST['swap_images']);
                
                foreach ($swaps as $swap) {
                    list($source, $target) = explode(':', $swap);
                    
                    // 确保两个字段都存在
                    if (isset($image_paths[$source]) && isset($image_paths[$target])) {
                        // 交换两个图片的路径
                        $temp = $image_paths[$source];
                        $image_paths[$source] = $image_paths[$target];
                        $image_paths[$target] = $temp;
                    }
                }
            }
            
            // 确保编辑模式下即使没有上传新图片也能更新其他信息
            $upload_success = true;
        }
        
        // 如果没有错误，保存产品
        if (empty($errors)) {
            if ($action == 'add_product') {
                // 添加新产品
                if ($custom_id > 0) {
                    // 使用自定义ID
                    $sql = "INSERT INTO products (id, title, subtitle, price, photo_pack_price, category_id, guest, image, image2, image3, image4, member_image1, member_image2, member_image3, member_image4, member_image5, member_image6, show_on_homepage, images_total_size, images_count, images_formats, paid_video, paid_video_size, paid_video_duration, paid_photos_zip, paid_photos_total_size, paid_photos_count, paid_photos_formats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        $imagesFormatsStr = implode(',', array_keys($finalFormatSet));
                        
                        // 确保图片格式统计不为空
                        if (empty($imagesFormatsStr)) {
                            $imagesFormatsStr = 'jpg';
                        }
                        
                        // 在新增模式下，将图片格式统计同步到付费图片格式
                        $paidPhotosFormats = $imagesFormatsStr;
                        error_log("新增模式：图片格式统计同步: " . $imagesFormatsStr);
                        
                        // 确保所有变量都已初始化
                        $custom_id = intval($custom_id);
                        $title = strval($title);
                        $subtitle = strval($subtitle);
                        $price = floatval($price);
                        $photo_pack_price = floatval($photo_pack_price);
                        $category_id = intval($category_id);
                        $guest_visible = intval($guest_visible);
                        $show_on_homepage = intval($show_on_homepage);
                        $finalImageTotalBytes = intval($finalImageTotalBytes);
                        $finalImageCount = intval($finalImageCount);
                        $imagesFormatsStr = strval($imagesFormatsStr);
                        $paidVideoPath = strval($paidVideoPath);
                        $paidVideoSize = intval($paidVideoSize);
                        $paidVideoDuration = intval($paidVideoDuration);
                        $paidPhotosZipPath = strval($paidPhotosZipPath);
                        $paidPhotosTotalSize = intval($paidPhotosTotalSize);
                        $paidPhotosCount = intval($paidPhotosCount);
                        $paidPhotosFormats = strval($paidPhotosFormats);
                        
                        // 确保图片路径变量已初始化
                        foreach (['image', 'image2', 'image3', 'image4', 'member_image1', 'member_image2', 'member_image3', 'member_image4', 'member_image5', 'member_image6'] as $field) {
                            if (!isset($image_paths[$field])) {
                                $image_paths[$field] = '';
                            }
                        }
                        
                        mysqli_stmt_bind_param($stmt, "isddissssssssssssiiiissiissi", 
                            $custom_id, $title, $subtitle, $price, $photo_pack_price, $category_id, $guest_visible,
                            $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'], 
                            $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                            $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6'], 
                            $show_on_homepage, $finalImageTotalBytes, $finalImageCount, $imagesFormatsStr, 
                            $paidVideoPath, $paidVideoSize, $paidVideoDuration,
                            $paidPhotosZipPath, $paidPhotosTotalSize, $paidPhotosCount, $paidPhotosFormats);

                        if (mysqli_stmt_execute($stmt)) {
                            $new_id = mysqli_insert_id($conn);
                            $success_message = "产品添加成功，ID: $new_id";
                            
                            // 添加调试日志，验证会员图片是否正确保存
                            error_log("Product added successfully with ID: $new_id");
                            error_log("Member image paths: " . 
                                "member_image1=" . ($image_paths['member_image1'] ?? 'NULL') . ", " .
                                "member_image2=" . ($image_paths['member_image2'] ?? 'NULL') . ", " .
                                "member_image3=" . ($image_paths['member_image3'] ?? 'NULL') . ", " .
                                "member_image4=" . ($image_paths['member_image4'] ?? 'NULL') . ", " .
                                "member_image5=" . ($image_paths['member_image5'] ?? 'NULL') . ", " .
                                "member_image6=" . ($image_paths['member_image6'] ?? 'NULL'));
                            
                            // 验证数据库中是否正确保存了会员图片
                            $verify_member_images_sql = "SELECT member_image1, member_image2, member_image3, member_image4, member_image5, member_image6 FROM products WHERE id = $new_id";
                            $verify_member_images_result = mysqli_query($conn, $verify_member_images_sql);
                            if ($verify_member_images_row = mysqli_fetch_assoc($verify_member_images_result)) {
                                error_log("Verified member images in database: " . 
                                    "member_image1=" . ($verify_member_images_row['member_image1'] ?? 'NULL') . ", " .
                                    "member_image2=" . ($verify_member_images_row['member_image2'] ?? 'NULL') . ", " .
                                    "member_image3=" . ($verify_member_images_row['member_image3'] ?? 'NULL') . ", " .
                                    "member_image4=" . ($verify_member_images_row['member_image4'] ?? 'NULL') . ", " .
                                    "member_image5=" . ($verify_member_images_row['member_image5'] ?? 'NULL') . ", " .
                                    "member_image6=" . ($verify_member_images_row['member_image6'] ?? 'NULL'));
                            }
                            
                            // 直接更新paid_photos_zip和paid_photos_formats字段，确保值正确
                            $updates = [];
                            if (!empty($paidPhotosZipPath)) {
                                $escaped_zip_path = mysqli_real_escape_string($conn, $paidPhotosZipPath);
                                $updates[] = "paid_photos_zip = '$escaped_zip_path'";
                            }
                            if (!empty($paidPhotosFormats)) {
                                $escaped_format = mysqli_real_escape_string($conn, $paidPhotosFormats);
                                $updates[] = "paid_photos_formats = '$escaped_format'";
                            }
                            
                            if (!empty($updates)) {
                                $direct_update_sql = "UPDATE products SET " . implode(", ", $updates) . " WHERE id = $new_id";
                                if (mysqli_query($conn, $direct_update_sql)) {
                                    error_log("Directly updated fields after insert: " . implode(", ", $updates));
                                    
                                    // 验证更新是否成功
                                    $verify_sql = "SELECT paid_photos_zip, paid_photos_formats FROM products WHERE id = $new_id";
                                    $verify_result = mysqli_query($conn, $verify_sql);
                                    if ($verify_row = mysqli_fetch_assoc($verify_result)) {
                                        error_log("Verified after insert - paid_photos_zip: " . ($verify_row['paid_photos_zip'] ?? 'NULL'));
                                        error_log("Verified after insert - paid_photos_formats: " . ($verify_row['paid_photos_formats'] ?? 'NULL'));
                                    }
                                } else {
                                    error_log("Failed to update fields after insert: " . mysqli_error($conn));
                                }
                            }
                            
                            // 清空表单数据
                            $title = $subtitle = $image_paths['image'] = '';
                            $price = 0;
                            $category_id = 0;
                            $guest_visible = 0;
                            $custom_id = 0;
                        } else {
                            $errors[] = "添加产品时出错: " . mysqli_error($conn);
                        }

                        mysqli_stmt_close($stmt);
                    }
                } else {
                    // 使用自动生成的ID
                    $sql = "INSERT INTO products (title, subtitle, price, photo_pack_price, category_id, guest, image, image2, image3, image4, member_image1, member_image2, member_image3, member_image4, member_image5, member_image6, show_on_homepage, images_total_size, images_count, images_formats, paid_video, paid_video_size, paid_video_duration, paid_photos_zip, paid_photos_total_size, paid_photos_count, paid_photos_formats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        $imagesFormatsStr = implode(',', array_keys($finalFormatSet));
                        
                        // 确保图片格式统计不为空
                        if (empty($imagesFormatsStr)) {
                            $imagesFormatsStr = 'jpg';
                        }
                        
                        // 在新增模式下，将图片格式统计同步到付费图片格式
                        $paidPhotosFormats = $imagesFormatsStr;
                        error_log("新增模式（自动ID）：图片格式统计同步: " . $imagesFormatsStr);
                        
                        // 确保所有变量都已初始化
                        $title = strval($title);
                        $subtitle = strval($subtitle);
                        $price = floatval($price);
                        $photo_pack_price = floatval($photo_pack_price);
                        $category_id = intval($category_id);
                        $guest_visible = intval($guest_visible);
                        $show_on_homepage = intval($show_on_homepage);
                        $finalImageTotalBytes = intval($finalImageTotalBytes);
                        $finalImageCount = intval($finalImageCount);
                        $imagesFormatsStr = strval($imagesFormatsStr);
                        $paidVideoPath = strval($paidVideoPath);
                        $paidVideoSize = intval($paidVideoSize);
                        $paidVideoDuration = intval($paidVideoDuration);
                        $paidPhotosZipPath = strval($paidPhotosZipPath);
                        $paidPhotosTotalSize = intval($paidPhotosTotalSize);
                        $paidPhotosCount = intval($paidPhotosCount);
                        $paidPhotosFormats = strval($paidPhotosFormats);
                        
                        // 确保图片路径变量已初始化
                        foreach (['image', 'image2', 'image3', 'image4', 'member_image1', 'member_image2', 'member_image3', 'member_image4', 'member_image5', 'member_image6'] as $field) {
                            if (!isset($image_paths[$field])) {
                                $image_paths[$field] = '';
                            }
                        }
                        
                        // 添加调试信息
                        error_log("Debug: Attempting to bind parameters for auto-generated ID insert");
                        error_log("SQL: " . $sql);
                        error_log("Parameters count: " . count([
                            $title, $subtitle, $price, $photo_pack_price, $category_id, $guest_visible,
                            $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'], 
                            $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                            $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6'], 
                            $show_on_homepage, $finalImageTotalBytes, $finalImageCount, $imagesFormatsStr, 
                            $paidVideoPath, $paidVideoSize, $paidVideoDuration,
                            $paidPhotosZipPath, $paidPhotosTotalSize, $paidPhotosCount, $paidPhotosFormats
                        ]));
                        
                        // 使用动态参数绑定方法
                        $params = [
                            $title, $subtitle, $price, $photo_pack_price, $category_id, $guest_visible,
                            $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'], 
                            $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                            $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6'], 
                            $show_on_homepage, $finalImageTotalBytes, $finalImageCount, $imagesFormatsStr, 
                            $paidVideoPath, $paidVideoSize, $paidVideoDuration,
                            $paidPhotosZipPath, $paidPhotosTotalSize, $paidPhotosCount, $paidPhotosFormats
                        ];
                        
                        // 构建类型字符串
                        $types = '';
                        foreach ($params as $param) {
                            if (is_int($param)) {
                                $types .= 'i';
                            } elseif (is_float($param)) {
                                $types .= 'd';
                            } else {
                                $types .= 's';
                            }
                        }
                        
                        error_log("动态生成的类型字符串: " . $types);
                        error_log("类型字符串长度: " . strlen($types));
                        error_log("参数数量: " . count($params));
                        
                        // 使用引用数组进行绑定
                        $bindParams = array();
                        $bindParams[] = &$types;
                        
                        for ($i = 0; $i < count($params); $i++) {
                            $bindParams[] = &$params[$i];
                        }
                        
                        call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt), $bindParams));

                        if (mysqli_stmt_execute($stmt)) {
                            $new_id = mysqli_insert_id($conn);
                            $success_message = "产品添加成功，ID: $new_id";
                            
                            // 添加调试日志，验证会员图片是否正确保存
                            error_log("Product added successfully with ID: $new_id");
                            error_log("Member image paths: " . 
                                "member_image1=" . ($image_paths['member_image1'] ?? 'NULL') . ", " .
                                "member_image2=" . ($image_paths['member_image2'] ?? 'NULL') . ", " .
                                "member_image3=" . ($image_paths['member_image3'] ?? 'NULL') . ", " .
                                "member_image4=" . ($image_paths['member_image4'] ?? 'NULL') . ", " .
                                "member_image5=" . ($image_paths['member_image5'] ?? 'NULL') . ", " .
                                "member_image6=" . ($image_paths['member_image6'] ?? 'NULL'));
                            
                            // 验证数据库中是否正确保存了会员图片
                            $verify_member_images_sql = "SELECT member_image1, member_image2, member_image3, member_image4, member_image5, member_image6 FROM products WHERE id = $new_id";
                            $verify_member_images_result = mysqli_query($conn, $verify_member_images_sql);
                            if ($verify_member_images_row = mysqli_fetch_assoc($verify_member_images_result)) {
                                error_log("Verified member images in database: " . 
                                    "member_image1=" . ($verify_member_images_row['member_image1'] ?? 'NULL') . ", " .
                                    "member_image2=" . ($verify_member_images_row['member_image2'] ?? 'NULL') . ", " .
                                    "member_image3=" . ($verify_member_images_row['member_image3'] ?? 'NULL') . ", " .
                                    "member_image4=" . ($verify_member_images_row['member_image4'] ?? 'NULL') . ", " .
                                    "member_image5=" . ($verify_member_images_row['member_image5'] ?? 'NULL') . ", " .
                                    "member_image6=" . ($verify_member_images_row['member_image6'] ?? 'NULL'));
                            }
                            
                            // 直接更新paid_photos_zip和paid_photos_formats字段，确保值正确
                            $updates = [];
                            if (!empty($paidPhotosZipPath)) {
                                $escaped_zip_path = mysqli_real_escape_string($conn, $paidPhotosZipPath);
                                $updates[] = "paid_photos_zip = '$escaped_zip_path'";
                            }
                            if (!empty($paidPhotosFormats)) {
                                $escaped_format = mysqli_real_escape_string($conn, $paidPhotosFormats);
                                $updates[] = "paid_photos_formats = '$escaped_format'";
                            }
                            
                            if (!empty($updates)) {
                                $direct_update_sql = "UPDATE products SET " . implode(", ", $updates) . " WHERE id = $new_id";
                                if (mysqli_query($conn, $direct_update_sql)) {
                                    error_log("Directly updated fields after insert: " . implode(", ", $updates));
                                    
                                    // 验证更新是否成功
                                    $verify_sql = "SELECT paid_photos_zip, paid_photos_formats FROM products WHERE id = $new_id";
                                    $verify_result = mysqli_query($conn, $verify_sql);
                                    if ($verify_row = mysqli_fetch_assoc($verify_result)) {
                                        error_log("Verified after insert - paid_photos_zip: " . ($verify_row['paid_photos_zip'] ?? 'NULL'));
                                        error_log("Verified after insert - paid_photos_formats: " . ($verify_row['paid_photos_formats'] ?? 'NULL'));
                                    }
                                } else {
                                    error_log("Failed to update fields after insert: " . mysqli_error($conn));
                                }
                            }
                            
                            // 清空表单数据
                            $title = $subtitle = $image_paths['image'] = '';
                            $price = 0;
                            $category_id = 0;
                            $guest_visible = 0;
                            $custom_id = 0;
                        } else {
                            $errors[] = "添加产品时出错: " . mysqli_error($conn);
                        }

                        mysqli_stmt_close($stmt);
                    }
                }
            } else {
                // 更新现有产品
                // 获取旧图片路径
                $old_image_paths = [];
                $sql_get_old_images = "SELECT image, image2, image3, image4, member_image1, member_image2, member_image3, member_image4, member_image5, member_image6 FROM products WHERE id = ?";
                if ($stmt_old = mysqli_prepare($conn, $sql_get_old_images)) {
                    mysqli_stmt_bind_param($stmt_old, "i", $product_id);
                    mysqli_stmt_execute($stmt_old);
                    $result_old = mysqli_stmt_get_result($stmt_old);
                    if ($row_old = mysqli_fetch_assoc($result_old)) {
                        $old_image_paths = $row_old;
                    }
                    mysqli_stmt_close($stmt_old);
                }
                
                // 删除已替换的旧图片
                // 处理游客图片
                for ($i = 1; $i <= 4; $i++) {
                    $field = 'image' . ($i > 1 ? $i : '');
                    $old_path = isset($old_image_paths[$field]) ? $old_image_paths[$field] : '';
                    $new_path = $image_paths[$field];
                    
                    // 如果有新图片上传且旧图片存在，则删除旧图片
                    if (!empty($new_path) && !empty($old_path) && $new_path != $old_path) {
                        $old_image_absolute_path = realpath(dirname(__FILE__) . '/../' . $old_path);
                        if ($old_image_absolute_path && file_exists($old_image_absolute_path)) {
                            @unlink($old_image_absolute_path);
                        } else {
                            // 尝试直接使用相对路径
                            $relative_path = dirname(__FILE__) . '/../' . $old_path;
                            if (file_exists($relative_path)) {
                                @unlink($relative_path);
                            }
                        }
                    }
                }
                
                // 处理会员专属图片
                for ($i = 1; $i <= 6; $i++) {
                    $field = 'member_image' . $i;
                    $old_path = isset($old_image_paths[$field]) ? $old_image_paths[$field] : '';
                    $new_path = $image_paths[$field];
                    
                    // 如果有新图片上传且旧图片存在，则删除旧图片
                    if (!empty($new_path) && !empty($old_path) && $new_path != $old_path) {
                        $old_image_absolute_path = realpath(dirname(__FILE__) . '/../' . $old_path);
                        if ($old_image_absolute_path && file_exists($old_image_absolute_path)) {
                            @unlink($old_image_absolute_path);
                        } else {
                            // 尝试直接使用相对路径
                            $relative_path = dirname(__FILE__) . '/../' . $old_path;
                            if (file_exists($relative_path)) {
                                @unlink($relative_path);
                            }
                        }
                    }
                }

                // 删除替换的旧视频（多路径兜底，防止残留占用存储）
                if (!empty($paidVideoPath) && !empty($product_data['paid_video']) && $paidVideoPath !== $product_data['paid_video']) {
                    $oldRel = $product_data['paid_video'];
                    $candidates = [
                        realpath(dirname(__FILE__) . '/../' . $oldRel),
                        dirname(__FILE__) . '/../' . $oldRel,
                        (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($oldRel, '/') : null)
                    ];
                    foreach ($candidates as $cand) {
                        if ($cand && file_exists($cand)) {
                            @unlink($cand);
                            break;
                        }
                    }
                }
                
                // 更新产品信息和图片路径
                // 如果需要更改ID，先处理ID更新
                if ($custom_id > 0 && $custom_id != $product_id) {
                    // 需要更改ID，使用事务处理
                    mysqli_autocommit($conn, false);

                    try {
                        // 创建临时表来存储产品数据
                        $temp_sql = "CREATE TEMPORARY TABLE temp_product AS SELECT * FROM products WHERE id = ?";
                        $temp_stmt = mysqli_prepare($conn, $temp_sql);
                        mysqli_stmt_bind_param($temp_stmt, "i", $product_id);
                        mysqli_stmt_execute($temp_stmt);
                        mysqli_stmt_close($temp_stmt);

                        // 更新临时表中的数据
                        $update_temp_sql = "UPDATE temp_product SET id = ?, title = ?, subtitle = ?, price = ?, photo_pack_price = ?, category_id = ?, guest = ?, image = ?, image2 = ?, image3 = ?, image4 = ?, member_image1 = ?, member_image2 = ?, member_image3 = ?, member_image4 = ?, member_image5 = ?, member_image6 = ?, show_on_homepage = ?";
                        $update_temp_stmt = mysqli_prepare($conn, $update_temp_sql);
                        mysqli_stmt_bind_param($update_temp_stmt, "issddissssssssssi", $custom_id, $title, $subtitle, $price, $photo_pack_price, $category_id, $guest_visible,
                            $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'], 
                            $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                            $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6'], $show_on_homepage);
                        mysqli_stmt_execute($update_temp_stmt);
                        mysqli_stmt_close($update_temp_stmt);

                        // 删除原记录
                        $delete_sql = "DELETE FROM products WHERE id = ?";
                        $delete_stmt = mysqli_prepare($conn, $delete_sql);
                        mysqli_stmt_bind_param($delete_stmt, "i", $product_id);
                        mysqli_stmt_execute($delete_stmt);
                        mysqli_stmt_close($delete_stmt);

                        // 插入新记录
                        $insert_sql = "INSERT INTO products SELECT * FROM temp_product";
                        mysqli_query($conn, $insert_sql);

                        // 删除临时表
                        mysqli_query($conn, "DROP TEMPORARY TABLE temp_product");

                        mysqli_commit($conn);
                        mysqli_autocommit($conn, true);

                        $success_message = "产品更新成功，ID已更改为: $custom_id";

                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        mysqli_autocommit($conn, true);
                        $errors[] = "更新产品ID时出错: " . $e->getMessage();
                    }
                } else {
                    // 不更改ID，正常更新
                    $sql = "UPDATE products SET title = ?, subtitle = ?, price = ?, photo_pack_price = ?, category_id = ?, guest = ?, image = ?, image2 = ?, image3 = ?, image4 = ?, member_image1 = ?, member_image2 = ?, member_image3 = ?, member_image4 = ?, member_image5 = ?, member_image6 = ?, show_on_homepage = ?, images_total_size = ?, images_count = ?, images_formats = ?, paid_video = ?, paid_video_size = ?, paid_video_duration = ?, paid_photos_zip = ?, paid_photos_total_size = ?, paid_photos_count = ?, paid_photos_formats = ? WHERE id = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        // 确保所有图片路径都不为null，防止数据库中的图片路径被覆盖为null
                        foreach ([
                            'image', 'image2', 'image3', 'image4', 
                            'member_image1', 'member_image2', 'member_image3', 'member_image4', 'member_image5', 'member_image6'
                        ] as $imgField) {
                            if (!isset($image_paths[$imgField])) {
                                $image_paths[$imgField] = '';
                            }
                        }

                        $imagesFormatsStr = implode(',', array_keys($finalFormatSet));
                        
                        // 确保图片格式统计不为空
                        if (empty($imagesFormatsStr)) {
                            $imagesFormatsStr = 'jpg';
                        }
                        
                        // 在编辑模式下，优先保持前端实时统计的格式值
                        if (isset($_POST['paid_photos_formats_manual']) && $_POST['paid_photos_formats_manual'] !== '') {
                            // 编辑模式：保持前端实时统计的格式值
                            error_log("编辑模式：保持前端实时统计的格式值: " . $paidPhotosFormats);
                        } else {
                            // 没有前端统计值时，使用自动计算的值
                        $paidPhotosFormats = $imagesFormatsStr;
                            error_log("编辑模式：使用自动计算的图片格式统计: " . $imagesFormatsStr);
                        }

                        // 添加调试信息
                        error_log("paidPhotosFormats before binding: " . $paidPhotosFormats);
                        error_log("manualFormats before binding: " . $manualFormats);
                        
                        // 确保$paidPhotosFormats的值正确
                        if (empty($paidPhotosFormats) && !empty($manualFormats)) {
                            $paidPhotosFormats = $manualFormats;
                            error_log("Resetting paidPhotosFormats to manualFormats: " . $paidPhotosFormats);
                        }
                        
                        // 确保$paidPhotosFormats不为null
                        if ($paidPhotosFormats === null) {
                            $paidPhotosFormats = '';
                            error_log("Setting paidPhotosFormats to empty string to avoid null");
                        }
                        
                        // 检查paid_photos_formats字段的数据类型
                        $check_type_sql = "SHOW COLUMNS FROM products LIKE 'paid_photos_formats'";
                        $check_type_result = mysqli_query($conn, $check_type_sql);
                        if ($check_type_row = mysqli_fetch_assoc($check_type_result)) {
                            error_log("paid_photos_formats field type: " . print_r($check_type_row, true));
                        }
                        
                        // 使用基本的参数绑定方式
                        mysqli_stmt_bind_param($stmt, 'ssddisssssssssssiiissiiissis', 
                            $title, $subtitle, 
                            $price, $photo_pack_price, 
                            $category_id, $guest_visible,
                            $image_paths['image'], $image_paths['image2'], $image_paths['image3'], $image_paths['image4'], 
                            $image_paths['member_image1'], $image_paths['member_image2'], $image_paths['member_image3'], 
                            $image_paths['member_image4'], $image_paths['member_image5'], $image_paths['member_image6'], 
                            $show_on_homepage,
                            $finalImageTotalBytes, $finalImageCount, $imagesFormatsStr, 
                            $paidVideoPath, $paidVideoSize, $paidVideoDuration,
                            $paidPhotosZipPath, $paidPhotosTotalSize, $paidPhotosCount, $paidPhotosFormats, 
                            $product_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "产品更新成功";
                        
                        // 直接更新paid_photos_formats字段，确保值正确
                        if (!empty($manualFormats)) {
                            // 使用直接的SQL查询而不是prepared statement
                            $escaped_format = mysqli_real_escape_string($conn, $manualFormats);
                            $direct_update_sql = "UPDATE products SET paid_photos_formats = '$escaped_format' WHERE id = $product_id";
                            if (mysqli_query($conn, $direct_update_sql)) {
                                error_log("Directly updated paid_photos_formats to: " . $manualFormats . " using direct query AFTER main update");
                                
                                // 立即验证更新是否成功
                                $verify_direct_sql = "SELECT paid_photos_formats FROM products WHERE id = $product_id";
                                $verify_direct_result = mysqli_query($conn, $verify_direct_sql);
                                if ($verify_direct_row = mysqli_fetch_assoc($verify_direct_result)) {
                                    error_log("Verified after direct update: " . ($verify_direct_row['paid_photos_formats'] ?? 'NULL'));
                                }
                            } else {
                                error_log("Failed to update paid_photos_formats: " . mysqli_error($conn));
                            }
                        }
                        
                        // 直接更新paid_photos_zip字段，确保值正确
                        if (!empty($paidPhotosZipPath)) {
                            // 使用直接的SQL查询而不是prepared statement
                            $escaped_zip_path = mysqli_real_escape_string($conn, $paidPhotosZipPath);
                            $direct_zip_update_sql = "UPDATE products SET paid_photos_zip = '$escaped_zip_path' WHERE id = $product_id";
                            if (mysqli_query($conn, $direct_zip_update_sql)) {
                                error_log("Directly updated paid_photos_zip to: " . $paidPhotosZipPath . " using direct query AFTER main update");
                                
                                // 立即验证更新是否成功
                                $verify_zip_sql = "SELECT paid_photos_zip FROM products WHERE id = $product_id";
                                $verify_zip_result = mysqli_query($conn, $verify_zip_sql);
                                if ($verify_zip_row = mysqli_fetch_assoc($verify_zip_result)) {
                                    error_log("Verified after direct update: " . ($verify_zip_row['paid_photos_zip'] ?? 'NULL'));
                                }
                            } else {
                                error_log("Failed to update paid_photos_zip: " . mysqli_error($conn));
                            }
                        }
                        
                        // 直接查询数据库，确认paid_photos_formats字段的值
                        $direct_sql = "SELECT paid_photos_formats, paid_photos_zip FROM products WHERE id = $product_id";
                        $direct_result = mysqli_query($conn, $direct_sql);
                        if ($direct_row = mysqli_fetch_assoc($direct_result)) {
                            error_log("Direct query paid_photos_formats: " . ($direct_row['paid_photos_formats'] ?? 'NULL'));
                            error_log("Direct query paid_photos_zip: " . ($direct_row['paid_photos_zip'] ?? 'NULL'));
                        }
                        
                        // 验证更新后的数据
                        $verify_sql = "SELECT image, image2, image3, image4, image5, image6, photo_pack_price, images_total_size, images_count, images_formats, paid_video, paid_video_size, paid_video_duration, paid_photos_zip, paid_photos_total_size, paid_photos_count, paid_photos_formats FROM products WHERE id = ?";
                        if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
                            mysqli_stmt_bind_param($verify_stmt, "i", $product_id);
                            mysqli_stmt_execute($verify_stmt);
                            $verify_result = mysqli_stmt_get_result($verify_stmt);
                            if ($verify_row = mysqli_fetch_assoc($verify_result)) {
                                // 添加调试信息
                                error_log("Verified paid_photos_formats: " . ($verify_row['paid_photos_formats'] ?? 'NULL'));
                            }
                            mysqli_stmt_close($verify_stmt);
                        }
                    } else {
                        $errors[] = "更新产品时出错: " . mysqli_error($conn);
                    }
                    
                    mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
    
    // 删除产品（包含清理视频文件与图片文件）
    if ($action == 'delete_product' && isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        
        // 先获取产品所有图片路径
        $sql = "SELECT image, image2, image3, image4, image5, image6, paid_video, paid_photos_zip FROM products WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $image_paths = [];
            $paid_video_rel = '';
            $paid_photos_zip_rel = '';
            
            if ($row = mysqli_fetch_assoc($result)) {
                $image_paths = [
                    $row['image'],
                    $row['image2'],
                    $row['image3'],
                    $row['image4'],
                    $row['image5'],
                    $row['image6']
                ];
                $paid_video_rel = $row['paid_video'];
                $paid_photos_zip_rel = $row['paid_photos_zip'];
            }
            mysqli_stmt_close($stmt);
            
            // 删除产品
            $sql = "DELETE FROM products WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // 删除所有相关图片文件
                    
                    foreach ($image_paths as $index => $path) {
                        if (!empty($path)) {
                            // 获取文件的绝对路径
                            $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
                            $alt_file_path = dirname(__FILE__) . '/../' . $path;
                            $third_attempt_path = realpath(dirname(__FILE__) . '/../') . '/' . $path;
                            
                            // 尝试多种路径方式删除文件
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            } elseif (file_exists($alt_file_path)) {
                                unlink($alt_file_path);
                            } elseif (file_exists($third_attempt_path)) {
                                unlink($third_attempt_path);
                            } else {
                                // 尝试直接使用路径
                                $direct_path = $path;
                                if (file_exists($direct_path)) {
                                    unlink($direct_path);
                                }
                            }
                        }
                    }

                    // 删除付费视频文件
                    if (!empty($paid_video_rel)) {
                        $cands = [
                            $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($paid_video_rel, '/'),
                            dirname(__FILE__) . '/../' . $paid_video_rel,
                            realpath(dirname(__FILE__) . '/../') . '/' . $paid_video_rel,
                        ];
                        foreach ($cands as $cand) {
                            if ($cand && file_exists($cand)) {
                                @unlink($cand);
                                break;
                            }
                        }
                    }
                    
                    // 删除付费图片打包文件
                    if (!empty($paid_photos_zip_rel)) {
                        $cands = [
                            $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($paid_photos_zip_rel, '/'),
                            dirname(__FILE__) . '/../' . $paid_photos_zip_rel,
                            realpath(dirname(__FILE__) . '/../') . '/' . $paid_photos_zip_rel,
                        ];
                        foreach ($cands as $cand) {
                            if ($cand && file_exists($cand)) {
                                @unlink($cand);
                                break;
                            }
                        }
                    }
                    
                    $success_message = "产品删除成功";

                    // 重定向到产品列表
                    echo '<script>
                        window.location.href = "admin.php?page=products";
                    </script>';
                    
                } else {
                    $errors[] = "删除产品时出错: " . mysqli_error($conn);
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // 批量删除产品
    if ($action == 'batch_delete_products' && isset($_POST['product_ids'])) {
        $product_ids = $_POST['product_ids'];
        
        // 验证产品ID数组
        if (!is_array($product_ids) || empty($product_ids)) {
            $errors[] = "请选择要删除的产品";
        } else {
            // 过滤和验证产品ID
            $valid_ids = [];
            foreach ($product_ids as $id) {
                $id = intval($id);
                if ($id > 0) {
                    $valid_ids[] = $id;
                }
            }
            
            if (empty($valid_ids)) {
                $errors[] = "无有效的产品ID";
            } else {
                $deleted_count = 0;
                $failed_deletes = [];
                
                foreach ($valid_ids as $product_id) {
                    // 先获取产品所有文件路径
                    $sql = "SELECT id, title, image, image2, image3, image4, image5, image6, paid_video, paid_photos_zip FROM products WHERE id = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "i", $product_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        
                        if ($row = mysqli_fetch_assoc($result)) {
                            $product_title = $row['title'];
                            $image_paths = [
                                $row['image'],
                                $row['image2'],
                                $row['image3'],
                                $row['image4'],
                                $row['image5'],
                                $row['image6']
                            ];
                            $paid_video_rel = $row['paid_video'];
                            $paid_photos_zip_rel = $row['paid_photos_zip'];
                            
                            mysqli_stmt_close($stmt);
                            
                            // 删除产品记录
                            $delete_sql = "DELETE FROM products WHERE id = ?";
                            if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                                mysqli_stmt_bind_param($delete_stmt, "i", $product_id);
                                
                                if (mysqli_stmt_execute($delete_stmt)) {
                                    $deleted_count++;
                                    
                                    // 删除所有相关文件
                                    $all_files = array_merge($image_paths, [$paid_video_rel, $paid_photos_zip_rel]);
                                    
                                    foreach ($all_files as $file_path) {
                                        if (!empty($file_path)) {
                                            $candidates = [
                                                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($file_path, '/'),
                                                dirname(__FILE__) . '/../' . $file_path,
                                                realpath(dirname(__FILE__) . '/../') . '/' . $file_path,
                                                $file_path
                                            ];
                                            
                                            foreach ($candidates as $candidate) {
                                                if ($candidate && file_exists($candidate)) {
                                                    @unlink($candidate);
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $failed_deletes[] = "产品 #{$product_id} ({$product_title})";
                                }
                                
                                mysqli_stmt_close($delete_stmt);
                            } else {
                                $failed_deletes[] = "产品 #{$product_id} ({$product_title})";
                            }
                        } else {
                            mysqli_stmt_close($stmt);
                            $failed_deletes[] = "产品 #{$product_id} (未找到)";
                        }
                    } else {
                        $failed_deletes[] = "产品 #{$product_id} (查询失败)";
                    }
                }
                
                // 设置成功消息
                if ($deleted_count > 0) {
                    $success_message = "成功删除 {$deleted_count} 个产品";
                    if (!empty($failed_deletes)) {
                        $success_message .= "，失败: " . implode(", ", $failed_deletes);
                    }
                } else {
                    $errors[] = "删除失败: " . implode(", ", $failed_deletes);
                }
                
                // 重定向到产品列表
                echo '<script>
                    window.location.href = "admin.php?page=products";
                </script>';
            }
        }
    }
}

// 处理编辑请求
$edit_mode = false;
$product_data = [
    'id' => '',
    'title' => '',
    'subtitle' => '',
    'price' => '',
    'photo_pack_price' => 0.00,
    'category_id' => '',
    'guest' => 0,
    'image' => '',
    'image2' => '', // 添加其他图片字段的初始化
    'image3' => '',
    'image4' => '',
    'image5' => '',
    'image6' => '',
    'show_on_homepage' => 0,
    'images_total_size' => 0,
    'images_count' => 0,
    'images_formats' => '',
    'paid_video' => '',
    'paid_video_size' => 0,
    'paid_video_duration' => 0,
    'paid_photos_zip' => '',
    'paid_photos_total_size' => 0,
    'paid_photos_count' => 0,
    'paid_photos_formats' => ''
];

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $product_id = intval($_GET['id']);
    
    $sql = "SELECT id, title, subtitle, price, photo_pack_price, category_id, guest, image, image2, image3, image4, member_image1, member_image2, member_image3, member_image4, member_image5, member_image6, show_on_homepage, images_total_size, images_count, images_formats, paid_video, paid_video_size, paid_video_duration, paid_photos_zip, paid_photos_total_size, paid_photos_count, paid_photos_formats FROM products WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $product_data = $row;
        }
        
        mysqli_stmt_close($stmt);
    }
}

// 保障统计用变量在非提交场景下有安全默认值
if (!isset($hasNewImageUpload)) { $hasNewImageUpload = false; }
if (!isset($uploadedImageCount)) { $uploadedImageCount = 0; }
if (!isset($uploadedImageBytes)) { $uploadedImageBytes = 0; }
if (!isset($uploadedFormatSet)) { $uploadedFormatSet = []; }
if (!isset($paidVideoSize)) { $paidVideoSize = isset($product_data['paid_video_size']) ? intval($product_data['paid_video_size']) : 0; }
if (!isset($paidVideoDuration)) { $paidVideoDuration = isset($product_data['paid_video_duration']) ? intval($product_data['paid_video_duration']) : 0; }
$existingImagesCountDB = isset($product_data['images_count']) ? intval($product_data['images_count']) : 0;
$existingImagesTotalSizeDB = isset($product_data['images_total_size']) ? intval($product_data['images_total_size']) : 0;
$existingImagesFormatsDB = isset($product_data['images_formats']) ? $product_data['images_formats'] : '';

// 获取所有类别
$categories = [];
$sql = "SELECT id, name FROM categories ORDER BY name ASC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// 获取所有产品
$products = [];

// 分页设置
$items_per_page_options = [10, 25, 50, 100]; // 每页显示数量选项
$default_items_per_page = 10; // 默认每页显示10个产品

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
$search_category = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
}
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $search_category = intval($_GET['category']);
}

// 构建SQL查询
$sql = "SELECT p.id, p.title, p.subtitle, p.price, p.guest, p.image, p.created_date, p.show_on_homepage, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";

// 添加搜索条件
if (!empty($search_term)) {
    // 搜索ID或名称
    if (is_numeric($search_term)) {
        $sql .= " AND (p.id = " . intval($search_term) . " OR p.title LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%')";
    } else {
        $sql .= " AND p.title LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%'";
    }
}

// 添加类别过滤
if (!empty($search_category)) {
    $sql .= " AND p.category_id = " . $search_category;
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
$sql .= " ORDER BY p.id DESC LIMIT " . $offset . ", " . $items_per_page;

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    mysqli_free_result($result);
}
?>

<div class="admin-content">
    <h2>产品管理</h2>
    
    <?php if (isset($success_message)): ?>
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
    <div class="admin-form-container" style="max-width: 1200px;">
        <h3><?php 
            if ($edit_mode) {
                echo '编辑产品';
            } elseif (isset($_GET['action']) && $_GET['action'] == 'batch_add') {
                echo '批量上传产品';
            } else {
                echo '添加新产品';
            }
        ?></h3>
        <?php if (isset($_GET['action']) && $_GET['action'] == 'batch_add'): ?>
        <!-- 批量上传产品表单 -->
        <style>
            .form-grid { 
                display: grid; 
                grid-template-columns: 40% 60%; 
                gap: 15px; 
            }
            .right-column {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto auto;
                gap: 15px;
            }
            .right-column > div:nth-child(3) {
                grid-column: span 2;
            }
            .form-grid .span-2 { grid-column: span 2; }
            .form-grid .span-3 { grid-column: span 3; }
            @media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } 
                .right-column { grid-template-columns: 1fr 1fr; }
            }
            @media (max-width: 768px) { 
                .right-column { grid-template-columns: 1fr; }
                .right-column > div:nth-child(3) { grid-column: span 1; }
            }
            fieldset { 
                border: 1px solid #f7a4b9; 
                padding: 12px; 
                margin-bottom: 15px; 
                border-radius: 5px; 
                background-color: #ffffff; 
            }
            fieldset legend { 
                padding: 0 8px; 
                color: #e75480; 
                font-weight: bold;
                background-color: #ffffff;
                font-size: 14px;
            }
            
            .product-form {
                margin-bottom: 20px;
                padding: 15px;
                border: 2px solid #f7a4b9;
                border-radius: 8px;
                background-color: #fff9fb;
            }
            
            .form-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                padding-bottom: 8px;
                border-bottom: 1px solid #f7a4b9;
            }
            
            .form-header h4 {
                margin: 0;
                color: #e75480;
                font-size: 18px;
            }
            
            .remove-form-button {
                background-color: #ff8da1;
                color: white;
                border: none;
                padding: 5px 10px;
                border-radius: 4px;
                cursor: pointer;
            }
            
            .remove-form-button:hover {
                background-color: #ff7c93;
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
            <p>在此页面您可以一次添加多个产品。表单与单个产品添加相同，可以根据需要添加多个产品。</p>
            <div class="batch-controls">
                <button type="button" id="add-product-form" class="add-button">添加一个产品表单</button>
                <span class="form-count">当前产品数量: <span id="product-count">1</span> / 10</span>
            </div>
        </div>
        <form method="post" enctype="multipart/form-data" id="batch-product-form">
            <input type="hidden" name="action" value="batch_add_product">
            <input type="hidden" name="product_count" id="product-count-input" value="1">
            <div id="product-forms-container">
                <!-- 初始表单将在这里动态添加 -->
            </div>
            <div class="form-buttons">
                <button type="submit" class="submit-button">批量保存所有产品</button>
            </div>
        </form>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit_product' : 'add_product'; ?>">
            <?php if ($edit_mode): ?>
            <input type="hidden" name="product_id" value="<?php echo $product_data['id']; ?>">
            <?php endif; ?>

            <style>
            .form-grid { 
                display: grid; 
    grid-template-columns: 1fr 1fr; 
                gap: 20px; 
            }
            .form-grid .span-2 { grid-column: span 2; }
            .form-grid .span-3 { grid-column: span 3; }
            @media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }
            fieldset { 
                border: 1px solid #f7a4b9; 
                padding: 15px; 
                margin-bottom: 20px; 
                border-radius: 5px; 
                background-color: #ffffff; 
            }
            fieldset legend { 
                padding: 0 10px; 
                color: #e75480; 
                font-weight: bold;
                background-color: #ffffff;
            }
            
            /* New styles for payment resource section */
            .payment-resource {
                background-color: #ffffff;
                border: 1px solid #f7a4b9;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .payment-resource h4 {
                margin-top: 0;
                color: #e75480;
                margin-bottom: 10px;
            }
            .payment-resource .form-group {
                margin-bottom: 10px;
            }
            .payment-resource input {
                border: 1px solid #f7a4b9;
                padding: 8px;
                width: 100%;
                box-sizing: border-box;
                border-radius: 4px;
            }
            .payment-resource input:focus {
                outline: none;
                border-color: #e75480;
                box-shadow: 0 0 0 2px rgba(231, 84, 128, 0.2);
            }
            
            /* Button styles */
            .btn-primary {
                background-color: #e75480;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-primary:hover {
                background-color: #d64072;
            }
            .btn-secondary {
                background-color: #ffecf0;
                color: #e75480;
                border: 1px solid #f7a4b9;
                padding: 8px 15px;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-secondary:hover {
                background-color: #ffccd5;
            }
            .btn-danger {
                background-color: #ff8da1;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-danger:hover {
                background-color: #ff7c93;
            }
            </style>

            <div class="form-grid">
                <fieldset>
                    <legend>基础信息</legend>
                    <div class="form-group">
                        <label for="custom_id">产品ID</label>
                        <input type="number" id="custom_id" name="custom_id" min="1" value="<?php echo $edit_mode ? $product_data['id'] : ''; ?>" placeholder="留空则自动生成">
                        <div class="help-text">
                            <?php if ($edit_mode): ?>当前ID: <?php echo $product_data['id']; ?>。修改此ID将更改产品的唯一标识符。<?php else: ?>可以指定自定义ID，留空则系统自动生成。<?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="title">产品名称 <span class="required">*</span></label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($product_data['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="subtitle">产品副标题</label>
                        <input type="text" id="subtitle" name="subtitle" value="<?php echo htmlspecialchars($product_data['subtitle']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="price">视频价格 ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo isset($product_data['price']) ? $product_data['price'] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="photo_pack_price">图片包价格 ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="photo_pack_price" name="photo_pack_price" value="<?php echo isset($product_data['photo_pack_price']) ? $product_data['photo_pack_price'] : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="category_id">类别 <span class="required">*</span></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">-- 选择类别 --</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($product_data['category_id'] == $category['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; align-items: center; white-space: nowrap;">
                            <label for="show_on_homepage" style="margin-right: 10px;">展示到首页</label>
                        <input type="checkbox" id="show_on_homepage" name="show_on_homepage" value="1" <?php echo ($product_data['show_on_homepage'] == 1) ? 'checked' : ''; ?>>
                        </div>
                        <span class="checkbox-label" id="homepage-display-text">
                            <?php
                            // 获取当前产品的类别
                            $categoryName = '';
                            if (!empty($product_data['category_id'])) {
                                $cat_sql = "SELECT name FROM categories WHERE id = " . intval($product_data['category_id']);
                                $cat_result = mysqli_query($conn, $cat_sql);
                                if ($cat_result && $cat_row = mysqli_fetch_assoc($cat_result)) {
                                    $categoryName = $cat_row['name'];
                                }
                            }
                            
                            if ($categoryName === 'Hair sales') {
                                echo '勾选此项表示将产品展示到首页下方的"New hair"区域（最多显示4个）';
                            } else {
                                echo '勾选此项表示将产品展示到首页上方的"精选产品"区域（最多显示4个）';
                            }
                            ?>
                        </span>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>游客图片上传（带水印）</legend>
                    <div class="form-group">
                        <label for="product_image">产品主图片 (游客可见) <span class="required">*</span></label>
                        <input type="file" id="product_image" name="product_image[]" accept="image/*" <?php echo $edit_mode ? '' : 'required'; ?> multiple onchange="previewImages(this, 'visitor-image-preview')">
                        <div class="help-text">允许的格式：JPG, PNG, GIF。最大文件大小：6GB。可一次上传多张图片（最多4张）</div>
                        <div id="visitor-image-preview"></div>
                    </div>
                </fieldset>
                
                <fieldset style="position: relative;">
                    <legend>会员专属图片上传（无水印）</legend>
                    <div class="form-group">
                        <label for="member_images">会员专属图片（无水印）</label>
                        <input type="file" id="member_images" name="member_images[]" accept="image/*" multiple onchange="previewImages(this, 'member-image-preview')">
                        <div class="help-text">会员专属图片，无水印，游客看不到。可一次上传多张图片（最多6张）</div>
                        <div id="member-image-preview"></div>
                    </div>
                </fieldset>

                <!-- 付费资源部分 - 横向排列 -->
                <fieldset class="span-3" style="margin-bottom: 20px; padding: 15px;">
                    <legend>付费资源</legend>
                    <div class="form-grid">
                        <!-- 第一行：上传部分 -->
                        <div class="form-group">
                            <label for="paid_video_file">付费视频上传</label>
                            <input type="file" id="paid_video_file" name="paid_video_file" accept="video/*,.zip,.rar,.7z" onchange="previewVideos(this, 'video-preview-container')">
                            <div class="help-text">支持格式：MP4, MOV, AVI, WEBM等视频格式，以及ZIP, RAR, 7Z等压缩文件格式。最大文件大小：6GB</div>
                            <div id="video-preview-container" class="image-preview-container">
                                <?php if (!empty($product_data['paid_video'])): ?>
                                <div class="preview-title">当前视频：</div>
                                <div class="preview-item">
                                    <video class="preview-video" controls>
                                        <source src="../<?php echo htmlspecialchars($product_data['paid_video']); ?>" type="video/mp4">
                                        您的浏览器不支持视频标签
                                    </video>
                                    <div class="preview-filename"><?php echo basename($product_data['paid_video']); ?></div>
                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="paid_photos_file">付费图片上传</label>
                            <input type="file" id="paid_photos_file" name="paid_photos_file[]" accept="image/*,.zip,.rar,.7z" multiple onchange="previewImages(this, 'photos-preview-container')">
                            <div class="help-text">支持格式：JPG, PNG, GIF, WEBP等图片格式，以及ZIP, RAR, 7Z等压缩文件格式。最大文件大小：6GB。支持多选文件一次上传，会自动压缩存储。</div>
                            <div id="photos-preview-container" class="image-preview-container"></div>
                        </div>
                        
                        <!-- 第二行：链接部分 -->
                        <div class="form-group">
                            <label for="paid_video">付费视频链接（可选）</label>
                            <input type="text" id="paid_video" name="paid_video" class="form-control" placeholder="输入链接" value="<?php echo isset($product_data['paid_video']) ? htmlspecialchars($product_data['paid_video']) : ''; ?>">
                            <div class="help-text">如果不上传视频文件，可以直接输入视频的完整URL链接</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="paid_photos_zip">付费照片打包链接</label>
                            <input type="text" id="paid_photos_zip" name="paid_photos_zip" class="form-control" placeholder="输入链接或上传图片自动生成" value="<?php echo isset($product_data['paid_photos_zip']) ? htmlspecialchars($product_data['paid_photos_zip']) : ''; ?>" readonly>
                            <div class="help-text">请输入照片文件的完整URL链接，上传图片后自动填充</div>
                        </div>
                    </div>
                                                </fieldset>
 
                <fieldset class="span-3">
                     <legend>付费图片与视频统计（实时更新）</legend>
                     <div class="form-grid">
                         <div class="form-group">
                            <label for="paid_photos_count_manual">付费图片数量</label>
                            <input type="number" id="paid_photos_count_manual" name="paid_photos_count_manual" min="0" value="<?php echo intval($product_data['paid_photos_count'] ?? 0); ?>">
                            <div class="help-text">请手动输入图片数量</div>
                        </div>
                         <div class="form-group">
                            <label for="paid_photos_formats_manual">付费图片格式</label>
                            <input type="text" id="paid_photos_formats_manual" name="paid_photos_formats_manual" value="<?php echo htmlspecialchars($product_data['paid_photos_formats'] ?? ''); ?>" placeholder="例如：png,jpg,webp">
                            <div class="help-text">请手动输入图片格式，用逗号分隔</div>
                        </div>
                         <div class="form-group">
                            <label for="paid_photos_total_size">付费图片总大小（MB）</label>
                            <input type="number" id="paid_photos_total_size" name="paid_photos_total_size" min="0" step="0.01" value="<?php echo isset($product_data['paid_photos_total_size']) ? round(intval($product_data['paid_photos_total_size']) / (1024*1024), 2) : 0; ?>">
                            <div class="help-text">请手动输入图片总大小（MB）</div>
                        </div>
                         <div class="form-group">
                            <label for="paid_video_size">付费视频大小（MB）</label>
                            <input type="number" id="paid_video_size" name="paid_video_size" min="0" step="0.01" value="<?php echo isset($product_data['paid_video_size']) ? round(intval($product_data['paid_video_size']) / (1024*1024), 2) : 0; ?>">
                            <div class="help-text">请手动输入视频大小（MB）</div>
                         </div>
                         <div class="form-group">
                            <label for="paid_video_duration">付费视频时长（分钟）</label>
                            <input type="number" id="paid_video_duration" name="paid_video_duration" min="0" step="0.01" value="<?php echo isset($product_data['paid_video_duration']) ? round(intval($product_data['paid_video_duration']) / 60, 2) : 0; ?>">
                         </div>
                     </div>
                     
                 </fieldset>


                                  <?php if ($edit_mode): ?>
                <fieldset class="span-3">
                    <legend>当前游客图片（带水印）</legend>
                    <div class="current-images" id="current-visitor-images">
                        <?php if (!empty($product_data['image'])): ?>
                            <div class="image-item" data-image-field="image">
                                <p>主图片 (游客可见)</p>
                                <img src="../<?php echo htmlspecialchars($product_data['image']); ?>" alt="当前产品主图片" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('image')">×</button>
                                <input type="hidden" name="delete_image" id="delete_image" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['image2'])): ?>
                            <div class="image-item" data-image-field="image2">
                                <p>游客图片2（带水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['image2']); ?>" alt="当前游客图片2" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('image2')">×</button>
                                <input type="hidden" name="delete_image2" id="delete_image2" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['image3'])): ?>
                            <div class="image-item" data-image-field="image3">
                                <p>游客图片3（带水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['image3']); ?>" alt="当前游客图片3" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('image3')">×</button>
                                <input type="hidden" name="delete_image3" id="delete_image3" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['image4'])): ?>
                            <div class="image-item" data-image-field="image4">
                                <p>游客图片4（带水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['image4']); ?>" alt="当前游客图片4" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('image4')">×</button>
                                <input type="hidden" name="delete_image4" id="delete_image4" value="0">
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>
                
                <fieldset class="span-3">
                    <legend>当前会员图片（无水印）</legend>
                    <div class="current-images" id="current-member-images">
                        <?php if (!empty($product_data['member_image1'])): ?>
                            <div class="image-item" data-image-field="member_image1">
                                <p>会员图片1（无水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['member_image1']); ?>" alt="当前会员图片1" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('member_image1')">×</button>
                                <input type="hidden" name="delete_member_image1" id="delete_member_image1" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['member_image2'])): ?>
                            <div class="image-item" data-image-field="member_image2">
                                <p>会员图片2（无水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['member_image2']); ?>" alt="当前会员图片2" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('member_image2')">×</button>
                                <input type="hidden" name="delete_member_image2" id="delete_member_image2" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['member_image3'])): ?>
                            <div class="image-item" data-image-field="member_image3">
                                <p>会员图片3（无水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['member_image3']); ?>" alt="当前会员图片3" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('member_image3')">×</button>
                                <input type="hidden" name="delete_member_image3" id="delete_member_image3" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['member_image4'])): ?>
                            <div class="image-item" data-image-field="member_image4">
                                <p>会员图片4（无水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['member_image4']); ?>" alt="当前会员图片4" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('member_image4')">×</button>
                                <input type="hidden" name="delete_member_image4" id="delete_member_image4" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['member_image5'])): ?>
                            <div class="image-item" data-image-field="member_image5">
                                <p>会员图片5（无水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['member_image5']); ?>" alt="当前会员图片5" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('member_image5')">×</button>
                                <input type="hidden" name="delete_member_image5" id="delete_member_image5" value="0">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product_data['member_image6'])): ?>
                            <div class="image-item" data-image-field="member_image6">
                                <p>会员图片6（无水印）</p>
                                <img src="../<?php echo htmlspecialchars($product_data['member_image6']); ?>" alt="当前会员图片6" style="max-width: 150px; max-height: 150px;">
                                <button type="button" class="delete-image-btn" onclick="markImageForDeletion('member_image6')">×</button>
                                <input type="hidden" name="delete_member_image6" id="delete_member_image6" value="0">
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>
                <?php endif; ?>
            </div>

            <div class="form-buttons">
                <button type="submit" class="submit-button"><?php echo $edit_mode ? '更新产品' : '添加产品'; ?></button>
                <a href="admin.php?page=products" class="cancel-button">取消</a>
            </div>
        </form>
        <?php endif; ?>
<?php if (isset($_GET['action']) && $_GET['action'] == 'batch_add'): ?>
        <!-- 批量上传产品的模板 -->
        <template id="product-form-template">
            <div class="product-form" id="product-form-{index}">
                <div class="form-header">
                    <h4>产品 #{index}</h4>
                    <button type="button" class="remove-form-button" data-index="{index}">删除此产品</button>
                </div>
                
                <div class="form-grid">
                    <!-- 左侧：基础信息 -->
                    <fieldset>
                        <legend>基础信息</legend>
                        <div class="form-group">
                        <label for="custom_id_{index}">产品ID</label>
                        <input type="number" id="custom_id_{index}" name="products[{index}][custom_id]" min="1">
                            <div class="help-text">可选，留空将自动分配</div>
                    </div>
                <div class="form-group">
                    <label for="title_{index}">产品名称 <span class="required">*</span></label>
                    <input type="text" id="title_{index}" name="products[{index}][title]" required>
                </div>
                <div class="form-group">
                    <label for="subtitle_{index}">产品副标题</label>
                    <input type="text" id="subtitle_{index}" name="products[{index}][subtitle]">
                </div>
                        <div class="form-group">
                        <label for="price_{index}">视频价格 ($) <span class="required">*</span></label>
                        <input type="number" id="price_{index}" name="products[{index}][price]" step="0.01" min="0" required>
                    </div>
                        <div class="form-group">
                        <label for="photo_pack_price_{index}">图片包价格 ($)</label>
                        <input type="number" id="photo_pack_price_{index}" name="products[{index}][photo_pack_price]" step="0.01" min="0">
                    </div>
                        <div class="form-group">
                            <label for="category_id_{index}">类别 <span class="required">*</span></label>
                            <select id="category_id_{index}" name="products[{index}][category_id]" required>
                                <option value="">-- 选择类别 --</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                        <div class="form-group">
                            <div style="display: flex; align-items: center; white-space: nowrap;">
                                <label for="show_on_homepage_{index}" style="margin-right: 10px;">展示到首页</label>
                                <input type="checkbox" id="show_on_homepage_{index}" name="products[{index}][show_on_homepage]" value="1">
                    </div>
                            <span class="checkbox-label">
                                勾选此项表示将产品展示到首页相应区域
                            </span>
                </div>
                    </fieldset>

                    <!-- 右侧：图片和付费资源 -->
                    <div class="right-column">
                        <!-- 游客图片 -->
                        <div>
                            <fieldset style="height: 100%;">
                                <legend>游客图片</legend>
                    <div class="form-group">
                                    <input type="file" id="guest_images_{index}" name="products[{index}][guest_images][]" accept="image/*" multiple required>
                                    <div class="help-text">有水印，最多4张</div>
                    </div>
                            </fieldset>
                        </div>
                        
                        <!-- 会员图片 -->
                        <div>
                            <fieldset style="height: 100%;">
                                <legend>会员图片</legend>
                                <div class="form-group">
                                    <input type="file" id="member_images_{index}" name="products[{index}][member_images][]" accept="image/*" multiple>
                                    <div class="help-text">无水印，最多6张</div>
                        </div>
                            </fieldset>
                    </div>

                        <!-- 付费资源 -->
                        <div>
                            <fieldset style="height: 100%;">
                                <legend>付费图片/视频</legend>
                    <div class="form-group">
                                    <label for="paid_video_{index}">视频上传</label>
                                    <input type="file" id="paid_video_{index}" name="products[{index}][paid_video]" accept="video/*,.zip,.rar,.7z" onchange="updateVideoStats(this, {index})">
                    </div>
                                
                                <div class="form-group">
                                    <label for="paid_photos_{index}">付费图片上传</label>
                                    <input type="file" id="paid_photos_{index}" name="products[{index}][paid_photos][]" accept="image/*,.zip,.rar,.7z" multiple onchange="updatePhotoStats(this, {index})">
                                    <div class="help-text">支持图片格式和压缩文件(ZIP、RAR、7Z)格式，多选图片将自动压缩为zip包</div>
                        </div>
                                
                                <!-- 统计信息 -->
                                <div class="stats-container" style="margin-top: 10px; border-top: 1px dashed #ddd; padding-top: 8px; background-color: #fff;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 5px;">
                                        <div class="form-group" style="margin-bottom: 5px; background-color: #fff;">
                                            <label for="paid_photos_count_{index}" style="font-size: 12px; display: block; margin-bottom: 2px;">付费图片数量</label>
                                            <input type="number" id="paid_photos_count_{index}" name="products[{index}][paid_photos_count]" value="0" style="height: 28px; padding: 2px 5px; width: 100%; background-color: #fff; border: 1px solid #ddd;">
                                            <div class="help-text" style="font-size: 11px; color: #777; height: 15px;">请手动输入图片数量</div>
                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 5px; background-color: #fff;">
                                            <label for="paid_photos_formats_{index}" style="font-size: 12px; display: block; margin-bottom: 2px;">付费图片格式</label>
                                            <input type="text" id="paid_photos_formats_{index}" name="products[{index}][paid_photos_formats]" placeholder="例如：png,jpg,webp" style="height: 28px; padding: 2px 5px; width: 100%; background-color: #fff; border: 1px solid #ddd;">
                                            <div class="help-text" style="font-size: 11px; color: #777; height: 15px;">请手动输入图片格式，用逗号分隔</div>
                    </div>
                        </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 5px;">
                                        <div class="form-group" style="margin-bottom: 5px; background-color: #fff;">
                                            <label for="paid_photos_total_size_{index}" style="font-size: 12px; display: block; margin-bottom: 2px;">付费图片总大小（MB）</label>
                                            <input type="number" id="paid_photos_total_size_{index}" name="products[{index}][paid_photos_total_size]" value="0" step="0.01" style="height: 28px; padding: 2px 5px; width: 100%; background-color: #fff; border: 1px solid #ddd;">
                                            <div class="help-text" id="paid_photos_size_display_{index}" style="font-size: 11px; color: #777; height: 15px;">请手动输入图片总大小（MB）</div>
                        </div>
                                        
                                        <div class="form-group" style="margin-bottom: 5px; background-color: #fff;">
                                            <label for="paid_video_size_{index}" style="font-size: 12px; display: block; margin-bottom: 2px;">付费视频大小（MB）</label>
                                            <input type="number" id="paid_video_size_{index}" name="products[{index}][paid_video_size]" value="0" step="0.01" style="height: 28px; padding: 2px 5px; width: 100%; background-color: #fff; border: 1px solid #ddd;">
                                            <div class="help-text" id="paid_video_size_display_{index}" style="font-size: 11px; color: #777; height: 15px;">请手动输入视频大小（MB）</div>
                    </div>
                        </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 5px;">
                                        <div class="form-group" style="margin-bottom: 5px; background-color: #fff;">
                                            <label for="paid_video_duration_{index}" style="font-size: 12px; display: block; margin-bottom: 2px;">付费视频时长（分钟）</label>
                                            <input type="number" id="paid_video_duration_{index}" name="products[{index}][paid_video_duration]" value="0" step="0.01" style="height: 28px; padding: 2px 5px; width: 100%; background-color: #fff; border: 1px solid #ddd;">
                                            <div class="help-text" style="font-size: 11px; color: #777; height: 15px;"></div>
                        </div>
                                        <div class="form-group" style="margin-bottom: 5px; background-color: #fff;">
                                            <label style="font-size: 12px; display: block; margin-bottom: 2px; visibility: hidden;">占位</label>
                                            <div style="height: 28px;"></div>
                                            <div class="help-text" style="font-size: 11px; color: #777; height: 15px;"></div>
                    </div>
                    </div>
                    </div>
                </fieldset>
                        </div>
                    </div>
                </div>
            </div>
        </template>
<?php endif; ?>
        
        <script>
        // 更新图片统计信息
        function updatePhotoStats(input, index) {
            if (!input.files || input.files.length === 0) {
                document.getElementById(`paid_photos_count_${index}`).value = "0";
                document.getElementById(`paid_photos_formats_${index}`).value = "";
                document.getElementById(`paid_photos_total_size_${index}`).value = "0";
                document.getElementById(`paid_photos_size_display_${index}`).innerHTML = "当前值: —";
                return;
            }
            
            // 检查是否是单个压缩文件
            if (input.files.length === 1) {
                const file = input.files[0];
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                if (['zip', 'rar', '7z'].includes(fileExt)) {
                    // 如果是压缩文件，直接更新统计信息
                    document.getElementById(`paid_photos_count_${index}`).value = "1";
                    document.getElementById(`paid_photos_formats_${index}`).value = fileExt;
                    document.getElementById(`paid_photos_total_size_${index}`).value = file.size;
                    
                    // 更新可读的大小显示
                    const sizeDisplay = formatFileSize(file.size);
                    document.getElementById(`paid_photos_size_display_${index}`).innerHTML = `当前值: ${sizeDisplay}`;
                    return;
                }
            }
            
            // 统计图片数量
            const count = input.files.length;
            document.getElementById(`paid_photos_count_${index}`).value = count;
            
            // 统计图片格式
            const formats = new Set();
            let totalSize = 0;
            
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                // 获取文件扩展名
                const extension = file.name.split('.').pop().toLowerCase();
                formats.add(extension);
                
                // 累计文件大小
                totalSize += file.size;
            }
            
            // 更新格式显示
            document.getElementById(`paid_photos_formats_${index}`).value = Array.from(formats).join(',');
            
            // 更新总大小
            document.getElementById(`paid_photos_total_size_${index}`).value = totalSize;
            
            // 更新可读的大小显示
            const sizeDisplay = formatFileSize(totalSize);
            document.getElementById(`paid_photos_size_display_${index}`).innerHTML = `当前值: ${sizeDisplay}`;
        }
        
        // 更新视频统计信息
        function updateVideoStats(input, index) {
            if (!input.files || input.files.length === 0) {
                document.getElementById(`paid_video_size_${index}`).value = "0";
                document.getElementById(`paid_video_size_display_${index}`).innerHTML = "当前值: —";
                document.getElementById(`paid_video_duration_${index}`).value = "0";
                return;
            }
            
            const file = input.files[0]; // 只取第一个视频文件
            const fileExt = file.name.split('.').pop().toLowerCase();
            
            // 更新视频大小
            // 将字节转换为MB
            document.getElementById(`paid_video_size_${index}`).value = (file.size / (1024 * 1024)).toFixed(2);
            
            // 更新可读的大小显示
            const sizeDisplay = formatFileSize(file.size);
            document.getElementById(`paid_video_size_display_${index}`).innerHTML = `当前值: ${sizeDisplay}`;
            
            // 如果是压缩文件，不尝试获取视频时长
            if (['zip', 'rar', '7z'].includes(fileExt)) {
                document.getElementById(`paid_video_duration_${index}`).value = "0";
                return;
            }
            
            // 尝试获取视频时长
            try {
                const videoElement = document.createElement('video');
                videoElement.preload = 'metadata';
                
                videoElement.onloadedmetadata = function() {
                    const duration = Math.round(videoElement.duration);
                    // 将秒转换为分钟
                    document.getElementById(`paid_video_duration_${index}`).value = (duration / 60).toFixed(2);
                    URL.revokeObjectURL(videoElement.src);
                };
                
                videoElement.src = URL.createObjectURL(file);
            } catch (e) {
                console.error("无法读取视频元数据", e);
            }
        }
        
        // 格式化文件大小为人类可读格式
        function formatFileSize(bytes) {
            if (bytes === 0) return "—";
            
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            let size = bytes;
            
            while (size >= 1024 && i < units.length - 1) {
                size /= 1024;
                i++;
            }
            
            return size >= 100 ? 
                Math.round(size) + ' ' + units[i] : 
                size.toFixed(2) + ' ' + units[i];
        }
        
        // 视频预览功能（支持多文件）
        function previewVideos(input, previewContainerId) {
            const previewContainer = document.getElementById(previewContainerId);
            if (!previewContainer) return;
            
            previewContainer.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                // 检查是否是压缩文件
                if (['zip', 'rar', '7z'].includes(fileExt)) {
                    // 如果是压缩文件，只显示文件信息，不显示预览
                    const previewTitle = document.createElement('div');
                    previewTitle.className = 'preview-title';
                    previewTitle.textContent = '已选择的视频：';
                    previewContainer.appendChild(previewTitle);
                    
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    
                    // 显示压缩包图标
                    const zipIcon = document.createElement('div');
                    zipIcon.className = 'video-icon';
                    zipIcon.innerHTML = '<i class="fa fa-file-archive-o"></i>';
                    previewItem.appendChild(zipIcon);
                    
                    const filename = document.createElement('div');
                    filename.className = 'preview-filename';
                    filename.textContent = file.name;
                    previewItem.appendChild(filename);
                    
                    // 显示文件大小
                    const fileSize = document.createElement('div');
                    fileSize.className = 'preview-filesize';
                    fileSize.textContent = formatFileSize(file.size);
                    previewItem.appendChild(fileSize);
                    
                    // 添加提示信息
                    const zipInfo = document.createElement('div');
                    zipInfo.className = 'preview-info';
                    zipInfo.textContent = '压缩文件不支持预览';
                    previewItem.appendChild(zipInfo);
                    
                    previewContainer.appendChild(previewItem);
                    
                    return;
                }
                
                const previewTitle = document.createElement('div');
                previewTitle.className = 'preview-title';
                previewTitle.textContent = '已选择的视频：';
                previewContainer.appendChild(previewTitle);
                
                const previewGrid = document.createElement('div');
                previewGrid.className = 'preview-grid';
                previewContainer.appendChild(previewGrid);
                
                // 创建视频时长隐藏字段
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'paid_video_duration_client';
                input.form && input.form.appendChild(hidden);
                
                // 记录总时长
                let totalDuration = 0;
                
                // 处理每个视频文件
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    
                    // 只为第一个视频创建播放器（节省资源）
                    if (i === 0) {
                        const videoElem = document.createElement('video');
                        videoElem.className = 'preview-video';
                        videoElem.controls = true;
                        previewItem.appendChild(videoElem);
                        
                        // 读取视频文件
                        const url = URL.createObjectURL(file);
                        videoElem.src = url;
                        
                        // 获取视频元数据（时长等）
                        videoElem.onloadedmetadata = function() {
                            if (videoElem.duration && isFinite(videoElem.duration)) {
                                totalDuration += Math.round(videoElem.duration);
                                hidden.value = totalDuration;
                                
                                // 显示视频时长信息
                                const durationInfo = document.createElement('div');
                                durationInfo.className = 'preview-filename';
                                const minutes = Math.floor(videoElem.duration / 60);
                                const seconds = Math.floor(videoElem.duration % 60);
                                durationInfo.textContent = `时长: ${minutes}分${seconds}秒`;
                                previewItem.appendChild(durationInfo);
                            }
                        };
                        
                        videoElem.onerror = function() {
                            URL.revokeObjectURL(url);
                            previewItem.innerHTML += '<div class="error-message">视频加载失败</div>';
                        };
                    } else {
                        // 对于其他视频，只显示文件名和大小
                        const videoIcon = document.createElement('div');
                        videoIcon.className = 'video-icon';
                        videoIcon.innerHTML = '<i class="fa fa-film"></i>';
                        previewItem.appendChild(videoIcon);
                    }
                    
                    const filename = document.createElement('div');
                    filename.className = 'preview-filename';
                    filename.textContent = file.name;
                    previewItem.appendChild(filename);
                    
                    // 显示文件大小
                    const fileSize = document.createElement('div');
                    fileSize.className = 'preview-filesize';
                    fileSize.textContent = formatFileSize(file.size);
                    previewItem.appendChild(fileSize);
                    
                    previewGrid.appendChild(previewItem);
                }
                
                // 如果有多个文件，显示总数
                if (input.files.length > 1) {
                    const totalInfo = document.createElement('div');
                    totalInfo.className = 'preview-total';
                    totalInfo.textContent = `共 ${input.files.length} 个视频文件`;
                    previewContainer.appendChild(totalInfo);
                }
            }
        }
        
        // 旧版视频时长获取（保留兼容性）
        (function(){
          var input = document.getElementById('paid_video');
          if (!input) return;
          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'paid_video_duration_client';
          input.form && input.form.appendChild(hidden);
          input.addEventListener('change', function(){
            hidden.value = '';
            if (!input.files || !input.files[0]) return;
            var file = input.files[0];
            var url = URL.createObjectURL(file);
            var video = document.createElement('video');
            video.preload = 'metadata';
            video.onloadedmetadata = function(){
              if (video.duration && isFinite(video.duration)) {
                hidden.value = Math.round(video.duration);
              }
              URL.revokeObjectURL(url);
            };
            video.onerror = function(){ URL.revokeObjectURL(url); };
            video.src = url;
          });
        })();
        </script>
    </div>
    <?php else: ?>
    <!-- 产品列表 -->
    <div class="page-header">
        <div class="action-buttons">
            <a href="admin.php?page=products&action=add" class="add-button">添加新产品</a>
            <a href="admin.php?page=products&action=batch_add" class="add-button">批量上传产品</a>
        </div>
        
        <!-- 批量删除按钮 - 固定显示在右上角 -->
        <div class="batch-delete-container">
            <button type="button" class="batch-delete-btn" onclick="showBatchDeleteConfirm()" title="批量删除选中的产品">
                <i class="fas fa-trash"></i> 批量删除
            </button>
        </div>
    </div>
    
    <!-- 添加搜索表单 -->
    <div class="search-container">
        <form action="admin.php" method="get" class="search-form">
            <input type="hidden" name="page" value="products">
            <input type="hidden" name="items_per_page" value="<?php echo $items_per_page; ?>">
            <div class="search-inputs">
                <input type="text" name="search" placeholder="搜索ID或名称..." value="<?php echo htmlspecialchars($search_term); ?>">
                <select name="category">
                    <option value="">所有类别</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo ($search_category == $category['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="search-button">搜索</button>
                <?php if (!empty($search_term) || !empty($search_category)): ?>
                <a href="admin.php?page=products&items_per_page=<?php echo $items_per_page; ?>" class="reset-button">重置</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40px;">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                </th>
                <th>ID</th>
                <th>图片</th>
                <th>名称</th>
                <th>类别</th>
                <th>价格</th>
                <th>首页展示</th>
                <th>添加日期</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
            <tr>
                <td colspan="9" class="no-data">暂无产品数据</td>
            </tr>
            <?php else: ?>
            <?php foreach ($products as $product): ?>
            <tr>
                <td>
                    <input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>" onchange="updateBatchDeleteButtonState()">
                </td>
                <td><?php echo $product['id']; ?></td>
                <td>
                    <?php if (!empty($product['image'])): ?>
                    <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="产品图片" style="width: 50px; height: 50px; object-fit: cover;">
                    <?php else: ?>
                    <span class="no-image">无图片</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($product['title']); ?></td>
                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                <td>$<?php echo number_format($product['price'], 2); ?></td>
                <td><?php echo $product['show_on_homepage'] ? '<span style="color: green; font-weight: bold;">是</span>' : '否'; ?></td>
                <td><?php echo date('Y-m-d', strtotime($product['created_date'])); ?></td>
                <td class="actions">
                    <a href="admin.php?page=products&action=edit&id=<?php echo $product['id']; ?>" class="edit-button">编辑</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确定要删除此产品吗？');">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" class="delete-button">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- 分页控件 -->
    <div class="pagination-container">
        <div class="pagination-wrapper">
            <div class="pagination-left"></div>
            
            <div class="pagination-center">
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="admin.php?page=products&page_num=1&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $search_category; ?>" class="page-link">首页</a>
                        <a href="admin.php?page=products&page_num=<?php echo $current_page - 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $search_category; ?>" class="page-link">上一页</a>
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
                                echo '<a href="admin.php?page=products&page_num=' . $i . '&items_per_page=' . $items_per_page . '&search=' . urlencode($search_term) . '&category=' . $search_category . '" class="page-link">' . $i . '</a>';
                            }
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="admin.php?page=products&page_num=<?php echo $current_page + 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $search_category; ?>" class="page-link">下一页</a>
                        <a href="admin.php?page=products&page_num=<?php echo $total_pages; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $search_category; ?>" class="page-link">末页</a>
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
</div>

<script>
function changeItemsPerPage(value) {
    // 获取当前URL
    var url = new URL(window.location.href);
    
    // 设置每页显示数量参数
    url.searchParams.set('items_per_page', value);
    
    // 重置页码为1
    url.searchParams.set('page_num', 1);
    
    // 跳转到新URL
    window.location.href = url.toString();
}

// 批量删除相关JavaScript函数
function toggleSelectAll(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateBatchDeleteButtonState();
}

function updateBatchDeleteButtonState() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
    const selectAllCheckbox = document.getElementById('selectAll');
    const batchDeleteBtn = document.querySelector('.batch-delete-btn');
    
    // 更新全选复选框状态
    if (checkedBoxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedBoxes.length === checkboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    }
    
    // 更新批量删除按钮状态
    if (batchDeleteBtn) {
        if (checkedBoxes.length > 0) {
            batchDeleteBtn.classList.remove('disabled');
            batchDeleteBtn.innerHTML = `<i class="fas fa-trash"></i> 批量删除 (${checkedBoxes.length})`;
            batchDeleteBtn.title = `批量删除选中的 ${checkedBoxes.length} 个产品`;
        } else {
            batchDeleteBtn.classList.add('disabled');
            batchDeleteBtn.innerHTML = `<i class="fas fa-trash"></i> 批量删除`;
            batchDeleteBtn.title = '请先选择要删除的产品';
        }
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    selectAllCheckbox.checked = false;
    selectAllCheckbox.indeterminate = false;
    
    updateBatchDeleteButtonState();
}

// 显示批量删除确认对话框
function showBatchDeleteConfirm() {
    const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        // 创建自定义提示对话框
        showCustomAlert('请先选择要删除的产品！', 'warning');
        return;
    }
    
    // 获取选中的产品ID和名称
    const selectedProducts = [];
    checkedBoxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const productId = checkbox.value;
        const productName = row.cells[3].textContent.trim(); // 名称在第4列（索引3）
        selectedProducts.push({
            id: productId,
            name: productName
        });
    });
    
    // 显示自定义确认对话框
    showBatchDeleteModal(selectedProducts);
}

// 显示自定义确认对话框
function showBatchDeleteModal(selectedProducts) {
    // 创建模态框
    const modal = document.createElement('div');
    modal.className = 'batch-delete-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeBatchDeleteModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> 批量删除确认</h3>
                <button class="modal-close" onclick="closeBatchDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="warning-text">⚠️ 您即将删除以下 <strong>${selectedProducts.length}</strong> 个产品：</p>
                <div class="product-list">
                    ${selectedProducts.slice(0, 10).map((product, index) => 
                        `<div class="product-item">
                            <span class="product-number">${index + 1}.</span>
                            <span class="product-name">${product.name}</span>
                        </div>`
                    ).join('')}
                    ${selectedProducts.length > 10 ? 
                        `<div class="product-item more-items">... 还有 ${selectedProducts.length - 10} 个产品</div>` 
                        : ''}
                </div>
                <div class="danger-notice">
                    <i class="fas fa-skull-crossbones"></i>
                    <strong>危险操作警告：</strong>
                    <ul>
                        <li>此操作将永久删除选中的所有产品</li>
                        <li>产品的所有相关文件（图片、视频等）也将被删除</li>
                        <li>此操作不可撤销，请谨慎操作！</li>
                    </ul>
                </div>
                <div class="confirm-input">
                    <label>请输入 "确认删除" 以继续：</label>
                    <input type="text" id="confirmText" placeholder="请输入：确认删除">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeBatchDeleteModal()">取消</button>
                <button class="btn btn-danger" onclick="executeBatchDelete()" id="confirmDeleteBtn" disabled>确认删除</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // 监听确认文本输入
    const confirmInput = document.getElementById('confirmText');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    
    confirmInput.addEventListener('input', function() {
        if (this.value === '确认删除') {
            confirmBtn.disabled = false;
            confirmBtn.classList.add('enabled');
        } else {
            confirmBtn.disabled = true;
            confirmBtn.classList.remove('enabled');
        }
    });
    
    // 存储选中的产品数据
    window.selectedProductsToDelete = selectedProducts;
    
    // 聚焦到输入框
    setTimeout(() => {
        confirmInput.focus();
    }, 100);
}

// 关闭模态框
function closeBatchDeleteModal() {
    const modal = document.querySelector('.batch-delete-modal');
    if (modal) {
        modal.remove();
    }
    window.selectedProductsToDelete = null;
}

// 执行批量删除
function executeBatchDelete() {
    const selectedProducts = window.selectedProductsToDelete;
    if (!selectedProducts) return;
    
    // 创建表单并提交
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    // 添加action字段
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'batch_delete_products';
    form.appendChild(actionInput);
    
    // 添加产品ID数组
    selectedProducts.forEach(product => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'product_ids[]';
        input.value = product.id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    
    // 关闭模态框
    closeBatchDeleteModal();
    
    // 显示加载提示
    showCustomAlert('正在删除产品，请稍候...', 'info');
    
    // 提交表单
    form.submit();
}

// 显示自定义提示
function showCustomAlert(message, type = 'info') {
    const alertBox = document.createElement('div');
    alertBox.className = `custom-alert alert-${type}`;
    
    const icon = type === 'warning' ? 'fas fa-exclamation-triangle' : 
                 type === 'error' ? 'fas fa-times-circle' : 
                 type === 'success' ? 'fas fa-check-circle' : 'fas fa-info-circle';
    
    alertBox.innerHTML = `
        <i class="${icon}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(alertBox);
    
    // 3秒后自动移除
    setTimeout(() => {
        if (alertBox.parentNode) {
            alertBox.remove();
        }
    }, 3000);
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 为所有产品复选框添加事件监听
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    productCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBatchDeleteButtonState);
    });
    
    // 初始化按钮状态
    updateBatchDeleteButtonState();
});
</script>

<link rel="stylesheet" href="css/data-table.css">
<style>
/* 页面头部布局 */
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

/* 批量删除模态框样式 */
.batch-delete-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(2px);
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.modal-header h3 {
    margin: 0;
    color: #dc3545;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f8f9fa;
    color: #dc3545;
}

.modal-body {
    padding: 25px;
    max-height: 50vh;
    overflow-y: auto;
}

.warning-text {
    font-size: 16px;
    margin-bottom: 20px;
    color: #495057;
}

.product-list {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
}

.product-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.product-item:last-child {
    border-bottom: none;
}

.product-number {
    font-weight: bold;
    color: #007bff;
    min-width: 30px;
}

.product-name {
    flex: 1;
    color: #495057;
    font-size: 14px;
}

.product-item.more-items {
    color: #6c757d;
    font-style: italic;
    justify-content: center;
}

.danger-notice {
    background: linear-gradient(135deg, #fff5f5, #fed7d7);
    border: 1px solid #fc8181;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    color: #c53030;
}

.danger-notice i {
    color: #e53e3e;
    margin-right: 8px;
}

.danger-notice ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.danger-notice li {
    margin-bottom: 5px;
}

.confirm-input {
    background: #fff8f0;
    border: 2px solid #fbd38d;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.confirm-input label {
    display: block;
    font-weight: bold;
    color: #c05621;
    margin-bottom: 10px;
}

.confirm-input input {
    width: 100%;
    padding: 12px;
    border: 2px solid #cbd5e0;
    border-radius: 6px;
    font-size: 16px;
    transition: border-color 0.2s ease;
}

.confirm-input input:focus {
    outline: none;
    border-color: #3182ce;
    box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    background: #f8f9fa;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 100px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: #c82333;
    transform: translateY(-1px);
}

.btn-danger:disabled {
    background: #adb5bd;
    cursor: not-allowed;
    opacity: 0.6;
}

.btn-danger.enabled {
    background: #dc3545;
    animation: pulseGlow 2s infinite;
}

@keyframes pulseGlow {
    0%, 100% {
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }
    50% {
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.6);
    }
}

/* 自定义提示框样式 */
.custom-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 6px;
    color: white;
    font-weight: 500;
    z-index: 10001;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: alertSlideIn 0.3s ease-out;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

@keyframes alertSlideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.alert-warning {
    background: linear-gradient(135deg, #f6ad55, #ed8936);
}

.alert-error {
    background: linear-gradient(135deg, #fc8181, #e53e3e);
}

.alert-success {
    background: linear-gradient(135deg, #68d391, #38a169);
}

.alert-info {
    background: linear-gradient(135deg, #63b3ed, #3182ce);
}

/* 复选框样式优化 */
.product-checkbox, #selectAll {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #007bff;
}

.product-checkbox:checked, #selectAll:checked {
    background-color: #007bff;
    border-color: #007bff;
}

/* 选中行高亮 */
.data-table tbody tr:has(.product-checkbox:checked) {
    background-color: #f8f9fa;
    border-left: 3px solid #007bff;
}

.data-table tbody tr:has(.product-checkbox:checked):hover {
    background-color: #e9ecef;
}

/* 表头复选框列样式 */
.data-table th:first-child {
    text-align: center;
    padding: 12px 8px;
}

.data-table td:first-child {
    text-align: center;
    padding: 12px 8px;
}

.data-table th {
    background-color: #ffccd5;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table tbody tr:hover {
    background-color: #fff5f7;
}
.admin-form-container {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

/* 批量上传样式 */
.batch-upload-container {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 5px;
}

.batch-controls {
    display: flex;
    align-items: center;
    margin: 15px 0;
}

.form-count {
    margin-left: 15px;
    font-weight: bold;
}

.product-form {
    background-color: #f5f5f5;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    border-left: 4px solid #9E9BC7;
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.form-header h4 {
    margin: 0;
    color: #333;
}

.remove-form-button {
    background-color: #ff6b6b;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
}

.remove-form-button:hover {
    background-color: #ff5252;
}

.submit-group {
    margin-top: 20px;
    text-align: center;
}

.admin-form-container h3 {
    margin-bottom: 20px;
    color: #4A4A4A;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #4A4A4A;
    font-weight: 500;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="email"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    box-sizing: border-box;
}

.form-group input[type="checkbox"] {
    margin-right: 5px;
}

.checkbox-label {
    color: #666;
    font-size: 14px;
}

.help-text {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    line-height: 1.4;
}

/* 图片预览样式 */
.image-preview-container {
    margin-top: 15px;
}

#photos-preview-container, #video-preview-container {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 10px;
    background-color: #fafafa;
}

.preview-title {
    font-weight: 500;
    margin-bottom: 10px;
    color: #4A4A4A;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.preview-item {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 5px;
    background-color: #f9f9f9;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    cursor: move;
    transition: all 0.2s ease;
}

.preview-item.dragging {
    opacity: 0.5;
    transform: scale(0.95);
    z-index: 1000;
}

.preview-item.drag-over {
    border: 2px dashed #4a90e2;
    background-color: #f0f7ff;
}

.delete-image-btn {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: #ff4d4f;
    color: white;
    border: none;
    font-size: 14px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    z-index: 10;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.delete-image-btn:hover {
    background-color: #ff7875;
    transform: scale(1.1);
}

.preview-image {
    max-width: 100%;
    max-height: 100px;
    object-fit: contain;
    margin-bottom: 5px;
}

.preview-video {
    max-width: 100%;
    max-height: 200px;
    margin-bottom: 5px;
}

.preview-filename {
    font-size: 11px;
    color: #666;
    text-align: center;
    word-break: break-all;
    width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.preview-filesize {
    font-size: 11px;
    color: #666;
    margin-top: 2px;
    text-align: center;
}

.stats-container {
    margin-top: 15px;
    padding: 10px;
    background-color: #f5f5f5;
    border-radius: 4px;
    border-left: 4px solid #ff9999;
}

.stats-title {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.stats-info {
    font-size: 14px;
    color: #666;
}

.stats-summary {
    margin-top: 15px;
}

.stats-box {
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stats-header {
    background-color: #ffccd5;
    color: #333;
    padding: 8px 12px;
    font-weight: bold;
    border-bottom: 1px solid #ddd;
}

.stats-content {
    padding: 10px;
    background-color: #fff;
}

.stats-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px dashed #eee;
}

.stats-row:last-child {
    border-bottom: none;
}

.stats-label {
    font-weight: 500;
    color: #555;
}

.stats-value {
    color: #333;
}

.required {
    color: #ff3860;
}

.current-image {
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    display: inline-block;
}

.current-images {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.image-item {
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    text-align: center;
    width: 180px;
    position: relative;
    cursor: move;
    transition: all 0.2s ease;
}

.image-item.marked-for-deletion {
    opacity: 0.5;
    background-color: #ffeeee;
    border: 1px dashed #ff8888;
}

.image-item.marked-for-deletion img {
    filter: grayscale(100%);
}

.image-item.marked-for-deletion p::after {
    content: " (已标记删除)";
    color: #ff4d4f;
    font-weight: bold;
}

.image-item.dragging {
    opacity: 0.5;
    transform: scale(0.95);
    z-index: 1000;
}

.image-item.drag-over {
    border: 2px dashed #4a90e2;
    background-color: #f0f7ff;
}

.image-item p {
    margin-bottom: 8px;
    font-size: 14px;
    color: #666;
}

.no-image {
    color: #999;
    font-style: italic;
}



.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background-color: #f9f9f9;
}

.actions {
    white-space: nowrap;
}

.edit-button {
    background-color: #ffccd5 !important;
    color: #333 !important;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 5px;
    text-decoration: none;
    display: inline-block;
}

.edit-button:hover {
    background-color: #f7a4b9 !important;
}

.delete-button {
    background-color: #ff8da1;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
}

.delete-button:hover {
    background-color: #ff7c93;
}

.add-button {
    background-color: #ffccd5;
    color: #333;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.add-button:hover {
    background-color: #f7a4b9;
}

.no-data {
    text-align: center;
    color: #999;
    padding: 20px;
    font-style: italic;
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

.delete-button:hover {
    background-color: #c82333;
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

.submit-button {
    background-color: #ffccd5 !important;
    color: #333 !important;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.submit-button:hover {
    background-color: #f7a4b9 !important;
}

.cancel-button {
    background-color: #ffecf0;
    color: #e75480;
    padding: 10px 20px;
    border: 1px solid #f7a4b9;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    text-decoration: none;
    display: inline-block;
    margin-left: 10px;
}

.cancel-button:hover {
    background-color: #ffccd5;
}

.add-button {
    background-color: #e75480;
    color: white;
    text-decoration: none;
}

.add-button:hover {
    background-color: #d64072;
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

.page-link {
    background-color: #ffecf0;
    color: #e75480;
    border: 1px solid #f7a4b9;
}

.page-link:hover, .page-link.active {
    background-color: #e75480;
    color: white;
    border-color: #e75480;
}
</style>

<script>
// 批量上传产品的JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // 批量上传相关功能
    const addProductFormButton = document.getElementById('add-product-form');
    const productFormsContainer = document.getElementById('product-forms-container');
    const productCountElement = document.getElementById('product-count');
    const productCountInput = document.getElementById('product-count-input');
    
    if (addProductFormButton && productFormsContainer) {
        let productCount = 1;
        
        // 初始化添加第一个表单
        if (productFormsContainer.children.length === 0) {
            addProductForm();
        }
        
        // 添加表单按钮点击事件
        addProductFormButton.addEventListener('click', function() {
            addProductForm();
        });
        
        // 添加产品表单函数
        function addProductForm() {
            // 检查表单数量限制
            const currentCount = productFormsContainer.querySelectorAll('.product-form').length;
            if (currentCount >= 10) {
                alert('最多只能同时创建10个产品表单！');
                return;
            }
            
            const template = document.getElementById('product-form-template');
            if (!template) return;
            
            const templateContent = template.innerHTML;
            const newForm = document.createElement('div');
            newForm.innerHTML = templateContent.replace(/{index}/g, productCount);
            // 插入到容器的最前面，而不是追加到末尾
            if (productFormsContainer.firstChild) {
                productFormsContainer.insertBefore(newForm, productFormsContainer.firstChild);
            } else {
            productFormsContainer.appendChild(newForm);
            }
            
            // 绑定删除按钮事件
            const removeButton = newForm.querySelector(`.remove-form-button[data-index="${productCount}"]`);
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    const index = this.getAttribute('data-index');
                    const formToRemove = document.getElementById(`product-form-${index}`);
                    if (formToRemove && productFormsContainer.querySelectorAll('.product-form').length > 1) {
                        formToRemove.remove();
                        updateProductCount();
                    } else {
                        alert('至少需要保留一个产品表单');
                    }
                });
            }
            
            // 更新计数
            productCount++;
            updateProductCount();
        }
        
        // 更新产品计数
        function updateProductCount() {
            const currentCount = productFormsContainer.querySelectorAll('.product-form').length;
            if (productCountElement) {
                productCountElement.textContent = currentCount;
            }
            if (productCountInput) {
                productCountInput.value = currentCount;
            }
            
            // 根据数量限制更新添加按钮状态
            if (addProductFormButton) {
                if (currentCount >= 10) {
                    addProductFormButton.disabled = true;
                    addProductFormButton.textContent = '已达到最大数量限制 (10个)';
                    addProductFormButton.style.backgroundColor = '#ccc';
                    addProductFormButton.style.cursor = 'not-allowed';
                } else {
                    addProductFormButton.disabled = false;
                    addProductFormButton.textContent = '添加一个产品表单';
                    addProductFormButton.style.backgroundColor = '';
                    addProductFormButton.style.cursor = '';
                }
            }
        }
    }

    // 当文档加载完成后执行其他功能
    // 获取分类选择框和首页展示文本元素
    const categorySelect = document.getElementById('category_id');
    const homepageDisplayText = document.getElementById('homepage-display-text');
    
    // 如果这两个元素都存在，添加事件监听器
    if (categorySelect && homepageDisplayText) {
        // 初始化时更新一次文本
        updateHomepageText();
        
        // 添加change事件监听器
        categorySelect.addEventListener('change', updateHomepageText);
        
        // 更新首页展示文本的函数
        function updateHomepageText() {
            // 获取所选分类的文本
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            const categoryName = selectedOption ? selectedOption.text : '';
            
            // 根据分类名称更新提示文本
            if (categoryName === 'Hair sales') {
                homepageDisplayText.textContent = '勾选此项表示将产品展示到首页下方的"New hair"区域（最多显示4个）';
            } else {
                homepageDisplayText.textContent = '勾选此项表示将产品展示到首页上方的"精选产品"区域（最多显示4个）';
            }
        }
    }
});

// 图片预览功能
function previewImages(input, previewContainerId) {
    const previewContainer = document.getElementById(previewContainerId);
    if (!previewContainer) return;
    
    previewContainer.innerHTML = ''; // 清空预览区域
    
    if (input.files && input.files.length > 0) {
        // 检查是否是压缩文件
        // 如果是单个文件且是压缩文件，则不显示预览
        if (input.files.length === 1) {
            const file = input.files[0];
            const fileExt = file.name.split('.').pop().toLowerCase();
            
            if (['zip', 'rar', '7z'].includes(fileExt)) {
                // 如果是压缩文件，只显示文件信息，不显示预览
                const previewTitle = document.createElement('div');
                previewTitle.className = 'preview-title';
                previewTitle.textContent = '图片预览:';
                previewContainer.appendChild(previewTitle);
                
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                
                // 显示压缩包图标
                const zipIcon = document.createElement('div');
                zipIcon.className = 'video-icon';
                zipIcon.innerHTML = '<i class="fa fa-file-archive-o"></i>';
                previewItem.appendChild(zipIcon);
                
                const filename = document.createElement('div');
                filename.className = 'preview-filename';
                filename.textContent = file.name;
                previewItem.appendChild(filename);
                
                // 显示文件大小
                const fileSize = document.createElement('div');
                fileSize.className = 'preview-filesize';
                fileSize.textContent = formatFileSize(file.size);
                previewItem.appendChild(fileSize);
                
                // 添加提示信息
                const zipInfo = document.createElement('div');
                zipInfo.className = 'preview-info';
                zipInfo.textContent = '压缩文件不支持预览';
                previewItem.appendChild(zipInfo);
                
                previewContainer.appendChild(previewItem);
                
                // 更新统计字段
                if (previewContainerId === 'photos-preview-container') {
                    if (document.getElementById('paid_photos_count_manual')) {
                        document.getElementById('paid_photos_count_manual').value = 1;
                    }
                    if (document.getElementById('paid_photos_formats_manual')) {
                        document.getElementById('paid_photos_formats_manual').value = fileExt;
                    }
                    if (document.getElementById('paid_photos_total_size')) {
                        document.getElementById('paid_photos_total_size').value = file.size;
                    }
                }
                
                return;
            }
        }
        
        // 创建预览标题
        const previewTitle = document.createElement('div');
        previewTitle.className = 'preview-title';
        previewTitle.textContent = '图片预览:';
        previewContainer.appendChild(previewTitle);
        
        // 创建预览图片容器
        const previewGrid = document.createElement('div');
        previewGrid.className = 'preview-grid';
        previewContainer.appendChild(previewGrid);
        
        // 统计变量
        let totalSize = 0;
        let imageCount = 0;
        let formats = new Set();
        
        // 遍历所有选择的文件
        for (let i = 0; i < input.files.length; i++) {
            const file = input.files[i];
            const fileExt = file.name.split('.').pop().toLowerCase();
            
            // 跳过压缩文件的预览
            if (['zip', 'rar', '7z'].includes(fileExt)) {
                // 统计文件大小和格式
                totalSize += file.size;
                formats.add(fileExt);
                imageCount++;
                continue;
            }
            
            // 检查是否是图片
            if (!file.type.startsWith('image/')) {
                continue;
            }
            
            // 统计图片数量
            imageCount++;
            
            // 统计文件大小
            totalSize += file.size;
            
            // 统计文件格式
            if (fileExt) formats.add(fileExt);
            
            // 创建预览项
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            previewItem.setAttribute('data-file-index', i);
            previewItem.setAttribute('draggable', 'true');
            
            // 添加拖拽事件监听
            previewItem.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('text/plain', i);
                this.classList.add('dragging');
            });
            
            previewItem.addEventListener('dragend', function() {
                this.classList.remove('dragging');
            });
            
            previewItem.addEventListener('dragover', function(e) {
                e.preventDefault();
            });
            
            previewItem.addEventListener('dragenter', function() {
                this.classList.add('drag-over');
            });
            
            previewItem.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });
            
            previewItem.addEventListener('drop', function(e) {
                e.preventDefault();
                const sourceIndex = parseInt(e.dataTransfer.getData('text/plain'));
                const targetIndex = parseInt(this.getAttribute('data-file-index'));
                
                if (sourceIndex !== targetIndex) {
                    // 重新排序文件
                    reorderFiles(input, sourceIndex, targetIndex, previewContainerId);
                }
                
                this.classList.remove('drag-over');
            });
            
            // 创建图片元素
            const img = document.createElement('img');
            img.className = 'preview-image';
            img.file = file;
            previewItem.appendChild(img);
            
            // 创建删除按钮
            const deleteButton = document.createElement('button');
            deleteButton.className = 'delete-image-btn';
            deleteButton.innerHTML = '×';
            deleteButton.title = '删除图片';
            deleteButton.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                removeFile(input, i, previewContainerId);
            };
            previewItem.appendChild(deleteButton);
            
            // 创建文件名显示
            const fileNameElement = document.createElement('div');
            fileNameElement.className = 'preview-filename';
            fileNameElement.textContent = file.name.length > 15 ? file.name.substring(0, 12) + '...' : file.name;
            previewItem.appendChild(fileNameElement);
            
            // 创建文件大小显示
            const fileSizeElement = document.createElement('div');
            fileSizeElement.className = 'preview-filesize';
            fileSizeElement.textContent = formatFileSize(file.size);
            previewItem.appendChild(fileSizeElement);
            
            // 将预览项添加到预览网格
            previewGrid.appendChild(previewItem);
            
            // 使用FileReader读取图片
            const reader = new FileReader();
            reader.onload = (function(aImg) {
                return function(e) {
                    aImg.src = e.target.result;
                };
            })(img);
            reader.readAsDataURL(file);
        }
        

        
        // 实时更新统计字段
        if (previewContainerId === 'photos-preview-container') {
            // 只有付费图片上传才更新统计
            if (document.getElementById('paid_photos_count_manual')) {
                document.getElementById('paid_photos_count_manual').value = imageCount;
            }
            if (document.getElementById('paid_photos_formats_manual')) {
                document.getElementById('paid_photos_formats_manual').value = Array.from(formats).join(',');
            }
            if (document.getElementById('paid_photos_total_size')) {
                document.getElementById('paid_photos_total_size').value = totalSize;
                
                // 更新显示的大小文本
                const sizeDisplay = document.getElementById('paid_photos_size_display');
                if (sizeDisplay) {
                    sizeDisplay.textContent = '当前值: ' + formatFileSize(totalSize);
                }
            }
            
            
        }
    }
}

// 格式化文件大小显示
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let size = bytes;
    
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    
    return size >= 100 ? 
        Math.round(size) + ' ' + units[i] : 
        size.toFixed(2) + ' ' + units[i];
}

// 删除文件
function removeFile(input, index, previewContainerId) {
    if (!input.files || input.files.length === 0) return;
    
    // 创建一个新的 FileList 对象（不包含被删除的文件）
    const dt = new DataTransfer();
    
    for (let i = 0; i < input.files.length; i++) {
        if (i !== index) {
            dt.items.add(input.files[i]);
        }
    }
    
    // 更新 input 的 files 属性
    input.files = dt.files;
    
    // 重新生成预览
    previewImages(input, previewContainerId);
}

// 重新排序文件
function reorderFiles(input, sourceIndex, targetIndex, previewContainerId) {
    if (!input.files || input.files.length === 0) return;
    
    // 创建一个新的 FileList 对象
    const dt = new DataTransfer();
    const files = Array.from(input.files);
    
    // 移动文件位置
    const movedFile = files.splice(sourceIndex, 1)[0];
    files.splice(targetIndex, 0, movedFile);
    
    // 重新添加所有文件
    for (let i = 0; i < files.length; i++) {
        dt.items.add(files[i]);
    }
    
    // 更新 input 的 files 属性
    input.files = dt.files;
    
    // 重新生成预览
    previewImages(input, previewContainerId);
}

function updatePhotoStats(input, index) {
    const countField = document.getElementById('paid_photos_count_' + index);
    const sizeField = document.getElementById('paid_photos_size_' + index);
    const formatField = document.getElementById('paid_photos_formats_' + index);
    
    if (input.files.length > 0) {
        // 计算所有文件的总大小
        let totalSize = 0;
        const formats = new Set();
        
        for (let i = 0; i < input.files.length; i++) {
            const file = input.files[i];
            totalSize += file.size;
            
            // 获取文件扩展名
            const fileName = file.name;
            const fileExt = fileName.split('.').pop().toLowerCase();
            formats.add(fileExt);
        }
        
        // 更新统计信息
        countField.value = input.files.length;
        sizeField.value = formatFileSize(totalSize);
        formatField.value = Array.from(formats).join(',');
    } else {
        // 重置统计信息
        countField.value = '0';
        sizeField.value = '0 KB';
        formatField.value = '';
    }
}

// 页面加载完成后初始化编辑模式的图片统计
// 标记图片为删除
function markImageForDeletion(fieldName) {
    // 设置隐藏字段值为1，表示需要删除
    const deleteField = document.getElementById('delete_' + fieldName);
    if (deleteField) {
        deleteField.value = '1';
    }
    
    // 视觉上标记该图片为已删除状态
    const imageItem = document.querySelector(`[data-image-field="${fieldName}"]`);
    if (imageItem) {
        imageItem.classList.add('marked-for-deletion');
        
        // 添加恢复按钮
        const deleteBtn = imageItem.querySelector('.delete-image-btn');
        if (deleteBtn) {
            deleteBtn.innerHTML = '↺';
            deleteBtn.title = '恢复图片';
            deleteBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                unmarkImageForDeletion(fieldName);
            };
        }
    }
}

// 取消标记图片为删除
function unmarkImageForDeletion(fieldName) {
    // 设置隐藏字段值为0，表示不需要删除
    const deleteField = document.getElementById('delete_' + fieldName);
    if (deleteField) {
        deleteField.value = '0';
    }
    
    // 移除已删除状态的视觉标记
    const imageItem = document.querySelector(`[data-image-field="${fieldName}"]`);
    if (imageItem) {
        imageItem.classList.remove('marked-for-deletion');
        
        // 恢复删除按钮
        const deleteBtn = imageItem.querySelector('.delete-image-btn');
        if (deleteBtn) {
            deleteBtn.innerHTML = '×';
            deleteBtn.title = '删除图片';
            deleteBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                markImageForDeletion(fieldName);
            };
        }
    }
}

// 初始化拖拽排序功能
function initDragSort() {
    const visitorImagesContainer = document.getElementById('current-visitor-images');
    const memberImagesContainer = document.getElementById('current-member-images');
    
    if (visitorImagesContainer) {
        makeContainerSortable(visitorImagesContainer, 'image');
    }
    
    if (memberImagesContainer) {
        makeContainerSortable(memberImagesContainer, 'member_image');
    }
}

// 使容器内的元素可排序
function makeContainerSortable(container, fieldPrefix) {
    const items = container.querySelectorAll('.image-item');
    
    items.forEach(item => {
        item.setAttribute('draggable', 'true');
        
        item.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', item.getAttribute('data-image-field'));
            this.classList.add('dragging');
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        
        item.addEventListener('dragenter', function() {
            this.classList.add('drag-over');
        });
        
        item.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            const sourceField = e.dataTransfer.getData('text/plain');
            const targetField = this.getAttribute('data-image-field');
            
            if (sourceField !== targetField) {
                swapImages(sourceField, targetField);
            }
            
            this.classList.remove('drag-over');
        });
    });
}

// 交换两个图片的位置
function swapImages(sourceField, targetField) {
    // 创建隐藏字段来记录交换信息
    let swapField = document.getElementById('swap_images');
    if (!swapField) {
        swapField = document.createElement('input');
        swapField.type = 'hidden';
        swapField.name = 'swap_images';
        swapField.id = 'swap_images';
        document.getElementById('product-form').appendChild(swapField);
    }
    
    // 添加交换信息到隐藏字段
    const swapInfo = `${sourceField}:${targetField}`;
    const currentValue = swapField.value;
    swapField.value = currentValue ? `${currentValue},${swapInfo}` : swapInfo;
    
    // 视觉上交换元素位置
    const sourceElement = document.querySelector(`[data-image-field="${sourceField}"]`);
    const targetElement = document.querySelector(`[data-image-field="${targetField}"]`);
    
    if (sourceElement && targetElement) {
        const sourceParent = sourceElement.parentNode;
        const targetParent = targetElement.parentNode;
        
        const sourceNext = sourceElement.nextElementSibling;
        const targetNext = targetElement.nextElementSibling;
        
        if (sourceNext === targetElement) {
            // 如果源元素在目标元素之前
            targetParent.insertBefore(targetElement, sourceElement);
        } else if (targetNext === sourceElement) {
            // 如果目标元素在源元素之前
            sourceParent.insertBefore(sourceElement, targetElement);
        } else {
            // 如果两个元素不相邻
            if (sourceNext) {
                sourceParent.insertBefore(targetElement, sourceNext);
            } else {
                sourceParent.appendChild(targetElement);
            }
            
            if (targetNext) {
                targetParent.insertBefore(sourceElement, targetNext);
            } else {
                targetParent.appendChild(sourceElement);
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // 初始化拖拽排序
    initDragSort();
    
    <?php if ($edit_mode): ?>
    // 编辑模式：初始化图片统计显示
    const countField = document.getElementById('paid_photos_count_manual');
    const sizeField = document.getElementById('paid_photos_total_size');
    const formatField = document.getElementById('paid_photos_formats_manual');
    const sizeDisplay = document.getElementById('paid_photos_size_display');
    
    // 如果字段存在且有值，确保显示正确
    if (countField && sizeField && formatField) {
        console.log('编辑模式初始化图片统计:', {
            count: countField.value,
            size: sizeField.value,
            formats: formatField.value
        });
        
        // 更新大小显示
        if (sizeDisplay && sizeField.value) {
            const sizeBytes = parseInt(sizeField.value);
            if (sizeBytes > 0) {
                sizeDisplay.textContent = '当前值: ' + formatFileSize(sizeBytes);
            }
        }
    }
    <?php endif; ?>
});
</script>