<?php
// 设置字符编码
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// 显示错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>简化登录测试</h1>";

// 步骤1: 测试基本PHP功能
echo "<h2>步骤1: 基本PHP功能</h2>";
echo "<p>✓ PHP版本: " . phpversion() . "</p>";

// 步骤2: 测试会话功能
echo "<h2>步骤2: 会话功能测试</h2>";
try {
    session_start();
    echo "<p>✓ session_start() 成功</p>";
    
    $_SESSION['test'] = 'test_value';
    echo "<p>✓ 会话变量设置成功</p>";
    
    echo "<p>✓ 会话ID: " . session_id() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 会话错误: " . $e->getMessage() . "</p>";
}

// 步骤3: 测试数据库配置加载
echo "<h2>步骤3: 数据库配置测试</h2>";
try {
    if (file_exists('db_config.php')) {
        echo "<p>✓ db_config.php 文件存在</p>";
        
        // 包含数据库配置
        require_once 'db_config.php';
        echo "<p>✓ db_config.php 加载成功</p>";
        
        if (isset($conn)) {
            echo "<p>✓ 数据库连接变量存在</p>";
            
            // 测试数据库连接
            if (mysqli_ping($conn)) {
                echo "<p style='color: green;'>✓ 数据库连接正常</p>";
            } else {
                echo "<p style='color: red;'>✗ 数据库连接失败</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ 数据库连接变量不存在</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ db_config.php 文件不存在</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 数据库配置错误: " . $e->getMessage() . "</p>";
}

// 步骤4: 测试POST数据处理
echo "<h2>步骤4: POST数据处理测试</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<p>✓ 收到POST请求</p>";
    
    if (isset($_POST['username'])) {
        echo "<p>✓ 用户名参数存在: " . htmlspecialchars($_POST['username']) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠ 用户名参数不存在</p>";
    }
    
    if (isset($_POST['password'])) {
        echo "<p>✓ 密码参数存在</p>";
    } else {
        echo "<p style='color: orange;'>⚠ 密码参数不存在</p>";
    }
} else {
    echo "<p>当前是GET请求，显示登录表单</p>";
    
    // 显示简单的登录表单
    echo '<form method="POST" action="">';
    echo '<p>用户名: <input type="text" name="username" value="test_user"></p>';
    echo '<p>密码: <input type="password" name="password" value="test_pass"></p>';
    echo '<p><input type="submit" value="测试登录"></p>';
    echo '</form>';
}

// 步骤5: 测试基本函数
echo "<h2>步骤5: 基本函数测试</h2>";
try {
    // 测试字符串函数
    $test_str = "test";
    $result = strlen($test_str);
    echo "<p>✓ strlen() 函数正常: " . $result . "</p>";
    
    // 测试数组函数
    $test_array = ['a', 'b', 'c'];
    $count = count($test_array);
    echo "<p>✓ count() 函数正常: " . $count . "</p>";
    
    // 测试时间函数
    $time = time();
    echo "<p>✓ time() 函数正常: " . $time . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 基本函数错误: " . $e->getMessage() . "</p>";
}

echo "<h2>测试完成</h2>";
echo "<p>如果所有步骤都显示绿色✓，说明基本功能正常。</p>";
echo "<p>如果出现红色✗，请告诉我具体是哪一步出错。</p>";
?>
