<?php
// 包含数据库配置文件
require_once "db_config.php";

// 初始化变量
$success_message = "";
$error_message = "";

// 获取当前设置
$settings = array();
$sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('activation_title', 'activation_subtitle', 'activation_button_text', 'activation_fee', 'activation_note')";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($result);
}

// 设置默认值
if (!isset($settings['activation_title'])) $settings['activation_title'] = 'Congratulations! Registration Success!';
if (!isset($settings['activation_subtitle'])) $settings['activation_subtitle'] = 'Select Payment Method, Activate your account!';
if (!isset($settings['activation_button_text'])) $settings['activation_button_text'] = 'The account contains USD 100';
if (!isset($settings['activation_fee'])) $settings['activation_fee'] = '100.00';
if (!isset($settings['activation_note'])) $settings['activation_note'] = 'Note: You must activate your account to access all features of our website. Without activation, you can only browse as a guest.';

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_activation"])) {
    // 获取表单数据
    $activation_title = trim($_POST["activation_title"]);
    $activation_subtitle = trim($_POST["activation_subtitle"]);
    $activation_button_text = ""; // 设置为空字符串，完全移除此选项
    $activation_fee = trim($_POST["activation_fee"]);
    $activation_note = trim($_POST["activation_note"]);
    
    // 验证价格格式
    if (!is_numeric($activation_fee) || $activation_fee <= 0) {
        $error_message = "价格必须是大于0的数字";
    } else {
        // 更新设置
        $settings_to_update = [
            'activation_title' => $activation_title,
            'activation_subtitle' => $activation_subtitle,
            'activation_button_text' => $activation_button_text,
            'activation_fee' => $activation_fee,
            'activation_note' => $activation_note
        ];
        
        $update_success = true;
        
        foreach ($settings_to_update as $key => $value) {
            // 检查设置是否已存在
            $check_sql = "SELECT id FROM settings WHERE setting_key = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $key);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            $exists = mysqli_stmt_num_rows($check_stmt) > 0;
            mysqli_stmt_close($check_stmt);
            
            if ($exists) {
                // 更新现有设置
                $sql = "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ss", $value, $key);
            } else {
                // 插入新设置
                $sql = "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ss", $key, $value);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                $update_success = false;
                $error_message = "保存设置时出错: " . mysqli_error($conn);
                break;
            }
            
            mysqli_stmt_close($stmt);
        }
        
        if ($update_success) {
            $success_message = "激活页面设置已保存！";
            
            // 更新本地变量以反映新的设置
            $settings['activation_title'] = $activation_title;
            $settings['activation_subtitle'] = $activation_subtitle;
            $settings['activation_button_text'] = $activation_button_text;
            $settings['activation_fee'] = $activation_fee;
            $settings['activation_note'] = $activation_note;
            
            // 更新activate_account.php中的激活费用变量
            $activate_account_file = "../activate_account.php";
            if (file_exists($activate_account_file)) {
                $file_content = file_get_contents($activate_account_file);
                
                // 使用正则表达式替换激活费用
                $pattern = '/\$activation_fee\s*=\s*\d+(\.\d+)?;/';
                $replacement = '$activation_fee = ' . $activation_fee . ';';
                $new_content = preg_replace($pattern, $replacement, $file_content);
                
                if ($new_content !== $file_content) {
                    file_put_contents($activate_account_file, $new_content);
                }
            }
        }
    }
}
?>

