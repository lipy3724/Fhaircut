<?php
// 包含数据库配置文件
require_once "db_config.php";

// 检查settings表是否存在
$check_settings_table = "SHOW TABLES LIKE 'settings'";
$settings_table_exists = mysqli_query($conn, $check_settings_table);

if (mysqli_num_rows($settings_table_exists) == 0) {
    // 创建settings表
    $create_settings_table = "CREATE TABLE settings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_settings_table)) {
        // 插入默认设置
        $default_settings = [
            ['contact_email', 'info@haircut.network'],
            ['contact_email2', 'support@haircut.network'],
            ['wechat', 'haircut_wechat']
        ];
        
        foreach ($default_settings as $setting) {
            $key = $setting[0];
            $value = $setting[1];
            
            $insert_sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
            if ($stmt = mysqli_prepare($conn, $insert_sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $key, $value);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        error_log("Error creating settings table: " . mysqli_error($conn));
    }
}

// 获取所有设置
function get_all_settings($conn) {
    $settings = [];
    $sql = "SELECT setting_key, setting_value FROM settings";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        mysqli_free_result($result);
    }
    
    return $settings;
}

// 获取单个设置
function get_setting($conn, $key, $default = '') {
    $sql = "SELECT setting_value FROM settings WHERE setting_key = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $key);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $value);
        
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return $value;
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return $default;
}

// 更新设置
function update_setting($conn, $key, $value) {
    // 检查设置是否存在
    $check_sql = "SELECT id FROM settings WHERE setting_key = ?";
    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "s", $key);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            // 更新现有设置
            mysqli_stmt_close($check_stmt);
            $update_sql = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
            if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "ss", $value, $key);
                $result = mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                return $result;
            }
        } else {
            // 插入新设置
            mysqli_stmt_close($check_stmt);
            $insert_sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
            if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                mysqli_stmt_bind_param($insert_stmt, "ss", $key, $value);
                $result = mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);
                return $result;
            }
        }
    }
    
    return false;
}
?>
