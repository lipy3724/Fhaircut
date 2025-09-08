<?php
// 确保此文件只能通过主管理页面访问
if (!defined('ADMIN_ACCESS')) {
    header("location: ../admin.php");
    exit;
}

// 处理AJAX重发邮件请求
if (isset($_GET['ajax']) && $_GET['ajax'] === 'resend_email' && isset($_POST['id'])) {
    $purchase_id = intval($_POST['id']);
    $response = array('success' => false, 'message' => '');
    
    // 获取购买记录
    $sql = "SELECT p.*, pr.title, pr.paid_video, pr.paid_photos_zip, pr.price, pr.photo_pack_price, u.username 
            FROM purchases p 
            LEFT JOIN products pr ON p.product_id = pr.id 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE p.id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // 确保PHP 7.4.33兼容性
        mysqli_stmt_bind_param($stmt, 'i', $purchase_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($purchase = mysqli_fetch_assoc($result)) {
            // 检查产品是否已删除
            if ($purchase['title'] === null) {
                $response['message'] = "产品已删除，无法发送邮件";
            } else {
            // 准备产品数据
            $product = [
                'id' => $purchase['product_id'],
                'title' => $purchase['title'],
                'paid_video' => $purchase['paid_video'],
                'paid_photos_zip' => $purchase['paid_photos_zip'],
                'price' => $purchase['price'],
                'photo_pack_price' => $purchase['photo_pack_price']
            ];
            
            // 获取用户名
            $username = !empty($purchase['username']) ? $purchase['username'] : 'Customer';
            
            // 重发邮件
            require_once __DIR__ . '/../email_functions.php';
            $email_sent = sendPurchaseConfirmationEmail(
                $purchase['email'],
                $username,
                $product,
                $purchase['order_id'],
                $purchase['is_photo_pack']
            );
            
            // 更新邮件发送状态
            if ($email_sent) {
                $update_sql = "UPDATE purchases SET email_sent = 1 WHERE id = ?";
                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                    // 确保PHP 7.4.33兼容性
                    mysqli_stmt_bind_param($update_stmt, 'i', $purchase_id);
                    $update_result = mysqli_stmt_execute($update_stmt);
                    $affected_rows = mysqli_stmt_affected_rows($update_stmt);
                    error_log("Admin resend - Update email_sent status: " . ($update_result ? "Success" : "Failed") . 
                             ", Affected rows: " . $affected_rows . 
                             ", Purchase ID: " . $purchase_id);
                    mysqli_stmt_close($update_stmt);
                }
                
                    $response['success'] = true;
                    $response['message'] = "邮件已成功重新发送。";
                    $response['email'] = $purchase['email'];
            } else {
                    $response['message'] = "邮件发送失败，请重试。";
                }
            }
        } else {
            $response['message'] = "未找到购买记录。";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = "数据库查询失败。";
    }
    
    // 返回JSON响应
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 获取筛选条件
$product_filter = isset($_GET['product_search']) ? trim($_GET['product_search']) : '';
$email_filter = isset($_GET['email_sent']) ? intval($_GET['email_sent']) : -1;
$source_filter = isset($_GET['email_source']) ? $_GET['email_source'] : '';
$type_filter = isset($_GET['purchase_type']) ? $_GET['purchase_type'] : '';

// 分页参数
$items_per_page = 10; // 每页显示10条记录
$current_page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
$current_page = max(1, $current_page); // 确保页码至少为1
$offset = ($current_page - 1) * $items_per_page;

// 构建查询条件
$where_conditions = [];
$params = [];
$types = "";

if (!empty($product_filter)) {
    // 按订单ID搜索
    $where_conditions[] = "p.order_id LIKE ?";
    $params[] = "%" . $product_filter . "%";
    $types .= "s";
}

if ($email_filter !== -1) {
    $where_conditions[] = "p.email_sent = ?";
    $params[] = $email_filter;
    $types .= "i";
}

if ($source_filter !== '') {
    $where_conditions[] = "p.email_source = ?";
    $params[] = $source_filter;
    $types .= "s";
}

if ($type_filter !== '') {
    $where_conditions[] = "p.purchase_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

// 构建完整查询
$sql_count = "SELECT COUNT(*) as total FROM purchases p 
              LEFT JOIN products pr ON p.product_id = pr.id 
              LEFT JOIN users u ON p.user_id = u.id";

if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
}

// 准备并执行计数查询
$stmt_count = mysqli_prepare($conn, $sql_count);
if ($stmt_count && !empty($params)) {
    // 使用call_user_func_array来绑定参数，以确保PHP 7.4.33兼容性
    // 修复：将参数转换为引用
    $bind_params = array($stmt_count, $types);
    foreach ($params as &$param) {
        $bind_params[] = &$param;
    }
    call_user_func_array('mysqli_stmt_bind_param', $bind_params);
}

$total_records = 0;
if ($stmt_count) {
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    if ($row_count = mysqli_fetch_assoc($result_count)) {
        $total_records = $row_count['total'];
    }
    mysqli_stmt_close($stmt_count);
}

// 计算总页数
$total_pages = ceil($total_records / $items_per_page);

// 构建数据查询
$sql = "SELECT p.*, 
        CASE 
            WHEN p.purchase_type = 'activation' THEN '账号激活' 
            ELSE pr.title 
        END as product_title, 
        pr.id as original_product_id,
        u.username 
        FROM purchases p 
        LEFT JOIN products pr ON p.product_id = pr.id 
        LEFT JOIN users u ON p.user_id = u.id";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY p.purchase_date DESC LIMIT ?, ?";

// 添加调试日志
error_log("查询订单SQL: " . $sql);
if (!empty($params)) {
    error_log("查询参数: " . print_r($params, true));
}

// 准备并执行查询
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    // 添加分页参数
    $limit_types = $types . "ii";
    $limit_params = $params;
    $limit_params[] = $offset;
    $limit_params[] = $items_per_page;
    
    // 使用call_user_func_array来绑定参数，以确保PHP 7.4.33兼容性
    // 修复：将参数转换为引用
    $bind_params = array($stmt, $limit_types);
    foreach ($limit_params as &$param) {
        $bind_params[] = &$param;
    }
    call_user_func_array('mysqli_stmt_bind_param', $bind_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $purchases = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $purchases[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    // 如果查询失败，记录错误
    error_log("查询失败: " . mysqli_error($conn));
    $purchases = [];
}

// 获取产品列表（用于筛选）
$products_sql = "SELECT id, title FROM products ORDER BY id DESC";
$products_result = mysqli_query($conn, $products_sql);
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}
?>

<h3>订单管理</h3>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-message">
        <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="error-message">
        <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
        ?>
    </div>
<?php endif; ?>

<!-- 添加加载中弹窗 -->
<div id="email-sending-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999; display: none; justify-content: center; align-items: center;">
    <div id="email-sending-content" style="background-color: white; padding: 30px; border-radius: 8px; text-align: center; max-width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <div style="margin-bottom: 20px; font-size: 18px; color: #333;">正在发送邮件...</div>
        <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #e75480; border-radius: 50%; margin: 0 auto 20px; animation: spin 1s linear infinite;"></div>
        <div>请稍候，邮件发送可能需要几秒钟时间</div>
    </div>
</div>

<div class="action-buttons">
    <form method="GET" action="" class="filter-form">
        <input type="hidden" name="page" value="purchases">
        <div class="form-row">
            <div class="form-group">
                <label for="product_search">订单ID:</label>
                <input type="text" name="product_search" id="product_search" value="<?php echo htmlspecialchars($product_filter); ?>" placeholder="输入订单ID" style="width: 150px; padding: 6px;">
            </div>
            
            <div class="form-group">
                <label for="email_sent">邮件状态:</label>
                <select name="email_sent" id="email_sent" style="padding: 6px;">
                    <option value="-1" <?php echo $email_filter === -1 ? 'selected' : ''; ?>>全部</option>
                    <option value="1" <?php echo $email_filter === 1 ? 'selected' : ''; ?>>已发送</option>
                    <option value="0" <?php echo $email_filter === 0 ? 'selected' : ''; ?>>未发送</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="email_source">邮箱来源:</label>
                <select name="email_source" id="email_source" style="padding: 6px;">
                    <option value="" <?php echo $source_filter === '' ? 'selected' : ''; ?>>全部</option>
                    <option value="session" <?php echo $source_filter === 'session' ? 'selected' : ''; ?>>网站账号</option>
                    <option value="paypal" <?php echo $source_filter === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="purchase_type">订单类型:</label>
                <select name="purchase_type" id="purchase_type" style="padding: 6px;">
                    <option value="" <?php echo $type_filter === '' ? 'selected' : ''; ?>>全部</option>
                    <option value="product" <?php echo $type_filter === 'product' ? 'selected' : ''; ?>>产品购买</option>
                    <option value="activation" <?php echo $type_filter === 'activation' ? 'selected' : ''; ?>>账号激活</option>
                    <option value="photo_pack" <?php echo $type_filter === 'photo_pack' ? 'selected' : ''; ?>>图片包</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="filter-button">筛选</button>
                <?php if (!empty($product_filter) || $email_filter !== -1 || $source_filter !== '' || $type_filter !== ''): ?>
                <a href="admin.php?page=purchases" class="reset-button">重置</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="table-container" style="margin-top: 0; overflow-x: auto;">
    <table class="data-table php74-compatible">
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
            // 使用获取到的 $purchases 数组进行循环
            if (!empty($purchases)) {
                foreach ($purchases as $row) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['purchase_date'] . "</td>";
                    
                    // 显示产品标题和ID
                    if ($row['purchase_type'] === 'activation') {
                        echo "<td>" . htmlspecialchars($row['product_title'] ?? '') . "</td>";
                    } else {
                        echo "<td>" . htmlspecialchars($row['product_title'] ?? '') . " (ID: " . ($row['original_product_id'] ?? '') . ")</td>";
                    }
                    
                    echo "<td>" . (!empty($row['username']) ? htmlspecialchars($row['username']) : '游客') . "</td>";
                    echo "<td class='email-cell' title='" . htmlspecialchars($row['email'] ?? '') . "'>" . htmlspecialchars($row['email'] ?? '') . "</td>";
                    echo "<td>" . ($row['email_source'] === 'session' ? '网站账号' : 'PayPal') . "</td>";
                    // 修改订单ID显示方式，确保完整显示
                    $order_id = $row['order_id'] ?? '';
                    
                    // 添加调试日志
                    error_log("订单ID(PayPal订单ID): " . $order_id . ", 长度: " . strlen($order_id) . ", 类型: " . gettype($order_id));
                    
                    // 使用简单的文本输出，确保完整显示订单ID
                    echo "<td class='order-id-cell' title='" . htmlspecialchars($order_id) . "' style='max-width:200px; word-break:break-all; white-space:normal;'>" . htmlspecialchars($order_id) . "</td>";
                    
                    // 显示类型
                    $typeText = '';
                    if ($row['purchase_type'] === 'activation') {
                        $typeText = '账号激活';
                    } else if ($row['purchase_type'] === 'balance') {
                        $typeText = '余额支付 - ' . ($row['is_photo_pack'] ? '图片包' : '视频');
                    } else {
                        $typeText = ($row['is_photo_pack'] ? '图片包' : '视频');
                    }
                    echo "<td>" . $typeText . "</td>";
                    
                    echo "<td>$" . number_format($row['amount'], 2) . "</td>";
                    
                    // 邮件状态
                    echo "<td>";
                    if ($row['email_sent']) {
                        echo "<span class='status-badge success'>已发送</span>";
                    } else {
                        echo "<span class='status-badge danger'>未发送</span>";
                    }
                    echo "</td>";
                    
                    // 操作按钮
                    echo "<td class='actions'>";
                    if ($row['purchase_type'] === 'product' || $row['purchase_type'] === 'balance') {
                        echo "<a href='javascript:void(0);' class='resend-email-btn' onclick='resendEmail(" . $row['id'] . ")'>重发邮件</a>";
                    } else {
                        echo "<span style='color:#999;'>无需操作</span>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='11' class='no-data'>没有找到购买记录</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- 分页控件 -->
<div class="pagination-container">
    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="admin.php?page=purchases&page_num=1&product_search=<?php echo urlencode($product_filter); ?>&email_sent=<?php echo $email_filter; ?>&email_source=<?php echo urlencode($source_filter); ?>&purchase_type=<?php echo urlencode($type_filter); ?>" class="page-link">首页</a>
            <a href="admin.php?page=purchases&page_num=<?php echo $current_page - 1; ?>&product_search=<?php echo urlencode($product_filter); ?>&email_sent=<?php echo $email_filter; ?>&email_source=<?php echo urlencode($source_filter); ?>&purchase_type=<?php echo urlencode($type_filter); ?>" class="page-link">上一页</a>
        <?php endif; ?>
        
        <?php
        // 显示页码
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        if ($start_page > 1) {
            echo '<span class="page-ellipsis">...</span>';
        }
        
        // 如果没有数据或只有一页，只显示页码1
        if ($total_pages <= 1) {
            echo '<span class="page-link current">1</span>';
        } else {
            // 有多页数据时，正常显示页码
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $current_page) {
                    echo '<span class="page-link current">' . $i . '</span>';
                } else {
                    echo '<a href="admin.php?page=purchases&page_num=' . $i . '&product_search=' . urlencode($product_filter) . '&email_sent=' . $email_filter . '&email_source=' . urlencode($source_filter) . '&purchase_type=' . urlencode($type_filter) . '" class="page-link">' . $i . '</a>';
                }
            }
        }
        
        if ($end_page < $total_pages) {
            echo '<span class="page-ellipsis">...</span>';
        }
        ?>
        
        <?php if ($current_page < $total_pages): ?>
            <a href="admin.php?page=purchases&page_num=<?php echo $current_page + 1; ?>&product_search=<?php echo urlencode($product_filter); ?>&email_sent=<?php echo $email_filter; ?>&email_source=<?php echo urlencode($source_filter); ?>&purchase_type=<?php echo urlencode($type_filter); ?>" class="page-link">下一页</a>
            <a href="admin.php?page=purchases&page_num=<?php echo $total_pages; ?>&product_search=<?php echo urlencode($product_filter); ?>&email_sent=<?php echo $email_filter; ?>&email_source=<?php echo urlencode($source_filter); ?>&purchase_type=<?php echo urlencode($type_filter); ?>" class="page-link">末页</a>
        <?php endif; ?>
        
        <!-- 添加跳转到指定页面的功能 -->
        <?php if ($total_pages > 1): ?>
        <div class="page-jump">
            <span>跳转到</span>
            <input type="number" id="jump-page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>">
            <span>页</span>
            <button id="jump-button" class="jump-button">确定</button>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="pagination-info">
        显示 <?php echo min(($current_page - 1) * $items_per_page + 1, $total_records); ?> - <?php echo min($current_page * $items_per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
    </div>
</div>

<link rel="stylesheet" href="css/data-table.css">
<style>
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

.no-data {
    text-align: center;
    color: #777;
    padding: 20px;
}

/* 表格样式 */
.data-table {
    min-width: 1200px; /* 确保表格有最小宽度 */
}

/* 分页样式 */
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
    background-color: #f8f9fa;
    color: #e75480;
    text-decoration: none;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
}

.page-link:hover {
    background-color: #e75480;
    color: white;
    border-color: #e75480;
}

.page-link.current {
    background-color: #e75480;
    color: white;
    border-color: #e75480;
    font-weight: bold;
}

.page-ellipsis {
    padding: 8px 12px;
    color: #6c757d;
}

.page-jump {
    display: inline-flex;
    align-items: center;
    margin-left: 10px;
    background-color: #f8f9fa;
    padding: 5px 10px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.page-jump span {
    color: #e75480;
    margin: 0 5px;
}

.page-jump input {
    width: 50px;
    text-align: center;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.jump-button {
    background-color: #e75480;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 5px;
}

.jump-button:hover {
    background-color: #d64072;
}

.pagination-info {
    color: #6c757d;
    font-size: 14px;
    text-align: center;
    margin-top: 5px;
}

.order-id-header {
    min-width: 180px !important;
    width: 180px !important;
}

.order-id-cell {
    min-width: 180px !important;
    width: 180px !important;
    max-width: 200px !important;
    white-space: normal !important;
    word-break: break-all !important;
    overflow: visible !important;
}

/* 模态框样式 */
.order-id-modal {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4);
    animation: fadeIn 0.3s;
}

.order-id-modal-content {
    background-color: #fff;
    padding: 20px;
    border-radius: 5px;
    width: 80%;
    max-width: 600px;
    position: relative;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #ddd;
}

.order-id-modal-close {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 24px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.order-id-modal-close:hover {
    color: #555;
}

.order-id-detail {
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 15px;
    word-break: break-all;
    font-family: monospace;
    font-size: 14px;
}

@keyframes fadeIn {
    from {opacity: 0}
    to {opacity: 1}
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* PHP 7.4.33 兼容性样式 */
.php74-compatible {
    table-layout: fixed;
    width: 100%;
}

.php74-compatible td {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.php74-compatible td.order-id-cell {
    white-space: normal;
    word-break: break-all;
    overflow: visible;
}

#product_search {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

.form-group button[type="submit"] {
    background-color: #e75480;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.form-group button[type="submit"]:hover {
    background-color: #d64072;
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
    background-color: #f7a4b9 !important;
}
</style>

<!-- 添加JavaScript以支持列宽调整和AJAX邮件发送 -->
<script>
// 初始化列宽 - 为订单ID列设置固定宽度
function initColumnWidths(selector, width) {
    const elements = document.querySelectorAll(selector);
    elements.forEach(el => {
        el.style.width = `${width}px`;
        el.style.minWidth = `${width}px`;
    });
}

// 使用AJAX重发邮件
function resendEmail(id) {
    if (!confirm("确定要重新发送邮件吗？")) {
        return false;
    }
    
    // 显示加载中弹窗
    document.getElementById("email-sending-overlay").style.display = "flex";
    
    // 创建AJAX请求
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "admin.php?ajax=resend_email", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    // 更新加载中弹窗内容
                    const overlay = document.getElementById("email-sending-overlay");
                    const overlayContent = document.getElementById("email-sending-content");
                    
                    if (response.success) {
                        // 成功发送邮件
                        overlayContent.innerHTML = `
                            <div style="margin-bottom: 20px; font-size: 18px; color: #28a745;">邮件发送成功</div>
                            <div style="margin-bottom: 20px;">邮件已成功发送至 ${response.email}</div>
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
                            <div style="margin-bottom: 20px;">${response.message}</div>
                            <div style="margin-top: 20px;">
                                <button onclick="document.getElementById('email-sending-overlay').style.display='none'" style="background-color: #e75480; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">关闭</button>
                            </div>
                        `;
                    }
                } catch (e) {
                    console.error("解析响应失败:", e);
                    document.getElementById("email-sending-overlay").style.display = "none";
                    alert("处理响应时出错，请重试");
                }
            } else {
                // 请求失败
                document.getElementById("email-sending-overlay").style.display = "none";
                alert("请求失败，请重试");
            }
        }
    };
    
    xhr.send("id=" + id);
    return false;
}

// 初始化列宽
// 添加PHP 7.4.33兼容性检查和修复
function fixTableDisplay() {
    // 确保表格正确显示
    const table = document.querySelector('.data-table');
    if (table) {
        table.style.tableLayout = 'fixed';
        table.style.width = '100%';
    }
    
    // 确保订单ID列正确显示
    const orderIdCells = document.querySelectorAll('.order-id-cell');
    orderIdCells.forEach(cell => {
        if (cell) {
            cell.style.maxWidth = '200px';
            cell.style.whiteSpace = 'normal';
            cell.style.wordBreak = 'break-all';
            cell.style.overflow = 'visible';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // 设置列宽
    initColumnWidths('.email-header', 150);
    
    // 运行兼容性修复
    fixTableDisplay();
    
    // 为订单ID列设置合适的宽度
    document.querySelectorAll('.order-id-header').forEach(header => {
        header.style.width = '180px';
        header.style.minWidth = '180px';
        header.style.maxWidth = '200px';
    });
    
    // 确保订单ID单元格内容完整显示
    document.querySelectorAll('.order-id-cell').forEach(cell => {
        cell.style.width = '180px';
        cell.style.minWidth = '180px';
        cell.style.maxWidth = '200px';
        cell.style.whiteSpace = 'normal';
        cell.style.wordBreak = 'break-all';
        cell.style.overflow = 'visible';
        cell.style.padding = '8px';
        cell.style.lineHeight = '1.4';
    });
    
    // 添加页面跳转功能
    const jumpButton = document.getElementById('jump-button');
    if (jumpButton) {
        jumpButton.addEventListener('click', function() {
            const pageInput = document.getElementById('jump-page');
            if (pageInput) {
                const pageNum = parseInt(pageInput.value);
                const minPage = parseInt(pageInput.min);
                const maxPage = parseInt(pageInput.max);
                
                if (pageNum >= minPage && pageNum <= maxPage) {
                    // 构建URL，保留所有筛选参数
                    const currentUrl = new URL(window.location.href);
                    const params = currentUrl.searchParams;
                    
                    // 更新或添加page_num参数
                    params.set('page_num', pageNum);
                    
                    // 跳转到新页面
                    window.location.href = currentUrl.pathname + '?' + params.toString();
                } else {
                    alert('请输入有效的页码（' + minPage + '-' + maxPage + '）');
                }
            }
        });
        
        // 添加回车键支持
        const pageInput = document.getElementById('jump-page');
        if (pageInput) {
            pageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    jumpButton.click();
                }
            });
        }
    }
});
</script> 