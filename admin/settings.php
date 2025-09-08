<?php
// 包含数据库配置文件
require_once "db_config.php";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_settings"])) {
          // 获取表单数据
      $contact_email = trim($_POST["contact_email"]);
      $contact_email2 = trim($_POST["contact_email2"]);
      $wechat = trim($_POST["wechat"]);
      
      // 验证邮箱
    if (empty($contact_email)) {
        $email_err = "请输入联系邮箱";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "请输入有效的邮箱地址";
    }
    
          // 验证邮箱2
      if (!empty($contact_email2) && !filter_var($contact_email2, FILTER_VALIDATE_EMAIL)) {
          $email2_err = "请输入有效的邮箱地址";
      }
      
      // 验证微信
      if (empty($wechat)) {
          $wechat_err = "请输入微信号";
      }
    
    // 处理横幅图片上传
    $banner_image_err = "";
    $banner_image_path = "";
    $background_image_err = "";
    $background_image_path = "";
    
    if (isset($_FILES["banner_image"]) && $_FILES["banner_image"]["error"] == 0) {
        $allowed_types = array("image/jpeg", "image/jpg", "image/png", "image/gif");
        $max_size = 2 * 1024 * 1024; // 2MB
        
        // 检查文件类型和大小
        if (!in_array($_FILES["banner_image"]["type"], $allowed_types)) {
            $banner_image_err = "只允许上传JPG、PNG和GIF格式的图片";
        } elseif ($_FILES["banner_image"]["size"] > $max_size) {
            $banner_image_err = "图片大小不能超过2MB";
        } else {
            // 创建上传目录（如果不存在）
            $upload_dir = "./uploads/banners/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // 生成唯一文件名
            $file_extension = pathinfo($_FILES["banner_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = "banner_" . time() . "." . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            // 上传文件
            if (move_uploaded_file($_FILES["banner_image"]["tmp_name"], $target_path)) {
                $banner_image_path = "uploads/banners/" . $new_filename;
            } else {
                $banner_image_err = "上传图片时出错，请重试";
            }
        }
    }
    
          // 如果没有错误，更新设置
      if (empty($email_err) && empty($email2_err) && empty($wechat_err) && empty($banner_image_err)) {
        // 更新邮箱
        $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = 'contact_email'";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $contact_email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
                  // 更新邮箱2
          $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = 'contact_email2'";
          if ($stmt = mysqli_prepare($conn, $sql)) {
              mysqli_stmt_bind_param($stmt, "s", $contact_email2);
              mysqli_stmt_execute($stmt);
              mysqli_stmt_close($stmt);
          }
          
          // 更新微信
          $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = 'wechat'";
          if ($stmt = mysqli_prepare($conn, $sql)) {
              mysqli_stmt_bind_param($stmt, "s", $wechat);
              mysqli_stmt_execute($stmt);
              mysqli_stmt_close($stmt);
          }
        
        // 处理背景图片上传
    if (isset($_FILES["background_image"]) && $_FILES["background_image"]["error"] == 0) {
        $allowed_types = array("image/jpeg", "image/jpg", "image/png", "image/gif");
        $max_size = 2 * 1024 * 1024; // 2MB
        
        // 检查文件类型和大小
        if (!in_array($_FILES["background_image"]["type"], $allowed_types)) {
            $background_image_err = "只允许上传JPG、PNG和GIF格式的图片";
        } elseif ($_FILES["background_image"]["size"] > $max_size) {
            $background_image_err = "图片大小不能超过2MB";
        } else {
            // 创建上传目录（如果不存在）
            $upload_dir = "./uploads/backgrounds/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // 生成唯一文件名
            $file_extension = pathinfo($_FILES["background_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = "background_" . time() . "." . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            // 上传文件
            if (move_uploaded_file($_FILES["background_image"]["tmp_name"], $target_path)) {
                $background_image_path = "uploads/backgrounds/" . $new_filename;
            } else {
                $background_image_err = "上传图片时出错，请重试";
            }
        }
    }
    
    // 如果有新的横幅图片，更新banner_image设置
        if (!empty($banner_image_path)) {
            // 检查是否已存在banner_image设置
            $check_sql = "SELECT COUNT(*) FROM settings WHERE setting_key = 'banner_image'";
            $result = mysqli_query($conn, $check_sql);
            $row = mysqli_fetch_row($result);
            
            if ($row[0] > 0) {
                // 更新现有设置
                $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = 'banner_image'";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $banner_image_path);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                // 插入新设置
                $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('banner_image', ?)";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $banner_image_path);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        // 如果有新的背景图片，更新background_image设置
        if (!empty($background_image_path)) {
            // 检查是否已存在background_image设置
            $check_sql = "SELECT COUNT(*) FROM settings WHERE setting_key = 'background_image'";
            $result = mysqli_query($conn, $check_sql);
            $row = mysqli_fetch_row($result);
            
            if ($row[0] > 0) {
                // 更新现有设置
                $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = 'background_image'";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $background_image_path);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                // 插入新设置
                $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('background_image', ?)";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "s", $background_image_path);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
        // 保存其他设置
        $settings_to_update = [];
        
        foreach ($settings_to_update as $key) {
            if (isset($_POST[$key])) {
                $value = trim($_POST[$key]);
                $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ss", $value, $key);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        
            $success_message = "设置已成功保存！";
}
}

// 从数据库获取设置
$settings = [];
$sql = "SELECT setting_key, setting_value FROM settings";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($result);
}

// 如果某些设置不存在，设置默认值
if (!isset($settings['contact_email'])) $settings['contact_email'] = 'info@haircut.network';
if (!isset($settings['contact_email2'])) $settings['contact_email2'] = 'support@haircut.network';
if (!isset($settings['wechat'])) $settings['wechat'] = 'haircut_wechat';
if (!isset($settings['banner_image'])) $settings['banner_image'] = '';
if (!isset($settings['background_image'])) $settings['background_image'] = 'background.jpg';
?>

<h2>网站设置</h2>

<?php if (isset($success_message)): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data">
    <h3>首页背景图片设置</h3>
    
    <div class="form-group">
        <label for="background_image">背景图片:</label>
        <input type="file" id="background_image" name="background_image" accept="image/*">
        <p class="form-hint">最大文件大小: 2MB</p>
        <?php if (isset($background_image_err) && !empty($background_image_err)): ?>
            <div class="error-message"><?php echo $background_image_err; ?></div>
        <?php endif; ?>
        
        <div class="current-image">
            <p>当前背景图片:</p>
            <?php if (!empty($settings['background_image']) && file_exists("./" . $settings['background_image'])): ?>
                <img src="<?php echo htmlspecialchars($settings['background_image']); ?>" alt="当前背景图片" style="max-width: 300px; max-height: 150px; margin-top: 10px; object-fit: contain;">
            <?php else: ?>
                <p>暂无背景图片</p>
            <?php endif; ?>
        </div>
    </div>
    
    <h3>首页横幅设置</h3>
    
    <div class="form-group">
        <label for="banner_image">横幅图片:</label>
        <input type="file" id="banner_image" name="banner_image" accept="image/*">
        <p class="form-hint">最大文件大小: 2MB</p>
        <?php if (isset($banner_image_err) && !empty($banner_image_err)): ?>
            <div class="error-message"><?php echo $banner_image_err; ?></div>
        <?php endif; ?>
        
        <div class="current-image">
            <p>当前图片:</p>
            <?php if (!empty($settings['banner_image']) && file_exists("./" . $settings['banner_image'])): ?>
                <img src="<?php echo htmlspecialchars($settings['banner_image']); ?>" alt="当前横幅" style="max-width: 300px; max-height: 150px; margin-top: 10px; object-fit: contain;">
            <?php else: ?>
                <p>暂无横幅图片</p>
            <?php endif; ?>
        </div>
    </div>
    
    <h3>页脚联系信息</h3>
    
          <div class="form-group">
          <label for="contact_email">联系邮箱1:</label>
          <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
          <?php if (isset($email_err)): ?>
              <div class="error-message"><?php echo $email_err; ?></div>
          <?php endif; ?>
      </div>
      
      <div class="form-group">
          <label for="contact_email2">联系邮箱2:</label>
          <input type="email" id="contact_email2" name="contact_email2" value="<?php echo htmlspecialchars($settings['contact_email2']); ?>">
          <?php if (isset($email2_err)): ?>
              <div class="error-message"><?php echo $email2_err; ?></div>
          <?php endif; ?>
      </div>
      
      <div class="form-group">
          <label for="wechat">微信号:</label>
          <input type="text" id="wechat" name="wechat" value="<?php echo htmlspecialchars($settings['wechat']); ?>" required>
          <?php if (isset($wechat_err)): ?>
              <div class="error-message"><?php echo $wechat_err; ?></div>
          <?php endif; ?>
      </div>
    
    <div class="form-buttons">
        <button type="submit" name="save_settings" class="submit-button" style="background-color: #ffccd5; color: #333; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">保存设置</button>
    </div>
</form>

<style>
.submit-button {
    background-color: #ffccd5 !important;
    color: #333 !important;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
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
    color: #e74c3c;
    font-size: 14px;
    margin-top: 5px;
}

.current-image {
    margin-top: 15px;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    background-color: #f9f9f9;
}
</style> 