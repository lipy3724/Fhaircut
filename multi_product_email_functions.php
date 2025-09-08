<?php
/**
 * 多商品邮件发送功能
 * 发送一封包含多个商品信息的邮件，但后台创建多个订单
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once 'env.php';
require_once 'email_functions.php';
require_once 'hair_email_functions.php';
require_once __DIR__ . '/url_signer.php';

/**
 * 发送多商品购买确认邮件并创建多个订单
 * @param mysqli $conn 数据库连接
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @param array $items 商品数组 [['item_type', 'item_id', 'title', 'price', ...], ...]
 * @param string $paymentMethod 支付方式 (balance, paypal, googlepay, applepay)
 * @param int $user_id 用户ID（用于余额支付）
 * @param int $maxRetries 最大重试次数
 * @return array 返回结果 ['success' => bool, 'order_ids' => array, 'total_amount' => float, 'message' => string]
 */
function processMultiProductPurchase($conn, $to, $username, $items, $paymentMethod = 'balance', $user_id = null, $maxRetries = 5) {
    $result = [
        'success' => false,
        'order_ids' => [],
        'total_amount' => 0,
        'message' => ''
    ];
    
    // 计算总金额
    $total_amount = 0;
    foreach ($items as $item) {
        $total_amount += floatval($item['price']);
    }
    $result['total_amount'] = $total_amount;
    
    // 如果是余额支付，先检查余额是否足够
    if ($paymentMethod === 'balance' && $user_id) {
        $balance_sql = "SELECT balance FROM users WHERE id = ?";
        if ($balance_stmt = mysqli_prepare($conn, $balance_sql)) {
            mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
            mysqli_stmt_execute($balance_stmt);
            $balance_result = mysqli_stmt_get_result($balance_stmt);
            $balance_row = mysqli_fetch_assoc($balance_result);
            mysqli_stmt_close($balance_stmt);
            
            if (!$balance_row || $balance_row['balance'] < $total_amount) {
                $result['message'] = 'Insufficient balance';
                return $result;
            }
        } else {
            $result['message'] = 'Failed to check balance';
            return $result;
        }
    }
    
    // 开始事务
    mysqli_begin_transaction($conn);
    
    try {
        $order_ids = [];
        
        // 为每个商品创建订单
        foreach ($items as $item) {
            $order_id = generateOrderId($paymentMethod === 'balance' ? 'MULTI_BAL' : 'MULTI_PP');
            $order_ids[] = $order_id;
            
            // 根据商品类型创建订单记录
            if ($item['item_type'] === 'hair') {
                // 创建头发订单 - 使用与现有系统兼容的格式
                $email_source = 'session';
                $transaction_id = $order_id; // 使用订单ID作为交易ID
                $purchase_type = ($paymentMethod === 'balance') ? 'balance' : 'paypal';
                
                $insert_sql = "INSERT INTO hair_purchases (user_id, email, email_source, hair_id, order_id, transaction_id, amount, purchase_date, email_sent, purchase_type) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?)";
                if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, "ississds", 
                        $user_id, $to, $email_source, $item['item_id'], $order_id, $transaction_id, $item['price'], $purchase_type);
                    if (!mysqli_stmt_execute($insert_stmt)) {
                        throw new Exception("Failed to create hair order: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($insert_stmt);
                }
            } else {
                // 创建产品订单 - 使用与现有系统兼容的格式
                $email_source = 'session';
                $transaction_id = $order_id; // 使用订单ID作为交易ID
                $is_photo_pack = ($item['item_type'] === 'photo_pack') ? 1 : 0;
                
                // 根据商品类型和支付方式设置purchase_type
                if ($paymentMethod === 'balance') {
                    $purchase_type = 'balance';
                } else {
                    // PayPal支付时，根据商品类型设置purchase_type，与单个产品PayPal支付保持一致
                    $purchase_type = $is_photo_pack ? 'photo_pack' : 'product';
                }
                
                $insert_sql = "INSERT INTO purchases (user_id, email, email_source, product_id, order_id, transaction_id, is_photo_pack, amount, purchase_date, email_sent, purchase_type) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?)";
                if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, "ississids", 
                        $user_id, $to, $email_source, $item['item_id'], $order_id, $transaction_id, $is_photo_pack, $item['price'], $purchase_type);
                    if (!mysqli_stmt_execute($insert_stmt)) {
                        throw new Exception("Failed to create product order: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($insert_stmt);
                }
            }
        }
        
        // 发送包含所有商品的邮件
        $email_sent = sendMultiProductEmail($to, $username, $items, $order_ids, $total_amount, $paymentMethod, $maxRetries);
        
        if (!$email_sent) {
            throw new Exception("Failed to send confirmation email");
        }
        
        // 如果是余额支付，扣除余额
        if ($paymentMethod === 'balance' && $user_id) {
            $update_balance_sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
            if ($update_stmt = mysqli_prepare($conn, $update_balance_sql)) {
                mysqli_stmt_bind_param($update_stmt, "di", $total_amount, $user_id);
                if (!mysqli_stmt_execute($update_stmt)) {
                    throw new Exception("Failed to update user balance");
                }
                mysqli_stmt_close($update_stmt);
            } else {
                throw new Exception("Failed to prepare balance update statement");
            }
        }
        
        // 更新邮件发送状态
        foreach ($items as $index => $item) {
            $order_id = $order_ids[$index];
            if ($item['item_type'] === 'hair') {
                updateHairEmailSentStatus($conn, $order_id);
            } else {
                updateProductEmailSentStatus($conn, $order_id);
            }
        }
        
        // 提交事务
        mysqli_commit($conn);
        
        $result['success'] = true;
        $result['order_ids'] = $order_ids;
        $result['message'] = 'Purchase completed successfully';
        
    } catch (Exception $e) {
        // 回滚事务
        mysqli_rollback($conn);
        $result['message'] = $e->getMessage();
        error_log("Multi-product purchase failed: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * 发送多商品购买确认邮件
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @param array $items 商品数组
 * @param array $order_ids 订单ID数组
 * @param float $total_amount 总金额
 * @param string $paymentMethod 支付方式
 * @param int $maxRetries 最大重试次数
 * @return bool 是否发送成功
 */
function sendMultiProductEmail($to, $username, $items, $order_ids, $total_amount, $paymentMethod = 'balance', $maxRetries = 5) {
    // 检查邮件发送频率限制
    if (isset($_SESSION['last_multi_email_sent']) && (time() - $_SESSION['last_multi_email_sent']) < 30) {
        error_log("Multi-product email sending limited: Last email was sent less than 30 seconds ago");
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
                error_log("Retry attempt #" . $retryCount . " for sending multi-product email to: " . $to);
            } else {
                error_log("Starting multi-product confirmation email to: " . $to);
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
            $mail->Subject = 'Purchase Confirmation - Multiple Items - Orders #' . implode(', #', $order_ids);
            
            // 构建邮件正文
            $mail->Body = generateMultiProductEmailTemplate($username, $items, $order_ids, $total_amount, $paymentMethodDisplay);
            
            // 发送邮件
            $mail->send();
            $success = true;
            
            // 记录成功发送时间
            $_SESSION['last_multi_email_sent'] = time();
            
            $end_time = microtime(true);
            $execution_time = round(($end_time - $start_time) * 1000, 2);
            
            error_log("Multi-product confirmation email sent successfully to: " . $to . " in " . $execution_time . "ms");
            
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            error_log("Multi-product confirmation email failed (attempt " . ($retryCount + 1) . "): " . $lastError);
            $retryCount++;
            
            // 如果不是最后一次重试，等待一段时间再重试
            if ($retryCount < $maxRetries) {
                sleep(1); // 等待1秒再重试
            }
        }
    }
    
    if (!$success) {
        error_log("All multi-product confirmation email attempts failed for: " . $to . ". Last error: " . $lastError);
    }
    
    return $success;
}

/**
 * 生成多商品购买确认邮件HTML模板
 * @param string $username 用户名
 * @param array $items 商品数组
 * @param array $order_ids 订单ID数组
 * @param float $total_amount 总金额
 * @param string $paymentMethod 支付方式显示名称
 * @return string HTML邮件内容
 */
function generateMultiProductEmailTemplate($username, $items, $order_ids, $total_amount, $paymentMethod) {
    $currentDate = date('F j, Y');
    
    // 构建商品列表HTML
    $productListHtml = '';
    $url_signer = new UrlSigner();
    $baseUrl = "https://vip.fhaircut.com";
    
    foreach ($items as $index => $item) {
        $order_id = $order_ids[$index];
        $itemTitle = htmlspecialchars($item['title'] ?? 'Product');
        $itemId = htmlspecialchars($item['item_id'] ?? '');
        $itemPrice = number_format($item['price'], 2);
        
        // 根据商品类型生成不同的显示内容
        if ($item['item_type'] === 'hair') {
            $itemType = 'Hair Product';
            $downloadLink = ''; // 头发商品通常没有下载链接
            
            // 构建头发详细信息
            $detailsHtml = '';
            if (!empty($item['hair_description'])) {
                $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Description:</strong> ' . htmlspecialchars($item['hair_description']) . '</p>';
            }
            if (isset($item['hair_length']) && $item['hair_length'] > 0) {
                $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Length:</strong> ' . number_format($item['hair_length'], 1) . ' cm</p>';
            }
            if (isset($item['hair_weight']) && $item['hair_weight'] > 0) {
                $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Weight:</strong> ' . number_format($item['hair_weight'], 1) . ' g</p>';
            }
            if (isset($item['hair_value']) && $item['hair_value'] > 0) {
                $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Estimated Value:</strong> $' . number_format($item['hair_value'], 2) . '</p>';
            }
        } else {
            $itemType = ($item['item_type'] === 'photo_pack') ? 'Photo Pack' : 'Video';
            
            // 构建产品详细信息
            $detailsHtml = '';
            if (!empty($item['product_subtitle'])) {
                $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Subtitle:</strong> ' . htmlspecialchars($item['product_subtitle']) . '</p>';
            }
            if (isset($item['product_sales']) && $item['product_sales'] > 0) {
                $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Sales:</strong> ' . number_format($item['product_sales']) . ' purchases</p>';
            }
            
            // 图片包详细信息
            if ($item['item_type'] === 'photo_pack') {
                if (isset($item['paid_photos_count']) && $item['paid_photos_count'] > 0) {
                    $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Photos:</strong> ' . number_format($item['paid_photos_count']) . ' images</p>';
                }
                if (isset($item['paid_photos_total_size']) && $item['paid_photos_total_size'] > 0) {
                    $sizeInMB = round($item['paid_photos_total_size'] / (1024 * 1024), 1);
                    $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Total Size:</strong> ' . $sizeInMB . ' MB</p>';
                }
                if (!empty($item['paid_photos_formats'])) {
                    $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Formats:</strong> ' . htmlspecialchars($item['paid_photos_formats']) . '</p>';
                }
            }
            
            // 视频详细信息
            if ($item['item_type'] === 'video') {
                if (isset($item['paid_video_size']) && $item['paid_video_size'] > 0) {
                    $sizeInMB = round($item['paid_video_size'] / (1024 * 1024), 1);
                    $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Video Size:</strong> ' . $sizeInMB . ' MB</p>';
                }
                if (isset($item['paid_video_duration']) && $item['paid_video_duration'] > 0) {
                    $minutes = floor($item['paid_video_duration'] / 60);
                    $seconds = $item['paid_video_duration'] % 60;
                    $detailsHtml .= '<p style="margin: 5px 0; color: #666; font-size: 13px;"><strong>Duration:</strong> ' . $minutes . ':' . sprintf('%02d', $seconds) . '</p>';
                }
            }
            
            // 生成下载链接 - 检查字段是否存在
            $productLink = '';
            if ($item['item_type'] === 'photo_pack' && isset($item['paid_photos_zip'])) {
                $productLink = $item['paid_photos_zip'];
            } elseif ($item['item_type'] === 'video' && isset($item['paid_video'])) {
                $productLink = $item['paid_video'];
            }
            
            if (!empty($productLink)) {
                $downloadUrl = $baseUrl . "/download.php?file=" . urlencode($productLink);
                $signedUrl = $url_signer->signUrl($downloadUrl);
                
                // 根据商品类型确定下载按钮文本
                $buttonText = 'Download ' . ($item['item_type'] === 'photo_pack' ? 'Photo Pack' : 'Video');
                
                $downloadLink = '
                    <div style="background-color: #e8f4ff; padding: 15px; margin: 10px 0; border-radius: 5px;">
                        <h4 style="margin-top: 0; color: #0056b3; font-size: 14px;">Download Link</h4>
                        <p style="margin: 5px 0; font-size: 13px;">Click the button below to access your purchased content:</p>
                        <div style="text-align: center; margin: 15px 0;">
                            <a href="' . htmlspecialchars($signedUrl) . '" style="background-color: #9E9BC7; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px;">' . $buttonText . '</a>
                        </div>
                        <p style="font-size: 11px; margin: 5px 0;">Or copy this link: ' . htmlspecialchars($signedUrl) . '</p>
                        <p style="color: #ff6600; font-size: 12px; margin: 5px 0;"><strong>Note:</strong> This download link will expire in 48 hours for security reasons.</p>
                    </div>';
            } else {
                $downloadLink = '
                    <div style="background-color: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px;">
                        <h4 style="margin-top: 0; color: #666; font-size: 14px;">Download Link</h4>
                        <p style="color: #999; font-size: 13px; margin: 5px 0;">Download link will be available after processing</p>
                    </div>';
            }
        }
        
        // 产品图片已删除 - 不在邮件中显示图片
        $productImageHtml = '';

        $productListHtml .= '
            <tr>
                <td style="padding: 15px; border-bottom: 1px solid #eee;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0; color: #333; font-size: 16px;">' . $itemTitle . '</h4>
                            <p style="margin: 0 0 5px 0; color: #666; font-size: 14px;">' . $itemType . ' (ID: ' . $itemId . ')</p>
                            <p style="margin: 0 0 5px 0; color: #666; font-size: 14px;"><strong>Order ID:</strong> ' . htmlspecialchars($order_id) . '</p>
                            ' . $detailsHtml . '
                            ' . $downloadLink . '
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 18px; font-weight: bold; color: #e91e63;">$' . $itemPrice . '</span>
                        </div>
                    </div>
                </td>
            </tr>';
    }
    
    return '
    <div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            
            <!-- Header -->
            <div style="text-align: center; border-bottom: 2px solid #e91e63; padding-bottom: 20px; margin-bottom: 30px;">
                <h1 style="color: #e91e63; margin: 0; font-size: 28px;">Jianfa</h1>
                <p style="color: #666; margin: 5px 0 0 0; font-size: 16px;">Purchase Confirmation - Multiple Items</p>
            </div>
            
            <!-- Greeting -->
            <div style="margin-bottom: 25px;">
                <h2 style="color: #333; margin: 0 0 10px 0; font-size: 24px;">Hello ' . htmlspecialchars($username) . '!</h2>
                <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0;">
                    Thank you for your multiple item purchase! We are excited to confirm that all your orders have been successfully processed.
                </p>
            </div>
            
            <!-- Order Summary -->
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <h3 style="color: #e91e63; margin: 0 0 15px 0; font-size: 18px;">Order Summary</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold; width: 40%;">Total Items:</td>
                        <td style="padding: 8px 0; color: #333;">' . count($items) . ' items</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-weight: bold;">Total Amount:</td>
                        <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 20px;">$' . number_format($total_amount, 2) . '</td>
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
            
            <!-- Product List -->
            <div style="margin-bottom: 25px;">
                <h3 style="color: #e91e63; margin: 0 0 15px 0; font-size: 18px;">Your Items</h3>
                <div style="background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse;">
                        ' . $productListHtml . '
                        <tr style="background-color: #f8f9fa;">
                            <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #e91e63;">
                                Total: $' . number_format($total_amount, 2) . '
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Success Message -->
            <div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-bottom: 25px;">
                <div style="color: #155724; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px;">All Purchases Successful!</h3>
                    <p style="margin: 0; font-size: 14px;">Your ' . count($items) . ' item(s) have been confirmed and processed successfully.</p>
                </div>
            </div>
            
            <!-- Download Notice -->
            <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 25px;">
                <p style="margin: 0; color: #0c5460; font-size: 14px;">
                    <strong>Download Instructions:</strong> For digital products (videos and photo packs), download links are provided above and are valid for 48 hours. Hair products do not require downloads.
                </p>
            </div>
            
            <!-- Important Notice -->
            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 25px;">
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    <strong>Important:</strong> Please keep this email for your records. Each item has been assigned a separate order ID for tracking purposes. If you have any questions about your purchases or need support, please contact our customer service team.
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
 * 更新产品订单的邮件发送状态
 * @param mysqli $conn 数据库连接
 * @param string $orderID 订单ID
 * @return bool 是否更新成功
 */
function updateProductEmailSentStatus($conn, $orderID) {
    $update_sql = "UPDATE purchases SET email_sent = 1 WHERE order_id = ?";
    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "s", $orderID);
        $result = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        return $result;
    }
    return false;
}

/**
 * 生成订单ID
 * @param string $prefix 前缀
 * @return string 订单ID
 */
function generateOrderId($prefix = 'MULTI') {
    return $prefix . '_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));
}
?>
