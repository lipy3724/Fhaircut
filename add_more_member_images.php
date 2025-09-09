<?php
// 包含数据库配置文件
require_once "db_config.php";

// 检查products表中是否存在会员专属图片字段
$fields_to_add = [
    'member_image7' => 'VARCHAR(255) NULL',
    'member_image8' => 'VARCHAR(255) NULL',
    'member_image9' => 'VARCHAR(255) NULL',
    'member_image10' => 'VARCHAR(255) NULL',
    'member_image11' => 'VARCHAR(255) NULL',
    'member_image12' => 'VARCHAR(255) NULL',
    'member_image13' => 'VARCHAR(255) NULL',
    'member_image14' => 'VARCHAR(255) NULL',
    'member_image15' => 'VARCHAR(255) NULL',
    'member_image16' => 'VARCHAR(255) NULL',
    'member_image17' => 'VARCHAR(255) NULL',
    'member_image18' => 'VARCHAR(255) NULL',
    'member_image19' => 'VARCHAR(255) NULL',
    'member_image20' => 'VARCHAR(255) NULL',
];

$added_fields = [];
$errors = [];

foreach ($fields_to_add as $field => $definition) {
    // 检查字段是否存在
    $check_field_sql = "SHOW COLUMNS FROM products LIKE '$field'";
    $check_field_result = mysqli_query($conn, $check_field_sql);
    
    if (mysqli_num_rows($check_field_result) == 0) {
        // 字段不存在，添加它
        $add_field_sql = "ALTER TABLE products ADD COLUMN $field $definition";
        if (mysqli_query($conn, $add_field_sql)) {
            $added_fields[] = $field;
        } else {
            $errors[] = "Failed to add field '$field': " . mysqli_error($conn);
        }
    } else {
        echo "Field '$field' already exists.<br>";
    }
}

if (!empty($added_fields)) {
    echo "Successfully added the following fields to products table: " . implode(", ", $added_fields) . "<br>";
}

if (!empty($errors)) {
    echo "Errors occurred:<br>";
    foreach ($errors as $error) {
        echo "- $error<br>";
    }
}

echo "Done.";
?>
