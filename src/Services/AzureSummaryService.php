<?php

declare(strict_types=1);

namespace Forge\Services;

class AzureSummaryService
{
    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_LANGUAGE_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_LANGUAGE_KEY'] ?? '';
    }

    /**
     * Extractive summarization — returns top sentences from the original text.
     * Returns null if key is not configured or request fails.
     */
    public function extractive(string $text, int $sentenceCount = 5): ?string
    {
        if ($this->key === '' || trim($text) === '') {
            return null;
        }

        $submitUrl = $this->endpoint . '/language/analyze-text/jobs?api-version=2023-04-01';
        $body      = json_encode([
            'displayName'   => 'forge-summary',
            'analysisInput' => ['documents' => [['id' => '1', 'language' => 'en', 'text' => mb_substr($text, 0, 5000)]]],
            'tasks'         => [['kind' => 'ExtractiveSummarization', 'parameters' => ['sentenceCount' => $sentenceCount]]],
        ]);

        $ch = curl_init($submitUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $this->key,
                'Content-Type: application/json',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 202) {
            return null;
        }

        $headers       = substr($raw, 0, $hdrSize);
        $operationUrl  = null;
        foreach (explode("\r\n", $headers) as $line) {
            if (stripos($line, 'operation-location:') === 0) {
                $operationUrl = trim(substr($line, strlen('operation-location:')));
                break;
            }
        }

        if ($operationUrl === null) {
            return null;
        }

        return $this->pollAndExtract($operationUrl);
    }

    private function pollAndExtract(string $operationUrl): ?string
    {
        for ($i = 0; $i < 20; $i++) {
            sleep(1);

            $ch = curl_init($operationUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    'Ocp-Apim-Subscription-Key: ' . $this->key,
                ],
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);

            if ($raw === false) continue;

            $data   = json_decode($raw, true) ?? [];
            $status = $data['status'] ?? 'running';

            if ($status === 'succeeded') {
                $sentences = $data['tasks']['items'][0]['results']['documents'][0]['sentences'] ?? [];
                usort($sentences, fn($a, $b) => $b['rankScore'] <=> $a['rankScore']);
                $top = array_slice($sentences, 0, 5);
                usort($top, fn($a, $b) => $a['offset'] <=> $b['offset']);
                return implode(' ', array_column($top, 'text'));
            }

            if ($status === 'failed') {
                return null;
            }
        }

        return null;
    }
}
