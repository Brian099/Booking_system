<?php

$config = require __DIR__ . '/config.php';

date_default_timezone_set($config['default_timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // 不再发送 no-store，允许浏览器启用 bfcache（手机切回页面可直接复用）
    session_cache_limiter('');
    session_name($config['session_name']);
    session_start();
    header('Cache-Control: private, no-cache');
}

require_once __DIR__ . '/app/core.php';
require_once __DIR__ . '/app/repositories.php';
require_once __DIR__ . '/app/webhooks.php';
