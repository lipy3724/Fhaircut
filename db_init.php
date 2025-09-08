<?php
// 加载环境变量
require_once __DIR__ . '/env.php';

// 数据库连接配置
$db_server = env('DB_SERVER', 'localhost');
$db_username = env('DB_USERNAME', 'root');
$db_password = env('DB_PASSWORD', '');
$db_name = env('DB_NAME', 'jianfa_db');

// 尝试连接到MySQL数据库
$conn = mysqli_connect($db_server, $db_username, $db_password);

// 检查连接
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 创建数据库（如果不存在）
$sql = "CREATE DATABASE IF NOT EXISTS " . $db_name;
if (mysqli_query($conn, $sql)) {
    // 选择数据库
    mysqli_select_db($conn, $db_name);
    
    // 创建users表
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        role ENUM('Member', 'Editor', 'Administrator') NOT NULL DEFAULT 'Member',
        status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
        is_activated TINYINT(1) NOT NULL DEFAULT 0,
        activation_payment_id VARCHAR(100) DEFAULT NULL,
        registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!mysqli_query($conn, $sql)) {
        echo "Failed to create users table: " . mysqli_error($conn);
    }
    
    // 检查users表是否有is_activated字段，如果没有则添加
    $check_column_sql = "SHOW COLUMNS FROM users LIKE 'is_activated'";
    $check_column_result = mysqli_query($conn, $check_column_sql);
    if ($check_column_result && mysqli_num_rows($check_column_result) == 0) {
        $add_column_sql = "ALTER TABLE users ADD COLUMN is_activated TINYINT(1) NOT NULL DEFAULT 0 AFTER status";
        if (mysqli_query($conn, $add_column_sql)) {
            error_log("Added is_activated column to users table");
        } else {
            error_log("Error adding is_activated column: " . mysqli_error($conn));
        }
    }
    
    // 检查users表是否有activation_payment_id字段，如果没有则添加
    $check_column_sql = "SHOW COLUMNS FROM users LIKE 'activation_payment_id'";
    $check_column_result = mysqli_query($conn, $check_column_sql);
    if ($check_column_result && mysqli_num_rows($check_column_result) == 0) {
        $add_column_sql = "ALTER TABLE users ADD COLUMN activation_payment_id VARCHAR(100) DEFAULT NULL AFTER is_activated";
        if (mysqli_query($conn, $add_column_sql)) {
            error_log("Added activation_payment_id column to users table");
        } else {
            error_log("Error adding activation_payment_id column: " . mysqli_error($conn));
        }
    }
    
    // 检查users表是否有balance字段，如果没有则添加
    $check_column_sql = "SHOW COLUMNS FROM users LIKE 'balance'";
    $check_column_result = mysqli_query($conn, $check_column_sql);
    if ($check_column_result && mysqli_num_rows($check_column_result) == 0) {
        $add_column_sql = "ALTER TABLE users ADD COLUMN balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER activation_payment_id";
        if (mysqli_query($conn, $add_column_sql)) {
            error_log("Added balance column to users table");
        } else {
            error_log("Error adding balance column: " . mysqli_error($conn));
        }
    }
    
    // 创建products表
    $sql = "CREATE TABLE IF NOT EXISTS products (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        subtitle VARCHAR(200),
        price DECIMAL(10,2) NOT NULL,
        sales INT(11) DEFAULT 0,
        clicks INT(11) DEFAULT 0,
        image VARCHAR(255),
        image2 VARCHAR(255),
        image3 VARCHAR(255),
        image4 VARCHAR(255),
        image5 VARCHAR(255),
        guest BOOLEAN DEFAULT TRUE,
        category_id INT(11),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        show_on_homepage BOOLEAN DEFAULT FALSE,
        images_total_size BIGINT DEFAULT 0,
        images_count INT DEFAULT 0,
        images_formats VARCHAR(255),
        paid_video VARCHAR(255),
        paid_video_size BIGINT DEFAULT 0,
        paid_video_duration INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!mysqli_query($conn, $sql)) {
        echo "Failed to create products table: " . mysqli_error($conn);
    }
    
    // 创建categories表
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!mysqli_query($conn, $sql)) {
        echo "Failed to create categories table: " . mysqli_error($conn);
    }
    
    // 插入默认管理员账户（如果不存在）
    $admin_username = "admin";
    $admin_password = password_hash("password123", PASSWORD_DEFAULT); // 使用安全的密码哈希
    $admin_email = "admin@example.com";
    
    $check_sql = "SELECT * FROM users WHERE username = '$admin_username'";
    $result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($result) == 0) {
        $sql = "INSERT INTO users (username, password, email, role) VALUES ('$admin_username', '$admin_password', '$admin_email', 'Administrator')";
        if (!mysqli_query($conn, $sql)) {
            echo "Failed to insert admin account: " . mysqli_error($conn);
        }
    }
    
    // 插入默认分类（如果不存在）
    $categories = [
        'Cool bobo hair',
        'Shovel long Bob',
        'Shovel short Bob',
        'Super short hair',
        'Halo hairstyle',
        'Broken hair',
        'Curly hair',
        'Bald buzzcut',
        'Other nice hair',
        'Hair sales',
        'Today 42.0% off',
        'Super short hair test'
    ];

    foreach ($categories as $category) {
        $category = mysqli_real_escape_string($conn, $category);
        $check_sql = "SELECT * FROM categories WHERE name = '$category'";
        $result = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($result) == 0) {
            $sql = "INSERT INTO categories (name) VALUES ('$category')";
            if (!mysqli_query($conn, $sql)) {
                echo "Failed to insert category: " . mysqli_error($conn);
            }
        }
    }
    
    // 创建验证码表
    $sql = "CREATE TABLE IF NOT EXISTS verification_codes (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!mysqli_query($conn, $sql)) {
        error_log("Error creating verification_codes table: " . mysqli_error($conn));
    }

    // 创建用户登录记录表
    $sql = "CREATE TABLE IF NOT EXISTS login_logs (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        login_ip VARCHAR(45) NOT NULL,
        login_location VARCHAR(255) DEFAULT NULL,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_agent TEXT,
        INDEX idx_user_id (user_id),
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_login_time (login_time),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!mysqli_query($conn, $sql)) {
        error_log("Error creating login_logs table: " . mysqli_error($conn));
    }

    // 检查purchases表是否存在
    $check_purchases_table = "SHOW TABLES LIKE 'purchases'";
    $purchases_table_exists = mysqli_query($conn, $check_purchases_table);

    if (mysqli_num_rows($purchases_table_exists) == 0) {
        // 创建purchases表
        $create_purchases_table = "CREATE TABLE purchases (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11),
            email VARCHAR(255) NOT NULL,
            email_source ENUM('session', 'paypal') NOT NULL DEFAULT 'session',
            product_id INT(11) NOT NULL,
            order_id VARCHAR(512) NOT NULL UNIQUE,
            transaction_id VARCHAR(512),
            is_photo_pack TINYINT(1) NOT NULL DEFAULT 0,
            amount DECIMAL(10,2) NOT NULL,
            purchase_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            purchase_type ENUM('product', 'activation', 'balance', 'photo_pack') NOT NULL DEFAULT 'product',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )";
        mysqli_query($conn, $create_purchases_table);
    } else {
        // 检查是否有purchase_type字段
        $check_purchase_type = "SHOW COLUMNS FROM purchases LIKE 'purchase_type'";
        $purchase_type_exists = mysqli_query($conn, $check_purchase_type);
        
        if (mysqli_num_rows($purchase_type_exists) == 0) {
            // 添加purchase_type字段
            $add_purchase_type = "ALTER TABLE purchases ADD COLUMN purchase_type ENUM('product', 'activation', 'balance') NOT NULL DEFAULT 'product'";
            mysqli_query($conn, $add_purchase_type);
        } else {
            // 检查purchase_type字段是否包含balance选项
            $check_balance_option = "SHOW COLUMNS FROM purchases WHERE Field = 'purchase_type' AND Type LIKE '%balance%'";
            $balance_option_exists = mysqli_query($conn, $check_balance_option);
            
            if (mysqli_num_rows($balance_option_exists) == 0) {
                // 修改purchase_type字段，添加balance选项
                $modify_purchase_type = "ALTER TABLE purchases MODIFY COLUMN purchase_type ENUM('product', 'activation', 'balance') NOT NULL DEFAULT 'product'";
                mysqli_query($conn, $modify_purchase_type);
            }
        }
        
        // 检查order_id字段的长度
        $check_order_id = "SHOW COLUMNS FROM purchases WHERE Field = 'order_id'";
        $order_id_result = mysqli_query($conn, $check_order_id);
        
        if ($order_id_result && $order_id_info = mysqli_fetch_assoc($order_id_result)) {
            // 检查字段类型是否需要扩展
            if (strpos($order_id_info['Type'], 'varchar(255)') !== false) {
                // 修改order_id字段为更长的字符串
                $modify_order_id = "ALTER TABLE purchases MODIFY COLUMN order_id VARCHAR(512) NOT NULL";
                if (mysqli_query($conn, $modify_order_id)) {
                    error_log("订单ID字段已成功修改为VARCHAR(512)");
                } else {
                    error_log("修改订单ID字段失败: " . mysqli_error($conn));
                }
            }
        }
        
        // 检查transaction_id字段的长度
        $check_transaction_id = "SHOW COLUMNS FROM purchases WHERE Field = 'transaction_id'";
        $transaction_id_result = mysqli_query($conn, $check_transaction_id);
        
        if ($transaction_id_result && $transaction_id_info = mysqli_fetch_assoc($transaction_id_result)) {
            // 检查字段类型是否需要扩展
            if (strpos($transaction_id_info['Type'], 'varchar(255)') !== false) {
                // 修改transaction_id字段为更长的字符串
                $modify_transaction_id = "ALTER TABLE purchases MODIFY COLUMN transaction_id VARCHAR(512)";
                if (mysqli_query($conn, $modify_transaction_id)) {
                    error_log("交易ID字段已成功修改为VARCHAR(512)");
                } else {
                    error_log("修改交易ID字段失败: " . mysqli_error($conn));
                }
            }
        }
        
        // 检查purchase_date字段的默认值
        $check_purchase_date = "SHOW COLUMNS FROM purchases WHERE Field = 'purchase_date'";
        $purchase_date_result = mysqli_query($conn, $check_purchase_date);
        
        if ($purchase_date_result && $purchase_date_info = mysqli_fetch_assoc($purchase_date_result)) {
            // 检查字段是否有默认值
            if (strpos($purchase_date_info['Default'], 'CURRENT_TIMESTAMP') === false) {
                // 修改purchase_date字段添加默认值
                $modify_purchase_date = "ALTER TABLE purchases MODIFY COLUMN purchase_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
                if (mysqli_query($conn, $modify_purchase_date)) {
                    error_log("购买日期字段已成功添加默认值CURRENT_TIMESTAMP");
                } else {
                    error_log("修改购买日期字段失败: " . mysqli_error($conn));
                }
            }
        }
        
        // 检查purchase_type字段是否包含photo_pack选项
        $check_photo_pack_option = "SHOW COLUMNS FROM purchases WHERE Field = 'purchase_type' AND Type LIKE '%photo_pack%'";
        $photo_pack_option_exists = mysqli_query($conn, $check_photo_pack_option);
        
        if (mysqli_num_rows($photo_pack_option_exists) == 0) {
            // 修改purchase_type字段，添加photo_pack选项
            $modify_purchase_type = "ALTER TABLE purchases MODIFY COLUMN purchase_type ENUM('product', 'activation', 'balance', 'photo_pack') NOT NULL DEFAULT 'product'";
            if (mysqli_query($conn, $modify_purchase_type)) {
                error_log("购买类型字段已成功添加photo_pack选项");
            } else {
                error_log("修改购买类型字段失败: " . mysqli_error($conn));
            }
        }
    }
    
    // 关闭初始连接
    mysqli_close($conn);
} else {
    echo "Failed to create database: " . mysqli_error($conn);
    mysqli_close($conn);
}
?>
