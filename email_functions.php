<?php
// 引入PHPMailer库 - 修复版本，兼容PHP 5.4.45
// 移除use语句，直接使用完整类名

require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once 'env.php';
require_once 'url_signer.php';

// 增加PHP执行时间限制
ini_set('max_execution_time', 300); // 设置为300秒

/**
 * 生成随机验证码
 * @param int $length 验证码长度
 * @return string 生成的验证码
 */
function generateVerificationCode($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * 发送邮件验证码，带重试机制
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @param string $code 验证码
 * @param int $maxRetries 最大重试次数
 * @return bool 是否发送成功
 */
function sendVerificationEmail($to, $username, $code, $maxRetries = 5) {
    // 检查是否已经发送过验证码
    if (isset($_SESSION['last_purchase_email_sent']) && (time() - $_SESSION['last_purchase_email_sent']) < 30) {
        // 如果在30秒内已经发送过邮件，则返回错误
        error_log("Email sending limited: Last email was sent less than 30 seconds ago");
        return false;
    }
    
    // 重试计数器
    $retryCount = 0;
    $success = false;
    $lastError = "";
    
    // 多种SMTP配置用于重试 - 与订单邮件机制保持一致
    $smtpConfigs = [
        [
            'name' => 'Gmail SMTP (STARTTLS)',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            'timeout' => 30
        ],
        [
            'name' => 'Gmail SMTP (SSL)',
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'encryption' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
            'timeout' => 30
        ],
        [
            'name' => 'Gmail SMTP (Extended Timeout)',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            'timeout' => 60
        ]
    ];
    
    // 尝试发送邮件，最多重试指定次数
    while ($retryCount < $maxRetries && !$success) {
        try {
            // 记录重试信息
            if ($retryCount > 0) {
                error_log("Retry attempt #" . $retryCount . " for sending email to: " . $to);
            } else {
                error_log("Starting email sending to: " . $to);
            }
            
            $start_time = microtime(true);
            
            // 创建PHPMailer实例 - 使用完整类名
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // 选择SMTP配置 - 与订单邮件机制保持一致
            $configIndex = $retryCount % count($smtpConfigs);
            $config = $smtpConfigs[$configIndex];
            
            error_log("Using SMTP config: " . $config['name']);
            
            // 服务器设置
            $mail->SMTPDebug = 0; // 调试模式：0=关闭，1=客户端消息，2=客户端和服务器消息
            $mail->isSMTP(); // 使用SMTP
            $mail->Host = $config['host']; // SMTP服务器
            $mail->SMTPAuth = true; // 启用SMTP认证
            $mail->Username = env('GMAIL_USERNAME', 'your_email@gmail.com'); // SMTP用户名
            $mail->Password = env('GMAIL_PASSWORD', 'your_app_password'); // SMTP密码（应用专用密码）
            $mail->SMTPSecure = $config['encryption']; // 加密方式
            $mail->Port = $config['port']; // 端口
            
            // 设置超时和连接参数
            $mail->Timeout = $config['timeout']; // SMTP超时时间（秒）
            $mail->SMTPKeepAlive = false;
            $mail->SMTPAutoTLS = true; // 保持SMTP连接
            
            // 禁用SSL证书验证（在某些环境中可能需要）
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // 收件人设置
            $mail->setFrom(env('GMAIL_USERNAME', 'your_email@gmail.com'), 'Jianfa Website'); // 发件人
            $mail->addAddress($to, $username); // 收件人
            
            // 邮件内容
            $mail->isHTML(true); // 设置邮件格式为HTML
            $mail->Subject = 'Your Account Verification Code'; // 邮件主题
            
            // 邮件正文
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;">
                    <h2 style="color: #9E9BC7; text-align: center;">Account Verification Code</h2>
                    <p>Dear ' . htmlspecialchars($username) . ',</p>
                    <p>Thank you for registering on our website. Please use the following verification code to complete your registration:</p>
                    <div style="background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;">
                        ' . $code . '
                    </div>
                    <p>This verification code is valid for 10 minutes. If you did not request this code, please ignore this email.</p>
                    <p>Thank you!</p>
                    <p style="font-size: 12px; color: #777; margin-top: 30px; text-align: center;">This is an automated message, please do not reply.</p>
                </div>
            ';
            
            // 纯文本版本（用于不支持HTML的邮件客户端）
            $mail->AltBody = 'Dear ' . $username . ', your verification code is: ' . $code . '. This code is valid for 10 minutes.';
            
            // 发送邮件
            $result = $mail->send();
            
            // 记录发送结果和耗时
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time);
            error_log("Email sending result: " . ($result ? "Success" : "Failed") . ", Time taken: " . $execution_time . " seconds");
            
            // 如果发送成功，记录最后发送时间并返回成功
            if ($result) {
                if (!isset($_SESSION)) {
                    session_start();
                }
                $_SESSION['last_purchase_email_sent'] = time();
                $success = true;
            }
            
            // 关闭SMTP连接
            $mail->smtpClose();
            
        } catch (Exception $e) {
            // 记录错误信息
            $lastError = $e->getMessage();
            error_log("Email sending failed (attempt #" . ($retryCount + 1) . "): " . $lastError);
            $retryCount++;
            
            // 如果不是最后一次重试，等待一段时间再重试
            if ($retryCount < $maxRetries) {
                $waitTime = $retryCount * 1; // 等待时间随重试次数增加
                error_log("Waiting " . $waitTime . " seconds before retry...");
                sleep($waitTime);
            }
        }
    }
    
    // 如果所有尝试都失败，记录最终错误
    if (!$success) {
        error_log("All " . $maxRetries . " attempts to send email failed. Last error: " . $lastError);
    }
    
    return $success;
}

