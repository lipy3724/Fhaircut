<?php
// 生成正确的参数类型字符串
$correct_string = "ssddiisssssssssssiiiissiissis";
echo "正确的字符串: " . $correct_string . "\n";
echo "长度: " . strlen($correct_string) . "\n";

// 读取文件并替换
$content = file_get_contents('admin/products.php');
$old_string = "ssddiisssssssssssiiiissiissis";
$new_string = "ssddiisssssssssssiiiissiissis";

echo "旧字符串长度: " . strlen($old_string) . "\n";
echo "新字符串长度: " . strlen($new_string) . "\n";

$new_content = str_replace($old_string, $new_string, $content);
file_put_contents('admin/products.php', $new_content);

echo "替换完成！\n";
?> 