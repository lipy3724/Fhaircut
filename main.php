<?php
// 启动会话
session_start();

// 包含数据库配置文件
require_once "db_config.php";
// 包含设置函数文件
require_once "db_settings.php";

// 检查用户是否已登录
$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$username = $isLoggedIn ? $_SESSION["username"] : "";
$userRole = $isLoggedIn ? $_SESSION["role"] : "";
$userBalance = 0;

// 如果用户已登录，检查是否已激活并获取余额
if ($isLoggedIn) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT is_activated, balance FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $is_activated, $userBalance);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        // 如果未激活，重定向到激活页面
        if (!$is_activated) {
            header("location: activate_account.php");
            exit;
        }
    }
}

// 获取当前类别
$currentCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;

// 获取排序方式
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// 验证排序参数
$allowedSorts = ['id', 'price', 'sales', 'clicks'];
$allowedOrders = ['asc', 'desc'];

if (!in_array($sort, $allowedSorts)) {
    $sort = 'id';
}

if (!in_array($order, $allowedOrders)) {
    $order = 'asc';
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

// 分页设置
// 从设置中获取每页显示数量
$productsPerPage = 10; // 默认值
$sql = "SELECT setting_value FROM settings WHERE setting_key = 'items_per_page'";
$result = mysqli_query($conn, $sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $productsPerPage = intval($row['setting_value']);
    if ($productsPerPage < 1) {
        $productsPerPage = 10; // 如果设置值无效，使用默认值
    }
    mysqli_free_result($result);
}

// 获取产品总数
$countSql = "SELECT COUNT(*) as total FROM products p";
if ($currentCategory > 0) {
    $countSql .= " WHERE p.category_id = " . $currentCategory;
}

$result = mysqli_query($conn, $countSql);
$totalProducts = 0;
if ($result && $row = mysqli_fetch_assoc($result)) {
    $totalProducts = $row['total'];
    mysqli_free_result($result);
}

$totalPages = ceil($totalProducts / $productsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$currentPage = min($currentPage, max(1, $totalPages));
$start = ($currentPage - 1) * $productsPerPage;

// 调试信息（可以在解决问题后删除）
// echo "<!-- Debug: currentPage = $currentPage, totalPages = $totalPages, totalProducts = $totalProducts, productsPerPage = $productsPerPage -->";


// 获取当前页的产品
$products = [];
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id";

// 添加类别过滤
if ($currentCategory > 0) {
    $sql .= " WHERE p.category_id = " . $currentCategory;
}

// 添加排序
$sql .= " ORDER BY p." . $sort . " " . strtoupper($order);

// 添加分页限制
$sql .= " LIMIT " . $start . ", " . $productsPerPage;

$result = mysqli_query($conn, $sql);
$pagedProducts = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pagedProducts[] = $row;
    }
    mysqli_free_result($result);
}

// 获取联系信息设置
$contact_email = "info@haircut.network"; // 默认值
$contact_phone = "+1-123-456-7890"; // 默认值

$sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('contact_email', 'contact_phone')";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['setting_key'] === 'contact_email') {
            $contact_email = $row['setting_value'];
        } elseif ($row['setting_key'] === 'contact_phone') {
            $contact_phone = $row['setting_value'];
        }
    }
    mysqli_free_result($result);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>剪发网 - 所有作品</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: #ffe4e8;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        header {
            background-color: #F0EFF8;
            color: #4A4A4A;
            padding: 15px 0;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
        }
        
        .site-title {
            font-size: 24px;
            font-weight: bold;
            padding-left: 20px;
            position: relative;
        }
        
        .site-title a {
            color: #4A4A4A;
            text-decoration: none;
        }
        
        .site-nav {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        
        .site-nav a {
            display: inline-block;
            padding: 6px 14px;
            background: transparent;
            color: #333;
            text-decoration: none;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .site-nav a:hover,
        .site-nav a.active {
            border-color: #999;
            color: #111;
            background: transparent;
        }
        
        .site-nav a:hover {
            background-color: #ff5252;
        }
        
        .membership-notice {
            text-align: center;
            font-size: 24px;
            color: #4A4A4A;
        }
        
        .favorite-shortcut {
            text-align: right;
            padding-right: 20px;
            color: #4A4A4A;
        }
        
        .container {
            display: flex;
            min-height: calc(100vh - 60px);
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
        }
        
        .sort-options {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .sort-options a {
            margin-left: 15px;
            color: #4A4A4A;
            text-decoration: none;
        }
        
        .sort-options a:hover {
            color: #9E9BC7;
        }
        
        .sort-options a.active {
            color: #9E9BC7;
            font-weight: bold;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
        }
        
        .product-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-card.member-only {
            position: relative;
        }
        
        .product-card.member-only::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }
        
        .product-card.member-only::after {
            content: "Members only";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            z-index: 2;
        }
        
        .product-images {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .member-images {
            display: none;
        }
        
        .member-logged-in .member-images {
            display: flex;
            position: absolute;
            bottom: 10px;
            left: 0;
            width: 100%;
            justify-content: center;
            gap: 5px;
            z-index: 3;
        }
        
        .member-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border: 2px solid white;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .member-thumbnail:hover {
            transform: scale(1.1);
        }
        
        .product-info {
            padding: 12px;
            text-align: center;
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
            margin-top: 5px;
        }
        
        .product-card a {
            display: inline-block;
            padding: 8px 15px;
            background-color: #F0EFF8;
            color: #4A4A4A;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .product-card a:hover {
            background-color: #e4e2f5;
        }
        
        .pagination {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
        }
        
        .pagination a {
            background-color: #ffccd5;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background-color: #f7a4b9;
            color: #e75480;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(231, 84, 128, 0.2);
        }
        
        .pagination span {
            background-color: #e75480;
            color: white;
            font-weight: bold;
        }
        
        .page-jump {
            margin-left: 15px;
            display: flex;
            align-items: center;
        }
        
        .page-jump input {
            width: 50px;
            padding: 8px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin: 0 5px;
            text-align: center;
        }
        
        .page-jump button {
            padding: 8px 12px;
            background-color: #F0EFF8;
            color: #4A4A4A;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        footer {
            background-color: #ffccd5;
            color: #333;
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 -2px 10px rgba(231, 84, 128, 0.1);
        }
        
        footer p {
            margin-bottom: 10px;
        }
        
        footer a {
            color: #4A4A4A;
            text-decoration: none;
            margin: 0 10px;
        }
        
        footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .products-container {
            margin-bottom: 30px;
        }
        
        .product-row {
            display: flex;
            margin: 36px 0 20px 0; /* 增加上方空间用于显示标题 */
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
            overflow: visible; /* 允许标题显示在卡片上方 */
            position: relative; /* 标题采用绝对定位放在卡片上方 */
        }
        
        .product-info-column {
            width: 16.66%; /* 修改为1/6，支持6列显示 */
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-right: 1px solid #f0f0f0;
        }

        .product-info-column:last-child {
            border-right: none;
        }
        
        .product-title-container {
            position: absolute;
            top: -30px; /* 移到卡片上方 */
            left: 10px;
            right: 10px;
            background-color: transparent;
            padding: 0;
            border-radius: 0;
            z-index: 10;
        }
        
        .product-title {
            display: inline-block;
            font-weight: bold;
            color: #e75480;
            font-size: 16px;
            text-align: left;
            margin: 0;
            background: #ffccd5;
            padding: 2px 6px;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(231, 84, 128, 0.1);
        }
        
        .product-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            margin-bottom: 10px;
            position: relative;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .member-only-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
            z-index: 2;
            overflow: hidden;
        }
        
        /* 添加模糊背景图片效果 */
        .member-only-overlay::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('uploads/member_blur/member_blur_bg.jpg') center center;
            background-size: cover;
            filter: blur(8px);
            transform: scale(1.1);
            z-index: -1;
        }
        
        .member-only-overlay::after {
            content: "Members Only";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        
        .view-details {
            margin-top: 10px;
            text-align: center;
        }
        
        .view-details a {
            display: inline-block;
            padding: 8px 15px;
            background-color: #e75480;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .view-details a:hover {
            background-color: #d64072;
        }
        
        .products-grid {
            display: none; /* 隐藏原来的网格布局 */
        }

        /* 添加模糊背景图片样式 */
        .member-blur-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .member-blur-bg {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: blur(10px);
            transform: scale(1.1);
        }

        .member-blur-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <?php
    // 包含header.php
    require_once "header.php";
    ?>
    
    <!-- 页面内容开始 -->
    <div class="container">
        <div class="sidebar">
            <h3>Navigation:</h3>
            <ul class="category-list">
                <li><a href="home.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'home.php') ? 'active' : ''; ?>">Homepage</a></li>
                <li><a href="main.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'main.php') ? 'active' : ''; ?>">All works</a></li>
                <li><a href="hair_list.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'hair_list.php') ? 'active' : ''; ?>">Hair List</a></li>
                <li><a href="taday_42_off.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'taday_42_off.php') ? 'active' : ''; ?>">Today 42.0% off</a></li>
            </ul>
            
            <h3>Product Categories:</h3>
            <ul class="category-list">
                <?php foreach ($categories as $category): 
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
            
            <?php if (!$isLoggedIn): ?>
            <div class="search-box">
                <input type="text" placeholder="Keyword" disabled>
                <button onclick="alert('Please login to use search function')">Search</button>
                <div class="help-text" style="color: #666; font-size: 12px; margin-top: 5px;">Please login to use search</div>
            </div>
            <?php else: ?>
            <div class="search-box">
                <form action="search.php" method="get">
                    <input type="text" name="keyword" placeholder="Keyword" required>
                    <input type="hidden" name="category" value="<?php echo $currentCategory; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="main-content">
            <?php if (isset($_SESSION["activation_success"]) && $_SESSION["activation_success"]): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <strong>Success!</strong> Your account has been successfully activated. You now have full access to all features.
                <?php unset($_SESSION["activation_success"]); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET["auto_activated"]) && $_GET["auto_activated"] == 1): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <strong>Welcome!</strong> Your account has been automatically activated 
                <?php 
                $reason = isset($_GET["reason"]) ? $_GET["reason"] : "";
                switch ($reason) {
                    case 'activation_payment':
                        echo "because we found a previous activation payment from your email.";
                        break;
                    case 'total_spent':
                        echo "because your email has spent over $100 on our products.";
                        break;
                    case 'purchase_count':
                        echo "because your email has made 5 or more purchases.";
                        break;
                    default:
                        echo "based on your previous activity.";
                }
                ?>
                You now have full access to all features.
                <?php 
                // 将auto_activated参数存入会话，然后重定向到不带auto_activated参数的页面
                $_SESSION["just_activated"] = true;
                $_SESSION["activation_reason"] = $reason;
                
                // 构建新的URL，保留其他参数但移除auto_activated
                $newUrl = 'main.php';
                $params = [];
                if (isset($_GET['category'])) $params[] = 'category=' . urlencode($_GET['category']);
                if (isset($_GET['sort'])) $params[] = 'sort=' . urlencode($_GET['sort']);
                if (isset($_GET['order'])) $params[] = 'order=' . urlencode($_GET['order']);
                if (isset($_GET['page'])) $params[] = 'page=' . urlencode($_GET['page']);
                
                if (!empty($params)) {
                    $newUrl .= '?' . implode('&', $params);
                }
                
                echo "<script>window.history.replaceState({}, document.title, '" . htmlspecialchars($newUrl, ENT_QUOTES) . "');</script>";
                ?>
            </div>
            <?php elseif (isset($_SESSION["just_activated"]) && $_SESSION["just_activated"]): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <strong>Welcome!</strong> Your account has been automatically activated 
                <?php 
                $reason = isset($_SESSION["activation_reason"]) ? $_SESSION["activation_reason"] : "";
                switch ($reason) {
                    case 'activation_payment':
                        echo "because we found a previous activation payment from your email.";
                        break;
                    case 'total_spent':
                        echo "because your email has spent over $100 on our products.";
                        break;
                    case 'purchase_count':
                        echo "because your email has made 5 or more purchases.";
                        break;
                    default:
                        echo "based on your previous activity.";
                }
                ?>
                You now have full access to all features.
                <?php 
                // 显示一次后清除会话变量
                unset($_SESSION["just_activated"]);
                unset($_SESSION["activation_reason"]);
                ?>
            </div>
            <?php endif; ?>
            
            <div class="sort-options">
                <a href="main.php?category=<?php echo $currentCategory; ?>&sort=price&order=<?php echo $sort == 'price' && $order == 'asc' ? 'desc' : 'asc'; ?>" <?php echo $sort == 'price' ? 'class="active"' : ''; ?>>
                    Price <?php echo $sort == 'price' && $order == 'asc' ? '↑' : '↓'; ?>
                </a>
                <a href="main.php?category=<?php echo $currentCategory; ?>&sort=sales&order=<?php echo $sort == 'sales' && $order == 'asc' ? 'desc' : 'asc'; ?>" <?php echo $sort == 'sales' ? 'class="active"' : ''; ?>>
                    Sales <?php echo $sort == 'sales' && $order == 'asc' ? '↑' : '↓'; ?>
                </a>
                <a href="main.php?category=<?php echo $currentCategory; ?>&sort=clicks&order=<?php echo $sort == 'clicks' && $order == 'asc' ? 'desc' : 'asc'; ?>" <?php echo $sort == 'clicks' ? 'class="active"' : ''; ?>>
                    Click <?php echo $sort == 'clicks' && $order == 'asc' ? '↑' : '↓'; ?>
                </a>
                <a href="main.php?category=<?php echo $currentCategory; ?>">All Year</a>
            </div>
            
            <div class="products-container">
                <?php foreach ($pagedProducts as $product): ?>
                <div class="product-row">
                    <!-- 产品标题，只显示一次在左上角，点击跳转详情 -->
                    <div class="product-title-container">
                        <a class="product-title" href="product_detail.php?id=<?php echo $product['id']; ?>"><?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?></a>
                    </div>
                    
                    <?php if ($isLoggedIn): /* 会员登录后看到会员专属图片 */ ?>
                    
                    <!-- 第一列：会员图片1 -->
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <?php if (!empty($product['member_image1'])): ?>
                            <img src="<?php echo htmlspecialchars($product['member_image1']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image 1" class="product-image">
                            <?php else: ?>
                            <img src="https://via.placeholder.com/300x200?text=No+Image" alt="No Image" class="product-image">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php 
                    // 动态显示会员图片（从第2张开始，最多显示到第6张）
                    for ($i = 2; $i <= 6; $i++): 
                        $memberImageField = 'member_image' . $i;
                        if (!empty($product[$memberImageField])): 
                    ?>
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product[$memberImageField]); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image <?php echo $i; ?>" class="product-image">
                        </div>
                    </div>
                    <?php 
                        endif;
                    endfor; 
                    ?>
                    
                    <?php else: /* 游客看到带水印的图片 */ ?>
                    
                    <!-- 第一列：游客可见图片 -->
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="product-image">
                            <?php else: ?>
                            <img src="https://via.placeholder.com/300x200?text=No+Image" alt="No Image" class="product-image">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 第二列：模糊效果 -->
                    <div class="product-info-column">
                        <div class="product-image-container member-blur-container">
                            <img src="uploads/member_blur/member_blur_bg.jpg" alt="Members Only" class="member-blur-bg">
                            <div class="member-blur-overlay">Members Only</div>
                        </div>
                    </div>
                    
                    <!-- 第三列：模糊效果 -->
                    <div class="product-info-column">
                        <div class="product-image-container member-blur-container">
                            <img src="uploads/member_blur/member_blur_bg.jpg" alt="Members Only" class="member-blur-bg">
                            <div class="member-blur-overlay">Members Only</div>
                        </div>
                    </div>
                    
                    <!-- 第四列：模糊效果 -->
                    <div class="product-info-column">
                        <div class="product-image-container member-blur-container">
                            <img src="uploads/member_blur/member_blur_bg.jpg" alt="Members Only" class="member-blur-bg">
                            <div class="member-blur-overlay">Members Only</div>
                        </div>
                    </div>
                    
                    <!-- 第五列：模糊效果 -->
                    <div class="product-info-column">
                        <div class="product-image-container member-blur-container">
                            <img src="uploads/member_blur/member_blur_bg.jpg" alt="Members Only" class="member-blur-bg">
                            <div class="member-blur-overlay">Members Only</div>
                        </div>
                    </div>
                    
                    <!-- 第六列：模糊效果 -->
                    <div class="product-info-column">
                        <div class="product-image-container member-blur-container">
                            <img src="uploads/member_blur/member_blur_bg.jpg" alt="Members Only" class="member-blur-bg">
                            <div class="member-blur-overlay">Members Only</div>
                        </div>
                    </div>
                    
                    <?php endif; ?>
                    
                    <!-- 已在上面的循环中显示了会员图片1-6，这里不需要额外显示 -->
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php
            // 清理变量以防止干扰分页变量
            unset($product);
            ?>
            
            <!-- 保留原来的产品网格，但设置为不显示 -->
            <div class="products-grid" style="display: none;">
                <?php foreach ($pagedProducts as $index => $product): ?>
                    <?php 
                    // 检查是否是第一列，如果不是且不是游客可见，则添加member-only类
                    $isFirstColumn = $index % 6 == 0;
                    $isMemberOnly = !$isFirstColumn && !$product['guest'] && !$isLoggedIn;
                    ?>
                    <div class="product-card <?php echo $isMemberOnly ? 'member-only' : ''; ?> <?php echo $isLoggedIn ? 'member-logged-in' : ''; ?>">
                        <div class="product-images">
                            <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="product-image">
                            <?php else: ?>
                            <img src="https://via.placeholder.com/300x150?text=No+Image" alt="No Image" class="product-image">
                            <?php endif; ?>
                            
                            <?php if ($isLoggedIn): ?>
                            <div class="member-images">
                                <?php if (!empty($product['image2'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image2']); ?>" alt="Member image 2" class="member-thumbnail" onclick="showFullImage(this.src)">
                                <?php endif; ?>
                                
                                <?php if (!empty($product['image3'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image3']); ?>" alt="Member image 3" class="member-thumbnail" onclick="showFullImage(this.src)">
                                <?php endif; ?>
                                
                                <?php if (!empty($product['image4'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image4']); ?>" alt="Member image 4" class="member-thumbnail" onclick="showFullImage(this.src)">
                                <?php endif; ?>
                                
                                <?php if (!empty($product['image5'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image5']); ?>" alt="Member image 5" class="member-thumbnail" onclick="showFullImage(this.src)">
                                <?php endif; ?>
                                
                                <?php if (!empty($product['image6'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image6']); ?>" alt="Member image 6" class="member-thumbnail" onclick="showFullImage(this.src)">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-title"><?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?></div>
                            <?php if ($isFirstColumn || $product['guest'] || $isLoggedIn): ?>
                            <!-- Removed View Details as requested -->
                            <?php else: ?>
                            <div class="view-details">
                                <a href="login.php">Login Please</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php
            // 清理变量以防止干扰分页变量
            unset($product);
            unset($index);
            unset($isFirstColumn);
            unset($isMemberOnly);
            
            ?>
            
            <?php if ($totalPages > 1): ?>
            <?php 
            // 调试信息
            echo "<!-- DEBUG: Original GET params: " . print_r($_GET, true) . " -->\n";
            echo "<!-- DEBUG: Original currentPage = " . $currentPage . " (type: " . gettype($currentPage) . ") -->\n";
            echo "<!-- DEBUG: totalPages = " . $totalPages . " -->\n";
            echo "<!-- DEBUG: URL: " . $_SERVER['REQUEST_URI'] . " -->\n";
            
            // 重新计算当前页面，确保正确
            $pageCurrent = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $pageCurrent = min($pageCurrent, max(1, $totalPages));
            
            echo "<!-- DEBUG: Recalculated pageCurrent = " . $pageCurrent . " -->\n";
            echo "<!-- DEBUG: pageCurrent > 1 = " . ($pageCurrent > 1 ? 'true' : 'false') . " -->\n";
            ?>
            <div class="pagination">
                <?php if ($pageCurrent > 1): ?>
                <a href="main.php?category=<?php echo htmlspecialchars($currentCategory); ?>&sort=<?php echo htmlspecialchars($sort); ?>&order=<?php echo htmlspecialchars($order); ?>&page=<?php echo $pageCurrent - 1; ?>">Previous</a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $pageCurrent - 2);
                $endPage = min($totalPages, $pageCurrent + 2);
                
                if ($startPage > 1) {
                    echo '<a href="main.php?category=' . htmlspecialchars($currentCategory) . '&sort=' . htmlspecialchars($sort) . '&order=' . htmlspecialchars($order) . '&page=1">1</a>';
                    if ($startPage > 2) {
                        echo '<span>...</span>';
                    }
                }
                
                for ($i = $startPage; $i <= $endPage; $i++) {
                    if ($i == $pageCurrent) {
                        echo '<span>' . $i . '</span>';
                    } else {
                        echo '<a href="main.php?category=' . htmlspecialchars($currentCategory) . '&sort=' . htmlspecialchars($sort) . '&order=' . htmlspecialchars($order) . '&page=' . $i . '">' . $i . '</a>';
                    }
                }
                
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span>...</span>';
                    }
                    echo '<a href="main.php?category=' . htmlspecialchars($currentCategory) . '&sort=' . htmlspecialchars($sort) . '&order=' . htmlspecialchars($order) . '&page=' . $totalPages . '">' . $totalPages . '</a>';
                }
                ?>
                
                <?php if ($pageCurrent < $totalPages): ?>
                <a href="main.php?category=<?php echo htmlspecialchars($currentCategory); ?>&sort=<?php echo htmlspecialchars($sort); ?>&order=<?php echo htmlspecialchars($order); ?>&page=<?php echo $pageCurrent + 1; ?>">Next</a>
                <?php endif; ?>
                
                <div class="page-jump">
                    <span>Go to</span>
                    <input type="number" min="1" max="<?php echo $totalPages; ?>" value="<?php echo $pageCurrent; ?>" id="page-input">
                    <button onclick="jumpToPage()">Go</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer>
        <?php
        // 获取联系信息设置
        try {
            $contact_email = get_setting($conn, 'contact_email', 'info@haircut.network');
            $contact_email2 = get_setting($conn, 'contact_email2', 'support@haircut.network');
            $wechat = get_setting($conn, 'wechat', 'haircut_wechat');
        } catch (Exception $e) {
            $contact_email = 'info@haircut.network';
            $contact_email2 = 'support@haircut.network';
            $wechat = 'haircut_wechat';
        }
        ?>
        <p>Email: <?php echo htmlspecialchars($contact_email); ?> / <?php echo htmlspecialchars($contact_email2); ?></p>
        <p>WeChat: <?php echo htmlspecialchars($wechat); ?></p>
    </footer>
    
    <script>
        function jumpToPage() {
            const pageInput = document.getElementById('page-input');
            const page = parseInt(pageInput.value);
            if (page >= 1 && page <= <?php echo $totalPages; ?>) {
                window.location.href = 'main.php?category=<?php echo htmlspecialchars($currentCategory); ?>&sort=<?php echo htmlspecialchars($sort); ?>&order=<?php echo htmlspecialchars($order); ?>&page=' + page;
            }
        }
        
        // 添加图片查看功能
        function showFullImage(src) {
            // 创建模态框
            const modal = document.createElement('div');
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.8)';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.zIndex = '1000';
            
            // 创建图片元素
            const img = document.createElement('img');
            img.src = src;
            img.style.maxWidth = '80%';
            img.style.maxHeight = '80%';
            img.style.objectFit = 'contain';
            
            // 点击关闭模态框
            modal.onclick = function() {
                document.body.removeChild(modal);
            };
            
            modal.appendChild(img);
            document.body.appendChild(modal);
        }
    </script>
</body>
</html> 