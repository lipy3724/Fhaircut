<?php
session_start();
require_once "db_config.php";
require_once "env.php";

// 检查用户是否已登录但未激活
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $user_id = $_SESSION["id"];
    $username = $_SESSION["username"];
    $email = isset($_SESSION["email"]) ? $_SESSION["email"] : "";
} elseif (isset($_SESSION["temp_id"])) {
    // 使用临时会话变量
    $user_id = $_SESSION["temp_id"];
    $username = $_SESSION["temp_username"];
    $email = isset($_SESSION["temp_email"]) ? $_SESSION["temp_email"] : "";
} else {
    header("location: login.php");
    exit;
}

// 如果用户已经激活，重定向到主页
$sql = "SELECT is_activated FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $is_activated);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if ($is_activated) {
        header("location: main.php");
        exit;
    }
}

// 处理支付成功回调
if (isset($_GET['success']) && $_GET['success'] === 'true' && isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // 更新用户激活状态
    $update_sql = "UPDATE users SET is_activated = 1, activation_payment_id = ? WHERE id = ?";
    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "si", $order_id, $user_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // 设置成功消息
        $_SESSION["activation_success"] = true;
        
        // 设置正式登录会话变量
        $_SESSION["loggedin"] = true;
        $_SESSION["id"] = $user_id;
        $_SESSION["username"] = $username;
        $_SESSION["email"] = $email;
        
        // 获取用户角色
        $role_sql = "SELECT role FROM users WHERE id = ?";
        if ($role_stmt = mysqli_prepare($conn, $role_sql)) {
            mysqli_stmt_bind_param($role_stmt, "i", $user_id);
            mysqli_stmt_execute($role_stmt);
            mysqli_stmt_bind_result($role_stmt, $role);
            mysqli_stmt_fetch($role_stmt);
            mysqli_stmt_close($role_stmt);
            
            $_SESSION["role"] = $role;
        }
        
        // 清除临时会话变量
        unset($_SESSION["temp_id"]);
        unset($_SESSION["temp_username"]);
        unset($_SESSION["temp_email"]);
        
        // 重定向到主页
        header("location: main.php");
        exit;
    }
}

