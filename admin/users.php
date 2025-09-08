<?php
// 删除会话检查代码，因为这已经在 admin.php 中完成
// if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "Administrator") {
//     header("location: login.php");
//     exit;
// }

// 处理用户删除操作
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // 防止删除管理员账户
    $check_sql = "SELECT role FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $role);
            mysqli_stmt_fetch($stmt);
            
            if ($role == "Administrator" && $id == 1) {
                $delete_error = "无法删除主管理员账户";
            } else {
                $delete_sql = "DELETE FROM users WHERE id = ?";
                if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                    mysqli_stmt_bind_param($delete_stmt, "i", $id);
                    if (mysqli_stmt_execute($delete_stmt)) {
                        // 删除成功，使用JavaScript重定向而不是header函数
                        echo "<script>window.location.href='admin.php?page=users&deleted=1';</script>";
                        // header("location: admin.php?page=users&deleted=1");
                        exit;
                    } else {
                        $delete_error = "删除用户时出错";
                    }
                    mysqli_stmt_close($delete_stmt);
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// 处理AJAX编辑用户请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $response = ['success' => false, 'message' => ''];
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'Member';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Active';
    $is_activated = isset($_POST['is_activated']) ? intval($_POST['is_activated']) : 0;
    
    // 验证输入
    if (empty($username)) {
        $response['message'] = "用户名不能为空";
    } elseif (empty($email)) {
        $response['message'] = "邮箱不能为空";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = "邮箱格式无效";
    } else {
        // 检查用户名和邮箱是否已存在
        $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        if ($stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $response['message'] = "用户名或邮箱已被使用";
            } else {
                if (!empty($password)) {
                    // 更新包括密码
                    $sql = "UPDATE users SET username = ?, email = ?, password = ?, role = ?, status = ?, is_activated = ? WHERE id = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        mysqli_stmt_bind_param($stmt, "sssssii", $username, $email, $hashed_password, $role, $status, $is_activated, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $response['success'] = true;
                            $response['message'] = "用户更新成功";
                        } else {
                            $response['message'] = "更新用户时出错";
                        }
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    // 更新不包括密码
                    $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, is_activated = ? WHERE id = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "sssssi", $username, $email, $role, $status, $is_activated, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $response['success'] = true;
                            $response['message'] = "用户更新成功";
                        } else {
                            $response['message'] = "更新用户时出错";
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // 返回JSON响应
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 从数据库获取用户列表
$users = [];

// 分页设置
$items_per_page_options = [10, 25, 50, 100]; // 每页显示数量选项
$default_items_per_page = 10; // 默认每页显示10个用户

// 获取用户选择的每页显示数量
$items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : $default_items_per_page;

// 确保每页显示数量是有效选项
if (!in_array($items_per_page, $items_per_page_options)) {
    $items_per_page = $default_items_per_page;
}

// 获取当前页码
$current_page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
if ($current_page < 1) {
    $current_page = 1;
}

// 处理搜索功能
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// 处理状态筛选
$status_filter = '';
if (isset($_GET['status_filter']) && in_array($_GET['status_filter'], ['active', 'inactive', 'all'])) {
    $status_filter = $_GET['status_filter'];
} else {
    $status_filter = 'all'; // 默认显示所有状态
}

// 处理角色筛选
$role_filter = '';
if (isset($_GET['role_filter']) && in_array($_GET['role_filter'], ['member', 'user', 'admin', 'all'])) {
    $role_filter = $_GET['role_filter'];
} else {
    $role_filter = 'all'; // 默认显示所有角色
}

// 构建SQL查询
$sql = "SELECT id, username, email, role, status, registered_date, is_activated, balance, last_login_ip, last_login_country, last_login_time FROM users WHERE 1=1";

// 添加搜索条件
if (!empty($search_term)) {
    $search_term_escaped = mysqli_real_escape_string($conn, $search_term);
    $sql .= " AND (username LIKE '%{$search_term_escaped}%' OR email LIKE '%{$search_term_escaped}%')";
}

// 添加状态筛选条件
if ($status_filter === 'active') {
    $sql .= " AND is_activated = 1";
} elseif ($status_filter === 'inactive') {
    $sql .= " AND is_activated = 0";
}

// 添加角色筛选条件
if ($role_filter === 'member') {
    $sql .= " AND role = 'Member' AND is_activated = 1";
} elseif ($role_filter === 'user') {
    $sql .= " AND ((role = 'Member' AND is_activated = 0) OR role = 'Editor' OR role = 'User')";
} elseif ($role_filter === 'admin') {
    $sql .= " AND role = 'Administrator'";
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

$sql .= " ORDER BY id ASC LIMIT " . $offset . ", " . $items_per_page;
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    mysqli_free_result($result);
}
?>

<div class="admin-content">
    <h2>用户管理</h2>
    
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
    <div class="alert alert-success">用户已成功删除</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
    <div class="alert alert-success">用户已成功更新</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    <div class="alert alert-success">用户已成功添加</div>
    <?php endif; ?>
    
    <?php if (isset($delete_error)): ?>
    <div class="alert alert-danger"><?php echo $delete_error; ?></div>
    <?php endif; ?>
    
    <div class="action-buttons">
        <button class="add-user-btn" onclick="showAddUserModal()">添加新用户</button>
    </div>
    
    <!-- 添加搜索表单 -->
    <div class="search-container">
        <form action="admin.php" method="get" class="search-form">
            <input type="hidden" name="page" value="users">
            <input type="hidden" name="items_per_page" value="<?php echo $items_per_page; ?>">
            <div class="search-inputs">
                <input type="text" name="search" placeholder="搜索用户名或邮箱..." value="<?php echo htmlspecialchars($search_term); ?>">
                <select name="status_filter" class="status-filter">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>所有状态</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>活跃</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>未激活</option>
                </select>
                <select name="role_filter" class="role-filter">
                    <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>所有角色</option>
                    <option value="member" <?php echo $role_filter === 'member' ? 'selected' : ''; ?>>会员</option>
                    <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>用户</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>管理员</option>
                </select>
                <button type="submit" class="search-button">搜索</button>
                <?php if (!empty($search_term) || $status_filter !== 'all' || $role_filter !== 'all'): ?>
                <a href="admin.php?page=users&items_per_page=<?php echo $items_per_page; ?>" class="reset-button">重置</a>
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
                <th>角色</th>
                <th>余额</th>
                <th>注册时间</th>
                <th>状态</th>
                <th>最后登录IP</th>
                <th>登录国家</th>
                <th>登录时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                    <?php 
                    switch($user['role']) {
                        case 'Member':
                            // 根据激活状态显示不同角色名称
                            if (isset($user['is_activated']) && $user['is_activated'] == 1) {
                            echo '会员';
                            } else {
                                echo '用户';
                            }
                            break;
                        case 'User':
                            echo '用户';
                            break;
                        case 'Editor':
                            echo '用户';  // 兼容旧数据
                            break;
                        case 'Administrator':
                            echo '管理员';
                            break;
                        default:
                            echo $user['role'];
                    }
                    ?>
                </td>
                <td>$<?php echo number_format($user['balance'], 2); ?></td>
                <td><?php echo date('Y-m-d', strtotime($user['registered_date'])); ?></td>
                <td>
                    <?php 
                    if ($user['status'] == 'Active') {
                        if (isset($user['is_activated']) && $user['is_activated'] == 1) {
                            echo '<span class="status-active">活跃</span>';
                        } else {
                            echo '<span class="status-pending">未激活</span>';
                        }
                    } else {
                        echo '<span class="status-inactive">未激活</span>';
                    }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($user['last_login_ip'] ?? '未登录'); ?></td>
                <td><?php echo htmlspecialchars($user['last_login_country'] ?? '未知'); ?></td>
                <td><?php echo !empty($user['last_login_time']) ? date('Y-m-d H:i:s', strtotime($user['last_login_time'])) : '未登录'; ?></td>
                <td class="actions">
                    <button class="edit-button" onclick="editUser(<?php echo $user['id']; ?>)">编辑</button>
                    <?php if ($user['id'] != 1 || $user['role'] != 'Administrator'): ?>
                    <a href="admin.php?page=users&action=delete&id=<?php echo $user['id']; ?>" class="delete-button" onclick="return confirm('确定要删除此用户吗？');">删除</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($users)): ?>
            <tr>
                <td colspan="10" class="no-data">暂无用户数据</td>
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
                        <a href="admin.php?page=users&page_num=1&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&status_filter=<?php echo urlencode($status_filter); ?>&role_filter=<?php echo urlencode($role_filter); ?>" class="page-link">首页</a>
                        <a href="admin.php?page=users&page_num=<?php echo $current_page - 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&status_filter=<?php echo urlencode($status_filter); ?>&role_filter=<?php echo urlencode($role_filter); ?>" class="page-link">上一页</a>
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
                                echo '<a href="admin.php?page=users&page_num=' . $i . '&items_per_page=' . $items_per_page . '&search=' . urlencode($search_term) . '&status_filter=' . urlencode($status_filter) . '&role_filter=' . urlencode($role_filter) . '" class="page-link">' . $i . '</a>';
                            }
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="admin.php?page=users&page_num=<?php echo $current_page + 1; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&status_filter=<?php echo urlencode($status_filter); ?>&role_filter=<?php echo urlencode($role_filter); ?>" class="page-link">下一页</a>
                        <a href="admin.php?page=users&page_num=<?php echo $total_pages; ?>&items_per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>&status_filter=<?php echo urlencode($status_filter); ?>&role_filter=<?php echo urlencode($role_filter); ?>" class="page-link">末页</a>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-info">
                    显示 <?php echo $offset + 1; ?> - <?php echo min($offset + $items_per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
                </div>
            </div>
            
            <div class="items-per-page">
                <span>每页显示：</span>
                <select id="items-per-page-select" onchange="changeItemsPerPage(this.value)">
                    <?php foreach ($items_per_page_options as $option): ?>
                    <option value="<?php echo $option; ?>" <?php echo ($option == $items_per_page) ? 'selected' : ''; ?>><?php echo $option; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- 用户编辑弹窗 -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeUserModal()">&times;</span>
        <h2 id="modalTitle">编辑用户</h2>
        <div id="modalError" class="alert alert-danger" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 4px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"></div>
        <form id="userForm">
            <input type="hidden" id="user_id" name="user_id">
            <input type="hidden" name="action" value="edit_user">
            
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码 <span id="passwordHint">(留空则不修改)</span></label>
                <input type="password" id="password" name="password" placeholder="新用户必须设置密码">
            </div>
            
            <div class="form-group">
                <label for="role">角色</label>
                <select id="role" name="role">
                    <option value="Member">会员</option>
                    <option value="Editor">用户</option>
                    <option value="Administrator">管理员</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="status">邮箱状态</label>
                <select id="status" name="status">
                    <option value="Active">已验证</option>
                    <option value="Inactive">未验证</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="is_activated">账号激活</label>
                <select id="is_activated" name="is_activated">
                    <option value="1">已激活</option>
                    <option value="0">未激活</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="balance">账户余额 ($)</label>
                <input type="number" id="balance" name="balance" step="0.01" min="0" value="0.00">
                <div class="help-text">用户当前余额，可用于购买产品</div>
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="btn-modal" id="saveButton">更新</button>
                <button type="button" class="btn-modal" id="cancelButton" onclick="closeUserModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<!-- 添加CSS样式 -->
<link rel="stylesheet" href="css/data-table.css">
<style>
.data-table th {
    background-color: #ffccd5;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table tbody tr:hover {
    background-color: #fff5f7;
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
    margin: 5% auto; /* 从10%改为5%，让弹窗更靠上 */
    padding: 20px;
    border-radius: 8px;
    width: 500px;
    max-width: 90%;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    border: 1px solid #f7a4b9;
    box-shadow: 0 2px 10px rgba(231, 84, 128, 0.2);
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

.edit-button {
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

.edit-button:hover {
    background-color: #f7a4b9 !important;
}

.status-active {
    color: #28a745;
    font-weight: bold;
}

.status-inactive {
    color: #dc3545;
    font-weight: bold;
}

.status-pending {
    color: #ffc107;
    font-weight: bold;
}

/* 添加搜索表单样式 */
.search-container {
    margin-bottom: 20px;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}

.search-form {
    display: flex;
    flex-direction: column;
}

.search-inputs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-inputs input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.status-filter,
.role-filter {
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

.add-user-btn {
    display: inline-block;
    padding: 8px 15px;
    background-color: #e75480;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    margin-bottom: 20px;
    cursor: pointer;
    border: none;
}

.add-user-btn:hover {
    background-color: #d64072;
}

.edit-btn, .action-btn {
    background-color: #ffccd5 !important;
    color: #333 !important;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 5px;
}

.edit-btn:hover, .action-btn:hover {
    background-color: #f7a4b9 !important;
}

.delete-button {
    background-color: #ff8da1;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.delete-button:hover {
    background-color: #ff7c93;
}

.status-active {
    color: #e75480;
    font-weight: bold;
}

.modal-header {
    background-color: #e75480;
    color: white;
}

.modal-footer .btn-primary {
    background-color: #e75480;
    border-color: #e75480;
}

.modal-footer .btn-primary:hover {
    background-color: #d64072;
    border-color: #d64072;
}

/* 分页控件样式 */
.pagination-container {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.pagination-left {
    flex: 1;
}

.pagination-center {
    flex: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.pagination {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 5px;
}

.page-link {
    display: inline-block;
    padding: 8px 12px;
    background-color: #ffecf0;
    color: #e75480;
    text-decoration: none;
    border-radius: 4px;
    border: 1px solid #f7a4b9;
    transition: all 0.2s ease;
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

.items-per-page {
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: flex-end;
    flex: 1;
}

.items-per-page span {
    color: #4A4A4A;
    white-space: nowrap;
}

.items-per-page select {
    padding: 6px 10px;
    border: 1px solid #f7a4b9;
    border-radius: 4px;
    background-color: #ffecf0;
    color: #333;
    cursor: pointer;
    transition: all 0.2s ease;
    appearance: auto;
    min-width: 60px;
    text-align: center;
}

.items-per-page select:hover {
    background-color: #ffccd5;
    border-color: #e75480;
}

.items-per-page select:focus {
    outline: none;
    border-color: #e75480;
    box-shadow: 0 0 0 2px rgba(231, 84, 128, 0.25);
}

.pagination-info {
    color: #6c757d;
    font-size: 14px;
    text-align: center;
}

.form-buttons {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-top: 15px;
}

.btn-modal {
    background-color: #ffccd5;
    color: #333;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    flex: 1;
    text-align: center;
}

.btn-modal:hover {
    background-color: #f7a4b9;
}

#saveButton {
    background-color: #ffccd5;
}

#cancelButton {
    background-color: #f0f0f0;
}
</style>

<!-- 添加JavaScript代码 -->
<script>
// 页面加载完成后处理URL参数
document.addEventListener('DOMContentLoaded', function() {
    // 检查URL中是否有success提示参数
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('updated') || urlParams.has('added') || urlParams.has('deleted')) {
        // 显示提示后，移除URL中的参数（不刷新页面）
        setTimeout(function() {
            // 创建新的URL对象
            const newUrl = new URL(window.location.href);
            // 移除success参数
            newUrl.searchParams.delete('updated');
            newUrl.searchParams.delete('added');
            newUrl.searchParams.delete('deleted');
            // 使用History API更新URL，不刷新页面
            window.history.replaceState({}, document.title, newUrl.toString());
        }, 3000); // 3秒后移除参数
    }
});

// 获取弹窗元素
var modal = document.getElementById('userModal');

// 移除点击弹窗外部关闭的功能，只能通过取消按钮或X关闭
// window.onclick = function(event) {
//     if (event.target == modal) {
//         closeUserModal();
//     }
// }

// 打开编辑用户弹窗
function editUser(userId) {
    // 先显示弹窗，避免用户等待
    modal.style.display = 'block';
    
    document.getElementById('modalTitle').innerText = '编辑用户';
    document.getElementById('passwordHint').innerText = '(留空则不修改)';
    document.getElementById('saveButton').innerText = '更新';
    
    // 清空表单
    document.getElementById('userForm').reset();
    document.getElementById('modalError').style.display = 'none';
    
    // 显示加载状态
    const saveButton = document.getElementById('saveButton');
    const originalText = saveButton.innerText;
    saveButton.innerText = '加载中...';
    saveButton.disabled = true;
    
    // 获取用户数据
    fetch('admin.php?page=users&action=get_user&id=' + userId, {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('网络响应错误');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            document.getElementById('user_id').value = data.data.id;
            document.getElementById('username').value = data.data.username;
            document.getElementById('email').value = data.data.email;
            document.getElementById('role').value = data.data.role;
            document.getElementById('status').value = data.data.status;
            document.getElementById('is_activated').value = data.data.is_activated;
            document.getElementById('balance').value = data.data.balance || 0.00;
        } else {
            document.getElementById('modalError').innerText = '获取用户信息失败';
            document.getElementById('modalError').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('modalError').innerText = '获取用户信息时出错: ' + error.message;
        document.getElementById('modalError').style.display = 'block';
    })
    .finally(() => {
        // 恢复按钮状态
        saveButton.innerText = originalText;
        saveButton.disabled = false;
    });
}

// 打开添加用户弹窗
function showAddUserModal() {
    document.getElementById('modalTitle').innerText = '添加新用户';
    document.getElementById('passwordHint').innerText = '(新用户必填)';
    document.getElementById('saveButton').innerText = '添加';
    
    // 清空表单
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('modalError').style.display = 'none';
    
    // 设置默认值
    document.getElementById('role').value = 'Member';
    document.getElementById('status').value = 'Active';
    document.getElementById('is_activated').value = '1';
    document.getElementById('balance').value = '0.00';
    
    // 显示弹窗
    modal.style.display = 'block';
}

// 关闭用户弹窗
function closeUserModal() {
    modal.style.display = 'none';
}

// 处理表单提交
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const isNewUser = !formData.get('user_id');
    
    // 显示加载状态
    const saveButton = document.getElementById('saveButton');
    const originalText = saveButton.innerText;
    saveButton.innerText = '处理中...';
    saveButton.disabled = true;
    
    // 隐藏之前的错误信息
    document.getElementById('modalError').style.display = 'none';
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('网络响应错误: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // 成功更新，刷新页面
            if (isNewUser) {
                window.location.href = 'admin.php?page=users&added=1';
            } else {
            window.location.href = 'admin.php?page=users&updated=1';
            }
        } else {
            // 显示错误信息
            document.getElementById('modalError').innerText = data.message || '操作失败';
            document.getElementById('modalError').style.display = 'block';
            
            // 恢复按钮状态
            saveButton.innerText = originalText;
            saveButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('modalError').innerText = '提交表单时出错: ' + error.message;
        document.getElementById('modalError').style.display = 'block';
        
        // 恢复按钮状态
        saveButton.innerText = originalText;
        saveButton.disabled = false;
    });
});

// 每页显示数量变更处理函数
function changeItemsPerPage(value) {
    // 获取当前URL
    var url = new URL(window.location.href);
    
    // 设置每页显示数量参数
    url.searchParams.set('items_per_page', value);
    
    // 重置页码为1
    url.searchParams.set('page_num', 1);
    
    // 保留搜索关键词和状态筛选
    if (url.searchParams.has('search') && url.searchParams.get('search') !== '') {
        // 搜索关键词已经在URL中，不需要额外处理
    } else {
        url.searchParams.delete('search');
    }
    
    if (url.searchParams.has('status_filter') && url.searchParams.get('status_filter') !== 'all') {
        // 状态筛选已经在URL中，不需要额外处理
    } else if (!url.searchParams.has('status_filter')) {
        url.searchParams.set('status_filter', 'all');
    }
    
    if (url.searchParams.has('role_filter') && url.searchParams.get('role_filter') !== 'all') {
        // 角色筛选已经在URL中，不需要额外处理
    } else if (!url.searchParams.has('role_filter')) {
        url.searchParams.set('role_filter', 'all');
    }
    
    // 跳转到新URL
    window.location.href = url.toString();
}
</script> 