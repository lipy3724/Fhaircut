<?php
// 账号激活支付API
header('Content-Type: application/json');

// 设置错误处理函数，确保返回JSON响应而不是PHP错误
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
require_once __DIR__ . '/env.php';

// 使用环境变量获取PayPal API凭据
$paypal_client_id = env('PAYPAL_CLIENT_ID');
$paypal_client_secret = env('PAYPAL_CLIENT_SECRET');
$paypal_sandbox = env('PAYPAL_SANDBOX', true);

// 获取应用URL和端口
$app_url = env('APP_URL', 'http://localhost');
$app_port = env('APP_PORT', '80');
$base_url = $app_port == '80' ? $app_url : $app_url . ':' . $app_port;

// PayPal API URL
$api_url = $paypal_sandbox 
    ? 'https://api-m.sandbox.paypal.com' 
    : 'https://api-m.paypal.com';

// 激活费用（美元）
$activation_fee = 100.00;

// 获取访问令牌
function getAccessToken() {
    global $api_url, $paypal_client_id, $paypal_client_secret;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $paypal_client_id . ':' . $paypal_client_secret);
    
    $headers = array();
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => 'Error:' . curl_error($ch)];
    }
    curl_close($ch);
    
    return json_decode($result, true);
}

// 创建订单
function createActivationOrder($user_id) {
    global $api_url, $base_url, $activation_fee;
    
    $access_token = getAccessToken();
    if (isset($access_token['error'])) {
        return $access_token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    $payload = json_encode([
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'activation_' . $user_id,
                'description' => 'Account Activation Fee',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $activation_fee
                ]
            ]
        ],
        'application_context' => [
            'return_url' => $base_url . '/activate_account.php?success=true',
            'cancel_url' => $base_url . '/activate_account.php?cancel=true'
        ]
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $access_token['access_token'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => 'Error:' . curl_error($ch)];
    }
    curl_close($ch);
    
    return json_decode($result, true);
}

// 捕获支付
function capturePayment($order_id) {
    global $api_url, $conn;
    
    $access_token = getAccessToken();
    if (isset($access_token['error'])) {
        return $access_token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders/' . $order_id . '/capture');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $access_token['access_token'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => 'Error:' . curl_error($ch)];
    }
    curl_close($ch);
    
    $response = json_decode($result, true);
    
    // 检查是否有错误
    if (isset($response['error'])) {
        return ['error' => $response['error']['message'] ?? 'Unknown error occurred'];
    }
    
    // 确保响应包含必要的字段
    if (!isset($response['id']) || !isset($response['status'])) {
        return ['error' => 'Invalid response from PayPal'];
    }
    
    // 检查支付状态
    if ($response['status'] !== 'COMPLETED') {
        return ['error' => 'Payment not completed'];
    }
    
    // 返回订单ID以便前端可以使用
    return ['id' => $response['id'], 'status' => $response['status']];
}

