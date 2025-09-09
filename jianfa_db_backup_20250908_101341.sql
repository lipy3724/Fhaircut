-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: jianfa_db
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
INSERT INTO `cart` (`id`, `user_id`, `item_id`, `is_photo_pack`, `quantity`, `price`, `created_at`, `updated_at`, `item_type`) VALUES (12,1,23,0,1,25.00,'2025-09-05 05:40:36','2025-09-05 05:44:36','photo'),(13,1,223,0,1,35.00,'2025-09-05 05:40:36','2025-09-05 05:44:36','video'),(14,1,1,0,1,388.00,'2025-09-05 05:40:36','2025-09-05 05:40:36','hair'),(15,23,1,0,1,999.00,'2025-09-05 07:50:04','2025-09-05 07:50:04','hair');
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_paypal_mappings`
--

DROP TABLE IF EXISTS `cart_paypal_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_paypal_mappings`
--

LOCK TABLES `cart_paypal_mappings` WRITE;
/*!40000 ALTER TABLE `cart_paypal_mappings` DISABLE KEYS */;
INSERT INTO `cart_paypal_mappings` (`id`, `user_id`, `paypal_order_id`, `cart_ids`, `total_amount`, `created_at`) VALUES (1,23,'17974362DM050145S','[16,11,10]',30.00,'2025-09-05 08:07:53'),(2,23,'2Y447715DJ626423J','[16,11,10]',30.00,'2025-09-05 08:35:44'),(3,23,'3NN1041680223434K','[18,17]',57.00,'2025-09-05 09:09:16'),(4,23,'6AA69700DY556303K','[18,17]',57.00,'2025-09-05 09:31:04'),(5,23,'2JE941816V869584R','[18,17]',57.00,'2025-09-05 09:40:04');
/*!40000 ALTER TABLE `cart_paypal_mappings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` (`id`, `name`) VALUES (1,'Bald buzzcut'),(2,'Broken hair'),(3,'Cool bobo hair'),(4,'Curly hair'),(5,'Hair sales'),(6,'Halo hairstyle'),(7,'Other nice hair'),(8,'Shovel long Bob'),(9,'Shovel short Bob'),(10,'Super short hair'),(73,'Super short hair test'),(11,'Today 42.0% off');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hair`
--

DROP TABLE IF EXISTS `hair`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hair`
--

LOCK TABLES `hair` WRITE;
/*!40000 ALTER TABLE `hair` DISABLE KEYS */;
INSERT INTO `hair` (`id`, `title`, `description`, `length`, `weight`, `value`, `image`, `image2`, `image3`, `image4`, `image5`, `status`, `created_at`, `updated_at`, `clicks`, `show_on_homepage`) VALUES (1,'短发','',10.00,233.00,999.00,'uploads/hair/68b931e3237c2_0.jpg','uploads/hair/68b931e3243ed_1.jpeg',NULL,NULL,NULL,'Active','2025-09-04 01:29:51','2025-09-05 06:30:28',2,0),(4,'q w','',233.00,111.00,12.00,'uploads/hair/68b931f215056_0.jpg','uploads/hair/68b931f2152a2_1.png','uploads/hair/68b931f215419_2.jpeg','uploads/hair/68b931f2154e0_3.png','uploads/hair/68b931f215584_4.jpg','Active','2025-09-04 01:52:24','2025-09-08 01:23:46',8,0),(5,'12','简介测试',10.00,23.00,11.00,'uploads/hair/68b8f9c9cb9d5.png',NULL,NULL,NULL,NULL,'Active','2025-09-04 02:30:33','2025-09-08 01:40:38',23,0);
/*!40000 ALTER TABLE `hair` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hair_purchases`
--

DROP TABLE IF EXISTS `hair_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hair_purchases`
--

