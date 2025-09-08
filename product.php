<?php
session_start();
require_once 'db_config.php';

// 检查是否有产品ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: main.php');
    exit;
}

$product_id = intval($_GET['id']);
$isLoggedIn = isset($_SESSION['user_id']);

// 获取产品信息
$product = null;
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $product = $row;
    }
    
    mysqli_stmt_close($stmt);
}

// 如果产品不存在，重定向到主页
if (!$product) {
    header('Location: main.php');
    exit;
}

// 更新点击次数
$update_sql = "UPDATE products SET clicks = clicks + 1 WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $update_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?> - v.58hair.net</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        header {
            background-color: #B8B5E1;
            color: white;
            padding: 20px 0;
            text-align: center;
        }
        
        .site-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .site-title a {
            color: white;
            text-decoration: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .product-header {
            padding: 30px 0;
            border-bottom: 2px solid #B8B5E1;
            text-align: center;
        }
        
        .product-title {
            font-size: 32px;
            font-weight: bold;
            color: #4A4A4A;
            margin-bottom: 10px;
        }
        
        .product-subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .product-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }
        
        .meta-item {
            text-align: center;
        }
        
        .meta-label {
            font-size: 14px;
            color: #888;
            margin-bottom: 5px;
        }
        
        .meta-value {
            font-size: 16px;
            font-weight: bold;
            color: #4A4A4A;
        }
        
        .product-images {
            padding: 30px 0;
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        @media (min-width: 768px) {
            .images-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }
        
        .image-item {
            display: flex;
            flex-direction: column;
        }
        
        .image-label {
            font-size: 16px;
            font-weight: bold;
            color: #4A4A4A;
            margin-bottom: 10px;
        }
        
        .product-image {
            width: 100%;
            max-width: 400px;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .product-image:hover {
            transform: scale(1.05);
        }
        
        .member-only {
            background-color: #B8B5E1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        
        .back-button {
            text-align: center;
            padding: 30px 0;
        }
        
        .back-button a {
            display: inline-block;
            padding: 12px 30px;
            background-color: #B8B5E1;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .back-button a:hover {
            background-color: #a29bd8;
        }
        
        /* 全屏图片查看器 */
        .fullscreen-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            cursor: pointer;
        }
        
        .fullscreen-image {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .close-button {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <header>
        <div class="site-title">
            <a href="index.php">v.58hair.net</a><br>
            Welcome to this website
        </div>
    </header>
    
    <div class="container">
        <div class="product-header">
            <h1 class="product-title"><?php echo $product['id']; ?>. <?php echo htmlspecialchars($product['title']); ?></h1>
            <?php if (!empty($product['subtitle'])): ?>
            <p class="product-subtitle"><?php echo htmlspecialchars($product['subtitle']); ?></p>
            <?php endif; ?>
            
            <div class="product-meta">
                <div class="meta-item">
                    <div class="meta-label">类别</div>
                    <div class="meta-value"><?php echo htmlspecialchars($product['category_name']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">价格</div>
                    <div class="meta-value">¥<?php echo number_format($product['price'], 2); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">点击次数</div>
                    <div class="meta-value"><?php echo $product['clicks']; ?></div>
                </div>
            </div>
        </div>
        
        <div class="product-images">
            <div class="images-grid">
                <!-- 主图片 (游客可见) -->
                <?php if (!empty($product['image'])): ?>
                <div class="image-item">
                    <div class="image-label">主图片</div>
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['title']); ?>" 
                         class="product-image" 
                         onclick="showFullImage(this.src)">
                </div>
                <?php endif; ?>
                
                <!-- 会员图片 -->
                <?php if ($isLoggedIn): ?>
                    <?php for ($i = 2; $i <= 6; $i++): ?>
                        <?php $imageField = 'image' . $i; ?>
                        <div class="image-item">
                            <div class="image-label">会员图片 <?php echo $i; ?></div>
                            <?php if (!empty($product[$imageField])): ?>
                            <img src="<?php echo htmlspecialchars($product[$imageField]); ?>" 
                                 alt="<?php echo htmlspecialchars($product['title']); ?> - Image <?php echo $i; ?>" 
                                 class="product-image" 
                                 onclick="showFullImage(this.src)">
                            <?php else: ?>
                            <div class="product-image" style="background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
                                暂无图片
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                <?php else: ?>
                    <?php for ($i = 2; $i <= 6; $i++): ?>
                    <div class="image-item">
                        <div class="image-label">会员图片 <?php echo $i; ?></div>
                        <div class="product-image member-only">
                            会员专享内容<br>请登录查看
                        </div>
                    </div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="back-button">
            <a href="main.php">返回产品列表</a>
        </div>
    </div>
    
    <!-- 全屏图片查看器 -->
    <div class="fullscreen-overlay" id="fullscreenOverlay" onclick="hideFullImage()">
        <span class="close-button" onclick="hideFullImage()">&times;</span>
        <img class="fullscreen-image" id="fullscreenImage" src="" alt="">
    </div>
    
    <script>
        function showFullImage(src) {
            document.getElementById('fullscreenImage').src = src;
            document.getElementById('fullscreenOverlay').style.display = 'block';
        }
        
        function hideFullImage() {
            document.getElementById('fullscreenOverlay').style.display = 'none';
        }
        
        // 按ESC键关闭全屏图片
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideFullImage();
            }
        });
    </script>
</body>
</html>
