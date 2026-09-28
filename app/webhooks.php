<?php

function dispatch_post_webhook(string $eventType, int $postId): void
{
    $post = find_post_by_id($postId, true);
    if (!$post) {
        return;
    }

    $payloadArray = [
        'event' => $eventType,
        'sent_at' => now(),
        'post' => [
            'id' => (int) $post['id'],
            'author_id' => (int) $post['author_id'],
            'author_name' => $post['author_name'],
            'content' => $post['content'],
            'expected_ship_date' => $post['expected_ship_date'],
            'created_at' => $post['created_at'],
            'updated_at' => $post['updated_at'],
            'deleted_at' => $post['deleted_at'],
            'tags' => array_map(static function ($tag) {
                return [
                    'id' => (int) $tag['id'],
                    'name' => $tag['name'],
                    'color' => $tag['color'],
                ];
            }, get_post_tags($postId)),
            'images' => array_map(static function ($image) {
                return [
                    'id' => (int) $image['id'],
                    'file_path' => $image['file_path'],
                    'url' => image_url($image['file_path']),
                ];
            }, get_post_images($postId)),
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

    if (!empty($subscription['secret'])) {
        $signature = hash_hmac('sha256', $payload, $subscription['secret']);
        $headers[] = 'X-Webhook-Signature: ' . $signature;
    }

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