// 处理API请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    // 记录请求信息，帮助调试
    error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
    error_log("GET params: " . json_encode($_GET));
    
    // 尝试从多个来源获取数据
    $request_data = [];
    
    // 从POST正文获取JSON数据
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $json_data = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
            $request_data = array_merge($request_data, $json_data);
        }
        error_log("Raw input: " . $input);
    }
    
    // 合并POST数组数据
    if (!empty($_POST)) {
        $request_data = array_merge($request_data, $_POST);
        error_log("POST data: " . json_encode($_POST));
    }
    
    // 合并GET参数
    if (!empty($_GET)) {
        $request_data = array_merge($request_data, $_GET);
    }
    
    if (isset($_GET['action'])) {
        // 创建订单
        if ($_GET['action'] === 'create_order') {
            // 检查用户是否已登录或有临时会话
            $user_id = 0;
            
            if (isset($_SESSION['id'])) {
                $user_id = $_SESSION['id'];
            } elseif (isset($_SESSION['temp_id'])) {
                $user_id = $_SESSION['temp_id'];
            }
            
            if ($user_id == 0) {
                echo json_encode(['error' => 'User not logged in']);
                exit;
            }
            
            // 检查用户是否已激活
            $sql = "SELECT is_activated FROM users WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $is_activated);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);
                
                if ($is_activated) {
                    echo json_encode(['error' => 'Account already activated']);
                    exit;
                }
            }
            
            // 创建PayPal订单
            $result = createActivationOrder($user_id);
            echo json_encode($result);
        }
        // 捕获支付
        else if ($_GET['action'] === 'capture_payment') {
            // 从URL参数或请求数据中获取order_id
            $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';
            
            // 如果URL中没有，尝试从请求数据中获取
            if (empty($order_id) && isset($request_data['order_id'])) {
                $order_id = $request_data['order_id'];
            }
            
            error_log("Capture payment for order_id: " . $order_id);
            
            if (!empty($order_id)) {
                // 捕获支付
                $capture_result = capturePayment($order_id);
                
                // 如果支付成功，更新用户激活状态
                if (isset($capture_result['id'])) {
                    // 获取用户ID
                    $user_id = 0;
                    
                    if (isset($_SESSION['id'])) {
                        $user_id = $_SESSION['id'];
                    } elseif (isset($_SESSION['temp_id'])) {
                        $user_id = $_SESSION['temp_id'];
                    }
                    
                    if ($user_id > 0) {
                        // 更新用户激活状态
                        $update_sql = "UPDATE users SET is_activated = 1, activation_payment_id = ?, balance = ? WHERE id = ?";
                        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                            // 添加激活费用到用户余额
                            $user_balance = $activation_fee;
                            mysqli_stmt_bind_param($update_stmt, "sdi", $capture_result['id'], $user_balance, $user_id);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                            
                            // 获取用户邮箱
                            $email = '';
                            $email_source = 'session';
                            
                            if (isset($_SESSION['email'])) {
                                $email = $_SESSION['email'];
                            } elseif (isset($_SESSION['temp_email'])) {
                                $email = $_SESSION['temp_email'];
                            } else {
                                // 从数据库获取用户邮箱
                                $email_sql = "SELECT email FROM users WHERE id = ?";
                                if ($email_stmt = mysqli_prepare($conn, $email_sql)) {
                                    mysqli_stmt_bind_param($email_stmt, "i", $user_id);
                                    mysqli_stmt_execute($email_stmt);
                                    mysqli_stmt_bind_result($email_stmt, $email);
                                    mysqli_stmt_fetch($email_stmt);
                                    mysqli_stmt_close($email_stmt);
                                }
                            }
                            
                            // 确保使用完整的PayPal订单ID
                            error_log("Account activation complete order ID: " . $order_id);
                            error_log("Account activation complete transaction ID: " . $capture_result['id']);
                            
                            // 将激活费用记录到purchases表
                            $insert_sql = "INSERT INTO purchases (user_id, email, email_source, product_id, order_id, 
                                          transaction_id, is_photo_pack, amount, purchase_date, email_sent, purchase_type) 
                                          VALUES (?, ?, ?, 0, ?, ?, 0, ?, NOW(), 1, 'activation')";
                            
                            if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                                $transaction_id = $capture_result['id'];
                                mysqli_stmt_bind_param(
                                    $insert_stmt, 
                                    "issssd", 
                                    $user_id, 
                                    $email, 
                                    $email_source, 
                                    $order_id, 
                                    $transaction_id, 
                                    $activation_fee
                                );
                                mysqli_stmt_execute($insert_stmt);
                                mysqli_stmt_close($insert_stmt);
                                
                                error_log("Account activation payment recorded in purchases table. User ID: $user_id, Order ID: $order_id");
                            } else {
                                error_log("Failed to record activation payment in purchases table: " . mysqli_error($conn));
                            }
                            
                            // 返回成功响应
                            echo json_encode([
                                'id' => $capture_result['id'],
                                'status' => $capture_result['status'],
                                'message' => 'Account activated successfully'
                            ]);
                            exit;
                        } else {
                            echo json_encode(['error' => 'Failed to update user activation status']);
                            exit;
                        }
                    } else {
                        echo json_encode(['error' => 'User not found']);
                        exit;
                    }
                } else {
                    // 返回PayPal的错误
                    echo json_encode($capture_result);
                    exit;
                }
            } else {
                echo json_encode(['error' => 'Order ID is required']);
            }
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
    } else {
        echo json_encode(['error' => 'Action is required']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?> 