<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

header('Content-Type: application/json');

try {
    $input   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    $history = $input['history'] ?? [];

    if (empty($history)) {
        echo json_encode(['success' => false, 'error' => 'No conversation to summarize.']);
        exit;
    }

    $conversation = '';
    foreach ($history as $msg) {
        $role    = ucfirst($msg['role'] ?? 'user');
        $content = $msg['content'] ?? '';
        if ($content !== '') {
            $conversation .= "{$role}: {$content}\n";
        }
    }

    $messages = [
        [
            'role'    => 'system',
            'content' => 'You are a conversation summarizer. Summarize the following conversation in 3-5 sentences, focusing on the key questions asked and conclusions reached.',
        ],
        [
            'role'    => 'user',
            'content' => $conversation,
        ],
    ];

    $chat    = new Forge\Services\ChatService();
    $summary = $chat->chat($messages);

    echo json_encode(['success' => true, 'summary' => $summary]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
