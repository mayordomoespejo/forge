<?php

declare(strict_types=1);

namespace Forge\Services;

class ComputerVisionService
{
    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_VISION_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_VISION_KEY'] ?? '';
    }

    /**
     * Returns true if Computer Vision credentials are configured.
     */
    public function isConfigured(): bool
    {
        return $this->endpoint !== '' && $this->key !== '';
    }

    /**
     * Analyses an image using Azure Computer Vision 4.0 — OCR, dense caption, and tags.
     *
     * Returns safe default values when credentials are absent or the file cannot be read.
     *
     * @param  string $imagePath Absolute path to the image file
     * @return array{ocr_text: string, caption: string, caption_confidence: float, tags: string[]}
     */
    public function analyze(string $imagePath): array
    {
        if (!$this->isConfigured() || !is_file($imagePath)) {
            return ['ocr_text' => '', 'caption' => '', 'caption_confidence' => 0.0, 'tags' => []];
        }

        $data = file_get_contents($imagePath);
        if ($data === false) {
            return ['ocr_text' => '', 'caption' => '', 'caption_confidence' => 0.0, 'tags' => []];
        }

        $url = $this->endpoint
            . '/computervision/imageanalysis:analyze'
            . '?api-version=2023-02-01-preview'
            . '&features=read,caption,tags'
            . '&language=en';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $this->key,
                'Content-Type: application/octet-stream',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            return ['ocr_text' => '', 'caption' => '', 'caption_confidence' => 0.0, 'tags' => []];
        }

        $result = json_decode($raw, true) ?? [];

        // OCR — concatenate all detected text lines
        $ocrLines = [];
        foreach ($result['readResult']['blocks'] ?? [] as $block) {
            foreach ($block['lines'] ?? [] as $line) {
                if (!empty($line['text'])) {
                    $ocrLines[] = $line['text'];
                }
            }
        }

        $caption           = $result['captionResult']['text']       ?? '';
        $captionConfidence = (float) ($result['captionResult']['confidence'] ?? 0.0);
        $tags              = array_map(
            fn($t) => $t['name'] ?? '',
            $result['tagsResult']['values'] ?? []
        );

        return [
            'ocr_text'           => implode("\n", $ocrLines),
            'caption'            => $caption,
            'caption_confidence' => $captionConfidence,
            'tags'               => array_values(array_filter($tags)),
        ];
    }
}
