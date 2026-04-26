<?php

declare(strict_types=1);

namespace Forge\Services;

class HealthcareNerService
{
    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_LANGUAGE_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_LANGUAGE_KEY'] ?? '';
    }

    /**
     * Extracts healthcare named entities from clinical or medical text.
     *
     * Submits an async job to Azure AI Language Healthcare NER and polls until
     * the result is available. Returns an empty array when credentials are absent,
     * the text is empty, or the API returns an error.
     *
     * @param  string $text Clinical or medical text (truncated to 5000 characters)
     * @return array<int, array{text: string, category: string, confidence: float}>
     */
    public function analyze(string $text): array
    {
        if ($this->key === '' || trim($text) === '') {
            return [];
        }

        $submitUrl = $this->endpoint . '/text/analytics/v3.1/entities/health/jobs';
        $body      = json_encode([
            'documents' => [['id' => '1', 'language' => 'en', 'text' => mb_substr($text, 0, 5000)]],
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
            return [];
        }

        $headers      = substr($raw, 0, $hdrSize);
        $operationUrl = null;
        foreach (explode("\r\n", $headers) as $line) {
            if (stripos($line, 'operation-location:') === 0) {
                $operationUrl = trim(substr($line, strlen('operation-location:')));
                break;
            }
        }

        if ($operationUrl === null) {
            return [];
        }

        return $this->pollAndExtract($operationUrl);
    }

    /**
     * @return array<int, array{text: string, category: string, confidence: float}>
     */
    private function pollAndExtract(string $operationUrl): array
    {
        for ($i = 0; $i < 20; $i++) {
            sleep(1);

            $ch = curl_init($operationUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => ['Ocp-Apim-Subscription-Key: ' . $this->key],
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);

            if ($raw === false) continue;

            $data   = json_decode($raw, true) ?? [];
            $status = $data['status'] ?? 'running';

            if ($status === 'succeeded') {
                $entities = $data['results']['documents'][0]['entities'] ?? [];
                return array_map(fn($e) => [
                    'text'       => $e['text']             ?? '',
                    'category'   => $e['category']         ?? '',
                    'confidence' => (float) ($e['confidenceScore'] ?? 0.0),
                ], $entities);
            }

            if ($status === 'failed') {
                return [];
            }
        }

        return [];
    }
}
