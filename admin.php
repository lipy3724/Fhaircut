<?php
// 开启输出缓冲，解决header已发送的问题
ob_start();

// 启动会话
session_start();

// 包含数据库配置文件
require_once "db_config.php";

// 处理AJAX请求
if (isset($_GET['ajax']) && $_GET['ajax'] === 'resend_email') {
    // 将请求转发到purchases_content.php处理
    define('ADMIN_ACCESS', true);
    require_once "admin/purchases_content.php";
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_user' && isset($_GET['id'])) {
    // 关闭任何可能的输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $user_id = intval($_GET['id']);
    $response = ['success' => false, 'data' => null];
    
    $sql = "SELECT id, username, email, role, status, is_activated, balance, last_login_ip, last_login_country, last_login_time FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                $response['success'] = true;
                $response['data'] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // 设置响应头
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 处理添加/编辑用户的AJAX请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    // 关闭任何可能的输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $response = ['success' => false, 'message' => ''];
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'Member';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Active';
    $is_activated = isset($_POST['is_activated']) ? intval($_POST['is_activated']) : 0;
    $balance = isset($_POST['balance']) ? floatval($_POST['balance']) : 0.00;
    
    // 验证输入
    if (empty($username)) {
        $response['message'] = "用户名不能为空";
    } elseif (empty($email)) {
        $response['message'] = "邮箱不能为空";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = "邮箱格式无效";
    } else {
        if ($user_id > 0) {
            // 编辑现有用户
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
                        $sql = "UPDATE users SET username = ?, email = ?, password = ?, role = ?, status = ?, is_activated = ?, balance = ? WHERE id = ?";
                        if ($update_stmt = mysqli_prepare($conn, $sql)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            mysqli_stmt_bind_param($update_stmt, "sssssidd", $username, $email, $hashed_password, $role, $status, $is_activated, $balance, $user_id);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $response['success'] = true;
                                $response['message'] = "用户更新成功";
                            } else {
                                $response['message'] = "更新用户时出错: " . mysqli_error($conn);
                            }
                            mysqli_stmt_close($update_stmt);
                        }
                    } else {
                        // 更新不包括密码
                        $sql = "UPDATE users SET username = ?, email = ?, role = ?, status = ?, is_activated = ?, balance = ? WHERE id = ?";
                        if ($update_stmt = mysqli_prepare($conn, $sql)) {
                            mysqli_stmt_bind_param($update_stmt, "ssssidd", $username, $email, $role, $status, $is_activated, $balance, $user_id);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $response['success'] = true;
                                $response['message'] = "用户更新成功";
                            } else {
                                $response['message'] = "更新用户时出错: " . mysqli_error($conn);
                            }
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // 添加新用户
            // 检查用户名和邮箱是否已存在
            $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            if ($stmt = mysqli_prepare($conn, $check_sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $username, $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $response['message'] = "用户名或邮箱已被使用";
                } else {
                    // 密码为必填项
                    if (empty($password)) {
                        $response['message'] = "密码不能为空";
                    } else {
                        // 添加新用户
                        $sql = "INSERT INTO users (username, email, password, role, status, is_activated, balance, registered_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        if ($insert_stmt = mysqli_prepare($conn, $sql)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            mysqli_stmt_bind_param($insert_stmt, "sssssid", $username, $email, $hashed_password, $role, $status, $is_activated, $balance);
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                $response['success'] = true;
                                $response['message'] = "用户添加成功";
                            } else {
                                $response['message'] = "添加用户时出错: " . mysqli_error($conn);
                            }
                            mysqli_stmt_close($insert_stmt);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // 返回JSON响应
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 检查用户是否已登录到后台系统
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    // 如果是POST请求，尝试登录
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST["username"];
        $password = $_POST["password"];
        
        // 验证管理员账户
        if ($username === "admin" && $password === "password123") {
            $_SESSION["admin_loggedin"] = true;
            $_SESSION["admin_username"] = "admin";
            $_SESSION["admin_role"] = "Administrator";
            
            // 重定向到管理面板
            header("location: admin.php");
            exit;
        } else {
            $login_err = "用户名或密码错误";
        }
    }
    
    // 显示登录表单
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录 - 剪发网</title>
        <style>
            body {
                font-family: "Microsoft YaHei", "PingFang SC", "Hiragino Sans GB", sans-serif;
                background-color: #F8F7FF;
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            
            .login-container {
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                width: 350px;
            }
            
            h2 {
                text-align: center;
                margin-bottom: 25px;
                color: #B8B5E1;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                color: #555;
            }
            
            .form-group input {
                width: 100%;
                padding: 10px;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                box-sizing: border-box;
            }
            
            .error-message {
                color: #e74c3c;
                font-size: 14px;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .submit-btn {
                width: 100%;
                padding: 12px;
                background-color: #e75480;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }
            
            .submit-btn:hover {
                background-color: #d64072;
            }
            
            .back-link {
                text-align: center;
                margin-top: 20px;
            }
            
            .back-link a {
                color: #9E9BC7;
                text-decoration: none;
            }
            
            .back-link a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>管理员登录</h2>
            
            <?php if (isset($login_err)): ?>
                <div class="error-message"><?php echo $login_err; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" class="submit-btn">登录</button>
            </form>
            
            <div class="back-link">
                <a href="index.php">返回首页</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 如果用户已登录，显示管理面板
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - 剪发网</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", "PingFang SC", "Hiragino Sans GB", sans-serif;
        }
        
        body {
            background-color: #fff5f7;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 200px;
            background-color: #ffb6c9;
            color: #333;
            padding: 20px 0;
        }
        
        .sidebar h1 {
            text-align: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 20px;
            font-size: 20px;
            color: #e75480;
        }
        
        .sidebar ul {
            list-style: none;
        }
        
        .sidebar ul li {
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar ul li a {
            color: #333;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
        }
        
        .sidebar ul li a:hover {
            padding-left: 5px;
            color: #e75480;
        }
        
        .sidebar ul li.active {
            background-color: #ffccd5;
        }
        
        /* 商品管理伸缩菜单样式 */
        .menu-group {
            position: relative;
        }
        
        .menu-group > a {
            position: relative;
            padding-right: 30px !important;
        }
        
        .menu-group > a::after {
            content: '▶';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s ease;
            font-size: 12px;
        }
        
        .menu-group.expanded > a::after {
            transform: translateY(-50%) rotate(90deg);
        }
        
        .submenu {
            display: none;
            background-color: rgba(255, 255, 255, 0.1);
            margin-left: 0;
        }
        
        .submenu.show {
            display: block;
        }
        
        .submenu li {
            padding-left: 40px !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .submenu li a {
            font-size: 14px;
            color: #666 !important;
        }
        
        .submenu li a:hover {
            color: #e75480 !important;
            padding-left: 5px;
        }
        
        .submenu li.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: white;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }
        
        .header h2 {
            color: #4A4A4A;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 15px;
            color: #4A4A4A;
        }
        
        .user-info a {
            color: #e75480;
            text-decoration: none;
        }
        
        .user-info a:hover {
            text-decoration: underline;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ffccd5;
        }
        
        table th {
            background-color: #ffccd5;
            color: #e75480;
            font-weight: 600;
        }
        
        table tr:nth-child(even) {
            background-color: #fff5f7;
        }
        
        table tr:hover {
            background-color: #ffecf0;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            background-color: #ffccd5;
            color: #e75480;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .pagination a.active {
            background-color: #e75480;
            color: white;
        }
        
        .pagination a:hover:not(.active) {
            background-color: #f7a4b9;
        }
        
        .modal-header {
            background-color: #e75480;
            color: white;
        }
        
        .modal-footer button {
            background-color: #e75480;
            color: white;
        }
        
        .modal-footer button:hover {
            background-color: #d64072;
        }
        
        .action-buttons a {
            display: inline-block;
            margin-right: 10px;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 3px;
        }
        
        .action-buttons a.edit {
            background-color: #B8B5E1;
            color: #4A4A4A;
        }
        
        .action-buttons a.delete {
            background-color: #ff6b6b;
            color: white;
        }
        
        form {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #4A4A4A;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .form-buttons {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            margin-top: 30px;
        }
        
        .form-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .form-buttons button[type="submit"] {
            background-color: #B8B5E1;
            color: #4A4A4A;
        }
        
        .form-buttons .cancel {
            background-color: #e0e0e0;
            color: #4A4A4A;
        }
        
        /* 添加更多样式 */
        .status-active {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #e74c3c;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .add-button {
            display: inline-block;
            padding: 8px 15px;
            background-color: #B8B5E1;
            color: #4A4A4A;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
        }
        
        .edit-button {
            background-color: #B8B5E1;
            color: #4A4A4A;
        }
        
        .delete-button {
            background-color: #ff6b6b;
            color: white;
        }
        
        .admin-content {
            margin-bottom: 30px;
        }
        
        .action-buttons {
            margin-bottom: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table th {
            background-color: #f8f8f8;
            font-weight: 600;
            color: #4A4A4A;
        }
        
        .actions a {
            display: inline-block;
            margin-right: 5px;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 3px;
        }
        
        .admin-form-container {
            max-width: 1200px;
            width: calc(100% - 40px);
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .submit-button {
            background-color: #B8B5E1;
            color: #4A4A4A;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            cursor: pointer;
        }
        
        .cancel-button {
            background-color: #e0e0e0;
            color: #4A4A4A;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            text-decoration: none;
            display: inline-block;
        }
        
        .no-data {
            text-align: center;
            color: #777;
            padding: 20px;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background-color: #e75480;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .submit-btn:hover {
            background-color: #d64072;
        }
        
        .action-btn {
            padding: 6px 12px;
            background-color: #e75480;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            font-size: 14px;
        }
        
        .action-btn:hover {
            background-color: #d64072;
        }
        
        .delete-btn {
            background-color: #ff8da1;
        }
        
        .delete-btn:hover {
            background-color: #ff7c93;
        }
        
        .add-btn {
            background-color: #e75480;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .add-btn:hover {
            background-color: #d64072;
        }
        
        .dashboard-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .stat-card {
            flex: 1;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
            margin: 0 10px;
            text-align: center;
        }
        
        .stat-card:first-child {
            margin-left: 0;
        }
        
        .stat-card:last-child {
            margin-right: 0;
        }
        
        .stat-title {
            font-size: 16px;
            color: #777;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #e75480;
        }
        
        .stat-link a {
            color: #e75480;
            text-decoration: none;
            font-size: 14px;
        }
        
        .stat-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h1>后台管理系统</h1>
        <ul>
            <li <?php echo !isset($_GET['page']) || $_GET['page'] === 'dashboard' ? 'class="active"' : ''; ?>>
                <a href="admin.php?page=dashboard">控制面板</a>
            </li>
            
            <!-- 商品管理伸缩菜单 -->
            <li class="menu-group <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['categories', 'products', 'hair'])) ? 'expanded' : ''; ?>">
                <a href="javascript:void(0)" onclick="toggleSubmenu(this)">商品管理</a>
                <ul class="submenu <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['categories', 'products', 'hair'])) ? 'show' : ''; ?>">
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'categories' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=categories">视频照片分类管理</a>
                    </li>
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'products' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=products">视频照片管理</a>
                    </li>
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'hair' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=hair">头发管理</a>
                    </li>
                </ul>
            </li>
            
            <!-- 订单管理伸缩菜单 -->
            <li class="menu-group <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['purchases', 'hair_orders'])) ? 'expanded' : ''; ?>">
                <a href="javascript:void(0)" onclick="toggleOrderSubmenu(this)">订单管理</a>
                <ul class="submenu <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['purchases', 'hair_orders'])) ? 'show' : ''; ?>">
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'purchases' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=purchases">视频照片订单管理</a>
                    </li>
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'hair_orders' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=hair_orders">头发订单管理</a>
                    </li>
                </ul>
            </li>
            
            <!-- 用户管理伸缩菜单 -->
            <li class="menu-group <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['users', 'login_logs'])) ? 'expanded' : ''; ?>">
                <a href="javascript:void(0)" onclick="toggleUserSubmenu(this)">用户管理</a>
                <ul class="submenu <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['users', 'login_logs'])) ? 'show' : ''; ?>">
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'users' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=users">用户列表</a>
                    </li>
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'login_logs' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=login_logs">登录记录</a>
                    </li>
                </ul>
            </li>
            
            <!-- 网页管理伸缩菜单 -->
            <li class="menu-group <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['background', 'banner', 'contact', 'activation'])) ? 'expanded' : ''; ?>">
                <a href="javascript:void(0)" onclick="toggleWebSubmenu(this)">网页管理</a>
                <ul class="submenu <?php echo (isset($_GET['page']) && in_array($_GET['page'], ['background', 'banner', 'contact', 'activation'])) ? 'show' : ''; ?>">
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'background' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=background">背景图片</a>
                    </li>
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'banner' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=banner">首页横幅</a>
                    </li>
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'contact' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=contact">联系方式</a>
                    </li>
                    <li <?php echo isset($_GET['page']) && $_GET['page'] === 'activation' ? 'class="active"' : ''; ?>>
                        <a href="admin.php?page=activation">注册激活页面</a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>
                <?php
                $page = $_GET['page'] ?? 'dashboard';
                switch ($page) {
                    case 'dashboard':
                        echo '控制面板';
                        break;
                    case 'categories':
                        echo '视频照片分类管理';
                        break;
                    case 'products':
                        echo '视频照片管理';
                        break;
                    case 'hair':
                        echo '头发管理';
                        break;
                    case 'hair_orders':
                        echo '头发订单管理';
                        break;
                    case 'users':
                        echo '用户管理';
                        break;
                    case 'login_logs':
                        echo '用户登录记录';
                        break;
                    case 'purchases':
                        echo '视频照片订单管理';
                        break;
                    case 'background':
                        echo '背景图片管理';
                        break;
                    case 'banner':
                        echo '首页横幅管理';
                        break;
                    case 'contact':
                        echo '联系方式管理';
                        break;
                    case 'activation':
                        echo '注册激活页面管理';
                        break;
                    default:
                        echo '控制面板';
                }
                ?>
            </h2>
            <div class="user-info">
                <span>欢迎, <?php echo htmlspecialchars($_SESSION["admin_username"]); ?></span>
                <a href="admin_logout.php">退出登录</a>
            </div>
        </div>
        
        <?php
        // 根据页面参数包含相应的内容
        $page = $_GET['page'] ?? 'dashboard';
        switch ($page) {
            case 'dashboard':
                include 'admin/dashboard.php';
                break;
            case 'categories':
                include 'admin/categories.php';
                break;
            case 'products':
                include 'admin/products.php';
                break;
            case 'hair':
                include 'admin/hair.php';
                break;
            case 'hair_orders':
                // 定义一个常量，表示是通过管理界面访问
                define('ADMIN_ACCESS', true);
                include 'admin/hair_orders.php';
                break;
            case 'users':
                include 'admin/users.php';
                break;
            case 'login_logs':
                include 'admin/login_logs.php';
                break;
            case 'purchases':
                // 定义一个常量，表示是通过管理界面访问
                define('ADMIN_ACCESS', true);
                include 'admin/purchases_content.php';
                break;
            case 'background':
                include 'admin/background.php';
                break;
            case 'banner':
                include 'admin/banner.php';
                break;
            case 'contact':
                include 'admin/contact.php';
                break;
            case 'activation':
                include 'admin/activation.php';
                break;
            default:
                include 'admin/dashboard.php';
        }
        ?>
    </div>
    
    <script>
        // 商品管理伸缩菜单功能
        function toggleSubmenu(element) {
            const menuGroup = element.parentNode;
            const submenu = menuGroup.querySelector('.submenu');
            
            // 切换展开状态
            menuGroup.classList.toggle('expanded');
            submenu.classList.toggle('show');
            
            // 保存状态到localStorage
            const isExpanded = menuGroup.classList.contains('expanded');
            localStorage.setItem('productMenuExpanded', isExpanded);
        }
        
        // 订单管理伸缩菜单功能
        function toggleOrderSubmenu(element) {
            const menuGroup = element.parentNode;
            const submenu = menuGroup.querySelector('.submenu');
            
            // 切换展开状态
            menuGroup.classList.toggle('expanded');
            submenu.classList.toggle('show');
            
            // 保存状态到localStorage
            const isExpanded = menuGroup.classList.contains('expanded');
            localStorage.setItem('orderMenuExpanded', isExpanded);
        }
        
        // 用户管理伸缩菜单功能
        function toggleUserSubmenu(element) {
            const menuGroup = element.parentNode;
            const submenu = menuGroup.querySelector('.submenu');
            
            // 切换展开状态
            menuGroup.classList.toggle('expanded');
            submenu.classList.toggle('show');
            
            // 保存状态到localStorage
            const isExpanded = menuGroup.classList.contains('expanded');
            localStorage.setItem('userMenuExpanded', isExpanded);
        }
        
        // 网页管理伸缩菜单功能
        function toggleWebSubmenu(element) {
            const menuGroup = element.parentNode;
            const submenu = menuGroup.querySelector('.submenu');
            
            // 切换展开状态
            menuGroup.classList.toggle('expanded');
            submenu.classList.toggle('show');
            
            // 保存状态到localStorage
            const isExpanded = menuGroup.classList.contains('expanded');
            localStorage.setItem('webMenuExpanded', isExpanded);
        }
        
        // 页面加载时恢复菜单状态
        document.addEventListener('DOMContentLoaded', function() {
            // 处理商品管理菜单
            const productMenuGroup = document.querySelectorAll('.menu-group')[0]; // 第一个菜单组
            const productSubmenu = productMenuGroup?.querySelector('.submenu');
            
            // 检查是否有活动的子菜单项
            const hasActiveProductSubmenu = productSubmenu?.querySelector('li.active');
            
            // 如果有活动的子菜单项，或者localStorage中保存了展开状态，则展开菜单
            const savedProductState = localStorage.getItem('productMenuExpanded');
            if (hasActiveProductSubmenu || savedProductState === 'true') {
                productMenuGroup?.classList.add('expanded');
                productSubmenu?.classList.add('show');
            }
            
            // 处理订单管理菜单
            const orderMenuGroup = document.querySelectorAll('.menu-group')[1]; // 第二个菜单组
            const orderSubmenu = orderMenuGroup?.querySelector('.submenu');
            
            // 检查是否有活动的子菜单项
            const hasActiveOrderSubmenu = orderSubmenu?.querySelector('li.active');
            
            // 如果有活动的子菜单项，或者localStorage中保存了展开状态，则展开菜单
            const savedOrderState = localStorage.getItem('orderMenuExpanded');
            if (hasActiveOrderSubmenu || savedOrderState === 'true') {
                orderMenuGroup?.classList.add('expanded');
                orderSubmenu?.classList.add('show');
            }
            
            // 处理用户管理菜单
            const userMenuGroup = document.querySelectorAll('.menu-group')[2]; // 第三个菜单组
            const userSubmenu = userMenuGroup?.querySelector('.submenu');
            
            // 检查是否有活动的子菜单项
            const hasActiveUserSubmenu = userSubmenu?.querySelector('li.active');
            
            // 如果有活动的子菜单项，或者localStorage中保存了展开状态，则展开菜单
            const savedUserState = localStorage.getItem('userMenuExpanded');
            if (hasActiveUserSubmenu || savedUserState === 'true') {
                userMenuGroup?.classList.add('expanded');
                userSubmenu?.classList.add('show');
            }
            
            // 处理网页管理菜单
            const webMenuGroup = document.querySelectorAll('.menu-group')[3]; // 第四个菜单组
            const webSubmenu = webMenuGroup?.querySelector('.submenu');
            
            // 检查是否有活动的子菜单项
            const hasActiveWebSubmenu = webSubmenu?.querySelector('li.active');
            
            // 如果有活动的子菜单项，或者localStorage中保存了展开状态，则展开菜单
            const savedWebState = localStorage.getItem('webMenuExpanded');
            if (hasActiveWebSubmenu || savedWebState === 'true') {
                webMenuGroup?.classList.add('expanded');
                webSubmenu?.classList.add('show');
            }
        });
        
        // 点击子菜单项时保持菜单展开状态
        document.querySelectorAll('.submenu a').forEach(function(link) {
            link.addEventListener('click', function() {
                // 确定是哪个菜单组
                const menuGroup = link.closest('.menu-group');
                const menuGroups = document.querySelectorAll('.menu-group');
                const isProductMenu = menuGroup === menuGroups[0];
                const isOrderMenu = menuGroup === menuGroups[1];
                const isUserMenu = menuGroup === menuGroups[2];
                const isWebMenu = menuGroup === menuGroups[3];
                
                if (isProductMenu) {
                    localStorage.setItem('productMenuExpanded', 'true');
                } else if (isOrderMenu) {
                    localStorage.setItem('orderMenuExpanded', 'true');
                } else if (isUserMenu) {
                    localStorage.setItem('userMenuExpanded', 'true');
                } else if (isWebMenu) {
                    localStorage.setItem('webMenuExpanded', 'true');
                }
            });
        });
    </script>
</body>
</html> 