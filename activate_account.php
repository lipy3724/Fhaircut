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

// 从数据库获取激活页面设置
$activation_settings = array();
$settings_sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('activation_title', 'activation_subtitle', 'activation_button_text', 'activation_fee', 'activation_note')";
$settings_result = mysqli_query($conn, $settings_sql);

if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $activation_settings[$row['setting_key']] = $row['setting_value'];
    }
    mysqli_free_result($settings_result);
    
    // 使用数据库中的设置覆盖默认值
    if (isset($activation_settings['activation_fee']) && is_numeric($activation_settings['activation_fee'])) {
        $activation_fee = (float)$activation_settings['activation_fee'];
    }
}

// 设置默认值
if (!isset($activation_settings['activation_title'])) $activation_settings['activation_title'] = 'Congratulations! Registration Success!';
if (!isset($activation_settings['activation_subtitle'])) $activation_settings['activation_subtitle'] = 'Select Payment Method, Activate your account!';
if (!isset($activation_settings['activation_button_text'])) $activation_settings['activation_button_text'] = 'The account contains USD 100';
if (!isset($activation_settings['activation_note'])) $activation_settings['activation_note'] = 'Note: You must activate your account to access all features of our website. Without activation, you can only browse as a guest.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Your Account - HairCut Network</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 确保页面可以滚动 */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        body {
            min-height: 100vh;
            background: #ffffff;
        }
        
        .activation-container {
            max-width: 600px;
            margin: 20px auto 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        /* 响应式设计 - 移动设备上的边距调整 */
        @media (max-width: 768px) {
            .activation-container {
                margin: 10px;
                padding: 20px;
            }
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
        .payment-methods {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fff;
        }
        
        .paypal-container, 
        .googlepay-container,
        .applepay-container {
            margin: 15px 0;
        }
        
        .applepay-container {
            display: none; /* 默认隐藏，JavaScript会在支持时显示 */
        }
        
        .googlepay-button-container,
        .applepay-button-container {
            min-height: 48px;
            width: 100%;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            z-index: 9999;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-overlay p {
            color: white;
            font-size: 16px;
            margin: 0;
        }
        .note {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }
        .skip-activation-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .skip-activation-btn:hover {
            background-color: #5a6268;
        }
        .skip-activation-container {
            width: 100%;
        }
    </style>
    <!-- PayPal SDK with Google Pay and Apple Pay -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo env('PAYPAL_CLIENT_ID'); ?>&currency=USD&components=buttons,googlepay,applepay&enable-funding=venmo,paylater,card"></script>
    <!-- Google Pay SDK -->
    <script async src="https://pay.google.com/gp/p/js/pay.js"></script>
    <!-- Apple Pay SDK -->
    <script src="https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js"></script>
</head>
<body>
    <div class="activation-container">
        <div class="activation-header">
            <h1><?php echo htmlspecialchars($activation_settings['activation_title']); ?></h1>
            <p><?php echo htmlspecialchars($activation_settings['activation_subtitle']); ?></p>
            <?php if (!empty($activation_settings['activation_button_text'])): ?>
            <p><strong>【<?php echo htmlspecialchars($activation_settings['activation_button_text']); ?>】</strong></p>
            <?php endif; ?>
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
        
        <div class="payment-methods">
            <div class="paypal-container" id="paypal-button-container"></div>
            <div class="googlepay-container">
                <div id="googlepay-button-container" class="googlepay-button-container"></div>
            </div>
            <div class="applepay-container">
                <div id="applepay-button-container" class="applepay-button-container"></div>
            </div>
        </div>
        
        <div id="payment-message" style="margin-top: 20px; padding: 10px; border-radius: 5px; display: none;"></div>
        
        <!-- Skip Activation Button -->
        <div class="skip-activation-container" style="margin: 20px 0;">
            <button type="button" id="skip-activation-btn" class="skip-activation-btn">
                Skip Activation
            </button>
        </div>
        
        <div class="note">
            <p><?php echo htmlspecialchars($activation_settings['activation_note']); ?></p>
        </div>
    </div>
    
    <script>
        // Google Pay 配置和实现
        const baseRequest = {
            apiVersion: 2,
            apiVersionMinor: 0
        };
        
        const baseCardPaymentMethod = {
            type: 'CARD',
            parameters: {
                allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA']
            }
        };
        
        // 获取Google Pay客户端
        function getGooglePaymentsClient() {
            return new google.payments.api.PaymentsClient({
                environment: '<?php echo env('PAYPAL_SANDBOX', true) ? 'TEST' : 'PRODUCTION'; ?>',
                paymentDataCallbacks: {
                    onPaymentAuthorized: onPaymentAuthorized
                }
            });
        }
        
        // 支付授权处理函数
        function onPaymentAuthorized(paymentData) {
            return new Promise(function(resolve, reject) {
                console.log('Google Pay payment authorized:', paymentData);
                
                // 创建PayPal订单
                createActivationOrder()
                    .then(function(orderId) {
                        console.log('PayPal order created:', orderId);
                        return confirmPayPalOrder(orderId, paymentData);
                    })
                    .then(function(result) {
                        console.log('Payment confirmed:', result);
                        showSuccessMessage(result);
                        resolve({
                            transactionState: 'SUCCESS'
                        });
                    })
                    .catch(function(error) {
                        console.error('Error in payment processing:', error);
                        resolve({
                            transactionState: 'ERROR',
                            error: {
                                intent: 'PAYMENT_AUTHORIZATION',
                                message: error.message || 'Payment failed',
                                reason: 'PAYMENT_DATA_INVALID'
                            }
                        });
                    });
            });
        }
        
        // 创建激活订单
        function createActivationOrder() {
            return fetch('account_activation_api.php?action=create_order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                console.log('Activation order creation response:', data);
                if (data.error) {
                    throw new Error(data.error);
                }
                return data.id;
            })
            .catch(function(error) {
                console.error('Error creating activation order:', error);
                throw error;
            });
        }
        
        // 确认PayPal订单
        function confirmPayPalOrder(orderId, paymentData) {
            console.log('Confirming PayPal order with ID:', orderId);
            
            const confirmParams = {
                orderId: orderId,
                paymentMethodData: paymentData.paymentMethodData
            };
            
            if (paymentData.paymentMethodData && 
                paymentData.paymentMethodData.info && 
                paymentData.paymentMethodData.info.billingAddress) {
                confirmParams.billingAddress = paymentData.paymentMethodData.info.billingAddress;
            }
            
            if (paymentData.shippingAddress) {
                confirmParams.shippingAddress = paymentData.shippingAddress;
            }
            
            if (paymentData.email) {
                confirmParams.email = paymentData.email;
            }
            
            return paypal.Googlepay().confirmOrder(confirmParams)
                .then(function(result) {
                    console.log('PayPal confirmOrder success:', result);
                    return result;
                })
                .catch(function(error) {
                    console.error('PayPal confirmOrder error:', error);
                    throw error;
                });
        }
        
        // 显示成功消息
        function showSuccessMessage(result) {
            document.getElementById('payment-message').style.display = 'block';
            document.getElementById('payment-message').style.backgroundColor = '#d4edda';
            document.getElementById('payment-message').innerHTML = '<p><strong>Success!</strong> Your account has been activated. Redirecting to homepage...</p>';
            
            setTimeout(function() {
                window.location.href = 'activate_account.php?success=true&order_id=' + result.id;
            }, 3000);
        }
        
        // Google Pay支付数据请求
        async function getGooglePaymentDataRequest() {
            try {
                const googlePayConfig = await paypal.Googlepay().config();
                console.log('PayPal Google Pay config:', googlePayConfig);
                
                const paymentDataRequest = Object.assign({}, baseRequest);
                paymentDataRequest.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
                paymentDataRequest.transactionInfo = getActivationTransactionInfo();
                paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;
                paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
                
                return paymentDataRequest;
            } catch (error) {
                console.error('Error getting Google Pay payment data request:', error);
                throw error;
            }
        }
        
        // 获取激活交易信息
        function getActivationTransactionInfo() {
            return {
                currencyCode: 'USD',
                totalPriceStatus: 'FINAL',
                totalPrice: '<?php echo number_format($activation_fee, 2); ?>'
            };
        }
        
        // Google Pay按钮点击处理
        async function onGooglePaymentButtonClicked() {
            try {
                console.log('Google Pay button clicked for activation');
                
                const overlay = document.createElement('div');
                overlay.className = 'loading-overlay';
                overlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
                overlay.style.display = 'flex';
                document.body.appendChild(overlay);
                
                const paymentDataRequest = await getGooglePaymentDataRequest();
                const paymentsClient = getGooglePaymentsClient();
                
                try {
                    await paymentsClient.loadPaymentData(paymentDataRequest);
                    console.log('Google Pay payment completed successfully');
                } catch (err) {
                    overlay.remove();
                    console.error('Google Pay error:', err);
                    
                    if (err.statusCode === "CANCELED") {
                        console.log('User canceled the payment');
                    } else if (err.statusCode === "DEVELOPER_ERROR") {
                        alert('Google Pay配置错误。请联系网站管理员。');
                    } else {
                        alert('Google Pay支付失败。请稍后再试。');
                    }
                }
            } catch (error) {
                console.error('Error in Google Pay flow:', error);
                alert('Google Pay支付过程中发生错误。请稍后再试。');
            }
        }
        
        // Google Pay初始化
        function onGooglePayLoaded() {
            console.log('Google Pay JavaScript loaded');
            const paymentsClient = getGooglePaymentsClient();
            
            const isReadyToPayRequest = Object.assign({}, baseRequest);
            isReadyToPayRequest.allowedPaymentMethods = [baseCardPaymentMethod];
            
            paymentsClient.isReadyToPay(isReadyToPayRequest)
                .then(function(response) {
                    console.log('Google Pay isReadyToPay response:', response);
                    if (response.result) {
                        addGooglePayButton();
                    } else {
                        console.log('Google Pay is not available on this device/browser');
                        document.getElementById('googlepay-button-container').style.display = 'none';
                    }
                })
                .catch(function(err) {
                    console.error('Google Pay isReadyToPay error:', err);
                    document.getElementById('googlepay-button-container').style.display = 'none';
                });
        }
        
        // 添加Google Pay按钮
        function addGooglePayButton() {
            const container = document.getElementById('googlepay-button-container');
            if (!container) return;
            
            const paymentsClient = getGooglePaymentsClient();
            const button = paymentsClient.createButton({
                onClick: onGooglePaymentButtonClicked,
                allowedPaymentMethods: [baseCardPaymentMethod],
                buttonColor: 'black',
                buttonType: 'buy',
                buttonSizeMode: 'fill'
            });
            
            container.appendChild(button);
        }
        
        // Google Pay安全加载器
        (function(){
            var attempts = 0;
            function tryInitGPay(){
                attempts++;
                var ready = window.google && google.payments && google.payments.api && typeof onGooglePayLoaded === 'function';
                if (ready) {
                    try { 
                        onGooglePayLoaded(); 
                    } catch(e) { 
                        console.error('onGooglePayLoaded threw', e); 
                    }
                    return;
                }
                if (attempts < 100) {
                    setTimeout(tryInitGPay, 100);
                } else {
                    console.warn('Google Pay not initialized: SDK or handler not ready');
                    document.getElementById('googlepay-button-container').style.display = 'none';
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryInitGPay);
            } else {
                tryInitGPay();
            }
        })();
        
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
        
        // Apple Pay 实现
        console.log('Apple Pay script loaded');
        
        // 检查Apple Pay是否可用
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking Apple Pay availability');
            
            if (window.ApplePaySession) {
                console.log('ApplePaySession is available');
                
                if (ApplePaySession.canMakePayments()) {
                    console.log('Device can make Apple Pay payments');
                    renderApplePayButton();
                    
                    const merchantId = '<?php echo env('APPLE_PAY_MERCHANT_ID', 'merchant.com.yourmerchantid'); ?>';
                    console.log('Checking for active cards with merchant ID:', merchantId);
                    
                    ApplePaySession.canMakePaymentsWithActiveCard(merchantId).then(function(canMakePayments) {
                        console.log('Can make payments with active card:', canMakePayments);
                    });
                } else {
                    console.log('Device cannot make Apple Pay payments');
                    hideApplePayButton();
                }
            } else {
                console.log('ApplePaySession is not available on this device/browser');
                hideApplePayButton();
            }
        });
        
        // 隐藏Apple Pay按钮
        function hideApplePayButton() {
            const container = document.getElementById('applepay-button-container');
            const wrapper = container ? container.parentElement : null;
            if (container) {
                container.style.display = 'none';
            }
            if (wrapper && wrapper.classList.contains('applepay-container')) {
                wrapper.style.display = 'none';
            }
        }
        
        // 渲染Apple Pay按钮
        function renderApplePayButton() {
            const container = document.getElementById('applepay-button-container');
            const wrapper = container ? container.parentElement : null;
            if (!container) return;
            
            console.log('Rendering Apple Pay button for activation');
            
            try {
                if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
                    console.log('Apple Pay is not supported on this device/browser');
                    hideApplePayButton();
                    return;
                }
                
                // 使用PayPal的Apple Pay组件创建标准按钮
                paypal.Applepay({
                    buttonStyle: {
                        type: 'buy',
                        color: 'black',
                        height: 48
                    },
                    onClick: function() {
                        console.log('Apple Pay button clicked for activation');
                        handleApplePayButtonClick();
                    }
                }).render('#applepay-button-container');
                
                container.style.width = '100%';
                container.style.minHeight = '48px';
                
                // 确保容器可见
                if (wrapper && wrapper.classList.contains('applepay-container')) {
                    wrapper.style.display = 'block';
                }
                container.style.display = 'block';
                
                console.log('Apple Pay button rendered successfully');
            } catch (error) {
                console.error('Error rendering Apple Pay button:', error);
                hideApplePayButton();
            }
        }
        
        // 处理Apple Pay按钮点击
        function handleApplePayButtonClick() {
            console.log('Handling Apple Pay button click for activation');
            
            // 创建加载覆盖层
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
            overlay.style.display = 'flex';
            document.body.appendChild(overlay);
            
            // 获取价格信息
            const priceInfo = getApplePayPriceInfo();
            console.log('Apple Pay price info:', priceInfo);
            
            try {
                // 创建PayPal订单
                createActivationOrder()
                    .then(function(orderId) {
                        console.log('PayPal order created with ID:', orderId);
                        
                        // 创建Apple Pay支付请求
                        const paymentRequest = {
                            countryCode: 'US',
                            currencyCode: priceInfo.currencyCode,
                            supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
                            merchantCapabilities: ['supports3DS'],
                            total: {
                                label: 'Account Activation',
                                type: 'final',
                                amount: priceInfo.totalPrice
                            }
                        };
                        
                        console.log('Apple Pay payment request:', paymentRequest);
                        
                        // 创建Apple Pay会话
                        const session = new ApplePaySession(6, paymentRequest);
                        
                        // 设置验证商家回调
                        session.onvalidatemerchant = function(event) {
                            console.log('Apple Pay merchant validation requested');
                            
                            // 使用PayPal的商家验证
                            paypal.Applepay().validateMerchant({
                                validationUrl: event.validationURL
                            }).then(function(result) {
                                console.log('Merchant validation successful:', result);
                                session.completeMerchantValidation(result.merchantSession);
                            }).catch(function(error) {
                                console.error('Merchant validation failed:', error);
                                session.abort();
                                overlay.remove();
                                alert('Apple Pay商家验证失败。请稍后再试。');
                            });
                        };
                        
                        // 设置支付授权回调
                        session.onpaymentauthorized = function(event) {
                            console.log('Apple Pay payment authorized:', event.payment);
                            
                            // 确认PayPal订单
                            paypal.Applepay().confirmOrder({
                                orderId: orderId,
                                token: event.payment.token,
                                billingContact: event.payment.billingContact,
                                shippingContact: event.payment.shippingContact
                            }).then(function(result) {
                                console.log('PayPal Apple Pay order confirmed:', result);
                                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                overlay.remove();
                                showSuccessMessage(result);
                            }).catch(function(error) {
                                console.error('PayPal Apple Pay order confirmation failed:', error);
                                session.completePayment(ApplePaySession.STATUS_FAILURE);
                                overlay.remove();
                                alert('Apple Pay支付确认失败。请稍后再试。');
                            });
                        };
                        
                        // 设置取消回调
                        session.oncancel = function() {
                            console.log('Apple Pay session cancelled');
                            overlay.remove();
                        };
                        
                        // 开始Apple Pay会话
                        session.begin();
                    })
                    .catch(function(error) {
                        console.error('Error creating PayPal order for Apple Pay:', error);
                        overlay.remove();
                        alert('创建订单失败。请稍后再试。');
                    });
            } catch (error) {
                console.error('Error in Apple Pay flow:', error);
                overlay.remove();
                alert('Apple Pay支付过程中发生错误。请稍后再试。');
            }
        }
        
        // 获取Apple Pay价格信息
        function getApplePayPriceInfo() {
            return {
                currencyCode: 'USD',
                totalPrice: '<?php echo number_format($activation_fee, 2); ?>'
            };
        }
        
        // Skip Activation功能
        document.getElementById('skip-activation-btn').addEventListener('click', function() {
            if (confirm('Are you sure you want to skip activation? You will have limited access to the website features.')) {
                window.location.href = 'home.php';
            }
        });
    </script>
</body>
</html> 