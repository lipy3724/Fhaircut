<?php
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/db_settings.php';

$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$username = $isLoggedIn ? $_SESSION['username'] : '';
$userRole = $isLoggedIn ? $_SESSION['role'] : '';
$userBalance = 0;
$is_activated = true;

// 检查用户是否已激活
if ($isLoggedIn) {
    $user_id = $_SESSION['id'];
    $sql = "SELECT is_activated, balance FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $is_activated, $userBalance);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$is_activated) {
            $isLoggedIn = false;
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

// 获取排序方式
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// 验证排序参数
$allowedSorts = ['id', 'length', 'weight', 'value'];
$allowedOrders = ['asc', 'desc'];

if (!in_array($sort, $allowedSorts)) {
    $sort = 'id';
}

if (!in_array($order, $allowedOrders)) {
    $order = 'desc';
}

// 获取头发列表
$hair_list = [];
$sql = "SELECT * FROM hair ORDER BY " . $sort . " " . strtoupper($order);
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hair_list[] = $row;
    }
    mysqli_free_result($result);
}

// 分页
$itemsPerPage = 10;
$totalItems = count($hair_list);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPageNum = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$currentPageNum = min($currentPageNum, max(1, $totalPages));
$start = ($currentPageNum - 1) * $itemsPerPage;
$pagedHair = array_slice($hair_list, $start, $itemsPerPage);

// 设置当前页面变量
$currentPage = 'hair_list.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Hair Collection - Fhaircut.com</title>
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
    
    .main-content {
        flex: 1;
        padding: 20px;
        width: 100%;
        max-width: none;
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
        color: #e75480;
    }
    
    .sort-options a.active {
        color: #e75480;
        font-weight: bold;
    }
    
    .hair-container {
        margin-bottom: 30px;
    }
    
    .hair-row {
        display: flex;
        margin: 36px 0 20px 0;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
        overflow: visible;
        position: relative;
    }
    
    .hair-info-column {
        width: 20%;
        padding: 15px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        border-right: none;
    }
    
    .hair-info-column:last-child {
        border-right: none;
    }
    
    .hair-title-container {
        position: absolute;
        top: -30px;
        left: 10px;
        right: 10px;
        background-color: transparent;
        padding: 0;
        border-radius: 0;
        z-index: 10;
    }
    
    .hair-title {
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
    
    .hair-image-container {
        width: 100%;
        height: 200px;
        overflow: hidden;
        margin-bottom: 10px;
        position: relative;
    }
    
    .empty-image {
        width: 100%;
        height: 200px;
        background-color: transparent;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .hair-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .hair-info-text {
        font-size: 14px;
        color: #666;
        margin: 2px 0;
        text-align: center;
    }
    
    .hair-value {
        font-size: 16px;
        font-weight: bold;
        color: #e75480;
        text-align: center;
    }
    
    .btn {
        display: inline-block;
        padding: 6px 12px;
        background: #e75480;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 12px;
        transition: background-color 0.3s;
    }
    
    .btn:hover {
        background: #d64072;
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
    
    .page-title {
        font-size: 28px;
        color: #e75480;
        margin-bottom: 20px;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .container {
            flex-direction: column;
        }
        
        .sidebar {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .hair-row {
            flex-direction: column;
        }
        
        .hair-info-column {
            width: 100%;
            border-right: none;
            border-bottom: none;
        }
        
        .hair-image-container {
            height: 150px;
        }
        
        .empty-image {
            height: 150px;
        }
        
        .hair-info-column:last-child {
            border-bottom: none;
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
            <h1 class="page-title">Hair Collection</h1>
            
            <div class="sort-options">
                <a href="hair_list.php?sort=length&order=<?php echo $sort == 'length' && $order == 'asc' ? 'desc' : 'asc'; ?>" <?php echo $sort == 'length' ? 'class="active"' : ''; ?>>
                    Length <?php echo $sort == 'length' && $order == 'asc' ? '↑' : '↓'; ?>
                </a>
                <a href="hair_list.php?sort=weight&order=<?php echo $sort == 'weight' && $order == 'asc' ? 'desc' : 'asc'; ?>" <?php echo $sort == 'weight' ? 'class="active"' : ''; ?>>
                    Weight <?php echo $sort == 'weight' && $order == 'asc' ? '↑' : '↓'; ?>
                </a>
                <a href="hair_list.php?sort=value&order=<?php echo $sort == 'value' && $order == 'asc' ? 'desc' : 'asc'; ?>" <?php echo $sort == 'value' ? 'class="active"' : ''; ?>>
                    Value <?php echo $sort == 'value' && $order == 'asc' ? '↑' : '↓'; ?>
                </a>
                <a href="hair_list.php">All Hair</a>
            </div>
            
            <div class="hair-container">
                <?php if (empty($pagedHair)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h3>No hair records found</h3>
                        <p>There are currently no hair records in the database.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pagedHair as $hair): ?>
                    <div class="hair-row">
                        <!-- 头发标题 -->
                        <div class="hair-title-container">
                            <a class="hair-title" href="hair_detail.php?id=<?php echo $hair['id']; ?>"><?php echo $hair['id']; ?>. <?php echo htmlspecialchars($hair['title']); ?></a>
                        </div>
                        
                        <?php 
                        // 显示5张图片
                        $image_fields = ['image', 'image2', 'image3', 'image4', 'image5'];
                        for ($i = 0; $i < 5; $i++): 
                        ?>
                        <div class="hair-info-column">
                            <div class="hair-image-container">
                                <?php if (!empty($hair[$image_fields[$i]])): ?>
                                    <img src="<?php echo htmlspecialchars($hair[$image_fields[$i]]); ?>" alt="<?php echo htmlspecialchars($hair['title']); ?> - Image <?php echo $i + 1; ?>" class="hair-image">
                                <?php else: ?>
                                    <!-- 空白区域 -->
                                    <div class="empty-image"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPageNum > 1): ?>
                <a href="hair_list.php?sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&page=<?php echo $currentPageNum - 1; ?>">Previous</a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $currentPageNum - 2);
                $endPage = min($totalPages, $currentPageNum + 2);
                
                if ($startPage > 1) {
                    echo '<a href="hair_list.php?sort=' . $sort . '&order=' . $order . '&page=1">1</a>';
                    if ($startPage > 2) {
                        echo '<span>...</span>';
                    }
                }
                
                for ($i = $startPage; $i <= $endPage; $i++) {
                    if ($i == $currentPageNum) {
                        echo '<span>' . $i . '</span>';
                    } else {
                        echo '<a href="hair_list.php?sort=' . $sort . '&order=' . $order . '&page=' . $i . '">' . $i . '</a>';
                    }
                }
                
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span>...</span>';
                    }
                    echo '<a href="hair_list.php?sort=' . $sort . '&order=' . $order . '&page=' . $totalPages . '">' . $totalPages . '</a>';
                }
                ?>
                
                <?php if ($currentPageNum < $totalPages): ?>
                <a href="hair_list.php?sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&page=<?php echo $currentPageNum + 1; ?>">Next</a>
                <?php endif; ?>
                
                <div class="page-jump">
                    <span>Go to</span>
                    <input type="number" min="1" max="<?php echo $totalPages; ?>" value="<?php echo $currentPageNum; ?>" id="page-input">
                    <button onclick="jumpToPage()">Go</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function jumpToPage() {
            const pageInput = document.getElementById('page-input');
            const page = parseInt(pageInput.value);
            if (page >= 1 && page <= <?php echo $totalPages; ?>) {
                window.location.href = 'hair_list.php?sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&page=' + page;
            }
        }
    </script>
</body>
</html>
