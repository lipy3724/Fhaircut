<?php
// 初始化会话
session_start();

// 包含数据库配置文件和邮件函数
require_once "db_config.php";
require_once "email_functions.php";

// 定义变量
$email = $code = "";
$email_err = $code_err = $verification_err = "";
$success_message = "";

// 如果是通过GET请求访问（从邮件链接点击进来）
if (isset($_GET["email"]) && !empty($_GET["email"])) {
    $email = $_GET["email"];
}

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 验证邮箱
    if (empty(trim($_POST["email"]))) {
        $email_err = "请输入邮箱地址";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // 验证验证码
    if (empty(trim($_POST["code"]))) {
        $code_err = "请输入验证码";
    } else {
        $code = trim($_POST["code"]);
    }
    
    // 检查输入错误
    if (empty($email_err) && empty($code_err)) {
        // 验证验证码
        if (verifyEmailCode($email, $code, $conn)) {
            // 验证成功，更新用户状态为激活
            $sql = "UPDATE users SET status = 'Active' WHERE email = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "邮箱验证成功！您现在可以 <a href='login.php'>登录</a> 了。";
                } else {
                    $verification_err = "验证过程中出现错误，请稍后再试";
                }
                
                mysqli_stmt_close($stmt);
            }
        } else {
            $verification_err = "验证码无效或已过期";
        }
    }
    
    // 关闭数据库连接
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>验证邮箱 - Jianfa Website</title>
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
        
        .verify-container {
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
        
        .verification-error {
            color: #ff3860;
            font-size: 16px;
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #fff8f8;
            border-radius: 4px;
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
    <div class="verify-container">
        <h2>验证邮箱</h2>
        
        <?php if (!empty($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($verification_err)): ?>
        <div class="verification-error"><?php echo $verification_err; ?></div>
        <?php endif; ?>
        
        <?php if (empty($success_message)): ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>邮箱地址</label>
                <input type="email" name="email" value="<?php echo $email; ?>">
                <?php if (!empty($email_err)): ?>
                <div class="error-message"><?php echo $email_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>验证码</label>
                <input type="text" name="code" maxlength="6">
                <?php if (!empty($code_err)): ?>
                <div class="error-message"><?php echo $code_err; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <input type="submit" class="submit-btn" value="验证">
            </div>
            
            <div class="login-link">
                已有账户？ <a href="login.php">登录</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <a href="index.php" class="home-link">返回首页</a>
</body>
</html> 