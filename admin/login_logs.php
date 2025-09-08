<?php
// 处理搜索和筛选
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

$date_filter = '';
if (isset($_GET['date_filter']) && !empty($_GET['date_filter'])) {
    $date_filter = $_GET['date_filter'];
}

$user_filter = '';
if (isset($_GET['user_filter']) && !empty($_GET['user_filter'])) {
    $user_filter = trim($_GET['user_filter']);
}

// 分页设置
$items_per_page_options = [10, 25, 50, 100];
$default_items_per_page = 25;
$items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : $default_items_per_page;

if (!in_array($items_per_page, $items_per_page_options)) {
    $items_per_page = $default_items_per_page;
}

$current_page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// 构建SQL查询
$sql = "SELECT ll.*, u.username as user_username, u.email as user_email 
        FROM login_logs ll 
        LEFT JOIN users u ON ll.user_id = u.id 
        WHERE 1=1";

// 添加搜索条件
if (!empty($search_term)) {
    $search_term_escaped = mysqli_real_escape_string($conn, $search_term);
    $sql .= " AND (ll.username LIKE '%{$search_term_escaped}%' 
              OR ll.email LIKE '%{$search_term_escaped}%' 
              OR ll.login_ip LIKE '%{$search_term_escaped}%' 
              OR ll.login_location LIKE '%{$search_term_escaped}%')";
}

// 添加日期筛选条件
if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $sql .= " AND DATE(ll.login_time) = CURDATE()";
            break;
        case 'yesterday':
            $sql .= " AND DATE(ll.login_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $sql .= " AND ll.login_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $sql .= " AND ll.login_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
    }
}

// 添加用户筛选条件
if (!empty($user_filter)) {
    $user_filter_escaped = mysqli_real_escape_string($conn, $user_filter);
    $sql .= " AND (ll.username LIKE '%{$user_filter_escaped}%' OR ll.email LIKE '%{$user_filter_escaped}%')";
}

// 获取总记录数
$count_sql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_table";
$count_result = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_result && $count_row = mysqli_fetch_assoc($count_result)) {
    $total_records = $count_row['total'];
}

// 计算总页数
$total_pages = ceil($total_records / $items_per_page);

// 确保当前页码不超过总页数
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// 计算LIMIT子句的偏移量
$offset = ($current_page - 1) * $items_per_page;

$sql .= " ORDER BY ll.login_time DESC LIMIT " . $offset . ", " . $items_per_page;
$result = mysqli_query($conn, $sql);

$login_logs = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $login_logs[] = $row;
    }
    mysqli_free_result($result);
}

