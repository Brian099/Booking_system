<?php

function dispatch_post_webhook(string $eventType, int $postId): void
{
    $post = find_post_by_id($postId, true);
    if (!$post) {
        return;
    }

    $actionText = [
        'post.created' => '发布',
        'post.updated' => '修改',
        'post.deleted' => '删除',
        'post.restored' => '恢复',
    ][$eventType] ?? '更新';

    $creator = !empty($post['author_name']) ? $post['author_name'] : ($post['author_username'] ?? '订货员');
    $content = (string) $post['content'];

    // 拼接详细信息链接
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $detailUrl = $scheme . '://' . $host . '/?route=post-view&id=' . (int) $post['id'];

    // 按照参考文件格式拼接 content 文本
    $textContent = "{$creator} [{$actionText}] 了订单！请及时查看并处理！\n- 关键词：{$content}\n\n查看详细信息：\n{$detailUrl}";

    // 标准机器人与客户端接收的 JSON 格式
    $payloadArray = [
        'msgtype' => 'text',
        'text' => [
            'content' => $textContent,
        ],
    ];

    $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $subscriptions = array_filter(list_webhooks(), static function ($item) {
        return (int) $item['is_active'] === 1;
    });

    foreach ($subscriptions as $subscription) {
        send_webhook_request($subscription, $eventType, $payload);
    }
}

function send_webhook_request(array $subscription, string $eventType, string $payload): void
{
    $headers = [
        'Content-Type: application/json',
        'X-Webhook-Event: ' . $eventType,
    ];

    $ch = curl_init($subscription['target_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
    ]);

    $responseBody = curl_exec($ch);
    $errorMessage = null;
    $statusCode = null;
    $isSuccess = false;

    if ($responseBody === false) {
        $errorMessage = curl_error($ch);
    } else {
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
    }

    curl_close($ch);

    record_webhook_log(
        (int) $subscription['id'],
        $eventType,
        $payload,
        $statusCode,
        $responseBody === false ? null : (string) $responseBody,
        $isSuccess,
        $errorMessage
    );
}
