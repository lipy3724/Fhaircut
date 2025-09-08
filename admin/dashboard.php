<?php
// 获取真实的统计数据
// 获取用户总数
$user_count = 0;
$sql = "SELECT COUNT(*) as count FROM users";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $user_count = $row['count'];
    mysqli_free_result($result);
}

// 获取产品总数
$product_count = 0;
$sql = "SELECT COUNT(*) as count FROM products";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $product_count = $row['count'];
    mysqli_free_result($result);
}

// 获取订单总数
$order_count = 0;
$sql = "SELECT COUNT(*) as count FROM purchases";
$result = mysqli_query($conn, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $order_count = $row['count'];
    mysqli_free_result($result);
}

// 获取最近添加的产品
$recent_products = [];
$sql = "SELECT p.id, p.title as name, c.name as category, p.created_date as date 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_products[] = $row;
    }
    mysqli_free_result($result);
}

// 如果没有产品数据，使用模拟数据
if (empty($recent_products)) {
    $recent_products = [
        ['id' => 1, 'name' => 'Haircut No.1528', 'category' => 'Cool bobo hair', 'date' => '2025-07-20'],
        ['id' => 2, 'name' => 'Haircut No.1527', 'category' => 'Shovel long Bob', 'date' => '2025-07-18'],
        ['id' => 3, 'name' => 'Haircut No.1526', 'category' => 'Super short hair', 'date' => '2025-07-15'],
        ['id' => 4, 'name' => 'Perm No.490', 'category' => 'Curly hair', 'date' => '2025-07-12'],
        ['id' => 5, 'name' => 'Haircut No.1525', 'category' => 'Halo hairstyle', 'date' => '2025-07-10']
    ];
}

// 获取最近的购买记录
$recent_purchases = [];
$sql = "SELECT p.id, p.order_id, p.email, pr.title as product_name, p.amount, p.purchase_date 
        FROM purchases p 
        JOIN products pr ON p.product_id = pr.id 
        ORDER BY p.purchase_date DESC LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_purchases[] = $row;
    }
    mysqli_free_result($result);
}
?>

<div class="dashboard">
    <h2>控制面板</h2>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-title">产品总数</div>
            <div class="stat-value"><?php echo $product_count; ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-title">用户总数</div>
            <div class="stat-value"><?php echo $user_count; ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-title">订单总数</div>
            <div class="stat-value"><?php echo $order_count; ?></div>
            <div class="stat-link"><a href="admin.php?page=purchases">查看所有订单</a></div>
        </div>
    </div>
    
    <div class="dashboard-sections">
    <div class="recent-products">
        <h3>最近添加的产品</h3>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>分类</th>
                    <th>添加日期</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_products as $product): ?>
                <tr>
                    <td data-label="ID"><?php echo $product['id']; ?></td>
                    <td data-label="名称"><?php echo $product['name']; ?></td>
                    <td data-label="分类"><?php echo $product['category']; ?></td>
                    <td data-label="添加日期"><?php echo date('Y-m-d', strtotime($product['date'])); ?></td>
                    <td class="actions" data-label="操作">
                        <a href="admin.php?page=products&action=edit&id=<?php echo $product['id']; ?>" class="edit-button">编辑</a>
                        <a href="admin.php?page=products&action=delete&id=<?php echo $product['id']; ?>" class="delete-button" onclick="return confirm('确定要删除此产品吗？');">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="action-buttons">
            <a href="admin.php?page=products&action=add" class="add-button">添加新产品</a>
        </div>
        </div>
        
        <?php if (!empty($recent_purchases)): ?>
        <div class="recent-purchases">
            <h3>最近的订单</h3>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>订单ID</th>
                        <th>产品</th>
                        <th>客户邮箱</th>
                        <th>金额</th>
                        <th>日期</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_purchases as $purchase): ?>
                    <tr>
                        <td data-label="订单ID"><?php echo htmlspecialchars(substr($purchase['order_id'], 0, 8) . '...'); ?></td>
                        <td data-label="产品"><?php echo htmlspecialchars($purchase['product_name']); ?></td>
                        <td data-label="客户邮箱"><?php echo htmlspecialchars($purchase['email']); ?></td>
                        <td data-label="金额">$<?php echo number_format($purchase['amount'], 2); ?></td>
                        <td data-label="日期"><?php echo date('Y-m-d', strtotime($purchase['purchase_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="action-buttons">
                <a href="admin.php?page=purchases" class="view-button">查看所有订单</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.dashboard {
    padding: 20px;
}

.dashboard h2 {
    margin-bottom: 30px;
    color: #e75480;
    font-size: 24px;
}

.dashboard-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
    flex: 1;
    text-align: center;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 2px 15px rgba(231, 84, 128, 0.2);
}

.stat-title {
    color: #4A4A4A;
    font-size: 16px;
    margin-bottom: 10px;
}

.stat-value {
    color: #e75480;
    font-size: 36px;
    font-weight: bold;
}

.stat-link {
    margin-top: 10px;
}

.stat-link a {
    color: #e75480;
    text-decoration: none;
    font-size: 14px;
}

.stat-link a:hover {
    text-decoration: underline;
}

.dashboard-sections {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    width: 100%;
    overflow-x: hidden;
}

.recent-products, .recent-purchases {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
    width: 100%;
    overflow-x: auto;
}

.recent-products h3, .recent-purchases h3 {
    color: #e75480;
    margin-bottom: 20px;
    font-size: 18px;
}

.recent-products .data-table, .recent-purchases .data-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    min-width: 450px; /* 确保表格在小屏幕上有最小宽度 */
}

.recent-products .data-table th, .recent-purchases .data-table th,
.recent-products .data-table td, .recent-purchases .data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
    font-size: 14px;
    line-height: 1.4;
    vertical-align: middle;
    height: 48px; /* 统一行高 */
    box-sizing: border-box;
    min-height: 48px;
}