// 获取统计信息
$stats_sql = "SELECT 
    COUNT(*) as total_logins,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT login_ip) as unique_ips,
    COUNT(CASE WHEN DATE(login_time) = CURDATE() THEN 1 END) as today_logins
    FROM login_logs";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<div class="admin-content">
    <h2>用户登录记录</h2>
    
    <!-- 统计卡片 -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-title">总登录次数</div>
            <div class="stat-value"><?php echo number_format($stats['total_logins']); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">独立用户数</div>
            <div class="stat-value"><?php echo number_format($stats['unique_users']); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">独立IP数</div>
            <div class="stat-value"><?php echo number_format($stats['unique_ips']); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">今日登录</div>
            <div class="stat-value"><?php echo number_format($stats['today_logins']); ?></div>
        </div>
    </div>
    
    <!-- 搜索和筛选表单 -->
    <div class="search-container">
        <form action="admin.php" method="get" class="search-form">
            <input type="hidden" name="page" value="login_logs">
            <input type="hidden" name="items_per_page" value="<?php echo $items_per_page; ?>">
            <div class="search-inputs">
                <input type="text" name="search" placeholder="搜索用户名、邮箱、IP或地址..." value="<?php echo htmlspecialchars($search_term); ?>">
                <select name="date_filter" class="date-filter">
                    <option value="" <?php echo $date_filter === '' ? 'selected' : ''; ?>>所有时间</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>今天</option>
                    <option value="yesterday" <?php echo $date_filter === 'yesterday' ? 'selected' : ''; ?>>昨天</option>
                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>最近一周</option>
                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>最近一月</option>
                </select>
                <input type="text" name="user_filter" placeholder="按用户筛选..." value="<?php echo htmlspecialchars($user_filter); ?>">
                <button type="submit" class="search-button">搜索</button>
                <?php if (!empty($search_term) || !empty($date_filter) || !empty($user_filter)): ?>
                <a href="admin.php?page=login_logs&items_per_page=<?php echo $items_per_page; ?>" class="reset-button">重置</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>用户名</th>
                <th>邮箱</th>
                <th>登录IP</th>
                <th>登录地址</th>
                <th>登录时间</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($login_logs as $log): ?>
            <tr>
                <td><?php echo $log['id']; ?></td>
                <td>
                    <?php echo htmlspecialchars($log['username']); ?>
                    <?php if ($log['user_id'] && $log['user_username'] && $log['username'] !== $log['user_username']): ?>
                    <br><small style="color: #666;">(当前: <?php echo htmlspecialchars($log['user_username']); ?>)</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($log['email']); ?>
                    <?php if ($log['user_id'] && $log['user_email'] && $log['email'] !== $log['user_email']): ?>
                    <br><small style="color: #666;">(当前: <?php echo htmlspecialchars($log['user_email']); ?>)</small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="ip-address"><?php echo htmlspecialchars($log['login_ip']); ?></span>
                    <button class="ip-lookup-btn" onclick="lookupIP('<?php echo htmlspecialchars($log['login_ip']); ?>')" title="查询IP信息">🔍</button>
                </td>
                <td>
                    <?php 
                    $location = $log['login_location'] ?: '未知';
                    if (strlen($location) > 30) {
                        echo '<span title="' . htmlspecialchars($location) . '">' . htmlspecialchars(substr($location, 0, 30)) . '...</span>';
                    } else {
                        echo htmlspecialchars($location);
                    }
                    ?>
                </td>
                <td>
                    <div class="login-time">
                        <?php echo date('Y-m-d H:i:s', strtotime($log['login_time'])); ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($login_logs)): ?>
            <tr>
                <td colspan="6" class="no-data">暂无登录记录</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- 分页控件 -->
    <div class="pagination-container">
        <div class="pagination-wrapper">
            <div class="pagination-left"></div>
            
            <div class="pagination-center">
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="admin.php?page=login_logs&page_num=1&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&date_filter=<?php echo urlencode($date_filter); ?>&user_filter=<?php echo urlencode($user_filter); ?>" class="page-link">首页</a>
                        <a href="admin.php?page=login_logs&page_num=<?php echo $current_page - 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&date_filter=<?php echo urlencode($date_filter); ?>&user_filter=<?php echo urlencode($user_filter); ?>" class="page-link">上一页</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    
                    if ($total_pages <= 1) {
                        echo '<span class="page-link current">1</span>';
                    } else {
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="page-link current">' . $i . '</span>';
                            } else {
                                echo '<a href="admin.php?page=login_logs&page_num=' . $i . '&items_per_page=' . $items_per_page . '&search=' . urlencode($search_term) . '&date_filter=' . urlencode($date_filter) . '&user_filter=' . urlencode($user_filter) . '" class="page-link">' . $i . '</a>';
                            }
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="admin.php?page=login_logs&page_num=<?php echo $current_page + 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&date_filter=<?php echo urlencode($date_filter); ?>&user_filter=<?php echo urlencode($user_filter); ?>" class="page-link">下一页</a>
                        <a href="admin.php?page=login_logs&page_num=<?php echo $total_pages; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&date_filter=<?php echo urlencode($date_filter); ?>&user_filter=<?php echo urlencode($user_filter); ?>" class="page-link">末页</a>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-info">
                    显示 <?php echo $offset + 1; ?> - <?php echo min($offset + $items_per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
                </div>
            </div>
            
            <div class="items-per-page">
                <span>每页显示：</span>
                <select id="items-per-page-select" onchange="changeItemsPerPageLogs(this.value)">
                    <?php foreach ($items_per_page_options as $option): ?>
                    <option value="<?php echo $option; ?>" <?php echo ($option == $items_per_page) ? 'selected' : ''; ?>><?php echo $option; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>


<!-- IP查询弹窗 -->
<div id="ipLookupModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeIPLookupModal()">&times;</span>
        <h2>IP地址信息</h2>
        <div id="ipLookupContent">
            <div class="loading">查询中...</div>
        </div>
    </div>
</div>

<style>
.dashboard-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    gap: 15px;
}

