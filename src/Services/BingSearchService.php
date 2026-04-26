<?php

declare(strict_types=1);

namespace Forge\Services;

class BingSearchService
{
    private const ENDPOINT = 'https://api.bing.microsoft.com/v7.0/search';

    private string $key;

    public function __construct()
    {
        $this->key = $_ENV['BING_SEARCH_KEY'] ?? '';
    }

    /**
     * Returns true if a Bing Search API key is configured.
     */
    public function isConfigured(): bool
    {
        return $this->key !== '';
    }

    /**
     * Searches the web and returns the top results as a formatted context string.
     *
     * Returns an empty string when the service is unconfigured or the query is blank.
     *
     * @param  string $query Search query (truncated to 200 characters)
     * @param  int    $count Maximum number of results to include
     * @return string        Formatted multi-line string with result names and snippets, or ''
     */
    public function search(string $query, int $count = 3): string
    {
        if (!$this->isConfigured() || trim($query) === '') {
            return '';
        }

        $url = self::ENDPOINT . '?' . http_build_query([
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
