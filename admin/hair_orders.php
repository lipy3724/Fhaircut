<?php
/**
 * 头发订单管理页面
 * 专门管理hair_purchases表中的头发购买订单
 */

// 检查是否通过管理界面访问
if (!defined('ADMIN_ACCESS')) {
    header('Location: ../admin.php?page=hair_orders');
    exit;
}

// 数据库连接已经在 admin.php 中建立，无需重复引用
// 引用头发邮件函数 - 使用绝对路径避免路径问题
require_once dirname(__DIR__) . '/hair_email_functions.php';

// 旧的邮件发送函数已被移除，现在使用 hair_email_functions.php 中的统一函数

// 处理操作请求
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'resend_email':
                // 设置响应头为JSON
                header('Content-Type: application/json');
                
                $record_id = $_POST['order_id'] ?? '';
                if (empty($record_id)) {
                    echo json_encode(['status' => 'error', 'message' => '无效的订单ID']);
                    exit;
                }
                
                try {
                    // 获取订单信息 - 使用记录ID而不是order_id
                    $query = "SELECT hp.*, h.title as hair_title, h.value as hair_value, u.username 
                             FROM hair_purchases hp 
                             LEFT JOIN hair h ON hp.hair_id = h.id 
                             LEFT JOIN users u ON hp.user_id = u.id 
                             WHERE hp.id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    if (!$stmt) {
                        throw new Exception('数据库查询准备失败: ' . mysqli_error($conn));
                    }
                    
                    mysqli_stmt_bind_param($stmt, "i", $record_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $order = mysqli_fetch_assoc($result);
                    
                    if (!$order) {
                        echo json_encode(['status' => 'error', 'message' => '订单不存在']);
                        exit;
                    }
                    
                    // 检查邮箱是否有效
                    if (empty($order['email'])) {
                        echo json_encode(['status' => 'error', 'message' => '订单邮箱为空']);
                        exit;
                    }
                    
                    // 构建头发信息数组
                    $hair_info = [
                        'id' => $order['hair_id'],
                        'title' => $order['hair_title'] ?? '头发产品',
                        'value' => $order['hair_value'] ?? ''
                    ];
                    
                    $username = $order['username'] ?? '游客';
                    
                    // 记录重发邮件尝试
                    error_log("Attempting to resend hair email for record ID: " . $record_id . " (Order: " . $order['order_id'] . ") to: " . $order['email']);
                    
                    // 发送邮件 - 使用新的统一邮件函数
                    $emailSent = sendHairPurchaseEmail(
                        $order['email'], 
                        $username, 
                        $hair_info, 
                        $order['order_id'], 
                        $order['amount'], 
                        $order['purchase_type'] ?? 'balance'
                    );
                    
                    if ($emailSent) {
                        // 更新邮件发送状态
                        $update_sql = "UPDATE hair_purchases SET email_sent = 1 WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, "i", $record_id);
                        $updateResult = mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                        
                        if ($updateResult) {
                            error_log("Hair email resent successfully for record ID: " . $record_id);
                            echo json_encode([
                                'status' => 'success', 
                                'message' => '邮件发送成功',
                                'order_id' => $order['order_id'],
                                'email' => $order['email']
                            ]);
                        } else {
                            error_log("Hair email sent but failed to update database for record ID: " . $record_id);
                            echo json_encode([
                                'status' => 'warning', 
                                'message' => '邮件发送成功，但状态更新失败'
                            ]);
                        }
                    } else {
                        error_log("Failed to resend hair email for record ID: " . $record_id);
                        echo json_encode([
                            'status' => 'error', 
                            'message' => '邮件发送失败，请稍后重试'
                        ]);
                    }
                    
                    mysqli_stmt_close($stmt);
                    
                } catch (Exception $e) {
                    error_log("Error in resend hair email: " . $e->getMessage());
                    echo json_encode([
                        'status' => 'error', 
                        'message' => '系统错误：' . $e->getMessage()
                    ]);
                }
                exit;
                break;
                
            case 'delete_order':
                $order_id = $_POST['order_id'] ?? '';
                if ($order_id) {
                    $delete_sql = "DELETE FROM hair_purchases WHERE order_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_sql);
                    mysqli_stmt_bind_param($delete_stmt, "s", $order_id);
                    
                    if (mysqli_stmt_execute($delete_stmt)) {
                        $success_message = "订单删除成功！";
                    } else {
                        $error_message = "删除失败：" . mysqli_error($conn);
                    }
                    mysqli_stmt_close($delete_stmt);
                }
                break;
        }
    }
}

