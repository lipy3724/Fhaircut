<?php
// 处理分类管理操作
$success_message = '';
$error_message = '';
$redirect_script = '';

// 从URL参数获取表单操作类型和分类ID
$formAction = $_GET['action'] ?? '';
$categoryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 处理添加或编辑分类提交
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['submit_category'])) {
    $category_name = trim($_POST['category_name']);
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    // 调试信息
    error_log("POST提交 - category_id: " . $category_id . ", category_name: " . $category_name);
    error_log("GET参数 - action: " . ($_GET['action'] ?? 'null') . ", id: " . ($_GET['id'] ?? 'null'));
    error_log("POST数据: " . print_r($_POST, true));
    error_log("GET数据: " . print_r($_GET, true));

    // 验证分类名称
    if (empty($category_name)) {
        $error_message = "分类名称不能为空";
    } else {
        // 检查是否是编辑操作
        if ($category_id > 0) {
            // 编辑现有分类
            
            // 检查名称是否与其他分类重复
            $check_sql = "SELECT id FROM categories WHERE name = ? AND id != ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "si", $category_name, $category_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error_message = "分类名称 '$category_name' 已存在";
            } else {
                mysqli_stmt_close($check_stmt);
                
                // 直接执行更新SQL
                $sql = "UPDATE categories SET name = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $category_name, $category_id);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "分类更新成功";
                    $redirect_script = "<script>window.location.href = 'admin.php?page=categories&success=updated';</script>";
                } else {
                    $error_message = "更新分类时出错: " . mysqli_error($conn);
                }
                
                mysqli_stmt_close($stmt);
            }
        } else {
            // 添加新分类
            // 检查分类名称是否已存在
            $check_sql = "SELECT id FROM categories WHERE name = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $category_name);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error_message = "分类名称 '$category_name' 已存在";
            } else {
                mysqli_stmt_close($check_stmt);

                // 获取下一个可用的ID
                $next_id_sql = "SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM categories";
                $next_id_result = mysqli_query($conn, $next_id_sql);
                $next_id_row = mysqli_fetch_assoc($next_id_result);
                $next_id = $next_id_row['next_id'];

                // 插入新分类，指定ID
                $sql = "INSERT INTO categories (id, name) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "is", $next_id, $category_name);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "分类添加成功";
                    $redirect_script = "<script>window.location.href = 'admin.php?page=categories&success=added';</script>";
                } else {
                    $error_message = "添加分类时出错: " . mysqli_error($conn);
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

// 处理删除分类
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $category_id = intval($_GET['id']);

    // 首先将该分类下的产品移动到未分类（NULL）
    $update_products_sql = "UPDATE products SET category_id = NULL WHERE category_id = ?";
    if ($update_stmt = mysqli_prepare($conn, $update_products_sql)) {
        mysqli_stmt_bind_param($update_stmt, "i", $category_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }

    // 然后删除分类
    $delete_sql = "DELETE FROM categories WHERE id = ?";
    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
        mysqli_stmt_bind_param($delete_stmt, "i", $category_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            // 重新整理ID，使其连续
            reorderCategoryIds($conn);

            $success_message = "分类删除成功";
            // 使用JavaScript重定向
            $redirect_script = "<script>window.location.href = 'admin.php?page=categories&success=deleted';</script>";
        } else {
            $error_message = "删除分类时出错: " . mysqli_error($conn);
        }

        mysqli_stmt_close($delete_stmt);
    }
}

