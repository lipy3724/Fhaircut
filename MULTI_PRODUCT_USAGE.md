# 多商品购买功能使用说明

## 功能概述

这个功能允许用户一次购买多个商品（产品1，产品2，头发1等），发送一封包含所有商品信息的邮件，但后台会为每个商品创建单独的订单。支付成功后扣除总金额，失败则不扣除任何金额。

## 主要特点

### 📧 邮件功能
- **统一邮件**: 发送一封包含所有购买商品的确认邮件
- **详细信息**: 每个商品显示标题、价格、订单ID和下载链接（如适用）
- **美观模板**: 使用现代化的HTML邮件模板，与现有邮件风格一致
- **重试机制**: 内置邮件发送重试机制，提高成功率

### 🛍️ 订单管理
- **分离订单**: 每个商品创建独立的订单记录
- **唯一订单ID**: 每个订单都有唯一的订单ID用于跟踪
- **分类存储**: 产品和头发商品分别存储在不同的数据表中
- **状态跟踪**: 记录邮件发送状态和支付状态

### 💳 支付逻辑
- **事务保护**: 使用数据库事务确保数据一致性
- **余额验证**: 余额支付前验证用户余额是否足够
- **邮件优先**: 只有邮件发送成功后才扣除金额
- **回滚机制**: 任何步骤失败都会回滚所有操作

## 文件结构

### 核心文件
- `multi_product_email_functions.php` - 多商品邮件发送核心功能
- `multi_product_purchase_api.php` - API端点处理购买请求
- `test_multi_product.php` - 测试页面和演示界面

### 主要函数

#### processMultiProductPurchase()
处理完整的多商品购买流程：
- 验证用户余额（余额支付）
- 创建多个订单记录
- 发送确认邮件
- 扣除支付金额
- 更新邮件状态

#### sendMultiProductEmail()
发送多商品购买确认邮件：
- 支持多种SMTP配置重试
- 生成包含所有商品的邮件模板
- 为数字产品生成签名下载链接
- 记录发送状态和时间

## API接口

### 余额支付
```javascript
POST multi_product_purchase_api.php
{
    "action": "process_multi_product_balance_payment",
    "items": [
        {
            "item_type": "video",
            "item_id": "123"
        },
        {
            "item_type": "hair",
            "item_id": "456"
        }
    ]
}
```

### PayPal支付
```javascript
POST multi_product_purchase_api.php
{
    "action": "process_multi_product_paypal_payment",
    "items": [
        {
            "item_type": "photo_pack",
            "item_id": "789"
        }
    ],
    "paypal_email": "user@example.com",
    "paypal_name": "User Name"
}
```

## 支持的商品类型

### 产品 (Products)
- **video**: 视频产品
- **photo_pack**: 图片包产品
- 自动生成48小时有效的签名下载链接

### 头发 (Hair)
- **hair**: 头发产品
- 不需要下载链接，仅记录购买信息

## 邮件模板特点

### 设计风格
- 现代化的响应式设计
- 与现有邮件模板保持一致的品牌风格
- 清晰的商品列表和价格显示
- 突出显示总金额和支付方式

### 内容包含
- 用户问候和感谢信息
- 详细的订单摘要
- 每个商品的单独信息卡片
- 下载链接（适用于数字产品）
- 重要提示和客服信息

## 数据库表结构

### purchases 表
存储产品订单：
- order_id (订单ID)
- user_id (用户ID)
- product_id (产品ID)
- amount (金额)
- purchase_type (商品类型: 'product'=视频, 'photo_pack'=图片包, 'balance'=余额充值, 'activation'=账号激活)
- email_sent (邮件发送状态)
- is_photo_pack (是否为图片包: 0=视频, 1=图片包)

### hair_purchases 表
存储头发订单：
- order_id (订单ID)
- user_id (用户ID)
- hair_id (头发ID)
- amount (金额)
- purchase_type (支付方式: 'balance'=余额支付, 'paypal'=PayPal支付, 'googlepay'=Google Pay, 'applepay'=Apple Pay)
- email_sent (邮件发送状态)

## 头发订单类型的填写和后台显示分析

### 📝 **订单类型填写逻辑**

#### 1. **数据库字段设置**
头发订单存储在 `hair_purchases` 表中，`purchase_type` 字段用于记录订单类型：

