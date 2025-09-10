<?php
session_start();
require_once __DIR__ . '/db_config.php';

// 读取头发ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: main.php');
    exit;
}

$hairId = intval($_GET['id']);
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// 记录用户登录状态
error_log("Hair detail page - User login status: " . ($isLoggedIn ? "Logged in" : "Not logged in"));
if ($isLoggedIn) {
    error_log("Logged in user - ID: " . $_SESSION['id'] . ", Username: " . $_SESSION['username'] . ", Email: " . ($_SESSION['email'] ?? 'Not set'));
}

// 用户余额和购物车信息将由header.php提供

// 获取头发信息
$hair = null;
$sql = "SELECT * FROM hair WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, 'i', $hairId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $hair = $row;
    }
    mysqli_stmt_close($stmt);
}

if (!$hair) {
    header('Location: main.php');
    exit;
}

// 更新点击次数 (为头发表添加clicks字段)
$update_clicks_sql = "ALTER TABLE hair ADD COLUMN IF NOT EXISTS clicks INT DEFAULT 0 COMMENT '点击次数'";
mysqli_query($conn, $update_clicks_sql);

if ($stmt = mysqli_prepare($conn, 'UPDATE hair SET clicks = clicks + 1 WHERE id = ?')) {
    mysqli_stmt_bind_param($stmt, 'i', $hairId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$hasValue = isset($hair['value']) && is_numeric($hair['value']) && floatval($hair['value']) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $hair['id']; ?>. <?php echo htmlspecialchars($hair['title']); ?> - Hair Detail</title>
  <!-- PayPal SDK -->
  <?php require_once __DIR__ . '/env.php'; ?>
  <script src="https://www.paypal.com/sdk/js?client-id=<?php echo env('PAYPAL_CLIENT_ID'); ?>&currency=USD&components=buttons,googlepay,applepay&enable-funding=venmo,paylater,card"></script>
  <!-- Google Pay SDK -->
  <script async src="https://pay.google.com/gp/p/js/pay.js"></script>
  <!-- Apple Pay SDK -->
  <script src="https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js"></script>
  <script>
    // 安全加载器：等待 pay.js 与回调函数均可用再触发
    (function(){
      var attempts = 0;
      function tryInitGPay(){
        attempts++;
        var ready = window.google && google.payments && google.payments.api && typeof onGooglePayLoaded === 'function';
        if (ready) {
          try { onGooglePayLoaded(); } catch(e) { console.error('onGooglePayLoaded threw', e); }
          return;
        }
        if (attempts < 100) { // 最长 ~10s
          setTimeout(tryInitGPay, 100);
        } else {
          console.warn('Google Pay not initialized: SDK or handler not ready');
        }
      }
      // DOM 就绪后开始轮询
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInitGPay);
      } else {
        tryInitGPay();
      }
    })();
  </script>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background-color: #F8F7FF; color: #333; }
    header { 
        background: #ffccd5; 
        color: #333; 
        padding: 14px 16px; 
        display: grid; 
        grid-template-columns: 1fr auto 1fr; 
        align-items: center; 
        box-shadow: 0 2px 10px rgba(231, 84, 128, 0.2);
    }
    .membership { text-align: center; font-size: 18px; }
    .container { display: flex; min-height: calc(100vh - 60px); position: relative; }
    
    .sidebar {
        width: 200px;
        background-color: #fff5f7;
        padding: 20px;
        box-shadow: 2px 0 5px rgba(231, 84, 128, 0.1);
    }
    
    .sidebar h3 {
        margin-bottom: 15px;
        margin-top: 25px;
        color: #e75480;
        font-size: 18px;
        border-bottom: 2px solid #ffb6c1;
        padding-bottom: 8px;
    }
    
    
    .sidebar h3:first-child {
        margin-top: 0;
    }
    
    .category-list {
        list-style: none;
        margin-bottom: 25px;
    }
    
    .category-list li {
        margin-bottom: 10px;
    }
    
    .category-list li a {
        color: #333;
        text-decoration: none;
        display: block;
        padding: 8px 10px;
        border-radius: 4px;
        transition: all 0.3s;
    }
    
    .category-list li a:hover, .category-list li a.active {
        background-color: #ffe1e6;
        color: #e75480;
    }
    
    .search-box {
        margin-bottom: 25px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        width: 80%;
    }
    
    .search-box input {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #f7a4b9;
        border-radius: 4px;
        transition: all 0.3s;
        margin-bottom: 8px;
        font-size: 14px;
    }
    
    .search-box input:focus {
        outline: none;
        border-color: #e75480;
        box-shadow: 0 0 0 2px rgba(231, 84, 128, 0.2);
    }
    
    .search-box button {
        padding: 6px 10px;
        background-color: #e75480;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
        width: 100%;
        font-size: 14px;
    }
    
    .search-box button:hover {
        background-color: #d64072;
    }
    
    .main-detail-content {
        flex: 1;
        padding: 20px;
        display: flex;
        justify-content: center;
    }
    
    .detail-container {
        width: 80%;
    }

    .title { font-size: 22px; font-weight: 700; padding: 10px 14px; background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1); }
    .title small { font-weight: 500; color: #777; margin-left: 8px; }

    .info-bar { margin-top: 12px; background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1); padding: 14px; display: grid; grid-template-columns: 160px 1fr auto; gap: 14px; align-items: center; }
    .thumb { width: 160px; height: 110px; overflow: hidden; border-radius: 6px; background: #fafafa; display: flex; align-items: center; justify-content: center; }
    .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .cart-actions { display: flex; flex-direction: column; gap: 8px; min-width: 120px; }

    .meta { display: grid; grid-template-columns: repeat(3, minmax(120px, 1fr)); gap: 10px; }
    .meta-item { font-size: 13px; color: #555; }
    .meta-item b { color: #222; }

    .purchase { background: #fff; border-radius: 6px; padding: 12px; box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1); margin-top: 12px; width: 50%; float: left; margin-right: 20px; margin-bottom: 20px; }
    .purchase h3 { margin: 0 0 10px 0; font-size: 16px; }
    .purchase .options { display: grid; grid-template-columns: 1fr; gap: 10px; }
    .card { border: 1px solid #f7a4b9; border-radius: 6px; padding: 10px; }
    .card h4 { margin: 0 0 6px 0; font-size: 15px; }
    .price { color: #e75480; font-weight: 700; }
    .btn { 
        display: inline-block; 
        padding: 8px 12px; 
        border-radius: 4px; 
        border: 1px solid #f7a4b9; 
        background: #ffccd5; 
        color: #333; 
        text-decoration: none; 
        font-size: 14px; 
    }
    .btn:hover { background: #f7a4b9; }
    
    .balance-pay-btn-hair {
        background-color: #e75480;
        color: white;
        border: 1px solid #e75480;
        margin-bottom: 10px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 14px;
    }
    .balance-pay-btn-hair:hover {
        background-color: #d64072;
        border-color: #d64072;
    }
    
    .btn-primary {
        background-color: #e75480;
        color: white;
        border: 1px solid #e75480;
    }
    .btn-primary:hover {
        background-color: #d64072;
        border-color: #d64072;
    }
    
    
    /* 支付按钮样式 */
    .googlepay-button-container {
      margin-top: 10px;
    }
    
    .applepay-button-container {
      margin-top: 10px;
      max-width: 250px;
    }
    
    .google-pay-button {
      background-color: #000;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 16px;
      font-weight: 500;
      padding: 0 15px;
      width: 100%;
      height: 48px;
    }
    
    .apple-pay-button {
      background-color: #000;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 16px;
      font-weight: 500;
      padding: 0 15px;
      width: 100%;
      height: 48px;
    }
    
    /* 修改PayPal的Apple Pay按钮样式 */
    .paypal-applepay-button {
      width: 100% !important;
      height: 48px !important;
      min-height: 48px !important;
      max-height: 48px !important;
      border-radius: 4px !important;
    }
    
    .apple-pay-icon {
      height: 24px;
      margin-left: 8px;
    }
    
    .apple-pay-text {
      margin-right: 5px;
    }
    
    .apple-pay-logo {
      display: inline-block;
      width: 45px;
      height: 18px;
      background-image: url("data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDMiIGhlaWdodD0iMTgiIHZpZXdCb3g9IjAgMCA0MyAxOCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYuMjUzOTEgMy4xMDM1MkM1Ljg0MjE5IDMuNjAzNTIgNS4xODM1OSA0LjAwMzUyIDQuNTI1IDQuMDAzNTJDNC40NTMxMyA0LjAwMzUyIDQuMzgxMjUgNC4wMDM1MiA0LjMwOTM4IDMuOTk0MTRDNC4yODUxNiAzLjk0NzI3IDQuMjEzMjggMy42MDM1MiA0LjIxMzI4IDMuNTk0MTRDMy45NzQ2MSAyLjY0MTAyIDQuNjgxNjQgMS42NTk3NyA1LjE1OTM4IDEuMTY5MTRDNS41NzEwOSAwLjY1OTc2NiA2LjI1MzkxIDAuMjUgNi45NjA5NCAwLjI1QzcuMDIzNDQgMC4yNSA3LjA5NTMxIDAuMjUgNy4xNTc4MSAwLjI1OTM3NUM3LjE3MzQ0IDAuMzI1MTk1IDcuMTgyMDMgMC4zODQ3NjYgNy4xOTA2MyAwLjQ0NDMzNkM3LjI2MjUgMS4zNSA2Ljc1NTg2IDIuNTEyNyA2LjI1MzkxIDMuMTAzNTJaTTEzLjMxMDUgMTMuNDY1OEMxMy4wNzE5IDEzLjcyNSAxMi43MzA1IDEzLjk5NDEgMTIuMjgzNiAxMy45OTQxQzExLjY5MTQgMTMuOTk0MSAxMS4zNjg2IDEzLjU3ODEgMTAuOTM4NSAxMy41NzgxQzEwLjQ4OTggMTMuNTc4MSAxMC4xMTk1IDEzLjk5NDEgOS41NzQ2MSAxMy45OTQxQzkuMDkxMDIgMTMuOTk0MSA4LjY5ODA1IDEzLjY5NzMgOC40MzA4NiAxMy4zNDQxQzcuNjQxOCAxMi4zNTM1IDcuNTQyMTkgMTAuODM3OSA4LjAwOTc3IDkuODI4MTNDOC4zMzU5NCA5LjEzMTY0IDguOTQ2NDggOC41ODc4OSA5LjYxNTYzIDguNTg3ODlDMTAuMDY0NSA4LjU4Nzg5IDEwLjQxODQgOC45MDM1MiAxMC44NjcyIDguOTAzNTJDMTEuMjk3MyA4LjkwMzUyIDExLjcwNzggOC41ODc4OSAxMi4yMTg0IDguNTg3ODlDMTIuODA0NyA4LjU4Nzg5IDEzLjMzNDggOS4wNTk3NyAxMy42NjEgOS42Njg3NUMxMy4yMzA5IDkuOTI4MTMgMTIuODY5MSAxMC4zNzUgMTIuODY5MSAxMC45NzI3QzEyLjg2OTEgMTEuODM3OSAxMy4zNTU1IDEyLjI5NDEgMTMuMzEwNSAxMy40NjU4Wk0xMi4yMDkgOC4wODc4OUMxMi4wMTc2IDguMTI1IDExLjgwNzggOC4xNTMzMiAxMS41OTggOC4xNTMzMkMxMS40NzI3IDguMTUzMzIgMTEuMTc1OCA4LjEyNSAxMS4wNjA1IDguMTI1QzExLjA1MTIgNy45MDYyNSAxMS4xMjc3IDcuNjU2MjUgMTEuMjQzIDcuNDQ2ODhDMTEuMzg2NyA3LjE5Njg4IDExLjYyNSA2Ljk5NDEgMTEuOTIxOSA2Ljg3NUMxMi4wMzczIDYuODI4MTMgMTIuMTcxOSA2LjgwMDc4IDEyLjMwNjYgNi44MDA3OEMxMi4zMzk4IDYuODAwNzggMTIuMzczIDYuODAwNzggMTIuNDA2MyA2LjgwMDc4QzEyLjQyNTggNy4wMzEyNSAxMi4zOTI2IDcuMjY5NTMgMTIuMzExNyA3LjQ5NjA5QzEyLjI4NTIgNy42MDkzOCAxMi4yNTU5IDcuNzIyNjYgMTIuMjA5IDcuODM1OTRWOC4wODc4OVpNMjEuNDUxMiAxNS4wMDM5QzIxLjA5MzggMTYuMzEyNSAyMC40MzUyIDE3LjUgMTkuNDY4OCAxNy41QzE5LjA0NjkgMTcuNSAxOC42NTM5IDE3LjMxMjUgMTguMzgzNiAxNi45ODQ0QzE4LjExNjUgMTYuNjU2MyAxNy45NTMxIDE2LjIyMjcgMTcuOTUzMSAxNS42OTUzQzE3Ljk1MzEgMTQuNzA0NyAxOC41MjczIDEzLjMxMjUgMTkuMTMzNiAxMi41QzE5LjgzMTEgMTEuNTM5MSAyMC41Mzg5IDExLjAwMzkgMjEuNDUxMiAxMS4wMDM5QzIxLjU3NTggMTEuMDAzOSAyMS44NTM1IDExLjAyMjcgMjIuMDM2MSAxMS4wNzAzTDIyLjA4MjQgMTEuMDc5N0MyMi4wODI0IDExLjA3OTcgMjIuMDczOCAxMS4wODkxIDIyLjA2NTIgMTEuMTA3NEMyMS45NTggMTEuNTQyMiAyMS4zMDUzIDEzLjk4NDQgMjEuMzA1MyAxMy45ODQ0TDIxLjMwMTUgMTMuOTkzOEMyMS4wNTM1IDE0LjgxMjUgMjAuNzk2MSAxNS40NjA5IDIwLjc5NjEgMTUuODc1QzIwLjc5NjEgMTYuMjgxMyAyMC45NTk1IDE2LjUgMjEuMjI2NiAxNi41QzIxLjY0ODQgMTYuNSAyMi4wNzAzIDE1Ljk3MjcgMjIuMzM3NCAxNS4xNDQ1QzIyLjQxOTkgMTQuODkwNiAyMi40NzY2IDE0LjY0MDYgMjIuNjEyMyAxNC4wODU5TDIzLjQ5MTIgMTAuNUgyNC41TDIzLjE2MDIgMTUuNzVDMjMuMDU0NyAxNi4yNSAyMi45NTggMTYuNzUgMjIuOTU4IDE3LjEyNUMyMi45NTggMTcuMzc1IDIzLjAyNjYgMTcuNSAyMy4yMDMxIDE3LjVDMjMuMzc1IDE3LjUgMjMuNTM4NCAxNy40MDYzIDIzLjY1MjMgMTcuMjgxM0MyMy43NjU2IDE3LjE1NjMgMjMuODYzMyAxNy4wMzEzIDIzLjk2ODggMTYuODc1TDI0LjI1IDE3LjEyNUMyMy45Njg4IDE3LjUgMjMuNDU3IDE3Ljk5MDIgMjIuNzUgMTcuOTkwMkMyMi4yNDggMTcuOTkwMiAyMS45Mzk1IDE3LjY5NTMgMjEuODI0MiAxNy4xNjhDMjEuNzk4OCAxNy4wNDMgMjEuNzg5MSAxNi45MDgyIDIxLjc4OTEgMTYuNzY1NkMyMS43ODkxIDE2LjQ0NTMgMjEuODMzMyAxNi4wODk4IDIxLjkxMDIgMTUuNzVMMjIuMDM1MiAxNS4wMDM5SDIxLjQ1MTJaTTMxLjI5MyAxNy4yNUMzMS4wNDQ5IDE3LjUgMzAuNjIzIDE3Ljc1IDMwLjA2NjQgMTcuNzVDMjkuNDgwNSAxNy43NSAyOS4xMDU1IDE3LjM3NSAyOS4xMDU1IDE2LjYyNUMyOS4xMDU1IDE2LjUgMjkuMTI1IDE2LjM3NSAyOS4xNTYyIDE2LjI1QzI5LjI0MjIgMTUuODc1IDI5LjM4MjggMTUuNDM3NSAyOS41MTc2IDE1LjEyNUMyOS42NTI0IDE0LjgxMjUgMjkuODI0MiAxNC40Mzc1IDMwLjAxNTYgMTQuMDYyNUwzMC4wNDY5IDE0QzI5LjkwMjMgMTMuODc1IDI5Ljc2NzYgMTMuNjg3NSAyOS42NzE5IDEzLjVDMjkuNTc2MiAxMy4zMTI1IDI5LjUyNzMgMTMuMTI1IDI5LjUyNzMgMTIuOTM3NUMyOS41MjczIDEyLjg3NSAyOS41MzY3IDEyLjgxMjUgMjkuNTQ2MSAxMi43NUMyOS42MTMzIDEyLjMxMjUgMjkuOTExNyAxMS44NzUgMzAuMzUxNiAxMS41NjI1QzMwLjc5MSAxMS4yNSAzMS4yNTc4IDExLjA2MjUgMzEuNzUgMTEuMDYyNUMzMi4wODU5IDExLjA2MjUgMzIuMzUxNiAxMS4xNTYzIDMyLjU0NjkgMTEuMzQzOEMzMi43NDIyIDExLjUzMTMgMzIuODM3OSAxMS43ODEzIDMyLjgzNzkgMTIuMDkzOEMzMi44Mzc5IDEyLjQwNjMgMzIuNzQyMiAxMi43MTg4IDMyLjU0NjkgMTMuMDMxM0MzMi4zNTE2IDEzLjM0MzggMzIuMDk1MyAxMy42MjUgMzEuNzc3MyAxMy44NzVMMzIuNzYxNyAxNS4yNUMzMi45NTcgMTQuODc1IDMzLjEyNSAxNC40MDYzIDMzLjI2OTUgMTMuODc1SDM0LjI1QzM0LjEwNTUgMTQuNTMxMyAzMy44NTk0IDE1LjEyNSAzMy41MTU2IDE1LjY1NjNMMzQuNjI4OSAxNy4yNUgzMy4zNzExTDMyLjc1MiAxNi4zNzVDMzIuMzQzOCAxNi45MDYzIDMxLjgzNTkgMTcuMjUgMzEuMjkzIDE3LjI1Wk0zMC4xMzI4IDEyLjg3NUMzMC4xMzI4IDEzLjA2MjUgMzAuMjI4NSAxMy4yNSAzMC40MjM4IDEzLjQzNzVDMzAuNzUgMTMuMjUgMzEuMDA1OSAxMy4wMzEzIDMxLjE5MTQgMTIuNzgxM0MzMS4zNzY5IDEyLjUzMTMgMzEuNDcyNyAxMi4yODEzIDMxLjQ3MjcgMTIuMDYyNUMzMS40NzI3IDExLjkzNzUgMzEuNDM1NSAxMS44NDM4IDMxLjM2NzIgMTEuNzgxM0MzMS4yOTg4IDExLjcxODggMzEuMjExOSAxMS42ODc1IDMxLjA5NzcgMTEuNjg3NUMzMC44NzMgMTEuNjg3NSAzMC42Njc5IDExLjc4MTMgMzAuNDgyNCAxMS45Njg4QzMwLjI5NjkgMTIuMTU2MyAzMC4xODM2IDEyLjM3NSAzMC4xNDI2IDEyLjYyNUMzMC4xMzY3IDEyLjY4NzUgMzAuMTMyOCAxMi43ODEzIDMwLjEzMjggMTIuODc1Wk0zMC4zNzExIDE1Ljc1QzMwLjM3MTEgMTYuMzEyNSAzMC42MDc0IDE2LjU5MzggMzEuMDg1OSAxNi41OTM4QzMxLjM4MjggMTYuNTkzOCAzMS42NTgyIDE2LjQwNjMgMzEuOTI1OCAxNi4wMzEzTDMwLjc5MSAxNC40Mzc1QzMwLjY5NTMgMTQuNjI1IDMwLjU5OTYgMTQuODc1IDMwLjUxMzcgMTUuMTg3NUMzMC40MTggMTUuNSAzMC4zNzExIDE1LjY1NjMgMzAuMzcxMSAxNS43NVpNMzkuMTM2NyAxNy4yNUMzOC44ODg3IDE3LjUgMzguNDY2OCAxNy43NSAzNy45MTAyIDE3Ljc1QzM3LjMyNDIgMTcuNzUgMzYuOTQ5MiAxNy4zNzUgMzYuOTQ5MiAxNi42MjVDMzYuOTQ5MiAxNi41IDM2Ljk2ODggMTYuMzc1IDM3IDE2LjI1QzM3LjA4NTkgMTUuODc1IDM3LjIyNjYgMTUuNDM3NSAzNy4zNjEzIDE1LjEyNUMzNy40OTYxIDE0LjgxMjUgMzcuNjY4IDE0LjQzNzUgMzcuODU5NCAxNC4wNjI1TDM3Ljg5MDYgMTRDMzcuNzQ2MSAxMy44NzUgMzcuNjExMyAxMy42ODc1IDM3LjUxNTYgMTMuNUMzNy40MiAxMy4zMTI1IDM3LjM3MTEgMTMuMTI1IDM3LjM3MTEgMTIuOTM3NUMzNy4zNzExIDEyLjg3NSAzNy4zODA5IDEyLjgxMjUgMzcuMzkwNiAxMi43NUMzNy40NTcgMTIuMzEyNSAzNy43NTU5IDExLjg3NSAzOC4xOTUzIDExLjU2MjVDMzguNjM0OCAxMS4yNSAzOS4xMDE2IDExLjA2MjUgMzkuNTkzOCAxMS4wNjI1QzM5LjkyOTcgMTEuMDYyNSA0MC4xOTUzIDExLjE1NjMgNDAuMzkwNiAxMS4zNDM4QzQwLjU4NTkgMTEuNTMxMyA0MC42ODE2IDExLjc4MTMgNDAuNjgxNiAxMi4wOTM4QzQwLjY4MTYgMTIuNDA2MyA0MC41ODU5IDEyLjcxODggNDAuMzkwNiAxMy4wMzEzQzQwLjE5NTMgMTMuMzQzOCAzOS45Mzk1IDEzLjYyNSAzOS42MjExIDEzLjg3NUw0MC42MDU1IDE1LjI1QzQwLjgwMDggMTQuODc1IDQwLjk2ODggMTQuNDA2MyA0MS4xMTMzIDEzLjg3NUg0Mi4wOTM4QzQxLjk0OTIgMTQuNTMxMyA0MS43MDMxIDE1LjEyNSA0MS4zNTk0IDE1LjY1NjNMNDIuNDcyNyAxNy4yNUg0MS4yMTQ4TDQwLjU5NTcgMTYuMzc1QzQwLjE4NzUgMTYuOTA2MyAzOS42Nzk3IDE3LjI1IDM5LjEzNjcgMTcuMjVaTTM3Ljk3NjYgMTIuODc1QzM3Ljk3NjYgMTMuMDYyNSAzOC4wNzIzIDEzLjI1IDM4LjI2NzYgMTMuNDM3NUMzOC41OTM4IDEzLjI1IDM4Ljg0OTYgMTMuMDMxMyAzOS4wMzUyIDEyLjc4MTNDMzkuMjIwNyAxMi41MzEzIDM5LjMxNjQgMTIuMjgxMyAzOS4zMTY0IDEyLjA2MjVDMzkuMzE2NCAxMS45Mzc1IDM5LjI3OTMgMTEuODQzOCAzOS4yMTA5IDExLjc4MTNDMzkuMTQyNiAxMS43MTg4IDM5LjA1NTcgMTEuNjg3NSAzOC45NDE0IDExLjY4NzVDMzguNzE2OCAxMS42ODc1IDM4LjUxMTcgMTEuNzgxMyAzOC4zMjYyIDExLjk2ODhDMzguMTQwNiAxMi4xNTYzIDM4LjAyNzMgMTIuMzc1IDM3Ljk4NjMgMTIuNjI1QzM3Ljk4MDUgMTIuNjg3NSAzNy45NzY2IDEyLjc4MTMgMzcuOTc2NiAxMi44NzVaTTM4LjIxNDggMTUuNzVDMzguMjE0OCAxNi4zMTI1IDM4LjQ1MTIgMTYuNTkzOCAzOC45Mjk3IDE2LjU5MzhDMzkuMjI2NiAxNi41OTM4IDM5LjUwMiAxNi40MDYzIDM5Ljc2OTUgMTYuMDMxM0wzOC42MzQ4IDE0LjQzNzVDMzguNTM5MSAxNC42MjUgMzguNDQzNCAxNC44NzUgMzguMzU3NCAxNS4xODc1QzM4LjI2MTcgMTUuNSAzOC4yMTQ4IDE1LjY1NjMgMzguMjE0OCAxNS43NVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=");
      background-repeat: no-repeat;
      background-position: center;
      background-size: contain;
    }
    
    /* 加载动画样式 */
    .loading-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(255, 255, 255, 0.8);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      border-radius: 6px;
    }

    .loading-overlay .spinner {
      border: 4px solid #f3f3f3;
      border-top: 4px solid #3498db;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin-bottom: 10px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .gallery { margin-top: 16px; background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 14px; width: 100%; clear: both; }
    .gallery h3 { margin: 0 0 12px 0; font-size: 16px; }
    .big-image { width: 100%; max-width: 980px; margin: 0 auto 16px; display: block; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
    
    /* 清除浮动 */
    .detail-container::after {
        content: "";
        display: table;
        clear: both;
    }

    @media (max-width: 992px) {
      .info-bar { grid-template-columns: 1fr; }
      .thumb { width: 100%; height: 180px; }
      .purchase { width: 100%; float: none; }
      .purchase .options { grid-template-columns: 1fr; }
      .gallery { clear: none; }
      .cart-actions { flex-direction: row; justify-content: center; min-width: auto; }
    }
    
    @media (max-width: 768px) {
      .container {
        flex-direction: column;
      }
      
      .sidebar {
        width: 100%;
        margin-bottom: 20px;
      }
      
      .main-detail-content {
        width: 100%;
        padding: 15px;
      }
    }
    
  </style>
</head>
<body>
  <?php require_once "header.php"; ?>

  <div class="container">
    <div class="sidebar">
      <h3>Navigation:</h3>
      <ul class="category-list">
        <li><a href="home.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'home.php') ? 'active' : ''; ?>">Homepage</a></li>
        <li><a href="main.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'main.php') ? 'active' : ''; ?>">All works</a></li>
        <li><a href="hair_list.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'hair_list.php') ? 'active' : ''; ?>">Hair List</a></li>
        <li><a href="taday_42_off.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'taday_42_off.php') ? 'active' : ''; ?>">Today 42.0% off</a></li>
      </ul>
      
      <h3>Product Categories:</h3>
      <ul class="category-list">
        <?php 
        // 获取所有类别
        $categories = [];
        $sql = "SELECT id, name FROM categories ORDER BY id ASC";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[] = $row;
            }
            mysqli_free_result($result);
        }
        
        foreach ($categories as $category): 
            // 跳过"Taday 42.0% off"分类，因为已经有"Today 42.0% off"
            if ($category['name'] === 'Taday 42.0% off') continue;
        ?>
        <li>
            <a href="main.php?category=<?php echo $category['id']; ?>">
                <?php echo htmlspecialchars($category['name']); ?>
            </a>
        </li>
        <?php endforeach; ?>
      </ul>
      
      <?php if (!$isLoggedIn): ?>
      <div class="search-box">
        <input type="text" placeholder="Keyword" disabled>
        <button onclick="alert('Please login to use search function')">Search</button>
        <div class="help-text" style="color: #666; font-size: 12px; margin-top: 5px;">Please login to use search</div>
      </div>
      <?php else: ?>
      <div class="search-box">
        <form action="search.php" method="get">
            <input type="text" name="keyword" placeholder="Keyword" required>
            <button type="submit">Search</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    
    <div class="main-detail-content">
      <div class="detail-container">
        <div class="title">
          <a href="hair_list.php" style="display: inline-block; padding: 5px 10px; color: #4A4A4A; text-decoration: none; margin-right: 10px; font-size: 14px;">
            <span style="display: inline-flex; align-items: center;">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M15 18l-6-6 6-6"/></svg>
              Back to List
            </span>
          </a>
          <?php echo $hair['id']; ?>. <?php echo htmlspecialchars($hair['title']); ?>
          <?php if (!empty($hair['description'])): ?>
            <small><?php echo htmlspecialchars($hair['description']); ?></small>
          <?php endif; ?>
        </div>

        <div class="info-bar">
          <div class="thumb">
            <?php if (!empty($hair['image'])): ?>
              <img src="<?php echo htmlspecialchars($hair['image']); ?>" alt="hair image" />
            <?php else: ?>
              <span>No image</span>
            <?php endif; ?>
          </div>

          <div class="meta">
            <div class="meta-item">Length: <b><?php echo number_format($hair['length'], 2); ?> cm</b></div>
            <div class="meta-item">Weight: <b><?php echo number_format($hair['weight'], 2); ?> g</b></div>
            <div class="meta-item">Clicks: <b><?php echo intval($hair['clicks'] ?? 0); ?></b></div>
          </div>
          
          <!-- 购物车按钮区域 -->
          <div class="cart-actions">
            <?php if ($isLoggedIn && $hasValue): ?>
            <button class="btn add-to-cart-btn" data-hair-id="<?php echo $hair['id']; ?>" data-item-type="hair" style="background-color: #e91e63; color: white; border: 1px solid #e91e63; font-size: 12px; padding: 6px 10px;">
              Add to Cart
            </button>
            <?php elseif (!$isLoggedIn): ?>
            <div style="font-size: 12px; color: #666; text-align: center;">
              Login to add<br>to cart
            </div>
            <?php elseif (!$hasValue): ?>
            <div style="font-size: 12px; color: #666; text-align: center;">
              Not for<br>sale
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="purchase">
          <h3>Hair Information & Purchase</h3>
          <div class="options">
            <div class="card">
              <h4>Hair Details</h4>
              <div class="meta-item">Title: <b><?php echo htmlspecialchars($hair['title']); ?></b></div>
              <?php if (!empty($hair['description'])): ?>
              <div class="meta-item">Description: <b><?php echo htmlspecialchars($hair['description']); ?></b></div>
              <?php endif; ?>
              <div class="meta-item">Length: <b><?php echo number_format($hair['length'], 2); ?> cm</b></div>
              <div class="meta-item">Weight: <b><?php echo number_format($hair['weight'], 2); ?> g</b></div>
              <?php if ($hasValue): ?>
              <div class="meta-item">Purchase Price: <span class="price">$<?php echo number_format($hair['value'], 2); ?></span></div>
              <div style="margin-top:8px;">
                <?php if ($isLoggedIn): ?>
                <button class="btn balance-pay-btn-hair" data-hair-id="<?php echo $hair['id']; ?>" data-price="<?php echo $hair['value']; ?>">Pay with Balance</button>
                <?php endif; ?>
                <div id="paypal-button-hair" data-hair-id="<?php echo $hair['id']; ?>" style="position: relative;"></div>
                <div id="googlepay-button-container-hair" class="googlepay-button-container" data-hair-id="<?php echo $hair['id']; ?>" style="margin-top: 10px;"></div>
                <div id="applepay-button-container-hair" class="applepay-button-container" data-hair-id="<?php echo $hair['id']; ?>" style="margin-top: 10px; max-width: 250px;"></div>
              </div>
              <?php else: ?>
              <div class="meta-item">Purchase Price: <span class="price">—</span></div>
              <div style="margin-top:8px;">
                <a class="btn" href="#" onclick="alert('This hair is for display only and not available for purchase.');return false;">Not for Sale</a>
                              </div>
                <?php endif; ?>
              </div>
          </div>
        </div>

        <!-- 头发图片展示区域 -->
        <div class="gallery">
          <h3>Hair Images</h3>
          
          <?php 
            // 显示头发的所有图片
            for ($i = 1; $i <= 5; $i++): 
              $imageField = ($i === 1) ? 'image' : 'image' . $i;
              if (!empty($hair[$imageField])): 
          ?>
                <img src="<?php echo htmlspecialchars($hair[$imageField]); ?>" alt="<?php echo htmlspecialchars($hair['title']); ?> - Image <?php echo $i; ?>" class="big-image">
          <?php 
              endif;
            endfor;
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- PayPal支付处理脚本 -->
  <script>
    // 初始化PayPal按钮 - 头发购买
    if (document.getElementById('paypal-button-hair')) {
      const hairButton = document.getElementById('paypal-button-hair');
      const hairId = hairButton.getAttribute('data-hair-id');
      
      // 添加加载覆盖层
      const hairOverlay = document.createElement('div');
      hairOverlay.className = 'loading-overlay';
      hairOverlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
      hairOverlay.style.display = 'none';
      hairButton.appendChild(hairOverlay);
      
      paypal.Buttons({
        style: {
          layout: 'vertical',
          color: 'gold',
          shape: 'rect',
          label: 'paypal'
        },
        
        // 点击按钮时
        onClick: function() {
          // 显示加载状态
          hairOverlay.style.display = 'flex';
        },
        
        // 创建订单
        createOrder: function(data, actions) {
          return fetch('/paypal_api.php?action=create_hair_order', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              hair_id: hairId
            })
          })
          .then(function(response) {
            return response.json();
          })
          .then(function(orderData) {
            if (orderData.error) {
              // 隐藏加载状态
              hairOverlay.style.display = 'none';
              console.error('Error creating order:', orderData.error);
              alert('Error creating order: ' + orderData.error);
              return;
            }
            return orderData.id;
          })
          .catch(function(error) {
            // 隐藏加载状态
            hairOverlay.style.display = 'none';
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
          });
        },
        
        // 支付完成
        onApprove: function(data, actions) {
          return fetch('/paypal_api.php?action=capture_hair_payment', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              order_id: data.orderID,
              hair_id: hairId
            })
          })
          .then(function(response) {
            return response.json();
          })
          .then(function(details) {
            // 隐藏加载状态
            hairOverlay.style.display = 'none';
            
            if (details.error) {
              console.error('Payment capture failed:', details.error);
              alert('Payment failed: ' + details.error);
              return;
            }
            
            // 支付成功
            alert('Thank you for your purchase! Transaction completed successfully.');
            
            // 刷新页面或重定向
            window.location.reload();
          })
          .catch(function(error) {
            // 隐藏加载状态
            hairOverlay.style.display = 'none';
            console.error('Error capturing payment:', error);
            alert('An error occurred during payment processing. Please try again.');
          });
        },
        
        // 支付取消
        onCancel: function(data) {
          // 隐藏加载状态
          hairOverlay.style.display = 'none';
          console.log('Payment cancelled');
        },
        
        // 支付错误
        onError: function(err) {
          // 隐藏加载状态
          hairOverlay.style.display = 'none';
          console.error('PayPal error:', err);
          alert('An error occurred with PayPal. Please try again.');
        }
      }).render('#paypal-button-hair');
    }
  </script>


  <!-- Google Pay支付处理脚本 -->
  <script>
    // Google Pay 基础配置
    const baseRequest = {
      apiVersion: 2,
      apiVersionMinor: 0
    };
    
    // 基础卡支付方法
    const baseCardPaymentMethod = {
      type: 'CARD',
      parameters: {
        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
        allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA']
      }
    };
    
    // 获取Google Pay客户端
    function getGooglePaymentsClient() {
      console.log('Creating Google Payments client for hair');
      const paymentsClient = new google.payments.api.PaymentsClient({
        environment: '<?php echo env('PAYPAL_SANDBOX', true) ? 'TEST' : 'PRODUCTION'; ?>',
        paymentDataCallbacks: {
          onPaymentAuthorized: onHairPaymentAuthorized
        }
      });
      
      return paymentsClient;
    }
    
    // 支付授权处理函数
    function onHairPaymentAuthorized(paymentData) {
      console.log('Hair payment authorized:', paymentData);
      
      return new Promise(function(resolve, reject) {
        try {
          // 处理头发支付数据
          console.log('Processing hair payment with data:', paymentData);
          
          // 模拟支付处理成功
          setTimeout(function() {
            console.log('Hair payment processing completed');
            resolve({ transactionState: 'SUCCESS' });
            
            // 显示成功消息并刷新
            alert('Payment successful! Thank you for your purchase.');
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          }, 1000);
        } catch (error) {
          console.error('Error processing hair payment:', error);
          reject({ transactionState: 'ERROR', error: error.message });
        }
      });
    }
    
    // Google Pay加载完成后的回调
    async function onGooglePayLoaded() {
      console.log('Google Pay loaded for hair detail');
      
      try {
        // 获取PayPal的Google Pay配置来检查可用性
        const googlePayConfig = await paypal.Googlepay().config();
        console.log('PayPal Google Pay config retrieved for hair');
        
        const paymentsClient = getGooglePaymentsClient();
        const isReadyToPayRequest = Object.assign({}, baseRequest);
        isReadyToPayRequest.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
        
        // 检查Google Pay是否可用
        paymentsClient.isReadyToPay(isReadyToPayRequest)
          .then(function(response) {
            console.log('Google Pay isReadyToPay response for hair:', response);
            if (response.result) {
              addGooglePayButton('googlepay-button-container-hair');
            } else {
              console.log('Google Pay is not available for hair');
            }
          })
          .catch(function(err) {
            console.error('Error checking Google Pay availability for hair:', err);
          });
      } catch (error) {
        console.error('Error initializing Google Pay for hair:', error);
      }
    }
    
    // 添加Google Pay按钮
    function addGooglePayButton(containerId) {
      const container = document.getElementById(containerId);
      if (!container) return;
      
      const hairId = container.getAttribute('data-hair-id');
      console.log('Adding Google Pay button for hair:', hairId);
      
      // 创建Google Pay按钮的覆盖层
      const overlay = document.createElement('div');
      overlay.className = 'loading-overlay';
      overlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
      overlay.style.display = 'none';
      container.parentNode.appendChild(overlay);
      
      const paymentsClient = getGooglePaymentsClient();
      const button = paymentsClient.createButton({
        onClick: function() {
          // 显示加载状态
          overlay.style.display = 'flex';
          onGooglePaymentButtonClicked(hairId, overlay);
        },
        buttonColor: 'black',
        buttonType: 'buy',
        buttonSizeMode: 'fill'
      });
      
      // 添加按钮到容器
      container.appendChild(button);
    }
    
    // Google Pay按钮点击处理
    async function onGooglePaymentButtonClicked(hairId, overlay) {
      try {
        console.log('Hair Google Pay button clicked:', hairId);
        
        // 获取Google Pay支付数据请求
        let paymentDataRequest;
        try {
          paymentDataRequest = await getHairGooglePaymentDataRequest(hairId);
          console.log('Hair payment data request:', paymentDataRequest);
        } catch (err) {
          console.error('Error getting hair payment data request:', err);
          overlay.style.display = 'none';
          alert('无法初始化Google Pay。请稍后再试或使用其他支付方式。');
          return;
        }
        
        // 加载Google Pay支付表单
        const paymentsClient = getGooglePaymentsClient();
        
        try {
          // Google Pay支付处理将在onHairPaymentAuthorized回调中完成
          await paymentsClient.loadPaymentData(paymentDataRequest);
          console.log('Hair loadPaymentData completed successfully');
        } catch (err) {
          overlay.style.display = 'none';
          console.error('Hair Google Pay loadPaymentData error:', err);
          
          if (err.statusCode === "CANCELED") {
            console.log('User canceled the hair payment');
          } else if (err.statusCode === "DEVELOPER_ERROR") {
            console.error('Developer error:', err.statusMessage);
            alert('Google Pay配置错误。请联系网站管理员。');
          } else {
            alert('Google Pay支付失败。请稍后再试。');
          }
        }
      } catch (error) {
        overlay.style.display = 'none';
        console.error('Error in Hair Google Pay flow:', error);
        alert('Google Pay支付过程中发生错误。请稍后再试。');
      }
    }
    
    /**
     * 获取头发Google Pay支付数据请求（使用PayPal集成）
     */
    async function getHairGooglePaymentDataRequest(hairId) {
      try {
        // 获取PayPal的Google Pay配置
        const googlePayConfig = await paypal.Googlepay().config();
        console.log('PayPal Google Pay config for hair:', googlePayConfig);
        
        // 获取头发价格
        const hairPrice = document.querySelector('[data-hair-id="' + hairId + '"][data-price]')?.getAttribute('data-price') || '0';
        
        // 构建支付请求对象
        const paymentDataRequest = Object.assign({}, baseRequest);
        
        // 设置允许的支付方式
        paymentDataRequest.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
        
        // 设置交易信息
        paymentDataRequest.transactionInfo = {
          totalPriceStatus: 'FINAL',
          totalPrice: parseFloat(hairPrice).toFixed(2),
          currencyCode: 'USD'
        };
        
        // 设置商家信息
        paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;
        
        // 设置回调意图
        paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
        
        console.log('Final hair payment data request:', paymentDataRequest);
        return paymentDataRequest;
      } catch (error) {
        console.error('Error getting hair Google Pay payment data request:', error);
        throw error;
      }
    }
  </script>

  <!-- Apple Pay支付处理脚本 -->
  <script>
    // Apple Pay初始化
    function initApplePay() {
      const container = document.getElementById('applepay-button-container-hair');
      if (!container) return;
      
      // 检查Apple Pay是否可用
      if (window.ApplePaySession && ApplePaySession.canMakePayments()) {
        const hairId = container.getAttribute('data-hair-id');
        
        // 创建Apple Pay按钮
        const applePayButton = document.createElement('button');
        applePayButton.className = 'apple-pay-button';
        applePayButton.innerHTML = '<span class="apple-pay-text">Buy with</span><span class="apple-pay-logo"></span>';
        
        // 添加点击事件
        applePayButton.addEventListener('click', function() {
          startApplePaySession(hairId);
        });
        
        container.appendChild(applePayButton);
      } else {
        console.log('Apple Pay is not available');
      }
    }
    
    // 启动Apple Pay会话
    function startApplePaySession(hairId) {
      const hairPrice = document.querySelector('[data-hair-id="' + hairId + '"][data-price]')?.getAttribute('data-price') || '0';
      
      const request = {
        countryCode: 'US',
        currencyCode: 'USD',
        supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
        merchantCapabilities: ['supports3DS'],
        total: {
          label: 'Hair Purchase',
          amount: parseFloat(hairPrice).toFixed(2)
        }
      };
      
      const session = new ApplePaySession(3, request);
      
      session.onvalidatemerchant = function(event) {
        // 验证商户（需要服务器端处理）
        console.log('Apple Pay merchant validation required');
        session.abort();
        alert('Apple Pay merchant validation not configured. Please use another payment method.');
      };
      
      session.onpaymentauthorized = function(event) {
        // 处理支付授权
        const payment = event.payment;
        
        fetch('balance_payment_api.php?action=process_hair_applepay', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            hair_id: hairId,
            payment_data: payment
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            session.completePayment(ApplePaySession.STATUS_SUCCESS);
            alert('Payment successful! Thank you for your purchase.');
            window.location.reload();
          } else {
            session.completePayment(ApplePaySession.STATUS_FAILURE);
            alert('Payment failed: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          session.completePayment(ApplePaySession.STATUS_FAILURE);
          console.error('Apple Pay error:', error);
          alert('Payment failed. Please try again.');
        });
      };
      
      session.begin();
    }
    
    // 页面加载完成后初始化Apple Pay
    document.addEventListener('DOMContentLoaded', function() {
      initApplePay();
    });
  </script>
  
  <!-- 购物车功能脚本 -->
  <script>
    // 为所有加入购物车按钮添加事件监听器
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
      button.addEventListener('click', function() {
        const hairId = this.getAttribute('data-hair-id');
        const itemType = this.getAttribute('data-item-type');
        
        // 禁用按钮并显示加载状态
        const originalText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = 'Adding...';
        
        // 发送添加到购物车的请求
        fetch('cart_api.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `action=add_to_cart&item_type=${itemType}&item_id=${hairId}&is_photo_pack=0&quantity=1`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // 成功添加到购物车
            this.innerHTML = 'Added!';
            this.style.backgroundColor = '#e91e63';
            
            // 更新页面头部的购物车数量徽章
            const cartBadge = document.getElementById('cart-badge');
            if (cartBadge && data.cart_count) {
              cartBadge.textContent = data.cart_count;
              cartBadge.classList.remove('empty');
            }
            
            // 显示成功消息
            showCartMessage(data.message, 'success');
            
            // 2秒后恢复按钮状态
            setTimeout(() => {
              this.innerHTML = originalText;
              this.disabled = false;
            }, 2000);
          } else {
            // 添加失败，检查是否为重复添加
            if (data.action === 'duplicate') {
              this.innerHTML = 'Already in Cart';
              this.style.backgroundColor = '#ffc107';
              this.style.color = '#856404';
              
              // 显示提示消息
              showCartMessage('This item is already in your cart', 'warning');
              
              // 2秒后恢复按钮状态
              setTimeout(() => {
                this.innerHTML = originalText;
                this.style.backgroundColor = '#e91e63';
                this.style.color = 'white';
                this.disabled = false;
              }, 2000);
            } else {
              // 其他添加失败情况
              this.innerHTML = originalText;
              this.disabled = false;
              showCartMessage(data.message || 'Failed to add to cart', 'error');
            }
          }
        })
        .catch(error => {
          console.error('Error:', error);
          this.innerHTML = originalText;
          this.disabled = false;
          showCartMessage('Network error, please try again', 'error');
        });
      });
    });
    
    // 显示购物车操作消息
    function showCartMessage(message, type) {
      // 创建消息元素
      const messageDiv = document.createElement('div');
      messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 4px;
        color: white;
        font-weight: bold;
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
      `;
      
      if (type === 'success') {
        messageDiv.style.backgroundColor = '#28a745';
      } else if (type === 'warning') {
        messageDiv.style.backgroundColor = '#ffc107';
        messageDiv.style.color = '#856404';
      } else {
        messageDiv.style.backgroundColor = '#dc3545';
      }
      
      messageDiv.textContent = message;
      
      // 添加到页面
      document.body.appendChild(messageDiv);
      
      // 3秒后自动移除
      setTimeout(() => {
        messageDiv.style.opacity = '0';
        messageDiv.style.transform = 'translateX(100%)';
        setTimeout(() => {
          if (messageDiv.parentNode) {
            messageDiv.parentNode.removeChild(messageDiv);
          }
        }, 300);
      }, 3000);
    }
    
    // 头发余额支付处理
    document.addEventListener('DOMContentLoaded', function() {
      // 获取用户余额
      const userBalance = <?php echo isset($userBalance) ? json_encode($userBalance) : 0; ?>;
      
      // 为头发余额支付按钮添加点击事件
      const balancePayBtnHair = document.querySelector('.balance-pay-btn-hair');
      if (balancePayBtnHair) {
        balancePayBtnHair.addEventListener('click', function() {
          const hairId = this.getAttribute('data-hair-id');
          const price = parseFloat(this.getAttribute('data-price'));
          
          // 检查余额是否足够
          if (userBalance < price) {
            alert('Insufficient balance. Your balance: $' + userBalance.toFixed(2) + ', Price: $' + price.toFixed(2));
            return;
          }
          
          // 确认购买
          if (confirm('Do you want to pay $' + price.toFixed(2) + ' for this hair using your account balance?')) {
            // 显示加载状态
            this.disabled = true;
            this.innerHTML = 'Processing...';
            
            // 显示加载弹窗
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = '<div class="spinner"></div><p>Transaction processing...</p>';
            loadingOverlay.style.display = 'flex';
            document.body.appendChild(loadingOverlay);
            
            // 发送支付请求
            fetch('balance_payment_api.php?action=process_hair_payment', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              credentials: 'same-origin',
              body: JSON.stringify({
                hair_id: hairId
              })
            })
            .then(response => {
              if (!response.ok) {
                throw new Error('Network response was not ok');
              }
              return response.json();
            })
            .then(data => {
              if (data.success) {
                // 隐藏加载弹窗
                if (loadingOverlay) {
                  loadingOverlay.remove();
                }
                
                // 支付成功
                alert('Payment successful! Order ID: ' + data.order_id + '\n\nA confirmation email with hair information has been sent to your email address.');
                
                // 更新页面上显示的余额
                const balanceElements = document.querySelectorAll('.user-balance');
                balanceElements.forEach(el => {
                  el.textContent = '$' + data.remaining_balance.toFixed(2);
                });
                
                // 重新加载页面以更新状态
                setTimeout(() => {
                  window.location.reload();
                }, 1500);
              } else {
                // 隐藏加载弹窗
                if (loadingOverlay) {
                  loadingOverlay.remove();
                }
                
                // 支付失败
                let errorMessage = 'Payment failed';
                
                // 检查是否为邮件限流错误
                if (data.error && (data.error.includes('Failed to send confirmation email') || data.error.includes('rate limited'))) {
                  errorMessage = 'Purchase interval too short, please try again later';
                } else if (data.error) {
                  errorMessage = 'Payment failed: ' + data.error;
                }
                
                alert(errorMessage);
                
                // 恢复按钮状态
                this.disabled = false;
                this.innerHTML = 'Pay with Balance';
              }
            })
            .catch(error => {
              // 隐藏加载弹窗
              if (loadingOverlay) {
                loadingOverlay.remove();
              }
              
              console.error('Error:', error);
              alert('An error occurred during payment processing. Please try again.');
              
              // 恢复按钮状态
              this.disabled = false;
              this.innerHTML = 'Pay with Balance';
            });
          }
        });
      }
    });
  </script>
  
</body>
</html>
