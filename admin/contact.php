<?php
// 包含数据库配置文件
require_once "db_config.php";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_contact"])) {
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
    
    // 如果没有错误，保存到数据库
    if (empty($email_err) && empty($email2_err) && empty($wechat_err)) {
        $success = true;
        
        // 保存联系邮箱1
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email', ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $contact_email, $contact_email);
        
        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
            $general_err = "保存联系邮箱1时出错: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        
        // 保存联系邮箱2
        if ($success) {
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email2', ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $contact_email2, $contact_email2);
            
            if (!mysqli_stmt_execute($stmt)) {
                $success = false;
                $general_err = "保存联系邮箱2时出错: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        
        // 保存微信号
        if ($success) {
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('wechat', ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $wechat, $wechat);
            
            if (!mysqli_stmt_execute($stmt)) {
                $success = false;
                $general_err = "保存微信号时出错: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        
        if ($success) {
            $success_message = "联系方式设置已保存！";
        }
    }
}

// 获取当前设置
$settings = array();
$sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('contact_email', 'contact_email2', 'wechat')";
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
?>

<h2>联系方式管理</h2>

<?php if (isset($success_message)): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (isset($general_err)): ?>
<div class="alert alert-error"><?php echo $general_err; ?></div>
<?php endif; ?>

<form action="" method="post">
    <div class="form-group">
        <label for="contact_email">主要联系邮箱:</label>
        <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
        <p class="form-hint">这将作为网站的主要联系邮箱显示给用户</p>
        <?php if (isset($email_err)): ?>
            <div class="error-message"><?php echo $email_err; ?></div>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <label for="contact_email2">备用联系邮箱:</label>
        <input type="email" id="contact_email2" name="contact_email2" value="<?php echo htmlspecialchars($settings['contact_email2']); ?>">
        <p class="form-hint">可选的备用联系邮箱，留空则不显示</p>
        <?php if (isset($email2_err)): ?>
            <div class="error-message"><?php echo $email2_err; ?></div>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <label for="wechat">微信号:</label>
        <input type="text" id="wechat" name="wechat" value="<?php echo htmlspecialchars($settings['wechat']); ?>" required>
        <p class="form-hint">用于微信联系的微信号或微信二维码说明</p>
        <?php if (isset($wechat_err)): ?>
            <div class="error-message"><?php echo $wechat_err; ?></div>
        <?php endif; ?>
    </div>
    
    <div class="form-buttons">
        <button type="submit" name="save_contact" class="submit-button">保存联系方式</button>
    </div>
</form>


<div class="preview-panel">
    <h3>当前联系方式预览</h3>
    <div class="contact-preview">
        <div class="contact-item">
            <strong>主要邮箱:</strong> <?php echo htmlspecialchars($settings['contact_email']); ?>
        </div>
        <?php if (!empty($settings['contact_email2'])): ?>
        <div class="contact-item">
            <strong>备用邮箱:</strong> <?php echo htmlspecialchars($settings['contact_email2']); ?>
        </div>
        <?php endif; ?>
        <div class="contact-item">
            <strong>微信号:</strong> <?php echo htmlspecialchars($settings['wechat']); ?>
        </div>
    </div>
</div>

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

.alert-error {
    color: #721c24;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
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

.form-group input[type="email"],
.form-group input[type="text"] {
    width: 100%;
    max-width: 400px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus {
    outline: none;
    border-color: #ffccd5;
    box-shadow: 0 0 0 2px rgba(255, 204, 213, 0.2);
}

.form-buttons {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}


.preview-panel {
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.preview-panel h3 {
    margin-top: 0;
    color: #495057;
}

.contact-preview {
    margin-top: 10px;
}

.contact-item {
    margin-bottom: 8px;
    padding: 5px 0;
    color: #333;
}

.contact-item strong {
    display: inline-block;
    width: 80px;
    color: #666;
}
</style>
