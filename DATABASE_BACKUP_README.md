# 数据库备份工具使用说明

## 📋 概述

这个工具集为剪发网站数据库提供完整的备份和恢复功能。

## 🗂️ 文件说明

### 核心工具
- `backup_database.php` - 数据库备份脚本
- `restore_database.php` - 数据库恢复脚本
- `database_manager.php` - 数据库管理界面（推荐使用）

### 备份目录
- `database_backups/` - 存放所有备份文件的目录
- `backup_info.txt` - 最新备份的信息文件

## 🚀 快速开始

### 方法一：使用管理工具（推荐）
```bash
/Applications/XAMPP/xamppfiles/bin/php database_manager.php
```

这将启动一个交互式菜单，提供以下功能：
1. 创建数据库备份
2. 查看现有备份
3. 恢复数据库
4. 查看数据库状态
5. 删除旧备份

### 方法二：直接使用备份脚本
```bash
# 创建备份
/Applications/XAMPP/xamppfiles/bin/php backup_database.php

# 恢复数据库
/Applications/XAMPP/xamppfiles/bin/php restore_database.php
```

## 📊 当前备份状态

**最新备份信息：**
- 备份文件：`jianfa_db_backup_2025-09-10_07-58-32.sql`
- 备份时间：2025-09-10 07:58:32
- 数据库：jianfa_db
- 表数量：14 个
- 文件大小：0.03 MB

**数据统计：**
- categories: 11 行 (产品分类)
- products: 23 行 (产品信息)
- users: 10 行 (用户信息)
- hair: 5 行 (头发信息)
- settings: 11 行 (系统设置)
- verification_codes: 4 行 (验证码)
- login_logs: 1 行 (登录日志)
- 其他表：主要为空表，用于扩展功能

## ⚠️ 重要提醒

### 备份安全
1. **定期备份**：建议每天或每周创建备份
2. **多地存储**：将重要备份复制到云盘或其他设备
3. **测试恢复**：定期测试备份文件是否可以正常恢复

### 恢复注意事项
1. **数据覆盖**：恢复操作会完全覆盖当前数据库
2. **创建备份**：恢复前建议先创建当前数据的备份
3. **确认操作**：恢复前会要求输入 'yes' 确认

## 🔧 故障排除

### 常见问题

**Q: PHP命令找不到**
A: 使用完整路径：`/Applications/XAMPP/xamppfiles/bin/php`

**Q: 数据库连接失败**
A: 检查XAMPP是否启动，确认数据库配置正确

**Q: 备份文件过大**
A: 考虑定期清理旧备份，或使用压缩

**Q: 恢复失败**
A: 检查备份文件完整性，确认数据库权限

## 📁 备份文件命名规则

格式：`jianfa_db_backup_YYYY-MM-DD_HH-mm-ss.sql`

示例：`jianfa_db_backup_2025-09-10_07-58-32.sql`

## 🔄 自动化建议

### 定时备份（可选）
可以设置 cron 任务定期备份：

```bash
# 每天凌晨2点备份
0 2 * * * /Applications/XAMPP/xamppfiles/bin/php /path/to/backup_database.php
```

### 备份保留策略
- 保留最近 7 天的每日备份
- 保留最近 4 周的每周备份
- 保留最近 12 个月的每月备份

## 📞 技术支持

如果遇到问题，请检查：
1. XAMPP 服务是否正常运行
2. 数据库连接配置是否正确
3. 文件权限是否充足
4. 磁盘空间是否充足

---

*最后更新：2025-09-10*
*工具版本：1.0*