.recent-products .data-table th, .recent-purchases .data-table th {
    background-color: #ffccd5;
    color: #333;
    font-weight: bold;
}

.action-buttons {
    margin-top: 20px;
    text-align: right;
}

.add-button, .view-button {
    display: inline-block;
    padding: 8px 15px;
    background-color: #e75480;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.add-button:hover, .view-button:hover {
    background-color: #d64072;
}

.edit-button, .delete-button {
    display: inline-block;
    padding: 6px 12px;
    text-decoration: none;
    border-radius: 4px;
    margin-right: 5px;
    font-size: 14px;
    line-height: 1;
    vertical-align: middle;
    height: 28px; /* 固定按钮高度 */
    box-sizing: border-box;
}

.edit-button {
    background-color: #ffccd5;
    color: #333;
}

.delete-button {
    background-color: #ff8da1;
    color: white;
}

.edit-button:hover {
    background-color: #f7a4b9;
}

.delete-button:hover {
    background-color: #ff6b8b;
}

/* 确保操作列不会影响行高 */
.actions {
    white-space: nowrap;
    height: 48px; /* 与表格行高保持一致 */
    display: flex;
    align-items: center;
}

/* 处理长文本内容 */
.recent-products .data-table td, .recent-purchases .data-table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 24px; /* 确保内容行高一致 */
}

@media (max-width: 992px) {
    .dashboard-sections {
        grid-template-columns: 1fr;
    }
    
    /* 在小屏幕上调整表格显示 */
    .recent-products .data-table, .recent-purchases .data-table {
        font-size: 13px;
    }
    
    .recent-products .data-table th, .recent-purchases .data-table th,
    .recent-products .data-table td, .recent-purchases .data-table td {
        padding: 10px 8px;
    }
    
    .recent-products, .recent-purchases {
        margin-bottom: 20px;
    }
}

@media (max-width: 768px) {
    /* 在更小的屏幕上进一步优化表格 */
    .dashboard-sections .data-table thead {
        display: none;
    }
    
    .dashboard-sections .data-table, 
    .dashboard-sections .data-table tbody, 
    .dashboard-sections .data-table tr, 
    .dashboard-sections .data-table td {
        display: block;
        width: 100%;
    }
    
    .dashboard-sections .data-table tr {
        margin-bottom: 15px;
        border: 1px solid #ffccd5;
        border-radius: 4px;
    }
    
    .dashboard-sections .data-table td {
        text-align: right;
        padding-left: 50%;
        position: relative;
        border-bottom: 1px solid #f0f0f0;
        height: auto;
        min-height: 40px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }
    
    .dashboard-sections .data-table td:before {
        content: attr(data-label);
        position: absolute;
        left: 12px;
        width: 45%;
        text-align: left;
        font-weight: bold;
    }
    
    .dashboard-sections .data-table td:last-child {
        border-bottom: none;
    }
    
    .dashboard-sections .actions {
        text-align: center;
        display: flex;
        justify-content: flex-end;
    }
    
    /* 确保表格容器不会重叠 */
    .recent-products, .recent-purchases {
        margin-bottom: 30px;
        overflow-x: visible;
    }
}
</style> 