<?php
// 初始化会话
session_start();

// 增加PHP执行时间限制
ini_set('max_execution_time', 300); // 设置为300秒

// 包含数据库配置文件和邮件函数
require_once "db_config.php";
require_once "email_functions.php";

// 定义变量并初始化为空值
$username = $password = $confirm_password = $email = $verification_code = "";
$username_err = $password_err = $confirm_password_err = $email_err = $verification_code_err = "";
$success_message = "";
$send_code_message = "";

// 处理AJAX请求发送验证码
if (isset($_POST['action']) && $_POST['action'] == 'send_code') {
    header('Content-Type: application/json');
    $email = $_POST['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address']);
        exit;
    }
    
    // 检查邮箱是否已注册
    $sql = "SELECT id FROM users WHERE email = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This email is already registered']);
            mysqli_stmt_close($stmt);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // 检查是否在短时间内多次请求验证码
    if (isset($_SESSION['last_code_request']) && (time() - $_SESSION['last_code_request']) < 30) {
        echo json_encode(['status' => 'error', 'message' => 'Request too frequent, please try again later']);
        exit;
    }
    
    // 记录本次请求时间
    $_SESSION['last_code_request'] = time();
    
    // 生成验证码
    $verification_code = generateVerificationCode();
    
    // 保存验证码到数据库
    if (saveVerificationCode($email, $verification_code, $conn)) {
        // 发送验证邮件
        if (sendVerificationEmail($email, $email, $verification_code)) {
            echo json_encode(['status' => 'success', 'message' => 'Email verification code has been sent to your email']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send email verification code, please try again later']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save email verification code, please try again later']);
    }
    exit;
}

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    
    // 验证用户名
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores";
    } else {
        // 准备一个查询语句
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // 绑定变量到预处理语句中
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // 设置参数
            $param_username = trim($_POST["username"]);
            
            // 执行预处理语句
            if (mysqli_stmt_execute($stmt)) {
                // 存储结果
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "This username is already taken";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Something went wrong. Please try again later.";
            }

            // 关闭语句
            mysqli_stmt_close($stmt);
        }
    }
    
    // 验证邮箱
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address";
    } else {
        // 准备一个查询语句
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // 绑定变量到预处理语句中
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            
            // 设置参数
            $param_email = trim($_POST["email"]);
            
            // 执行预处理语句
            if (mysqli_stmt_execute($stmt)) {
                // 存储结果
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $email_err = "This email is already registered";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Something went wrong. Please try again later.";
            }

            // 关闭语句
            mysqli_stmt_close($stmt);
        }
    }
    
    // 验证验证码
    if (empty(trim($_POST["verification_code"]))) {
        $verification_code_err = "Please enter email verification code";
    } else {
        $verification_code = trim($_POST["verification_code"]);
        
        // 只通过数据库验证验证码
        if (!verifyEmailCode($email, $verification_code, $conn)) {
            $verification_code_err = "Invalid or expired email verification code";
        }
    }
    
    // 验证密码
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // 验证确认密码
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match";
        }
    }
    
    // 检查输入错误，然后插入到数据库
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($email_err) && empty($verification_code_err)) {
        
        // 检查邮箱是否符合自动激活条件
        $activation_check = checkEmailForAutoActivation($email, $conn);
        $should_activate = $activation_check['should_activate'];
        $activation_reason = $activation_check['reason'];
        
        // 记录自动激活原因（如果适用）
        error_log("Registration: Email $email activation check - Should activate: " . ($should_activate ? "Yes" : "No") . ", Reason: $activation_reason");
        if ($should_activate) {
            error_log("Auto activation details: " . json_encode($activation_check['details']));
        }
        
        // 插入新用户
        $sql = "INSERT INTO users (username, password, email, status, is_activated) VALUES (?, ?, ?, 'Active', ?)";
         
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // 绑定变量到预处理语句中
            mysqli_stmt_bind_param($stmt, "sssi", $param_username, $param_password, $param_email, $is_activated);
            
            // 设置参数
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // 创建密码哈希
            $param_email = $email;
            $is_activated = $should_activate ? 1 : 0; // 如果符合条件则自动激活
            
            // 尝试执行预处理语句
            if (mysqli_stmt_execute($stmt)) {
                // 获取新插入的用户ID
                $user_id = mysqli_insert_id($conn);
                
                // 如果自动激活，记录激活原因
                if ($should_activate) {
                    $activation_note = "";
                    switch ($activation_reason) {
                        case 'activation_payment':
                            $activation_note = "Auto-activated: Previous activation payment found";
                            break;
                        case 'total_spent':
                            $activation_note = "Auto-activated: Total spent over $100";
                            break;
                        case 'purchase_count':
                            $activation_note = "Auto-activated: Purchase count over 5";
                            break;
                    }
                    
                    // 可以选择将激活原因存储到数据库中
                    $note_sql = "UPDATE users SET activation_payment_id = ? WHERE id = ?";
                    if ($note_stmt = mysqli_prepare($conn, $note_sql)) {
                        mysqli_stmt_bind_param($note_stmt, "si", $activation_note, $user_id);
                        mysqli_stmt_execute($note_stmt);
                        mysqli_stmt_close($note_stmt);
                    }
                }
                
                // 设置会话变量
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $user_id;
                $_SESSION["username"] = $username;
                $_SESSION["email"] = $email;
                $_SESSION["role"] = "Member";
                
                // 根据激活状态重定向
                if ($should_activate) {
                    // 如果已自动激活，直接跳转到主页
                    header("location: main.php?auto_activated=1&reason=" . urlencode($activation_reason));
                } else {
                    // 否则跳转到激活账号页面
                    header("location: activate_account.php");
                }
                exit;
            } else {
                echo "Something went wrong. Please try again later.";
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
    <title>Register - HairCut Network</title>
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
        
        .register-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
            padding: 30px;
            width: 400px;
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
        
        .success-message {
            color: #23d160;
            font-size: 16px;
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #f8fff8;
            border-radius: 4px;
            border: 1px solid #a3e9b5;
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
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #4A4A4A;
        }
        
        .login-link a {
            color: #e75480;
            text-decoration: none;
        }
        
        .login-link a:hover {
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
        
        .verification-group {
            display: flex;
            gap: 10px;
        }
        
        .verification-group input {
            flex: 1;
        }
        
        .send-code-btn {
            background-color: #e75480;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 0 15px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            white-space: nowrap;
        }
        
        .send-code-btn:hover {
            background-color: #d64072;
        }
        
        .send-code-btn:disabled {
            background-color: #ffb6c9;
            cursor: not-allowed;
        }
        
        .code-message {
            font-size: 14px;
            margin-top: 5px;
        }
        
        .code-success {
            color: #23d160;
        }
        
        .code-warning {
            color: #ffaa00;
        }
        
        .code-error {
            color: #ff3860;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Register Account</h2>
        
        <?php if (!empty($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="registerForm">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?php echo $username; ?>">
                <?php if (!empty($username_err)): ?>
                <div class="error-message"><?php echo $username_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="email" value="<?php echo $email; ?>">
                <?php if (!empty($email_err)): ?>
                <div class="error-message"><?php echo $email_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Email Verification Code</label>
                <div class="verification-group">
                    <input type="text" name="verification_code" id="verification_code" maxlength="6" value="<?php echo $verification_code; ?>">
                    <button type="button" class="send-code-btn" id="sendCodeBtn">Send Code</button>
                </div>
                <div id="codeMessage" class="code-message"></div>
                <?php if (!empty($verification_code_err)): ?>
                <div class="error-message"><?php echo $verification_code_err; ?></div>
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
                <label>Confirm Password</label>
                <input type="password" name="confirm_password">
                <?php if (!empty($confirm_password_err)): ?>
                <div class="error-message"><?php echo $confirm_password_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="submit" class="submit-btn" value="Register">
            </div>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
    </div>
    
    <a href="index.php" class="home-link">Back to Home</a>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sendCodeBtn = document.getElementById('sendCodeBtn');
            const emailInput = document.getElementById('email');
            const verificationCodeInput = document.getElementById('verification_code');
            const codeMessage = document.getElementById('codeMessage');
            let cooldown = 0;
            let cooldownInterval;
            
            // 移除验证码自动填充功能
            // 发送验证码
            sendCodeBtn.addEventListener('click', function() {
                const email = emailInput.value.trim();
                
                if (!email) {
                    codeMessage.textContent = 'Please enter your email address to receive verification code';
                    codeMessage.className = 'code-message code-error';
                    return;
                }
                
                if (!isValidEmail(email)) {
                    codeMessage.textContent = 'Please enter a valid email address to receive verification code';
                    codeMessage.className = 'code-message code-error';
                    return;
                }
                
                // 显示发送中状态
                codeMessage.textContent = 'Sending email verification code... This may take up to 60 seconds';
                codeMessage.className = 'code-message';
                
                // 禁用按钮并开始倒计时
                sendCodeBtn.disabled = true;
                cooldown = 60;
                sendCodeBtn.textContent = `Wait (${cooldown}s)`;
                
                // 倒计时处理
                cooldownInterval = setInterval(function() {
                    cooldown--;
                    sendCodeBtn.textContent = `Wait (${cooldown}s)`;
                    
                    if (cooldown <= 0) {
                        clearInterval(cooldownInterval);
                        sendCodeBtn.disabled = false;
                        sendCodeBtn.textContent = 'Send Code';
                    }
                }, 1000);
                
                // 发送AJAX请求
                const formData = new FormData();
                formData.append('action', 'send_code');
                formData.append('email', email);
                
                // 设置请求超时
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000); // 60秒超时
                
                fetch('register.php', {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                })
                .then(response => response.json())
                .then(data => {
                    clearTimeout(timeoutId);
                    if (data.status === 'success') {
                        codeMessage.textContent = data.message;
                        codeMessage.className = 'code-message code-success';
                    } else {
                        codeMessage.textContent = data.message;
                        codeMessage.className = 'code-message code-error';
                        
                        // 如果出错，可以提前结束倒计时
                        if (cooldown > 45) { // 如果还有很长时间，就提前结束
                            clearInterval(cooldownInterval);
                            cooldown = 10; // 设置为10秒后可以重试
                            sendCodeBtn.textContent = `Wait (${cooldown}s)`;
                        }
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    console.error('Error:', error);
                    
                    // 如果是超时错误
                    if (error.name === 'AbortError') {
                        codeMessage.textContent = 'Request timed out. The email verification code may still be sent, please check your email.';
                    } else {
                        codeMessage.textContent = 'An error occurred. Please try again.';
                    }
                    codeMessage.className = 'code-message code-error';
                    
                    // 如果出错，提前结束倒计时
                    clearInterval(cooldownInterval);
                    cooldown = 5; // 5秒后可以重试
                    sendCodeBtn.textContent = `Wait (${cooldown}s)`;
                });
            });
            
            // 验证邮箱格式
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
        });
    </script>
</body>
</html> 