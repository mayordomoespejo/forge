<?php

namespace Forge\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class DocumentService
{
    private Client $client;
    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_DOCUMENT_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_DOCUMENT_KEY'] ?? '';

        $this->client = new Client([
            'timeout' => 60,
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->key,
                'Content-Type'              => 'application/json',
            ],
        ]);
    }

    /**
     * @return array{page_count: int, content: string, paragraphs: string[], tables: array}
     * @throws \RuntimeException
     */
    public function extract(string $filePath): array
    {
        try {
            $data = file_get_contents($filePath);
            if ($data === false) {
                throw new \RuntimeException('Unable to read file: ' . $filePath);
            }

            $base64    = base64_encode($data);
            $submitUrl = $this->endpoint
                . '/formrecognizer/documentModels/prebuilt-read:analyze'
                . '?api-version=2023-07-31';

            $submitResponse    = $this->client->post($submitUrl, ['json' => ['base64Source' => $base64]]);
            $operationLocation = $submitResponse->getHeader('Operation-Location')[0] ?? null;

            if (!$operationLocation) {
                throw new \RuntimeException('No Operation-Location header returned from Document Intelligence.');
            }

            return $this->parseResult($this->poll($operationLocation));
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('DocumentService error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function poll(string $operationUrl): array
    {
        for ($i = 0; $i < 30; $i++) {
            sleep(1);
            try {
                $response = $this->client->get($operationUrl);
                $body     = json_decode((string) $response->getBody(), true) ?? [];
                $status   = $body['status'] ?? 'running';

                if ($status === 'succeeded') {
                    return $body['analyzeResult'] ?? [];
                }
                if ($status === 'failed') {
                    throw new \RuntimeException('Document analysis failed: ' . ($body['error']['message'] ?? 'Unknown error'));
                }
            } catch (GuzzleException $e) {
                throw new \RuntimeException('Polling error: ' . $e->getMessage(), 0, $e);
            }
        }
        throw new \RuntimeException('Document analysis timed out after 30 seconds.');
    }

    /**
     * @param  array<string, mixed> $analyzeResult
     * @return array{page_count: int, content: string, paragraphs: string[], tables: array}
     */
    private function parseResult(array $analyzeResult): array
    {
        $pageCount  = count($analyzeResult['pages'] ?? []);
        $content    = $analyzeResult['content'] ?? '';
        $paragraphs = array_values(array_filter(array_map(
            fn($p) => $p['content'] ?? '',
            $analyzeResult['paragraphs'] ?? []
        )));

        $tables = [];
        foreach ($analyzeResult['tables'] ?? [] as $table) {
            $rowCount = $table['rowCount'] ?? 0;
            $colCount = $table['columnCount'] ?? 0;
            if ($rowCount === 0 || $colCount === 0) continue;

            $grid = array_fill(0, $rowCount, array_fill(0, $colCount, ''));
            foreach ($table['cells'] ?? [] as $cell) {
                $r = $cell['rowIndex'] ?? 0;
                $c = $cell['columnIndex'] ?? 0;
                if (isset($grid[$r][$c])) {
                    $grid[$r][$c] = $cell['content'] ?? '';
                }
            }
            $tables[] = $grid;
        }

        return [
            'page_count' => $pageCount,
            'content'    => $content,
            'paragraphs' => $paragraphs,
            'tables'     => $tables,
        ];
    }
}
