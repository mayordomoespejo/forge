<?php

declare(strict_types=1);

namespace Forge\Services;

use Forge\Exceptions\AnalysisException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class DocumentService
{
    private const API_VERSION        = '2023-07-31';
    private const DEFAULT_MODEL      = 'prebuilt-read';
    private const POLL_MAX_ATTEMPTS  = 30;
    private const POLL_SLEEP_SECONDS = 1;

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
     * Extracts structured content from a document using Azure Document Intelligence.
     *
     * @param  string   $filePath    Absolute path to the document (PDF, JPEG, PNG, etc.)
     * @param  string   $model       Document model to use (default: 'prebuilt-read')
     * @param  string[] $queryFields Additional query fields for extraction (switches to 'prebuilt-layout')
     * @return array{page_count: int, content: string, paragraphs: string[], tables: array, fields: array}
     * @throws AnalysisException when the file cannot be read or the Azure API returns an error
     */
    public function extract(string $filePath, string $model = self::DEFAULT_MODEL, array $queryFields = []): array
    {
        try {
            $data = file_get_contents($filePath);
            if ($data === false) {
                throw new AnalysisException('Unable to read file: ' . $filePath);
            }

            $base64      = base64_encode($data);
            $requestBody = ['base64Source' => $base64];
            if (!empty($queryFields)) {
                $requestBody['queryFields'] = $queryFields;
                if ($model === self::DEFAULT_MODEL) {
                    $model = 'prebuilt-layout';
                }
            }

            $submitUrl = $this->endpoint
                . '/formrecognizer/documentModels/' . $model . ':analyze'
                . '?api-version=' . self::API_VERSION;

            $submitResponse    = $this->client->post($submitUrl, ['json' => $requestBody]);
            $operationLocation = $submitResponse->getHeader('Operation-Location')[0] ?? null;

            if (!$operationLocation) {
                throw new AnalysisException('No Operation-Location header returned from Document Intelligence.');
            }

            return $this->parseResult($this->poll($operationLocation));
        } catch (AnalysisException $e) {
            throw $e;
        } catch (GuzzleException $e) {
            throw new AnalysisException('DocumentService error: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Polls the operation URL until the job succeeds or fails.
     *
     * @return array<string, mixed>
     * @throws AnalysisException when the analysis fails or times out
     */
    private function poll(string $operationUrl): array
    {
        for ($i = 0; $i < self::POLL_MAX_ATTEMPTS; $i++) {
            sleep(self::POLL_SLEEP_SECONDS);
            try {
                $response = $this->client->get($operationUrl);
                $body     = json_decode((string) $response->getBody(), true) ?? [];
                $status   = $body['status'] ?? 'running';

                if ($status === 'succeeded') {
                    return $body['analyzeResult'] ?? [];
                }
                if ($status === 'failed') {
                    throw new AnalysisException('Document analysis failed: ' . ($body['error']['message'] ?? 'Unknown error'));
                }
            } catch (GuzzleException $e) {
                throw new AnalysisException('Polling error: ' . $e->getMessage(), $e);
            }
        }

        throw new AnalysisException('Document analysis timed out after ' . self::POLL_MAX_ATTEMPTS . ' seconds.');
    }

    /**
     * Parses the raw Azure analyze result into a structured array.
     *
     * @param  array<string, mixed> $analyzeResult
     * @return array{page_count: int, content: string, paragraphs: string[], tables: array, fields: array}
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

        $fields = [];
        foreach ($analyzeResult['documents'][0]['fields'] ?? [] as $fieldName => $fieldData) {
            $value = $fieldData['valueString']
                  ?? $fieldData['content']
                  ?? (isset($fieldData['valueNumber']) ? (string) $fieldData['valueNumber'] : null)
                  ?? (isset($fieldData['valueDate'])   ? $fieldData['valueDate']             : null)
                  ?? null;
            if ($value !== null) {
                $fields[$fieldName] = $value;
            }
            if (isset($fieldData['valueArray'])) {
                $items = [];
                foreach ($fieldData['valueArray'] as $item) {
                    $itemFields = [];
                    foreach ($item['valueObject'] ?? [] as $k => $v) {
                        $itemFields[$k] = $v['valueString'] ?? $v['content'] ?? (string) ($v['valueNumber'] ?? '');
                    }
                    if (!empty($itemFields)) $items[] = $itemFields;
                }
                if (!empty($items)) $fields[$fieldName] = $items;
            }
        }

        return [
            'page_count' => $pageCount,
            'content'    => $content,
            'paragraphs' => $paragraphs,
            'tables'     => $tables,
            'fields'     => $fields,
        ];
    }
}
