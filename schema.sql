PRAGMA foreign_keys = OFF;

DROP TABLE IF EXISTS webhook_logs;
DROP TABLE IF EXISTS webhook_subscriptions;
DROP TABLE IF EXISTS post_comments;
DROP TABLE IF EXISTS post_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS post_images;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;

PRAGMA foreign_keys = ON;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    expected_ship_date TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT NULL,
    deleted_by_user_id INTEGER NULL,
    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (deleted_by_user_id) REFERENCES users(id)
);

CREATE TABLE post_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    file_path TEXT NOT NULL,
    original_name TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    color TEXT NOT NULL DEFAULT '#2563eb',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE post_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    tagged_by_user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id),
    FOREIGN KEY (tagged_by_user_id) REFERENCES users(id)
);

CREATE TABLE post_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    is_done INTEGER NOT NULL DEFAULT 0,
    done_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE webhook_subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    target_url TEXT NOT NULL,
    secret TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE webhook_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subscription_id INTEGER NULL,
    event_type TEXT NOT NULL,
    payload TEXT NOT NULL,
    response_status INTEGER NULL,
    response_body TEXT NULL,
    is_success INTEGER NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (subscription_id) REFERENCES webhook_subscriptions(id)
);

CREATE INDEX idx_posts_author_id ON posts(author_id);
CREATE INDEX idx_posts_updated_at ON posts(updated_at);
CREATE INDEX idx_posts_deleted_at ON posts(deleted_at);
CREATE INDEX idx_post_images_post_id ON post_images(post_id);
CREATE INDEX idx_post_tags_post_id ON post_tags(post_id);
CREATE INDEX idx_post_tags_tag_id ON post_tags(tag_id);
CREATE INDEX idx_post_comments_post_id ON post_comments(post_id);
CREATE INDEX idx_post_comments_created_at ON post_comments(created_at);

INSERT INTO users (username, password_hash, display_name, role, created_at, updated_at)
VALUES ('admin', '$2y$10$9FvWlCaKMluZFjST01N06OzOj2v4LPgAvFp8ZUBKbjDgq09uXBJtu', '管理员', 'admin', datetime('now', 'localtime'), datetime('now', 'localtime'));

INSERT INTO tags (name, color, sort_order, is_active, created_at, updated_at)
VALUES
('仓库', '#2563eb', 1, 1, datetime('now', 'localtime'), datetime('now', 'localtime')),
('市场', '#16a34a', 2, 1, datetime('now', 'localtime'), datetime('now', 'localtime')),
('工厂', '#ea580c', 3, 1, datetime('now', 'localtime'), datetime('now', 'localtime')),
('部分仓库', '#7c3aed', 4, 1, datetime('now', 'localtime'), datetime('now', 'localtime')),
('部分市场', '#db2777', 5, 1, datetime('now', 'localtime'), datetime('now', 'localtime')),
('部分工厂', '#0891b2', 6, 1, datetime('now', 'localtime'), datetime('now', 'localtime'));
