<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

header('Content-Type: application/json');

try {
    $input    = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    $text     = trim((string) ($input['text'] ?? ''));
    $language = trim((string) ($input['language'] ?? 'en'));

    $allowed = ['en','es','fr','de','pt','it','ja','zh-Hans','ar','ko','ru','nl','pl','tr','sv'];
    if (!in_array($language, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid target language.']);
        exit;
    }

    if ($text === '') {
        echo json_encode(['success' => false, 'error' => 'Empty text.']);
        exit;
    }

    $service    = new Forge\Services\TranslationService();
    $translated = $service->translate($text, $language);

    echo json_encode(['success' => true, 'translated' => $translated, 'language' => $language]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
