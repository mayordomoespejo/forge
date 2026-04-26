<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

try {
    $input   = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    $message = trim((string) ($input['message'] ?? ''));
    $history = $input['history'] ?? [];

    if ($message === '') {
        echo "data: [DONE]\n\n";
        exit;
    }

    $resultContext = $_SESSION['result'] ?? [];
    $contextJson   = json_encode($resultContext);
    if (strlen($contextJson) > 2000) {
        $contextJson = substr($contextJson, 0, 2000) . '... [truncated]';
    }

    $search    = new Forge\Services\SearchService();
    $ragContext = '';
    if ($search->isConfigured()) {
        $past = $search->search($message, 3);
        if (!empty($past)) {
            $ragContext = "\n\nRelevant past analyses:\n";
            foreach ($past as $r) {
                $ragContext .= '- [' . $r['type'] . '] ' . ($r['filename'] ?: 'text') . ': '
                    . ($r['summary'] ?: mb_substr($r['content'], 0, 200)) . "\n";
            }
        }
    }

    $bingContext = '';
    $bing = new Forge\Services\BingSearchService();
    if ($bing->isConfigured()) {
        $bingContext = $bing->search($message);
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
        [[
            'role'    => 'system',
            'content' => "You are an AI assistant helping analyze content. Context: {$contextJson}. Answer helpfully and concisely.{$ragContext}{$bingContext}",
        ]],
        $cleanHistory,
        [['role' => 'user', 'content' => $message]]
    );

    $token = $_ENV['GITHUB_TOKEN'] ?? '';
    $body  = json_encode([
        'model'    => 'gpt-4o-mini',
        'messages' => $messages,
        'stream'   => true,
    ]);

    $ch = curl_init('https://models.inference.ai.azure.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $body,
        CURLOPT_TIMEOUT       => 60,
        CURLOPT_HTTPHEADER    => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_WRITEFUNCTION => static function ($ch, $data): int {
            echo $data;
            ob_flush();
            flush();
            return strlen($data);
        },
    ]);

    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "data: " . json_encode(['error' => $err]) . "\n\n";
    }

} catch (\Throwable $e) {
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    echo "data: [DONE]\n\n";
}
