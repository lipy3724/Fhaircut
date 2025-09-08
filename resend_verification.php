<?php
// 包含数据库配置文件和邮件函数
require_once "db_config.php";
require_once "email_functions.php";

// 定义变量
$email = "";
$email_err = "";
$success_message = "";
$error_message = "";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 验证邮箱
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // 检查输入错误
    if (empty($email_err)) {
        // 查询用户
        $sql = "SELECT username, status FROM users WHERE email = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $username, $status);
                    mysqli_stmt_fetch($stmt);
                    
                    if ($status == "Active") {
                        $error_message = "This account is already verified. You can <a href='login.php'>login</a> now.";
                    } else {
                        // 生成新的验证码
                        $verification_code = generateVerificationCode();
                        
                        // 保存验证码到数据库
                        if (saveVerificationCode($email, $verification_code, $conn)) {
                            // 发送验证邮件
                            if (sendVerificationEmail($email, $username, $verification_code)) {
                                $success_message = "Verification code has been sent to your email.";
                            } else {
                                $error_message = "Failed to send verification email. Please try again later.";
                            }
                        } else {
                            $error_message = "Something went wrong. Please try again later.";
                        }
                    }
                } else {
                    $error_message = "No account found with that email address.";
                }
            } else {
                $error_message = "Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // 关闭数据库连接
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - HairCut Network</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #F8F7FF;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            flex-direction: column;
        }
        
        .resend-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 350px;
            max-width: 100%;
        }
        
        h2 {
            color: #9E9BC7;
            text-align: center;
            margin-bottom: 30px;
        }
        
        p {
            color: #4A4A4A;
            text-align: center;
            margin-bottom: 20px;
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
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .error-message {
            color: #ff3860;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .error-alert {
            color: #ff3860;
            font-size: 16px;
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #fff8f8;
            border-radius: 4px;
        }
        
        .error-alert a {
            color: #9E9BC7;
            text-decoration: none;
        }
        
        .error-alert a:hover {
            text-decoration: underline;
        }
        
        .success-message {
            color: #23d160;
            font-size: 16px;
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #f8fff8;
            border-radius: 4px;
        }
        
        .submit-btn {
            background-color: #B8B5E1;
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
            background-color: #9E9BC7;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #4A4A4A;
        }
        
        .login-link a {
            color: #9E9BC7;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .home-link {
            color: #9E9BC7;
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
    <div class="resend-container">
        <h2>Resend Verification</h2>
        
        <p>Enter your email address to receive a new verification code.</p>
        
        <?php if (!empty($error_message)): ?>
        <div class="error-alert"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo $email; ?>">
                <?php if (!empty($email_err)): ?>
                <div class="error-message"><?php echo $email_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="submit" class="submit-btn" value="Send Verification Code">
            </div>
            
            <div class="login-link">
                Already verified? <a href="login.php">Login here</a>
            </div>
        </form>
    </div>
    
    <a href="index.php" class="home-link">Back to Home</a>
</body>
</html> 