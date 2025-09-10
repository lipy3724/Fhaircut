-- ========================================
-- 数据库备份文件
-- 数据库: jianfa_db
-- 备份时间: 2025-09-10 07:58:32
-- 生成工具: 剪发网站数据库备份脚本
-- ========================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- --------------------------------------------------------
-- 表的结构 `cart`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `is_photo_pack` tinyint(1) NOT NULL DEFAULT 0,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_type` enum('photo','video','hair') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_item` (`user_id`,`item_type`,`item_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_item` (`item_type`,`item_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 表的结构 `cart_paypal_mappings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cart_paypal_mappings`;
CREATE TABLE `cart_paypal_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `paypal_order_id` varchar(255) NOT NULL,
  `cart_ids` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `paypal_order_id` (`paypal_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 表的结构 `categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 转存表中的数据 `categories`
-- --------------------------------------------------------

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Bald buzzcut'),
(2, 'Broken hair'),
(3, 'Cool bobo hair'),
(4, 'Curly hair'),
(5, 'Hair sales'),
(6, 'Halo hairstyle'),
(7, 'Other nice hair'),
(8, 'Shovel long Bob'),
(9, 'Shovel short Bob'),
(10, 'Super short hair'),
(11, 'Today 42.0% off');

-- --------------------------------------------------------
-- 表的结构 `hair`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `hair`;
CREATE TABLE `hair` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '头发标题',
  `description` text DEFAULT NULL COMMENT '头发简介',
  `length` decimal(5,2) DEFAULT 0.00 COMMENT '长度(厘米)',
  `weight` decimal(8,2) DEFAULT 0.00 COMMENT '重量(克)',
  `value` decimal(10,2) DEFAULT 0.00 COMMENT '价值(元)',
  `image` varchar(255) DEFAULT NULL COMMENT '主图片',
  `image2` varchar(255) DEFAULT NULL COMMENT '图片2',
  `image3` varchar(255) DEFAULT NULL COMMENT '图片3',
  `image4` varchar(255) DEFAULT NULL COMMENT '图片4',
  `image5` varchar(255) DEFAULT NULL COMMENT '图片5',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active' COMMENT '状态',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  `clicks` int(11) DEFAULT 0 COMMENT '点击次数',
  `show_on_homepage` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='头发信息表';

-- --------------------------------------------------------
-- 转存表中的数据 `hair`
-- --------------------------------------------------------

INSERT INTO `hair` (`id`, `title`, `description`, `length`, `weight`, `value`, `image`, `image2`, `image3`, `image4`, `image5`, `status`, `created_at`, `updated_at`, `clicks`, `show_on_homepage`) VALUES
(1, '头发3', '', 123.00, 123.00, 123.00, 'uploads/hair/68c0d4247a8a2.jpg', NULL, NULL, NULL, NULL, 'Active', '2025-09-10 09:28:04', '2025-09-10 11:10:29', 1, 0),
(2, '头发', '', 12.00, 112.00, 12.00, 'uploads/hair/68c0d4247c63a.jpg', NULL, NULL, NULL, NULL, 'Active', '2025-09-10 09:28:04', '2025-09-10 09:28:04', 0, 0),
(3, '头发1', 1, 11.00, 11.00, 11.00, 'uploads/hair/68c0d4247cc13.png', NULL, NULL, NULL, NULL, 'Active', '2025-09-10 09:28:04', '2025-09-10 09:28:04', 0, 0),
(4, 'q w', '', 233.00, 111.00, 12.00, 'uploads/hair/68b931f215056_0.jpg', 'uploads/hair/68b931f2152a2_1.png', 'uploads/hair/68b931f215419_2.jpeg', 'uploads/hair/68b931f2154e0_3.png', 'uploads/hair/68b931f215584_4.jpg', 'Active', '2025-09-04 01:52:24', '2025-09-08 01:23:46', 8, 0),
(5, 12, '简介测试', 10.00, 23.00, 11.00, 'uploads/hair/68b8f9c9cb9d5.png', NULL, NULL, NULL, NULL, 'Active', '2025-09-04 02:30:33', '2025-09-08 01:40:38', 23, 0);

-- --------------------------------------------------------
-- 表的结构 `hair_purchases`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `hair_purchases`;
CREATE TABLE `hair_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_source` varchar(50) DEFAULT NULL,
  `hair_id` int(11) DEFAULT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `purchase_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_sent` tinyint(1) DEFAULT 0,
  `purchase_type` varchar(50) DEFAULT 'balance',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `hair_id` (`hair_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 表的结构 `login_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `login_ip` varchar(45) NOT NULL,
  `login_location` varchar(255) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_login_time` (`login_time`),
  CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 转存表中的数据 `login_logs`
-- --------------------------------------------------------

INSERT INTO `login_logs` (`id`, `user_id`, `username`, `email`, `login_ip`, `login_location`, `login_time`, `user_agent`) VALUES
(1, 23, 'bj123', '1725633271@qq.com', '::1', '未知', '2025-09-10 11:22:12', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36');

-- --------------------------------------------------------
-- 表的结构 `product_categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`,`category_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `product_categories_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 表的结构 `product_member_images`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `product_member_images`;
CREATE TABLE `product_member_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 表的结构 `products`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `subtitle` varchar(200) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `sales` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `image2` varchar(255) DEFAULT NULL,
  `image3` varchar(255) DEFAULT NULL,
  `image4` varchar(255) DEFAULT NULL,
  `image5` varchar(255) DEFAULT NULL,
  `guest` tinyint(1) DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `show_on_homepage` tinyint(1) DEFAULT 0,
  `paid_video` varchar(255) DEFAULT NULL,
  `paid_video_size` bigint(20) DEFAULT 0,
  `paid_video_duration` int(11) DEFAULT 0,
  `images_total_size` bigint(20) DEFAULT 0,
  `images_count` int(11) DEFAULT 0,
  `images_formats` varchar(255) DEFAULT NULL,
  `paid_photos_zip` varchar(255) DEFAULT NULL,
  `paid_photos_total_size` bigint(20) DEFAULT 0,
  `paid_photos_count` int(11) DEFAULT 0,
  `paid_photos_formats` varchar(255) DEFAULT NULL,
  `image6` varchar(255) DEFAULT NULL,
  `photo_pack_price` decimal(10,2) DEFAULT 0.00,
  `member_image1` varchar(255) DEFAULT NULL,
  `member_image2` varchar(255) DEFAULT NULL,
  `member_image3` varchar(255) DEFAULT NULL,
  `member_image4` varchar(255) DEFAULT NULL,
  `member_image5` varchar(255) DEFAULT NULL,
  `member_image6` varchar(255) DEFAULT NULL,
  `member_image7` varchar(255) DEFAULT NULL,
  `member_image8` varchar(255) DEFAULT NULL,
  `member_image9` varchar(255) DEFAULT NULL,
  `member_image10` varchar(255) DEFAULT NULL,
  `member_image11` varchar(255) DEFAULT NULL,
  `member_image12` varchar(255) DEFAULT NULL,
  `member_image13` varchar(255) DEFAULT NULL,
  `member_image14` varchar(255) DEFAULT NULL,
  `member_image15` varchar(255) DEFAULT NULL,
  `member_image16` varchar(255) DEFAULT NULL,
  `member_image17` varchar(255) DEFAULT NULL,
  `member_image18` varchar(255) DEFAULT NULL,
  `member_image19` varchar(255) DEFAULT NULL,
  `member_image20` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78964 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 转存表中的数据 `products`
-- --------------------------------------------------------

INSERT INTO `products` (`id`, `title`, `subtitle`, `price`, `sales`, `clicks`, `image`, `image2`, `image3`, `image4`, `image5`, `guest`, `category_id`, `created_date`, `show_on_homepage`, `paid_video`, `paid_video_size`, `paid_video_duration`, `images_total_size`, `images_count`, `images_formats`, `paid_photos_zip`, `paid_photos_total_size`, `paid_photos_count`, `paid_photos_formats`, `image6`, `photo_pack_price`, `member_image1`, `member_image2`, `member_image3`, `member_image4`, `member_image5`, `member_image6`, `member_image7`, `member_image8`, `member_image9`, `member_image10`, `member_image11`, `member_image12`, `member_image13`, `member_image14`, `member_image15`, `member_image16`, `member_image17`, `member_image18`, `member_image19`, `member_image20`) VALUES
(1, 1, 0, 1.00, 0, 1, 'uploads/products/1757468239_4132_5F85F584-F173-4D7C-97B8-FDD340E6FCD0.png', '', '', '', NULL, 0, 2, '2025-09-10 09:37:19', 1, '', 0, 0, 850012, 1, 0, '', 0, 0, 0, NULL, 1.00, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 12, 12, 12.00, 0, 0, 'uploads/products/68c0d66c6c164.png', '', '', '', NULL, 1, 5, '2025-09-10 09:37:48', 0, '', 0, 0, 850012, 1, 'png', '', 850012, 1, 'png', NULL, 12.00, '', '', '', '', '', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, '测试', 0, 45.00, 15, 131, 'uploads/products/68c0d4f5989e1.jpg', 'uploads/products/68c0d4f598f56.png', 'uploads/products/68c0d4f59b940.png', '', '', 1, 1, '2025-08-13 09:22:58', 0, 0, 1772093, 4, 3902807, 3, 'jpg,png', 0, 0, 11, 'png', 0, 45.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(123, 'qqq', 123, 12.00, 0, 0, 'uploads/products/1757469470_5218_286f9c31-4da6-420a-98c1-3ef49d673f41568580919.png', '', '', '', NULL, 0, 1, '2025-09-10 09:57:50', 0, '', 0, 0, 2844588, 1, 'png', '', 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(223, 222, 0, 0.04, 1, 7, 'uploads/products/68afc2d068c37.jpg', NULL, NULL, NULL, NULL, 1, 5, '2025-08-28 02:45:36', 1, 'https://www.yinghuo.ai/', 2, 2, 152501, 1, 'jpg', NULL, 0, 0, NULL, NULL, 20.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1123, 123, 0, 12.00, 0, 0, 'uploads/products/1757468749_6551_20250811100139.jpg', 'uploads/products/1757468749_8622_286f9c31-4da6-420a-98c1-3ef49d673f41568580919.png', '', '', NULL, 0, 2, '2025-09-10 09:45:49', 0, '', 0, 0, 3052795, 2, 0, '', 0, 0, 0, NULL, 2.00, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1221, '测试3', 0, 78.00, 4, 38, 'uploads/products/68abc9434ac68.png', NULL, NULL, NULL, NULL, 1, 1, '2025-08-25 02:24:03', 1, 'https://yinghuo.ai', 1546, 30, 3316703, 2, 'png', 'https://www.yinghuo.ai', 7586, 7, 'png,jpeg', NULL, 87.00, 'uploads/products/68abc9434af81.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1234, 'qas', 0, 1.00, 0, 0, 'uploads/products/68c0d4e15c8c1.png', '', '', '', NULL, 1, 1, '2025-09-09 15:15:12', 0, '', 0, 0, 2844588, 1, 'png', 0, 0, 2, 'jpeg,png', NULL, 1.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(2345, 'tyu', 0, 1.00, 0, 6, 'uploads/products/68c0d4cb07bff.jpg', '', '', '', NULL, 1, 1, '2025-09-09 15:54:52', 0, '', 0, 0, 208207, 1, 'jpg', 0, 0, 2, 'png', NULL, 1.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(66666, 555, 0, 1.00, 0, 0, 'uploads/products/68c0d4b3bf282.jpg', '', '', '', NULL, 1, 7, '2025-09-09 17:49:50', 0, '', 0, 0, 314565, 2, 'jpg', 0, 2, 2, 'png', NULL, 1.00, 'uploads/products/68c0d4b3bf70b.jpg', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(78927, 123, 'Broken hair style product', 4.00, 3, 19, 'uploads/products/68afed4b67b40.jpg', NULL, NULL, NULL, NULL, 1, 3, '2025-08-28 05:46:51', 1, 2, 2454, 0, 687902, 2, 'jpg', NULL, 0, 43, NULL, NULL, 4.00, 'uploads/products/68b159714a54c.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78928, 'new', '', 3.00, 6, 13, 'uploads/products/68b0058232bfc.png', NULL, NULL, NULL, NULL, 1, 5, '2025-08-28 06:46:59', 1, 'https://www.yinghuo.ai/', 3, 5, 874788, 1, 'png', 'https://www.yinghuo.ai/', 333333, 30, 'png', NULL, 3.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78929, 'new2', 'hair sales product', 12.00, 3, 14, 'uploads/products/68affb76ac737.png', 'uploads/products/68b1120d1617e.jpg', 'uploads/products/68b01d3f91267.jpg', NULL, NULL, 1, 5, '2025-08-28 06:47:18', 1, 'https://www.yinghuo.ai/', 1234, 3, 4988847, 4, 'png,jpg', 'vip.fhaircut.com/uploads/photos/photo_78929.zip', 1771561, 3, 'png,jpg', NULL, 6.00, 'uploads/products/68b01d3f91474.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78954, 'po9', '', 1.00, 0, 0, 'uploads/products/68c0d440b9098.png', '', '', '', NULL, 1, 2, '2025-09-10 00:00:00', 0, '', 0, 0, 3719376, 2, 'png', 0, 0, 0, 0, NULL, 1.00, 'uploads/products/68c0d440bbb9f.png', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(78955, 'qqq1', '', 12.00, 0, 0, 'uploads/products/1757469499_9505_虚拟试鞋结果_1756198192267.jpg', '', '', '', NULL, 1, 2, '2025-09-10 09:58:19', 0, '', 0, 0, 1120351, 1, 'jpg', 0, 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(78956, 'qwe', '', 9.00, 0, 0, 'uploads/products/1757470106_1613_20250811100210.jpg', '', '', '', NULL, 0, 2, '2025-09-10 10:08:26', 0, '', 0, 0, 189992, 1, 'jpg', '', 0, 0, 0, NULL, 8.00, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78957, 123, '', 12.00, 0, 0, 'uploads/products/1757470546_7566_5F85F584-F173-4D7C-97B8-FDD340E6FCD0.png', 'uploads/products/1757470546_7961_3C34C8C0-F98E-44FB-93F7-2E9F857CF7A3.png', '', '', NULL, 0, 10, '2025-09-10 10:15:46', 0, '', 0, 0, 1724800, 2, 'png', '', 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78958, 'qweq', '', 12.00, 0, 0, 'uploads/products/1757471044_4069_286f9c31-4da6-420a-98c1-3ef49d673f41568580919.png', 'uploads/products/1757471044_2138_test2.jpg', '', '', NULL, 0, 2, '2025-09-10 10:24:04', 0, '', 0, 0, 2910629, 2, 'png,jpg', '', 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78959, 'asd', '', 23.00, 0, 2, 'uploads/products/1757471445_6458_running-shoe-371625_1920.jpg', 'uploads/products/1757471445_4959_shoes-5351339_1280.jpg', '', '', NULL, 1, 10, '2025-09-10 10:30:45', 0, '', 0, 0, 658089, 2, 'jpg', 0, 0, 0, 0, NULL, 23.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(78960, 'qwert', '', 12.00, 0, 0, 'uploads/products/1757471898_1055_fashion-photography-9636682_1920.jpg', '', '', '', NULL, 0, 9, '2025-09-10 10:38:18', 0, '', 0, 0, 392089, 1, 'jpg', '', 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78961, '123qwe', '', 12.00, 0, 1, 'uploads/products/1757473774_4430_20250811100139.jpg', 'uploads/products/1757473774_4227_20250811100210.jpg', '', '', NULL, 1, 1, '2025-09-10 11:09:34', 0, '', 0, 0, 398199, 2, 'jpg', 0, 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(78962, '测试产品112', '', 12.00, 0, 2, 'uploads/products/1757474613_4623_fashion-photography-9636682_1920.jpg', '', '', '', NULL, 1, 1, '2025-09-10 11:23:33', 0, '', 0, 0, 392089, 1, 'jpg', 0, 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0),
(78963, 'qwe', '', 12.00, 0, 0, 'uploads/products/1757483246_9801_new2.png', '', '', '', NULL, 1, 1, '2025-09-10 13:47:26', 0, '', 0, 0, 106278, 1, 'png', 0, 0, 0, 0, NULL, 12.00, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0);

-- --------------------------------------------------------
-- 表的结构 `purchases`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `email_source` enum('session','paypal') DEFAULT 'paypal',
  `product_id` int(11) NOT NULL,
  `order_id` varchar(512) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `is_photo_pack` tinyint(1) NOT NULL DEFAULT 0,
  `amount` decimal(10,2) NOT NULL,
  `purchase_date` datetime NOT NULL DEFAULT current_timestamp(),
  `email_sent` tinyint(1) NOT NULL DEFAULT 0,
  `purchase_type` enum('product','activation','balance','photo_pack') NOT NULL DEFAULT 'product',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 表的结构 `settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 转存表中的数据 `settings`
-- --------------------------------------------------------

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'contact_email', 'info@haircut.network', '2025-09-10 09:21:12', '2025-09-10 09:21:12'),
(2, 'contact_email2', 'support@haircut.network', '2025-09-10 09:21:12', '2025-09-10 09:21:12'),
(3, 'wechat', 'haircut_wechat', '2025-09-10 09:21:12', '2025-09-10 09:21:12'),
(4, 'site_description', 'Professional hair cutting tutorials and resources', '2025-09-10 09:24:59', '2025-08-13 10:43:43'),
(5, 'items_per_page', 10, '2025-09-10 09:24:59', '2025-08-13 10:51:10'),
(6, 'banner_image', 'banner_1757467798_3195.png', '2025-09-10 09:29:58', '2025-09-10 09:29:58'),
(7, 'activation_title', 'Congratulations! Registration Success!', '2025-09-10 09:30:06', '2025-09-10 09:30:06'),
(8, 'activation_subtitle', 'Select Payment Method, Activate your account!', '2025-09-10 09:30:06', '2025-09-10 09:30:06'),
(9, 'activation_button_text', '', '2025-09-10 09:30:06', '2025-09-10 09:30:06'),
(10, 'activation_fee', 100.00, '2025-09-10 09:30:06', '2025-09-10 09:30:06'),
(11, 'activation_note', 'Note: You must activate your account to access all features of our website. Without activation, you can only browse as a guest.', '2025-09-10 09:30:06', '2025-09-10 09:30:06');

-- --------------------------------------------------------
-- 表的结构 `user_login_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_login_logs`;
CREATE TABLE `user_login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `login_ip` varchar(45) NOT NULL,
  `login_location` varchar(100) DEFAULT '未知',
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_login_time` (`login_time`),
  CONSTRAINT `user_login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 表的结构 `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('Member','Editor','Administrator') NOT NULL DEFAULT 'Member',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `is_activated` tinyint(1) NOT NULL DEFAULT 0,
  `activation_payment_id` varchar(100) DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `registered_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_country` varchar(100) DEFAULT NULL,
  `last_login_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 转存表中的数据 `users`
-- --------------------------------------------------------

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `status`, `is_activated`, `activation_payment_id`, `balance`, `registered_date`, `last_login_ip`, `last_login_country`, `last_login_time`) VALUES
(1, 'admin', '$2y$10$ODcO.hpu87ldcdmDiuGnauzINNBvgXuG5MD9MF4Dy.x/Ob5BXogU6', 'admin@example.com', 'Administrator', 'Active', 1, NULL, 0.00, '2025-08-07 14:51:37', NULL, NULL, NULL),
(13, 'lipy9', '$2y$12$YxZmo2QnSTTG9DeQBEP6OekwzlfN5Rd9QDZHxOu4MBrKIzeuPyOr2', '2806448542@qq.com', 'Member', 'Active', 1, '868707582M164094U', 0.00, '2025-08-15 07:15:21', NULL, NULL, NULL),
(15, 'testuser3591', '$2y$12$wQXU1goUB1x80rS2WBttkeCOYNyWNCcTMVU8Kt4sfxWk57BGLHWTK', 'test4424@example.com', 'Member', 'Active', 1, NULL, 0.00, '2025-08-16 03:33:35', NULL, NULL, NULL),
(16, 'testuser2498', '$2y$12$WYK1MH/2RUp0eJiOo4q.iO47S81IWDBBlq5KFIB2zG2g2nzDyo66q', 'test1062@example.com', 'Member', 'Inactive', 0, NULL, 0.00, '2025-08-16 03:34:05', NULL, NULL, NULL),
(17, 'testuser3763', '$2y$12$M7/rBuxrWFkhQnZbco5QWOXK0SgoDm.in2AGG6GUebT9XrZSgQCwe', 'test3992@example.com', 'Editor', 'Inactive', 0, 'Auto-activated during login: Total spent over $100', 0.00, '2025-08-16 03:35:58', NULL, NULL, NULL),
(20, 'test', '$2y$12$TLHoAUIoeo42fXEUjM1MveLya3qJu7iXgAQJNUoUV8kqAcuhAN2qK', 'test3724@example.com', 'Member', 'Active', 1, NULL, 99.00, '2025-08-25 03:27:28', '5.34.216.84', 'United States', '2025-08-28 14:17:41'),
(21, 'lll3724', '$2y$12$j/wkUQM7dBTBBjRwnn70WOa4qfsByCBFaVzz/R9te0cl4o9/U63AK', 'lpy3724@foxmail.com', 'Member', 'Active', 1, 'Auto-activated: Total spent over $100', 0.00, '2025-08-25 09:04:36', '115.60.62.210', 'China', '2025-08-25 17:06:32'),
(22, 'bj1234', '$2y$10$p137SjJDvsLu5RkXoHO0Sez.E2YXzSeNvh6LgQRkCr9fFnzZjikOO', 'li786684763@gmail.com', 'Member', 'Active', 1, '8PW90853FF348723Y', 1193.96, '2025-08-27 12:01:28', '::1', '未知', '2025-09-08 09:32:44'),
(23, 'bj123', '$2y$10$3P7ACyPfirrtbHnYcKc8bOICRoYJiCeZXPdxzlMvByBhVkGWZNEpy', '1725633271@qq.com', 'Member', 'Active', 1, '5TW42484RW8242137', 9969.00, '2025-09-01 14:00:34', '::1', '未知', '2025-09-10 11:22:12'),
(24, 'lipy', '$2y$12$QC1nLQ6eZM89VCz.U5srUONP2HWtnkomREoeQZSQ24H7o4gyh810S', '493302210@qq.com', 'Member', 'Active', 1, 'Auto-activated: Previous activation payment found', 0.00, '2025-09-08 10:53:02', '::1', '未知', '2025-09-09 09:37:23');

-- --------------------------------------------------------
-- 表的结构 `verification_codes`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `verification_codes`;
CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 转存表中的数据 `verification_codes`
-- --------------------------------------------------------

INSERT INTO `verification_codes` (`id`, `email`, `code`, `expires_at`, `created_at`) VALUES
(2, 'lipy3724@gmail.com', 195951, '2025-08-14 11:03:45', '2025-08-14 10:53:45'),
(27, 'test@example.com', 723620, '2025-08-14 15:17:02', '2025-08-14 15:07:02'),
(44, '1558661815@qq.com', 441855, '2025-08-14 16:20:09', '2025-08-14 16:10:09'),
(66, '1725633271@qq.com', 962571, '2025-09-05 09:15:55', '2025-09-05 09:05:55');

-- ========================================
-- 备份完成
-- 结束时间: 2025-09-10 07:58:32
-- ========================================
