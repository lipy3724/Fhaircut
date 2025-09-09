<?php
// 设置字符编码
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// 显示错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 启动会话
session_start();

// 包含数据库配置文件
require_once "db_config.php";

// 包含设置文件
require_once "db_settings.php";

// 检查products表是否有show_on_homepage字段，如果没有则添加
$check_column_sql = "SHOW COLUMNS FROM products LIKE 'show_on_homepage'";
$check_column_result = mysqli_query($conn, $check_column_sql);
if ($check_column_result && mysqli_num_rows($check_column_result) == 0) {
    $add_column_sql = "ALTER TABLE products ADD COLUMN show_on_homepage BOOLEAN DEFAULT FALSE";
    mysqli_query($conn, $add_column_sql);
}
if ($check_column_result) {
    mysqli_free_result($check_column_result);
}

// 检查用户是否已登录
$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$username = $isLoggedIn ? $_SESSION["username"] : "";
$userRole = $isLoggedIn ? $_SESSION["role"] : "";
$userBalance = 0;
$is_activated = true; // 默认值

// 检查用户是否已激活
if ($isLoggedIn) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT is_activated, balance FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $is_activated, $userBalance);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        // 如果未激活，则不显示为已登录状态
        if (!$is_activated) {
            $isLoggedIn = false;
            // 保留会话信息，但不显示为已登录
        }
    }
}

// 获取所有类别
$categories = [];
$sql = "SELECT id, name FROM categories ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// 获取Hair sales类别的ID
$hairSalesCategoryId = null;
$sql = "SELECT id FROM categories WHERE name = 'Hair sales'";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $hairSalesCategoryId = $row['id'];
    mysqli_free_result($result);
}

// 获取精选产品（标记为首页展示且不是Hair sales类型的产品，最多4个）
$featuredProducts = [];
$sql = "SELECT p.id, p.title, p.image, p.member_image1, p.member_image2, p.member_image3, p.member_image4, p.member_image5, p.member_image6 FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.show_on_homepage = 1";

// 如果找到了Hair sales类别，则排除它
if ($hairSalesCategoryId !== null) {
    $sql .= " AND p.category_id != " . intval($hairSalesCategoryId);
}

$sql .= " LIMIT 4";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $featuredProducts[] = $row;
    }
    mysqli_free_result($result);
} else {
    // 如果查询失败，记录错误并设置空数组
    error_log("Error loading featured products: " . mysqli_error($conn));
    $featuredProducts = [];
}

// 获取Hair sales类型的产品（最多4个）
$hairSalesProducts = [];
if ($hairSalesCategoryId !== null) {
    $sql = "SELECT p.id, p.title, p.image, p.member_image1, p.member_image2, p.member_image3, p.member_image4, p.member_image5, p.member_image6 FROM products p 
            WHERE p.category_id = " . intval($hairSalesCategoryId) . " AND p.show_on_homepage = 1 
            ORDER BY p.id DESC LIMIT 4";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $hairSalesProducts[] = $row;
        }
        mysqli_free_result($result);
    } else {
        // 如果查询失败，记录错误并设置空数组
        error_log("Error loading hair sales products: " . mysqli_error($conn));
        $hairSalesProducts = [];
    }
} else {
    // 如果没有找到Hair sales类别，设置空数组
    $hairSalesProducts = [];
}

// 调试信息
error_log("Featured products count: " . count($featuredProducts));
error_log("Hair sales products count: " . count($hairSalesProducts));

