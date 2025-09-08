<?php
// 包含数据库配置文件
require_once "db_config.php";

echo "<h2>数据库初始化</h2>";
echo "<p>正在初始化数据库和表...</p>";

// 创建用户表
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('User', 'Administrator', 'Editor') NOT NULL DEFAULT 'User',
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "用户表创建成功<br>";
} else {
    echo "创建用户表时出错: " . mysqli_error($conn) . "<br>";
}

// 创建类别表
$sql = "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "类别表创建成功<br>";
} else {
    echo "创建类别表时出错: " . mysqli_error($conn) . "<br>";
}

// 创建产品表
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    sales INT DEFAULT 0,
    clicks INT DEFAULT 0,
    category_id INT,
    image VARCHAR(255),
    image2 VARCHAR(255),
    image3 VARCHAR(255),
    image4 VARCHAR(255),
    image5 VARCHAR(255),
    image6 VARCHAR(255),
    video VARCHAR(255),
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    guest BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
)";

if (mysqli_query($conn, $sql)) {
    echo "产品表创建成功<br>";
} else {
    echo "创建产品表时出错: " . mysqli_error($conn) . "<br>";
}

// 创建设置表
$sql = "CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "设置表创建成功<br>";
    
    // 插入默认设置
    $default_settings = [
        ['contact_email', 'info@haircut.network'],
        ['contact_phone', '+1-123-456-7890'],
        ['site_title', 'HairCut Network'],
        ['site_description', 'Professional hair cutting tutorials and resources'],
        ['items_per_page', '10'],
        ['facebook_url', 'https://facebook.com/haircutting'],
        ['twitter_url', 'https://twitter.com/haircutting'],
        ['instagram_url', 'https://instagram.com/haircutting']
    ];
    
    foreach ($default_settings as $setting) {
        $key = $setting[0];
        $value = $setting[1];
        
        // 检查设置是否已存在
        $check_sql = "SELECT id FROM settings WHERE setting_key = '$key'";
        $result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($result) == 0) {
            // 设置不存在，插入
            $insert_sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')";
            mysqli_query($conn, $insert_sql);
            echo "已添加默认设置: $key<br>";
        }
    }
} else {
    echo "创建设置表时出错: " . mysqli_error($conn) . "<br>";
}

// 创建验证码表
$sql = "CREATE TABLE IF NOT EXISTS verification_codes (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql)) {
    echo "验证码表创建成功";
} else {
    echo "创建验证码表失败: " . mysqli_error($conn);
}

// 关闭数据库连接
mysqli_close($conn);

echo "<br>数据库设置完成!";
?> 