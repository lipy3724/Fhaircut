<?php
// 初始化会话
session_start();

// 清除前台会话变量
unset($_SESSION["loggedin"]);
unset($_SESSION["id"]);
unset($_SESSION["username"]);
unset($_SESSION["role"]);

// 重定向到main.php页面
header("location: main.php");
exit;
?> 