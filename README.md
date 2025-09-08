# Fhaircut - 头发交易平台

一个基于PHP的头发交易电商平台，支持多种支付方式和用户管理功能。

## 功能特性

### 🛍️ 电商功能
- 产品展示和详情页面
- 购物车功能
- 多种支付方式支持（PayPal、Google Pay、Apple Pay）
- 订单管理系统
- 用户购买历史

### 👥 用户管理
- 用户注册和登录
- 邮箱验证系统
- 密码重置功能
- 用户余额管理
- 会员权限系统

### 🎨 界面特性
- 响应式设计
- 现代化UI界面
- 图片画廊展示
- 搜索功能
- 分类浏览

### 🔧 管理功能
- 管理员后台
- 产品管理
- 用户管理
- 订单管理
- 系统设置

## 技术栈

- **后端**: PHP 7.4+
- **数据库**: MySQL 5.7+
- **前端**: HTML5, CSS3, JavaScript
- **支付**: PayPal SDK, Google Pay, Apple Pay
- **邮件**: PHPMailer
- **其他**: Bootstrap, jQuery

## 安装和配置

### 1. 环境要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Apache/Nginx 服务器
- Composer (可选)

### 2. 克隆项目

```bash
git clone https://github.com/lipy3724/Fhaircut.git
cd Fhaircut
```

### 3. 配置环境

1. 复制示例配置文件：
```bash
cp env.example.php env.php
cp db_config.example.php db_config.php
```

2. 编辑 `env.php` 文件，配置以下信息：
```php
// 数据库配置
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'jianfa_db');

// PayPal配置
define('PAYPAL_CLIENT_ID', 'your_paypal_client_id');
define('PAYPAL_CLIENT_SECRET', 'your_paypal_client_secret');
define('PAYPAL_SANDBOX', true); // 生产环境设为 false
```

### 4. 数据库设置

项目会自动初始化数据库。首次访问时，系统会：
- 创建必要的数据库表
- 插入初始数据
- 创建管理员账户

### 5. 文件权限

确保以下目录有写入权限：
```bash
chmod 755 uploads/
chmod 755 uploads/products/
chmod 755 uploads/hair/
chmod 755 uploads/photos/
chmod 755 uploads/videos/
chmod 755 backups/
```

### 6. 服务器配置

#### Apache
确保启用了 mod_rewrite 模块，并配置 `.htaccess` 文件。

#### Nginx
添加以下配置到你的服务器块：
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

## 使用说明

### 管理员登录

1. 访问 `/admin.php`
2. 使用默认管理员账户登录
3. 在管理后台可以管理产品、用户、订单等

### 支付配置

#### PayPal
1. 在 [PayPal Developer](https://developer.paypal.com/) 创建应用
2. 获取 Client ID 和 Client Secret
3. 在 `env.php` 中配置相关信息

#### Google Pay
1. 在 [Google Pay Console](https://pay.google.com/business/console/) 设置商户账户
2. 配置支付方式和商户信息

#### Apple Pay
1. 在 [Apple Developer](https://developer.apple.com/) 配置 Apple Pay
2. 设置商户标识符

## 项目结构

```
Fhaircut/
├── admin/                  # 管理后台文件
├── uploads/               # 上传文件目录
│   ├── products/         # 产品图片
│   ├── hair/            # 头发图片
│   ├── photos/          # 用户照片
│   └── videos/          # 视频文件
├── PHPMailer-6.9.1/      # 邮件库
├── vendor/               # Composer依赖
├── backups/              # 数据库备份
├── *.php                 # 主要PHP文件
├── style.css             # 主样式文件
├── README.md             # 项目说明
├── .gitignore            # Git忽略文件
└── env.example.php       # 环境配置示例
```

## 主要文件说明

- `index.php` - 入口文件
- `home.php` - 首页
- `main.php` - 产品列表页
- `product_detail.php` - 产品详情页
- `hair_detail.php` - 头发详情页
- `cart.php` - 购物车页面
- `login.php` - 用户登录
- `register.php` - 用户注册
- `admin.php` - 管理后台入口

## API接口

- `cart_api.php` - 购物车API
- `paypal_api.php` - PayPal支付API
- `balance_payment_api.php` - 余额支付API
- `multi_product_purchase_api.php` - 多产品购买API

## 安全注意事项

1. **环境配置**: 确保 `env.php` 和 `db_config.php` 不被版本控制
2. **文件上传**: 限制上传文件类型和大小
3. **SQL注入**: 使用预处理语句
4. **XSS防护**: 对用户输入进行过滤和转义
5. **HTTPS**: 生产环境建议使用HTTPS

## 开发和贡献

### 本地开发

1. 克隆项目到本地
2. 配置本地环境
3. 启动本地服务器：`php -S localhost:8082`

### 贡献指南

1. Fork 项目
2. 创建功能分支：`git checkout -b feature/new-feature`
3. 提交更改：`git commit -am 'Add new feature'`
4. 推送到分支：`git push origin feature/new-feature`
5. 创建 Pull Request

## 许可证

本项目采用 MIT 许可证。详见 [LICENSE](LICENSE) 文件。

## 支持

如有问题或建议，请创建 [Issue](https://github.com/lipy3724/Fhaircut/issues) 或联系开发者。

## 更新日志

### v1.0.0
- 初始版本发布
- 基本电商功能
- 用户管理系统
- 多种支付方式支持
- 管理后台

---

**注意**: 这是一个商业项目，请确保遵循相关法律法规，特别是关于在线交易和用户数据保护的规定。