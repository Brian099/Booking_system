# 订货记录系统

一个基于 `PHP 7.4 + SQLite` 的轻量内部订货记录系统，核心以 `Post` 为中心，支持图片附件、标签协作、注释代办、回收站和 Webhook 通知。

## 功能

- 多账户登录
- 我的 Post / 全部 Post
- Post 创建、编辑、软删除、回收站恢复
- 图片上传与截图粘贴
- 多标签协作
- Post 注释
- 今日注释汇总与复选框代办
- 关键词、作者、日期、标签、期望发货期筛选
- Webhook 事件：`post.created` / `post.updated` / `post.deleted`

## 初始化

在项目根目录执行：

```bash
php scripts/init_db.php
```

启动本地服务：

```bash
php -S 127.0.0.1:8000 -t public
```

浏览器访问：

```text
http://127.0.0.1:8000
```

默认管理员账号：

- 用户名：`admin`
- 密码：`admin123`

## 目录

- `public/` Web 入口、静态资源、上传目录
- `app/` 核心函数、数据库操作、Webhook 分发
- `scripts/init_db.php` 初始化数据库
- `schema.sql` SQLite 表结构