.stat-card {
    flex: 1;
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
    text-align: center;
    border: 1px solid #ffccd5;
}

.stat-title {
    font-size: 14px;
    color: #777;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #e75480;
}

.search-container {
    margin-bottom: 20px;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #ffccd5;
}

.search-inputs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.search-inputs input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.date-filter {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
    min-width: 120px;
}

.search-button {
    background-color: #e75480;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.search-button:hover {
    background-color: #d64072;
}

.reset-button {
    background-color: #f8f9fa;
    color: #6c757d;
    border: 1px solid #ddd;
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
}

.reset-button:hover {
    background-color: #e9ecef;
}

.ip-address {
    font-family: monospace;
    font-weight: bold;
}

.ip-lookup-btn {
    background: none;
    border: none;
    cursor: pointer;
    margin-left: 5px;
    font-size: 12px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.ip-lookup-btn:hover {
    opacity: 1;
}

.login-time {
    white-space: nowrap;
}


.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 600px;
    max-width: 90%;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    border: 1px solid #f7a4b9;
    max-height: 80vh;
    overflow-y: auto;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
}

.loading {
    text-align: center;
    padding: 20px;
    color: #666;
}

.detail-row {
    display: flex;
    margin-bottom: 15px;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.detail-label {
    font-weight: bold;
    width: 120px;
    color: #333;
}

.detail-value {
    flex: 1;
    color: #666;
    word-break: break-all;
}

.no-data {
    text-align: center;
    color: #777;
    padding: 40px;
    font-style: italic;
}

@media (max-width: 768px) {
    .dashboard-stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .search-inputs {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-inputs input[type="text"], 
    .date-filter {
        min-width: auto;
        width: 100%;
    }
}
</style>

<script>
// 每页显示数量变更处理函数
function changeItemsPerPageLogs(value) {
    var url = new URL(window.location.href);
    url.searchParams.set('items_per_page', value);
    url.searchParams.set('page_num', 1);
    window.location.href = url.toString();
}


// IP查询功能
function lookupIP(ip) {
    const modal = document.getElementById('ipLookupModal');
    const content = document.getElementById('ipLookupContent');
    
    modal.style.display = 'block';
    content.innerHTML = '<div class="loading">查询中...</div>';
    
    // 使用免费的IP查询API
    fetch(`http://ip-api.com/json/${ip}?lang=zh-CN`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                content.innerHTML = `
                    <div class="detail-row">
                        <div class="detail-label">IP地址:</div>
                        <div class="detail-value">${ip}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">国家:</div>
                        <div class="detail-value">${data.country || '未知'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">地区:</div>
                        <div class="detail-value">${data.regionName || '未知'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">城市:</div>
                        <div class="detail-value">${data.city || '未知'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">ISP:</div>
                        <div class="detail-value">${data.isp || '未知'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">组织:</div>
                        <div class="detail-value">${data.org || '未知'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">时区:</div>
                        <div class="detail-value">${data.timezone || '未知'}</div>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="detail-row">
                        <div class="detail-label">IP地址:</div>
                        <div class="detail-value">${ip}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">查询结果:</div>
                        <div class="detail-value">查询失败，请稍后再试</div>
                    </div>
                `;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div class="detail-row">
                    <div class="detail-label">IP地址:</div>
                    <div class="detail-value">${ip}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">错误:</div>
                    <div class="detail-value">网络错误，无法查询IP信息</div>
                </div>
            `;
        });
}

// 关闭IP查询弹窗
function closeIPLookupModal() {
    document.getElementById('ipLookupModal').style.display = 'none';
}

// 点击弹窗外部关闭
window.onclick = function(event) {
    const ipModal = document.getElementById('ipLookupModal');
    
    if (event.target == ipModal) {
        closeIPLookupModal();
    }
}
</script>
