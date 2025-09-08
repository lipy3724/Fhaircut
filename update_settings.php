<?php
// 包含数据库配置文件
require_once "db_config.php";

// 检查是否已存在contact_email2设置
$sql = "SELECT COUNT(*) FROM settings WHERE setting_key = 'contact_email2'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_row($result);

if ($row[0] == 0) {
    // 不存在，插入新设置
    $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email2', 'support@haircut.network')";
    if (mysqli_query($conn, $sql)) {
        echo "成功添加contact_email2设置<br>";
    } else {
        echo "添加contact_email2设置失败: " . mysqli_error($conn) . "<br>";
    }
}

// 检查是否已存在wechat设置
$sql = "SELECT COUNT(*) FROM settings WHERE setting_key = 'wechat'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_row($result);

if ($row[0] == 0) {
    // 不存在，插入新设置
    $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('wechat', 'haircut_wechat')";
    if (mysqli_query($conn, $sql)) {
        echo "成功添加wechat设置<br>";
    } else {
        echo "添加wechat设置失败: " . mysqli_error($conn) . "<br>";
    }
}

echo "数据库设置更新完成。<a href='admin.php?page=settings'>返回设置页面</a>";

// 关闭数据库连接
mysqli_close($conn);
?>
