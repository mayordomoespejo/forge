<?php

namespace Forge\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class LanguageService
{
    private Client $client;
    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_LANGUAGE_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_LANGUAGE_KEY'] ?? '';

        $this->client = new Client([
            'timeout' => 20,
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->key,
                'Content-Type'              => 'application/json',
            ],
        ]);
    }

    /**
     * @return array{
     *   sentiment: string,
     *   sentiment_scores: array{positive: float, neutral: float, negative: float},
     *   key_phrases: string[],
     *   entities: array<int, array{text: string, category: string}>,
     *   language: string,
     *   language_confidence: float
     * }
     */
    public function analyze(string $text): array
    {
        $body = ['documents' => [['id' => '1', 'text' => $text]]];

        $sentiment  = $this->call('/text/analytics/v3.1/sentiment', $body);
        $keyPhrases = $this->call('/text/analytics/v3.1/keyPhrases', $body);
        $entities   = $this->call('/text/analytics/v3.1/entities/recognition/general', $body);
        $languages  = $this->call('/text/analytics/v3.1/languages', $body);

        $sentDoc   = $sentiment['documents'][0] ?? [];
        $sentLabel = $sentDoc['sentiment'] ?? 'unknown';
        $scores    = $sentDoc['confidenceScores'] ?? ['positive' => 0.0, 'neutral' => 0.0, 'negative' => 0.0];

        $phrases = $keyPhrases['documents'][0]['keyPhrases'] ?? [];

        $rawEntities    = $entities['documents'][0]['entities'] ?? [];
        $mappedEntities = array_map(
            fn($e) => ['text' => $e['text'] ?? '', 'category' => $e['category'] ?? ''],
            $rawEntities
        );

        $langDoc  = $languages['documents'][0]['detectedLanguage'] ?? [];
        $langName = $langDoc['name'] ?? 'Unknown';
        $langConf = (float) ($langDoc['confidenceScore'] ?? 0.0);

        return [
            'sentiment'           => $sentLabel,
            'sentiment_scores'    => [
                'positive' => (float) ($scores['positive'] ?? 0.0),
                'neutral'  => (float) ($scores['neutral']  ?? 0.0),
                'negative' => (float) ($scores['negative'] ?? 0.0),
            ],
            'key_phrases'         => $phrases,
            'entities'            => $mappedEntities,
            'language'            => $langName,
            'language_confidence' => $langConf,
        ];
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function call(string $path, array $body): array
    {
        try {
            $response = $this->client->post($this->endpoint . $path, ['json' => $body]);
            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            return ['documents' => []];
        }
    }
}
