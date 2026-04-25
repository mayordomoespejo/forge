<?php

declare(strict_types=1);

namespace Forge\Services;

class SearchService
{
    private string $endpoint;
    private string $key;
    private string $index;
    private string $apiVersion = '2023-11-01';

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_SEARCH_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_SEARCH_KEY']            ?? '';
        $this->index    = $_ENV['AZURE_SEARCH_INDEX']          ?? 'forge-analyses';
    }

    public function isConfigured(): bool
    {
        return $this->endpoint !== '' && $this->key !== '';
    }

    /**
     * Saves an analysis result to the search index (creates index on first use).
     */
    public function save(string $id, array $result): void
    {
        if (!$this->isConfigured()) return;

        $this->ensureIndex();

        $doc = [
            '@search.action' => 'mergeOrUpload',
            'id'             => preg_replace('/[^a-zA-Z0-9_\-=]/', '_', $id),
            'timestamp'      => date('c'),
            'type'           => $result['type']                               ?? 'unknown',
            'filename'       => $result['file']                               ?? '',
            'content'        => mb_substr($result['analysis']['content']      ?? $result['content'] ?? '', 0, 32000),
            'summary'        => $result['pipeline']['summary']                ?? '',
            'entities'       => json_encode($result['pipeline']['entities']   ?? []),
            'language'       => $result['analysis']['language']               ?? ($result['language']['language'] ?? ''),
        ];

        $body = json_encode(['value' => [$doc]]);
        $url  = $this->endpoint . '/indexes/' . $this->index . '/docs/index?api-version=' . $this->apiVersion;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $this->key,
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * @return array<int, array{type: string, summary: string, content: string, timestamp: string}>
     */
    public function search(string $query, int $top = 3): array
    {
        if (!$this->isConfigured() || trim($query) === '') return [];

        $body = json_encode([
            'search'  => $query,
            'select'  => 'type,filename,summary,content,timestamp,language',
            'top'     => $top,
            'orderby' => 'timestamp desc',
        ]);

        $url = $this->endpoint . '/indexes/' . $this->index . '/docs/search?api-version=' . $this->apiVersion;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $this->key,
                'Content-Type: application/json',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) return [];

        $data = json_decode($raw, true) ?? [];
        return array_map(fn($v) => [
            'type'      => $v['type']      ?? '',
            'filename'  => $v['filename']  ?? '',
            'summary'   => $v['summary']   ?? '',
            'content'   => mb_substr($v['content'] ?? '', 0, 500),
            'timestamp' => $v['timestamp'] ?? '',
            'language'  => $v['language']  ?? '',
        ], $data['value'] ?? []);
    }

    private function ensureIndex(): void
    {
        $url = $this->endpoint . '/indexes/' . $this->index . '?api-version=' . $this->apiVersion;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['api-key: ' . $this->key],
        ]);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_exec($ch);
        curl_close($ch);

        if ($httpCode === 200) return;

        $schema = json_encode([
            'name'   => $this->index,
            'fields' => [
                ['name' => 'id',        'type' => 'Edm.String',         'key' => true,  'searchable' => false],
                ['name' => 'timestamp', 'type' => 'Edm.DateTimeOffset', 'key' => false, 'searchable' => false, 'sortable' => true, 'filterable' => true],
                ['name' => 'type',      'type' => 'Edm.String',         'key' => false, 'searchable' => false, 'filterable' => true],
                ['name' => 'filename',  'type' => 'Edm.String',         'key' => false, 'searchable' => true],
                ['name' => 'content',   'type' => 'Edm.String',         'key' => false, 'searchable' => true],
                ['name' => 'summary',   'type' => 'Edm.String',         'key' => false, 'searchable' => true],
                ['name' => 'entities',  'type' => 'Edm.String',         'key' => false, 'searchable' => true],
                ['name' => 'language',  'type' => 'Edm.String',         'key' => false, 'searchable' => false, 'filterable' => true],
            ],
        ]);

        $putUrl = $this->endpoint . '/indexes/' . $this->index . '?api-version=' . $this->apiVersion;
        $ch     = curl_init($putUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $schema,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $this->key,
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