LOCK TABLES `hair_purchases` WRITE;
/*!40000 ALTER TABLE `hair_purchases` DISABLE KEYS */;
INSERT INTO `hair_purchases` (`id`, `user_id`, `email`, `email_source`, `hair_id`, `order_id`, `transaction_id`, `amount`, `purchase_date`, `email_sent`, `purchase_type`) VALUES (1,23,'1725633271@qq.com','session',5,'HAIR_BAL_1756965762_23_5','HAIR_BAL_1756965762_23_5',11.00,'2025-09-04 06:02:42',1,'balance'),(2,23,'1725633271@qq.com','session',5,'HAIR_BAL_1756968376_23_5','HAIR_BAL_1756968376_23_5',11.00,'2025-09-04 06:46:16',1,'balance'),(3,23,'1725633271@qq.com','session',5,'HAIR_BAL_1756971769_23_5','HAIR_BAL_1756971769_23_5',11.00,'2025-09-04 07:43:13',1,'balance'),(4,23,'1725633271@qq.com','session',4,'CART_BAL_1757058563.2076_28230_23','CART_BAL_1757058563.2076_28230_23',12.00,'2025-09-05 07:49:27',1,'balance'),(9,23,'1725633271@qq.com','session',4,'MULTI_BAL_20250905_4A87910B','MULTI_BAL_20250905_4A87910B',12.00,'2025-09-05 08:51:52',1,'balance'),(11,23,'1725633271@qq.com','session',4,'MULTI_PP_20250905_006B7C08','MULTI_PP_20250905_006B7C08',12.00,'2025-09-05 09:40:22',1,'paypal');
/*!40000 ALTER TABLE `hair_purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_logs`
--

LOCK TABLES `login_logs` WRITE;
/*!40000 ALTER TABLE `login_logs` DISABLE KEYS */;
INSERT INTO `login_logs` (`id`, `user_id`, `username`, `email`, `login_ip`, `login_location`, `login_time`, `user_agent`) VALUES (1,23,'bj123','1725633271@qq.com','::1','未知','2025-09-08 01:24:34','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'),(2,22,'bj1234','li786684763@gmail.com','::1','未知','2025-09-08 01:32:44','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36');
/*!40000 ALTER TABLE `login_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78949 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `title`, `subtitle`, `price`, `sales`, `clicks`, `image`, `image2`, `image3`, `image4`, `image5`, `guest`, `category_id`, `created_date`, `show_on_homepage`, `paid_video`, `paid_video_size`, `paid_video_duration`, `images_total_size`, `images_count`, `images_formats`, `paid_photos_zip`, `paid_photos_total_size`, `paid_photos_count`, `paid_photos_formats`, `image6`, `photo_pack_price`, `member_image1`, `member_image2`, `member_image3`, `member_image4`, `member_image5`, `member_image6`) VALUES (23,'测试','0',45.00,15,124,'uploads/products/689be8f239775.png','','','','',1,1,'2025-08-13 01:22:58',1,'0',1775978,4,6573029,4,'png','0',1681454,11,'png','0',45.00,'uploads/products/68abbbc8c4a31.png','uploads/products/68abbbc8c4d4e.png','uploads/products/68abbbc8c4f08.png','','',''),(223,'222','0',0.04,1,7,'uploads/products/68afc2d068c37.jpg','','','',NULL,1,5,'2025-08-28 02:45:36',1,'https://www.yinghuo.ai/',2,2,152501,1,'jpg','0',2,2,'png,jpeg',NULL,20.00,'','','','','',''),(1221,'测试3','0',78.00,4,34,'uploads/products/68abc9434ac68.png','','','',NULL,1,1,'2025-08-25 02:24:03',1,'https://yinghuo.ai',1546,30,3316703,2,'png','https://www.yinghuo.ai',7586,7,'png,jpeg',NULL,87.00,'uploads/products/68abc9434af81.png','','','','',''),(78927,'123','Broken hair style product',4.00,3,18,'uploads/products/68afed4b67b40.jpg','','','',NULL,1,3,'2025-08-28 05:46:51',1,'2',2454,0,687902,2,'jpg','0',0,43,'0',NULL,4.00,'uploads/products/68b159714a54c.jpg','','','','',''),(78928,'new','',3.00,6,13,'uploads/products/68b0058232bfc.png','','','',NULL,1,5,'2025-08-28 06:46:59',1,'https://www.yinghuo.ai/',3,5,874788,1,'png','https://www.yinghuo.ai/',333333,30,'png',NULL,3.00,'','','','','',''),(78929,'new2','hair sales product',12.00,3,14,'uploads/products/68affb76ac737.png','uploads/products/68b1120d1617e.jpg','uploads/products/68b01d3f91267.jpg','',NULL,1,5,'2025-08-28 06:47:18',1,'https://www.yinghuo.ai/',1234,3,4988847,4,'png,jpg','vip.fhaircut.com/uploads/photos/photo_78929.zip',1771561,3,'png,jpg',NULL,6.00,'uploads/products/68b01d3f91474.png','','','','',''),(78930,'1','12',1.00,2,5,'uploads/products/68b6aee1cd193.png','uploads/products/68b6aed8279ea.jpg','uploads/products/68b6abeaccdda.png','uploads/products/68b6abeacd9d9.png',NULL,1,1,'2025-08-29 03:33:44',1,'uploads/videos/video_1756801939_5156.mp4',533841,8,6066441,8,'png,jpg','0',6066441,8,'0',NULL,5.00,'uploads/products/68b6ac0aaa16a.jpg','uploads/products/68b6ac0aabc46.jpg','uploads/products/68b6ac0aaceb0.jpg','uploads/products/68b6ac0aaff21.jpg','',''),(78931,'tea','',12.00,1,5,'uploads/products/68b6b042799aa.jpg','uploads/products/68b6b042837f5.png','uploads/products/68b6af1866853.jpg','uploads/products/68b6af1869173.jpg',NULL,1,1,'2025-09-02 08:47:20',0,'vip.fhaircut.com/uploads/videos/video_78931.zip',12407563,37,3556837,8,'jpg,png','vip.fhaircut.com/uploads/photos/photo_78931.zip',3556837,8,'png',NULL,12.00,'uploads/products/68b6af4d94fc0.jpg','uploads/products/68b6af4d95395.jpg','uploads/products/68b6af4d957ab.jpg','uploads/products/68b6af4d97f4b.jpg','',''),(78941,'12','hair sales product',21.00,0,0,'uploads/products/68b7eb6c77870.jpg','','','',NULL,1,3,'2025-09-03 07:17:00',0,'',0,0,1607300,2,'jpg','',1607300,2,'jpg',NULL,12.00,'uploads/products/68b7eb6c7a578.jpg','','','','',''),(78942,'娃娃','21',1.00,0,0,'uploads/products/1756884046_7560_fashion-photography-9639843_1920.jpg','0','','',NULL,1,2,'2025-09-03 07:20:46',0,'vip.fhaircut.com/uploads/videos/video_78942.zip',10512882,46,486949,1,'jpg','0',486949,1,'0',NULL,0.00,'','','','','',''),(78943,'wqe','',1.00,0,0,'uploads/products/1756885007_3224_5F85F584-F173-4D7C-97B8-FDD340E6FCD0.png','0','','',NULL,0,3,'2025-09-03 07:36:47',0,'uploads/videos/1756885007_1658_899256822-1-16.mp4',1933890,37,850012,1,'png','',0,0,'',NULL,2.00,'','','','','',''),(78944,'23','12',1.00,0,0,'uploads/products/1756885121_3732_虚拟试鞋结果_1756198192267.jpg','0','','',NULL,0,7,'2025-09-03 07:38:42',0,'',0,0,1120351,1,'jpg','uploads/photos/1756885121_8622_photos.zip',1999389,3,'jpg',NULL,1.00,'','','','','',''),(78945,'12','12',12.00,0,0,'uploads/products/1756889911_4819_3C34C8C0-F98E-44FB-93F7-2E9F857CF7A3.png','0','','',NULL,1,5,'2025-09-03 08:58:31',0,'vip.fhaircut.com/uploads/videos/video_78945.mp4',1933890,37,874788,1,'png','0',874788,1,'0',NULL,12.00,'','','','','',''),(78947,'q w','',1.00,0,0,'uploads/products/1756891043_6654_fashion-photography-9639843_1920.jpg','0','','',NULL,0,6,'2025-09-03 09:17:23',0,'vip.fhaircut.com/uploads/videos/1756891043_3341_1203237282-1-192.mp4',10547582,46,486949,1,'jpg','',0,0,'',NULL,1.00,'','','','','','');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchases`
--

LOCK TABLES `purchases` WRITE;
/*!40000 ALTER TABLE `purchases` DISABLE KEYS */;
INSERT INTO `purchases` (`id`, `user_id`, `email`, `email_source`, `product_id`, `order_id`, `transaction_id`, `is_photo_pack`, `amount`, `purchase_date`, `email_sent`, `purchase_type`) VALUES (1,NULL,'sb-843xlg45255682@personal.example.com','paypal',75489,'0H9699136A0861726','4',0,59.00,'2025-08-15 09:17:15',1,'product'),(2,10,'sb-843xlg45255682@personal.example.com','paypal',75489,'95H16822762924802','7',0,59.00,'2025-08-15 09:23:05',1,'product'),(3,10,'sb-843xlg45255682@personal.example.com','paypal',75489,'ORDER_1','2ND44044VG3403021',1,27.00,'2025-08-15 09:40:35',0,'product'),(4,10,'493302210@qq.com','session',75489,'ORDER_8','8M809612GN964892D',1,27.00,'2025-08-15 10:00:22',0,'product'),(5,9,'2806448542@qq.com','session',75489,'ORDER_5','1LE91484JH175534N',1,27.00,'2025-08-15 10:10:30',0,'product'),(7,9,'2806448542@qq.com','session',75489,'ORDER_2','53F39065YX544212G',0,59.00,'2025-08-15 10:44:29',1,'product'),(8,NULL,'sb-843xlg45255682@personal.example.com','paypal',75489,'94589426','6YE165379J193284E',1,27.00,'2025-08-15 10:52:35',1,'product'),(9,13,'2806448542@qq.com','session',0,'868707582M164094U','868707582M164094U',0,100.00,'2025-08-15 15:29:16',1,'activation'),(10,14,'493302210@qq.com','session',0,'5D00782937968294X','5D00782937968294X',0,100.00,'2025-08-15 16:53:18',1,'activation'),(13,14,'493302210@qq.com','session',75489,'ORDER_0','BAL17553060672906',1,27.00,'2025-08-16 09:01:07',1,'balance'),(14,NULL,'test_auto_activate_1755313452@example.com','paypal',75489,'test_order_1_1755313452','test_trans_1_1755313452',0,40.00,'2025-08-16 11:04:12',1,'product'),(15,NULL,'test_auto_activate_1755313452@example.com','paypal',75489,'test_order_2_1755313452','test_trans_2_1755313452',1,30.00,'2025-08-16 11:04:12',1,'product'),(16,NULL,'test_auto_activate_1755313452@example.com','paypal',75489,'test_order_3_1755313452','test_trans_3_1755313452',0,35.00,'2025-08-16 11:04:12',1,'product'),(17,NULL,'test_auto_activate_1755314025@example.com','paypal',75489,'test_order_1_1755314025','test_trans_1_1755314025',0,40.00,'2025-08-16 11:13:45',1,'product'),(18,NULL,'test_auto_activate_1755314025@example.com','paypal',75489,'test_order_2_1755314025','test_trans_2_1755314025',1,30.00,'2025-08-16 11:13:45',1,'product'),(19,NULL,'test_auto_activate_1755314025@example.com','paypal',75489,'test_order_3_1755314025','test_trans_3_1755314025',0,35.00,'2025-08-16 11:13:45',1,'product'),(20,16,'test1062@example.com','session',1,'TEST975475','TRANS320697',0,30.00,'2025-08-06 03:34:05',1,'product'),(21,16,'test1062@example.com','session',1,'TEST140114','TRANS466759',0,25.50,'2025-08-09 03:34:05',1,'product'),(22,16,'test1062@example.com','session',1,'TEST167195','TRANS927829',0,20.00,'2025-08-13 03:34:05',1,'product'),(23,16,'test1062@example.com','session',1,'TEST155428','TRANS310271',0,30.00,'2025-08-15 03:34:05',1,'product'),(24,17,'test3992@example.com','session',1,'TEST611758','TRANS479255',0,30.00,'2025-08-06 03:35:58',1,'product'),(25,17,'test3992@example.com','session',1,'TEST938642','TRANS613153',0,25.50,'2025-08-09 03:35:58',1,'product'),(26,17,'test3992@example.com','session',1,'TEST996204','TRANS995505',0,20.00,'2025-08-13 03:35:58',1,'product'),(27,17,'test3992@example.com','session',1,'TEST522445','TRANS606050',0,30.00,'2025-08-15 03:35:58',1,'product'),(28,11,'lpy3724@foxmail.com','session',75489,'ORDER_14','63H86523YR644552L',1,27.00,'2025-08-18 17:24:25',1,'product'),(29,11,'lpy3724@foxmail.com','session',7859,'ORDER_7','05360698HU343534S',1,19.00,'2025-08-18 17:36:20',1,'product'),(30,11,'lpy3724@foxmail.com','session',75489,'5JP13478R9812005U','5AC99323YD300035T',1,27.00,'2025-08-18 17:56:08',1,'product'),(31,11,'lpy3724@foxmail.com','session',7,'8NY56422V8484414B','4F835632CC185753X',1,67.00,'2025-08-19 09:15:36',1,'product'),(32,11,'lpy3724@foxmail.com','session',7859,'6NY56422V8484414B','5257784264620782S',1,19.00,'2025-08-19 09:17:08',1,'product'),(33,NULL,'sb-kxgca587921@personal.example.com','paypal',75489,'3','5CM91844HL295673R',1,27.00,'2025-08-19 16:25:37',1,'product'),(34,NULL,'sb-kxgca587921@personal.example.com','paypal',75489,'5','35A42747AW615512S',1,27.00,'2025-08-19 16:37:39',0,'product'),(35,14,'493302210@qq.com','session',75489,'1','98F381723D298940U',1,27.00,'2025-08-19 16:48:33',0,'product'),(36,14,'493302210@qq.com','session',75489,'50','4PA93072180962111',1,27.00,'2025-08-19 17:01:05',0,'product'),(38,14,'493302210@qq.com','session',75489,'7','2U366342C25185055',1,27.00,'2025-08-19 17:18:56',0,'product'),(39,14,'493302210@qq.com','session',75489,'0','0B865987WM702752E',1,27.00,'2025-08-19 17:28:38',1,'product'),(41,14,'493302210@qq.com','session',75489,'0S14569077621260D','0S14569077621260D',1,27.00,'2025-08-19 17:46:44',1,'product'),(42,14,'493302210@qq.com','session',75489,'7FL54429MR302441J','8X4876197W246554P',1,27.00,'2025-08-19 17:54:29',1,'product'),(43,14,'493302210@qq.com','session',75489,'9AJ3161955077333Y','15363603ST616180R',1,27.00,'2025-08-19 17:58:38',1,'product'),(44,14,'493302210@qq.com','session',75489,'3J61519023464331L','3UW90340FR386884A',1,27.00,'2025-08-20 09:50:01',1,'product'),(45,NULL,'sb-kxgca587921@personal.example.com','paypal',75489,'2T863720BL8962327','4634835579490553X',1,27.00,'2025-08-20 15:37:08',1,'product'),(47,14,'493302210@qq.com','session',75489,'BAL_1756084878.1824_83984_14','BAL_1756084878.1824_83984_14',0,1.00,'2025-08-25 09:21:18',1,'balance'),(48,20,'test3724@example.com','session',75489,'BAL_1756094365.6664_98719_20','BAL_1756094365.6664_98719_20',0,1.00,'2025-08-25 11:59:25',0,'balance'),(49,21,'lpy3724@foxmail.com','session',23,'BAL_1756112949.4346_19759_21','BAL_1756112949.4346_19759_21',1,45.00,'2025-08-25 17:09:09',0,'balance'),(50,21,'lpy3724@foxmail.com','session',23,'BAL_1756115281.9189_24257_21','BAL_1756115281.9189_24257_21',1,45.00,'2025-08-25 17:48:01',0,'balance'),(51,21,'lpy3724@foxmail.com','session',23,'BAL_1756115468.2479_46653_21','BAL_1756115468.2479_46653_21',1,45.00,'2025-08-25 17:51:08',1,'balance'),(52,21,'lpy3724@foxmail.com','session',23,'BAL_1756116232.1419_10711_21','BAL_1756116232.1419_10711_21',1,45.00,'2025-08-25 18:03:52',1,'balance'),(53,14,'493302210@qq.com','session',75489,'BAL_1756116678.7159_79056_14','BAL_1756116678.7159_79056_14',0,1.00,'2025-08-25 18:11:18',1,'balance'),(54,14,'493302210@qq.com','session',75489,'BAL_1756169082.9493_18007_14','BAL_1756169082.9493_18007_14',1,1.00,'2025-08-26 08:44:42',0,'balance'),(55,14,'493302210@qq.com','session',23,'BAL_1756266594.9389_23833_14','BAL_1756266594.9389_23833_14',1,45.00,'2025-08-27 11:49:54',0,'balance'),(56,14,'493302210@qq.com','session',23,'BAL_1756266816.9883_29510_14','BAL_1756266816.9883_29510_14',1,45.00,'2025-08-27 11:53:36',1,'balance'),(57,14,'493302210@qq.com','session',23,'BAL_1756267034.7369_53483_14','BAL_1756267034.7369_53483_14',1,45.00,'2025-08-27 11:57:14',1,'balance'),(58,22,'li786684763@gmail.com','session',0,'8PW90853FF348723Y','8PW90853FF348723Y',0,100.00,'2025-08-27 13:31:41',1,'activation'),(59,22,'li786684763@gmail.com','session',23,'BAL_1756272724.3836_11007_22','BAL_1756272724.3836_11007_22',1,45.00,'2025-08-27 13:32:04',0,'balance'),(60,22,'li786684763@gmail.com','session',23,'BAL_1756274335.4479_90950_22','BAL_1756274335.4479_90950_22',1,45.00,'2025-08-27 13:58:55',1,'balance'),(61,22,'li786684763@gmail.com','session',23,'BAL_1756274623.6718_26331_22','BAL_1756274623.6718_26331_22',1,45.00,'2025-08-27 14:03:43',1,'balance'),(62,22,'li786684763@gmail.com','session',898,'BAL_1756274800.8253_67471_22','BAL_1756274800.8253_67471_22',1,32.00,'2025-08-27 14:06:40',0,'balance'),(63,22,'li786684763@gmail.com','session',898,'BAL_1756275567.0881_30974_22','BAL_1756275567.0881_30974_22',1,32.00,'2025-08-27 14:19:27',0,'balance'),(64,22,'li786684763@gmail.com','session',898,'BAL_1756276946.3287_72419_22','BAL_1756276946.3287_72419_22',1,32.00,'2025-08-27 14:42:26',1,'balance'),(65,22,'li786684763@gmail.com','session',898,'BAL_1756277148.8416_51564_22','BAL_1756277148.8416_51564_22',1,32.00,'2025-08-27 14:45:48',1,'balance'),(66,22,'li786684763@gmail.com','session',1221,'BAL_1756277225.3241_65303_22','BAL_1756277225.3241_65303_22',0,78.00,'2025-08-27 14:47:05',0,'balance'),(67,22,'li786684763@gmail.com','session',75489,'BAL_1756277616.4981_83091_22','BAL_1756277616.4981_83091_22',0,1.00,'2025-08-27 14:53:36',1,'balance'),(68,22,'li786684763@gmail.com','session',75489,'BAL_1756277661.9408_28547_22','BAL_1756277661.9408_28547_22',1,1.00,'2025-08-27 14:54:21',1,'balance'),(69,22,'li786684763@gmail.com','session',75489,'BAL_1756277709.7829_95669_22','BAL_1756277709.7829_95669_22',0,1.00,'2025-08-27 14:55:09',1,'balance'),(70,22,'li786684763@gmail.com','session',75489,'BAL_1756277733.4059_60508_22','BAL_1756277733.4059_60508_22',1,1.00,'2025-08-27 14:55:33',0,'balance'),(71,22,'li786684763@gmail.com','session',1221,'BAL_1756277743.5018_45524_22','BAL_1756277743.5018_45524_22',0,78.00,'2025-08-27 14:55:43',0,'balance'),(72,22,'li786684763@gmail.com','session',1221,'BAL_1756277904.6968_29793_22','BAL_1756277904.6968_29793_22',0,78.00,'2025-08-27 14:58:24',1,'balance'),(73,22,'li786684763@gmail.com','session',1221,'BAL_1756277921.1444_36883_22','BAL_1756277921.1444_36883_22',1,87.00,'2025-08-27 14:58:41',1,'balance'),(74,22,'li786684763@gmail.com','session',75489,'BAL_1756277987.6854_18501_22','BAL_1756277987.6854_18501_22',1,1.00,'2025-08-27 14:59:47',1,'balance'),(75,22,'li786684763@gmail.com','session',75489,'BAL_1756278060.7434_40817_22','BAL_1756278060.7434_40817_22',0,1.00,'2025-08-27 15:01:00',1,'balance'),(76,22,'li786684763@gmail.com','session',75489,'BAL_1756278174.1338_13610_22','BAL_1756278174.1338_13610_22',1,1.00,'2025-08-27 15:02:54',1,'balance'),(77,22,'li786684763@gmail.com','session',75489,'BAL_1756278212.4138_38348_22','BAL_1756278212.4138_38348_22',1,1.00,'2025-08-27 15:03:32',1,'balance'),(78,22,'li786684763@gmail.com','session',23,'BAL_1756278391.5182_45537_22','BAL_1756278391.5182_45537_22',1,45.00,'2025-08-27 15:06:31',1,'balance'),(79,22,'li786684763@gmail.com','session',23,'BAL_1756279002.1111_75431_22','BAL_1756279002.1111_75431_22',1,45.00,'2025-08-27 15:16:42',1,'balance'),(80,22,'li786684763@gmail.com','session',23,'BAL_1756280386.9094_88872_22','BAL_1756280386.9094_88872_22',1,45.00,'2025-08-27 15:39:46',1,'balance'),(81,22,'li786684763@gmail.com','session',75489,'BAL_1756286704.4526_55930_22','BAL_1756286704.4526_55930_22',1,1.00,'2025-08-27 17:25:04',1,'balance'),(82,22,'li786684763@gmail.com','session',75489,'BAL_1756286913.5908_92515_22','BAL_1756286913.5908_92515_22',0,1.00,'2025-08-27 17:28:33',1,'balance'),(83,22,'li786684763@gmail.com','session',75489,'BAL_1756287226.2642_80109_22','BAL_1756287226.2642_80109_22',0,1.00,'2025-08-27 17:33:46',1,'balance'),(84,22,'li786684763@gmail.com','session',75489,'BAL_1756287267.9191_74145_22','BAL_1756287267.9191_74145_22',1,1.00,'2025-08-27 17:34:27',1,'balance'),(85,22,'li786684763@gmail.com','session',23,'BAL_1756287347.4213_62895_22','BAL_1756287347.4213_62895_22',1,45.00,'2025-08-27 17:35:47',1,'balance'),(87,22,'li786684763@gmail.com','session',898,'BAL_1756287807.5722_82120_22','BAL_1756287807.5722_82120_22',1,32.00,'2025-08-27 17:43:27',1,'balance'),(88,22,'li786684763@gmail.com','session',898,'BAL_1756287892.4562_34144_22','BAL_1756287892.4562_34144_22',1,32.00,'2025-08-27 17:44:52',1,'balance'),(91,22,'li786684763@gmail.com','session',75489,'BAL_1756287933.9284_20656_22','BAL_1756287933.9284_20656_22',1,1.00,'2025-08-27 17:45:33',1,'balance'),(92,22,'li786684763@gmail.com','session',75489,'BAL_1756288037.2273_35573_22','BAL_1756288037.2273_35573_22',0,1.00,'2025-08-27 17:47:17',1,'balance'),(96,22,'li786684763@gmail.com','session',75489,'BAL_1756288233.6111_32895_22','BAL_1756288233.6111_32895_22',0,1.00,'2025-08-27 17:50:33',1,'balance'),(99,22,'li786684763@gmail.com','session',75489,'BAL_1756288438.966_41463_22','BAL_1756288438.966_41463_22',0,1.00,'2025-08-27 17:53:58',1,'balance'),(100,22,'li786684763@gmail.com','session',75489,'BAL_1756288684.2627_18746_22','BAL_1756288684.2627_18746_22',0,1.00,'2025-08-27 17:58:04',1,'balance'),(101,22,'li786684763@gmail.com','session',75489,'BAL_1756341731.9403_30686_22','BAL_1756341731.9403_30686_22',0,1.00,'2025-08-28 08:42:11',1,'balance'),(103,22,'li786684763@gmail.com','session',75489,'BAL_1756342119.3317_27803_22','BAL_1756342119.3317_27803_22',1,1.00,'2025-08-28 08:48:39',1,'balance'),(105,22,'li786684763@gmail.com','session',75489,'BAL_1756342346.2295_40492_22','BAL_1756342346.2295_40492_22',0,1.00,'2025-08-28 08:52:26',1,'balance'),(106,22,'li786684763@gmail.com','session',75489,'BAL_1756342414.075_85562_22','BAL_1756342414.075_85562_22',1,1.00,'2025-08-28 08:53:34',1,'balance'),(107,22,'li786684763@gmail.com','session',23,'BAL_1756364024.3914_67838_22','BAL_1756364024.3914_67838_22',1,45.00,'2025-08-28 14:53:44',1,'balance'),(108,22,'li786684763@gmail.com','session',78927,'9J03299866501533L','4LH719535N320762M',1,4.00,'2025-08-28 15:05:29',1,'product'),(109,22,'li786684763@gmail.com','session',78927,'91H33973M8973260K','9TH216112C749952C',1,4.00,'2025-08-28 15:19:33',1,'product'),(111,22,'li786684763@gmail.com','session',78927,'3D330228GB429664X','71G97164D7159921S',1,4.00,'2025-08-28 15:28:19',1,'product'),(112,22,'li786684763@gmail.com','session',78928,'BAL_1756366249.5814_66743_22','BAL_1756366249.5814_66743_22',0,3.00,'2025-08-28 15:30:49',1,'balance'),(115,22,'li786684763@gmail.com','session',78928,'0FS86218X8432943X','4PU60013VA566961W',1,3.00,'2025-08-28 15:42:21',1,'product'),(118,22,'li786684763@gmail.com','session',78928,'BAL_1756367020.7796_44398_22','BAL_1756367020.7796_44398_22',1,3.00,'2025-08-28 15:43:40',1,'balance'),(119,22,'li786684763@gmail.com','session',78928,'BAL_1756367076.2064_75795_22','BAL_1756367076.2064_75795_22',1,3.00,'2025-08-28 15:44:36',1,'balance'),(120,22,'li786684763@gmail.com','session',78928,'BAL_1756368277.9932_81941_22','BAL_1756368277.9932_81941_22',1,3.00,'2025-08-28 16:04:38',1,'balance'),(121,22,'li786684763@gmail.com','session',78928,'70S991382F496322R','3CF290044C856014P',1,3.00,'2025-08-28 16:13:20',1,'product'),(122,22,'li786684763@gmail.com','session',78929,'0D248092YK6115641','1YS55031MD2582351',1,4.00,'2025-08-28 16:18:17',1,'product'),(123,22,'li786684763@gmail.com','session',78929,'0MC32382D4108160R','1UH28222CA365360J',1,6.00,'2025-08-28 16:20:39',1,'product'),(124,22,'li786684763@gmail.com','session',78929,'0R675157TN974573U','0JN168637L2218354',1,6.00,'2025-08-28 16:45:48',1,'product'),(125,22,'li786684763@gmail.com','session',223,'BAL_1756456165.0022_85465_22','BAL_1756456165.0022_85465_22',0,0.04,'2025-08-29 16:29:25',1,'balance'),(129,23,'1725633271@qq.com','session',0,'5TW42484RW8242137','5TW42484RW8242137',0,100.00,'2025-09-01 14:01:25',1,'activation'),(130,23,'1725633271@qq.com','session',78930,'9A646071KR2690707','9WG32173UP369603D',0,1.00,'2025-09-01 15:43:00',1,'product'),(131,23,'1725633271@qq.com','session',78930,'4JM0525704364960K','78314871W1112503P',0,1.00,'2025-09-01 16:18:19',1,'product'),(132,23,'1725633271@qq.com','session',78931,'BAL_1756863039.6453_69798_23','BAL_1756863039.6453_69798_23',0,12.00,'2025-09-03 09:30:39',1,'balance'),(138,23,'1725633271@qq.com','session',78929,'MULTI_BAL_20250905_4A87A480','MULTI_BAL_20250905_4A87A480',1,6.00,'2025-09-05 16:51:52',1,'balance'),(139,23,'1725633271@qq.com','session',78929,'MULTI_BAL_20250905_4A87AF60','MULTI_BAL_20250905_4A87AF60',0,12.00,'2025-09-05 16:51:52',1,'balance'),(141,23,'1725633271@qq.com','session',23,'MULTI_PP_20250905_006B5C7E','MULTI_PP_20250905_006B5C7E',0,45.00,'2025-09-05 17:40:22',1,'product');
/*!40000 ALTER TABLE `purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES (1,'contact_email','3724lpy@foxmail.com','2025-08-13 02:46:03'),(2,'contact_phone','+86 18657891241','2025-08-28 09:31:09'),(3,'site_title','HairCut Network','2025-08-13 02:43:43'),(4,'site_description','Professional hair cutting tutorials and resources','2025-08-13 02:43:43'),(5,'items_per_page','10','2025-08-13 02:51:10'),(6,'facebook_url','https://facebook.com/haircutting','2025-08-13 02:43:43'),(7,'twitter_url','https://twitter.com/haircutting','2025-08-13 02:43:43'),(8,'instagram_url','https://instagram.com/haircutting','2025-08-13 02:43:43'),(9,'use_gmail_api','1','2025-08-13 07:48:51'),(10,'banner_image','banner_1757062275_3642.jpg','2025-09-05 08:51:15'),(11,'background_image','uploads/backgrounds/background_1756795568.jpg','2025-09-02 06:46:08'),(12,'contact_email2','support@haircut.network1','2025-09-02 07:23:58'),(13,'wechat','haircut_wechat','2025-09-02 07:21:12');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_login_logs`
--

DROP TABLE IF EXISTS `user_login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `login_ip` varchar(45) NOT NULL,
  `login_location` varchar(100) DEFAULT 'æœªçŸ¥',
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_login_time` (`login_time`),
  CONSTRAINT `user_login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_login_logs`
--

LOCK TABLES `user_login_logs` WRITE;
/*!40000 ALTER TABLE `user_login_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_login_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `status`, `is_activated`, `activation_payment_id`, `balance`, `registered_date`, `last_login_ip`, `last_login_country`, `last_login_time`) VALUES (1,'admin','$2y$10$ODcO.hpu87ldcdmDiuGnauzINNBvgXuG5MD9MF4Dy.x/Ob5BXogU6','admin@example.com','Administrator','Active',1,NULL,0.00,'2025-08-07 06:51:37',NULL,NULL,NULL),(13,'lipy9','$2y$12$YxZmo2QnSTTG9DeQBEP6OekwzlfN5Rd9QDZHxOu4MBrKIzeuPyOr2','2806448542@qq.com','Member','Active',1,'868707582M164094U',0.00,'2025-08-15 07:15:21',NULL,NULL,NULL),(14,'lipy','$2y$12$F/mlfvQ6U4/WVXAkNhoYY.HrZfeuyI2EyG3a3bNsYJVb4xmbus2nu','493302210@qq.com','Member','Active',1,'5D00782937968294X',62.00,'2025-08-15 08:48:41','::1','未知','2025-08-27 11:48:38'),(15,'testuser3591','$2y$12$wQXU1goUB1x80rS2WBttkeCOYNyWNCcTMVU8Kt4sfxWk57BGLHWTK','test4424@example.com','Member','Active',1,NULL,0.00,'2025-08-16 03:33:35',NULL,NULL,NULL),(16,'testuser2498','$2y$12$WYK1MH/2RUp0eJiOo4q.iO47S81IWDBBlq5KFIB2zG2g2nzDyo66q','test1062@example.com','Member','Inactive',0,NULL,0.00,'2025-08-16 03:34:05',NULL,NULL,NULL),(17,'testuser3763','$2y$12$M7/rBuxrWFkhQnZbco5QWOXK0SgoDm.in2AGG6GUebT9XrZSgQCwe','test3992@example.com','Editor','Inactive',0,'Auto-activated during login: Total spent over $100',0.00,'2025-08-16 03:35:58',NULL,NULL,NULL),(20,'test','$2y$12$TLHoAUIoeo42fXEUjM1MveLya3qJu7iXgAQJNUoUV8kqAcuhAN2qK','test3724@example.com','Member','Active',1,NULL,99.00,'2025-08-25 03:27:28','5.34.216.84','United States','2025-08-28 14:17:41'),(21,'lll3724','$2y$12$j/wkUQM7dBTBBjRwnn70WOa4qfsByCBFaVzz/R9te0cl4o9/U63AK','lpy3724@foxmail.com','Member','Active',1,'Auto-activated: Total spent over $100',0.00,'2025-08-25 09:04:36','115.60.62.210','China','2025-08-25 17:06:32'),(22,'bj1234','$2y$10$p137SjJDvsLu5RkXoHO0Sez.E2YXzSeNvh6LgQRkCr9fFnzZjikOO','li786684763@gmail.com','Member','Active',1,'8PW90853FF348723Y',1193.96,'2025-08-27 04:01:28','::1','未知','2025-09-08 09:32:44'),(23,'bj123','$2y$10$3P7ACyPfirrtbHnYcKc8bOICRoYJiCeZXPdxzlMvByBhVkGWZNEpy','1725633271@qq.com','Member','Active',1,'5TW42484RW8242137',9969.00,'2025-09-01 06:00:34','::1','未知','2025-09-08 09:24:34');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_codes`
--

DROP TABLE IF EXISTS `verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_codes`
--

LOCK TABLES `verification_codes` WRITE;
/*!40000 ALTER TABLE `verification_codes` DISABLE KEYS */;
INSERT INTO `verification_codes` (`id`, `email`, `code`, `expires_at`, `created_at`) VALUES (2,'lipy3724@gmail.com','195951','2025-08-14 11:03:45','2025-08-14 02:53:45'),(27,'test@example.com','723620','2025-08-14 15:17:02','2025-08-14 07:07:02'),(44,'1558661815@qq.com','441855','2025-08-14 16:20:09','2025-08-14 08:10:09'),(66,'1725633271@qq.com','962571','2025-09-05 09:15:55','2025-09-05 01:05:55');
/*!40000 ALTER TABLE `verification_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'jianfa_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-08 10:13:41
