<?php

function find_user_by_username(string $username): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function find_user_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function list_users(): array
{
    $stmt = db()->query('SELECT * FROM users ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function create_user(string $username, string $password, string $displayName, string $role = 'user'): int
{
    $time = now();
    $stmt = db()->prepare(
        'INSERT INTO users (username, password_hash, display_name, role, created_at, updated_at)
         VALUES (:username, :password_hash, :display_name, :role, :created_at, :updated_at)'
    );
    $stmt->execute([
        'username' => trim($username),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => trim($displayName),
        'role' => $role === 'admin' ? 'admin' : 'user',
        'created_at' => $time,
        'updated_at' => $time,
    ]);

    return (int) db()->lastInsertId();
}

function update_user_profile(int $id, string $displayName, ?string $newPassword = null): void
{
    $time = now();
    if ($newPassword !== null && $newPassword !== '') {
        $stmt = db()->prepare('UPDATE users SET display_name = :display_name, password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'display_name' => trim($displayName),
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => $time,
        ]);
    } else {
        $stmt = db()->prepare('UPDATE users SET display_name = :display_name, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'display_name' => trim($displayName),
            'updated_at' => $time,
        ]);
    }
}

function list_tags(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM tags';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';

    return db()->query($sql)->fetchAll();
}

function find_tag_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM tags WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $tag = $stmt->fetch();

    return $tag ?: null;
}

function create_tag(string $name, string $color = '#2563eb', int $sortOrder = 0): int
{
    $time = now();
    $stmt = db()->prepare(
        'INSERT INTO tags (name, color, sort_order, is_active, created_at, updated_at)
         VALUES (:name, :color, :sort_order, 1, :created_at, :updated_at)'
    );
    $stmt->execute([
        'name' => trim($name),
        'color' => trim($color) ?: '#2563eb',
        'sort_order' => $sortOrder,
        'created_at' => $time,
        'updated_at' => $time,
    ]);

    return (int) db()->lastInsertId();
}

function update_tag(int $id, string $name, string $color, int $sortOrder, int $isActive): void
{
    $stmt = db()->prepare(
        'UPDATE tags
         SET name = :name, color = :color, sort_order = :sort_order, is_active = :is_active, updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'name' => trim($name),
        'color' => trim($color) ?: '#2563eb',
        'sort_order' => $sortOrder,
        'is_active' => $isActive ? 1 : 0,
        'updated_at' => now(),
    ]);
}

function touch_post(int $postId): void
{
    $stmt = db()->prepare('UPDATE posts SET updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'id' => $postId,
        'updated_at' => now(),
    ]);
}

