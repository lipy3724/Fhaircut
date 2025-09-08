<?php
// 设置字符编码
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// 显示错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 启动会话
session_start();

// 包含数据库配置文件和购物车管理类
require_once "db_config.php";
require_once "CartManager.php";

// 检查用户是否已登录
$isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

if (!$isLoggedIn) {
    header("Location: login.php?redirect=cart.php");
    exit();
}

$user_id = $_SESSION["id"];
$username = $_SESSION["username"];

// 初始化购物车管理器
$cartManager = new CartManager($conn);

// 获取购物车商品和总价
$cartItems = $cartManager->getCartItems($user_id);
$cartTotal = $cartManager->calculateTotal($user_id);
$cartCount = $cartManager->getCartCount($user_id);

// 设置当前页面变量
$currentPage = 'cart.php';

// 获取类别数据用于侧边栏
$categories = [];
$sql = "SELECT id, name FROM categories ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// 获取当前类别（用于搜索）
$currentCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Fhaircut.com</title>
    
    <!-- PayPal SDK -->
    <?php require_once __DIR__ . '/env.php'; ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo env('PAYPAL_CLIENT_ID'); ?>&currency=USD&components=buttons,googlepay,applepay&enable-funding=venmo,paylater,card"></script>
    <!-- Google Pay SDK -->
    <script async src="https://pay.google.com/gp/p/js/pay.js"></script>
    <!-- Apple Pay SDK -->
    <script src="https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js"></script>
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
        
        .cart-wrapper {
            flex: 1;
            padding: 20px;
        }
        
        .cart-header {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
        }
        
        .cart-title {
            font-size: 28px;
            color: #e75480;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .cart-summary {
            text-align: center;
            color: #666;
            font-size: 16px;
        }
        
        .cart-content {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
        }
        
        .cart-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .batch-controls {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #e75480;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #d64072;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        
        .hidden {
            display: none !important;
        }
        
        .delete-mode-controls {
            display: none;
        }
        
        .delete-mode-controls.show {
            display: flex;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .cart-table th,
        .cart-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .cart-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .item-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .item-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .item-type {
            color: #666;
            font-size: 12px;
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
        }
        
        .price {
            font-weight: bold;
            color: #e75480;
        }
        
        .cart-footer {
            border-top: 2px solid #e75480;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .total-section {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .total-price {
            color: #e75480;
            font-size: 24px;
        }
        
        .checkout-section {
            display: flex;
            gap: 10px;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-cart img {
            width: 120px;
            height: 120px;
            opacity: 0.5;
            margin-bottom: 20px;
        }
        
        .continue-shopping {
            margin-top: 20px;
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .loading-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        /* 支付等待弹窗样式 - 参考产品页面 */
        .payment-waiting-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-in-out;
        }

        .payment-waiting-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            color: white;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            position: relative;
            overflow: hidden;
        }

        .payment-waiting-content::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 2s infinite;
        }

        .payment-icon {
            font-size: 60px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        .payment-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .payment-message {
            font-size: 16px;
            margin-bottom: 30px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .payment-progress {
            width: 100%;
            height: 4px;
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .payment-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A, #CDDC39);
            border-radius: 2px;
            animation: progressMove 3s ease-in-out infinite;
        }

        .payment-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
            position: relative;
            z-index: 1;
        }

        .payment-steps {
            text-align: left;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .payment-step {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .payment-step.active {
            opacity: 1;
        }

        .payment-step.completed {
            opacity: 1;
            color: #4CAF50;
        }

        .step-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.3);
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .payment-step.active .step-icon {
            background-color: #FFC107;
            animation: pulse 1s infinite;
        }

        .payment-step.completed .step-icon {
            background-color: #4CAF50;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes progressMove {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }
        
        /* 结算弹窗样式 - 参考产品详情页Purchase区域 */
        .checkout-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            overflow: auto;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 3% auto;
            padding: 0;
            border: none;
            border-radius: 6px;
            width: 90%;
            max-width: 650px;
            box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            background: #fff;
            color: #333;
            padding: 16px 20px;
            border-radius: 6px 6px 0 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .close-btn {
            color: #aaa;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .close-btn:hover {
            color: #e75480;
            background-color: #fef7f8;
        }
        
        .modal-body {
            padding: 20px;
            background: #fafafa;
        }
        
        .checkout-summary {
            background: #fff;
            border-radius: 6px;
            padding: 12px;
            box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1);
            margin-bottom: 12px;
        }
        
        .checkout-summary h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .selected-items {
            margin-bottom: 10px;
        }
        
        .item-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .item-summary:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .item-name {
            font-weight: 500;
            color: #333;
            font-size: 13px;
        }
        
        .item-type-badge {
            background-color: #f7a4b9;
            color: #333;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 6px;
        }
        
        .item-price {
            color: #e75480;
            font-weight: 700;
            font-size: 13px;
        }
        
        .checkout-total {
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
            text-align: right;
            font-size: 16px;
            color: #e75480;
            font-weight: 700;
        }
        
        .payment-methods {
            background: #fff;
            border-radius: 6px;
            padding: 12px;
            box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1);
        }
        
        .payment-methods h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }
        
        .payment-option {
            margin-bottom: 10px;
            border: 1px solid #f7a4b9;
            border-radius: 6px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .payment-option:hover {
            background: #f7a4b9;
        }
        
        .payment-option:last-child {
            margin-bottom: 0;
        }
        
        .payment-btn {
            width: 100%;
            padding: 10px;
            background: #ffccd5;
            border: none;
            cursor: pointer;
            text-align: left;
            transition: background-color 0.3s;
            color: #333;
        }
        
        .payment-btn:hover {
            background-color: #f7a4b9;
        }
        
        .balance-payment-btn {
            background-color: #e75480;
            color: white;
            border: 1px solid #e75480;
            margin-bottom: 10px;
            width: 100%;
            font-weight: bold;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        .balance-payment-btn:hover {
            background-color: #d64072;
            border-color: #d64072;
        }
        
        
        
        .paypal-button-container,
        .googlepay-button-container,
        .applepay-button-container {
            margin-bottom: 10px;
            min-height: 45px;
        }
        
        .paypal-button-container:last-child,
        .googlepay-button-container:last-child,
        .applepay-button-container:last-child {
            margin-bottom: 0;
        }
        
        /* Google Pay 按钮样式 */
        .googlepay-button-container .gpay-button {
            width: 100% !important;
            height: 45px !important;
            border-radius: 4px !important;
        }
        
        /* Apple Pay 按钮样式 */
        .applepay-button-container .apple-pay-button {
            width: 100% !important;
            height: 45px !important;
            border-radius: 4px !important;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .cart-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .batch-controls {
                justify-content: center;
            }
            
            .cart-table {
                font-size: 14px;
            }
            
            .cart-table th,
            .cart-table td {
                padding: 8px 4px;
            }
            
            .item-image {
                width: 60px;
                height: 60px;
            }
            
            .cart-footer {
                flex-direction: column;
                text-align: center;
            }
            
            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }
            
            .cart-wrapper {
                padding: 10px;
            }
            
            .cart-table {
                font-size: 12px;
            }
            
            .cart-table th,
            .cart-table td {
                padding: 8px 4px;
            }
            
            .product-image {
                width: 40px;
                height: 40px;
            }
            
            .quantity-controls {
                gap: 5px;
            }
            
            .quantity-controls button {
                padding: 4px 8px;
                font-size: 12px;
            }
            
            .quantity-controls input {
                width: 40px;
                padding: 4px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php require_once "header.php"; ?>
    
    <div class="container">
        <div class="sidebar">
            <h3>Navigation:</h3>
            <ul class="category-list">
                <li><a href="home.php" class="<?php echo ($currentPage === 'home.php') ? 'active' : ''; ?>">Homepage</a></li>
                <li><a href="main.php" class="<?php echo ($currentPage === 'main.php') ? 'active' : ''; ?>">All works</a></li>
                <li><a href="hair_list.php" class="<?php echo ($currentPage === 'hair_list.php') ? 'active' : ''; ?>">Hair List</a></li>
                <li><a href="taday_42_off.php" class="<?php echo ($currentPage === 'taday_42_off.php') ? 'active' : ''; ?>">Today 42.0% off</a></li>
                <li><a href="cart.php" class="<?php echo ($currentPage === 'cart.php') ? 'active' : ''; ?>">Shopping Cart</a></li>
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
            
            <div class="search-box">
                <form action="search.php" method="get">
                    <input type="text" name="keyword" placeholder="Keyword" required>
                    <input type="hidden" name="category" value="<?php echo $currentCategory; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
        </div>
        
        <div class="cart-wrapper">
            <div class="cart-header">
                <h1 class="cart-title">My Shopping Cart</h1>
                <div class="cart-summary">
                    Total <span id="cart-count"><?php echo $cartCount; ?></span> items
                </div>
            </div>
        
        <div class="cart-content">
            <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <div style="font-size: 24px; color: #ddd; margin-bottom: 20px; font-weight: bold;">EMPTY CART</div>
                <h3>Your cart is empty</h3>
                <p>No items added to cart yet</p>
                <div class="continue-shopping">
                    <a href="home.php" class="btn btn-primary">Continue Shopping</a>
                </div>
            </div>
                         <?php else: ?>
             
             <div class="cart-controls">
                <div class="select-all-container">
                    <input type="checkbox" id="select-all" class="select-all-checkbox">
                    <label for="select-all">Select All</label>
                </div>
                <div class="batch-controls">
                    <button type="button" class="btn btn-primary" id="manage-btn">Manage</button>
                </div>
                <div class="batch-controls delete-mode-controls" id="delete-mode-controls">
                    <button type="button" class="btn btn-danger" id="delete-selected">Delete Selected</button>
                    <button type="button" class="btn btn-secondary" id="cancel-manage">Cancel</button>
                </div>
            </div>
            
            <table class="cart-table">
                <thead>
                    <tr>
                        <th width="40">Select</th>
                        <th width="100">Image</th>
                        <th>Product Info</th>
                        <th width="100">Price</th>
                        <th width="80" id="action-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                    <tr data-cart-id="<?php echo $item['cart_id']; ?>">
                        <td>
                            <input type="checkbox" class="item-checkbox" data-cart-id="<?php echo $item['cart_id']; ?>">
                        </td>
                        <td>
                            <?php if (!empty($item['image'])): ?>
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="item-image">
                            <?php else: ?>
                            <div class="item-image" style="background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="item-type">
                                <?php 
                                if ($item['item_type'] === 'photo') {
                                    echo 'Product - Photo Package';
                                } elseif ($item['item_type'] === 'video') {
                                    echo 'Product - Video';
                                } else {
                                    echo 'Hair';
                                }
                                ?>
                            </div>
                            <?php if (!empty($item['description'])): ?>
                            <div style="color: #666; font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars(substr($item['description'], 0, 50)) . (strlen($item['description']) > 50 ? '...' : ''); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="price">$<?php echo number_format($item['price'], 2); ?></span>
                        </td>
                        <td class="action-cell">
                            <button type="button" class="btn btn-danger btn-sm delete-action" onclick="removeItem(<?php echo $item['cart_id']; ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="cart-footer">
                <div class="total-section">
                    Total: <span class="total-price" id="cart-total">$<?php echo number_format($cartTotal, 2); ?></span>
                </div>
                <div class="checkout-section">
                    <a href="home.php" class="btn btn-secondary">Continue Shopping</a>
                    <button type="button" class="btn btn-success" id="checkout-btn">Checkout</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 结算支付弹窗 -->
    <div class="checkout-modal" id="checkout-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Checkout - Payment</h3>
                <span class="close-btn" id="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="checkout-summary">
                    <h4>Order Summary</h4>
                    <div class="selected-items" id="selected-items-summary"></div>
                    <div class="checkout-total">
                        <strong>Total: <span id="checkout-total">$0.00</span></strong>
                    </div>
                </div>
                
                <div class="payment-methods">
                    <h4>Select Payment Method</h4>
                    
                    <!-- 余额支付 -->
                    <button class="balance-payment-btn" id="balance-payment-btn">Pay with Balance</button>
                    
                                         <!-- PayPal支付 -->
                     <div id="paypal-button-container" class="paypal-button-container"></div>
                     
                     <!-- Google Pay -->
                     <div id="googlepay-button-container" class="googlepay-button-container"></div>
                     
                     <!-- Apple Pay -->
                     <div id="applepay-button-container" class="applepay-button-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 加载提示 -->
    <div class="loading" id="loading">
        <div class="loading-content">
            <div>Processing...</div>
        </div>
    </div>

    <!-- 支付等待弹窗 -->
    <div class="payment-waiting-modal" id="payment-waiting-modal">
        <div class="payment-waiting-content">
            <div class="payment-icon">💳</div>
            <div class="payment-title">Processing Payment</div>
            <div class="payment-message">Please wait while we process your balance payment...</div>
            <div class="payment-progress">
                <div class="payment-progress-bar"></div>
            </div>
            <div class="payment-spinner"></div>
            <div class="payment-steps">
                <div class="payment-step" id="step-1">
                    <div class="step-icon">1</div>
                    <div>Verifying balance</div>
                </div>
                <div class="payment-step" id="step-2">
                    <div class="step-icon">2</div>
                    <div>Processing transaction</div>
                </div>
                <div class="payment-step" id="step-3">
                    <div class="step-icon">3</div>
                    <div>Sending confirmation</div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // 全局变量
        let isUpdating = false;
        let isManageMode = false;
        
        // 页面加载完成后初始化
        $(document).ready(function() {
            updateSelectAllState();
            updateSelectedTotal();
            bindEvents();
            initializeManageMode();
        });
        
        // 绑定事件
        function bindEvents() {
            // 全选/取消全选
            $('#select-all').on('change', function() {
                $('.item-checkbox').prop('checked', this.checked);
                updateSelectedTotal();
            });
            
            // 单个商品选择
            $('.item-checkbox').on('change', function() {
                updateSelectAllState();
                updateSelectedTotal();
            });
            
            // 删除选中商品
            $('#delete-selected').on('click', function() {
                const selectedItems = $('.item-checkbox:checked').map(function() {
                    return $(this).data('cart-id');
                }).get();
                
                if (selectedItems.length === 0) {
                    alert('Please select items to delete');
                    return;
                }
                
                if (confirm(`Are you sure you want to delete ${selectedItems.length} selected items?`)) {
                    batchRemoveItems(selectedItems);
                }
            });
            
            
            // 管理模式切换
            $('#manage-btn').on('click', function() {
                enterManageMode();
            });
            
            // 取消管理模式
            $('#cancel-manage').on('click', function() {
                exitManageMode();
            });
            
            // 结算按钮
            $('#checkout-btn').on('click', function() {
                openCheckoutModal();
            });
            
            // 关闭弹窗
            $('#close-modal').on('click', function() {
                closeCheckoutModal();
            });
            
            // 点击弹窗外部关闭
            $('#checkout-modal').on('click', function(e) {
                if (e.target === this) {
                    closeCheckoutModal();
                }
            });
        }
        
        // 更新全选状态
        function updateSelectAllState() {
            const totalItems = $('.item-checkbox').length;
            const checkedItems = $('.item-checkbox:checked').length;
            
            if (totalItems === 0) {
                $('#select-all').prop('indeterminate', false).prop('checked', false);
            } else if (checkedItems === 0) {
                $('#select-all').prop('indeterminate', false).prop('checked', false);
            } else if (checkedItems === totalItems) {
                $('#select-all').prop('indeterminate', false).prop('checked', true);
            } else {
                $('#select-all').prop('indeterminate', true).prop('checked', false);
            }
        }
        
        
        // 删除单个商品
        function removeItem(cartId) {
            if (isUpdating) return;
            
            // 检查是否在管理模式
            if (!isManageMode) {
                alert('Please click "Manage" button to enter management mode first');
                return;
            }
            
            if (!confirm('Are you sure you want to delete this item?')) {
                return;
            }
            
            isUpdating = true;
            showLoading();
            
            $.ajax({
                url: 'cart_api.php',
                method: 'POST',
                data: {
                    action: 'remove_items',
                    cart_ids: [cartId]
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $(`tr[data-cart-id="${cartId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            updateCartDisplay();
                            checkEmptyCart();
                        });
                    } else {
                        alert('Delete failed: ' + response.message);
                    }
                },
                error: function() {
                    alert('Network error, please try again');
                },
                complete: function() {
                    isUpdating = false;
                    hideLoading();
                }
            });
        }
        
        // 批量删除商品
        function batchRemoveItems(cartIds) {
            if (isUpdating) return;
            
            // 检查是否在管理模式
            if (!isManageMode) {
                alert('请先点击"管理"按钮进入管理模式');
                return;
            }
            
            isUpdating = true;
            showLoading();
            
            $.ajax({
                url: 'cart_api.php',
                method: 'POST',
                data: {
                    action: 'remove_items',
                    cart_ids: cartIds
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        cartIds.forEach(function(cartId) {
                            $(`tr[data-cart-id="${cartId}"]`).fadeOut(300, function() {
                                $(this).remove();
                                updateCartDisplay();
                                checkEmptyCart();
                            });
                        });
                    } else {
                        alert('Delete failed: ' + response.message);
                    }
                },
                error: function() {
                    alert('Network error, please try again');
                },
                complete: function() {
                    isUpdating = false;
                    hideLoading();
                }
            });
        }
        
        
        // 更新购物车显示
        function updateCartDisplay() {
            $.ajax({
                url: 'cart_api.php',
                method: 'GET',
                data: {
                    action: 'get_cart_summary'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#cart-count').text(response.data.count);
                        
                        updateSelectAllState();
                        updateSelectedTotal();
                    }
                }
            });
        }
        
        // 更新选中商品总计
        function updateSelectedTotal() {
            const selectedItems = $('.item-checkbox:checked').map(function() {
                return $(this).data('cart-id');
            }).get();
            
            if (selectedItems.length === 0) {
                $('#cart-total').text('$0.00');
                return;
            }
            
            $.ajax({
                url: 'cart_api.php',
                method: 'GET',
                data: {
                    action: 'get_selected_total',
                    cart_ids: selectedItems.join(',')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#cart-total').text('$' + parseFloat(response.data.total).toFixed(2));
                    }
                },
                error: function() {
                    console.error('Failed to get selected items total');
                }
            });
        }
        
        // 检查是否为空购物车
        function checkEmptyCart() {
            if ($('.cart-table tbody tr').length === 0) {
                setTimeout(function() {
                    location.reload();
                }, 500);
            }
        }
        
        // 显示加载提示
        function showLoading() {
            $('#loading').css('display', 'flex');
        }
        
        // 隐藏加载提示
        function hideLoading() {
            $('#loading').hide();
        }

        // 显示支付等待弹窗
        function showPaymentWaitingModal() {
            $('#payment-waiting-modal').css('display', 'flex');
            
            // 模拟支付步骤进度
            setTimeout(function() {
                $('#step-1').addClass('active');
            }, 500);
            
            setTimeout(function() {
                $('#step-1').removeClass('active').addClass('completed');
                $('#step-1 .step-icon').text('✓');
                $('#step-2').addClass('active');
            }, 1500);
            
            setTimeout(function() {
                $('#step-2').removeClass('active').addClass('completed');
                $('#step-2 .step-icon').text('✓');
                $('#step-3').addClass('active');
            }, 2500);
        }

        // 隐藏支付等待弹窗
        function hidePaymentWaitingModal() {
            $('#payment-waiting-modal').hide();
            
            // 重置步骤状态
            $('.payment-step').removeClass('active completed');
            $('.step-icon').text(function(index) {
                return index + 1;
            });
        }
        
        // 初始化管理模式
        function initializeManageMode() {
            // 默认隐藏操作列和删除按钮
            hideActionColumn();
        }
        
        // 进入管理模式
                 function enterManageMode() {
             isManageMode = true;
             
             // 隐藏管理按钮，显示删除模式控制按钮
             $('#manage-btn').closest('.batch-controls').hide();
             $('#delete-mode-controls').addClass('show');
            
            // 显示操作列和删除按钮
            showActionColumn();
            
            // 清除所有选中状态
            $('.item-checkbox').prop('checked', false);
            $('#select-all').prop('checked', false);
            updateSelectedTotal();
        }
        
        // 退出管理模式
                 function exitManageMode() {
             isManageMode = false;
             
             // 显示管理按钮，隐藏删除模式控制按钮
             $('#manage-btn').closest('.batch-controls').show();
             $('#delete-mode-controls').removeClass('show');
            
            // 隐藏操作列和删除按钮
            hideActionColumn();
            
            // 清除所有选中状态
            $('.item-checkbox').prop('checked', false);
            $('#select-all').prop('checked', false);
            updateSelectedTotal();
        }
        
        // 显示操作列
        function showActionColumn() {
            $('#action-column').show();
            $('.action-cell').show();
            $('.delete-action').show();
        }
        
        // 隐藏操作列
        function hideActionColumn() {
            $('#action-column').hide();
            $('.action-cell').hide();
            $('.delete-action').hide();
        }
        
        // 打开结算弹窗
        function openCheckoutModal() {
            const selectedItems = $('.item-checkbox:checked');
            
            if (selectedItems.length === 0) {
                alert('Please select items to checkout');
                return;
            }
            
            // 获取选中的商品信息
            const selectedCartIds = selectedItems.map(function() {
                return $(this).data('cart-id');
            }).get();
            
            // 获取选中商品的详细信息
            getSelectedItemsDetails(selectedCartIds);
            
            // 显示弹窗
            $('#checkout-modal').show();
        }
        
        // 关闭结算弹窗
        function closeCheckoutModal() {
            $('#checkout-modal').hide();
            
            // 清理支付按钮
            $('#paypal-button-container').empty();
            $('#googlepay-button-container').empty();
            $('#applepay-button-container').empty();
        }
        
        // 获取选中商品详情
        function getSelectedItemsDetails(cartIds) {
            showLoading();
            
            $.ajax({
                url: 'cart_checkout_api.php',
                method: 'POST',
                data: {
                    action: 'get_checkout_items',
                    cart_ids: cartIds
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayCheckoutSummary(response.data.items, response.data.total);
                        updateUserBalance(response.data.user_balance);
                        initializePaymentMethods(response.data.items, response.data.total);
                    } else {
                        alert('Error loading checkout data: ' + response.message);
                        closeCheckoutModal();
                    }
                },
                error: function() {
                    alert('Network error, please try again');
                    closeCheckoutModal();
                },
                complete: function() {
                    hideLoading();
                }
            });
        }
        
        // 显示结算摘要
        function displayCheckoutSummary(items, total) {
            let summaryHtml = '';
            
            items.forEach(function(item) {
                let itemTypeText = '';
                if (item.item_type === 'photo') {
                    itemTypeText = 'Photo Package';
                } else if (item.item_type === 'video') {
                    itemTypeText = 'Video';
                } else if (item.item_type === 'hair') {
                    itemTypeText = 'Hair';
                }
                
                summaryHtml += `
                    <div class="item-summary">
                        <div class="item-info">
                            <span class="item-name">${item.title}</span>
                            <span class="item-type-badge">${itemTypeText}</span>
                        </div>
                        <span class="item-price">$${parseFloat(item.price).toFixed(2)}</span>
                    </div>
                `;
            });
            
            $('#selected-items-summary').html(summaryHtml);
            $('#checkout-total').text('$' + parseFloat(total).toFixed(2));
        }
        
        // Google Pay安全加载器
        (function(){
            var attempts = 0;
            function tryInitGPay(){
                attempts++;
                var ready = window.google && google.payments && google.payments.api;
                if (ready) {
                    console.log('Google Pay SDK loaded for cart');
                    return;
                }
                if (attempts < 100) {
                    setTimeout(tryInitGPay, 100);
                } else {
                    console.warn('Google Pay SDK not loaded for cart');
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryInitGPay);
            } else {
                tryInitGPay();
            }
        })();
        
        // 全局用户余额变量
        let currentUserBalance = 0;
        
        // 更新用户余额显示
        function updateUserBalance(balance) {
            currentUserBalance = parseFloat(balance);
            // 如果有余额显示元素，更新它（用于兼容性）
            if ($('#user-balance-display').length) {
                $('#user-balance-display').text('$' + currentUserBalance.toFixed(2));
            }
        }
        
        // 初始化支付方式
        function initializePaymentMethods(items, total) {
            const cartIds = items.map(item => item.cart_id);
            
            // 初始化余额支付
            initializeBalancePayment(cartIds, total);
            
            // 初始化PayPal支付
            initializePayPalPayment(cartIds, total);
            
            // 初始化Google Pay
            initializeGooglePay(cartIds, total);
            
            // 初始化Apple Pay
            initializeApplePay(cartIds, total);
        }
        
        // 初始化余额支付
        function initializeBalancePayment(cartIds, total) {
            $('#balance-payment-btn').off('click').on('click', function() {
                if (currentUserBalance < total) {
                    alert(`Insufficient balance. Your balance: $${currentUserBalance.toFixed(2)}, Total: $${total.toFixed(2)}`);
                    return;
                }
                
                if (confirm(`Do you want to pay $${total.toFixed(2)} using your account balance?`)) {
                    processBalancePayment(cartIds);
                }
            });
        }
        
        // 处理余额支付
        function processBalancePayment(cartIds) {
            showPaymentWaitingModal();
            
            $.ajax({
                url: 'cart_checkout_api.php',
                method: 'POST',
                data: {
                    action: 'process_balance_payment',
                    cart_ids: cartIds
                },
                dataType: 'json',
                success: function(response) {
                    // 确保最后一步完成动画
                    setTimeout(function() {
                        $('#step-3').removeClass('active').addClass('completed');
                        $('#step-3 .step-icon').text('✓');
                        
                        setTimeout(function() {
                            hidePaymentWaitingModal();
                            
                            if (response.success) {
                                showPaymentSuccess(response.order_ids);
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            } else {
                                // 检查是否是邮件发送失败
                                if (response.email_failed || (response.error && response.error.includes('Email sending failed'))) {
                                    alert('Email sending failed - rate limited. Your balance was not charged. Please wait 30 seconds before trying again.');
                                } else {
                                    alert('Payment failed: ' + (response.error || response.message));
                                }
                            }
                        }, 1000);
                    }, 1000);
                },
                error: function() {
                    setTimeout(function() {
                        hidePaymentWaitingModal();
                        alert('Network error, please try again');
                    }, 1000);
                }
            });
        }
        
        // 初始化PayPal支付
        function initializePayPalPayment(cartIds, total) {
            $('#paypal-button-container').empty();
            
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal'
                },
                
                createOrder: function(data, actions) {
                    return $.ajax({
                        url: 'cart_checkout_api.php',
                        method: 'POST',
                        data: {
                            action: 'create_paypal_orders',
                            cart_ids: cartIds
                        },
                        dataType: 'json'
                    }).then(function(response) {
                        if (response.success && response.data.length > 0) {
                            // 返回第一个订单ID用于PayPal处理
                            return response.data[0].order_id;
                        } else {
                            throw new Error(response.message || 'Failed to create orders');
                        }
                    });
                },
                
                onApprove: function(data, actions) {
                    return $.ajax({
                        url: 'cart_checkout_api.php',
                        method: 'POST',
                        data: {
                            action: 'capture_paypal_payments',
                            paypal_order_id: data.orderID,
                            cart_ids: cartIds
                        },
                        dataType: 'json'
                    }).then(function(response) {
                        if (response.success) {
                            showPaymentSuccess(response.order_ids);
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        } else {
                            // 检查是否是邮件发送失败
                            if (response.email_failed || (response.error && response.error.includes('Failed to send purchase confirmation email'))) {
                                alert('Email sending failed. Payment was not completed. Please try again later.');
                            } else {
                                alert('Payment processing failed: ' + (response.error || response.message));
                            }
                        }
                    });
                },
                
                onError: function(err) {
                    console.error('PayPal error:', err);
                    alert('PayPal payment failed. Please try again.');
                }
            }).render('#paypal-button-container');
        }
        
        // Google Pay 基础配置
        const baseRequest = {
            apiVersion: 2,
            apiVersionMinor: 0
        };
        
        // 基础卡支付方法
        const baseCardPaymentMethod = {
            type: 'CARD',
            parameters: {
                allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA']
            }
        };
        
        // 获取Google Pay客户端
        function getGooglePaymentsClient() {
            console.log('Creating Google Payments client for cart');
            const paymentsClient = new google.payments.api.PaymentsClient({
                environment: '<?php echo env('PAYPAL_SANDBOX', true) ? 'TEST' : 'PRODUCTION'; ?>',
                paymentDataCallbacks: {
                    onPaymentAuthorized: onCartPaymentAuthorized
                }
            });
            return paymentsClient;
        }
        
        // 初始化Google Pay
        function initializeGooglePay(cartIds, total) {
            console.log('Initializing Google Pay for cart:', cartIds, total);
            
            if (!window.google || !google.payments || !google.payments.api) {
                console.log('Google Pay SDK not loaded yet, will retry');
                setTimeout(() => initializeGooglePay(cartIds, total), 100);
                return;
            }
            
            const paymentsClient = getGooglePaymentsClient();
            const isReadyToPayRequest = Object.assign({}, baseRequest);
            isReadyToPayRequest.allowedPaymentMethods = [baseCardPaymentMethod];
            
            // 检查设备是否支持Google Pay
            paymentsClient.isReadyToPay(isReadyToPayRequest)
                .then(function(response) {
                    console.log('Google Pay isReadyToPay response:', response);
                    if (response.result) {
                        addGooglePayButtonToCart(cartIds, total);
                    } else {
                        console.log('Google Pay is not available on this device/browser');
                        $('#googlepay-button-container').hide();
                    }
                })
                .catch(function(err) {
                    console.error('Error checking Google Pay availability:', err);
                    $('#googlepay-button-container').hide();
                });
        }
        
        // 添加Google Pay按钮到购物车
        function addGooglePayButtonToCart(cartIds, total) {
            const container = document.getElementById('googlepay-button-container');
            if (!container) return;
            
            // 清空容器
            container.innerHTML = '';
            
            // 创建加载覆盖层
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
            overlay.style.display = 'none';
            container.parentNode.appendChild(overlay);
            
            const paymentsClient = getGooglePaymentsClient();
            const button = paymentsClient.createButton({
                onClick: function() {
                    overlay.style.display = 'flex';
                    onCartGooglePaymentButtonClicked(cartIds, total, overlay);
                },
                allowedPaymentMethods: [baseCardPaymentMethod],
                buttonColor: 'black',
                buttonType: 'buy',
                buttonSizeMode: 'fill'
            });
            
            container.appendChild(button);
        }
        
        // 处理购物车Google Pay按钮点击
        function onCartGooglePaymentButtonClicked(cartIds, total, overlay) {
            console.log('Cart Google Pay button clicked:', cartIds, total);
            
            // 创建PayPal订单用于购物车商品
            createCartPayPalOrder(cartIds, total)
                .then(function(orderId) {
                    console.log('PayPal order created for cart:', orderId);
                    
                    const paymentDataRequest = {
                        apiVersion: 2,
                        apiVersionMinor: 0,
                        allowedPaymentMethods: [{
                            type: 'CARD',
                            parameters: {
                                allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                                allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA']
                            },
                            tokenizationSpecification: {
                                type: 'PAYMENT_GATEWAY',
                                parameters: {
                                    gateway: 'paypal',
                                    gatewayMerchantId: '<?php echo env('PAYPAL_CLIENT_ID'); ?>'
                                }
                            }
                        }],
                        transactionInfo: {
                            totalPriceStatus: 'FINAL',
                            totalPrice: total.toFixed(2),
                            currencyCode: 'USD',
                            countryCode: 'US'
                        },
                        merchantInfo: {
                            merchantName: 'Your Store'
                        }
                    };
                    
                    const paymentsClient = getGooglePaymentsClient();
                    return paymentsClient.loadPaymentData(paymentDataRequest);
                })
                .then(function(paymentData) {
                    console.log('Google Pay payment data received for cart:', paymentData);
                    // 支付成功处理将在onCartPaymentAuthorized中完成
                })
                .catch(function(err) {
                    overlay.style.display = 'none';
                    console.error('Cart Google Pay error:', err);
                    
                    if (err.statusCode === "CANCELED") {
                        console.log('User canceled the payment');
                    } else {
                        alert('Google Pay支付失败。请稍后再试。');
                    }
                });
        }
        
        // 购物车支付授权回调
        function onCartPaymentAuthorized(paymentData) {
            console.log('Cart payment authorized:', paymentData);
            
            // 这里应该处理支付完成逻辑
            // 暂时返回成功状态
            return new Promise(function(resolve, reject) {
                // 模拟支付处理
                setTimeout(function() {
                    console.log('Cart payment processing completed');
                    resolve({ transactionState: 'SUCCESS' });
                    
                    // 显示成功消息并刷新
                    alert('Payment successful!');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }, 1000);
            });
        }
        
        // 创建购物车PayPal订单
        function createCartPayPalOrder(cartIds, total) {
            return new Promise(function(resolve, reject) {
                // 这里应该调用后端API创建PayPal订单
                // 暂时模拟返回订单ID
                setTimeout(function() {
                    const orderId = 'CART_ORDER_' + Date.now();
                    console.log('Mock cart PayPal order created:', orderId);
                    resolve(orderId);
                }, 500);
            });
        }
        
        // 初始化Apple Pay
        function initializeApplePay(cartIds, total) {
            console.log('Initializing Apple Pay for cart:', cartIds, total);
            
            // 检查Apple Pay是否可用
            if (window.ApplePaySession && ApplePaySession.canMakePayments()) {
                console.log('Apple Pay is available');
                renderCartApplePayButton(cartIds, total);
            } else {
                console.log('Apple Pay is not available on this device/browser');
                $('#applepay-button-container').hide();
            }
        }
        
        // 渲染购物车Apple Pay按钮
        function renderCartApplePayButton(cartIds, total) {
            const container = document.getElementById('applepay-button-container');
            if (!container) return;
            
            console.log('Rendering Apple Pay button for cart');
            
            try {
                // 使用PayPal的Apple Pay组件
                paypal.Applepay({
                    buttonStyle: {
                        type: 'buy',
                        color: 'black',
                        height: 45
                    },
                    onClick: function() {
                        console.log('Cart Apple Pay button clicked');
                        handleCartApplePayButtonClick(cartIds, total, container);
                    }
                }).render('applepay-button-container');
                
                // 确保按钮容器样式正确
                container.style.width = '100%';
                container.style.minHeight = '45px';
                
                console.log('Apple Pay button rendered successfully for cart');
            } catch (error) {
                console.error('Error rendering Apple Pay button for cart:', error);
                container.style.display = 'none';
            }
        }
        
        // 处理购物车Apple Pay按钮点击
        function handleCartApplePayButtonClick(cartIds, total, container) {
            console.log('Handling cart Apple Pay button click');
            
            // 创建加载覆盖层
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
            overlay.style.display = 'flex';
            container.parentNode.appendChild(overlay);
            
            try {
                // 创建PayPal订单
                createCartPayPalOrder(cartIds, total)
                    .then(function(orderId) {
                        console.log('PayPal order created for cart Apple Pay:', orderId);
                        
                        // 创建Apple Pay支付请求
                        const paymentRequest = {
                            countryCode: 'US',
                            currencyCode: 'USD',
                            supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
                            merchantCapabilities: ['supports3DS'],
                            total: {
                                label: 'Cart Total',
                                type: 'final',
                                amount: total.toFixed(2)
                            }
                        };
                        
                        console.log('Cart Apple Pay payment request:', paymentRequest);
                        
                        // 创建Apple Pay会话
                        const session = new ApplePaySession(6, paymentRequest);
                        
                        // 设置验证商家回调
                        session.onvalidatemerchant = function(event) {
                            console.log('Cart Apple Pay validate merchant:', event);
                            // 这里应该调用后端验证商家
                            // 暂时模拟成功
                            setTimeout(function() {
                                session.completeMerchantValidation({});
                            }, 500);
                        };
                        
                        // 设置支付授权回调
                        session.onpaymentauthorized = function(event) {
                            console.log('Cart Apple Pay payment authorized:', event);
                            
                            // 模拟支付处理
                            setTimeout(function() {
                                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                overlay.style.display = 'none';
                                
                                alert('Apple Pay payment successful!');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            }, 1000);
                        };
                        
                        // 开始Apple Pay会话
                        session.begin();
                    })
                    .catch(function(error) {
                        console.error('Error creating PayPal order for cart Apple Pay:', error);
                        overlay.style.display = 'none';
                        alert('Unable to initialize Apple Pay. Please try again.');
                    });
            } catch (error) {
                console.error('Error in cart Apple Pay flow:', error);
                overlay.style.display = 'none';
                alert('Apple Pay支付过程中发生错误。请稍后再试。');
            }
        }
        
        // 显示支付成功消息
        function showPaymentSuccess(orderIds) {
            closeCheckoutModal();
            
            let message = 'Payment successful!\n\n';
            message += `Orders created: ${orderIds.length}\n`;
            message += `Order IDs: ${orderIds.join(', ')}\n\n`;
            message += 'Confirmation emails have been sent to your email address.';
            
            alert(message);
        }
        
    </script>
        </div> <!-- cart-wrapper -->
    </div> <!-- container -->
</body>
</html>
