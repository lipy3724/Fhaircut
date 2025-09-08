<?php
// 余额支付API
header('Content-Type: application/json');

// 开启错误日志
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Balance payment API called: " . $_SERVER['REQUEST_URI']);

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
require_once __DIR__ . '/email_functions.php';

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

// 生成唯一订单ID，确保不是简单的数字
function generateOrderId() {
    $timestamp = microtime(true);
    $random = mt_rand(10000, 99999);
    $user_part = isset($_SESSION['id']) ? $_SESSION['id'] : 'guest';
    return 'BAL_' . $timestamp . '_' . $random . '_' . $user_part;
}

// 处理API请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取请求数据
    $input = file_get_contents('php://input');
    error_log("Request input: " . $input);
    $request_data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }
    
    error_log("Decoded request data: " . print_r($request_data, true));
    
    if (isset($_GET['action'])) {
        error_log("Action: " . $_GET['action']);
        // 创建订单
        if ($_GET['action'] === 'process_payment') {
            // 验证必要参数
            if (!isset($request_data['product_id']) || !isset($request_data['is_photo_pack'])) {
                echo json_encode(['error' => 'Missing required parameters']);
                exit;
            }
            
            $product_id = intval($request_data['product_id']);
            // 将is_photo_pack转换为整数值，确保它不是空字符串
            $is_photo_pack = isset($request_data['is_photo_pack']) && ($request_data['is_photo_pack'] === true || $request_data['is_photo_pack'] === 1) ? 1 : 0;
            
            error_log("Processing payment for product_id: $product_id, is_photo_pack: " . $is_photo_pack);
            
            // 获取产品信息和价格
            $sql = "SELECT title, price, photo_pack_price, paid_video, paid_photos_zip FROM products WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($product = mysqli_fetch_assoc($result)) {
                    // 确定价格
                    $price = $is_photo_pack ? $product['photo_pack_price'] : $product['price'];
                    $product_title = $product['title'];
                    $download_url = $is_photo_pack ? $product['paid_photos_zip'] : $product['paid_video'];
                    
                    error_log("Product found: " . $product_title . ", Price: " . $price . ", User balance: " . $user_balance);
                    
                    // 检查余额是否足够
                    if ($user_balance < $price) {
                        echo json_encode(['error' => 'Insufficient balance']);
                        exit;
                    }
                    
                    // 生成唯一订单ID - 确保足够长且唯一，格式类似PayPal
                    $order_id = generateOrderId();
                    $transaction_id = $order_id;
                    error_log("Generated balance payment order ID: " . $order_id);
                    
                    // 验证订单ID长度
                    if (strlen($order_id) < 15) {
                        error_log("Warning: Generated order_id is too short: " . $order_id);
                        // 添加额外字符确保长度
                        $order_id = $order_id . '_' . uniqid();
                        $transaction_id = $order_id;
                    }
                    
                    // 开始事务
                    mysqli_begin_transaction($conn);
                    error_log("Transaction started");
                    
                    try {
                        // 1. 扣除用户余额
                        $update_balance_sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
                        error_log("Updating user balance: -$price for user $user_id");
                        
                        if ($update_stmt = mysqli_prepare($conn, $update_balance_sql)) {
                            mysqli_stmt_bind_param($update_stmt, "di", $price, $user_id);
                            if (!mysqli_stmt_execute($update_stmt)) {
                                $error = mysqli_stmt_error($update_stmt);
                                error_log("Failed to update balance: $error");
                                throw new Exception("Failed to update user balance: $error");
                            }
                            $affected_rows = mysqli_stmt_affected_rows($update_stmt);
                            error_log("Balance update affected rows: $affected_rows");
                            mysqli_stmt_close($update_stmt);
                        } else {
                            throw new Exception("Failed to prepare balance update statement");
                        }
                        
                        // 2. 记录购买记录
                        $email = $_SESSION['email'] ?? '';
                        $email_source = 'session';
                        
                        error_log("Recording purchase with email: $email, order_id: $order_id");
                        
                        $insert_sql = "INSERT INTO purchases (user_id, email, email_source, product_id, order_id, 
                                      transaction_id, is_photo_pack, amount, purchase_date, email_sent, purchase_type) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, 'balance')";
                        
                        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                            mysqli_stmt_bind_param(
                                $insert_stmt, 
                                "ississid", 
                                $user_id, 
                                $email, 
                                $email_source, 
                                $product_id, 
                                $order_id, 
                                $transaction_id, 
                                $is_photo_pack, 
                                $price
                            );
                            
                            if (!mysqli_stmt_execute($insert_stmt)) {
                                $error = mysqli_stmt_error($insert_stmt);
                                error_log("Failed to record purchase: $error");
                                error_log("SQL: $insert_sql");
                                error_log("Parameters: user_id=$user_id, email=$email, email_source=$email_source, product_id=$product_id, order_id=$order_id, transaction_id=$transaction_id, is_photo_pack=$is_photo_pack, price=$price");
                                throw new Exception("Failed to record purchase: $error");
                            }
                            
                            $purchase_id = mysqli_insert_id($conn);
                            mysqli_stmt_close($insert_stmt);
                        } else {
                            throw new Exception("Failed to prepare purchase record statement");
                        }
                        
                        // 3. 发送确认邮件（成功前不记销量）
                        $username = $_SESSION['username'] ?? 'Customer';
                        $email_sent = false;
                        
                        // 邮箱是交付凭据，缺少邮箱则直接失败并回滚
                        if (empty($email)) {
                            throw new Exception("No email found for the user; unable to deliver purchase. No charge has been made.");
                        }

                            // 确保产品数组包含id键
                            $product['id'] = $product_id;
                            
                        // 发送邮件，失败则抛出异常（整笔交易回滚，不扣款、不记销量）
                            $email_sent = sendPurchaseConfirmationEmail(
                                $email,
                                $username,
                                $product,
                                $order_id,
                                $is_photo_pack
                            );

                        if (!$email_sent) {
                            throw new Exception("Failed to send confirmation email - rate limited. Your balance was not charged. Please wait 30 seconds before trying again.");
                        }
                            
                            // 更新邮件发送状态
                                $update_email_sql = "UPDATE purchases SET email_sent = 1 WHERE id = ?";
                                if ($update_email_stmt = mysqli_prepare($conn, $update_email_sql)) {
                                    mysqli_stmt_bind_param($update_email_stmt, "i", $purchase_id);
                                    mysqli_stmt_execute($update_email_stmt);
                                    mysqli_stmt_close($update_email_stmt);
                                }

                        // 4. 在邮件成功后再更新产品销量
                        $update_sales_sql = "UPDATE products SET sales = sales + 1 WHERE id = ?";
                        if ($update_sales_stmt = mysqli_prepare($conn, $update_sales_sql)) {
                            mysqli_stmt_bind_param($update_sales_stmt, 'i', $product_id);
                            mysqli_stmt_execute($update_sales_stmt);
                            mysqli_stmt_close($update_sales_stmt);
                        }
                        
                        // 提交事务
                        mysqli_commit($conn);
                        
                        // 返回成功响应
                        echo json_encode([
                            'success' => true,
                            'order_id' => $order_id,
                            'message' => 'Payment successful',
                            'email_sent' => $email_sent,
                            'remaining_balance' => $user_balance - $price
                        ]);
                        exit;
                        
                    } catch (Exception $e) {
                        // 回滚事务
                        mysqli_rollback($conn);
                        echo json_encode(['error' => $e->getMessage()]);
                        exit;
                    }
                    
                } else {
                    echo json_encode(['error' => 'Product not found']);
                    exit;
                }
                
                mysqli_stmt_close($stmt);
            } else {
                echo json_encode(['error' => 'Failed to get product information']);
                exit;
            }
        } else if ($_GET['action'] === 'process_hair_payment') {
            handleHairBalancePayment();
        } else if ($_GET['action'] === 'process_hair_googlepay') {
            handleHairGooglePay();
        } else if ($_GET['action'] === 'process_hair_applepay') {
            handleHairApplePay();
        } else {
            echo json_encode(['error' => 'Invalid action']);
            exit;
        }
    } else {
        echo json_encode(['error' => 'Action is required']);
        exit;
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// 处理头发余额支付
function handleHairBalancePayment() {
    global $conn, $user_id, $user_balance;
    
    // 引入头发邮件发送功能
    require_once 'hair_email_functions.php';
    
    // 获取请求数据
    $request_body = file_get_contents('php://input');
    $request_data = json_decode($request_body, true);
    
    $hair_id = $request_data['hair_id'] ?? 0;
    
    if (!$hair_id) {
        echo json_encode(['error' => 'Hair ID is required']);
        return;
    }
    
    // 获取头发信息
    $sql = "SELECT title, value FROM hair WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $hair_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($hair = mysqli_fetch_assoc($result)) {
            $price = floatval($hair['value']);
            
            if ($price <= 0) {
                echo json_encode(['error' => 'This hair is not available for purchase']);
                mysqli_stmt_close($stmt);
                return;
            }
            
            // 检查余额是否足够
            if ($user_balance < $price) {
                echo json_encode(['error' => 'Insufficient balance']);
                mysqli_stmt_close($stmt);
                return;
            }
            
            // 获取用户信息
            $email = $_SESSION['email'] ?? '';
            $username = $_SESSION['username'] ?? 'Customer';
            $email_source = 'session';
            
            // 检查是否有邮箱（邮件发送必需）
            if (empty($email)) {
                echo json_encode(['error' => 'No email found for the user; unable to send confirmation email']);
                mysqli_stmt_close($stmt);
                return;
            }
            
            // 开始数据库事务
            mysqli_autocommit($conn, false);
            
            try {
                // 1. 扣除用户余额
                $new_balance = $user_balance - $price;
                $update_balance_sql = "UPDATE users SET balance = ? WHERE id = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_balance_sql)) {
                    mysqli_stmt_bind_param($update_stmt, 'di', $new_balance, $user_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                } else {
                    throw new Exception("Failed to update user balance");
                }
                
                // 2. 生成订单ID
                $order_id = 'HAIR_BAL_' . time() . '_' . $user_id . '_' . $hair_id;
                
                // 3. 创建hair_purchases表（如果不存在）
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
                    purchase_type VARCHAR(50) DEFAULT 'balance',
                    INDEX(user_id),
                    INDEX(hair_id),
                    INDEX(order_id)
                )";
                mysqli_query($conn, $create_table_sql);
                
                // 4. 先尝试发送邮件
                $hair_info = [
                    'id' => $hair_id,
                    'title' => $hair['title'],
                    'value' => $price
                ];
                
                $email_sent = sendHairPurchaseEmail($email, $username, $hair_info, $order_id, $price, 'balance');
                
                if (!$email_sent) {
                    throw new Exception("Failed to send confirmation email - rate limited. Your balance was not charged. Please wait 30 seconds before trying again.");
                }
                
                // 5. 邮件发送成功后保存购买记录
                $insert_sql = "INSERT INTO hair_purchases (user_id, email, email_source, hair_id, order_id, transaction_id, amount, purchase_date, email_sent, purchase_type) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, 'balance')";
                              
                if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, "ississd", $user_id, $email, $email_source, $hair_id, $order_id, $order_id, $price);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $purchase_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($insert_stmt);
                        
                        // 6. 提交事务
                        mysqli_commit($conn);
                        
                        error_log("Hair balance purchase successful: Order ID: $order_id, User: $user_id, Email sent: " . ($email_sent ? 'Yes' : 'No'));
                        
                        // 返回成功响应
                        echo json_encode([
                            'success' => true,
                            'order_id' => $order_id,
                            'message' => 'Hair purchase successful',
                            'email_sent' => $email_sent,
                            'remaining_balance' => $new_balance
                        ]);
                        
                    } else {
                        throw new Exception("Failed to save purchase record");
                    }
                } else {
                    throw new Exception("Failed to prepare purchase insert statement");
                }
                
            } catch (Exception $e) {
                // 回滚事务
                mysqli_rollback($conn);
                error_log("Hair balance payment failed: " . $e->getMessage());
                echo json_encode(['error' => $e->getMessage()]);
            }
            
        } else {
            echo json_encode(['error' => 'Hair not found']);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
}

