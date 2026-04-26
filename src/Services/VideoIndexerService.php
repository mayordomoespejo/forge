<?php

declare(strict_types=1);

namespace Forge\Services;

class VideoIndexerService
{
    private string $accountId;
    private string $location;
    private string $apiKey;
    private string $baseUrl = 'https://api.videoindexer.ai';

    public function __construct()
    {
        $this->accountId = $_ENV['AZURE_VIDEO_INDEXER_ACCOUNT_ID'] ?? '';
        $this->location  = $_ENV['AZURE_VIDEO_INDEXER_LOCATION']   ?? 'trial';
        $this->apiKey    = $_ENV['AZURE_VIDEO_INDEXER_API_KEY']     ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->accountId !== '' && $this->apiKey !== '';
    }

    /**
     * @return array{transcript: string, topics: string[], keywords: string[], duration: string}
     * @throws \RuntimeException
     */
    public function index(string $filePath): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Video Indexer not configured.');
        }

        $token   = $this->getAccessToken();
        $videoId = $this->uploadVideo($filePath, $token);
        $index   = $this->pollIndex($videoId, $token);

        return $this->parseInsights($index);
    }

    private function getAccessToken(): string
    {
        $url = sprintf(
            '%s/auth/%s/Accounts/%s/AccessToken?allowEdit=true',
            $this->baseUrl, $this->location, $this->accountId
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Ocp-Apim-Subscription-Key: ' . $this->apiKey],
        ]);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            throw new \RuntimeException('Failed to get Video Indexer access token.');
        }

        return trim($raw, '"');
    }

    private function uploadVideo(string $filePath, string $token): string
    {
        $name = basename($filePath);
        $url  = sprintf(
            '%s/%s/Accounts/%s/Videos?accessToken=%s&name=%s&privacy=Private&videoUrl=',
            $this->baseUrl, $this->location, $this->accountId,
            urlencode($token), urlencode($name)
        );

        $file = new \CURLFile($filePath);
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => $file],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => ['Ocp-Apim-Subscription-Key: ' . $this->apiKey],
        ]);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            throw new \RuntimeException('Video upload failed (HTTP ' . $httpCode . ').');
        }

        $data = json_decode($raw, true) ?? [];
        if (empty($data['id'])) {
            throw new \RuntimeException('Video upload returned no ID.');
        }

        return $data['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function pollIndex(string $videoId, string $token): array
    {
        for ($i = 0; $i < 60; $i++) {
            sleep(5);

            $url = sprintf(
                '%s/%s/Accounts/%s/Videos/%s/Index?accessToken=%s',
                $this->baseUrl, $this->location, $this->accountId,
                $videoId, urlencode($token)
            );

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => ['Ocp-Apim-Subscription-Key: ' . $this->apiKey],
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);

            if ($raw === false) continue;

            $data  = json_decode($raw, true) ?? [];
            $state = $data['videos'][0]['state'] ?? 'Processing';

            if ($state === 'Processed') {
                return $data;
            }

            if ($state === 'Failed') {
                throw new \RuntimeException('Video indexing failed.');
            }
        }

        throw new \RuntimeException('Video indexing timed out.');
    }

    /**
     * @param  array<string, mixed> $index
     * @return array{transcript: string, topics: string[], keywords: string[], duration: string}
     */
    private function parseInsights(array $index): array
    {
        $insights = $index['videos'][0]['insights'] ?? [];

        $transcript = implode(' ', array_map(
            fn($b) => $b['text'] ?? '',
            $insights['transcript'] ?? []
        ));

        $topics   = array_unique(array_map(fn($t) => $t['name'] ?? '', $insights['topics']   ?? []));
        $keywords = array_unique(array_map(fn($k) => $k['text'] ?? '', $insights['keywords'] ?? []));

        $durationSec = $index['durationInSeconds'] ?? 0;
        $duration    = gmdate('H:i:s', (int) $durationSec);

        return [
            'transcript' => $transcript,
            'topics'     => array_values(array_filter($topics)),
            'keywords'   => array_values(array_filter($keywords)),
            'duration'   => $duration,
        ];
    }
}
