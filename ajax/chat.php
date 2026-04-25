<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

header('Content-Type: application/json');

try {
    $input   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    $message = trim((string) ($input['message'] ?? $_POST['message'] ?? ''));
    $history = $input['history'] ?? [];

    if ($message === '') {
        echo json_encode(['success' => false, 'error' => 'Empty message.']);
        exit;
    }

    $resultContext = $_SESSION['result'] ?? [];
    $contextJson   = json_encode($resultContext);

    if (strlen($contextJson) > 2000) {
        $contextJson = substr($contextJson, 0, 2000) . '... [truncated for brevity]';
    }

    $cleanHistory = [];
    foreach ($history as $item) {
        $role    = in_array($item['role'] ?? '', ['user', 'assistant', 'system']) ? $item['role'] : 'user';
        $content = (string) ($item['content'] ?? '');
        if ($content !== '') {
            $cleanHistory[] = ['role' => $role, 'content' => $content];
        }
    }

    $messages = array_merge(
        [
            [
                'role'    => 'system',
                'content' => "You are an AI assistant helping analyze content. Context of the analyzed content: {$contextJson}. Answer questions about this content helpfully and concisely.",
            ],
        ],
        $cleanHistory,
        [
            ['role' => 'user', 'content' => $message],
        ]
    );

    $chat  = new Forge\Services\ChatService();
    $reply = $chat->chat($messages);

    echo json_encode(['success' => true, 'message' => $reply]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
