<?php
// 设置字符编码
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

// 启动会话
session_start();

// 包含数据库配置文件和购物车管理类
require_once "db_config.php";
require_once "CartManager.php";

// 检查用户是否已登录
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION["id"];

// 初始化购物车管理器
$cartManager = new CartManager($conn);

// 获取请求方法和动作
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($cartManager, $user_id, $action);
            break;
        case 'POST':
            handlePostRequest($cartManager, $user_id, $action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Cart API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * 处理GET请求
 */
function handleGetRequest($cartManager, $user_id, $action) {
    switch ($action) {
        case 'get_cart_items':
            $items = $cartManager->getCartItems($user_id);
            echo json_encode([
                'success' => true,
                'data' => $items
            ]);
            break;
            
        case 'get_cart_summary':
            $items = $cartManager->getCartItems($user_id);
            $total = $cartManager->calculateTotal($user_id);
            $count = $cartManager->getCartCount($user_id);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'total' => $total,
                    'count' => $count
                ]
            ]);
            break;
            
        case 'get_cart_count':
            $count = $cartManager->getCartCount($user_id);
            echo json_encode([
                'success' => true,
                'data' => [
                    'count' => $count
                ]
            ]);
            break;
            
        case 'get_selected_total':
            $cartIds = isset($_GET['cart_ids']) ? explode(',', $_GET['cart_ids']) : [];
            $cartIds = array_map('intval', array_filter($cartIds));
            
            $total = $cartManager->calculateSelectedTotal($user_id, $cartIds);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'selected_count' => count($cartIds)
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * 处理POST请求
 */
function handlePostRequest($cartManager, $user_id, $action) {
    switch ($action) {
        case 'add_to_cart':
            addToCart($cartManager, $user_id);
            break;
            
        case 'update_quantity':
            updateQuantity($cartManager, $user_id);
            break;
            
        case 'remove_items':
            removeItems($cartManager, $user_id);
            break;
            
        case 'clear_cart':
            clearCart($cartManager, $user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * 添加商品到购物车
 */
function addToCart($cartManager, $user_id) {
    // 验证必要参数
    $item_type = isset($_POST['item_type']) ? $_POST['item_type'] : '';
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $is_photo_pack = isset($_POST['is_photo_pack']) ? (bool)$_POST['is_photo_pack'] : false;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if (empty($item_type) || $item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        return;
    }
    
    if (!in_array($item_type, ['photo', 'video', 'hair'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid item type']);
        return;
    }
    
    $result = $cartManager->addToCart($user_id, $item_type, $item_id, $is_photo_pack, $quantity);
    
    // 如果成功，获取最新的购物车统计
    if ($result['success']) {
        $count = $cartManager->getCartCount($user_id);
        $result['cart_count'] = $count;
    }
    
    echo json_encode($result);
}

/**
 * 更新商品数量
 */
function updateQuantity($cartManager, $user_id) {
    $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    
    if ($cart_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart item ID']);
        return;
    }
    
    $result = $cartManager->updateQuantity($user_id, $cart_id, $quantity);
    
    // 如果成功，获取最新的购物车统计
    if ($result['success']) {
        $total = $cartManager->calculateTotal($user_id);
        $count = $cartManager->getCartCount($user_id);
        $result['cart_total'] = $total;
        $result['cart_count'] = $count;
    }
    
    echo json_encode($result);
}

/**
 * 删除商品
 */
function removeItems($cartManager, $user_id) {
    $cart_ids = isset($_POST['cart_ids']) ? $_POST['cart_ids'] : [];
    
    // 确保cart_ids是数组
    if (!is_array($cart_ids)) {
        $cart_ids = [$cart_ids];
    }
    
    // 转换为整数数组
    $cart_ids = array_map('intval', $cart_ids);
    $cart_ids = array_filter($cart_ids, function($id) { return $id > 0; });
    
    if (empty($cart_ids)) {
        echo json_encode(['success' => false, 'message' => 'No items to remove']);
        return;
    }
    
    $result = $cartManager->removeItems($user_id, $cart_ids);
    
    // 如果成功，获取最新的购物车统计
    if ($result['success']) {
        $total = $cartManager->calculateTotal($user_id);
        $count = $cartManager->getCartCount($user_id);
        $result['cart_total'] = $total;
        $result['cart_count'] = $count;
    }
    
    echo json_encode($result);
}

/**
 * 清空购物车
 */
function clearCart($cartManager, $user_id) {
    $result = $cartManager->clearCart($user_id);
    
    // 如果成功，购物车统计为0
    if ($result['success']) {
        $result['cart_total'] = 0;
        $result['cart_count'] = 0;
    }
    
    echo json_encode($result);
}

/**
 * 验证商品是否存在并获取信息
 */
function validateItem($conn, $item_type, $item_id) {
    if (in_array($item_type, ['photo', 'video'])) {
        // 检查是否有status字段
        $check_status_sql = "SHOW COLUMNS FROM products LIKE 'status'";
        $check_result = mysqli_query($conn, $check_status_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $sql = "SELECT id, title, price, photo_pack_price, image FROM products WHERE id = ? AND status = 'Active'";
        } else {
            $sql = "SELECT id, title, price, photo_pack_price, image FROM products WHERE id = ?";
        }
        
        // 对于photo和video类型，还需要检查相应的内容是否存在
        if ($item_type === 'photo') {
            $sql .= " AND paid_photos_zip IS NOT NULL AND paid_photos_zip != ''";
        } elseif ($item_type === 'video') {
            $sql .= " AND paid_video IS NOT NULL AND paid_video != ''";
        }
    } else {
        $sql = "SELECT id, title, value as price, image FROM hair WHERE id = ?";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $item;
}
?>