// 设置当前页面变量
$currentPage = 'home.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fhaircut.com - 首页</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: #F8F7FF;
        }
        
        .container {
            display: flex;
            min-height: calc(100vh - 60px);
            position: relative;
        }
        
        .sidebar {
            width: 200px;
            background-color: #fff5f7;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(231, 84, 128, 0.1);
        }
        
        .sidebar h3 {
            margin-bottom: 15px;
            margin-top: 25px;
            color: #e75480;
            font-size: 18px;
            border-bottom: 2px solid #ffb6c1;
            padding-bottom: 8px;
        }
        
        .sidebar h3:first-child {
            margin-top: 0;
        }
        
        .category-list {
            list-style: none;
            margin-bottom: 25px;
        }
        
        .category-list li {
            margin-bottom: 10px;
        }
        
        .category-list li a {
            color: #333;
            text-decoration: none;
            display: block;
            padding: 8px 10px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .category-list li a:hover, .category-list li a.active {
            background-color: #ffe1e6;
            color: #e75480;
        }
        
        .search-box {
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 80%;
        }
        
        .search-box input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #f7a4b9;
            border-radius: 4px;
            transition: all 0.3s;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #e75480;
            box-shadow: 0 0 0 2px rgba(231, 84, 128, 0.2);
        }
        
        .search-box button {
            padding: 6px 10px;
            background-color: #e75480;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 100%;
            font-size: 14px;
        }
        
        .search-box button:hover {
            background-color: #d64072;
        }
        
        .login-form {
            margin-bottom: 20px;
        }
        
        .login-form div {
            margin-bottom: 10px;
            color: #4A4A4A;
        }
        
        .login-form input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .login-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .login-buttons button {
            padding: 8px 15px;
            background-color: #F0EFF8;
            color: #4A4A4A;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .login-buttons a {
            padding: 8px 15px;
            background-color: #F0EFF8;
            color: #4A4A4A;
            text-decoration: none;
            border-radius: 4px;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .login-buttons button:hover, .login-buttons a:hover {
            background-color: #e4e2f5;
        }
        
        .forgot-password {
            text-align: center;
        }
        
        .forgot-password a {
            color: #9E9BC7;
            text-decoration: none;
            font-size: 14px;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .user-info {
            color: #4A4A4A;
        }
        
        .user-info p {
            margin-bottom: 10px;
        }
        
        .user-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }
        
        .user-actions a {
            padding: 8px 15px;
            background-color: #F0EFF8;
            color: #4A4A4A;
            text-decoration: none;
            border-radius: 4px;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .user-actions a:hover {
            background-color: #e4e2f5;
        }
        
        .admin-link {
            background-color: #e4e2f5 !important;
            color: #4A4A4A !important;
        }
        
        .logout-btn {
            background-color: #F0EFF8 !important;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            width: 100%;
            max-width: none;
        }
        
        /* 首页特定样式 */
        .homepage-banner {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 30px;
            background-color: #fff;
            padding: 20px;
            width: 100%;
        }
        
        .banner-image {
            text-align: center;
            max-width: 100%;
        }
        
        .banner-image img {
            max-width: 100%;
            height: auto;
            border-radius: 3px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .banner-title {
            text-align: center;
            font-size: 28px;
            color: #ff6b6b;
            margin-bottom: 20px;
        }
        
        .featured-products {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            width: 100%;
            justify-items: center;
            justify-content: space-between;
        }
        
        /* 当商品数量不足4个时的样式调整 */
        .featured-products.items-1 {
            grid-template-columns: 1fr;
            max-width: 25%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .featured-products.items-2 {
            grid-template-columns: repeat(2, 1fr);
            max-width: 50%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .featured-products.items-3 {
            grid-template-columns: repeat(3, 1fr);
            max-width: 75%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .product-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
            transition: transform 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: none;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(231, 84, 128, 0.2);
        }
        
        .product-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            position: relative;
            background-color: #f9f9f9;
            padding: 0;
            border-bottom: 1px solid #eee;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .product-info {
            padding: 15px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .product-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #4A4A4A;
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .view-details {
            margin-top: 10px;
        }
        
        .view-details a {
            display: inline-block;
            padding: 8px 15px;
            background-color: #F0EFF8;
            color: #4A4A4A;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .view-details a:hover {
            background-color: #e4e2f5;
        }
        
        .section-title {
            font-size: 24px;
            color: #e75480;
            margin-bottom: 20px;
            text-align: center;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background-color: #e75480;
            margin: 10px auto;
        }
        
        .new-hair-section {
            margin-top: 40px;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
        }
        
        .new-hair-title {
            text-align: center;
            font-size: 32px;
            color: #e75480;
            margin-bottom: 10px;
        }
        
        .new-hair-subtitle {
            text-align: center;
            font-size: 24px;
            color: #e75480;
            margin-bottom: 20px;
        }
        
        .hair-styles {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
            width: 100%;
            justify-items: center;
            justify-content: space-between;
        }
        
        /* 当Hair styles数量不足4个时的样式调整 */
        .hair-styles.items-1 {
            grid-template-columns: 1fr;
            max-width: 25%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hair-styles.items-2 {
            grid-template-columns: repeat(2, 1fr);
            max-width: 50%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hair-styles.items-3 {
            grid-template-columns: repeat(3, 1fr);
            max-width: 75%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hair-style-card {
            width: 100%;
            text-align: center;
            padding: 0;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
            transition: transform 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            max-width: none;
        }
        
        .hair-style-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(231, 84, 128, 0.2);
        }
        
        .hair-style-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0;
            margin-bottom: 0;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .hair-style-title {
            font-size: 16px;
            color: #4A4A4A;
            margin-bottom: 5px;
        }
        
        footer {
            background-color: #ffccd5;
            color: #333;
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 -2px 10px rgba(231, 84, 128, 0.1);
            width: 100%;
            position: relative;
            bottom: 0;
            left: 0;
        }
        
        footer p {
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 500;
        }
        
        footer a {
            color: #4A4A4A;
            text-decoration: none;
            margin: 0 10px;
        }
        
        footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 1200px) {
            .featured-products:not(.items-1):not(.items-2):not(.items-3),
            .hair-styles:not(.items-1):not(.items-2):not(.items-3) {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
        
        @media (max-width: 992px) {
            .featured-products:not(.items-1):not(.items-2),
            .hair-styles:not(.items-1):not(.items-2) {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .featured-products.items-3,
            .hair-styles.items-3 {
                grid-template-columns: repeat(3, 1fr) !important;
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }
            
            .featured-products:not(.items-1),
            .hair-styles:not(.items-1) {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px;
                max-width: 100%;
            }
            
            .product-image-container {
                height: 180px;
            }
        }
        
        @media (max-width: 576px) {
            .featured-products,
            .hair-styles {
                grid-template-columns: repeat(2, 1fr) !important;
                max-width: 100%;
                gap: 10px;
            }
            
            .product-card,
            .hair-style-card {
                max-width: 100%;
            }
            
            .product-image-container {
                height: 160px;
            }
        }
        
        @media (max-width: 480px) {
            .featured-products,
            .hair-styles {
                grid-template-columns: 1fr !important;
                max-width: 100%;
            }
            
            .product-image-container {
                height: 220px;
            }
        }
    </style>
</head>
<body>
    <?php
    // 包含header.php
    require_once "header.php";
    ?>
    
    <div class="container">
        <div class="sidebar">
            <h3>Navigation:</h3>
            <ul class="category-list">
                <li><a href="home.php" class="<?php echo ($currentPage === 'home.php') ? 'active' : ''; ?>">Homepage</a></li>
                <li><a href="main.php" class="<?php echo ($currentPage === 'main.php') ? 'active' : ''; ?>">All works</a></li>
                <li><a href="hair_list.php" class="<?php echo ($currentPage === 'hair_list.php') ? 'active' : ''; ?>">Hair List</a></li>
                <li><a href="taday_42_off.php" class="<?php echo ($currentPage === 'taday_42_off.php') ? 'active' : ''; ?>">Today 42.0% off</a></li>
            </ul>
            
            <h3>Product Categories:</h3>
            <ul class="category-list">
                <?php 
                // 获取当前类别参数
                $currentCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;
                
                foreach ($categories as $category): 
                    // 跳过"Taday 42.0% off"分类，因为已经有"Today 42.0% off"
                    if ($category['name'] === 'Taday 42.0% off') continue;
                ?>
                <li>
                    <a href="main.php?category=<?php echo $category['id']; ?>" 
                       class="<?php echo ($currentCategory == $category['id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="search-box">
                <?php if ($isLoggedIn): ?>
                <form action="search.php" method="get">
                    <input type="text" name="keyword" placeholder="Keyword" required>
                    <button type="submit">Search</button>
                </form>
                <?php else: ?>
                <input type="text" placeholder="Keyword" disabled>
                <button onclick="alert('Please login to use search function')">Search</button>
                <div class="help-text" style="color: #666; font-size: 12px; margin-top: 5px;">Please login to use search</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="main-content">
            <!-- 首页横幅 -->
            <div class="homepage-banner">
                <?php
                // 获取banner图片设置
                $banner_image = '';
                $sql = "SELECT setting_value FROM settings WHERE setting_key = 'banner_image'";
                $result = mysqli_query($conn, $sql);
                if ($result && mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    $banner_image = $row['setting_value'];
                }
                
                if (!empty($banner_image) && file_exists("./" . $banner_image)) {
                    // 显示上传的banner图片，固定高度为150px
                    echo '<div class="banner-image"><img src="' . htmlspecialchars($banner_image) . '" alt="网站横幅" style="max-height: 150px; max-width: 100%; object-fit: contain;"></div>';
                } else {
                    // 显示默认文字标题
                    echo '<div class="banner-title">
                        mkv Subtitles【Google Translate】:Engliash ภาษาไทย<br>
                        简体中文 繁体中文 Das ist Deutsch 한국인 French 日語
                    </div>';
                }
                ?>
                
                <!-- 精选产品展示 - 非Hair sales类型 -->
                <div class="section-title"></div>
                <?php 
                // 根据产品数量添加对应的CSS类
                $productCount = count($featuredProducts);
                $productCountClass = !empty($featuredProducts) ? "items-$productCount" : "";
                ?>
                <div class="featured-products <?php echo $productCountClass; ?>">
                    <?php if (!empty($featuredProducts)): ?>
                    <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card">
                        <div class="product-image-container">
                            <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                            <?php if ($isLoggedIn && !empty($product['member_image1'])): ?>
                                <!-- 会员看到的会员专属图片 -->
                                <img src="<?php echo htmlspecialchars($product['member_image1']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="product-image">
                            <?php elseif (!empty($product['image'])): ?>
                                <!-- 游客看到的普通图片 -->
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="product-image">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x200?text=No+Image" alt="No Image" class="product-image">
                            <?php endif; ?>
                            </a>
                        </div>
                        <div class="product-info">
                            <div class="product-title">
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" style="color: #4A4A4A; text-decoration: none;">
                                    <?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="text-align: center; width: 100%; padding: 20px;">
                        <p>没有产品被设置为在此区域显示。请在后台勾选非Hair sales类型产品的"展示到首页"选项。</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 新发型展示区域 - 只展示Hair sales类型 -->
            <div class="new-hair-section">
                <div class="section-title">
                <div class="new-hair-title">New hair</div>
                <div class="new-hair-subtitle">新しい髪</div>
                </div>
                
                <?php 
                // 根据Hair styles数量添加对应的CSS类
                $hairProductCount = count($hairSalesProducts);
                $hairProductCountClass = !empty($hairSalesProducts) ? "items-$hairProductCount" : "";
                ?>
                <div class="hair-styles <?php echo $hairProductCountClass; ?>">
                    <?php if (!empty($hairSalesProducts)): ?>
                    <?php foreach ($hairSalesProducts as $index => $product): ?>
                    <div class="hair-style-card">
                        <div class="product-image-container" style="position: relative;">
                        <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                        <?php if ($isLoggedIn && !empty($product['member_image1'])): ?>
                            <!-- 会员看到的会员专属图片 -->
                            <img src="<?php echo htmlspecialchars($product['member_image1']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="hair-style-image">
                        <?php elseif (!empty($product['image'])): ?>
                            <!-- 游客看到的普通图片 -->
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="hair-style-image">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/300x200?text=No+Image" alt="No Image" class="hair-style-image">
                        <?php endif; ?>
                        </a>
                        </div>
                        <div class="product-info">
                            <div class="product-title">
                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" style="color: #4A4A4A; text-decoration: none;">
                                <?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?>
                            </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="text-align: center; width: 100%; padding: 20px; grid-column: span 3;">
                        <p>没有产品被设置为在此区域显示。请在后台勾选Hair sales类型产品的"展示到首页"选项。</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <footer>
        <?php
        // 获取联系信息设置
        $contact_email = get_setting($conn, 'contact_email', 'info@haircut.network');
        $contact_email2 = get_setting($conn, 'contact_email2', 'support@haircut.network');
        $wechat = get_setting($conn, 'wechat', 'haircut_wechat');
        ?>
        <p>Email: <?php echo htmlspecialchars($contact_email); ?> / <?php echo htmlspecialchars($contact_email2); ?></p>
        <p>WeChat: <?php echo htmlspecialchars($wechat); ?></p>
    </footer>
    
    <script>
        // 页面加载完成后的调试信息
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking layouts...');
            
            // 调试信息
            const featuredProducts = document.querySelector('.featured-products');
            const hairStyles = document.querySelector('.hair-styles');
            const featuredCards = featuredProducts.querySelectorAll('.product-card');
            const hairCards = hairStyles.querySelectorAll('.hair-style-card');
            
            console.log('Featured products count:', featuredCards.length);
            console.log('Hair styles count:', hairCards.length);
            console.log('Featured products grid template:', getComputedStyle(featuredProducts).gridTemplateColumns);
            console.log('Hair styles grid template:', getComputedStyle(hairStyles).gridTemplateColumns);
        });
    </script>
</body>
</html> 