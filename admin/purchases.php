<?php
// 重定向到集成的订单管理页面
session_start();
header("Location: ../admin.php?page=purchases");
exit;
?> 