// 处理头发Google Pay支付
function handleHairGooglePay() {
    global $conn, $user_id;
    
    // 引入头发邮件发送功能
    require_once 'hair_email_functions.php';
    
    // 获取请求数据
    $request_body = file_get_contents('php://input');
    $request_data = json_decode($request_body, true);
    
    $hair_id = $request_data['hair_id'] ?? 0;
    $payment_data = $request_data['payment_data'] ?? null;
    
    if (!$hair_id || !$payment_data) {
        echo json_encode(['error' => 'Hair ID and payment data are required']);
        return;
    }
    
    // 这里应该验证Google Pay的支付数据
    // 实际项目中需要调用Google Pay的验证API
    // 目前简化处理，假设支付验证成功
    
    // 获取头发信息
    $sql = "SELECT title, value FROM hair WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $hair_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($hair = mysqli_fetch_assoc($result)) {
            $price = floatval($hair['value']);
            
            if ($price <= 0) {
                echo json_encode(['error' => 'This hair is not available for purchase']);
                mysqli_stmt_close($stmt);
                return;
            }
            
            // 获取用户信息
            $email = $_SESSION['email'] ?? '';
            $username = $_SESSION['username'] ?? 'Customer';
            $email_source = 'session';
            
            // 检查是否有邮箱（邮件发送必需）
            if (empty($email)) {
                echo json_encode(['error' => 'No email found for the user; unable to send confirmation email']);
                mysqli_stmt_close($stmt);
                return;
            }
            
            // 生成订单ID
            $order_id = 'HAIR_GPAY_' . time() . '_' . $user_id . '_' . $hair_id;
            
            // 开始数据库事务
            mysqli_autocommit($conn, false);
            
            try {
                // 1. 先尝试发送邮件
                $hair_info = [
                    'id' => $hair_id,
                    'title' => $hair['title'],
                    'value' => $price
                ];
                
                $email_sent = sendHairPurchaseEmail($email, $username, $hair_info, $order_id, $price, 'googlepay');
                
                if (!$email_sent) {
                    throw new Exception("Failed to send confirmation email - rate limited. Please wait 30 seconds before trying again.");
                }
                
                // 2. 创建hair_purchases表（如果不存在）
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
                    purchase_type VARCHAR(50) DEFAULT 'googlepay',
                    INDEX(user_id),
                    INDEX(hair_id),
                    INDEX(order_id)
                )";
                mysqli_query($conn, $create_table_sql);
                
                // 3. 邮件发送成功后保存购买记录
                $insert_sql = "INSERT INTO hair_purchases (user_id, email, email_source, hair_id, order_id, transaction_id, amount, purchase_date, email_sent, purchase_type) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, 'googlepay')";
                              
                if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, "ississd", $user_id, $email, $email_source, $hair_id, $order_id, $order_id, $price);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $purchase_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($insert_stmt);
                        
                        // 4. 提交事务
                        mysqli_commit($conn);
                        
                        error_log("Hair Google Pay purchase successful: Order ID: $order_id, User: $user_id, Email sent: Yes");
                        
                        echo json_encode([
                            'success' => true,
                            'order_id' => $order_id,
                            'message' => 'Google Pay hair purchase successful',
                            'email_sent' => $email_sent
                        ]);
                        
                    } else {
                        throw new Exception("Failed to save purchase record");
                    }
                } else {
                    throw new Exception("Failed to prepare purchase insert statement");
                }
                
            } catch (Exception $e) {
                // 回滚事务
                mysqli_rollback($conn);
                error_log("Hair Google Pay payment failed: " . $e->getMessage());
                echo json_encode(['error' => $e->getMessage()]);
            }
            
        } else {
            echo json_encode(['error' => 'Hair not found']);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
}