<h2>注册激活页面管理</h2>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<form action="" method="post">
    <div class="form-group">
        <label for="activation_title">激活页面标题:</label>
        <input type="text" id="activation_title" name="activation_title" value="<?php echo htmlspecialchars($settings['activation_title']); ?>" required>
        <p class="form-hint">显示在激活页面顶部的主标题</p>
    </div>
    
    <div class="form-group">
        <label for="activation_subtitle">激活页面副标题:</label>
        <input type="text" id="activation_subtitle" name="activation_subtitle" value="<?php echo htmlspecialchars($settings['activation_subtitle']); ?>" required>
        <p class="form-hint">显示在主标题下方的说明文字</p>
    </div>
    
    <!-- 已删除激活按钮文字输入框 -->
    
    <div class="form-group">
        <label for="activation_fee">激活费用 (USD):</label>
        <input type="number" id="activation_fee" name="activation_fee" value="<?php echo htmlspecialchars($settings['activation_fee']); ?>" step="0.01" min="0.01" required>
        <p class="form-hint">用户需要支付的激活费用</p>
    </div>
    
    <div class="form-group">
        <label for="activation_note">底部提示文字:</label>
        <textarea id="activation_note" name="activation_note" rows="3" required><?php echo htmlspecialchars($settings['activation_note']); ?></textarea>
        <p class="form-hint">显示在激活页面底部的提示信息</p>
    </div>
    
    <div class="form-group">
        <h3>激活页面预览</h3>
        <div class="preview-container">
            <div class="preview-header">
                <h3 id="preview_title"><?php echo htmlspecialchars($settings['activation_title']); ?></h3>
                <p id="preview_subtitle"><?php echo htmlspecialchars($settings['activation_subtitle']); ?></p>
                <!-- 已删除激活按钮文字 -->
            </div>
            
            <div class="preview-details">
                <div class="preview-row">
                    <div class="preview-label">Username:</div>
                    <div class="preview-value">example_user</div>
                </div>
                <div class="preview-row">
                    <div class="preview-label">Payment amount:</div>
                    <div class="preview-value highlight">USD <span id="preview_fee"><?php echo number_format((float)$settings['activation_fee'], 2); ?></span></div>
                </div>
            </div>
            
            <div class="preview-button-container">
                <div class="preview-paypal-button">PayPal 按钮</div>
            </div>
            
            <div class="preview-note">
                <p id="preview_note"><?php echo htmlspecialchars($settings['activation_note']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="form-buttons">
        <button type="submit" name="save_activation" class="submit-button">保存设置</button>
    </div>
</form>

<script>
// 实时预览功能
document.getElementById('activation_title').addEventListener('input', function() {
    document.getElementById('preview_title').textContent = this.value;
});

document.getElementById('activation_subtitle').addEventListener('input', function() {
    document.getElementById('preview_subtitle').textContent = this.value;
});

// 已删除激活按钮文字相关代码

document.getElementById('activation_fee').addEventListener('input', function() {
    document.getElementById('preview_fee').textContent = parseFloat(this.value).toFixed(2);
});

document.getElementById('activation_note').addEventListener('input', function() {
    document.getElementById('preview_note').textContent = this.value;
});
</script>

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

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input, .form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 14px;
}

.form-buttons {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
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

/* 预览样式 */
.preview-container {
    max-width: 600px;
    margin: 20px auto;
    padding: 30px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
    text-align: center;
}

.preview-header {
    margin-bottom: 30px;
}

.preview-header h3 {
    color: #333;
    margin-bottom: 10px;
}

.preview-header p {
    color: #666;
    font-size: 16px;
    margin: 5px 0;
}

.preview-details {
    margin: 30px 0;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 5px;
}

.preview-row {
    display: flex;
    justify-content: space-between;
    margin: 10px 0;
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.preview-row:last-child {
    border-bottom: none;
    font-weight: bold;
}

.preview-label {
    text-align: left;
    color: #555;
}

.preview-value {
    text-align: right;
    color: #333;
}

.preview-value.highlight {
    color: #e75480;
    font-weight: bold;
    font-size: 18px;
}

.preview-button-container {
    margin: 30px 0;
}

.preview-paypal-button {
    display: inline-block;
    padding: 10px 20px;
    background-color: #0070ba;
    color: white;
    border-radius: 25px;
    font-weight: bold;
    font-size: 16px;
}

.preview-note {
    margin-top: 30px;
    font-size: 14px;
    color: #777;
}
</style>