function create_post(int $authorId, string $content, string $expectedShipDate, array $images): int
{
    $time = now();
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO posts (author_id, content, expected_ship_date, created_at, updated_at)
             VALUES (:author_id, :content, :expected_ship_date, :created_at, :updated_at)'
        );
        $stmt->execute([
            'author_id' => $authorId,
            'content' => trim($content),
            'expected_ship_date' => $expectedShipDate,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        $postId = (int) $pdo->lastInsertId();

        if ($images) {
            $imageStmt = $pdo->prepare(
                'INSERT INTO post_images (post_id, file_path, original_name, sort_order, created_at)
                 VALUES (:post_id, :file_path, :original_name, :sort_order, :created_at)'
            );
            foreach ($images as $index => $image) {
                $imageStmt->execute([
                    'post_id' => $postId,
                    'file_path' => $image['file_path'],
                    'original_name' => $image['original_name'],
                    'sort_order' => $index + 1,
                    'created_at' => $time,
                ]);
            }
        }

        $pdo->commit();
        return $postId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function update_post_record(int $postId, string $content, string $expectedShipDate, array $newImages, array $removeImageIds = []): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'UPDATE posts
             SET content = :content, expected_ship_date = :expected_ship_date, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $postId,
            'content' => trim($content),
            'expected_ship_date' => $expectedShipDate,
            'updated_at' => now(),
        ]);

        if ($removeImageIds) {
            $placeholders = implode(',', array_fill(0, count($removeImageIds), '?'));
            $fetchStmt = $pdo->prepare("SELECT * FROM post_images WHERE post_id = ? AND id IN ($placeholders)");
            $fetchStmt->execute(array_merge([$postId], $removeImageIds));
            $images = $fetchStmt->fetchAll();

            foreach ($images as $image) {
                $absolutePath = app_config('upload_dir') . '/' . ltrim($image['file_path'], '/');
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }

            $deleteStmt = $pdo->prepare("DELETE FROM post_images WHERE post_id = ? AND id IN ($placeholders)");
            $deleteStmt->execute(array_merge([$postId], $removeImageIds));
        }

        if ($newImages) {
            $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM post_images WHERE post_id = ' . (int) $postId)->fetchColumn();
            $imageStmt = $pdo->prepare(
                'INSERT INTO post_images (post_id, file_path, original_name, sort_order, created_at)
                 VALUES (:post_id, :file_path, :original_name, :sort_order, :created_at)'
            );
            foreach ($newImages as $offset => $image) {
                $imageStmt->execute([
                    'post_id' => $postId,
                    'file_path' => $image['file_path'],
                    'original_name' => $image['original_name'],
                    'sort_order' => $maxSort + $offset + 1,
                    'created_at' => now(),
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function soft_delete_post(int $postId, int $deletedByUserId): void
{
    $stmt = db()->prepare(
        'UPDATE posts
         SET deleted_at = :deleted_at, deleted_by_user_id = :deleted_by_user_id, updated_at = :updated_at
         WHERE id = :id'
    );
    $time = now();
    $stmt->execute([
        'id' => $postId,
        'deleted_at' => $time,
        'deleted_by_user_id' => $deletedByUserId,
        'updated_at' => $time,
    ]);
}

function restore_post(int $postId): void
{
    $stmt = db()->prepare(
        'UPDATE posts
         SET deleted_at = NULL, deleted_by_user_id = NULL, updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $postId,
        'updated_at' => now(),
    ]);
}

function find_post_by_id(int $postId, bool $includeDeleted = false): ?array
{
    $sql = '
        SELECT p.*, u.display_name AS author_name, u.username AS author_username
        FROM posts p
        INNER JOIN users u ON u.id = p.author_id
        WHERE p.id = :id
    ';

    if (!$includeDeleted) {
        $sql .= ' AND p.deleted_at IS NULL';
    }

    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();

    return $post ?: null;
}

function get_post_images(int $postId): array
{
    $stmt = db()->prepare('SELECT * FROM post_images WHERE post_id = :post_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['post_id' => $postId]);
    return $stmt->fetchAll();
}

function get_post_tags(int $postId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, pt.tagged_by_user_id, u.display_name AS tagged_by_name
         FROM post_tags pt
         INNER JOIN tags t ON t.id = pt.tag_id
         LEFT JOIN users u ON u.id = pt.tagged_by_user_id
         WHERE pt.post_id = :post_id
         ORDER BY t.sort_order ASC, t.id ASC'
    );
    $stmt->execute(['post_id' => $postId]);
    return $stmt->fetchAll();
}

function get_post_comments(int $postId): array
{
    $stmt = db()->prepare(
        'SELECT c.*, u.display_name AS user_name
         FROM post_comments c
         INNER JOIN users u ON u.id = c.user_id
         WHERE c.post_id = :post_id
         ORDER BY c.created_at DESC'
    );
    $stmt->execute(['post_id' => $postId]);
    return $stmt->fetchAll();
}

function list_posts(array $filters, string $scope, int $currentUserId, bool $includeDeleted = false): array
{
    $conditions = [];
    $params = [];

    if ($scope === 'mine') {
        $conditions[] = 'p.author_id = :author_scope_id';
        $params['author_scope_id'] = $currentUserId;
    }

    if ($includeDeleted) {
        $conditions[] = 'p.deleted_at IS NOT NULL';
    } else {
        $conditions[] = 'p.deleted_at IS NULL';
    }

    if (!empty($filters['keyword'])) {
        $conditions[] = '(p.content LIKE :keyword OR u.display_name LIKE :keyword OR EXISTS (
            SELECT 1
            FROM post_tags pt2
            INNER JOIN tags t2 ON t2.id = pt2.tag_id
            WHERE pt2.post_id = p.id AND t2.name LIKE :keyword
        ))';
        $params['keyword'] = '%' . trim($filters['keyword']) . '%';
    }

    if (!empty($filters['author_id'])) {
        $conditions[] = 'p.author_id = :author_id';
        $params['author_id'] = (int) $filters['author_id'];
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 'date(p.updated_at) >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'date(p.updated_at) <= :date_to';
        $params['date_to'] = $filters['date_to'];
    }

    if (!empty($filters['expected_ship_date'])) {
        $conditions[] = 'p.expected_ship_date = :expected_ship_date';
        $params['expected_ship_date'] = $filters['expected_ship_date'];
    }

    if (!empty($filters['tag_id'])) {
        $conditions[] = 'EXISTS (
            SELECT 1 FROM post_tags pt
            WHERE pt.post_id = p.id AND pt.tag_id = :tag_id
        )';
        $params['tag_id'] = (int) $filters['tag_id'];
    }

    $sql = '
        SELECT p.*, u.display_name AS author_name, u.username AS author_username
        FROM posts p
        INNER JOIN users u ON u.id = p.author_id
    ';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY p.updated_at DESC, p.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    foreach ($posts as &$post) {
        $post['images'] = get_post_images((int) $post['id']);
        $post['tags'] = get_post_tags((int) $post['id']);
        $post['comment_count'] = count_post_comments((int) $post['id']);
    }

    return $posts;
}

