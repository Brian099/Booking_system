<?php

function app_config(string $key = null)
{
    static $config;

    if ($config === null) {
        $config = require dirname(__DIR__) . '/config.php';
    }

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? null;
}

function db(): PDO
{
    static $pdo;

    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . app_config('db_path'));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    return $pdo;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user;
    if ($user === null || (int) $user['id'] !== (int) $_SESSION['user_id']) {
        $user = find_user_by_id((int) $_SESSION['user_id']);
    }

    return $user;
}

function login_user(array $user): void
{
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', '请先登录');
        redirect('/?route=login');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? 'user') !== 'admin') {
        flash('error', '此页面仅管理员可访问');
        redirect('/?route=posts');
    }

    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user && ($user['role'] ?? 'user') === 'admin';
}

function flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;
        return null;
    }

    if (!isset($_SESSION['flash'][$type])) {
        return null;
    }

    $value = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);

    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('CSRF token 无效');
    }
}

function is_post_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function route_name(): string
{
    return $_GET['route'] ?? 'posts';
}

function old_input(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function ensure_upload_directory(): void
{
    $directory = app_config('upload_dir');
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

function detect_image_mime_type(string $filePath): string
{
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($filePath);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    $imageInfo = @getimagesize($filePath);
    if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
        return (string) $imageInfo['mime'];
    }

    return '';
}

function store_uploaded_images(array $fileInput): array
{
    ensure_upload_directory();

    if (empty($fileInput['name']) || !is_array($fileInput['name'])) {
        return [];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $saved = [];
    $targetFolder = app_config('upload_dir') . '/' . date('Y/m');
    if (!is_dir($targetFolder)) {
        mkdir($targetFolder, 0775, true);
    }

    foreach ($fileInput['name'] as $index => $originalName) {
        if (($fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($fileInput['error'][$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('图片上传失败，请重试');
        }

        $tmpName = $fileInput['tmp_name'][$index] ?? '';
        $size = (int) ($fileInput['size'][$index] ?? 0);
        $mime = detect_image_mime_type($tmpName);

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('仅支持 JPG、PNG、WEBP、GIF 图片');
        }

        if ($size > 10 * 1024 * 1024) {
            throw new RuntimeException('单张图片大小不能超过 10MB');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = $targetFolder . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('保存图片失败');
        }

        $relativePath = date('Y/m') . '/' . $filename;
        $saved[] = [
            'file_path' => $relativePath,
            'original_name' => (string) $originalName,
        ];
    }

    return $saved;
}

function image_url(string $relativePath): string
{
    return rtrim(app_config('upload_url'), '/') . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function can_edit_post(array $post, array $user): bool
{
    return (int) $post['author_id'] === (int) $user['id'] || ($user['role'] ?? 'user') === 'admin';
}

function page_title(string $title): string
{
    return $title . ' - ' . app_config('app_name');
}
