<?php
// 包含数据库配置文件
require_once "db_config.php";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_banner"])) {
    // 处理横幅图片上传
    $banner_image_err = "";
    $banner_image_path = "";
    
    if (isset($_FILES["banner_image"]) && $_FILES["banner_image"]["error"] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_file_size = 2 * 1024 * 1024; // 2MB
        
        $file_type = $_FILES["banner_image"]["type"];
        $file_size = $_FILES["banner_image"]["size"];
        
        // 验证文件类型
        if (!in_array($file_type, $allowed_types)) {
            $banner_image_err = "只允许上传 JPG, JPEG, PNG, GIF 格式的图片";
        }
        // 验证文件大小
        elseif ($file_size > $max_file_size) {
            $banner_image_err = "文件大小不能超过 2MB";
        }
        else {
            // 生成唯一的文件名
            $file_extension = pathinfo($_FILES["banner_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = "banner_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
            $upload_path = "./" . $new_filename;
            
            // 移动上传的文件
            if (move_uploaded_file($_FILES["banner_image"]["tmp_name"], $upload_path)) {
                $banner_image_path = $new_filename;
                
                // 删除旧的横幅图片
                $old_image_sql = "SELECT setting_value FROM settings WHERE setting_key = 'banner_image'";
                $old_image_result = mysqli_query($conn, $old_image_sql);
                if ($old_image_result && mysqli_num_rows($old_image_result) > 0) {
                    $old_image_row = mysqli_fetch_assoc($old_image_result);
                    $old_image_path = $old_image_row['setting_value'];
                    if (!empty($old_image_path) && file_exists("./" . $old_image_path)) {
                        unlink("./" . $old_image_path);
                    }
                    mysqli_free_result($old_image_result);
                }
            } else {
                $banner_image_err = "文件上传失败";
            }
        }
    }
    
    // 如果没有错误，保存到数据库
    if (empty($banner_image_err) && !empty($banner_image_path)) {
        // 更新或插入横幅图片设置
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('banner_image', ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $banner_image_path, $banner_image_path);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "首页横幅设置已保存！";
        } else {
            $banner_image_err = "保存设置时出错: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}

// 获取当前设置
$settings = array();
$sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key = 'banner_image'";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($result);
}

// 如果横幅图片设置不存在，设置默认值
if (!isset($settings['banner_image'])) $settings['banner_image'] = '';
?>

<h2>首页横幅管理</h2>

<?php if (isset($success_message)): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="banner_image">横幅图片:</label>
        <input type="file" id="banner_image" name="banner_image" accept="image/*">
        <p class="form-hint">最大文件大小: 2MB，支持 JPG, JPEG, PNG, GIF 格式</p>
        <?php if (isset($banner_image_err) && !empty($banner_image_err)): ?>
            <div class="error-message"><?php echo $banner_image_err; ?></div>
        <?php endif; ?>
        
        <div class="current-image">
            <p>当前横幅图片:</p>
            <?php if (!empty($settings['banner_image']) && file_exists("./" . $settings['banner_image'])): ?>
                <img src="<?php echo htmlspecialchars($settings['banner_image']); ?>" alt="当前横幅" style="max-width: 500px; max-height: 150px; margin-top: 10px; object-fit: contain; border: 1px solid #ddd; border-radius: 4px;">
            <?php else: ?>
                <p style="color: #666; font-style: italic;">暂无横幅图片</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="form-buttons">
        <button type="submit" name="save_banner" class="submit-button">保存横幅设置</button>
    </div>
</form>


<style>
.submit-button {
    background-color: #ffccd5 !important;
    color: #333 !important;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.submit-button:hover {
    background-color: #f7a4b9 !important;
}

.form-hint {
    font-size: 12px;
    color: #777;
    margin-top: 5px;
}

.error-message {
    color: #d32f2f;
    font-size: 12px;
    margin-top: 5px;
}

.alert {
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.current-image {
    margin-top: 15px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.current-image p {
    margin: 0 0 10px 0;
    font-weight: bold;
    color: #495057;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-buttons {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

</style>
