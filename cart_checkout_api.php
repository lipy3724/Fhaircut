<?php
// 购物车结算API
header('Content-Type: application/json');

// 开启错误日志
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Cart checkout API called: " . $_SERVER['REQUEST_URI']);

// 设置错误处理函数
function handleError($errno, $errstr, $errfile, $errline) {
    $error_message = "Error: $errstr in $errfile on line $errline";
    error_log($error_message);
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
    exit;
}

// 设置异常处理函数
function handleException($exception) {
    $error_message = "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    error_log($error_message);
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
    exit;
}

// 注册错误和异常处理器
set_error_handler('handleError');
set_exception_handler('handleException');

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/CartManager.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// 检查用户是否已激活
$user_id = $_SESSION['id'];
$sql = "SELECT is_activated, balance FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $is_activated, $user_balance);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$is_activated) {
        echo json_encode(['error' => 'Account not activated']);
        exit;
    }
} else {
    echo json_encode(['error' => 'Failed to check user status']);
    exit;
}


// 处理API请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'get_checkout_items':
            handleGetCheckoutItems();
            break;
        case 'process_balance_payment':
            handleBalancePayment();
            break;
        case 'create_paypal_orders':
            handleCreatePayPalOrders();
            break;
        case 'capture_paypal_payments':
            handleCapturePayPalPayments();
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