// 重新整理分类ID的函数
function reorderCategoryIds($conn) {
    // 获取所有分类，按名称排序
    $sql = "SELECT id, name FROM categories ORDER BY name ASC";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
        mysqli_free_result($result);

        // 开始事务
        mysqli_autocommit($conn, false);

        try {
            // 创建临时表
            $sql = "CREATE TEMPORARY TABLE temp_categories (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                old_id INT(11)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            mysqli_query($conn, $sql);

            // 插入数据到临时表，自动生成新的连续ID
            foreach ($categories as $index => $category) {
                $new_id = $index + 1;
                $sql = "INSERT INTO temp_categories (id, name, old_id) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "isi", $new_id, $category['name'], $category['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            // 更新products表中的category_id引用
            $sql = "UPDATE products p
                    INNER JOIN temp_categories t ON p.category_id = t.old_id
                    SET p.category_id = t.id";
            mysqli_query($conn, $sql);

            // 清空原分类表
            mysqli_query($conn, "DELETE FROM categories");

            // 从临时表复制数据回原表
            $sql = "INSERT INTO categories (id, name) SELECT id, name FROM temp_categories ORDER BY id";
            mysqli_query($conn, $sql);

            // 删除临时表
            mysqli_query($conn, "DROP TEMPORARY TABLE temp_categories");

            // 提交事务
            mysqli_commit($conn);
            mysqli_autocommit($conn, true);

        } catch (Exception $e) {
            // 回滚事务
            mysqli_rollback($conn);
            mysqli_autocommit($conn, true);
            error_log("重新整理分类ID失败: " . $e->getMessage());
        }
    }
}

$formTitle = '';
$formName = '';
$formSubmitLabel = '';

// 设置表单标题和按钮文本
if ($formAction === 'add') {
    $formTitle = '添加新分类';
    $formSubmitLabel = '添加分类';
} else if ($formAction === 'edit' && $categoryId) {
    $formTitle = '编辑分类';
    $formSubmitLabel = '更新分类';
    
    // 查询要编辑的分类信息
    $sql = "SELECT name FROM categories WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $categoryId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $formName = $row['name'];
        } else {
            // 如果找不到分类，使用JavaScript重定向
            $redirect_script = "<script>window.location.href = 'admin.php?page=categories&error=not_found';</script>";
        }
        
        mysqli_stmt_close($stmt);
    }
}

// 获取所有分类及其产品数量
$categories = [];

// 处理搜索功能
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

$sql = "SELECT c.id, c.name, COUNT(p.id) as products_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id";
        
// 添加搜索条件
if (!empty($search_term)) {
    $search_term_escaped = mysqli_real_escape_string($conn, $search_term);
    $sql .= " WHERE c.name LIKE '%{$search_term_escaped}%'";
}

$sql .= " GROUP BY c.id
        ORDER BY c.id ASC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_free_result($result);
}

// 显示成功或错误消息
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = "分类添加成功";
            break;
        case 'updated':
            $success_message = "分类更新成功";
            break;
        case 'deleted':
            $success_message = "分类删除成功";
            break;
        }
    }

if (isset($_GET['error']) && $_GET['error'] == 'not_found') {
    $error_message = "未找到指定的分类";
}

// 输出JavaScript重定向脚本
echo $redirect_script;
?>

<div class="admin-content">
<h2>分类管理</h2>

    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>    <?php if ($formAction === 'add' || $formAction === 'edit'): ?>
<!-- 添加/编辑分类表单 -->
    <div class="category-form">
    <h3><?php echo $formTitle; ?></h3>

        <!-- 表单提交到categories页面 -->
        <form method="post" action="admin.php?page=categories<?php echo ($formAction === 'edit' && $categoryId) ? '&action=edit&id=' . $categoryId : ''; ?>">
            <?php if ($formAction === 'edit' && $categoryId): ?>
            <!-- 隐藏字段传递category_id -->
            <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">
            <?php endif; ?>
            
    <div class="form-group">
        <label for="category_name">分类名称:</label>
                <input type="text" id="category_name" name="category_name" value="<?php echo htmlspecialchars($formName); ?>" required>
    </div>
            
                <div class="form-buttons">
                <button type="submit" name="submit_category" value="1" class="btn-submit" style="background-color: #ffccd5 !important; color: #333 !important;"><?php echo $formSubmitLabel; ?></button>
                <a href="admin.php?page=categories" class="btn-cancel">取消</a>
            </div>
        </form>
    </div>
