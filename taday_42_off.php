<?php
// Today 42.0% off page: independent listing using a dedicated category
$view = 'promo';
$pageTitle = 'HairCut Network - Today 42.0% Off';

// Ensure the special category exists and route main listing to it
require_once __DIR__ . '/db_config.php';

$categoryName = 'Today 42.0% off';
$promoCategoryId = 0;

// Look up category; create if missing
$find_sql = "SELECT id FROM categories WHERE name = ? OR name = 'Taday 42.0% off' LIMIT 1";
if ($stmt = mysqli_prepare($conn, $find_sql)) {
    mysqli_stmt_bind_param($stmt, 's', $categoryName);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $promoCategoryId = intval($row['id']);
        
        // 如果找到的是旧分类名称，则更新为新分类名称
        if ($row['name'] === 'Taday 42.0% off') {
            $update_sql = "UPDATE categories SET name = ? WHERE id = ?";
            if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, 'si', $categoryName, $promoCategoryId);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
        }
    }
    mysqli_stmt_close($stmt);
}

if ($promoCategoryId === 0) {
    $insert_sql = "INSERT INTO categories (name) VALUES (?)";
    if ($stmt2 = mysqli_prepare($conn, $insert_sql)) {
        mysqli_stmt_bind_param($stmt2, 's', $categoryName);
        if (mysqli_stmt_execute($stmt2)) {
            $promoCategoryId = mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt2);
    }
}

// Force main.php to display only this category
$_GET['category'] = $promoCategoryId;

require_once __DIR__ . '/main.php'; 