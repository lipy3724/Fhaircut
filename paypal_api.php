<?php
// PayPal API处理
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
require_once __DIR__ . '/email_functions.php';

// 使用环境变量获取PayPal API凭据
$paypal_client_id = env('PAYPAL_CLIENT_ID');
$paypal_client_secret = env('PAYPAL_CLIENT_SECRET');
$paypal_sandbox = env('PAYPAL_SANDBOX', true);

// 获取应用URL和端口
$app_url = env('APP_URL', 'http://localhost');
$app_port = env('APP_PORT', '8082');

// 清理URL，移除可能的注释
$app_url = preg_replace('/#.*$/', '', $app_url);
$app_url = trim($app_url);

$base_url = $app_port == '80' ? $app_url : $app_url . ':' . $app_port;

// PayPal API URL
$api_url = $paypal_sandbox 
    ? 'https://api-m.sandbox.paypal.com' 
    : 'https://api-m.paypal.com';

// 获取访问令牌
function getAccessToken() {
    global $api_url, $paypal_client_id, $paypal_client_secret;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $paypal_client_id . ':' . $paypal_client_secret);
    
    // SSL证书验证设置
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem'); // 使用本地证书文件
    
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
function createOrder($product_id, $product_name, $price, $currency = 'USD') {
    global $api_url, $base_url;
    
    $access_token = getAccessToken();
    if (isset($access_token['error'])) {
        return $access_token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    // SSL证书验证设置
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem'); // 使用本地证书文件
    
    $payload = json_encode([
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'product_' . $product_id,
                'description' => $product_name,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $price
                ]
            ]
        ],
        'application_context' => [
            'return_url' => $base_url . '/product_detail.php?id=' . $product_id . '&success=true',
            'cancel_url' => $base_url . '/product_detail.php?id=' . $product_id . '&cancel=true'
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
    global $api_url;
    
    $access_token = getAccessToken();
    if (isset($access_token['error'])) {
        return $access_token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders/' . $order_id . '/capture');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    
    // SSL证书验证设置
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem'); // 使用本地证书文件
    
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

// 处理API请求
$request_body = file_get_contents('php://input');
$request_data = json_decode($request_body, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'create_order') {
        // 从数据库获取产品信息
        $product_id = $request_data['product_id'] ?? 0;
        $is_photo_pack = $request_data['is_photo_pack'] ?? false;
        $custom_amount = $request_data['amount'] ?? null; // 获取前端传递的金额
        
        // 记录请求信息
        error_log("Create order request: product_id=$product_id, is_photo_pack=" . ($is_photo_pack ? 'true' : 'false') . ", custom_amount=" . ($custom_amount ?? 'NULL'));
        
        // 查询产品信息
        $sql = "SELECT title, price, photo_pack_price FROM products WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                $product_name = $row['title'];
                
                // 如果前端传递了金额，使用该金额，否则从数据库获取
                if ($custom_amount !== null) {
                    $price = $custom_amount;
                    error_log("Using custom amount from frontend: $price");
                } else {
                    $price = $is_photo_pack ? $row['photo_pack_price'] : $row['price'];
                    error_log("Using database price: $price (is_photo_pack: " . ($is_photo_pack ? 'true' : 'false') . ")");
                }
                
                // 创建PayPal订单
                $order_result = createOrder($product_id, $product_name, $price);
                echo json_encode($order_result);
            } else {
                error_log("Product not found for ID: $product_id");
                echo json_encode(['error' => 'Product not found']);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
    } elseif ($action === 'create_hair_order') {
        // 创建头发购买订单
        $hair_id = $request_data['hair_id'] ?? 0;
        
        error_log("Create hair order request: hair_id=$hair_id");
        
        // 查询头发信息
        $sql = "SELECT title, value FROM hair WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $hair_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                $hair_name = $row['title'];
                $price = $row['value'];
                
                if ($price > 0) {
                    // 创建PayPal订单
                    $order_result = createOrder($hair_id, $hair_name, $price);
                    echo json_encode($order_result);
                } else {
                    echo json_encode(['error' => 'This hair is not available for purchase']);
                }
            } else {
                echo json_encode(['error' => 'Hair not found']);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
        
    } elseif ($action === 'capture_hair_payment') {
        // 处理头发购买的支付捕获
        require_once 'hair_email_functions.php';
        
        $order_id = $request_data['order_id'] ?? '';
        $hair_id = $request_data['hair_id'] ?? 0;
        
        if ($order_id && $hair_id) {
            // 捕获支付
            $capture_result = capturePayment($order_id);
            
            // 如果支付成功，保存购买记录并发送邮件
            if (isset($capture_result['status']) && $capture_result['status'] === 'COMPLETED') {
                $transaction_id = $capture_result['id'] ?? '';
                $amount = isset($capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value']) 
                    ? floatval($capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value']) 
                    : 0;
                
                // 获取用户信息
                $user_id = $_SESSION['id'] ?? null;
                $email = $_SESSION['email'] ?? '';
                $username = $_SESSION['username'] ?? 'Customer';
                $email_source = 'session';
                
                // 如果用户未登录，尝试从PayPal获取邮箱
                if (empty($email) && isset($capture_result['payer']['email_address'])) {
                    $email = $capture_result['payer']['email_address'];
                    $username = $capture_result['payer']['name']['given_name'] ?? 'Customer';
                    $email_source = 'paypal';
                    error_log("Using PayPal email for hair purchase: $email");
                }
                
                if (!empty($email)) {
                    // 获取头发信息用于邮件
                    $hair_sql = "SELECT title, value FROM hair WHERE id = ?";
                    if ($hair_stmt = mysqli_prepare($conn, $hair_sql)) {
                        mysqli_stmt_bind_param($hair_stmt, 'i', $hair_id);
                        mysqli_stmt_execute($hair_stmt);
                        $hair_result = mysqli_stmt_get_result($hair_stmt);
                        
                        if ($hair_data = mysqli_fetch_assoc($hair_result)) {
                            $hair_info = [
                                'id' => $hair_id,
                                'title' => $hair_data['title'],
                                'value' => $amount
                            ];
                            
                            // 先尝试发送邮件
                            $email_sent = sendHairPurchaseEmail($email, $username, $hair_info, $order_id, $amount, 'paypal');
                            
                            if (!$email_sent) {
                                error_log("Failed to send hair purchase confirmation email to: " . $email . " for order ID: " . $order_id);
                                // 邮件发送失败，返回错误信息，不进行支付捕获
                                echo json_encode([
                                    'error' => 'Failed to send purchase confirmation email. Payment was not completed. Please try again later.',
                                    'email_failed' => true
                                ]);
                                return;
                            }
                            
                            // 邮件发送成功，保存购买记录
                            // 创建hair_purchases表（如果不存在）
                            $create_table_sql = "CREATE TABLE IF NOT EXISTS hair_purchases (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                user_id INT,
                                email VARCHAR(255),
                                email_source VARCHAR(50),
                                hair_id INT,
                                order_id VARCHAR(255),
                                transaction_id VARCHAR(255),
                                amount DECIMAL(10,2),
                                purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                email_sent BOOLEAN DEFAULT 0,
                                purchase_type VARCHAR(50) DEFAULT 'paypal',
                                INDEX(user_id),
                                INDEX(hair_id),
                                INDEX(order_id)
                            )";
                            mysqli_query($conn, $create_table_sql);
                            
                            // 保存头发购买记录到数据库
                            $insert_sql = "INSERT INTO hair_purchases (user_id, email, email_source, hair_id, order_id, transaction_id, amount, purchase_date, email_sent, purchase_type) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, 'paypal')";
                                          
                            if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                                mysqli_stmt_bind_param($insert_stmt, "ississd", $user_id, $email, $email_source, $hair_id, $order_id, $transaction_id, $amount);
                                
                                if (mysqli_stmt_execute($insert_stmt)) {
                                    $purchase_id = mysqli_insert_id($conn);
                                    error_log("Hair PayPal purchase successful: Order ID: $order_id, Purchase ID: $purchase_id, Email sent: Yes");
                                    mysqli_stmt_close($insert_stmt);
                                } else {
                                    error_log("Error saving hair purchase record: " . mysqli_error($conn));
                                }
                            }
                        }
                        mysqli_stmt_close($hair_stmt);
                    }
                } else {
                    error_log("No email available for hair purchase confirmation");
                }
            }
            
            echo json_encode($capture_result);
        } else {
            echo json_encode(['error' => 'Order ID and Hair ID are required']);
        }
        
    } elseif ($action === 'capture_payment') {
        $order_id = $request_data['order_id'] ?? '';
        
        if (!empty($order_id)) {
            // 记录接收到的订单ID格式
            error_log("Received order_id for capture: " . $order_id . " (length: " . strlen($order_id) . ")");
            
            // 确保订单ID格式正确 - PayPal订单ID通常是以字母开头的长字符串
            if (empty($order_id) || strlen($order_id) < 5) {
                error_log("Error: Invalid or too short order_id received: " . $order_id);
                echo json_encode(['error' => 'Invalid order ID format']);
                exit;
            }
            
            $capture_result = capturePayment($order_id);
            
            // 记录完整的订单ID
            error_log("PayPal complete order ID: " . $order_id);
            
            // 如果支付成功，先检查是否能获取到必要信息
            if (isset($capture_result['status']) && $capture_result['status'] === 'COMPLETED') {
                // 从捕获结果中获取产品ID和类型
                $reference_id = $capture_result['purchase_units'][0]['reference_id'] ?? '';
                $product_id = 0;
                
                if (strpos($reference_id, 'product_') === 0) {
                    $product_id = intval(substr($reference_id, 8));
                }
                
                // 获取交易ID和金额
                $transaction_id = $capture_result['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';
                $amount = $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
                
                // 记录交易ID
                error_log("PayPal transaction ID (将用作订单ID): " . $transaction_id);
                error_log("PayPal transaction ID length: " . strlen($transaction_id));
                error_log("PayPal transaction ID full dump: " . var_export($transaction_id, true));
                
                // 验证交易ID是否有效
                if (empty($transaction_id) || strlen($transaction_id) < 10) {
                    error_log("错误：PayPal交易ID无效或为空: " . $transaction_id);
                    echo json_encode(['error' => 'Invalid transaction ID received from PayPal']);
                    exit;
                }
                
                // 记录session状态
                error_log("Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Not active"));
                error_log("Session data: " . json_encode($_SESSION));
                
                // 获取当前用户信息
                $user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
                $email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
                $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
                
                // 记录用户信息来源
                $email_source = "session";
                
                // 检查是否有用户登录信息
                if (!empty($user_id) && !empty($email)) {
                    // 用户已登录，使用session中的邮箱
                    error_log("Using logged-in user email: $email for user ID: $user_id");
                } 
                // 如果用户未登录或session中没有邮箱，尝试从订单中获取邮箱
                else if (isset($capture_result['payer']['email_address'])) {
                    $email = $capture_result['payer']['email_address'];
                    $username = $capture_result['payer']['name']['given_name'] ?? 'Customer';
                    $email_source = "paypal";
                    error_log("Using PayPal email: $email (user not logged in)");
                } else {
                    error_log("No email available from session or PayPal");
                }
                
                error_log("Purchase email determination: User ID: " . ($user_id ?? 'NULL') . 
                          ", Email: " . $email . 
                          ", Source: " . $email_source);
                
                // 如果有产品ID和邮箱，先尝试发送邮件，成功后再保存购买记录
                if ($product_id > 0 && !empty($email)) {
                    // 确定是否为图片包购买
                    $is_photo_pack = false;
                    
                    // 查询产品详细信息
                    $sql = "SELECT * FROM products WHERE id = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, 'i', $product_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        
                        if ($product = mysqli_fetch_assoc($result)) {
                            // 根据金额判断是视频还是图片包
                            if (abs($amount - $product['photo_pack_price']) < 0.01) {
                                $is_photo_pack = true;
                            }
                            
                            // 检查订单是否已存在
                            $check_sql = "SELECT id FROM purchases WHERE order_id = ? OR transaction_id = ?";
                            $check_stmt = mysqli_prepare($conn, $check_sql);
                            mysqli_stmt_bind_param($check_stmt, 'ss', $order_id, $transaction_id);
                            mysqli_stmt_execute($check_stmt);
                            mysqli_stmt_store_result($check_stmt);
                            $order_exists = mysqli_stmt_num_rows($check_stmt) > 0;
                            mysqli_stmt_close($check_stmt);
                            
                            if ($order_exists) {
                                // 订单已存在，记录日志并返回成功
                                error_log("Order already processed with order ID: " . $order_id . ", skipping duplicate insertion");
                                echo json_encode($capture_result);
                                exit;
                            }
                            
                            // 先尝试发送购买确认邮件，只有成功后才会继续捕获支付
                            $email_sent = sendPurchaseConfirmationEmail(
                                $email, 
                                $username, 
                                $product, 
                                $order_id, // 使用订单ID而不是交易ID
                                $is_photo_pack
                            );
                            
                            // 如果邮件发送失败，记录错误并返回错误信息，不进行捕获支付
                            if (!$email_sent) {
                                error_log("Failed to send purchase confirmation email to: " . $email . " for order ID: " . $order_id);
                                echo json_encode([
                                    'error' => 'Failed to send purchase confirmation email. No payment has been processed. Please try again later.',
                                    'email_failed' => true
                                ]);
                                exit;
                            }
                            
                            // 邮件发送成功，保存购买记录
                            $sql = "INSERT INTO purchases (user_id, email, email_source, product_id, order_id, transaction_id, is_photo_pack, amount, email_sent) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            if ($insert_stmt = mysqli_prepare($conn, $sql)) {
                                // 邮件已成功发送
                                $email_sent_value = 1;
                                
                                mysqli_stmt_bind_param(
                                    $insert_stmt, 
                                    'isssssdii', 
                                    $user_id, 
                                    $email, 
                                    $email_source,
                                    $product_id, 
                                    $order_id, // 使用原始PayPal订单ID作为订单ID
                                    $transaction_id, // 交易ID保持不变
                                    $is_photo_pack, 
                                    $amount,
                                    $email_sent_value
                                );
                                
                                if (mysqli_stmt_execute($insert_stmt)) {
                                    // 记录日志
                                    error_log("Purchase record saved with order ID: " . $order_id . ", transaction ID: " . $transaction_id . ", email sent: " . ($email_sent ? "Yes" : "No"));
                                    
                                    // 更新产品销量
                                    $update_sales_sql = "UPDATE products SET sales = sales + 1 WHERE id = ?";
                                    if ($update_sales_stmt = mysqli_prepare($conn, $update_sales_sql)) {
                                        mysqli_stmt_bind_param($update_sales_stmt, 'i', $product_id);
                                        
                                        if (mysqli_stmt_execute($update_sales_stmt)) {
                                            error_log("Product ID $product_id sales count updated");
                                        } else {
                                            error_log("Failed to update sales count for product ID $product_id: " . mysqli_error($conn));
                                        }
                                        
                                        mysqli_stmt_close($update_sales_stmt);
                                    }
                                } else {
                                    // 记录详细错误信息
                                    $error_message = mysqli_error($conn);
                                    error_log("Error saving purchase record: " . $error_message);
                                    error_log("SQL: INSERT INTO purchases (user_id, email, email_source, product_id, order_id, transaction_id, is_photo_pack, amount, email_sent)");
                                    error_log("Values: user_id=" . ($user_id ?? 'NULL') . 
                                              ", email=" . $email . 
                                              ", email_source=" . $email_source . 
                                              ", product_id=" . $product_id . 
                                              ", order_id=" . $order_id . 
                                              ", transaction_id=" . $transaction_id . 
                                              ", is_photo_pack=" . $is_photo_pack . 
                                              ", amount=" . $amount . 
                                              ", email_sent=" . $email_sent_value);
                                }
                                
                                mysqli_stmt_close($insert_stmt);
                            } else {
                                error_log("Error preparing purchase insert statement: " . mysqli_error($conn));
                            }
                        } else {
                            error_log("Product not found for ID: " . $product_id);
                        }
                        
                        mysqli_stmt_close($stmt);
                    } else {
                        error_log("Error preparing product select statement: " . mysqli_error($conn));
                    }
                } else {
                    error_log("Missing product_id or email for purchase record");
                }
            }
            
            echo json_encode($capture_result);
        } else {
            echo json_encode(['error' => 'Order ID is required']);
        }
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?> 