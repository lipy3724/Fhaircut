<?php
// 设置字符编码
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// 显示错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 初始化会话
session_start();

// 检查用户是否已经登录，如果是则重定向到主页
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: home.php");
    exit;
}

// 包含数据库配置文件和邮件函数
require_once "db_config.php";
require_once "email_functions.php";

// 定义变量并初始化为空值
$username = $password = "";
$username_err = $password_err = $login_err = "";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 检查用户名是否为空
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // 检查密码是否为空
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter password";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // 验证凭据
    if (empty($username_err) && empty($password_err)) {
        // 准备查询语句
        $sql = "SELECT id, username, password, role, status, email, is_activated FROM users WHERE username = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // 绑定变量到预处理语句中
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // 设置参数
            $param_username = $username;
            
            // 尝试执行预处理语句
            if (mysqli_stmt_execute($stmt)) {
                // 存储结果
                mysqli_stmt_store_result($stmt);
                
                // 检查用户名是否存在，如果存在则验证密码
                if (mysqli_stmt_num_rows($stmt) == 1) {                    
                    // 绑定结果变量
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role, $status, $email, $is_activated);
                    if (mysqli_stmt_fetch($stmt)) {
                        // 检查账户状态
                        if ($status == "Inactive") {
                            $login_err = "Your account is not activated. Please check your email for verification code or <a href='verify.php?email=" . urlencode($email) . "'>click here</a> to verify your account.";
                        } else if (password_verify($password, $hashed_password)) {
                            // 检查账户是否已付费激活
                            if (!$is_activated) {
                                // 检查邮箱是否符合自动激活条件
                                $activation_check = checkEmailForAutoActivation($email, $conn);
                                $should_activate = $activation_check['should_activate'];
                                $activation_reason = $activation_check['reason'];
                                
                                if ($should_activate) {
                                    // 自动激活账户
                                    error_log("Auto-activating account during login - Email: $email, Reason: $activation_reason");
                                    error_log("Auto activation details: " . json_encode($activation_check['details']));
                                    
                                    // 更新用户激活状态
                                    $activation_note = "";
                                    switch ($activation_reason) {
                                        case 'activation_payment':
                                            $activation_note = "Auto-activated during login: Previous activation payment found";
                                            break;
                                        case 'total_spent':
                                            $activation_note = "Auto-activated during login: Total spent over $100";
                                            break;
                                        case 'purchase_count':
                                            $activation_note = "Auto-activated during login: Purchase count over 5";
                                            break;
                                    }
                                    
                                    $update_sql = "UPDATE users SET is_activated = 1, activation_payment_id = ? WHERE id = ?";
                                    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                                        mysqli_stmt_bind_param($update_stmt, "si", $activation_note, $id);
                                        mysqli_stmt_execute($update_stmt);
                                        mysqli_stmt_close($update_stmt);
                                        
                                        // 设置会话变量
                                        $_SESSION["loggedin"] = true;
                                        $_SESSION["id"] = $id;
                                        $_SESSION["username"] = $username;
                                        $_SESSION["role"] = $role;
                                        $_SESSION["email"] = $email;
                                        
                                        // 获取用户IP地址和位置信息（为自动激活用户记录登录）
                                        $ip_address = $_SERVER['REMOTE_ADDR'];
                                        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                                            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
                                        }
                                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                        
                                        // 获取地理位置信息
                                        $country = "未知";
                                        $location = "";
                                        try {
                                            $ip_data = @file_get_contents("http://ip-api.com/json/".$ip_address."?lang=zh-CN");
                                            if ($ip_data !== false) {
                                                $ip_info = json_decode($ip_data, true);
                                                if ($ip_info && $ip_info['status'] === 'success') {
                                                    $country = $ip_info['country'] ?? '未知';
                                                    $region = $ip_info['regionName'] ?? '';
                                                    $city = $ip_info['city'] ?? '';
                                                    
                                                    $location_parts = array_filter([$country, $region, $city]);
                                                    $location = implode(', ', $location_parts);
                                                }
                                            }
                                        } catch (Exception $e) {
                                            error_log("获取地理位置信息失败: " . $e->getMessage());
                                        }
                                        
                                        if (empty($location)) {
                                            $location = $country;
                                        }
                                        
                                        // 插入登录记录到login_logs表
                                        $insert_log_sql = "INSERT INTO login_logs (user_id, username, email, login_ip, login_location, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
                                        if ($log_stmt = mysqli_prepare($conn, $insert_log_sql)) {
                                            mysqli_stmt_bind_param($log_stmt, "isssss", $id, $username, $email, $ip_address, $location, $user_agent);
                                            if (mysqli_stmt_execute($log_stmt)) {
                                                error_log("自动激活用户登录记录已保存 - 用户ID: $id, 用户名: $username, IP: $ip_address, 位置: $location");
                                            } else {
                                                error_log("保存自动激活用户登录记录失败: " . mysqli_error($conn));
                                            }
                                            mysqli_stmt_close($log_stmt);
                                        }
                                        
                                        // 重定向到主页，显示自动激活消息
                                        header("location: home.php?auto_activated=1&reason=" . urlencode($activation_reason));
                                        exit;
                                    }
                                } else {
                                    // 密码正确，但账户未激活，启动会话以便激活流程
                                    session_start();
                                    
                                    // 存储数据到会话变量，但仅用于激活流程
                                    $_SESSION["temp_id"] = $id;
                                    $_SESSION["temp_username"] = $username;
                                    $_SESSION["temp_email"] = $email;
                                    
                                    // 记录登录信息
                                    error_log("User attempted login but not activated - ID: $id, Username: $username, Email: $email");
                                    
                                    // 直接重定向到激活账号页面
                                    header("location: activate_account.php");
                                    exit;
                                }
                            } else {
                                // 密码正确，账户已激活，启动会话
                                session_start();
                                
                                // 存储数据到会话变量
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id;
                                $_SESSION["username"] = $username;
                                $_SESSION["role"] = $role;
                                $_SESSION["email"] = $email;
                                
                                // 获取用户IP地址
                                $ip_address = $_SERVER['REMOTE_ADDR'];
                                if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                                    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
                                }
                                
                                // 获取用户代理信息
                                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                
                                // 获取地理位置信息（使用免费API）
                                $country = "未知";
                                $region = "";
                                $city = "";
                                $location = "";
                                try {
                                    $ip_data = @file_get_contents("http://ip-api.com/json/".$ip_address."?lang=zh-CN");
                                    if ($ip_data !== false) {
                                        $ip_info = json_decode($ip_data, true);
                                        if ($ip_info && $ip_info['status'] === 'success') {
                                            $country = $ip_info['country'] ?? '未知';
                                            $region = $ip_info['regionName'] ?? '';
                                            $city = $ip_info['city'] ?? '';
                                            
                                            // 组合地理位置信息
                                            $location_parts = array_filter([$country, $region, $city]);
                                            $location = implode(', ', $location_parts);
                                        }
                                    }
                                } catch (Exception $e) {
                                    error_log("获取地理位置信息失败: " . $e->getMessage());
                                }
                                
                                // 如果没有获取到详细位置，使用国家信息
                                if (empty($location)) {
                                    $location = $country;
                                }
                                
                                // 更新用户的最后登录信息
                                $update_login_sql = "UPDATE users SET last_login_ip = ?, last_login_country = ?, last_login_time = NOW() WHERE id = ?";
                                if ($login_stmt = mysqli_prepare($conn, $update_login_sql)) {
                                    mysqli_stmt_bind_param($login_stmt, "ssi", $ip_address, $country, $id);
                                    mysqli_stmt_execute($login_stmt);
                                    mysqli_stmt_close($login_stmt);
                                }
                                
                                // 插入登录记录到login_logs表
                                $insert_log_sql = "INSERT INTO login_logs (user_id, username, email, login_ip, login_location, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
                                if ($log_stmt = mysqli_prepare($conn, $insert_log_sql)) {
                                    mysqli_stmt_bind_param($log_stmt, "isssss", $id, $username, $email, $ip_address, $location, $user_agent);
                                    if (mysqli_stmt_execute($log_stmt)) {
                                        error_log("登录记录已保存 - 用户ID: $id, 用户名: $username, IP: $ip_address, 位置: $location");
                                    } else {
                                        error_log("保存登录记录失败: " . mysqli_error($conn));
                                    }
                                    mysqli_stmt_close($log_stmt);
                                }
                                    
                                // 记录登录信息
                                error_log("User logged in - ID: $id, Username: $username, Email: $email, IP: $ip_address, Location: $location");
                                
                                // 重定向用户到主页
                                header("location: home.php");
                            }
                        } else {
                            // 密码不正确
                            $login_err = "Invalid username or password";
                        }
                    }
                } else {
                    // 用户名不存在
                    $login_err = "Invalid username or password";
                }
            } else {
                echo "Something went wrong! Please try again later.";
            }

            // 关闭语句
            mysqli_stmt_close($stmt);
        }
    }
    
    // 关闭连接
    mysqli_close($conn);
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HairCut Network</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #fff5f7;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            flex-direction: column;
        }
        
        .login-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
            padding: 30px;
            width: 350px;
            max-width: 100%;
            border: 1px solid #ffccd5;
        }
        
        h2 {
            color: #e75480;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #4A4A4A;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #f7a4b9;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #e75480;
            box-shadow: 0 0 0 2px rgba(231, 84, 128, 0.2);
        }
        
        .error-message {
            color: #ff3860;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .login-error {
            color: #ff3860;
            font-size: 16px;
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #fff8f8;
            border-radius: 4px;
            border: 1px solid #ffccd5;
        }
        
        .login-error a {
            color: #e75480;
            text-decoration: none;
        }
        
        .login-error a:hover {
            text-decoration: underline;
        }
        
        .submit-btn {
            background-color: #e75480;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 12px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #d64072;
        }
        
        .forgot-link, .register-link {
            text-align: center;
            margin-top: 15px;
            color: #4A4A4A;
        }
        
        .forgot-link a, .register-link a {
            color: #e75480;
            text-decoration: none;
        }
        
        .forgot-link a:hover, .register-link a:hover {
            text-decoration: underline;
        }
        
        .home-link {
            color: #e75480;
            text-decoration: none;
            margin-top: 20px;
            display: inline-block;
        }
        
        .home-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>User Login</h2>
        
        <?php if (!empty($login_err)): ?>
        <div class="login-error"><?php echo $login_err; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo $username; ?>">
                <?php if (!empty($username_err)): ?>
                <div class="error-message"><?php echo $username_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password">
                <?php if (!empty($password_err)): ?>
                <div class="error-message"><?php echo $password_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="submit" class="submit-btn" value="Login">
            </div>
            
            <div class="forgot-link">
                <a href="forgot-password.php">Forgot password?</a>
            </div>
            
            <div class="register-link">
                No account? <a href="register.php">Register now</a>
            </div>
        </form>
    </div>
    
    <a href="index.php" class="home-link">Back to Home</a>
</body>
</html> 