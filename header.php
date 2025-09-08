<?php
// 检查用户是否已登录
$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$username = $isLoggedIn ? $_SESSION["username"] : "";
$userRole = $isLoggedIn ? $_SESSION["role"] : "";
$userBalance = 0;

// 如果用户已登录，获取余额和购物车数量
if ($isLoggedIn && isset($_SESSION["id"])) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT balance FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $userBalance);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // 获取购物车商品数量
    $cartCount = 0;
    $sql = "SELECT SUM(quantity) as count FROM cart WHERE user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $cartCount);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
    $cartCount = $cartCount ?: 0;
}

// 确定当前活动页面
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- 头部样式 -->
<style>
    :root {
        --main-bg-color: #ffe4e8;       /* 浅粉色背景 */
        --header-bg-color: #ffccd5;     /* 稍深一点的粉色，用于头部 */
        --accent-color: #e75480;        /* 深粉色，用于强调和按钮 */
        --hover-color: #d64072;         /* 鼠标悬停时的颜色 */
        --text-color: #333333;          /* 文本颜色 */
        --light-accent: #ffb6c1;        /* 浅强调色 */
        --border-color: #f7a4b9;        /* 边框颜色 */
    }

    body {
        background-color: var(--main-bg-color);
    }
    
    header {
        background-color: var(--header-bg-color);
        color: var(--text-color);
        padding: 25px 0 25px 0;  /* 增加顶部内边距 */
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        position: relative;
        box-shadow: 0 2px 10px rgba(231, 84, 128, 0.2);
    }
    
    .site-title {
        font-size: 24px;
        font-weight: bold;
        padding-left: 20px;
        position: relative;
        display: flex;
        align-items: center;
        height: 100%;
    }

    .site-nav {
        display: flex;
        align-items: center;
    }
    
    .site-nav h1 {
        margin: 0;
        padding: 0;
        font-size: 26px;
        color: var(--accent-color);
        font-family: Arial, sans-serif;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
    }
    
    .site-nav a {
        display: inline-block;
        padding: 6px 14px;
        background: transparent;
        color: var(--text-color);
        text-decoration: none;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .site-nav a:hover,
    .site-nav a.active {
        border-color: var(--accent-color);
        color: var(--accent-color);
        background: white;
    }
    
    .site-nav a:hover {
        background-color: #fff0f5;
    }
    
    .membership-notice {
        text-align: center;
        font-size: 24px;
        color: var(--accent-color);
        margin-top: 15px;
    }
    
    .favorite-shortcut {
        position: absolute;
        top: 10px;
        right: 20px;
        color: var(--accent-color);
        font-size: 14px;
        font-weight: bold;
    }
    
    /* 添加右上角登录区域样式 */
    .auth-buttons {
        position: absolute;
        bottom: 15px;
        right: 20px;
        display: flex;
        gap: 10px;
    }
    
    .auth-button {
        padding: 8px 16px;
        background-color: var(--accent-color);
        color: white;
        border: none;
        border-radius: 4px;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s;
        box-shadow: 0 2px 4px rgba(231, 84, 128, 0.3);
        font-weight: bold;
    }
    
    .auth-button:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(231, 84, 128, 0.4);
    }
    
    .user-top-info {
        position: absolute;
        bottom: 15px;
        right: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .user-top-info .welcome {
        color: var(--text-color);
        font-size: 16px;
        font-weight: 500;
    }
    
    .user-top-info .balance {
        font-weight: bold;
        color: var(--accent-color);
        font-size: 18px;
    }
    
    .user-top-info .logout-link {
        color: var(--accent-color);
        text-decoration: none;
        font-size: 16px;
        font-weight: bold;
        padding: 0 5px;
    }
    
    .user-top-info .logout-button {
        color: white;
        background-color: var(--accent-color);
        padding: 6px 12px;
        border-radius: 4px;
        border: none;
        transition: all 0.2s;
    }
    
    .user-top-info .logout-button:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(231, 84, 128, 0.3);
    }
    
    .user-top-info .admin-link {
        color: white;
        background-color: var(--hover-color);
        padding: 6px 12px;
        border-radius: 4px;
        border: none;
        margin-right: 10px;
        transition: all 0.2s;
    }
    
    .user-top-info .admin-link:hover {
        background-color: #c43767;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(231, 84, 128, 0.3);
    }
    
    .cart-icon {
        position: relative;
        display: inline-block;
        margin-right: 10px;
        padding: 6px 10px;
        background-color: var(--light-accent);
        border-radius: 4px;
        transition: all 0.2s;
        text-decoration: none;
        color: var(--text-color);
    }
    
    .cart-icon:hover {
        background-color: var(--accent-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(231, 84, 128, 0.3);
    }
    
    .cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: var(--accent-color);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        min-width: 20px;
    }
    
    .cart-badge.empty {
        display: none;
    }
    
    @media (max-width: 768px) {
        header {
            grid-template-columns: 1fr;
            gap: 10px;
            text-align: center;
        }
        
        .site-title {
            padding-left: 0;
        }
        
        .site-nav {
            align-items: center;
        }
        
        .favorite-shortcut {
            position: static;
            margin-top: 10px;
        }
        
        .auth-buttons, .user-top-info {
            position: static;
            margin-top: 15px;
            justify-content: center;
        }
    }
</style>

<!-- 头部HTML结构 -->
<header>
    <div class="site-title">
        <div class="site-nav">
            <!-- 左上角显示网站标题 -->
            <h1>Fhaircut.com</h1>
        </div>
    </div>
    <div class="membership-notice">we site membership system, please log on to access</div>
    <div class="favorite-shortcut">Ctrl+D Add favorite</div>
    
    <?php if (!$isLoggedIn): ?>
    <div class="auth-buttons">
        <a href="login.php" class="auth-button">Login</a>
        <a href="register.php" class="auth-button">Register</a>
    </div>
    <?php else: ?>
    <div class="user-top-info">
        <span class="welcome">Welcome, <?php echo htmlspecialchars($username); ?></span>
        <a href="cart.php" class="cart-icon" title="购物车">
            Cart
            <span class="cart-badge <?php echo $cartCount == 0 ? 'empty' : ''; ?>" id="cart-badge">
                <?php echo $cartCount; ?>
            </span>
        </a>
        <span class="balance">$<?php echo number_format($userBalance, 2); ?></span>
        <?php if ($userRole === "Administrator"): ?>
        <a href="admin.php" class="logout-link admin-link">Admin</a>
        <?php endif; ?>
        <a href="logout.php" class="logout-link logout-button">Logout</a>
    </div>
    <?php endif; ?>
</header> 