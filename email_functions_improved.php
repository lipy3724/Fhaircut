<?php
// 改进的邮件发送函数
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once 'env.php';

/**
 * 改进的购买确认邮件发送函数
 */
function sendPurchaseConfirmationEmailImproved($to, $username, $product, $orderID, $isPhotoPack = false, $maxRetries = 5) {
    // 检查是否已经发送过邮件
    if (isset($_SESSION['last_email_sent']) && (time() - $_SESSION['last_email_sent']) < 30) {
        error_log("Email sending limited: Last email was sent less than 30 seconds ago");
        return false;
    }
    
    // 重试计数器
    $retryCount = 0;
    $success = false;
    $lastError = "";
    
    // 获取产品链接
    $productLink = $isPhotoPack ? $product['paid_photos_zip'] : $product['paid_video'];
    $productType = $isPhotoPack ? 'Photo Pack' : 'Video';
    $productTitle = $product['title'];
    $productId = isset($product['id']) ? $product['id'] : (isset($product['product_id']) ? $product['product_id'] : '');
    $price = $isPhotoPack ? $product['photo_pack_price'] : $product['price'];
    
    // 多种SMTP配置
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
                error_log("Retry attempt #" . $retryCount . " for sending purchase confirmation to: " . $to);
            } else {
                error_log("Starting purchase confirmation email to: " . $to);
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
                            <a href="' . htmlspecialchars($productLink) . '" style="background-color: #9E9BC7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Download ' . $productType . '</a>
                        </div>
                        <p style="font-size: 12px;">Or copy this link: ' . htmlspecialchars($productLink) . '</p>
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
                             'Download link: ' . $productLink;
            
            // 发送邮件
            $result = $mail->send();
            
            // 记录发送结果和耗时
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time);
            error_log("Purchase confirmation email result: " . ($result ? "Success" : "Failed") . ", Time taken: " . $execution_time . " seconds, Config: " . $config['name']);
            
            // 如果发送成功，记录最后发送时间并返回成功
            if ($result) {
                if (!isset($_SESSION)) {
                    session_start();
                }
                $_SESSION['last_email_sent'] = time();
                $success = true;
            }
            
            // 关闭SMTP连接
            $mail->smtpClose();
            
        } catch (Exception $e) {
            // 记录错误信息
            $lastError = $e->getMessage();
            error_log("Purchase confirmation email failed (attempt #" . ($retryCount + 1) . "): " . $lastError);
        }
        
        // 如果发送失败且未达到最大重试次数，则等待一段时间后重试
        if (!$success && $retryCount < $maxRetries - 1) {
            $retryCount++;
            $waitTime = $retryCount * 3; // 增加等待时间
            error_log("Waiting " . $waitTime . " seconds before retry...");
            sleep($waitTime);
        } else {
            break;
        }
    }
    
    // 如果所有尝试都失败，记录最终错误
    if (!$success) {
        error_log("All " . $maxRetries . " attempts to send purchase confirmation email failed. Last error: " . $lastError);
    }
    
    return $success;
}
?>
