<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/bootstrap.php';

$databasePath = app_config('db_path');
$storageDir = dirname($databasePath);

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

if (!is_dir(app_config('upload_dir'))) {
    mkdir(app_config('upload_dir'), 0775, true);
}

$schema = file_get_contents($root . '/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "读取 schema.sql 失败\n");
    exit(1);
}

db()->exec($schema);

$adminPassword = 'admin123';
$stmt = db()->prepare('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE username = :username');
$stmt->execute([
    'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
    'updated_at' => now(),
    'username' => 'admin',
]);

echo "数据库初始化完成\n";
echo "默认管理员账号: admin\n";
echo "默认管理员密码: {$adminPassword}\n";
