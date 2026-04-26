<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

try {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    $text  = trim((string) ($input['text'] ?? ''));

    if ($text === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Empty text.']);
        exit;
    }

    $tts   = new Forge\Services\TtsService();
    $audio = $tts->synthesize($text);

    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($audio));
    header('Cache-Control: no-store');
    echo $audio;

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
