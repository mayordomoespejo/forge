<?php

declare(strict_types=1);

namespace Forge\Services;

class ContentSafetyService
{
    private const API_VERSION     = '2023-10-01';
    private const BLOCK_SEVERITY  = 4;
    private const MAX_TEXT_LENGTH = 10000;

    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_CONTENT_SAFETY_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_CONTENT_SAFETY_KEY'] ?? '';
    }

    /**
     * Analyses plain text for harmful content across four categories.
     *
     * Returns a safe default when credentials are absent or text is empty.
     *
     * @param  string $text Text to evaluate (truncated to MAX_TEXT_LENGTH characters)
     * @return array{safe: bool, flags: array<int, array{category: string, severity: int}>}
     */
    public function analyzeText(string $text): array
    {
        if ($this->key === '' || trim($text) === '') {
            return ['safe' => true, 'flags' => []];
        }

        $body = json_encode([
            'text'       => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
            'categories' => ['Hate', 'Violence', 'Sexual', 'SelfHarm'],
            'outputType' => 'FourSeverityLevels',
        ]);

        return $this->call($this->endpoint . '/contentsafety/text:analyze?api-version=' . self::API_VERSION, $body);
    }

    /**
     * Analyses an image file for harmful content across four categories.
     *
     * Returns a safe default when credentials are absent or the file cannot be read.
     *
     * @param  string $imagePath Absolute path to the image file
     * @return array{safe: bool, flags: array<int, array{category: string, severity: int}>}
     */
    public function analyzeImage(string $imagePath): array
    {
        if ($this->key === '' || !is_file($imagePath)) {
            return ['safe' => true, 'flags' => []];
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            return ['safe' => true, 'flags' => []];
        }

        $body = json_encode([
            'image'      => ['content' => base64_encode($imageData)],
            'categories' => ['Hate', 'Violence', 'Sexual', 'SelfHarm'],
            'outputType' => 'FourSeverityLevels',
        ]);

        return $this->call($this->endpoint . '/contentsafety/image:analyze?api-version=' . self::API_VERSION, $body);
    }

    /**
     * @return array{safe: bool, flags: array<int, array{category: string, severity: int}>}
     */
    private function call(string $url, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $this->key,
                'Content-Type: application/json',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            return ['safe' => true, 'flags' => []];
        }

        $data  = json_decode($raw, true) ?? [];
        $flags = [];
        $safe  = true;

        foreach ($data['categoriesAnalysis'] ?? [] as $item) {
            $severity = (int) ($item['severity'] ?? 0);
            if ($severity >= self::BLOCK_SEVERITY) {
                $safe    = false;
                $flags[] = ['category' => $item['category'] ?? '', 'severity' => $severity];
            }
        }

        return ['safe' => $safe, 'flags' => $flags];
    }
}
