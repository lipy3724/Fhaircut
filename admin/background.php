<?php
// 包含数据库配置文件
require_once "db_config.php";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_background"])) {
    // 处理背景图片上传
    $background_image_err = "";
    $background_image_path = "";
    
    if (isset($_FILES["background_image"]) && $_FILES["background_image"]["error"] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_file_size = 2 * 1024 * 1024; // 2MB
        
        $file_type = $_FILES["background_image"]["type"];
        $file_size = $_FILES["background_image"]["size"];
        
        // 验证文件类型
        if (!in_array($file_type, $allowed_types)) {
            $background_image_err = "只允许上传 JPG, JPEG, PNG, GIF 格式的图片";
        }
        // 验证文件大小
        elseif ($file_size > $max_file_size) {
            $background_image_err = "文件大小不能超过 2MB";
        }
        else {
            // 生成唯一的文件名
            $file_extension = pathinfo($_FILES["background_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = "background_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
            $upload_path = "./" . $new_filename;
            
            // 移动上传的文件
            if (move_uploaded_file($_FILES["background_image"]["tmp_name"], $upload_path)) {
                $background_image_path = $new_filename;
                
                // 删除旧的背景图片
                $old_image_sql = "SELECT setting_value FROM settings WHERE setting_key = 'background_image'";
                $old_image_result = mysqli_query($conn, $old_image_sql);
                if ($old_image_result && mysqli_num_rows($old_image_result) > 0) {
                    $old_image_row = mysqli_fetch_assoc($old_image_result);
                    $old_image_path = $old_image_row['setting_value'];
                    if (!empty($old_image_path) && file_exists("./" . $old_image_path) && $old_image_path !== 'background.jpg') {
                        unlink("./" . $old_image_path);
                    }
                    mysqli_free_result($old_image_result);
                }
            } else {
                $background_image_err = "文件上传失败";
            }
        }
    }
    
    // 如果没有错误，保存到数据库
    if (empty($background_image_err) && !empty($background_image_path)) {
        // 更新或插入背景图片设置
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('background_image', ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $background_image_path, $background_image_path);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "背景图片设置已保存！";
        } else {
            $background_image_err = "保存设置时出错: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}

// 获取当前设置
$settings = array();
$sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key = 'background_image'";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($result);
}

// 如果背景图片设置不存在，设置默认值
if (!isset($settings['background_image'])) $settings['background_image'] = 'background.jpg';
?>

<h2>背景图片管理</h2>

<?php if (isset($success_message)): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label for="background_image">背景图片:</label>
        <input type="file" id="background_image" name="background_image" accept="image/*">
        <p class="form-hint">最大文件大小: 2MB，支持 JPG, JPEG, PNG, GIF 格式</p>
        <?php if (isset($background_image_err) && !empty($background_image_err)): ?>
            <div class="error-message"><?php echo $background_image_err; ?></div>
        <?php endif; ?>
        
        <div class="current-image">
            <p>当前背景图片:</p>
            <?php if (!empty($settings['background_image']) && file_exists("./" . $settings['background_image'])): ?>
                <img src="<?php echo htmlspecialchars($settings['background_image']); ?>" alt="当前背景图片" style="max-width: 400px; max-height: 200px; margin-top: 10px; object-fit: contain; border: 1px solid #ddd; border-radius: 4px;">
            <?php else: ?>
                <p style="color: #666; font-style: italic;">暂无背景图片</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="form-buttons">
        <button type="submit" name="save_background" class="submit-button">保存背景图片</button>
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
