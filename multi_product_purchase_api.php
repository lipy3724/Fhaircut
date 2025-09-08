<?php
/**
 * 多商品购买API
 * 处理多个商品的购买请求，发送一封包含所有商品的邮件，但创建多个订单
 */

session_start();
require_once 'config.php';
require_once 'multi_product_email_functions.php';

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

// 处理不同的动作
switch ($action) {
    case 'process_multi_product_balance_payment':
        processMultiProductBalancePayment($input);
        break;
    case 'process_multi_product_paypal_payment':
        processMultiProductPayPalPayment($input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * 处理多商品余额支付
 */
function processMultiProductBalancePayment($input) {
    global $conn;
    
    // 验证用户登录
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['email'] ?? '';
    $username = $_SESSION['username'] ?? 'Customer';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'No email found for the user']);
        return;
    }
    
    // 验证商品数据
    $items = $input['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'No items provided']);
        return;
    }
    
    // 验证和获取商品详细信息
    $validated_items = validateAndGetItemDetails($items);
    if ($validated_items === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid item data']);
        return;
    }
    
    // 处理购买
    $result = processMultiProductPurchase($conn, $email, $username, $validated_items, 'balance', $user_id);
    
    echo json_encode($result);
}

/**
 * 处理多商品PayPal支付
 */
function processMultiProductPayPalPayment($input) {
    global $conn;
    
    $email = $_SESSION['email'] ?? '';
    $username = $_SESSION['username'] ?? 'Customer';
    $user_id = $_SESSION['user_id'] ?? null;
    
    // 如果session中没有邮箱，尝试从PayPal获取
    $paypal_email = $input['paypal_email'] ?? '';
    $paypal_name = $input['paypal_name'] ?? '';
    
    if (empty($email) && !empty($paypal_email)) {
        $email = $paypal_email;
        $username = $paypal_name ?: 'Customer';
    }
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'No email found for the user']);
        return;
    }
    
    // 验证商品数据
    $items = $input['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'No items provided']);
        return;
    }
    
    // 验证和获取商品详细信息
    $validated_items = validateAndGetItemDetails($items);
    if ($validated_items === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid item data']);
        return;
    }
    
    // 处理购买（PayPal支付不需要扣除余额）
    $result = processMultiProductPurchase($conn, $email, $username, $validated_items, 'paypal', $user_id);
    
    echo json_encode($result);
}

/**
 * 验证并获取商品详细信息
 * @param array $items 商品数组
 * @return array|false 验证后的商品数组或false
 */
function validateAndGetItemDetails($items) {
    global $conn;
    
    $validated_items = [];
    
    foreach ($items as $item) {
        $item_type = $item['item_type'] ?? '';
        $item_id = $item['item_id'] ?? '';
        
        if (empty($item_type) || empty($item_id)) {
            return false;
        }
        
        if ($item_type === 'hair') {
            // 获取头发商品信息
            $sql = "SELECT id, title, value as price FROM hair WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $item_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $hair_data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                if (!$hair_data) {
                    return false;
                }
                
                $validated_items[] = [
                    'item_type' => 'hair',
                    'item_id' => $hair_data['id'],
                    'title' => $hair_data['title'],
                    'price' => $hair_data['price']
                ];
            }
        } else if ($item_type === 'video' || $item_type === 'photo_pack') {
            // 获取产品信息
            $sql = "SELECT id, title, price, photo_pack_price, paid_video, paid_photos_zip FROM products WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $item_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $product_data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                if (!$product_data) {
                    return false;
                }
                
                $price = ($item_type === 'photo_pack') ? $product_data['photo_pack_price'] : $product_data['price'];
                $file_path = ($item_type === 'photo_pack') ? $product_data['paid_photos_zip'] : $product_data['paid_video'];
                
                $validated_items[] = [
                    'item_type' => $item_type,
                    'item_id' => $product_data['id'],
                    'title' => $product_data['title'],
                    'price' => $price,
                    'paid_video' => $product_data['paid_video'],
                    'paid_photos_zip' => $product_data['paid_photos_zip']
                ];
            }
        } else {
            return false;
        }
    }
    
    return $validated_items;
}

/**
 * 记录API调用日志
 */
function logApiCall($action, $data, $result) {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'data' => $data,
        'result' => $result,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    error_log("Multi-Product Purchase API: " . json_encode($log_data));
}
?>
