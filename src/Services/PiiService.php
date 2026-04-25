<?php

declare(strict_types=1);

namespace Forge\Services;

class PiiService
{
    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_LANGUAGE_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_LANGUAGE_KEY'] ?? '';
    }

    /**
     * @return array{redacted_text: string, pii_found: array<int, array{text: string, category: string}>}
     */
    public function redact(string $text): array
    {
        if ($text === '' || $this->key === '') {
            return ['redacted_text' => $text, 'pii_found' => []];
        }

        $url  = $this->endpoint . '/text/analytics/v3.1/entities/recognition/pii';
        $body = json_encode(['documents' => [['id' => '1', 'text' => $text]]]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $this->key,
                'Content-Type: application/json',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            return ['redacted_text' => $text, 'pii_found' => []];
        }

        $data = json_decode($raw, true) ?? [];
        $doc  = $data['documents'][0] ?? [];

        $redacted = $doc['redactedText'] ?? $text;
        $entities = array_map(
            fn($e) => ['text' => $e['text'] ?? '', 'category' => $e['category'] ?? ''],
            $doc['entities'] ?? []
        );

        return ['redacted_text' => $redacted, 'pii_found' => $entities];
    }
}
