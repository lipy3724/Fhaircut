<?php
// 启动会话
session_start();

// 包含数据库配置文件
require_once "db_config.php";

// 包含设置文件
require_once "db_settings.php";

// 检查用户是否已登录
$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$username = $isLoggedIn ? $_SESSION["username"] : "";
$userRole = $isLoggedIn ? $_SESSION["role"] : "";
$userBalance = 0;

// 如果用户未登录，重定向到登录页面
if (!$isLoggedIn) {
    header("location: login.php");
    exit;
}

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

// 获取搜索关键词
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$searchResults = [];
$searchMessage = '';
// 获取当前类别ID
$currentCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;

// 获取所有类别（用于侧边栏）
$categories = [];
$sql = "SELECT id, name FROM categories ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// 执行搜索
if (!empty($keyword)) {
    // 构建SQL查询
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ";
    
    // 如果指定了类别，则添加类别过滤条件
    if ($currentCategory > 0) {
        $sql .= "p.category_id = ? AND (p.id = ? OR p.title LIKE ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // 准备搜索参数
            $keywordLike = "%" . $keyword . "%";
            $keywordId = is_numeric($keyword) ? intval($keyword) : 0;
            
            mysqli_stmt_bind_param($stmt, "iis", $currentCategory, $keywordId, $keywordLike);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            // 获取搜索结果
            while ($row = mysqli_fetch_assoc($result)) {
                $searchResults[] = $row;
            }
            
            mysqli_stmt_close($stmt);
        }
    } else {
        // 没有指定类别，搜索所有产品
        $sql .= "p.id = ? OR p.title LIKE ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // 准备搜索参数
            $keywordLike = "%" . $keyword . "%";
            $keywordId = is_numeric($keyword) ? intval($keyword) : 0;
            
            mysqli_stmt_bind_param($stmt, "is", $keywordId, $keywordLike);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            // 获取搜索结果
            while ($row = mysqli_fetch_assoc($result)) {
                $searchResults[] = $row;
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // 设置搜索结果消息
    if (count($searchResults) > 0) {
        $categoryName = "";
        if ($currentCategory > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $currentCategory) {
                    $categoryName = $cat['name'];
                    break;
                }
            }
            $searchMessage = 'Search for "' . htmlspecialchars($keyword) . '" in category "' . htmlspecialchars($categoryName) . '" found ' . count($searchResults) . ' results';
        } else {
            $searchMessage = 'Search for "' . htmlspecialchars($keyword) . '" found ' . count($searchResults) . ' results';
        }
    } else {
        if ($currentCategory > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $currentCategory) {
                    $categoryName = $cat['name'];
                    break;
                }
            }
            $searchMessage = 'No results found for "' . htmlspecialchars($keyword) . '" in category "' . htmlspecialchars($categoryName) . '"';
        } else {
            $searchMessage = 'No results found for "' . htmlspecialchars($keyword) . '"';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索结果 - <?php echo htmlspecialchars($keyword); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
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
        
        body {
            background-color: #F8F7FF;
        }
        
        header {
            background-color: #F0EFF8;
            color: #4A4A4A;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .site-title {
            font-size: 20px;
            font-weight: bold;
        }
        
        .site-nav {
            display: flex;
            gap: 20px;
        }
        
        .site-nav a {
            color: #4A4A4A;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .site-nav a:hover, .site-nav a.active {
            background-color: #e4e2f5;
        }
        
        .membership-notice {
            text-align: center;
            font-size: 18px;
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
        
        .search-header {
            margin-bottom: 20px;
        }
        
        .search-header h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .search-message {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .products-container {
            margin-bottom: 30px;
        }
        
        .product-row {
            display: flex;
            margin: 36px 0 20px 0;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: visible;
            position: relative;
        }
        
        .product-info-column {
            width: 16.66%;
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
            top: -30px;
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
            color: #4A4A4A;
            font-size: 16px;
            text-align: left;
            margin: 0;
            background: #ffffff;
            padding: 2px 6px;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
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
        
        /* 添加模糊背景图片样式 */
        .member-blur-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 6px;
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
        
        .view-details {
            margin-top: 10px;
            text-align: center;
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
        
        .no-results {
            padding: 40px;
            text-align: center;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .no-results h3 {
            color: #4A4A4A;
            margin-bottom: 15px;
        }
        
        .no-results p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .no-results a {
            display: inline-block;
            padding: 8px 15px;
            background-color: #F0EFF8;
            color: #4A4A4A;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .no-results a:hover {
            background-color: #e4e2f5;
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
                <li><a href="home.php">Homepage</a></li>
                <li><a href="main.php">All works</a></li>
                <li><a href="hair_list.php">Hair List</a></li>
                <li><a href="taday_42_off.php">Today 42.0% off</a></li>
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
                    <a href="search.php?keyword=<?php echo urlencode($keyword); ?>&category=<?php echo $category['id']; ?>" <?php echo $currentCategory == $category['id'] ? 'class="active"' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="search-box">
                <form action="search.php" method="get">
                    <input type="text" name="keyword" placeholder="Keyword" value="<?php echo htmlspecialchars($keyword); ?>" required>
                    <input type="hidden" name="category" value="<?php echo $currentCategory; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            
            <div class="user-info">
                <p>Welcome, <?php echo htmlspecialchars($username); ?></p>
                <p>Role: <?php echo htmlspecialchars($userRole); ?></p>
                <p>Balance: $<?php echo number_format($userBalance, 2); ?></p>
                <div class="user-actions">
                    <?php if ($userRole === "Administrator"): ?>
                    <a href="admin.php" class="admin-link">管理员登录</a>
                    <?php endif; ?>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="search-header">
                <h2>Search Results</h2>
                <?php if (!empty($searchMessage)): ?>
                <div class="search-message"><?php echo $searchMessage; ?></div>
                <?php endif; ?>
            </div>
            
            <?php if (count($searchResults) > 0): ?>
            <div class="products-container">
                <?php foreach ($searchResults as $product): ?>
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
                    
                    <!-- 第二列：会员图片2 -->
                    <?php if (!empty($product['member_image2'])): ?>
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['member_image2']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image 2" class="product-image">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 第三列：会员图片3 -->
                    <?php if (!empty($product['member_image3'])): ?>
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['member_image3']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image 3" class="product-image">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 第四列：会员图片4 -->
                    <?php if (!empty($product['member_image4'])): ?>
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['member_image4']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image 4" class="product-image">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 第五列：会员图片5 -->
                    <?php if (!empty($product['member_image5'])): ?>
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['member_image5']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image 5" class="product-image">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 第六列：会员图片6 -->
                    <?php if (!empty($product['member_image6'])): ?>
                    <div class="product-info-column">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['member_image6']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image 6" class="product-image">
                        </div>
                    </div>
                    <?php endif; ?>
                    
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
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-results">
                <h3>No matching products found</h3>
                <p>Please try a different keyword or browse all products.</p>
                <a href="main.php">View All Products</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <?php
        // 获取联系信息设置
        $contact_email = get_setting($conn, 'contact_email', 'info@haircut.network');
        $contact_phone = get_setting($conn, 'contact_phone', '+1-123-456-7890');
        ?>
        <p>Email: <?php echo htmlspecialchars($contact_email); ?> / <?php echo htmlspecialchars(get_setting($conn, 'contact_email2', 'support@haircut.network')); ?></p>
        <p>WeChat: <?php echo htmlspecialchars(get_setting($conn, 'wechat', 'haircut_wechat')); ?></p>
    </footer>
</body>
</html> 