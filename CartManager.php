<?php
/**
 * 购物车管理类
 * 负责购物车的增删改查操作
 */
class CartManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * 添加商品到购物车
     * @param int $userId 用户ID
     * @param string $itemType 商品类型 (photo/video/hair)
     * @param int $itemId 商品ID
     * @param bool $isPhotoPack 是否为图片包（已废弃，保留兼容性）
     * @param int $quantity 数量
     * @return array 结果信息
     */
    public function addToCart($userId, $itemType, $itemId, $isPhotoPack = false, $quantity = 1) {
        try {
            // 验证参数
            if (!in_array($itemType, ['photo', 'video', 'hair'])) {
                return ['success' => false, 'message' => 'Invalid item type'];
            }
            
            if ($quantity <= 0) {
                $quantity = 1;
            }
            
            // 检查商品是否存在
            $itemExists = $this->checkItemExists($itemType, $itemId);
            if (!$itemExists) {
                return ['success' => false, 'message' => 'Item not found'];
            }
            
            // 检查是否已在购物车中
            $existingItem = $this->getCartItem($userId, $itemType, $itemId, $isPhotoPack);
            
            if ($existingItem) {
                // 如果商品已在购物车中，不允许重复添加
                return ['success' => false, 'message' => 'Item already in cart', 'action' => 'duplicate'];
            } else {
                // 添加新商品 - 需要获取价格信息
                $itemPrice = $this->getItemPrice($itemType, $itemId, $isPhotoPack);
                if ($itemPrice === null) {
                    return ['success' => false, 'message' => 'Unable to get item price'];
                }
                
                $sql = "INSERT INTO cart (user_id, item_type, item_id, quantity, price) VALUES (?, ?, ?, 1, ?)";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "isid", $userId, $itemType, $itemId, $itemPrice);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    return ['success' => true, 'message' => 'Item added to cart successfully', 'action' => 'added'];
                } else {
                    mysqli_stmt_close($stmt);
                    return ['success' => false, 'message' => 'Failed to add item to cart'];
                }
            }
        } catch (Exception $e) {
            error_log("CartManager addToCart error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while adding to cart'];
        }
    }
    
    /**
     * 更新购物车商品数量
     * @param int $userId 用户ID
     * @param int $cartItemId 购物车项目ID
     * @param int $quantity 新数量
     * @return array 结果信息
     */
    public function updateQuantity($userId, $cartItemId, $quantity) {
        try {
            if ($quantity <= 0) {
                return $this->removeItems($userId, [$cartItemId]);
            }
            
            $sql = "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "iii", $quantity, $cartItemId, $userId);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                
                if ($affected > 0) {
                    return ['success' => true, 'message' => 'Quantity updated successfully'];
                } else {
                    return ['success' => false, 'message' => 'Cart item not found'];
                }
            } else {
                mysqli_stmt_close($stmt);
                return ['success' => false, 'message' => 'Failed to update quantity'];
            }
        } catch (Exception $e) {
            error_log("CartManager updateQuantity error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating quantity'];
        }
    }
    
    /**
     * 批量删除购物车商品
     * @param int $userId 用户ID
     * @param array $cartItemIds 购物车项目ID数组
     * @return array 结果信息
     */
    public function removeItems($userId, $cartItemIds) {
        try {
            if (empty($cartItemIds)) {
                return ['success' => false, 'message' => 'No items to remove'];
            }
            
            // 创建占位符
            $placeholders = str_repeat('?,', count($cartItemIds) - 1) . '?';
            $sql = "DELETE FROM cart WHERE user_id = ? AND id IN ($placeholders)";
            
            $stmt = mysqli_prepare($this->conn, $sql);
            
            // 绑定参数
            $types = str_repeat('i', count($cartItemIds) + 1);
            $params = array_merge([$userId], $cartItemIds);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                return ['success' => true, 'message' => "$affected items removed from cart"];
            } else {
                mysqli_stmt_close($stmt);
                return ['success' => false, 'message' => 'Failed to remove items'];
            }
        } catch (Exception $e) {
            error_log("CartManager removeItems error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while removing items'];
        }
    }
    
    /**
     * 获取购物车内容
     * @param int $userId 用户ID
     * @return array 购物车商品列表
     */
    public function getCartItems($userId) {
        try {
            // 先检查products和hair表是否有description字段
            $check_product_desc = "SHOW COLUMNS FROM products LIKE 'description'";
            $product_desc_result = mysqli_query($this->conn, $check_product_desc);
            $has_product_desc = mysqli_num_rows($product_desc_result) > 0;
            
            $check_hair_desc = "SHOW COLUMNS FROM hair LIKE 'description'";
            $hair_desc_result = mysqli_query($this->conn, $check_hair_desc);
            $has_hair_desc = mysqli_num_rows($hair_desc_result) > 0;
            
            // 构建SQL查询，根据字段是否存在决定是否包含description
            $product_desc_field = $has_product_desc ? "p.description" : "NULL";
            $hair_desc_field = $has_hair_desc ? "h.description" : "NULL";
            
            $sql = "SELECT 
                        c.id as cart_id,
                        c.item_type,
                        c.item_id,
                        c.quantity,
                        c.price,
                        c.created_at as added_at,
                        CASE 
                            WHEN c.item_type IN ('photo', 'video') THEN p.title
                            WHEN c.item_type = 'hair' THEN h.title
                        END as title,
                        CASE 
                            WHEN c.item_type IN ('photo', 'video') THEN p.image
                            WHEN c.item_type = 'hair' THEN h.image
                        END as image,
                        CASE 
                            WHEN c.item_type IN ('photo', 'video') THEN $product_desc_field
                            WHEN c.item_type = 'hair' THEN $hair_desc_field
                        END as description
                    FROM cart c
                    LEFT JOIN products p ON c.item_type IN ('photo', 'video') AND c.item_id = p.id
                    LEFT JOIN hair h ON c.item_type = 'hair' AND c.item_id = h.id
                    WHERE c.user_id = ?
                    AND (
                        (c.item_type IN ('photo', 'video') AND p.id IS NOT NULL) OR
                        (c.item_type = 'hair' AND h.id IS NOT NULL)
                    )
                    ORDER BY c.created_at DESC";
                    
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $cartItems = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $cartItems[] = $row;
            }
            
            mysqli_stmt_close($stmt);
            return $cartItems;
        } catch (Exception $e) {
            error_log("CartManager getCartItems error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 计算购物车总价
     * @param int $userId 用户ID
     * @return float 总价
     */
    public function calculateTotal($userId) {
        try {
            $sql = "SELECT SUM(c.price) as total 
                    FROM cart c
                    LEFT JOIN products p ON c.item_type IN ('photo', 'video') AND c.item_id = p.id
                    LEFT JOIN hair h ON c.item_type = 'hair' AND c.item_id = h.id
                    WHERE c.user_id = ?
                    AND (
                        (c.item_type IN ('photo', 'video') AND p.id IS NOT NULL) OR
                        (c.item_type = 'hair' AND h.id IS NOT NULL)
                    )";
                    
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            mysqli_stmt_close($stmt);
            return floatval($row['total'] ?? 0);
        } catch (Exception $e) {
            error_log("CartManager calculateTotal error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 计算选中商品的总价
     * @param int $userId 用户ID
     * @param array $cartIds 选中的购物车商品ID数组
     * @return float 选中商品总价
     */
    public function calculateSelectedTotal($userId, $cartIds) {
        try {
            if (empty($cartIds)) {
                return 0;
            }
            
            // 构建占位符
            $placeholders = str_repeat('?,', count($cartIds) - 1) . '?';
            
            $sql = "SELECT SUM(c.price) as total 
                    FROM cart c
                    LEFT JOIN products p ON c.item_type IN ('photo', 'video') AND c.item_id = p.id
                    LEFT JOIN hair h ON c.item_type = 'hair' AND c.item_id = h.id
                    WHERE c.user_id = ? AND c.id IN ($placeholders)
                    AND (
                        (c.item_type IN ('photo', 'video') AND p.id IS NOT NULL) OR
                        (c.item_type = 'hair' AND h.id IS NOT NULL)
                    )";
                    
            $stmt = mysqli_prepare($this->conn, $sql);
            
            // 绑定参数
            $types = 'i' . str_repeat('i', count($cartIds));
            $params = array_merge([$userId], $cartIds);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            mysqli_stmt_close($stmt);
            return floatval($row['total'] ?? 0);
        } catch (Exception $e) {
            error_log("CartManager calculateSelectedTotal error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取购物车商品数量
     * @param int $userId 用户ID
     * @return int 商品数量
     */
    public function getCartCount($userId) {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM cart c
                    LEFT JOIN products p ON c.item_type IN ('photo', 'video') AND c.item_id = p.id
                    LEFT JOIN hair h ON c.item_type = 'hair' AND c.item_id = h.id
                    WHERE c.user_id = ?
                    AND (
                        (c.item_type IN ('photo', 'video') AND p.id IS NOT NULL) OR
                        (c.item_type = 'hair' AND h.id IS NOT NULL)
                    )";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            mysqli_stmt_close($stmt);
            return intval($row['count'] ?? 0);
        } catch (Exception $e) {
            error_log("CartManager getCartCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 清空购物车
     * @param int $userId 用户ID
     * @return array 结果信息
     */
    public function clearCart($userId) {
        try {
            $sql = "DELETE FROM cart WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                return ['success' => true, 'message' => "Cart cleared ($affected items removed)"];
            } else {
                mysqli_stmt_close($stmt);
                return ['success' => false, 'message' => 'Failed to clear cart'];
            }
        } catch (Exception $e) {
            error_log("CartManager clearCart error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while clearing cart'];
        }
    }
    
    /**
     * 清理无效的购物车项目（引用的商品不存在）
     * @param int $userId 用户ID
     * @return array 结果信息
     */
    public function cleanupInvalidItems($userId) {
        try {
            // 查找无效的商品项目
            $sql = "SELECT c.id, c.item_type, c.item_id
                    FROM cart c
                    LEFT JOIN products p ON c.item_type IN ('photo', 'video') AND c.item_id = p.id
                    LEFT JOIN hair h ON c.item_type = 'hair' AND c.item_id = h.id
                    WHERE c.user_id = ?
                    AND (
                        (c.item_type IN ('photo', 'video') AND p.id IS NULL) OR
                        (c.item_type = 'hair' AND h.id IS NULL)
                    )";
                    
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $invalidIds = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $invalidIds[] = $row['id'];
            }
            mysqli_stmt_close($stmt);
            
            if (empty($invalidIds)) {
                return ['success' => true, 'message' => 'No invalid items found'];
            }
            
            // 删除无效项目
            $placeholders = str_repeat('?,', count($invalidIds) - 1) . '?';
            $deleteSql = "DELETE FROM cart WHERE user_id = ? AND id IN ($placeholders)";
            $deleteStmt = mysqli_prepare($this->conn, $deleteSql);
            
            $types = str_repeat('i', count($invalidIds) + 1);
            $params = array_merge([$userId], $invalidIds);
            mysqli_stmt_bind_param($deleteStmt, $types, ...$params);
            
            if (mysqli_stmt_execute($deleteStmt)) {
                $affected = mysqli_stmt_affected_rows($deleteStmt);
                mysqli_stmt_close($deleteStmt);
                return ['success' => true, 'message' => "Cleaned up $affected invalid items"];
            } else {
                mysqli_stmt_close($deleteStmt);
                return ['success' => false, 'message' => 'Failed to cleanup invalid items'];
            }
        } catch (Exception $e) {
            error_log("CartManager cleanupInvalidItems error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while cleaning up invalid items'];
        }
    }
    
    /**
     * 检查商品是否存在
     * @param string $itemType 商品类型
     * @param int $itemId 商品ID
     * @return bool 是否存在
     */
    private function checkItemExists($itemType, $itemId) {
        try {
            if (in_array($itemType, ['photo', 'video'])) {
                // 先检查是否有status字段
                $check_status_sql = "SHOW COLUMNS FROM products LIKE 'status'";
                $check_result = mysqli_query($this->conn, $check_status_sql);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $sql = "SELECT id FROM products WHERE id = ? AND status = 'Active'";
                } else {
                    $sql = "SELECT id FROM products WHERE id = ?";
                }
                
                // 对于photo和video类型，还需要检查相应的内容是否存在
                if ($itemType === 'photo') {
                    $sql .= " AND paid_photos_zip IS NOT NULL AND paid_photos_zip != ''";
                } elseif ($itemType === 'video') {
                    $sql .= " AND paid_video IS NOT NULL AND paid_video != ''";
                }
            } else {
                $sql = "SELECT id FROM hair WHERE id = ?";
            }
            
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $itemId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $exists = mysqli_num_rows($result) > 0;
            
            mysqli_stmt_close($stmt);
            return $exists;
        } catch (Exception $e) {
            error_log("CartManager checkItemExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取购物车中的特定商品
     * @param int $userId 用户ID
     * @param string $itemType 商品类型
     * @param int $itemId 商品ID
     * @param bool $isPhotoPack 是否为图片包（保留参数以兼容现有调用）
     * @return array|null 购物车项目信息
     */
    private function getCartItem($userId, $itemType, $itemId, $isPhotoPack) {
        try {
            // 由于表中没有is_photo_pack字段，我们只根据user_id, item_type, item_id查找
            $sql = "SELECT id, quantity FROM cart WHERE user_id = ? AND item_type = ? AND item_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "isi", $userId, $itemType, $itemId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $item = mysqli_fetch_assoc($result);
            
            mysqli_stmt_close($stmt);
            return $item;
        } catch (Exception $e) {
            error_log("CartManager getCartItem error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取商品价格
     * @param string $itemType 商品类型
     * @param int $itemId 商品ID
     * @param bool $isPhotoPack 是否为图片包
     * @return float|null 商品价格
     */
    private function getItemPrice($itemType, $itemId, $isPhotoPack) {
        try {
            if (in_array($itemType, ['photo', 'video'])) {
                // 先检查是否有status字段
                $check_status_sql = "SHOW COLUMNS FROM products LIKE 'status'";
                $check_result = mysqli_query($this->conn, $check_status_sql);
                
                // 根据商品类型选择价格字段
                $price_field = ($itemType === 'photo') ? "photo_pack_price" : "price";
                
                if (mysqli_num_rows($check_result) > 0) {
                    $sql = "SELECT $price_field as price FROM products WHERE id = ? AND status = 'Active'";
                } else {
                    $sql = "SELECT $price_field as price FROM products WHERE id = ?";
                }
            } else {
                $sql = "SELECT value as price FROM hair WHERE id = ?";
            }
            
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $itemId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            mysqli_stmt_close($stmt);
            return $row ? floatval($row['price']) : null;
        } catch (Exception $e) {
            error_log("CartManager getItemPrice error: " . $e->getMessage());
            return null;
        }
    }
}
?>
