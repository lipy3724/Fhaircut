<?php
// 包含数据库配置文件
require_once "db_config.php";

// 清空激活按钮文字
$setting_key = 'activation_button_text';
$setting_value = ''; // 空字符串，这样就不会显示任何文字

// 检查设置是否存在
$check_sql = "SELECT id FROM settings WHERE setting_key = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "s", $setting_key);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);
$exists = mysqli_stmt_num_rows($check_stmt) > 0;
mysqli_stmt_close($check_stmt);

if ($exists) {
    // 更新现有设置
    $sql = "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $setting_value, $setting_key);
} else {
    // 插入新设置
    $sql = "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $setting_key, $setting_value);
}

if (mysqli_stmt_execute($stmt)) {
    echo "成功删除激活按钮文字！";
} else {
    echo "修改设置时出错: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