<?php else: ?>
<!-- 分类列表 -->
    <div class="action-buttons">
        <a href="admin.php?page=categories&action=add" class="add-button">添加新分类</a>
</div>

<!-- 添加搜索表单 -->
<div class="search-container">
    <form action="admin.php" method="get" class="search-form">
        <input type="hidden" name="page" value="categories">
        <div class="search-inputs">
            <input type="text" name="search" placeholder="搜索分类名称..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="search-button">搜索</button>
            <?php if (!empty($search_term)): ?>
            <a href="admin.php?page=categories" class="reset-button">重置</a>
            <?php endif; ?>
        </div>
    </form>
</div>

    <table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>名称</th>
            <th>产品数量</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
            <?php if (empty($categories)): ?>
            <tr>
                <td colspan="4" class="no-data">暂无分类数据</td>
            </tr>
            <?php else: ?>
        <?php foreach ($categories as $category): ?>
        <tr>
            <td><?php echo $category['id']; ?></td>
                <td><?php echo htmlspecialchars($category['name']); ?></td>
            <td><?php echo $category['products_count']; ?></td>
                <td class="actions">
                    <a href="admin.php?page=categories&action=edit&id=<?php echo $category['id']; ?>" class="edit-button">编辑</a>
                    <a href="admin.php?page=categories&action=delete&id=<?php echo $category['id']; ?>" class="delete-button" onclick="return confirm('确定要删除这个分类吗？该分类下的所有产品将被移动到未分类。');">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
            <?php endif; ?>
    </tbody>
</table>
<?php endif; ?> 
</div>

<link rel="stylesheet" href="css/data-table.css">
<style>
.data-table th {
    background-color: #ffccd5;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table tbody tr:hover {
    background-color: #fff5f7;
}
.category-form {
    background-color: #fff5f7;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(231, 84, 128, 0.1);
    border: 1px solid #ffccd5;
}

.category-form h3 {
    margin-top: 0;
    color: #e75480;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #f7a4b9;
    border-radius: 4px;
}

.form-group input[type="text"]:focus {
    outline: none;
    border-color: #e75480;
    box-shadow: 0 0 0 2px rgba(231, 84, 128, 0.2);
}

.btn-submit {
    background-color: #ffccd5;
    color: #333;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-submit:hover {
    background-color: #f7a4b9;
}

.btn-cancel {
    background-color: #ffecf0;
    color: #e75480;
    border: 1px solid #f7a4b9;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 10px;
}

.btn-cancel:hover {
    background-color: #ffccd5;
}

.edit-btn, .action-btn {
    background-color: #e75480;
    color: white;
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 5px;
}

.edit-btn:hover, .action-btn:hover {
    background-color: #d64072;
}

.delete-btn {
    background-color: #ff8da1;
    color: white;
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.delete-btn:hover {
    background-color: #ff7c93;
}



.actions {
    white-space: nowrap;
}

.edit-button, .delete-button {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    margin-right: 5px;
    font-size: 14px;
}

.edit-button {
    background-color: #ffccd5;
    color: #333;
}

.delete-button {
    background-color: #ff6b6b;
    color: white;
}

.add-button {
    display: inline-block;
    padding: 8px 15px;
    background-color: #e75480;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    margin-bottom: 20px;
}

.add-button:hover {
    background-color: #d64072;
}

.no-data {
    text-align: center;
    color: #999;
    padding: 20px;
    font-style: italic;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

/* 添加搜索表单样式 */
.search-container {
    margin-bottom: 20px;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}

.search-form {
    display: flex;
    flex-direction: column;
}

.search-inputs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-inputs input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-button {
    background-color: #e75480;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.search-button:hover {
    background-color: #d64072;
}

.reset-button {
    background-color: #f8f9fa;
    color: #6c757d;
    border: 1px solid #ddd;
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
}

.reset-button:hover {
    background-color: #e2e6ea;
}
</style> 