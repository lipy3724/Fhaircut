<?php
session_start();
require_once __DIR__ . '/db_config.php';

// 读取产品ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: main.php');
    exit;
}

$productId = intval($_GET['id']);
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// 记录用户登录状态
error_log("Product detail page - User login status: " . ($isLoggedIn ? "Logged in" : "Not logged in"));
if ($isLoggedIn) {
    error_log("Logged in user - ID: " . $_SESSION['id'] . ", Username: " . $_SESSION['username'] . ", Email: " . ($_SESSION['email'] ?? 'Not set'));
}

// 用户余额和购物车信息将由header.php提供

// 获取产品信息
$product = null;
$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $product = $row;
    }
    mysqli_stmt_close($stmt);
}

// 获取所有分类（包括主分类和附加分类）
$all_categories = [];
$additional_categories = [];
if ($product) {
    $sql = "SELECT c.id, c.name FROM product_categories pc 
            LEFT JOIN categories c ON pc.category_id = c.id 
            WHERE pc.product_id = ? ORDER BY c.name";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $productId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $all_categories[] = $row;
            // 如果不是主分类，则添加到附加分类列表
            if ($row['id'] != $product['category_id']) {
                $additional_categories[] = $row['name'];
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if (!$product) {
    header('Location: main.php');
    exit;
}

// 更新点击次数
if ($stmt = mysqli_prepare($conn, 'UPDATE products SET clicks = clicks + 1 WHERE id = ?')) {
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$hasPrice = isset($product['price']) && is_numeric($product['price']) && floatval($product['price']) > 0;
$hasPhotoPackPrice = isset($product['photo_pack_price']) && is_numeric($product['photo_pack_price']) && floatval($product['photo_pack_price']) > 0;

// 检查是否存在对应的付费内容
$hasVideoContent = !empty($product['paid_video']);
$hasPhotoContent = !empty($product['paid_photos_zip']) || !empty($product['paid_photos_count']);

// 只有当价格存在且对应内容存在时，才显示购物车按钮
$showVideoCartButton = $hasPrice && $hasVideoContent;
$showPhotoCartButton = $hasPhotoPackPrice && $hasPhotoContent;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?> - Detail</title>
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
    
    .sidebar h3.product-categories {
        line-height: 1.3;
        word-break: break-word;
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
        width: 90%;
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
        width: 70%;
        font-size: 14px;
        margin-top: 5px;
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

    .purchase { background: #fff; border-radius: 6px; padding: 12px; box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1); margin-top: 12px; }
    .purchase h3 { margin: 0 0 10px 0; font-size: 16px; }
    .purchase .options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
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
    
    .btn-primary {
        background-color: #e75480;
        color: white;
        border: 1px solid #e75480;
    }
    .btn-primary:hover {
        background-color: #d64072;
        border-color: #d64072;
    }
    
    .btn-success {
        background-color: #e75480;
        color: white;
        border: 1px solid #e75480;
    }
    .btn-success:hover {
        background-color: #d64072;
        border-color: #d64072;
    }
    
    .btn-info {
        background-color: #ffccd5;
        color: #333;
        border: 1px solid #f7a4b9;
    }
    .btn-info:hover {
        background-color: #f7a4b9;
        color: #333;
    }
    
    .btn-warning {
        background-color: #ffb6c9;
        color: #333;
        border: 1px solid #f7a4b9;
    }
    .btn-warning:hover {
        background-color: #f7a4b9;
        color: #333;
    }
    
    .balance-pay-btn {
        background-color: #e75480;
        color: white;
        border: 1px solid #e75480;
        margin-bottom: 10px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 4px;
    }
    .balance-pay-btn:hover {
        background-color: #d64072;
        border-color: #d64072;
    }
    
    .nav-tabs {
        border-bottom: 1px solid #f7a4b9;
    }
    .nav-tabs .nav-link {
        color: #333;
    }
    .nav-tabs .nav-link.active {
        color: #e75480;
        border-color: #f7a4b9 #f7a4b9 #fff;
    }
    .nav-tabs .nav-link:hover {
        border-color: #ffccd5 #ffccd5 #f7a4b9;
    }
    
    /* Google Pay 按钮容器样式 */
    .googlepay-button-container {
      width: 100%;
      min-height: 40px;
      margin-top: 10px;
    }
    
    /* 修改Google Pay按钮样式 */
    .googlepay-button-container .gpay-button {
      width: 100% !important;
      height: 48px !important;
      min-height: 48px !important;
      border-radius: 4px !important;
    }
    
    /* Apple Pay 按钮容器样式 */
    .applepay-button-container {
      width: 100%;
      min-height: 48px;
      margin-top: 10px;
      max-width: 100%;
      display: block;
    }
    
    /* 自定义Apple Pay按钮样式 */
    .apple-pay-button-wrapper {
      width: 100%;
      display: block;
      margin: 0;
      padding: 0;
    }
    
    .apple-pay-button {
      width: 100%;
      height: 48px;
      min-height: 48px;
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
      background-image: url("data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDMiIGhlaWdodD0iMTgiIHZpZXdCb3g9IjAgMCA0MyAxOCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYuMjUzOTEgMy4xMDM1MkM1Ljg0MjE5IDMuNjAzNTIgNS4xODM1OSA0LjAwMzUyIDQuNTI1IDQuMDAzNTJDNC40NTMxMyA0LjAwMzUyIDQuMzgxMjUgNC4wMDM1MiA0LjMwOTM4IDMuOTk0MTRDNC4yODUxNiAzLjk0NzI3IDQuMjEzMjggMy42MDM1MiA0LjIxMzI4IDMuNTk0MTRDMy45NzQ2MSAyLjY0MTAyIDQuNjgxNjQgMS42NTk3NyA1LjE1OTM4IDEuMTY5MTRDNS51NzEwOSAwLjY1OTc2NiA2LjI1MzkxIDAuMjUgNi45NjA5NCAwLjI1QzcuMDIzNDQgMC4yNSA3LjA5NTMxIDAuMjUgNy4xNTc4MSAwLjI1OTM3NUM3LjE3MzQ0IDAuMzI1MTk1IDcuMTgyMDMgMC4zODQ3NjYgNy4xOTA2MyAwLjQ0NDMzNkM3LjI2MjUgMS4zNSA2Ljc1NTg2IDIuNTEyNyA2LjI1MzkxIDMuMTAzNTJaTTEzLjMxMDUgMTMuNDY1OEMxMy4wNzE5IDEzLjcyNSAxMi43MzA1IDEzLjk5NDEgMTIuMjgzNiAxMy45OTQxQzExLjY5MTQgMTMuOTk0MSAxMS4zNjg2IDEzLjU3ODEgMTAuOTM4NSAxMy41NzgxQzEwLjQ4OTggMTMuNTc4MSAxMC4xMTk1IDEzLjk5NDEgOS41NzQ2MSAxMy45OTQxQzkuMDkxMDIgMTMuOTk0MSA4LjY5ODA1IDEzLjY5NzMgOC40MzA4NiAxMy4zNDQxQzcuNjQxOCAxMi4zNTM1IDcuNTQyMTkgMTAuODM3OSA4LjAwOTc3IDkuODI4MTNDOC4zMzU5NCA5LjEzMTY0IDguOTQ2NDggOC41ODc4OSA5LjYxNTYzIDguNTg3ODlDMTAuMDY0NSA4LjU4Nzg5IDEwLjQxODQgOC45MDM1MiAxMC44NjcyIDguOTAzNTJDMTEuMjk3MyA4LjkwMzUyIDExLjcwNzggOC41ODc4OSAxMi4yMTg0IDguNTg3ODlDMTIuODA0NyA4LjU4Nzg5IDEzLjMzNDggOS4wNTk3NyAxMy42NjEgOS42Njg3NUMxMy4yMzA5IDkuOTI4MTMgMTIuODY5MSAxMC4zNzUgMTIuODY5MSAxMC45NzI3QzEyLjg2OTEgMTEuODM3OSAxMy4zNTU1IDEyLjI5NDEgMTMuMzEwNSAxMy40NjU4Wk0xMi4yMDkgOC4wODc4OUMxMi4wMTc2IDguMTI1IDExLjgwNzggOC4xNTMzMiAxMS41OTggOC4xNTMzMkMxMS40NzI3IDguMTUzMzIgMTEuMTc1OCA4LjEyNSAxMS4wNjA1IDguMTI1QzExLjA1MTIgNy45MDYyNSAxMS4xMjc3IDcuNjU2MjUgMTEuMjQzIDcuNDQ2ODhDMTEuMzg2NyA3LjE5Njg4IDExLjYyNSA2Ljk5NDEgMTEuOTIxOSA2Ljg3NUMxMi4wMzczIDYuODI4MTMgMTIuMTcxOSA2LjgwMDc4IDEyLjMwNjYgNi44MDA3OEMxMi4zMzk4IDYuODAwNzggMTIuMzczIDYuODAwNzggMTIuNDA2MyA2LjgwMDc4QzEyLjQyNTggNy4wMzEyNSAxMi4zOTI2IDcuMjY5NTMgMTIuMzExNyA3LjQ5NjA5QzEyLjI4NTIgNy42MDkzOCAxMi4yNTU5IDcuNzIyNjYgMTIuMjA5IDcuODM1OTRWOC4wODc4OVpNMjEuNDUxMiAxNS4wMDM5QzIxLjA5MzggMTYuMzEyNSAyMC40MzUyIDE3LjUgMTkuNDY4OCAxNy41QzE5LjA0NjkgMTcuNSAxOC42NTM5IDE3LjMxMjUgMTguMzgzNiAxNi45ODQ0QzE4LjExNjUgMTYuNjU2MyAxNy45NTMxIDE2LjIyMjcgMTcuOTUzMSAxNS42OTUzQzE3Ljk1MzEgMTQuNzA0NyAxOC41MjczIDEzLjMxMjUgMTkuMTMzNiAxMi41QzE5LjgzMTEgMTEuNTM5MSAyMC41Mzg5IDExLjAwMzkgMjEuNDUxMiAxMS4wMDM5QzIxLjU3NTggMTEuMDAzOSAyMS44NTM1IDExLjAyMjcgMjIuMDM2MSAxMS4wNzAzTDIyLjA4MjQgMTEuMDc5N0MyMi4wODI0IDExLjA3OTcgMjIuMDczOCAxMS4wODkxIDIyLjA2NTIgMTEuMTA3NEMyMS45NTggMTEuNTQyMiAyMS4zMDUzIDEzLjk4NDQgMjEuMzA1MyAxMy45ODQ0TDIxLjMwMTUgMTMuOTkzOEMyMS4wNTM1IDE0LjgxMjUgMjAuNzk2MSAxNS40NjA5IDIwLjc5NjEgMTUuODc1QzIwLjc5NjEgMTYuMjgxMyAyMC45NTk1IDE2LjUgMjEuMjI2NiAxNi41QzIxLjY0ODQgMTYuNSAyMi4wNzAzIDE1Ljk3MjcgMjIuMzM3NCAxNS4xNDQ1QzIyLjQxOTkgMTQuODkwNiAyMi40NzY2IDE0LjY0MDYgMjIuNjEyMyAxNC4wODU5TDIzLjQ5MTIgMTAuNUgyNC41TDIzLjE2MDIgMTUuNzVDMjMuMDU0NyAxNi4yNSAyMi45NTggMTYuNzUgMjIuOTU4IDE3LjEyNUMyMi45NTggMTcuMzc1IDIzLjAyNjYgMTcuNSAyMy4yMDMxIDE3LjVDMjMuMzc1IDE3LjUgMjMuNTM4NCAxNy40MDYzIDIzLjY1MjMgMTcuMjgxM0MyMy43NjU2IDE3LjE1NjMgMjMuODYzMyAxNy4wMzEzIDIzLjk2ODggMTYuODc1TDI0LjI1IDE3LjEyNUMyMy45Njg4IDE3LjUgMjMuNDU3IDE3Ljk5MDIgMjIuNzUgMTcuOTkwMkMyMi4yNDggMTcuOTkwMiAyMS45Mzk1IDE3LjY5NTMgMjEuODI0MiAxNy4xNjhDMjEuNzk4OCAxNy4wNDMgMjEuNzg5MSAxNi45MDgyIDIxLjc4OTEgMTYuNzY1NkMyMS43ODkxIDE2LjQ0NTMgMjEuODMzMyAxNi4wODk4IDIxLjkxMDIgMTUuNzVMMjIuMDM1MiAxNS4wMDM5SDIxLjQ1MTJaTTMxLjI5MyAxNy4yNUMzMS4wNDQ5IDE3LjUgMzAuNjIzIDE3Ljc1IDMwLjA2NjQgMTcuNzVDMjkuNDgwNSAxNy43NSAyOS4xMDU1IDE3LjM3NSAyOS4xMDU1IDE2LjYyNUMyOS4xMDU1IDE2LjUgMjkuMTI1IDE2LjM3NSAyOS4xNTYyIDE2LjI1QzI5LjI0MjIgMTUuODc1IDI5LjM4MjggMTUuNDM3NSAyOS41MTc2IDE1LjEyNUMyOS42NTI0IDE0LjgxMjUgMjkuODI0MiAxNC40Mzc1IDMwLjAxNTYgMTQuMDYyNUwzMC4wNDY5IDE0QzI5LjkwMjMgMTMuODc1IDI5Ljc2NzYgMTMuNjg3NSAyOS42NzE5IDEzLjVDMjkuNTc2MiAxMy4zMTI1IDI5LjUyNzMgMTMuMTI1IDI5LjUyNzMgMTIuOTM3NUMyOS41MjczIDEyLjg3NSAyOS41MzY3IDEyLjgxMjUgMjkuNTQ2MSAxMi43NUMyOS42MTMzIDEyLjMxMjUgMjkuOTExNyAxMS44NzUgMzAuMzUxNiAxMS41NjI1QzMwLjc5MSAxMS4yNSAzMS4yNTc4IDExLjA2MjUgMzEuNzUgMTEuMDYyNUMzMi4wODU5IDExLjA2MjUgMzIuMzUxNiAxMS4xNTYzIDMyLjU0NjkgMTEuMzQzOEMzMi43NDIyIDExLjUzMTMgMzIuODM3OSAxMS43ODEzIDMyLjgzNzkgMTIuMDkzOEMzMi44Mzc5IDEyLjQwNjMgMzIuNzQyMiAxMi43MTg4IDMyLjU0NjkgMTMuMDMxM0MzMi4zNTE2IDEzLjM0MzggMzIuMDk1MyAxMy42MjUgMzEuNzc3MyAxMy44NzVMMzIuNzYxNyAxNS4yNUMzMi45NTcgMTQuODc1IDMzLjEyNSAxNC40MDYzIDMzLjI2OTUgMTMuODc1SDM0LjI1QzM0LjEwNTUgMTQuNTMxMyAzMy44NTk0IDE1LjEyNSAzMy41MTU2IDE1LjY1NjNMMzQuNjI4OSAxNy4yNUgzMy4zNzExTDMyLjc1MiAxNi4zNzVDMzIuMzQzOCAxNi45MDYzIDMxLjgzNTkgMTcuMjUgMzEuMjkzIDE3LjI1Wk0zMC4xMzI4IDEyLjg3NUMzMC4xMzI4IDEzLjA2MjUgMzAuMjI4NSAxMy4yNSAzMC40MjM4IDEzLjQzNzVDMzAuNzUgMTMuMjUgMzEuMDA1OSAxMy4wMzEzIDMxLjE5MTQgMTIuNzgxM0MzMS4zNzY5IDEyLjUzMTMgMzEuNDcyNyAxMi4yODEzIDMxLjQ3MjcgMTIuMDYyNUMzMS40NzI3IDExLjkzNzUgMzEuNDM1NSAxMS44NDM4IDMxLjM2NzIgMTEuNzgxM0MzMS4yOTg4IDExLjcxODggMzEuMjExOSAxMS42ODc1IDMxLjA5NzcgMTEuNjg3NUMzMC44NzMgMTEuNjg3NSAzMC42Njc5IDExLjc4MTMgMzAuNDgyNCAxMS45Njg4QzMwLjI5NjkgMTIuMTU2MyAzMC4xODM2IDEyLjM3NSAzMC4xNDI2IDEyLjYyNUMzMC4xMzY3IDEyLjY4NzUgMzAuMTMyOCAxMi43ODEzIDMwLjEzMjggMTIuODc1Wk0zMC4zNzExIDE1Ljc1QzMwLjM3MTEgMTYuMzEyNSAzMC42MDc0IDE2LjU5MzggMzEuMDg1OSAxNi41OTM4QzMxLjM4MjggMTYuNTkzOCAzMS42NTgyIDE2LjQwNjMgMzEuOTI1OCAxNi4wMzEzTDMwLjc5MSAxNC40Mzc1QzMwLjY5NTMgMTQuNjI1IDMwLjU5OTYgMTQuODc1IDMwLjUxMzcgMTUuMTg3NUMzMC40MTggMTUuNSAzMC4zNzExIDE1LjY1NjMgMzAuMzcxMSAxNS43NVpNMzkuMTM2NyAxNy4yNUMzOC44ODg3IDE3LjUgMzguNDY2OCAxNy43NSAzNy45MTAyIDE3Ljc1QzM3LjMyNDIgMTcuNzUgMzYuOTQ5MiAxNy4zNzUgMzYuOTQ5MiAxNi42MjVDMzYuOTQ5MiAxNi41IDM2Ljk2ODggMTYuMzc1IDM3IDE2LjI1QzM3LjA4NTkgMTUuODc1IDM3LjIyNjYgMTUuNDM3NSAzNy4zNjEzIDE1LjEyNUMzNy40OTYxIDE0LjgxMjUgMzcuNjY4IDE0LjQzNzUgMzcuODU5NCAxNC4wNjI1TDM3Ljg5MDYgMTRDMzcuNzQ2MSAxMy44NzUgMzcuNjExMyAxMy42ODc1IDM3LjUxNTYgMTMuNUMzNy40MiAxMy4zMTI1IDM3LjM3MTEgMTMuMTI1IDM3LjM3MTEgMTIuOTM3NUMzNy4zNzExIDEyLjg3NSAzNy4zODA5IDEyLjgxMjUgMzcuMzkwNiAxMi43NUMzNy40NTcgMTIuMzEyNSAzNy43NTU5IDExLjg3NSAzOC4xOTUzIDExLjU2MjVDMzguNjM0OCAxMS4yNSAzOS4xMDE2IDExLjA2MjUgMzkuNTkzOCAxMS4wNjI1QzM5LjkyOTcgMTEuMDYyNSA0MC4xOTUzIDExLjE1NjMgNDAuMzkwNiAxMS4zNDM4QzQwLjU4NTkgMTEuNTMxMyA0MC42ODE2IDExLjc4MTMgNDAuNjgxNiAxMi4wOTM4QzQwLjY4MTYgMTIuNDA2MyA0MC41ODU5IDEyLjcxODggNDAuMzkwNiAxMy4wMzEzQzQwLjE5NTMgMTMuMzQzOCAzOS45Mzk1IDEzLjYyNSAzOS42MjExIDEzLjg3NUw0MC42MDU1IDE1LjI1QzQwLjgwMDggMTQuODc1IDQwLjk2ODggMTQuNDA2MyA0MS4xMTMzIDEzLjg3NUg0Mi4wOTM4QzQxLjk0OTIgMTQuNTMxMyA0MS43MDMxIDE1LjEyNSA0MS4zNTk0IDE1LjY1NjNMNDIuNDcyNyAxNy4yNUg0MS4yMTQ4TDQwLjU5NTcgMTYuMzc1QzQwLjE4NzUgMTYuOTA2MyAzOS42Nzk3IDE3LjI1IDM5LjEzNjcgMTcuMjVaTTM3Ljk3NjYgMTIuODc1QzM3Ljk3NjYgMTMuMDYyNSAzOC4wNzIzIDEzLjI1IDM4LjI2NzYgMTMuNDM3NUMzOC41OTM4IDEzLjI1IDM4Ljg0OTYgMTMuMDMxMyAzOS4wMzUyIDEyLjc4MTNDMzkuMjIwNyAxMi41MzEzIDM5LjMxNjQgMTIuMjgxMyAzOS4zMTY0IDEyLjA2MjVDMzkuMzE2NCAxMS45Mzc1IDM5LjI3OTMgMTEuODQzOCAzOS4yMTA5IDExLjc4MTNDMzkuMTQyNiAxMS43MTg4IDM5LjA1NTcgMTEuNjg3NSAzOC45NDE0IDExLjY4NzVDMzguNzE2OCAxMS42ODc1IDM4LjUxMTcgMTEuNzgxMyAzOC4zMjYyIDExLjk2ODhDMzguMTQwNiAxMi4xNTYzIDM4LjAyNzMgMTIuMzc1IDM3Ljk4NjMgMTIuNjI1QzM3Ljk4MDUgMTIuNjg3NSAzNy45NzY2IDEyLjc4MTMgMzcuOTc2NiAxMi44NzVaTTM4LjIxNDggMTUuNzVDMzguMjE0OCAxNi4zMTI1IDM4LjQ1MTIgMTYuNTkzOCAzOC45Mjk3IDE2LjU5MzhDMzkuMjI2NiAxNi41OTM4IDM5LjUwMiAxNi40MDYzIDM5Ljc2OTUgMTYuMDMxM0wzOC42MzQ4IDE0LjQzNzVDMzguNTM5MSAxNC42MjUgMzguNDQzNCAxNC44NzUgMzguMzU3NCAxNS4xODc1QzM4LjI2MTcgMTUuNSAzOC4yMTQ4IDE1LjY1NjMgMzguMjE0OCAxNS43NVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=");
      background-repeat: no-repeat;
      background-position: center;
      background-size: contain;
    }
    
    .gallery { margin-top: 16px; background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 14px; }
    .gallery h3 { margin: 0 0 12px 0; font-size: 16px; }
    .big-image { width: 100%; max-width: 980px; margin: 0 auto 16px; display: block; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
    
    /* 删除紫色背景的会员锁定样式 */
    /* .member-lock { width: 100%; max-width: 980px; height: 520px; margin: 0 auto 16px; border-radius: 6px; background: #B8B5E1; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; } */
    
    /* 添加模糊背景图片样式 */
    .member-blur-container {
      position: relative;
      width: 100%;
      max-width: 980px;
      height: 520px;
      overflow: hidden;
      border-radius: 6px;
      margin: 0 auto 16px;
    }
    
    .member-blur-bg {
      width: 100%;
      height: 100%;
      object-fit: cover;
      filter: blur(10px);
      transform: scale(1.1);
    }
    
    .member-blur-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 24px;
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

    @media (max-width: 992px) {
      .info-bar { grid-template-columns: 1fr; }
      .thumb { width: 100%; height: 180px; }
      .purchase .options { grid-template-columns: 1fr; }
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
    
    .video-container {
        margin-top: 12px;
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1);
        overflow: hidden;
    }
    
    .video-js .vjs-big-play-button {
        background-color: rgba(231, 84, 128, 0.7) !important;
        border-color: #e75480 !important;
    }
    
    .video-js .vjs-play-progress, 
    .video-js .vjs-volume-level {
        background-color: #e75480 !important;
    }
    
    .video-js .vjs-control-bar {
        background-color: rgba(255, 204, 213, 0.7) !important;
    }
    
    .image-gallery {
        margin-top: 12px;
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(231, 84, 128, 0.1);
        padding: 12px;
    }
    
    .gallery-item {
        border: 1px solid #f7a4b9;
        border-radius: 4px;
        overflow: hidden;
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .gallery-item:hover {
        transform: scale(1.03);
        box-shadow: 0 2px 8px rgba(231, 84, 128, 0.2);
    }
    
    .lightbox-overlay {
        background-color: rgba(0, 0, 0, 0.85);
    }
    
    .lightbox-controls button {
        background-color: rgba(231, 84, 128, 0.7);
        color: white;
    }
    
    .lightbox-controls button:hover {
        background-color: rgba(214, 64, 114, 0.9);
    }
    
    .footer {
        margin-top: 40px;
        padding: 20px;
        background-color: #ffccd5;
        color: #333;
        text-align: center;
        border-top: 1px solid #f7a4b9;
    }
    
    .footer a {
        color: #e75480;
        text-decoration: none;
    }
    
    .footer a:hover {
        text-decoration: underline;
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
      
      <h3 class="product-categories">Product<br>Categories:</h3>
      <ul class="category-list">
        <?php
        // 获取所有类别
        $categories = [];
        $sql = "SELECT id, name FROM categories ORDER BY id ASC";
        $cat_result = mysqli_query($conn, $sql);
        
        if ($cat_result) {
            while ($cat_row = mysqli_fetch_assoc($cat_result)) {
                // 跳过"Taday 42.0% off"分类，因为已经有"Today 42.0% off"
                if ($cat_row['name'] === 'Taday 42.0% off') continue;
                ?>
                <li>
                    <a href="main.php?category=<?php echo $cat_row['id']; ?>" class="<?php echo (isset($_GET['category']) && $_GET['category'] == $cat_row['id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat_row['name']); ?>
                    </a>
                </li>
                <?php
            }
            mysqli_free_result($cat_result);
        }
        ?>
      </ul>
      
      <div class="search-box">
        <?php if ($isLoggedIn): ?>
        <form action="search.php" method="get">
            <input type="text" name="keyword" placeholder="Keyword" required>
            <button type="submit">Search</button>
        </form>
        <?php else: ?>
        <input type="text" placeholder="Keyword" disabled>
        <button onclick="alert('Please login to use search function')">Search</button>
        <div class="help-text" style="color: #666; font-size: 12px; margin-top: 5px;">Please login to use search</div>
        <?php endif; ?>
      </div>
    </div>
    
          <div class="main-detail-content">
        <div class="detail-container">
    <div class="title">
      <a href="main.php" style="display: inline-block; padding: 5px 10px; color: #4A4A4A; text-decoration: none; margin-right: 10px; font-size: 14px;">
        <span style="display: inline-flex; align-items: center;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M15 18l-6-6 6-6"/></svg>
          Back to List
        </span>
      </a>
      <?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?>
      <?php if (!empty($product['subtitle'])): ?>
        <small><?php echo htmlspecialchars($product['subtitle']); ?></small>
      <?php endif; ?>
    </div>

    <div class="info-bar">
      <div class="thumb">
        <?php if ($isLoggedIn && !empty($product['member_image1'])): ?>
          <!-- 会员看到无水印的会员图片1 -->
          <img src="<?php echo htmlspecialchars($product['member_image1']); ?>" alt="thumb" />
        <?php elseif (!empty($product['image'])): ?>
          <!-- 游客看到带水印的主图 -->
          <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="thumb" />
        <?php else: ?>
          <span>No image</span>
        <?php endif; ?>
      </div>

      <div class="meta">
        <div class="meta-item">
          Category: <b><?php echo htmlspecialchars($product['category_name']); ?></b>
          <?php if (!empty($additional_categories)): ?>
            <br><span style="font-size: 12px; color: #666;">Additional: 
            <?php echo implode(', ', array_map('htmlspecialchars', $additional_categories)); ?></span>
          <?php endif; ?>
        </div>
        <div class="meta-item">Clicks: <b><?php echo intval($product['clicks']); ?></b></div>
      </div>
      
      <!-- 购物车按钮区域 -->
      <div class="cart-actions">
        <?php if ($isLoggedIn): ?>
          <?php if ($showVideoCartButton): ?>
          <button class="btn add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="false" data-item-type="video" style="background-color: #e91e63; color: white; border: 1px solid #e91e63; font-size: 12px; padding: 6px 10px;">
            Add Video to Cart
          </button>
          <?php endif; ?>
          
          <?php if ($showPhotoCartButton): ?>
          <button class="btn add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="true" data-item-type="photo" style="background-color: #e91e63; color: white; border: 1px solid #e91e63; font-size: 12px; padding: 6px 10px;">
            Add Photos to Cart
          </button>
          <?php endif; ?>
          
          <?php if (!$showVideoCartButton && !$showPhotoCartButton): ?>
          <div style="font-size: 12px; color: #666; text-align: center;">
            No items<br>available
          </div>
          <?php endif; ?>
        <?php else: ?>
          <div style="font-size: 12px; color: #666; text-align: center;">
            Login to add<br>to cart
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="purchase">
      <h3>Purchase Area</h3>
      <div class="options">
        <?php if (!empty($product['paid_video'])): ?>
        <div class="card">
          <h4>Buy Video</h4>
          <div class="meta-item">Includes: Full video</div>
          <?php if (!empty($product['paid_video_size']) || !empty($product['paid_video_duration'])): ?>
          <div class="meta-item">Video Spec: <b>H.265 / 4K</b></div>
          <?php if (!empty($product['paid_video_size'])): ?>
          <div class="meta-item">File Size: <b><?php
            $size = isset($product['paid_video_size']) ? intval($product['paid_video_size']) : 0;
            if ($size > 0) {
              $units = ['B','KB','MB','GB'];
              $i = 0; $num = $size;
              while ($num >= 1024 && $i < count($units)-1) { $num /= 1024; $i++; }
              echo number_format($num, $num >= 100 ? 0 : 2) . ' ' . $units[$i];
            } else { echo '—'; }
          ?></b></div>
          <?php endif; ?>
          <?php if (!empty($product['paid_video_duration'])): ?>
          <div class="meta-item">Duration: <b><?php echo !empty($product['paid_video_duration']) ? intval($product['paid_video_duration']) . 's' : '—'; ?></b></div>
          <?php endif; ?>
          <?php endif; ?>
          <?php if ($hasPrice): ?>
           <div class="meta-item">Price: <span class="price">$<?php echo number_format($product['price'], 2); ?></span></div>
           <div style="margin-top:8px;">
             <?php if ($isLoggedIn): ?>
             <button class="btn balance-pay-btn" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="false" data-price="<?php echo $product['price']; ?>">Pay with Balance</button>
             <?php endif; ?>
             <div id="paypal-button-video" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="false"></div>
             <div id="googlepay-button-container-video" class="googlepay-button-container" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="false" style="margin-top: 10px;"></div>
             <div id="applepay-button-container-video" class="applepay-button-container" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="false" style="margin-top: 10px; max-width: 250px;"></div>
           </div>
           <?php else: ?>
           <div class="meta-item">Price: <span class="price">—</span></div>
           <div style="margin-top:8px;">
             <a class="btn" href="#" onclick="alert('Price will be available after admin uploads product assets.');return false;">Buy Now</a>
           </div>
           <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($product['paid_photos_zip']) || !empty($product['paid_photos_count'])): ?>
        <div class="card">
          <h4>Buy Photo Pack</h4>
          <div class="meta-item">Includes: HD photos</div>
          <?php if (intval($product['paid_photos_count']) > 0): ?>
          <div class="meta-item">Photos: <b><?php echo intval($product['paid_photos_count']); ?></b></div>
          <?php endif; ?>
          <?php if (!empty($product['paid_photos_formats'])): ?>
          <div class="meta-item">Formats: <b><?php echo htmlspecialchars($product['paid_photos_formats']); ?></b></div>
          <?php endif; ?>
          <?php if (!empty($product['paid_photos_total_size'])): ?>
          <div class="meta-item">Total Size: <b><?php
            $isz = isset($product['paid_photos_total_size']) ? intval($product['paid_photos_total_size']) : 0;
            if ($isz > 0) {
              $units = ['B','KB','MB','GB'];
              $i = 0; $num = $isz;
              while ($num >= 1024 && $i < count($units)-1) { $num /= 1024; $i++; }
              echo number_format($num, $num >= 100 ? 0 : 2) . ' ' . $units[$i];
            } else { echo '—'; }
          ?></b></div>
          <?php endif; ?>
                       <?php if ($hasPhotoPackPrice): ?>
           <div class="meta-item">Price: <span class="price">$<?php echo number_format($product['photo_pack_price'], 2); ?></span></div>
           <div style="margin-top:8px;">
             <?php if ($isLoggedIn): ?>
             <button class="btn balance-pay-btn" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="true" data-price="<?php echo $product['photo_pack_price']; ?>">Pay with Balance</button>
             <?php endif; ?>
             <div id="paypal-button-photo" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="true"></div>
             <div id="googlepay-button-container-photo" class="googlepay-button-container" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="true" style="margin-top: 10px;"></div>
             <div id="applepay-button-container-photo" class="applepay-button-container" data-product-id="<?php echo $product['id']; ?>" data-is-photo-pack="true" style="margin-top: 10px; max-width: 250px;"></div>
           </div>
           <?php else: ?>
           <div class="meta-item">Price: <span class="price">—</span></div>
           <div style="margin-top:8px;">
             <a class="btn" href="#" onclick="alert('Price will be available after admin uploads product assets.');return false;">Buy Now</a>
           </div>
           <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($product['paid_video']) && empty($product['paid_photos_zip']) && empty($product['paid_photos_count'])): ?>
        <div class="card">
          <h4>No Purchase Options Available</h4>
          <div class="meta-item">This product currently has no purchase options.</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 产品图片展示区域 -->
    <div class="gallery">
      <h3>Product Images</h3>
      
      <?php 
        // 游客显示带水印的图片1-4（包括主图）
        if (!$isLoggedIn) {
          // 显示游客图片（带水印）
          // 主图已经在上面显示过，这里显示image2-image4
          for ($i = 2; $i <= 4; $i++): 
            $imageField = 'image' . $i;
            if (!empty($product[$imageField])): 
      ?>
              <img src="<?php echo htmlspecialchars($product[$imageField]); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Image <?php echo $i; ?>" class="big-image">
      <?php 
            endif;
          endfor;
          // 在游客图片末尾添加提示信息
      ?>
          <div style="margin: 30px 0; padding: 25px; background-color: #ffe4e8; border-radius: 8px; text-align: center;">
            <p style="margin: 0; color: #d64072; font-size: 20px; font-weight: bold; line-height: 1.5;">
              To view more exclusive photos, please recharge to activate membership or purchase more videos!
            </p>
          </div>
      <?php
        } else {
          // 会员显示无水印的图片1-20（完全替换游客图片）
          // 主图位置也显示会员图片
          for ($i = 2; $i <= 20; $i++): 
            $memberImageField = 'member_image' . $i;
            if (!empty($product[$memberImageField])): 
      ?>
              <img src="<?php echo htmlspecialchars($product[$memberImageField]); ?>" alt="<?php echo htmlspecialchars($product['title']); ?> - Member Image <?php echo $i; ?>" class="big-image">
      <?php 
            endif;
          endfor;
        }
      ?>
      </div>
  </div>
      </div>
  </div>
  
  <!-- 调试信息 -->
  <?php
  error_log("Product paid_photos_formats: " . ($product['paid_photos_formats'] ?? 'NULL'));
  ?>
  
  <!-- PayPal支付处理脚本 -->
  <script>
    // 初始化PayPal按钮 - 视频购买
    if (document.getElementById('paypal-button-video')) {
      const videoButton = document.getElementById('paypal-button-video');
      const productId = videoButton.getAttribute('data-product-id');
      const isPhotoPack = false;
      
      // 添加加载覆盖层
      const videoOverlay = document.createElement('div');
      videoOverlay.className = 'loading-overlay';
      videoOverlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
      videoOverlay.style.display = 'none';
      videoButton.parentNode.appendChild(videoOverlay);
      
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
          videoOverlay.style.display = 'flex';
        },
        
        // 创建订单
        createOrder: function(data, actions) {
          return fetch('/paypal_api.php?action=create_order', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // 确保发送cookie和session信息
            body: JSON.stringify({
              product_id: productId,
              is_photo_pack: isPhotoPack
            })
          })
          .then(function(response) {
            return response.json();
          })
          .then(function(orderData) {
            if (orderData.error) {
              // 隐藏加载状态
              videoOverlay.style.display = 'none';
              console.error('Error creating order:', orderData.error);
              alert('Error creating order: ' + orderData.error);
              return;
            }
            return orderData.id;
          })
          .catch(function(error) {
            // 隐藏加载状态
            videoOverlay.style.display = 'none';
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
          });
        },
        
        // 支付完成
        onApprove: function(data, actions) {
          return fetch('/paypal_api.php?action=capture_payment', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // 确保发送cookie和session信息
            body: JSON.stringify({
              order_id: data.orderID
            })
          })
          .then(function(response) {
            // 检查响应状态
            if (!response.ok) {
              // 隐藏加载状态
              videoOverlay.style.display = 'none';
              throw new Error('Network response was not ok');
            }
            
            // 尝试解析JSON响应
            return response.text().then(function(text) {
              try {
                return JSON.parse(text);
              } catch (e) {
                // 隐藏加载状态
                videoOverlay.style.display = 'none';
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid server response');
              }
            });
          })
          .then(function(captureData) {
            // 隐藏加载状态
            videoOverlay.style.display = 'none';
            
            if (captureData.error) {
              console.error('Error capturing payment:', captureData.error);
              
              // 检查是否是邮件发送失败
              if (captureData.email_failed || (typeof captureData.error === 'string' && captureData.error.includes('Failed to send purchase confirmation email'))) {
                alert('Email sending failed. Payment was not completed. Please try again later.');
              } else {
                alert('Error processing your payment: ' + captureData.error);
              }
              return;
            }
            
            // 支付成功
            const resultMessage = document.createElement('div');
            resultMessage.innerHTML = `
              <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-top: 10px; border-radius: 5px;">
                <h4>Payment Successful!</h4>
                <p>Order ID: ${captureData.id}</p>
                <p>Transaction ID: ${captureData.purchase_units[0].payments.captures[0].id}</p>
                <p>Status: ${captureData.status}</p>
                <p><strong>A confirmation email with download link has been sent to your email address.</strong></p>
              </div>
            `;
            videoButton.parentNode.appendChild(resultMessage);
          })
          .catch(function(error) {
            // 隐藏加载状态
            videoOverlay.style.display = 'none';
            console.error('Payment processing error:', error);
            alert('An error occurred while processing your payment. The payment might have been successful, but there was an issue updating our records. Please contact support with your PayPal transaction ID.');
          });
        },
        
        // 支付取消
        onCancel: function(data) {
          // 隐藏加载状态
          videoOverlay.style.display = 'none';
          console.log('Payment cancelled');
        },
        
        // 支付错误
        onError: function(err) {
          // 隐藏加载状态
          videoOverlay.style.display = 'none';
          console.error('PayPal error:', err);
          alert('An error occurred with your payment. Please try again.');
        }
      }).render('#paypal-button-video');
    }
    
    // 初始化PayPal按钮 - 图片包购买
    if (document.getElementById('paypal-button-photo')) {
      const photoButton = document.getElementById('paypal-button-photo');
      const productId = photoButton.getAttribute('data-product-id');
      const isPhotoPack = true;
      
      // 添加加载覆盖层
      const photoOverlay = document.createElement('div');
      photoOverlay.className = 'loading-overlay';
      photoOverlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
      photoOverlay.style.display = 'none';
      photoButton.parentNode.appendChild(photoOverlay);
      
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
          photoOverlay.style.display = 'flex';
        },
        
        // 创建订单
        createOrder: function(data, actions) {
          return fetch('/paypal_api.php?action=create_order', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // 确保发送cookie和session信息
            body: JSON.stringify({
              product_id: productId,
              is_photo_pack: isPhotoPack
            })
          })
          .then(function(response) {
            return response.json();
          })
          .then(function(orderData) {
            if (orderData.error) {
              // 隐藏加载状态
              photoOverlay.style.display = 'none';
              console.error('Error creating order:', orderData.error);
              alert('Error creating order: ' + orderData.error);
              return;
            }
            return orderData.id;
          })
          .catch(function(error) {
            // 隐藏加载状态
            photoOverlay.style.display = 'none';
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
          });
        },
        
        // 支付完成
        onApprove: function(data, actions) {
          return fetch('/paypal_api.php?action=capture_payment', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // 确保发送cookie和session信息
            body: JSON.stringify({
              order_id: data.orderID
            })
          })
          .then(function(response) {
            // 检查响应状态
            if (!response.ok) {
              // 隐藏加载状态
              photoOverlay.style.display = 'none';
              throw new Error('Network response was not ok');
            }
            
            // 尝试解析JSON响应
            return response.text().then(function(text) {
              try {
                return JSON.parse(text);
              } catch (e) {
                // 隐藏加载状态
                photoOverlay.style.display = 'none';
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid server response');
              }
            });
          })
          .then(function(captureData) {
            // 隐藏加载状态
            photoOverlay.style.display = 'none';
            
            if (captureData.error) {
              console.error('Error capturing payment:', captureData.error);
              
              // 检查是否是邮件发送失败
              if (captureData.email_failed || (typeof captureData.error === 'string' && captureData.error.includes('Failed to send purchase confirmation email'))) {
                alert('Email sending failed. Payment was not completed. Please try again later.');
              } else {
                alert('Error processing your payment: ' + captureData.error);
              }
              return;
            }
            
            // 支付成功
            const resultMessage = document.createElement('div');
            resultMessage.innerHTML = `
              <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-top: 10px; border-radius: 5px;">
                <h4>Payment Successful!</h4>
                <p>Order ID: ${captureData.id}</p>
                <p>Transaction ID: ${captureData.purchase_units[0].payments.captures[0].id}</p>
                <p>Status: ${captureData.status}</p>
                <p><strong>A confirmation email with download link has been sent to your email address.</strong></p>
              </div>
            `;
            photoButton.parentNode.appendChild(resultMessage);
          })
          .catch(function(error) {
            // 隐藏加载状态
            photoOverlay.style.display = 'none';
            console.error('Payment processing error:', error);
            alert('An error occurred while processing your payment. The payment might have been successful, but there was an issue updating our records. Please contact support with your PayPal transaction ID.');
          });
        },
        
        // 支付取消
        onCancel: function(data) {
          // 隐藏加载状态
          photoOverlay.style.display = 'none';
          console.log('Payment cancelled');
        },
        
        // 支付错误
        onError: function(err) {
          // 隐藏加载状态
          photoOverlay.style.display = 'none';
          console.error('PayPal error:', err);
          alert('An error occurred with your payment. Please try again.');
        }
      }).render('#paypal-button-photo');
    }
    
    // 检查URL参数是否有支付结果
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'true') {
      alert('Payment successful! Thank you for your purchase. A confirmation email with download link has been sent to your email address.');
    } else if (urlParams.get('cancel') === 'true') {
      alert('Payment was cancelled.');
    }
  </script>
  
  <!-- 余额支付处理脚本 -->
  <script>
    // 获取用户余额
    const userBalance = <?php echo isset($userBalance) ? json_encode($userBalance) : 0; ?>;
    
    // 为所有余额支付按钮添加点击事件
    document.querySelectorAll('.balance-pay-btn').forEach(button => {
      button.addEventListener('click', function() {
        const productId = this.getAttribute('data-product-id');
        const isPhotoPack = this.getAttribute('data-is-photo-pack') === 'true';
        const price = parseFloat(this.getAttribute('data-price'));
        
        // 检查余额是否足够
        if (userBalance < price) {
          alert('Insufficient balance. Your balance: $' + userBalance.toFixed(2) + ', Price: $' + price.toFixed(2));
          return;
        }
        
        // 确认购买
        if (confirm('Do you want to pay $' + price.toFixed(2) + ' using your account balance?')) {
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
          fetch('balance_payment_api.php?action=process_payment', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              product_id: productId,
              is_photo_pack: isPhotoPack ? true : false  // 使用布尔值，服务器端会正确处理
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
              alert('Payment successful! Order ID: ' + data.order_id + '\n\nA confirmation email with download link has been sent to your email address.');
              
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
            alert('An error occurred while processing your payment. Please try again.');
            
            // 恢复按钮状态
            this.disabled = false;
            this.innerHTML = 'Pay with Balance';
          });
        }
      });
    });
  </script>
  
  <!-- Google Pay 实现 -->
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
      console.log('Creating Google Payments client');
      const paymentsClient = new google.payments.api.PaymentsClient({
        environment: '<?php echo env('PAYPAL_SANDBOX', true) ? 'TEST' : 'PRODUCTION'; ?>',
        paymentDataCallbacks: {
          onPaymentAuthorized: onPaymentAuthorized
        }
      });
      
      return paymentsClient;
    }
    
    // 支付授权处理函数 - 完全重写
    function onPaymentAuthorized(paymentData) {
      return new Promise(function(resolve, reject) {
        console.log('Payment authorized callback triggered with data:', JSON.stringify(paymentData));
        
        // 获取URL参数
        const urlParams = new URLSearchParams(window.location.search);
        const productId = urlParams.get('id');
        
        // 判断是否为图片包
        const isPhotoPack = document.querySelector('.balance-pay-btn[data-is-photo-pack="true"]') ? true : false;
        
        // 获取价格信息
        const priceInfo = getGoogleTransactionInfo(productId, isPhotoPack);
        console.log('Price info for payment:', priceInfo);
        
        // 创建订单
        fetch('/paypal_api.php?action=create_order', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            product_id: productId,
            is_photo_pack: isPhotoPack,
            amount: priceInfo.totalPrice // 明确传递金额
          })
        })
        .then(function(response) {
          return response.json();
        })
        .then(function(orderData) {
          console.log('PayPal order created:', orderData);
          
          if (orderData.error) {
            console.error('Error creating PayPal order:', orderData.error);
            resolve({
              transactionState: 'ERROR',
              error: {
                intent: 'PAYMENT_AUTHORIZATION',
                message: 'Failed to create order',
                reason: 'PAYMENT_DATA_INVALID'
              }
            });
            return;
          }
          
          // 构建确认参数
          const confirmParams = {
            orderId: orderData.id,
            paymentMethodData: paymentData.paymentMethodData
          };
          
          // 如果有账单地址，添加到确认参数
          if (paymentData.paymentMethodData && 
              paymentData.paymentMethodData.info && 
              paymentData.paymentMethodData.info.billingAddress) {
            confirmParams.billingAddress = paymentData.paymentMethodData.info.billingAddress;
          }
          
          // 如果有送货地址，添加到确认参数
          if (paymentData.shippingAddress) {
            confirmParams.shippingAddress = paymentData.shippingAddress;
          }
          
          // 如果有电子邮件，添加到确认参数
          if (paymentData.email) {
            confirmParams.email = paymentData.email;
          }
          
          console.log('Confirming order with params:', JSON.stringify(confirmParams));
          
          // 确认订单
          return paypal.Googlepay().confirmOrder(confirmParams);
        })
        .then(function(result) {
          console.log('PayPal confirmOrder success:', result);
          
          // 前端确认成功后，调用后端捕获订单，确保完成扣款并写入购买记录
          return fetch('/paypal_api.php?action=capture_payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ order_id: result.id })
          })
          .then(function(resp) { return resp.json(); })
          .then(function(capture) {
            console.log('Capture response:', capture);
            if (capture && capture.status === 'COMPLETED') {
          // 返回成功
              resolve({ transactionState: 'SUCCESS' });
          
          // 显示成功消息
          const containers = document.querySelectorAll('.googlepay-button-container');
          if (containers.length > 0) {
            const container = containers[0];
            const resultMessage = document.createElement('div');
            resultMessage.innerHTML = `
              <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-top: 10px; border-radius: 5px;">
                <h4>Payment Successful!</h4>
                    <p>Order ID: ${capture.id || result.id}</p>
                    <p>Status: ${capture.status}</p>
                <p><strong>A confirmation email with download link has been sent to your email address.</strong></p>
              </div>
            `;
            container.parentNode.appendChild(resultMessage);
            
            // 1.5秒后刷新页面
                setTimeout(() => { window.location.reload(); }, 1500);
          }
            } else if (capture && capture.error && capture.email_failed) {
              console.error('Email sending failed:', capture);
              resolve({
                transactionState: 'ERROR',
                error: {
                  intent: 'PAYMENT_AUTHORIZATION',
                  message: 'Email sending failed',
                  reason: 'EMAIL_DELIVERY_FAILED'
                }
              });
              alert('Email sending failed. Payment was not completed. Please try again later.');
            } else {
              console.error('Capture failed:', capture);
              resolve({
                transactionState: 'ERROR',
                error: {
                  intent: 'PAYMENT_AUTHORIZATION',
                  message: 'Capture failed',
                  reason: 'PAYMENT_DATA_INVALID'
                }
              });
              alert('Payment not completed. Please try again later or use another payment method.');
            }
          });
        })
        .catch(function(error) {
          console.error('Error in payment processing:', error);
          
          // 返回错误
          resolve({
            transactionState: 'ERROR',
            error: {
              intent: 'PAYMENT_AUTHORIZATION',
              message: error.message || 'Payment failed',
              reason: 'PAYMENT_DATA_INVALID'
            }
          });
        });
      });
    }
    
    // 创建PayPal订单
    function createPayPalOrder() {
      // 获取URL参数
      const urlParams = new URLSearchParams(window.location.search);
      const productId = urlParams.get('id');
      
      // 判断是否为图片包
      const isPhotoPack = document.querySelector('.balance-pay-btn[data-is-photo-pack="true"]') ? true : false;
      
      // 获取价格信息
      const priceInfo = getGoogleTransactionInfo(productId, isPhotoPack);
      console.log('Price info for order creation:', priceInfo);
      
      // 创建订单
      return fetch('/paypal_api.php?action=create_order', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          product_id: productId,
          is_photo_pack: isPhotoPack,
          amount: priceInfo.totalPrice // 明确传递金额
        })
      })
      .then(function(response) {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(function(data) {
        console.log('PayPal order creation response:', data);
        if (data.error) {
          throw new Error(data.error);
        }
        return data.id;
      })
      .catch(function(error) {
        console.error('Error creating PayPal order:', error);
        throw error;
      });
    }
    
    // 确认PayPal订单
    function confirmPayPalOrder(orderId, paymentData) {
      console.log('Confirming PayPal order with ID:', orderId);
      console.log('Payment data for confirmation:', JSON.stringify(paymentData));
      
      // 构建确认参数
      const confirmParams = {
        orderId: orderId,
        paymentMethodData: paymentData.paymentMethodData
      };
      
      // 如果有账单地址，添加到确认参数
      if (paymentData.paymentMethodData && 
          paymentData.paymentMethodData.info && 
          paymentData.paymentMethodData.info.billingAddress) {
        confirmParams.billingAddress = paymentData.paymentMethodData.info.billingAddress;
      }
      
      // 如果有送货地址，添加到确认参数
      if (paymentData.shippingAddress) {
        confirmParams.shippingAddress = paymentData.shippingAddress;
      }
      
      // 如果有电子邮件，添加到确认参数
      if (paymentData.email) {
        confirmParams.email = paymentData.email;
      }
      
      console.log('Final confirm params:', JSON.stringify(confirmParams));
      
      // 调用PayPal的confirmOrder API
      return paypal.Googlepay().confirmOrder(confirmParams)
        .then(function(result) {
          console.log('PayPal confirmOrder success:', result);
          return result;
        })
        .catch(function(error) {
          console.error('PayPal confirmOrder error:', error);
          throw error;
        });
    }
    
    // 显示成功消息
    function showSuccessMessage(result) {
      // 查找容器
      const containers = document.querySelectorAll('.googlepay-button-container');
      if (containers.length > 0) {
        const container = containers[0];
        const resultMessage = document.createElement('div');
        resultMessage.innerHTML = `
          <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-top: 10px; border-radius: 5px;">
            <h4>Payment Successful!</h4>
            <p>Order ID: ${result.id}</p>
            <p>Status: ${result.status}</p>
            <p><strong>A confirmation email with download link has been sent to your email address.</strong></p>
          </div>
        `;
        container.parentNode.appendChild(resultMessage);
        
        // 1.5秒后刷新页面
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      }
    }
    
    // 检查设备是否支持Google Pay的请求对象
    const isReadyToPayRequest = Object.assign({}, baseRequest);
    isReadyToPayRequest.allowedPaymentMethods = [baseCardPaymentMethod];
    
    /**
     * 当Google Pay JavaScript加载完成时调用
     */
    function onGooglePayLoaded() {
      console.log('Google Pay JavaScript loaded');
      const paymentsClient = getGooglePaymentsClient();
      
      // 检查设备是否支持Google Pay
      paymentsClient.isReadyToPay(isReadyToPayRequest)
        .then(function(response) {
          console.log('Google Pay isReadyToPay response:', response);
          if (response.result) {
            // 设备支持Google Pay，添加按钮
            console.log('Google Pay is available, adding buttons');
            
            // 添加视频购买的Google Pay按钮
            if (document.getElementById('googlepay-button-container-video')) {
              addGooglePayButton('googlepay-button-container-video', false);
            }
            
            // 添加图片包购买的Google Pay按钮
            if (document.getElementById('googlepay-button-container-photo')) {
              addGooglePayButton('googlepay-button-container-photo', true);
            }
          } else {
            console.log('Google Pay is not available on this device/browser');
          }
        })
        .catch(function(err) {
          console.error('Google Pay isReadyToPay error:', err);
        });
    }
    
    /**
     * 添加Google Pay按钮 - 严格按照官方文档实现
     */
    function addGooglePayButton(containerId, isPhotoPack) {
      const container = document.getElementById(containerId);
      if (!container) return;
      
      const productId = container.getAttribute('data-product-id');
      console.log('Adding Google Pay button for product:', productId, 'isPhotoPack:', isPhotoPack);
      
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
          onGooglePaymentButtonClicked(productId, isPhotoPack, overlay);
        },
        allowedPaymentMethods: [baseCardPaymentMethod],
        buttonColor: 'black',
        buttonType: 'buy',
        buttonSizeMode: 'fill'
      });
      
      // 添加按钮到容器
      container.appendChild(button);
    }
    
    /**
     * 获取交易信息
     */
    function getGoogleTransactionInfo(productId, isPhotoPack) {
      console.log('Getting transaction info for product:', productId, 'isPhotoPack:', isPhotoPack);
      
      // 获取产品价格
      let price = '0.00';
      
      if (isPhotoPack) {
        // 获取图片包价格
        const photoElements = document.querySelectorAll('.card');
        for (const element of photoElements) {
          if (element.textContent.includes('Photo Pack') || element.textContent.includes('图片包')) {
            const priceElement = element.querySelector('.price');
            if (priceElement) {
              // 移除价格中的美元符号和其他非数字字符
              const rawPrice = priceElement.textContent.replace(/[^\d.]/g, '');
              if (rawPrice) {
                price = rawPrice;
                console.log('Found photo pack price:', price);
              }
            }
          }
        }
      } else {
        // 获取视频价格
        const videoElements = document.querySelectorAll('.card');
        for (const element of videoElements) {
          if (element.textContent.includes('Buy Video') || element.textContent.includes('视频')) {
            const priceElement = element.querySelector('.price');
            if (priceElement) {
              // 移除价格中的美元符号和其他非数字字符
              const rawPrice = priceElement.textContent.replace(/[^\d.]/g, '');
              if (rawPrice) {
                price = rawPrice;
                console.log('Found video price:', price);
              }
            }
          }
        }
      }
      
      // 如果无法从DOM获取价格，尝试从balance-pay-btn属性获取
      if (price === '0.00') {
        if (isPhotoPack) {
          const photoButton = document.querySelector('.balance-pay-btn[data-is-photo-pack="true"][data-product-id="' + productId + '"]');
          if (photoButton) {
            price = photoButton.getAttribute('data-price');
            console.log('Found photo pack price from button:', price);
          }
        } else {
          const videoButton = document.querySelector('.balance-pay-btn[data-is-photo-pack="false"][data-product-id="' + productId + '"]');
          if (videoButton) {
            price = videoButton.getAttribute('data-price');
            console.log('Found video price from button:', price);
          }
        }
      }
      
      // 确保价格是一个有效的数字字符串
      if (!price || isNaN(parseFloat(price))) {
        price = isPhotoPack ? '27.00' : '59.00'; // 默认价格
        console.log('Using default price:', price);
      }
      
      console.log('Final price for Google Pay:', price);
      
      return {
        currencyCode: 'USD',
        totalPriceStatus: 'FINAL',
        totalPrice: price
      };
    }
    
    /**
      * 获取Google Pay支付数据请求 - 严格按照官方文档实现
      */
     async function getGooglePaymentDataRequest(productId, isPhotoPack) {
       try {
         // 获取PayPal的Google Pay配置
         const googlePayConfig = await paypal.Googlepay().config();
         console.log('PayPal Google Pay config:', googlePayConfig);
         
         // 构建支付请求对象
         const paymentDataRequest = Object.assign({}, baseRequest);
         
         // 设置允许的支付方式
         paymentDataRequest.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods;
         
         // 设置交易信息
         paymentDataRequest.transactionInfo = getGoogleTransactionInfo(productId, isPhotoPack);
         
         // 设置商家信息
         paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;
         
         // 设置回调意图
         paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
         
         console.log('Final payment data request:', paymentDataRequest);
         return paymentDataRequest;
       } catch (error) {
         console.error('Error getting Google Pay payment data request:', error);
         throw error;
       }
     }
     
     /**
      * 获取Google Pay交易信息 - 严格按照官方文档实现
      */
     function getGoogleTransactionInfo(productId, isPhotoPack) {
       // 获取产品价格
       let price = '0.00';
       
       // 尝试从DOM元素获取价格
       try {
         if (isPhotoPack) {
           // 尝试从图片包按钮获取价格
           const photoPriceButton = document.querySelector('.balance-pay-btn[data-is-photo-pack="true"]');
           if (photoPriceButton) {
             price = photoPriceButton.getAttribute('data-price');
             console.log('Found photo pack price from button:', price);
           } else {
             // 尝试从页面查找图片包价格
             const photoCards = document.querySelectorAll('.card');
             for (const card of photoCards) {
               if (card.textContent.includes('Photo Pack') || card.textContent.includes('图片包')) {
                 const priceElement = card.querySelector('.price');
                 if (priceElement) {
                   price = priceElement.textContent.replace(/[^0-9.]/g, '');
                   console.log('Found photo pack price from card:', price);
                   break;
                 }
               }
             }
           }
         } else {
           // 尝试从视频按钮获取价格
           const videoPriceButton = document.querySelector('.balance-pay-btn[data-is-photo-pack="false"]');
           if (videoPriceButton) {
             price = videoPriceButton.getAttribute('data-price');
             console.log('Found video price from button:', price);
           } else {
             // 尝试从页面查找视频价格
             const videoCards = document.querySelectorAll('.card');
             for (const card of videoCards) {
               if (card.textContent.includes('Buy Video') || card.textContent.includes('视频')) {
                 const priceElement = card.querySelector('.price');
                 if (priceElement) {
                   price = priceElement.textContent.replace(/[^0-9.]/g, '');
                   console.log('Found video price from card:', price);
                   break;
                 }
               }
             }
           }
         }
       } catch (error) {
         console.error('Error getting price from DOM:', error);
       }
       
       // 确保价格是一个有效的数字
       if (!price || isNaN(parseFloat(price)) || parseFloat(price) <= 0) {
         price = isPhotoPack ? '1.00' : '1.00';  // 使用1美元作为测试金额
         console.log('Using default test price:', price);
       }
       
       console.log('Final price for transaction:', price);
       
       // 返回交易信息
       return {
         currencyCode: 'USD',
         totalPriceStatus: 'FINAL',
         totalPrice: price.toString()
       };
     }
    
    /**
     * 处理Google Pay按钮点击 - 严格按照官方文档实现
     */
    async function onGooglePaymentButtonClicked(productId, isPhotoPack, overlay) {
      try {
        console.log('Google Pay button clicked for product:', productId, 'isPhotoPack:', isPhotoPack);
        
        // 获取Google Pay支付数据请求
        let paymentDataRequest;
        try {
          paymentDataRequest = await getGooglePaymentDataRequest(productId, isPhotoPack);
          console.log('Payment data request:', paymentDataRequest);
        } catch (err) {
          console.error('Error getting payment data request:', err);
          overlay.style.display = 'none';
          alert('无法初始化Google Pay。请稍后再试或使用其他支付方式。');
          return;
        }
        
        // 加载Google Pay支付表单
        const paymentsClient = getGooglePaymentsClient();
        
        try {
          // Google Pay支付处理将在onPaymentAuthorized回调中完成
          await paymentsClient.loadPaymentData(paymentDataRequest);
          console.log('loadPaymentData completed successfully');
        } catch (err) {
          overlay.style.display = 'none';
          console.error('Google Pay loadPaymentData error:', err);
          
          if (err.statusCode === "CANCELED") {
            console.log('User canceled the payment');
          } else if (err.statusCode === "DEVELOPER_ERROR") {
            console.error('Developer error:', err.statusMessage);
            alert('Google Pay配置错误。请联系网站管理员。');
          } else {
            alert('Google Pay支付失败。请稍后再试。');
          }
        }
      } catch (error) {
        overlay.style.display = 'none';
        console.error('Error in Google Pay flow:', error);
        alert('Google Pay支付过程中发生错误。请稍后再试。');
      }
    }
  </script>

  <!-- Apple Pay 实现 -->
  <script>
    // 添加调试日志
    console.log('Apple Pay script loaded');
    
    // 检查Apple Pay是否可用
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, checking Apple Pay availability');
      
      // 检查设备是否支持Apple Pay
      if (window.ApplePaySession) {
        console.log('ApplePaySession is available');
        
        // 检查设备是否可以进行Apple Pay支付
        if (ApplePaySession.canMakePayments()) {
          console.log('Device can make Apple Pay payments');
          
          // 直接显示Apple Pay按钮
          renderApplePayButtons();
          
          // 检查是否可以使用特定的支付卡进行支付
          const merchantId = '<?php echo env('APPLE_PAY_MERCHANT_ID', 'merchant.com.yourmerchantid'); ?>';
          console.log('Checking for active cards with merchant ID:', merchantId);
          
          ApplePaySession.canMakePaymentsWithActiveCard(merchantId).then(function(canMakePayments) {
            console.log('Can make payments with active card:', canMakePayments);
          });
        } else {
          console.log('Device cannot make Apple Pay payments');
          hideApplePayButtons();
        }
      } else {
        console.log('ApplePaySession is not available on this device/browser');
        hideApplePayButtons();
      }
    });
    
    // 隐藏Apple Pay按钮
    function hideApplePayButtons() {
      document.querySelectorAll('.applepay-button-container').forEach(function(container) {
        container.style.display = 'none';
      });
    }
    
    // 渲染所有Apple Pay按钮
    function renderApplePayButtons() {
      console.log('Rendering Apple Pay buttons');
      
      // 渲染视频的Apple Pay按钮
      const videoButtonContainer = document.getElementById('applepay-button-container-video');
      if (videoButtonContainer) {
        console.log('Found video button container');
        const productId = videoButtonContainer.getAttribute('data-product-id');
        renderApplePayButton(videoButtonContainer, productId, false);
      } else {
        console.log('Video button container not found');
      }
      
      // 渲染图片包的Apple Pay按钮
      const photoButtonContainer = document.getElementById('applepay-button-container-photo');
      if (photoButtonContainer) {
        console.log('Found photo button container');
        const productId = photoButtonContainer.getAttribute('data-product-id');
        renderApplePayButton(photoButtonContainer, productId, true);
      } else {
        console.log('Photo button container not found');
      }
    }
    
    // 渲染单个Apple Pay按钮
    function renderApplePayButton(container, productId, isPhotoPack) {
      console.log('Rendering Apple Pay button for container:', container.id, 'product:', productId, 'isPhotoPack:', isPhotoPack);
      
      try {
        // 检查设备是否支持Apple Pay
        if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
          console.log('Apple Pay is not supported on this device/browser');
          container.style.display = 'none';
          return;
        }
        
        // 使用PayPal的Apple Pay组件创建标准按钮
        paypal.Applepay({
          buttonStyle: {
            type: 'buy',
            color: 'black',
            height: 48
          },
          onClick: function() {
            console.log('Apple Pay button clicked');
            handleApplePayButtonClick(container, productId, isPhotoPack);
          }
        }).render(container.id);
        
        // 确保按钮容器样式正确
        container.style.width = '100%';
        container.style.minHeight = '48px';
        
        console.log('Apple Pay button rendered successfully');
      } catch (error) {
        console.error('Error rendering Apple Pay button:', error);
        // 隐藏容器，避免显示空白区域
        container.style.display = 'none';
      }
    }
    
    // 处理Apple Pay按钮点击
    function handleApplePayButtonClick(container, productId, isPhotoPack) {
      console.log('Handling Apple Pay button click');
      
      // 创建加载覆盖层
      const overlay = document.createElement('div');
      overlay.className = 'loading-overlay';
      overlay.innerHTML = '<div class="spinner"></div><p>Processing payment...</p>';
      overlay.style.display = 'flex';
      container.parentNode.appendChild(overlay);
      
      // 获取价格信息
      const priceInfo = getApplePayPriceInfo(productId, isPhotoPack);
      console.log('Price info:', priceInfo);
      
      try {
        // 创建PayPal订单
        createPayPalOrder(productId, isPhotoPack, priceInfo.totalPrice)
          .then(function(orderId) {
            console.log('PayPal order created with ID:', orderId);
            
            // 创建Apple Pay支付请求
            const paymentRequest = {
              countryCode: 'US',
              currencyCode: priceInfo.currencyCode,
              supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
              merchantCapabilities: ['supports3DS'],
              total: {
                label: '<?php echo htmlspecialchars($product['title'] ?? 'Your Purchase'); ?>',
                type: 'final',
                amount: priceInfo.totalPrice
              }
            };
            
            console.log('Payment request:', paymentRequest);
            
            // 创建Apple Pay会话
            const session = new ApplePaySession(6, paymentRequest);
            
            // 设置验证商家回调
            session.onvalidatemerchant = function(event) {
              console.log('Validate merchant callback triggered:', event);
              
              // 使用PayPal验证商家
              paypal.Applepay().validateMerchant({
                validationUrl: event.validationURL
              }).then(function(merchantSession) {
                console.log('Merchant validation successful');
                session.completeMerchantValidation(merchantSession);
              }).catch(function(error) {
                console.error('Merchant validation failed:', error);
                session.abort();
                overlay.style.display = 'none';
                alert('Apple Pay is not available at this time. Please try another payment method.');
              });
            };
            
            // 设置支付授权回调
            session.onpaymentauthorized = function(event) {
              console.log('Payment authorized callback triggered:', event);
              
              // 使用PayPal确认订单
              paypal.Applepay().confirmOrder({
                orderId: orderId,
                token: event.payment.token,
                billingContact: event.payment.billingContact,
                shippingContact: event.payment.shippingContact
              }).then(function(result) {
                console.log('Order confirmation successful:', result);
                
                // 检查是否存在邮件发送失败的错误
                if (result && result.error && result.email_failed) {
                  console.error('Email sending failed:', result);
                  session.completePayment(ApplePaySession.STATUS_FAILURE);
                  overlay.style.display = 'none';
                  alert('Email sending failed. Payment was not completed. Please try again later.');
                  return;
                }
                
                // 完成Apple Pay会话
                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                
                // 显示成功消息
                showSuccessMessage(container, result);
                
                // 隐藏加载状态
                overlay.style.display = 'none';
              }).catch(function(error) {
                console.error('Order confirmation failed:', error);
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                overlay.style.display = 'none';
                alert('Payment failed. Please try again.');
              });
            };
            
            // 开始Apple Pay会话
            console.log('Beginning Apple Pay session');
            session.begin();
          })
          .catch(function(error) {
            console.error('Error creating PayPal order:', error);
            overlay.style.display = 'none';
            alert('An error occurred. Please try again.');
          });
      } catch (error) {
        console.error('Error in Apple Pay flow:', error);
        overlay.style.display = 'none';
        alert('An error occurred with Apple Pay. Please try another payment method.');
      }
    }
    
    // 创建PayPal订单
    function createPayPalOrder(productId, isPhotoPack, amount) {
      return fetch('/paypal_api.php?action=create_order', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          product_id: productId,
          is_photo_pack: isPhotoPack,
          amount: amount
        })
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(data) {
        if (data.error) {
          throw new Error(data.error);
        }
        return data.id;
      });
    }
    
    // 获取Apple Pay价格信息
    function getApplePayPriceInfo(productId, isPhotoPack) {
      // 获取产品价格
      let price = '0.00';
      
      // 尝试从DOM元素获取价格
      try {
        if (isPhotoPack) {
          // 尝试从图片包按钮获取价格
          const photoPriceButton = document.querySelector('.balance-pay-btn[data-is-photo-pack="true"]');
          if (photoPriceButton) {
            price = photoPriceButton.getAttribute('data-price');
            console.log('Found photo pack price from button:', price);
          } else {
            // 尝试从页面查找图片包价格
            const photoCards = document.querySelectorAll('.card');
            for (const card of photoCards) {
              if (card.textContent.includes('Photo Pack') || card.textContent.includes('图片包')) {
                const priceElement = card.querySelector('.price');
                if (priceElement) {
                  price = priceElement.textContent.replace(/[^0-9.]/g, '');
                  console.log('Found photo pack price from card:', price);
                  break;
                }
              }
            }
          }
        } else {
          // 尝试从视频按钮获取价格
          const videoPriceButton = document.querySelector('.balance-pay-btn[data-is-photo-pack="false"]');
          if (videoPriceButton) {
            price = videoPriceButton.getAttribute('data-price');
            console.log('Found video price from button:', price);
          } else {
            // 尝试从页面查找视频价格
            const videoCards = document.querySelectorAll('.card');
            for (const card of videoCards) {
              if (card.textContent.includes('Buy Video') || card.textContent.includes('视频')) {
                const priceElement = card.querySelector('.price');
                if (priceElement) {
                  price = priceElement.textContent.replace(/[^0-9.]/g, '');
                  console.log('Found video price from card:', price);
                  break;
                }
              }
            }
          }
        }
      } catch (error) {
        console.error('Error getting price from DOM:', error);
      }
      
      // 确保价格是一个有效的数字
      if (!price || isNaN(parseFloat(price)) || parseFloat(price) <= 0) {
        price = isPhotoPack ? '1.00' : '1.00';  // 使用1美元作为测试金额
        console.log('Using default test price:', price);
      }
      
      console.log('Final price for Apple Pay transaction:', price);
      
      // 返回价格信息
      return {
        currencyCode: 'USD',
        totalPriceStatus: 'FINAL',
        totalPrice: price.toString()
      };
    }
    
    // 显示成功消息
    function showSuccessMessage(container, result) {
      const resultMessage = document.createElement('div');
      resultMessage.innerHTML = `
        <div style="background-color: #d4edda; color: #155724; padding: 15px; margin-top: 10px; border-radius: 5px;">
          <h4>Payment Successful!</h4>
          <p>Order ID: ${result.id}</p>
          <p>Status: ${result.status}</p>
          <p><strong>A confirmation email with download link has been sent to your email address.</strong></p>
        </div>
      `;
      container.parentNode.appendChild(resultMessage);
      
      // 1.5秒后刷新页面
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    }
  </script>
  
  <!-- 购物车功能脚本 -->
  <script>
    // 为所有加入购物车按钮添加事件监听器
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
      button.addEventListener('click', function() {
        const productId = this.getAttribute('data-product-id');
        const isPhotoPack = this.getAttribute('data-is-photo-pack') === 'true';
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
          body: `action=add_to_cart&item_type=${itemType}&item_id=${productId}&is_photo_pack=${isPhotoPack ? 1 : 0}&quantity=1`
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
  </script>
  
  <div id="product-images">
      <?php if (!empty($product['video_file'])): ?>
      <video controls width="100%" style="max-height: 500px; background: #000;">
        <source src="uploads/products/<?php echo htmlspecialchars($product['video_file']); ?>" type="video/mp4">
        Your browser does not support the video tag.
      </video>
      <?php endif; ?>
    </div>
    
</body>
</html> 