function count_post_comments(int $postId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM post_comments WHERE post_id = :post_id');
    $stmt->execute(['post_id' => $postId]);
    return (int) $stmt->fetchColumn();
}

function replace_post_tags(int $postId, array $tagIds, int $taggedByUserId): void
{
    $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $deleteStmt = $pdo->prepare('DELETE FROM post_tags WHERE post_id = :post_id');
        $deleteStmt->execute(['post_id' => $postId]);

        if ($tagIds) {
            $insertStmt = $pdo->prepare(
                'INSERT INTO post_tags (post_id, tag_id, tagged_by_user_id, created_at)
                 VALUES (:post_id, :tag_id, :tagged_by_user_id, :created_at)'
            );
            foreach ($tagIds as $tagId) {
                $insertStmt->execute([
                    'post_id' => $postId,
                    'tag_id' => $tagId,
                    'tagged_by_user_id' => $taggedByUserId,
                    'created_at' => now(),
                ]);
            }
        }

        touch_post($postId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function create_comment(int $postId, int $userId, string $content): int
{
    $stmt = db()->prepare(
        'INSERT INTO post_comments (post_id, user_id, content, is_done, done_at, created_at, updated_at)
         VALUES (:post_id, :user_id, :content, 0, NULL, :created_at, :updated_at)'
    );
    $time = now();
    $stmt->execute([
        'post_id' => $postId,
        'user_id' => $userId,
        'content' => trim($content),
        'created_at' => $time,
        'updated_at' => $time,
    ]);

    touch_post($postId);

    return (int) db()->lastInsertId();
}

function find_comment_by_id(int $commentId): ?array
{
    $stmt = db()->prepare('SELECT * FROM post_comments WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return $comment ?: null;
}

function toggle_comment_done(int $commentId): void
{
    $comment = find_comment_by_id($commentId);
    if (!$comment) {
        return;
    }

    $isDone = (int) $comment['is_done'] === 1 ? 0 : 1;
    $doneAt = $isDone ? now() : null;
    $stmt = db()->prepare(
        'UPDATE post_comments
         SET is_done = :is_done, done_at = :done_at, updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $commentId,
        'is_done' => $isDone,
        'done_at' => $doneAt,
        'updated_at' => now(),
    ]);

    touch_post((int) $comment['post_id']);
}

function list_today_comments(string $status = 'all'): array
{
    $conditions = ['date(c.created_at) = :comment_date'];
    $params = ['comment_date' => today()];

    if ($status === 'pending') {
        $conditions[] = 'c.is_done = 0';
    } elseif ($status === 'done') {
        $conditions[] = 'c.is_done = 1';
    }

    $sql = '
        SELECT c.*, p.content AS post_content, p.id AS post_id, u.display_name AS user_name, au.display_name AS post_author_name
        FROM post_comments c
        INNER JOIN posts p ON p.id = c.post_id
        INNER JOIN users u ON u.id = c.user_id
        INNER JOIN users au ON au.id = p.author_id
        WHERE ' . implode(' AND ', $conditions) . ' AND p.deleted_at IS NULL
        ORDER BY c.is_done ASC, c.created_at DESC
    ';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function list_webhooks(): array
{
    return db()->query('SELECT * FROM webhook_subscriptions ORDER BY created_at DESC')->fetchAll();
}

function create_webhook(string $name, string $targetUrl, string $secret): int
{
    $time = now();
    $stmt = db()->prepare(
        'INSERT INTO webhook_subscriptions (name, target_url, secret, is_active, created_at, updated_at)
         VALUES (:name, :target_url, :secret, 1, :created_at, :updated_at)'
    );
    $stmt->execute([
        'name' => trim($name),
        'target_url' => trim($targetUrl),
        'secret' => trim($secret),
        'created_at' => $time,
        'updated_at' => $time,
    ]);

    return (int) db()->lastInsertId();
}

function toggle_webhook(int $id): void
{
    $stmt = db()->prepare(
        'UPDATE webhook_subscriptions
         SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'updated_at' => now(),
    ]);
}

function record_webhook_log(?int $subscriptionId, string $eventType, string $payload, ?int $responseStatus, ?string $responseBody, bool $isSuccess, ?string $errorMessage): void
{
    $stmt = db()->prepare(
        'INSERT INTO webhook_logs (
            subscription_id, event_type, payload, response_status, response_body, is_success, error_message, created_at
         ) VALUES (
            :subscription_id, :event_type, :payload, :response_status, :response_body, :is_success, :error_message, :created_at
         )'
    );
    $stmt->execute([
        'subscription_id' => $subscriptionId,
        'event_type' => $eventType,
        'payload' => $payload,
        'response_status' => $responseStatus,
        'response_body' => $responseBody,
        'is_success' => $isSuccess ? 1 : 0,
        'error_message' => $errorMessage,
        'created_at' => now(),
    ]);
}

function list_recent_webhook_logs(): array
{
    $sql = '
        SELECT wl.*, ws.name AS webhook_name
        FROM webhook_logs wl
        LEFT JOIN webhook_subscriptions ws ON ws.id = wl.subscription_id
        ORDER BY wl.created_at DESC, wl.id DESC
        LIMIT 50
    ';

    return db()->query($sql)->fetchAll();
}

function list_tags_with_counts(bool $onlyActive = true): array
{
    $sql = '
        SELECT t.*, COUNT(pt.id) AS post_count
        FROM tags t
        LEFT JOIN post_tags pt ON pt.tag_id = t.id
        LEFT JOIN posts p ON p.id = pt.post_id AND p.deleted_at IS NULL
    ';
    if ($onlyActive) {
        $sql .= ' WHERE t.is_active = 1 ';
    }
    $sql .= ' GROUP BY t.id ORDER BY t.sort_order ASC, t.id ASC';

    return db()->query($sql)->fetchAll();
}

function get_active_post_dates(string $yearMonth, string $scope = 'all', int $currentUserId = 0): array
{
    $conditions = [
        "strftime('%Y-%m', p.created_at) = :ym",
        'p.deleted_at IS NULL'
    ];
    $params = ['ym' => $yearMonth];

    if ($scope === 'mine' && $currentUserId > 0) {
        $conditions[] = 'p.author_id = :author_id';
        $params['author_id'] = $currentUserId;
    }

    $sql = '
        SELECT DISTINCT strftime("%Y-%m-%d", p.created_at) AS post_date, COUNT(*) AS count
        FROM posts p
        WHERE ' . implode(' AND ', $conditions) . '
        GROUP BY post_date
    ';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $result[$row['post_date']] = (int) $row['count'];
    }

    return $result;
}