/**
 * 验证邮箱验证码
 * @param string $email 邮箱
 * @param string $code 用户输入的验证码
 * @param object $conn 数据库连接
 * @return bool 验证是否成功
 */
function verifyEmailCode($email, $code, $conn) {
    // 准备查询语句
    $sql = "SELECT * FROM verification_codes WHERE email = ? AND code = ? AND expires_at > NOW()";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // 绑定参数
        mysqli_stmt_bind_param($stmt, "ss", $email, $code);
        
        // 执行查询
        if (mysqli_stmt_execute($stmt)) {
            // 存储结果
            mysqli_stmt_store_result($stmt);
            
            // 检查是否有匹配的记录
            if (mysqli_stmt_num_rows($stmt) > 0) {
                // 验证成功，删除验证码记录
                $delete_sql = "DELETE FROM verification_codes WHERE email = ?";
                if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                    mysqli_stmt_bind_param($delete_stmt, "s", $email);
                    mysqli_stmt_execute($delete_stmt);
                    mysqli_stmt_close($delete_stmt);
                }
                
                mysqli_stmt_close($stmt);
                return true;
            }
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return false;
}

/**
 * 保存验证码到数据库
 * @param string $email 邮箱
 * @param string $code 验证码
 * @param object $conn 数据库连接
 * @return bool 是否保存成功
 */
function saveVerificationCode($email, $code, $conn) {
    // 删除旧的验证码
    $delete_sql = "DELETE FROM verification_codes WHERE email = ?";
    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
        mysqli_stmt_bind_param($delete_stmt, "s", $email);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
    }
    
    // 插入新的验证码，10分钟有效期
    $sql = "INSERT INTO verification_codes (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $email, $code);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    
    return false;
}

/**
 * 发送购买确认邮件，包含产品链接
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @param array $product 产品信息
 * @param string $orderID 订单ID
 * @param bool $isPhotoPack 是否为图片包购买
 * @param int $maxRetries 最大重试次数
 * @return bool 是否发送成功
 */