// 获取筛选参数
$search_order_id = $_GET['search_order_id'] ?? '';
$search_email = $_GET['search_email'] ?? '';
$email_status = $_GET['email_status'] ?? '';
$email_source = $_GET['email_source'] ?? '';
$items_per_page = (int)($_GET['items_per_page'] ?? 25);
$current_page = (int)($_GET['current_page'] ?? 1);

// 构建查询条件
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search_order_id)) {
    $where_conditions[] = "hp.order_id LIKE ?";
    $params[] = "%$search_order_id%";
    $types .= "s";
}

if (!empty($search_email)) {
    $where_conditions[] = "hp.email LIKE ?";
    $params[] = "%$search_email%";
    $types .= "s";
}

if ($email_status !== '') {
    $where_conditions[] = "hp.email_sent = ?";
    $params[] = (int)$email_status;
    $types .= "i";
}

if (!empty($email_source)) {
    $where_conditions[] = "hp.email_source = ?";
    $params[] = $email_source;
    $types .= "s";
}

// 计算总记录数
$count_sql = "SELECT COUNT(*) as total FROM hair_purchases hp";
if (!empty($where_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($count_stmt);

// 计算分页
$offset = ($current_page - 1) * $items_per_page;
$total_pages = ceil($total_records / $items_per_page);

// 构建数据查询
$sql = "SELECT hp.*, 
        h.title as hair_title, 
        h.value as hair_value,
        u.username 
        FROM hair_purchases hp 
        LEFT JOIN hair h ON hp.hair_id = h.id 
        LEFT JOIN users u ON hp.user_id = u.id";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY hp.id DESC LIMIT ?, ?";

// 准备并执行查询
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    // 添加分页参数
    $limit_types = $types . "ii";
    $limit_params = $params;
    $limit_params[] = $offset;
    $limit_params[] = $items_per_page;
    
    mysqli_stmt_bind_param($stmt, $limit_types, ...$limit_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $hair_orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $hair_orders[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("头发订单查询失败: " . mysqli_error($conn));
    $hair_orders = [];
}

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>头发订单管理</title>
    <link rel="stylesheet" href="css/data-table.css">
    <style>
        .hair-orders-container {
            padding: 20px;
        }
        
        h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        /* 筛选表单样式 - 与订单管理一致 */
        .filter-form {
            margin-bottom: 10px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .form-row {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 10px;
        }
        
        .form-group {
            margin-right: 10px;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }
        
        .form-group label {
            margin-right: 5px;
            font-weight: normal;
            white-space: nowrap;
        }
        
        .form-group input,
        .form-group select {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-button {
            background-color: #e75480;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .filter-button:hover {
            background-color: #d64072;
        }
        
        .reset-button {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #ddd;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        
        .reset-button:hover {
            background-color: #e9ecef;
        }
        
        /* 表格容器 */
        .table-container {
            margin-top: 0;
            overflow-x: auto;
        }
        
        /* 表格样式 - 使用与订单管理一致的样式 */
        .data-table {
            min-width: 1200px;
            table-layout: fixed;
            width: 100%;
        }
        
        .data-table th {
            background-color: #ffccd5;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table tbody tr:hover {
            background-color: #fff5f7;
        }
        
        .data-table td {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .data-table td.order-id-cell {
            white-space: normal;
            word-break: break-all;
            overflow: visible;
        }
        
        /* 状态标签样式 - 与订单管理一致 */
        .status-badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .status-badge.success {
            background-color: #28a745;
        }
        
        .status-badge.danger {
            background-color: #dc3545;
        }
        
        /* 操作按钮样式 - 与订单管理一致 */
        .resend-email-btn {
            background-color: #ffccd5 !important;
            color: #333 !important;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .resend-email-btn:hover {
            background-color: #ffb3c1 !important;
        }
        
        .source-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .source-session {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .source-paypal {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .source-balance {
            background: #e8f5e8;
            color: #2e7d2e;
        }
        
        /* 分页样式 - 与订单管理一致 */
        .pagination-container {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            text-align: center;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .page-link {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            text-decoration: none;
            border: 1px solid #e75480;
            color: #e75480;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background-color: #e75480;
            color: white;
        }
        
        .page-link.current {
            background-color: #e75480;
            color: white;
        }
        
        .items-per-page {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        
        /* 无数据样式 */
        .no-data {
            text-align: center;
            color: #777;
            padding: 20px;
        }
        
        /* 成功/错误消息 */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        /* 操作按钮 */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        /* 响应式 */
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .orders-table {
                font-size: 12px;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 8px 4px;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="hair-orders-container">
        <h3>头发订单管理</h3>

        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- 搜索筛选表单 -->
        <form method="GET" class="filter-form">
            <input type="hidden" name="page" value="hair_orders">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="search_order_id">订单ID:</label>
                    <input type="text" id="search_order_id" name="search_order_id" 
                           value="<?php echo htmlspecialchars($search_order_id); ?>" 
                           placeholder="输入订单ID" style="width: 150px;">
                </div>
                
                <div class="form-group">
                    <label for="email_status">邮件状态:</label>
                    <select id="email_status" name="email_status">
                        <option value="">全部</option>
                        <option value="1" <?php echo $email_status === '1' ? 'selected' : ''; ?>>已发送</option>
                        <option value="0" <?php echo $email_status === '0' ? 'selected' : ''; ?>>未发送</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="email_source">邮箱来源:</label>
                    <select id="email_source" name="email_source">
                        <option value="">全部</option>
                        <option value="session" <?php echo $email_source === 'session' ? 'selected' : ''; ?>>网站账号</option>
                        <option value="paypal" <?php echo $email_source === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                        <option value="balance" <?php echo $email_source === 'balance' ? 'selected' : ''; ?>>余额支付</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="filter-button">筛选</button>
                    <?php if (!empty($search_order_id) || $email_status !== '' || $email_source !== ''): ?>
                    <a href="admin.php?page=hair_orders" class="reset-button">重置</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>日期</th>
                        <th>产品</th>
                        <th>客户</th>
                        <th>邮箱</th>
                        <th>来源</th>
                        <th>订单ID</th>
                        <th>类型</th>
                        <th>金额</th>
                        <th>邮件状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (!empty($hair_orders)) {
                        foreach ($hair_orders as $index => $order): 
                            $row_id = ($current_page - 1) * $items_per_page + $index + 1;
                    ?>
                        <tr>
                            <td><?php echo $row_id; ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($order['purchase_date'])); ?></td>
                            <td><?php echo htmlspecialchars($order['hair_title'] ?? '头发产品'); ?> (ID: <?php echo $order['hair_id']; ?>)</td>
                            <td><?php echo !empty($order['username']) ? htmlspecialchars($order['username']) : '游客'; ?></td>
                            <td class="email-cell" title="<?php echo htmlspecialchars($order['email']); ?>"><?php echo htmlspecialchars($order['email']); ?></td>
                            <td><?php echo ($order['email_source'] === 'session' ? '网站账号' : ($order['email_source'] === 'paypal' ? 'PayPal' : '余额支付')); ?></td>
                            <td class="order-id-cell" title="<?php echo htmlspecialchars($order['order_id']); ?>" style="max-width:200px; word-break:break-all; white-space:normal;"><?php echo htmlspecialchars($order['order_id']); ?></td>
                            <td>余额支付 - 头发</td>
                            <td>¥<?php echo number_format($order['amount'] ?? 0, 2); ?></td>
                            <td>
                                <?php if ($order['email_sent']): ?>
                                    <span class="status-badge success">已发送</span>
                                <?php else: ?>
                                    <span class="status-badge danger">未发送</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="javascript:void(0);" class="resend-email-btn" onclick="resendEmail(<?php echo $order['id']; ?>)">重发邮件</a>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    } else {
                        echo "<tr><td colspan='11' class='no-data'>没有找到头发订单记录</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- 分页控件 -->
        <div class="pagination-container">
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="admin.php?page=hair_orders&current_page=1&search_order_id=<?php echo urlencode($search_order_id); ?>&email_status=<?php echo $email_status; ?>&email_source=<?php echo urlencode($email_source); ?>" class="page-link">首页</a>
                    <a href="admin.php?page=hair_orders&current_page=<?php echo $current_page - 1; ?>&search_order_id=<?php echo urlencode($search_order_id); ?>&email_status=<?php echo $email_status; ?>&email_source=<?php echo urlencode($email_source); ?>" class="page-link">上一页</a>
                <?php endif; ?>
                
                <?php
                // 显示页码
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $current_page): ?>
                        <span class="page-link current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="admin.php?page=hair_orders&current_page=<?php echo $i; ?>&search_order_id=<?php echo urlencode($search_order_id); ?>&email_status=<?php echo $email_status; ?>&email_source=<?php echo urlencode($email_source); ?>" class="page-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="admin.php?page=hair_orders&current_page=<?php echo $current_page + 1; ?>&search_order_id=<?php echo urlencode($search_order_id); ?>&email_status=<?php echo $email_status; ?>&email_source=<?php echo urlencode($email_source); ?>" class="page-link">下一页</a>
                    <a href="admin.php?page=hair_orders&current_page=<?php echo $total_pages; ?>&search_order_id=<?php echo urlencode($search_order_id); ?>&email_status=<?php echo $email_status; ?>&email_source=<?php echo urlencode($email_source); ?>" class="page-link">末页</a>
                <?php endif; ?>
            </div>
            
            <div class="pagination-info">
                显示 <?php echo min(($current_page - 1) * $items_per_page + 1, $total_records); ?> - <?php echo min($current_page * $items_per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
            </div>
        </div>
    </div>

    <!-- 添加加载中弹窗 -->
    <div id="email-sending-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999; display: none; justify-content: center; align-items: center;">
        <div id="email-sending-content" style="background-color: white; padding: 30px; border-radius: 8px; text-align: center; max-width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <div style="margin-bottom: 20px; font-size: 18px; color: #333;">正在发送邮件...</div>
            <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #e75480; border-radius: 50%; margin: 0 auto 20px; animation: spin 1s linear infinite;"></div>
            <div>请稍候，邮件发送可能需要几秒钟时间</div>
        </div>
    </div>

    <script>
        // 自动提交表单当改变每页显示数量时
        document.getElementById('items_per_page').addEventListener('change', function() {
            this.form.submit();
        });
        
        // 重发邮件函数 - 更新为与产品订单一致的弹窗提示
        function resendEmail(orderId) {
            if (!orderId) {
                alert('订单ID无效');
                return;
            }
            
            // 确认对话框
            if (!confirm('确定要重新发送邮件吗？')) {
                return;
            }
            
            // 显示加载中弹窗
            document.getElementById("email-sending-overlay").style.display = "flex";
            
            // 发送AJAX请求
            const formData = new FormData();
            formData.append('action', 'resend_email');
            formData.append('order_id', orderId);
            
            fetch('admin.php?page=hair_orders', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // 首先检查响应状态
                if (!response.ok) {
                    throw new Error('网络响应错误: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // 更新加载中弹窗内容
                const overlay = document.getElementById("email-sending-overlay");
                const overlayContent = document.getElementById("email-sending-content");
                
                if (data.status === 'success') {
                    // 成功发送邮件
                    overlayContent.innerHTML = `
                        <div style="margin-bottom: 20px; font-size: 18px; color: #28a745;">邮件发送成功</div>
                        <div style="margin-bottom: 20px;">邮件已成功发送至 ${data.email || '指定邮箱'}</div>
                        <div style="margin-bottom: 10px;">订单ID: ${data.order_id || '未知'}</div>
                        <div style="margin-top: 20px;">
                            <button onclick="location.reload()" style="background-color: #e75480; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">刷新页面</button>
                        </div>
                    `;
                    
                    // 3秒后自动刷新页面
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else if (data.status === 'warning') {
                    // 发送成功但有警告
                    overlayContent.innerHTML = `
                        <div style="margin-bottom: 20px; font-size: 18px; color: #ffc107;">发送完成（有警告）</div>
                        <div style="margin-bottom: 20px;">${data.message}</div>
                        <div style="margin-top: 20px;">
                            <button onclick="location.reload()" style="background-color: #e75480; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">刷新页面</button>
                        </div>
                    `;
                    
                    // 3秒后自动刷新页面
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    // 发送失败
                    overlayContent.innerHTML = `
                        <div style="margin-bottom: 20px; font-size: 18px; color: #dc3545;">邮件发送失败</div>
                        <div style="margin-bottom: 20px;">${data.message || '未知错误'}</div>
                        <div style="margin-top: 20px;">
                            <button onclick="document.getElementById('email-sending-overlay').style.display='none'" style="background-color: #e75480; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">关闭</button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('发送请求时出错:', error);
                
                // 显示错误弹窗
                const overlayContent = document.getElementById("email-sending-content");
                overlayContent.innerHTML = `
                    <div style="margin-bottom: 20px; font-size: 18px; color: #dc3545;">请求失败</div>
                    <div style="margin-bottom: 20px;">发送请求时出错: ${error.message}</div>
                    <div style="margin-top: 20px;">
                        <button onclick="document.getElementById('email-sending-overlay').style.display='none'" style="background-color: #e75480; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">关闭</button>
                    </div>
                `;
            });
        }
        
        // 添加加载动画
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form[method="POST"]');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const button = form.querySelector('button[type="submit"]');
                    if (button) {
                        button.disabled = true;
                        button.textContent = '处理中...';
                        setTimeout(function() {
                            button.disabled = false;
                            button.textContent = button.textContent.replace('处理中...', '');
                        }, 3000);
                    }
                });
            });
        });
    </script>

    <style>
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
</body>
</html>