```83:84:multi_product_email_functions.php
$insert_sql = "INSERT INTO hair_purchases (user_id, email, email_source, hair_id, order_id, transaction_id, amount, purchase_date, email_sent, purchase_type) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?)";
```

#### 2. **订单类型值的设定**
在创建头发订单时，`purchase_type` 字段根据支付方式自动设置：

```81:81:multi_product_email_functions.php
$purchase_type = ($paymentMethod === 'balance') ? 'balance' : 'paypal';
```

**支持的订单类型值：**
- `'balance'` - 余额支付
- `'paypal'` - PayPal支付  
- `'googlepay'` - Google Pay支付
- `'applepay'` - Apple Pay支付

### 🖥️ **后台显示逻辑**

#### 1. **固定显示方式**
在后台管理界面 `admin/hair_orders.php` 中，头发订单的类型显示是**硬编码**的：

```623:623:admin/hair_orders.php
<td>余额支付 - 头发</td>
```

#### 2. **显示问题**
**重要发现：** 后台显示存在问题！无论实际的 `purchase_type` 值是什么，后台都固定显示为 "余额支付 - 头发"。这意味着：

- ✅ 数据库中正确记录了实际的支付方式（`balance`、`paypal` 等）
- ❌ 后台界面没有正确显示实际的支付方式
- ❌ 所有头发订单在后台都显示为"余额支付 - 头发"

#### 3. **正确的显示方式应该是**
根据数据库中的 `purchase_type` 字段动态显示：

```php
<code_block_to_apply_changes_from>
```

### 📊 **数据库表结构**

根据文档说明，`hair_purchases` 表的结构如下：

```125:132:MULTI_PRODUCT_USAGE.md
### hair_purchases 表
存储头发订单：
- order_id (订单ID)
- user_id (用户ID)
- hair_id (头发ID)
- amount (金额)
- purchase_type (支付方式: 'balance'=余额支付, 'paypal'=PayPal支付, 'googlepay'=Google Pay, 'applepay'=Apple Pay)
- email_sent (邮件发送状态)
```

### 🔧 **修复建议**

要修复后台显示问题，需要修改 `admin/hair_orders.php` 文件第623行，将固定的显示文本改为动态读取 `purchase_type` 字段的值并进行相应的中文显示转换。

这样可以确保后台管理界面准确反映每个头发订单的实际支付方式。

## 测试说明

### 访问测试页面
打开 `test_multi_product.php` 进行功能测试：

1. **选择商品**: 勾选要购买的商品
2. **查看摘要**: 实时显示选中商品和总价
3. **选择支付**: 余额支付或PayPal模拟支付
4. **确认购买**: 查看购买结果和订单信息

### 测试场景
- ✅ 单个商品购买
- ✅ 多个同类商品购买
- ✅ 混合商品类型购买
- ✅ 余额不足的错误处理
- ✅ 邮件发送失败的回滚
- ✅ 网络错误的处理

## 安全特性

### 数据验证
- 严格验证商品ID和类型
- 验证用户权限和登录状态
- 检查商品是否存在和有效

### 事务保护
- 使用数据库事务确保原子性
- 任何步骤失败都会完全回滚
- 防止部分成功的数据不一致

### 邮件安全
- 使用签名URL防止未授权下载
- 48小时有效期限制
- 防止邮件发送频率过高

## 日志记录

系统会记录以下信息：
- API调用日志
- 邮件发送尝试和结果
- 错误信息和重试次数
- 用户操作和支付状态

## 扩展性

### 支持新商品类型
在 `validateAndGetItemDetails()` 函数中添加新的商品类型处理逻辑。

### 支持新支付方式
在 `processMultiProductPurchase()` 函数中添加新的支付方式处理。

### 自定义邮件模板
修改 `generateMultiProductEmailTemplate()` 函数来自定义邮件外观和内容。

## 注意事项

1. **邮件发送**: 确保SMTP配置正确，否则购买会失败
2. **余额检查**: 余额支付会预先检查用户余额
3. **事务处理**: 所有操作都在事务中进行，确保数据一致性
4. **错误处理**: 任何步骤失败都会回滚，不会扣除金额
5. **日志监控**: 建议监控错误日志以及时发现问题