function sendPurchaseConfirmationEmail($to, $username, $product, $orderID, $isPhotoPack = false, $maxRetries = 5) {
    // 检查是否已经发送过邮件
    if (isset($_SESSION['last_purchase_email_sent']) && (time() - $_SESSION['last_purchase_email_sent']) < 30) {
        error_log("Email sending limited: Last email was sent less than 30 seconds ago");
        return false;
    }
    
    // 初始化URL签名器
    $url_signer = new UrlSigner();
    
    // 重试计数器
    $retryCount = 0;
    $success = false;
    $lastError = "";
    
    // 多种SMTP配置用于重试 - 与头发邮件机制保持一致
    $smtpConfigs = [
        [
            'name' => 'Gmail SMTP (STARTTLS)',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            'timeout' => 30
        ],
        [
            'name' => 'Gmail SMTP (SSL)',
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'encryption' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
            'timeout' => 30
        ],
        [
            'name' => 'Gmail SMTP (Extended Timeout)',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            'timeout' => 60
        ]
    ];
    
    // 获取产品链接
    $productLink = $isPhotoPack ? $product['paid_photos_zip'] : $product['paid_video'];
    
    // 生成带签名的下载链接
    $baseUrl = "https://vip.fhaircut.com";
    $downloadUrl = $baseUrl . "/download.php?file=" . urlencode($productLink);
    $signedUrl = $url_signer->signUrl($downloadUrl);
    
    $productType = $isPhotoPack ? 'Photo Pack' : 'Video';
    $productTitle = $product['title'];
    $productId = isset($product['id']) ? $product['id'] : (isset($product['product_id']) ? $product['product_id'] : '');
    $price = $isPhotoPack ? $product['photo_pack_price'] : $product['price'];
    
    // 尝试发送邮件，最多重试指定次数
    while ($retryCount < $maxRetries && !$success) {
        try {
            // 记录重试信息
            if ($retryCount > 0) {
                error_log("Retry attempt #" . $retryCount . " for sending purchase confirmation to: " . $to);
            } else {
                error_log("Starting purchase confirmation email to: " . $to);
            }
            
            $start_time = microtime(true);
            
            // 创建PHPMailer实例 - 使用完整类名
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // 选择SMTP配置 - 与头发邮件机制保持一致
            $configIndex = $retryCount % count($smtpConfigs);
            $config = $smtpConfigs[$configIndex];
            
            error_log("Using SMTP config: " . $config['name']);
            
            // 服务器设置
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = env('GMAIL_USERNAME', 'your_email@gmail.com');
            $mail->Password = env('GMAIL_PASSWORD', 'your_app_password');
            $mail->SMTPSecure = $config['encryption'];
            $mail->Port = $config['port'];
            
            // 设置超时和连接参数
            $mail->Timeout = $config['timeout'];
            $mail->SMTPKeepAlive = false;
            $mail->SMTPAutoTLS = true;
            
            // 禁用SSL证书验证
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // 收件人设置
            $mail->setFrom(env('GMAIL_USERNAME', 'your_email@gmail.com'), 'Jianfa Website');
            $mail->addAddress($to, $username);
            
            // 邮件内容
            $mail->isHTML(true);
            $mail->Subject = 'Your Purchase Confirmation - Order #' . $orderID;
            
            // 邮件正文
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;">
                    <h2 style="color: #9E9BC7; text-align: center;">Purchase Confirmation</h2>
                    <p>Dear ' . htmlspecialchars($username) . ',</p>
                    <p>Thank you for your purchase! Your order has been successfully processed.</p>
                    
                    <div style="background-color: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px;">
                        <h3 style="margin-top: 0; color: #333;">Order Details</h3>
                        <p><strong>Order ID:</strong> ' . $orderID . '</p>
                        <p><strong>Product:</strong> ' . htmlspecialchars($productTitle) . 
                        ($productId ? ' (ID: ' . $productId . ')' : '') . '</p>
                        <p><strong>Type:</strong> ' . $productType . '</p>
                        <p><strong>Price:</strong> $' . number_format($price, 2) . '</p>
                    </div>
                    
                    <div style="background-color: #e8f4ff; padding: 15px; margin: 20px 0; border-radius: 5px;">
                        <h3 style="margin-top: 0; color: #0056b3;">Download Link</h3>
                        <p>Click the button below to access your purchased content:</p>
                        <div style="text-align: center; margin: 20px 0;">
                            <a href="' . htmlspecialchars($signedUrl) . '" style="background-color: #9E9BC7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Download ' . $productType . '</a>
                        </div>
                        <p style="font-size: 12px;">Or copy this link: ' . htmlspecialchars($signedUrl) . '</p>
                        <p style="color: #ff6600; font-size: 13px;"><strong>Note:</strong> This download link will expire in 48 hours for security reasons.</p>
                    </div>
                    
                    <p>If you have any questions about your purchase, please contact our support team.</p>
                    <p>Thank you for shopping with us!</p>
                    <p style="font-size: 12px; color: #777; margin-top: 30px; text-align: center;">This is an automated message, please do not reply.</p>
                </div>
            ';
            
            // 纯文本版本
            $mail->AltBody = 'Dear ' . $username . ', thank you for your purchase! Your order #' . $orderID . ' has been processed. ' . 
                             'You purchased: ' . $productTitle . 
                             ($productId ? ' (ID: ' . $productId . ')' : '') . 
                             ' (' . $productType . '). ' . 
                             'Download link (valid for 48 hours): ' . $signedUrl;
            
            // 发送邮件
            $result = $mail->send();
            
            // 记录发送结果和耗时
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time);
            error_log("Purchase confirmation email result: " . ($result ? "Success" : "Failed") . ", Time taken: " . $execution_time . " seconds");
            
            // 如果发送成功，记录最后发送时间并返回成功
            if ($result) {
                if (!isset($_SESSION)) {
                    session_start();
                }
                $_SESSION['last_purchase_email_sent'] = time();
                $success = true;
            }
            
            // 关闭SMTP连接
            $mail->smtpClose();
            
        } catch (Exception $e) {
            // 记录错误信息
            $lastError = $e->getMessage();
            error_log("Purchase confirmation email failed (attempt #" . ($retryCount + 1) . "): " . $lastError);
            $retryCount++;
            
            // 如果不是最后一次重试，等待一段时间再重试
            if ($retryCount < $maxRetries) {
                $waitTime = $retryCount * 1;
                error_log("Waiting " . $waitTime . " seconds before retry...");
                sleep($waitTime);
            }
        }
    }
    
    // 如果所有尝试都失败，记录最终错误
    if (!$success) {
        error_log("All " . $maxRetries . " attempts to send purchase confirmation email failed. Last error: " . $lastError);
    }
    
    return $success;
}