// 激活费用（美元）
$activation_fee = 100.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Your Account - HairCut Network</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .activation-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .activation-header {
            margin-bottom: 30px;
        }
        .activation-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .activation-header p {
            color: #666;
            font-size: 16px;
        }
        .activation-details {
            margin: 30px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .activation-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .activation-details .detail-row:last-child {
            border-bottom: none;
            font-weight: bold;
        }
        .activation-details .label {
            text-align: left;
            color: #555;
        }
        .activation-details .value {
            text-align: right;
            color: #333;
        }
        .activation-details .highlight {
            color: #B8B5E1;
            font-weight: bold;
            font-size: 18px;
        }
        .paypal-container {
            margin: 30px 0;
        }
        .note {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }
    </style>
    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo env('PAYPAL_CLIENT_ID'); ?>&currency=USD&components=buttons"></script>
</head>
<body>
    <div class="activation-container">
        <div class="activation-header">
            <h1>Congratulations! Registration Success!</h1>
            <p>Select Payment Method, Activate your account!</p>
            <p><strong>【The account contains USD 100】</strong></p>
        </div>
        
        <div class="activation-details">
            <div class="detail-row">
                <div class="label">Username:</div>
                <div class="value"><?php echo htmlspecialchars($username); ?></div>
            </div>
            <div class="detail-row">
                <div class="label">Payment amount:</div>
                <div class="value highlight">USD <?php echo number_format($activation_fee, 2); ?></div>
            </div>
        </div>
        
        <div class="paypal-container" id="paypal-button-container"></div>
        <div id="payment-message" style="margin-top: 20px; padding: 10px; border-radius: 5px; display: none;"></div>
        
        <div class="note">
            <p>Note: You must activate your account to access all features of our website. Without activation, you can only browse as a guest.</p>
        </div>
    </div>
    
    <script>
        // PayPal集成
        paypal.Buttons({
            // 设置交易
            createOrder: function(data, actions) {
                // 显示处理消息
                document.getElementById('payment-message').style.display = 'block';
                document.getElementById('payment-message').style.backgroundColor = '#e2f0ff';
                document.getElementById('payment-message').innerHTML = '<p>Processing your payment request...</p>';
                
                return fetch('account_activation_api.php?action=create_order', {
                    method: 'post',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                }).then(function(res) {
                    if (!res.ok) {
                        throw new Error('Network response was not ok: ' + res.status);
                    }
                    return res.text().then(function(text) {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Invalid server response: ' + text.substring(0, 100));
                        }
                    });
                }).then(function(orderData) {
                    console.log("Create order response:", orderData);
                    
                    if (orderData.error) {
                        document.getElementById('payment-message').style.backgroundColor = '#f8d7da';
                        document.getElementById('payment-message').innerHTML = '<p>Error: ' + orderData.error + '</p>';
                        throw new Error(orderData.error);
                    }
                    
                    if (!orderData.id) {
                        document.getElementById('payment-message').style.backgroundColor = '#f8d7da';
                        document.getElementById('payment-message').innerHTML = '<p>Error: No order ID returned from server</p>';
                        throw new Error('No order ID returned from server');
                    }
                    
                    return orderData.id;
                }).catch(function(err) {
                    console.error('Create order error:', err);
                    document.getElementById('payment-message').style.backgroundColor = '#f8d7da';
                    document.getElementById('payment-message').innerHTML = '<p>Error: Unable to process your request. Please try again. Details: ' + err.message + '</p>';
                    throw err;
                });
            },
            
            // 完成交易
            onApprove: function(data, actions) {
                document.getElementById('payment-message').style.backgroundColor = '#e2f0ff';
                document.getElementById('payment-message').innerHTML = '<p>Finalizing your payment...</p>';
                
                console.log("Payment approved, order ID:", data.orderID);
                
                return fetch('account_activation_api.php?action=capture_payment&order_id=' + data.orderID, {
                    method: 'post',
                    credentials: 'same-origin'
                }).then(function(res) {
                    if (!res.ok) {
                        throw new Error('Network response was not ok: ' + res.status);
                    }
                    return res.json();
                }).then(function(orderData) {
                    console.log("Capture response:", orderData);
                    
                    if (orderData.error) {
                        document.getElementById('payment-message').style.backgroundColor = '#f8d7da';
                        document.getElementById('payment-message').innerHTML = '<p>Error: ' + orderData.error + '</p>';
                        return;
                    }
                    
                    // 显示成功消息
                    document.getElementById('payment-message').style.backgroundColor = '#d4edda';
                    document.getElementById('payment-message').innerHTML = '<p><strong>Success!</strong> Your account has been activated. Redirecting to homepage...</p>';
                    
                    // 重定向到带有成功参数的URL
                    setTimeout(function() {
                        window.location.href = 'activate_account.php?success=true&order_id=' + data.orderID;
                    }, 3000); // 3秒后重定向
                }).catch(function(err) {
                    console.error('Capture error:', err);
                    document.getElementById('payment-message').style.backgroundColor = '#f8d7da';
                    document.getElementById('payment-message').innerHTML = '<p>Error: Unable to complete your payment. Please try again. Details: ' + err.message + '</p>';
                });
            },
            
            onCancel: function() {
                document.getElementById('payment-message').style.display = 'block';
                document.getElementById('payment-message').style.backgroundColor = '#fff3cd';
                document.getElementById('payment-message').innerHTML = '<p>Payment cancelled. You can try again when you are ready.</p>';
            },
            
            onError: function(err) {
                document.getElementById('payment-message').style.display = 'block';
                document.getElementById('payment-message').style.backgroundColor = '#f8d7da';
                document.getElementById('payment-message').innerHTML = '<p>Error: Something went wrong with your payment. Please try again.</p>';
                console.error('Error:', err);
            }
        }).render('#paypal-button-container');
    </script>
</body>
</html> 