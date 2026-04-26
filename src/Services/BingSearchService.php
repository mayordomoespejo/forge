<?php

declare(strict_types=1);

namespace Forge\Services;

class BingSearchService
{
    private string $key;
    private string $endpoint = 'https://api.bing.microsoft.com/v7.0/search';

    public function __construct()
    {
        $this->key = $_ENV['BING_SEARCH_KEY'] ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->key !== '';
    }

    /**
     * Returns top search results as a formatted context string.
     */
    public function search(string $query, int $count = 3): string
    {
        if (!$this->isConfigured() || trim($query) === '') {
            return '';
        }

        $url = $this->endpoint . '?' . http_build_query([
            'q'     => mb_substr($query, 0, 200),
            'count' => $count,
            'mkt'   => 'en-US',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Ocp-Apim-Subscription-Key: ' . $this->key],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            return '';
        }

        $data    = json_decode($raw, true) ?? [];
        $results = $data['webPages']['value'] ?? [];

        if (empty($results)) {
            return '';
        }

        $lines = ["\n\nWeb search results for \"" . $query . "\":"];
        foreach (array_slice($results, 0, $count) as $r) {
            $lines[] = '- ' . ($r['name'] ?? '') . ': ' . ($r['snippet'] ?? '');
        }

        return implode("\n", $lines);
    }
}