/**
 * 检查邮箱是否符合自动激活条件
 * 
 * @param string $email 要检查的邮箱
 * @param mysqli $conn 数据库连接
 * @return array 包含是否符合激活条件及原因的数组
 */
function checkEmailForAutoActivation($email, $conn) {
    $result = [
        'should_activate' => false,
        'reason' => '',
        'details' => []
    ];
    
    // 检查是否已经有激活付款记录
    $sql = "SELECT COUNT(*) as count FROM purchases WHERE email = ? AND purchase_type = 'activation'";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $activation_result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($activation_result);
        
        if ($row['count'] > 0) {
            $result['should_activate'] = true;
            $result['reason'] = 'activation_payment';
            $result['details']['activation_count'] = $row['count'];
            mysqli_stmt_close($stmt);
            return $result;
        }
        mysqli_stmt_close($stmt);
    }
    
    // 检查累计消费金额
    $sql = "SELECT SUM(amount) as total_spent, COUNT(*) as purchase_count FROM purchases WHERE email = ? AND purchase_type != 'activation'";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $spend_result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($spend_result);
        
        $total_spent = $row['total_spent'] ?? 0;
        $purchase_count = $row['purchase_count'] ?? 0;
        
        $result['details']['total_spent'] = $total_spent;
        $result['details']['purchase_count'] = $purchase_count;
        
        // 检查累计消费是否超过100美元
        if ($total_spent >= 100) {
            $result['should_activate'] = true;
            $result['reason'] = 'total_spent';
            mysqli_stmt_close($stmt);
            return $result;
        }
        
        // 检查购买次数是否超过5次
        if ($purchase_count >= 5) {
            $result['should_activate'] = true;
            $result['reason'] = 'purchase_count';
            mysqli_stmt_close($stmt);
            return $result;
        }
        
        mysqli_stmt_close($stmt);
    }
    
    return $result;
}
?>