// 处理头发Apple Pay支付
function handleHairApplePay() {
    global $conn, $user_id;
    
    // 引入头发邮件发送功能
    require_once 'hair_email_functions.php';
    
    // 获取请求数据
    $request_body = file_get_contents('php://input');
    $request_data = json_decode($request_body, true);
    
    $hair_id = $request_data['hair_id'] ?? 0;
    $payment_data = $request_data['payment_data'] ?? null;
    
    if (!$hair_id || !$payment_data) {
        echo json_encode(['error' => 'Hair ID and payment data are required']);
        return;
    }
    
    // 这里应该验证Apple Pay的支付数据
    // 实际项目中需要调用Apple Pay的验证API
    // 目前简化处理，假设支付验证成功
    
    // 获取头发信息
    $sql = "SELECT title, value FROM hair WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $hair_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($hair = mysqli_fetch_assoc($result)) {
            $price = floatval($hair['value']);
            
            if ($price <= 0) {
                echo json_encode(['error' => 'This hair is not available for purchase']);
                mysqli_stmt_close($stmt);
                return;
            }
            
            // 获取用户信息
            $email = $_SESSION['email'] ?? '';
            $username = $_SESSION['username'] ?? 'Customer';
            $email_source = 'session';
            
            // 检查是否有邮箱（邮件发送必需）
            if (empty($email)) {
                echo json_encode(['error' => 'No email found for the user; unable to send confirmation email']);
                mysqli_stmt_close($stmt);
                return;
            }
            
            // 生成订单ID
            $order_id = 'HAIR_APAY_' . time() . '_' . $user_id . '_' . $hair_id;
            
            // 开始数据库事务
            mysqli_autocommit($conn, false);
            
            try {
                // 1. 先尝试发送邮件
                $hair_info = [
                    'id' => $hair_id,
                    'title' => $hair['title'],
                    'value' => $price
                ];
                
                $email_sent = sendHairPurchaseEmail($email, $username, $hair_info, $order_id, $price, 'applepay');
                
                if (!$email_sent) {
                    throw new Exception("Failed to send confirmation email - rate limited. Please wait 30 seconds before trying again.");
                }
                
                // 2. 创建hair_purchases表（如果不存在）
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
                    purchase_type VARCHAR(50) DEFAULT 'applepay',
                    INDEX(user_id),
                    INDEX(hair_id),
                    INDEX(order_id)
                )";
                mysqli_query($conn, $create_table_sql);
                
                // 3. 邮件发送成功后保存购买记录
                $insert_sql = "INSERT INTO hair_purchases (user_id, email, email_source, hair_id, order_id, transaction_id, amount, purchase_date, email_sent, purchase_type) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, 'applepay')";
                              
                if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, "ississd", $user_id, $email, $email_source, $hair_id, $order_id, $order_id, $price);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $purchase_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($insert_stmt);
                        
                        // 4. 提交事务
                        mysqli_commit($conn);
                        
                        error_log("Hair Apple Pay purchase successful: Order ID: $order_id, User: $user_id, Email sent: Yes");
                        
                        echo json_encode([
                            'success' => true,
                            'order_id' => $order_id,
                            'message' => 'Apple Pay hair purchase successful',
                            'email_sent' => $email_sent
                        ]);
                        
                    } else {
                        throw new Exception("Failed to save purchase record");
                    }
                } else {
                    throw new Exception("Failed to prepare purchase insert statement");
                }
                
            } catch (Exception $e) {
                // 回滚事务
                mysqli_rollback($conn);
                error_log("Hair Apple Pay payment failed: " . $e->getMessage());
                echo json_encode(['error' => $e->getMessage()]);
            }
            
        } else {
            echo json_encode(['error' => 'Hair not found']);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
}
?> 