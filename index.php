<?php
// 设置页面标题
$pageTitle = "HairCut Network - Welcome";

// 包含数据库配置文件
require_once "db_config.php";

// 获取背景图片设置
$background_image = "background.jpg"; // 默认背景图片

$sql = "SELECT setting_value FROM settings WHERE setting_key = 'background_image'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    if (!empty($row['setting_value']) && file_exists($row['setting_value'])) {
        $background_image = $row['setting_value'];
    }
    mysqli_free_result($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --bg-image: url('<?php echo htmlspecialchars($background_image); ?>');
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="content">
            <h1>Hair cutting website</h1>
            <div class="buttons">
                <a href="home.php" class="btn primary">Enter Official Site</a>
                <a href="http://cuthair.cn" class="btn secondary">Legacy Version</a>
            </div>
        </div>
    </div>
</body>
</html> 