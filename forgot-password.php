<?php
// 初始化会话
session_start();

// 包含数据库配置文件和邮件函数
require_once "db_config.php";
require_once "email_functions.php";

// 定义变量并初始化为空值
$email = $verification_code = $new_password = $confirm_password = "";
$email_err = $verification_code_err = $new_password_err = $confirm_password_err = "";
$success_message = $error_message = "";
$show_email_form = true;
$show_verification_form = false;
$show_password_form = false;

// 处理AJAX请求发送验证码
if (isset($_POST['action']) && $_POST['action'] == 'send_code') {
    header('Content-Type: application/json');
    $email = $_POST['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address']);
        exit;
    }
    
    // 检查邮箱是否存在于用户表中
    $sql = "SELECT id FROM users WHERE email = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 0) {
            echo json_encode(['status' => 'error', 'message' => 'No account found with this email address']);
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
            echo json_encode(['status' => 'success', 'message' => 'Verification code has been sent to your email']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send verification code, please try again later']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save verification code, please try again later']);
    }
    exit;
}

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 第一步：验证邮箱和验证码
    if (isset($_POST["verify_email"])) {
        // 验证邮箱
        if (empty(trim($_POST["email"]))) {
            $email_err = "Please enter your email";
        } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address";
        } else {
            $email = trim($_POST["email"]);
            
            // 检查邮箱是否存在于用户表中
            $sql = "SELECT id FROM users WHERE email = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 0) {
                    $email_err = "No account found with this email address";
                } else {
                    // 验证验证码
                    if (empty(trim($_POST["verification_code"] ?? ""))) {
                        $verification_code_err = "Please enter verification code";
                    } else {
                        $verification_code = trim($_POST["verification_code"]);
                        
                        // 验证码是否正确
                        if (!verifyEmailCode($email, $verification_code, $conn)) {
                            $verification_code_err = "Invalid or expired verification code";
                        } else {
                            // 验证通过，直接进入密码重置页面
                            $show_email_form = false;
                            $show_verification_form = false;
                            $show_password_form = true;
                            
                            // 保存邮箱到会话，以便后续步骤使用
                            $_SESSION["reset_email"] = $email;
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // 第二步：验证验证码
    if (isset($_POST["verify_code"])) {
        // 从会话中获取邮箱
        $email = $_SESSION["reset_email"] ?? "";
        
        // 验证验证码
        if (empty(trim($_POST["verification_code"]))) {
            $verification_code_err = "Please enter verification code";
        } else {
            $verification_code = trim($_POST["verification_code"]);
            
            // 验证码是否正确
            if (!verifyEmailCode($email, $verification_code, $conn)) {
                $verification_code_err = "Invalid or expired verification code";
            } else {
                // 验证码验证通过，显示密码重置表单
                $show_verification_form = false;
                $show_password_form = true;
            }
        }
    }
    
    // 第三步：重置密码
    if (isset($_POST["reset_password"])) {
        // 从会话中获取邮箱
        $email = $_SESSION["reset_email"] ?? "";
        
        // 验证新密码
        if (empty(trim($_POST["new_password"]))) {
            $new_password_err = "Please enter a new password";     
        } elseif (strlen(trim($_POST["new_password"])) < 6) {
            $new_password_err = "Password must have at least 6 characters";
        } else {
            $new_password = trim($_POST["new_password"]);
        }
        
        // 验证确认密码
        if (empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm password";     
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if (empty($new_password_err) && ($new_password != $confirm_password)) {
                $confirm_password_err = "Passwords did not match";
            }
        }
        
        // 检查输入错误，然后更新密码
        if (empty($new_password_err) && empty($confirm_password_err)) {
            // 准备更新语句
            $sql = "UPDATE users SET password = ? WHERE email = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                // 绑定变量到预处理语句中
                mysqli_stmt_bind_param($stmt, "ss", $param_password, $param_email);
                
                // 设置参数
                $param_password = password_hash($new_password, PASSWORD_DEFAULT); // 创建密码的哈希值
                $param_email = $email;
                
                // 执行预处理语句
                if (mysqli_stmt_execute($stmt)) {
                    // 密码重置成功
                    $success_message = "Your password has been reset successfully. You can now <a href='login.php'>login</a> with your new password.";
                    
                    // 清除会话变量
                    unset($_SESSION["reset_email"]);
                    
                    // 隐藏所有表单
                    $show_email_form = false;
                    $show_verification_form = false;
                    $show_password_form = false;
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
                
                // 关闭语句
                mysqli_stmt_close($stmt);
            }
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
    <title>Forgot Password - HairCut Network</title>
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
        
        .reset-container {
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
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #f7a4b9;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fff;
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
        
        .error-alert {
            color: #ff3860;
            font-size: 16px;
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #fff8f8;
            border-radius: 4px;
            border: 1px solid #ffccd5;
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
            display: flex;
            justify-content: center;
            align-items: center;
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
            padding: 12px 15px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            white-space: nowrap;
            min-width: 100px;
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
            margin-bottom: 15px;
            text-align: center;
        }
        
        .code-success {
            color: #23d160;
        }
        
        .code-error {
            color: #ff3860;
        }
        
        .step-title {
            color: #e75480;
            font-size: 18px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .next-icon {
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Forgot Password</h2>
        
        <?php if (!empty($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="error-alert"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($show_email_form): ?>
        <div class="step-title">Step 1: Enter your email address</div>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="emailForm">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="email" value="<?php echo $email; ?>" placeholder="Enter email">
                <?php if (!empty($email_err)): ?>
                <div class="error-message"><?php echo $email_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>OTP Code</label>
                <div class="verification-group">
                    <input type="text" name="verification_code" id="verification_code" maxlength="6" placeholder="Enter OTP code">
                    <button type="button" class="send-code-btn" id="sendCodeBtn">Send OTP</button>
                </div>
                <div id="codeMessage" class="code-message"></div>
                <?php if (!empty($verification_code_err)): ?>
                <div class="error-message"><?php echo $verification_code_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="submit-btn" name="verify_email">
                    Next
                    <span class="next-icon">→</span>
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if ($show_verification_form): ?>
        <div class="step-title">Step 2: Enter verification code</div>
        <p>A verification code has been sent to <?php echo $_SESSION["reset_email"]; ?></p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Verification Code</label>
                <input type="text" name="verification_code" maxlength="6" value="<?php echo $verification_code; ?>" placeholder="Enter verification code">
                <?php if (!empty($verification_code_err)): ?>
                <div class="error-message"><?php echo $verification_code_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="submit-btn" name="verify_code">
                    Verify
                    <span class="next-icon">→</span>
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if ($show_password_form): ?>
        <div class="step-title">Step 3: Create new password</div>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password">
                <?php if (!empty($new_password_err)): ?>
                <div class="error-message"><?php echo $new_password_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password">
                <?php if (!empty($confirm_password_err)): ?>
                <div class="error-message"><?php echo $confirm_password_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="submit-btn" name="reset_password">
                    Reset Password
                    <span class="next-icon">→</span>
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <div class="login-link">
            Remember your password? <a href="login.php">Login here</a>
        </div>
    </div>
    
    <a href="index.php" class="home-link">Back to Home</a>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sendCodeBtn = document.getElementById('sendCodeBtn');
            if (sendCodeBtn) {
                const emailInput = document.getElementById('email');
                const codeMessage = document.getElementById('codeMessage');
                let cooldown = 0;
                let cooldownInterval;
                
                // 发送验证码
                sendCodeBtn.addEventListener('click', function() {
                    const email = emailInput.value.trim();
                    
                    if (!email) {
                        codeMessage.textContent = 'Please enter your email address';
                        codeMessage.className = 'code-message code-error';
                        return;
                    }
                    
                    if (!isValidEmail(email)) {
                        codeMessage.textContent = 'Please enter a valid email address';
                        codeMessage.className = 'code-message code-error';
                        return;
                    }
                    
                    // 显示发送中状态
                    codeMessage.textContent = 'Sending verification code... This may take up to 60 seconds';
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
                            sendCodeBtn.textContent = 'Send OTP';
                        }
                    }, 1000);
                    
                    // 发送AJAX请求
                    const formData = new FormData();
                    formData.append('action', 'send_code');
                    formData.append('email', email);
                    
                    // 设置请求超时
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 60000); // 60秒超时
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
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
                            codeMessage.textContent = 'Request timed out. The verification code may still be sent, please check your email.';
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
            }
        });
    </script>
</body>
</html> 