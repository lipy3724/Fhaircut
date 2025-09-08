<?php
/**
 * 头发订单邮件发送功能
 * 支持所有支付方式的统一邮件发送
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once 'env.php';

/**
 * 发送头发购买确认邮件
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @param array $hair_info 头发信息数组 ['id', 'title', 'value']
 * @param string $orderID 订单ID
 * @param float $amount 支付金额
 * @param string $paymentMethod 支付方式 (balance, paypal, googlepay, applepay)
 * @param int $maxRetries 最大重试次数
 * @return bool 是否发送成功
 */
function sendHairPurchaseEmail($to, $username, $hair_info, $orderID, $amount, $paymentMethod = 'balance', $maxRetries = 5) {
    // 检查邮件发送频率限制
    if (isset($_SESSION['last_hair_email_sent']) && (time() - $_SESSION['last_hair_email_sent']) < 30) {
        error_log("Hair email sending limited: Last email was sent less than 30 seconds ago");
        return false;
    }
    
    // 重试计数器
    $retryCount = 0;
    $success = false;
    $lastError = "";
    
    // 获取支付方式显示名称
    $paymentMethodNames = [
        'balance' => 'Balance Payment',
        'paypal' => 'PayPal',
        'googlepay' => 'Google Pay',
        'applepay' => 'Apple Pay'
    ];
    $paymentMethodDisplay = $paymentMethodNames[$paymentMethod] ?? 'Online Payment';
    
    // 多种SMTP配置用于重试
    $smtpConfigs = [
        [
            'name' => 'Gmail SMTP (STARTTLS)',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
            'timeout' => 30
        ],
        [
            'name' => 'Gmail SMTP (SSL)',
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'encryption' => PHPMailer::ENCRYPTION_SMTPS,
            'timeout' => 30
        ],
        [
            'name' => 'Gmail SMTP (Extended Timeout)',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
            'timeout' => 60
        ]
    ];
    
    // 尝试发送邮件，最多重试指定次数
    while ($retryCount < $maxRetries && !$success) {
        try {
            // 记录重试信息
            if ($retryCount > 0) {
                error_log("Retry attempt #" . $retryCount . " for sending hair purchase email to: " . $to);
            } else {
                error_log("Starting hair purchase confirmation email to: " . $to);
            }
            
            $start_time = microtime(true);
            
            // 创建PHPMailer实例
            $mail = new PHPMailer(true);
            
            // 选择SMTP配置
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
            $mail->Timeout = $config['timeout'];
            
            // 设置连接参数
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
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Subject = 'Hair Purchase Confirmation - Order #' . $orderID;
            
            // 构建邮件正文
            $mail->Body = generateHairEmailTemplate($username, $hair_info, $orderID, $amount, $paymentMethodDisplay);
            
            // 发送邮件
            $mail->send();
            $success = true;
            
            // 记录成功发送时间
            $_SESSION['last_hair_email_sent'] = time();
            
            $end_time = microtime(true);
            $execution_time = round(($end_time - $start_time) * 1000, 2);
            
            error_log("Hair purchase confirmation email sent successfully to: " . $to . " in " . $execution_time . "ms");
            
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            error_log("Hair purchase confirmation email failed (attempt " . ($retryCount + 1) . "): " . $lastError);
            $retryCount++;
            
            // 如果不是最后一次重试，等待一段时间再重试
            if ($retryCount < $maxRetries) {
                sleep(1); // 等待1秒再重试
            }
        }
    }
    
    if (!$success) {
        error_log("All hair purchase confirmation email attempts failed for: " . $to . ". Last error: " . $lastError);
    }
    
    return $success;
}

/**
 * 生成头发购买确认邮件HTML模板
 * @param string $username 用户名
 * @param array $hair_info 头发信息
 * @param string $orderID 订单ID
 * @param float $amount 金额
 * @param string $paymentMethod 支付方式显示名称
 * @return string HTML邮件内容
 */
function generateHairEmailTemplate($username, $hair_info, $orderID, $amount, $paymentMethod) {
    $currentDate = date('F j, Y');
    $hairTitle = htmlspecialchars($hair_info['title'] ?? 'Hair Product');
    $hairId = htmlspecialchars($hair_info['id'] ?? '');
    
    return '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            
            <!-- Header -->
            <div style="text-align: center; border-bottom: 2px solid #e91e63; padding-bottom: 20px; margin-bottom: 30px;">
                <h1 style="color: #e91e63; margin: 0; font-size: 28px;">Jianfa</h1>
                <p style="color: #666; margin: 5px 0 0 0; font-size: 16px;">Hair Purchase Confirmation</p>
            </div>
            
            <!-- Greeting -->
            <div style="margin-bottom: 25px;">
                <h2 style="color: #333; margin: 0 0 10px 0; font-size: 24px;">Hello ' . htmlspecialchars($username) . '!</h2>
                <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0;">
                    Thank you for your hair purchase! We are excited to confirm that your order has been successfully processed.
                </p>
            </div>
            
            <!-- Order Details -->
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <h3 style="color: #e91e63; margin: 0 0 15px 0; font-size: 18px;">📋 Order Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold; width: 40%;">Order ID:</td>
                        <td style="padding: 8px 0; color: #333;">' . htmlspecialchars($orderID) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Product:</td>
                        <td style="padding: 8px 0; color: #333;">' . $hairTitle . ' (ID: ' . $hairId . ')</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Amount:</td>
                        <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 18px;">$' . number_format($amount, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Payment Method:</td>
                        <td style="padding: 8px 0; color: #333;">' . htmlspecialchars($paymentMethod) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Date:</td>
                        <td style="padding: 8px 0; color: #333;">' . $currentDate . '</td>
                    </tr>
                </table>
            </div>
            
            <!-- Success Message -->
            <div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-bottom: 25px;">
                <div style="color: #155724; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;">✅ Purchase Successful!</h3>
                    <p style="margin: 0; font-size: 14px;">Your hair purchase has been confirmed and processed successfully.</p>
                </div>
            </div>
            
            <!-- Important Notice -->
            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 25px;">
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    <strong>📌 Important:</strong> Please keep this email for your records. If you have any questions about your purchase or need support, please contact our customer service team.
                </p>
            </div>
            
            
            <!-- Footer -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666;">
                <p style="margin: 0 0 10px 0; font-size: 16px; font-weight: bold; color: #e91e63;">Thank you for choosing Jianfa!</p>
                <p style="margin: 0 0 5px 0; font-size: 14px;">We appreciate your business and trust in our products.</p>
                <p style="margin: 0; font-size: 12px; color: #999;">
                    This is an automated email. Please do not reply to this message.<br>
                    If you need assistance, please contact our support team.
                </p>
            </div>
        </div>
    </div>';
}

/**
 * 更新头发订单的邮件发送状态
 * @param mysqli $conn 数据库连接
 * @param string $orderID 订单ID
 * @return bool 是否更新成功
 */
function updateHairEmailSentStatus($conn, $orderID) {
    $update_sql = "UPDATE hair_purchases SET email_sent = 1 WHERE order_id = ?";
    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "s", $orderID);
        $result = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        return $result;
    }
    return false;
}
?>