// 获取结算商品信息
function handleGetCheckoutItems() {
    global $conn, $user_id, $user_balance;
    
    $cart_ids = isset($_POST['cart_ids']) ? $_POST['cart_ids'] : [];
    
    if (empty($cart_ids)) {
        echo json_encode(['error' => 'No items selected']);
        return;
    }
    
    // 确保cart_ids是数组并转换为整数
    if (!is_array($cart_ids)) {
        $cart_ids = [$cart_ids];
    }
    $cart_ids = array_map('intval', array_filter($cart_ids));
    
    if (empty($cart_ids)) {
        echo json_encode(['error' => 'Invalid cart items']);
        return;
    }
    
    try {
        // 获取选中的购物车商品详细信息
        $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
        
        $sql = "SELECT 
                    c.id as cart_id,
                    c.item_type,
                    c.item_id,
                    c.price,
                    CASE 
                        WHEN c.item_type IN ('photo', 'video') THEN p.title
                        WHEN c.item_type = 'hair' THEN h.title
                    END as title,
                    CASE 
                        WHEN c.item_type IN ('photo', 'video') THEN p.image
                        WHEN c.item_type = 'hair' THEN h.image
                    END as image
                FROM cart c
                LEFT JOIN products p ON c.item_type IN ('photo', 'video') AND c.item_id = p.id
                LEFT JOIN hair h ON c.item_type = 'hair' AND c.item_id = h.id
                WHERE c.user_id = ? AND c.id IN ($placeholders)
                AND (
                    (c.item_type IN ('photo', 'video') AND p.id IS NOT NULL) OR
                    (c.item_type = 'hair' AND h.id IS NOT NULL)
                )
                ORDER BY c.created_at DESC";
        
        $stmt = mysqli_prepare($conn, $sql);
        $types = 'i' . str_repeat('i', count($cart_ids));
        $params = array_merge([$user_id], $cart_ids);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $items = [];
        $total = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
            $total += floatval($row['price']);
        }
        
        mysqli_stmt_close($stmt);
        
        if (empty($items)) {
            echo json_encode(['error' => 'No valid items found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $total,
                'user_balance' => $user_balance
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting checkout items: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to get checkout items']);
    }
}

// 处理余额支付
function handleBalancePayment() {
    global $conn, $user_id, $user_balance;
    
    require_once __DIR__ . '/multi_product_email_functions.php';
    
    $cart_ids = isset($_POST['cart_ids']) ? $_POST['cart_ids'] : [];
    
    if (empty($cart_ids)) {
        echo json_encode(['error' => 'No items selected']);
        return;
    }
    
    // 确保cart_ids是数组并转换为整数
    if (!is_array($cart_ids)) {
        $cart_ids = [$cart_ids];
    }
    $cart_ids = array_map('intval', array_filter($cart_ids));
    
    try {
        // 获取购物车商品信息
        $cartManager = new CartManager($conn);
        $total = $cartManager->calculateSelectedTotal($user_id, $cart_ids);
        
        if ($total <= 0) {
            echo json_encode(['error' => 'Invalid total amount']);
            return;
        }
        
        // 检查余额是否足够
        if ($user_balance < $total) {
            echo json_encode(['error' => 'Insufficient balance']);
            return;
        }
        
        // 获取购物车商品详细信息
        $items = getCartItemsDetails($cart_ids);
        if (empty($items)) {
            echo json_encode(['error' => 'No valid items found']);
            return;
        }
        
        // 转换购物车商品格式为多商品邮件功能需要的格式，包含所有详细信息
        $purchase_items = [];
        foreach ($items as $item) {
            $item_type = $item['item_type'] === 'photo' ? 'photo_pack' : $item['item_type'];
            $purchase_item = [
                'item_type' => $item_type,
                'item_id' => $item['item_id'],
                'title' => $item['title'],
                'price' => $item['price'],
                'image' => $item['image']
            ];
            
            // 添加产品详细信息
            if ($item_type === 'photo_pack' || $item_type === 'video') {
                $purchase_item['product_subtitle'] = $item['product_subtitle'];
                $purchase_item['product_sales'] = $item['product_sales'];
                $purchase_item['images_count'] = $item['images_count'];
                $purchase_item['images_total_size'] = $item['images_total_size'];
                $purchase_item['images_formats'] = $item['images_formats'];
                $purchase_item['paid_video'] = $item['paid_video'];
                $purchase_item['paid_video_size'] = $item['paid_video_size'];
                $purchase_item['paid_video_duration'] = $item['paid_video_duration'];
                $purchase_item['paid_photos_zip'] = $item['paid_photos_zip'];
                $purchase_item['paid_photos_total_size'] = $item['paid_photos_total_size'];
                $purchase_item['paid_photos_count'] = $item['paid_photos_count'];
                $purchase_item['paid_photos_formats'] = $item['paid_photos_formats'];
                $purchase_item['photo_pack_price'] = $item['photo_pack_price'];
            } elseif ($item_type === 'hair') {
                $purchase_item['hair_description'] = $item['hair_description'];
                $purchase_item['hair_length'] = $item['hair_length'];
                $purchase_item['hair_weight'] = $item['hair_weight'];
                $purchase_item['hair_value'] = $item['hair_value'];
            }
            
            $purchase_items[] = $purchase_item;
        }
        
        // 获取用户邮箱信息
        $email = $_SESSION['email'] ?? '';
        $username = $_SESSION['username'] ?? 'Customer';
        
        if (empty($email)) {
            echo json_encode(['error' => 'No email found for the user']);
            return;
        }
        
        // 使用多商品购买处理函数（余额支付）
        $result = processMultiProductPurchase($conn, $email, $username, $purchase_items, 'balance', $user_id);
        
        if ($result['success']) {
            // 删除购物车中的商品
            $cartManager = new CartManager($conn);
            $cartManager->removeItems($user_id, $cart_ids);
            
            echo json_encode([
                'success' => true,
                'order_ids' => $result['order_ids'],
                'message' => 'Payment successful - Single confirmation email sent',
                'remaining_balance' => $user_balance - $total
            ]);
        } else {
            // 检查是否是邮件发送失败
            $error_message = $result['message'];
            if (strpos($error_message, 'Failed to send confirmation email') !== false) {
                echo json_encode([
                    'error' => 'Email sending failed - rate limited. Your balance was not charged. Please wait 30 seconds before trying again.',
                    'email_failed' => true
                ]);
            } else {
                echo json_encode(['error' => $error_message]);
            }
        }
        
    } catch (Exception $e) {
        error_log("Cart balance payment failed: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// 创建PayPal订单
function handleCreatePayPalOrders() {
    global $conn, $user_id;
    
    require_once __DIR__ . '/env.php';
    
    $cart_ids = isset($_POST['cart_ids']) ? $_POST['cart_ids'] : [];
    
    if (empty($cart_ids)) {
        echo json_encode(['error' => 'No items selected']);
        return;
    }
    
    // 确保cart_ids是数组并转换为整数
    if (!is_array($cart_ids)) {
        $cart_ids = [$cart_ids];
    }
    $cart_ids = array_map('intval', array_filter($cart_ids));
    
    try {
        // 获取购物车商品信息
        $items = getCartItemsDetails($cart_ids);
        if (empty($items)) {
            echo json_encode(['error' => 'No valid items found']);
            return;
        }
        
        // PayPal API配置
        $paypal_client_id = env('PAYPAL_CLIENT_ID');
        $paypal_client_secret = env('PAYPAL_CLIENT_SECRET');
        $paypal_sandbox = env('PAYPAL_SANDBOX', true);
        
        $api_url = $paypal_sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        // 获取访问令牌
        $access_token = getPayPalAccessToken($api_url, $paypal_client_id, $paypal_client_secret);
        if (!$access_token) {
            throw new Exception("Failed to get PayPal access token");
        }
        
        // 计算总金额
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += floatval($item['price']);
        }
        
        // 创建一个合并订单（包含所有商品）
        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'cart_checkout_' . time(),
                    'description' => 'Cart Checkout - ' . count($items) . ' items',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($total_amount, 2, '.', '')
                    ]
                ]
            ]
        ];
        
        $paypal_order = createPayPalOrder($api_url, $access_token, $order_data);
        if (!$paypal_order || isset($paypal_order['error'])) {
            throw new Exception("Failed to create PayPal order");
        }
        
        // 在数据库中保存订单映射关系
        $mapping_id = saveCartPayPalMapping($cart_ids, $paypal_order['id'], $total_amount);
        
        echo json_encode([
            'success' => true,
            'data' => [
                [
                    'order_id' => $paypal_order['id'],
                    'mapping_id' => $mapping_id,
                    'total' => $total_amount
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error creating PayPal orders: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// 捕获PayPal支付
function handleCapturePayPalPayments() {
    global $conn, $user_id;
    
    require_once __DIR__ . '/env.php';
    require_once __DIR__ . '/email_functions.php';
    
    $paypal_order_id = isset($_POST['paypal_order_id']) ? $_POST['paypal_order_id'] : '';
    $cart_ids = isset($_POST['cart_ids']) ? $_POST['cart_ids'] : [];
    
    if (empty($paypal_order_id) || empty($cart_ids)) {
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }
    
    try {
        // PayPal API配置
        $paypal_client_id = env('PAYPAL_CLIENT_ID');
        $paypal_client_secret = env('PAYPAL_CLIENT_SECRET');
        $paypal_sandbox = env('PAYPAL_SANDBOX', true);
        
        $api_url = $paypal_sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        // 获取访问令牌
        $access_token = getPayPalAccessToken($api_url, $paypal_client_id, $paypal_client_secret);
        if (!$access_token) {
            throw new Exception("Failed to get PayPal access token");
        }
        
        // 捕获PayPal支付
        $capture_result = capturePayPalPayment($api_url, $access_token, $paypal_order_id);
        if (!$capture_result || $capture_result['status'] !== 'COMPLETED') {
            throw new Exception("Failed to capture PayPal payment");
        }
        
        // 获取购物车商品信息
        $items = getCartItemsDetails($cart_ids);
        if (empty($items)) {
            throw new Exception("No valid items found");
        }
        
        // 开始事务
        mysqli_begin_transaction($conn);
        
        try {
            // 转换购物车商品格式为多商品邮件功能需要的格式，包含所有详细信息
            $purchase_items = [];
            foreach ($items as $item) {
                $item_type = $item['item_type'] === 'photo' ? 'photo_pack' : $item['item_type'];
                $purchase_item = [
                    'item_type' => $item_type,
                    'item_id' => $item['item_id'],
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'image' => $item['image']
                ];
                
                // 添加产品详细信息
                if ($item_type === 'photo_pack' || $item_type === 'video') {
                    $purchase_item['product_subtitle'] = $item['product_subtitle'];
                    $purchase_item['product_sales'] = $item['product_sales'];
                    $purchase_item['images_count'] = $item['images_count'];
                    $purchase_item['images_total_size'] = $item['images_total_size'];
                    $purchase_item['images_formats'] = $item['images_formats'];
                    $purchase_item['paid_video'] = $item['paid_video'];
                    $purchase_item['paid_video_size'] = $item['paid_video_size'];
                    $purchase_item['paid_video_duration'] = $item['paid_video_duration'];
                    $purchase_item['paid_photos_zip'] = $item['paid_photos_zip'];
                    $purchase_item['paid_photos_total_size'] = $item['paid_photos_total_size'];
                    $purchase_item['paid_photos_count'] = $item['paid_photos_count'];
                    $purchase_item['paid_photos_formats'] = $item['paid_photos_formats'];
                    $purchase_item['photo_pack_price'] = $item['photo_pack_price'];
                } elseif ($item_type === 'hair') {
                    $purchase_item['hair_description'] = $item['hair_description'];
                    $purchase_item['hair_length'] = $item['hair_length'];
                    $purchase_item['hair_weight'] = $item['hair_weight'];
                    $purchase_item['hair_value'] = $item['hair_value'];
                }
                
                $purchase_items[] = $purchase_item;
            }
            
            // 获取用户邮箱信息
            $email = $_SESSION['email'] ?? '';
            $username = $_SESSION['username'] ?? 'Customer';
            
            // 如果session中没有邮箱，尝试从PayPal获取
            if (empty($email) && isset($capture_result['payer']['email_address'])) {
                $email = $capture_result['payer']['email_address'];
                $username = $capture_result['payer']['name']['given_name'] ?? 'Customer';
            }
            
            if (empty($email)) {
                throw new Exception("No email found for the user");
            }
            
            // 使用多商品购买处理函数（PayPal支付）
            require_once __DIR__ . '/multi_product_email_functions.php';
            $result = processMultiProductPurchase($conn, $email, $username, $purchase_items, 'paypal', $user_id);
            
            if (!$result['success']) {
                // 检查是否是邮件发送失败，如果是则返回特定错误信息
                $error_message = $result['message'];
                if (strpos($error_message, 'Failed to send confirmation email') !== false) {
                    throw new Exception('Failed to send purchase confirmation email. Payment was not completed. Please try again later.');
                } else {
                    throw new Exception($error_message);
                }
            }
            
            $order_ids = $result['order_ids'];
            
            // 删除购物车中的商品
            $cartManager = new CartManager($conn);
            $cartManager->removeItems($user_id, $cart_ids);
            
            // 提交事务
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'order_ids' => $order_ids,
                'message' => 'PayPal payment successful - Single confirmation email sent'
            ]);
            
        } catch (Exception $e) {
            // 回滚事务
            mysqli_rollback($conn);
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error capturing PayPal payment: " . $e->getMessage());
        
        // 检查是否是邮件发送失败
        $error_message = $e->getMessage();
        if (strpos($error_message, 'Failed to send purchase confirmation email') !== false) {
            echo json_encode([
                'error' => 'Failed to send purchase confirmation email. Payment was not completed. Please try again later.',
                'email_failed' => true
            ]);
        } else {
            echo json_encode(['error' => $error_message]);
        }
    }
}

// 辅助函数：获取购物车商品详细信息
function getCartItemsDetails($cart_ids) {
    global $conn, $user_id;
    
    if (empty($cart_ids)) {
        return [];
    }
    
    $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
    
    $sql = "SELECT 
                c.id as cart_id,
                c.item_type,
                c.item_id,
                c.price,
                CASE 
                    WHEN c.item_type IN ('photo', 'video') THEN p.title
                    WHEN c.item_type = 'hair' THEN h.title
                END as title,
                CASE 
                    WHEN c.item_type IN ('photo', 'video') THEN p.image
                    WHEN c.item_type = 'hair' THEN h.image
                END as image,
                -- 产品详细信息
                p.subtitle as product_subtitle,
                p.sales as product_sales,
                p.images_count,
                p.images_total_size,
                p.images_formats,
                p.paid_video,
                p.paid_video_size,
                p.paid_video_duration,
                p.paid_photos_zip,
                p.paid_photos_total_size,
                p.paid_photos_count,
                p.paid_photos_formats,
                p.photo_pack_price,
                -- 头发详细信息
                h.description as hair_description,
                h.length as hair_length,
                h.weight as hair_weight,
                h.value as hair_value
            FROM cart c
            LEFT JOIN products p ON c.item_type IN ('photo', 'video') AND c.item_id = p.id
            LEFT JOIN hair h ON c.item_type = 'hair' AND c.item_id = h.id
            WHERE c.user_id = ? AND c.id IN ($placeholders)
            AND (
                (c.item_type IN ('photo', 'video') AND p.id IS NOT NULL) OR
                (c.item_type = 'hair' AND h.id IS NOT NULL)
            )
            ORDER BY c.created_at DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    $types = 'i' . str_repeat('i', count($cart_ids));
    $params = array_merge([$user_id], $cart_ids);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $items;
}

// PayPal API辅助函数
function getPayPalAccessToken($api_url, $client_id, $client_secret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('PayPal access token error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    
    $data = json_decode($result, true);
    return isset($data['access_token']) ? $data['access_token'] : null;
}

function createPayPalOrder($api_url, $access_token, $order_data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('PayPal create order error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    
    return json_decode($result, true);
}

function capturePayPalPayment($api_url, $access_token, $order_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders/' . $order_id . '/capture');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('PayPal capture payment error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    
    return json_decode($result, true);
}

function saveCartPayPalMapping($cart_ids, $paypal_order_id, $total_amount) {
    global $conn, $user_id;
    
    // 创建映射表（如果不存在）
    $create_table_sql = "CREATE TABLE IF NOT EXISTS cart_paypal_mappings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        paypal_order_id VARCHAR(255) NOT NULL,
        cart_ids TEXT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(paypal_order_id)
    )";
    mysqli_query($conn, $create_table_sql);
    
    // 保存映射关系
    $insert_sql = "INSERT INTO cart_paypal_mappings (user_id, paypal_order_id, cart_ids, total_amount) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_sql);
    $cart_ids_json = json_encode($cart_ids);
    mysqli_stmt_bind_param($stmt, "issd", $user_id, $paypal_order_id, $cart_ids_json, $total_amount);
    
    if (mysqli_stmt_execute($stmt)) {
        $mapping_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $mapping_id;
    } else {
        mysqli_stmt_close($stmt);
        return null;
    }
}
?>
