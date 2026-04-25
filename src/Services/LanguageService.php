<?php

namespace Forge\Services;

class LanguageService
{
    private string $endpoint;
    private string $key;

    public function __construct()
    {
        $this->endpoint = rtrim($_ENV['AZURE_LANGUAGE_ENDPOINT'] ?? '', '/');
        $this->key      = $_ENV['AZURE_LANGUAGE_KEY'] ?? '';
    }

    /**
     * @return array{
     *   sentiment: string,
     *   sentiment_scores: array{positive: float, neutral: float, negative: float},
     *   opinions: array<int, array{target: string, sentiment: string, assessments: string[]}>,
     *   key_phrases: string[],
     *   entities: array<int, array{text: string, category: string}>,
     *   language: string,
     *   language_confidence: float
     * }
     */
    public function analyze(string $text): array
    {
        $body = json_encode(['documents' => [['id' => '1', 'text' => $text]]]);

        $endpoints = [
            'sentiment'  => '/text/analytics/v3.1/sentiment?opinionMining=true',
            'keyPhrases' => '/text/analytics/v3.1/keyPhrases',
            'entities'   => '/text/analytics/v3.1/entities/recognition/general',
            'languages'  => '/text/analytics/v3.1/languages',
        ];

        $multi   = curl_multi_init();
        $handles = [];

        foreach ($endpoints as $key => $path) {
            $ch = curl_init($this->endpoint . $path);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Ocp-Apim-Subscription-Key: ' . $this->key,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 20,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi);
            }
        } while ($running > 0);

        $results = [];
        foreach ($handles as $key => $ch) {
            $raw           = curl_multi_getcontent($ch);
            $results[$key] = json_decode($raw ?: '{}', true) ?? [];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        $sentDoc   = $results['sentiment']['documents'][0] ?? [];
        $sentLabel = $sentDoc['sentiment'] ?? 'unknown';
        $scores    = $sentDoc['confidenceScores'] ?? ['positive' => 0.0, 'neutral' => 0.0, 'negative' => 0.0];

        $opinions = [];
        foreach ($sentDoc['sentences'] ?? [] as $sentence) {
            foreach ($sentence['opinions'] ?? [] as $opinion) {
                $target      = $opinion['target'] ?? [];
                $assessments = array_map(fn($a) => $a['text'] ?? '', $opinion['assessments'] ?? []);
                if (!empty($target['text'])) {
                    $opinions[] = [
                        'target'      => $target['text'] ?? '',
                        'sentiment'   => $target['sentiment'] ?? '',
                        'assessments' => $assessments,
                    ];
                }
            }
        }

        $phrases = $results['keyPhrases']['documents'][0]['keyPhrases'] ?? [];

        $rawEntities    = $results['entities']['documents'][0]['entities'] ?? [];
        $mappedEntities = array_map(
            fn($e) => ['text' => $e['text'] ?? '', 'category' => $e['category'] ?? ''],
            $rawEntities
        );

        $langDoc  = $results['languages']['documents'][0]['detectedLanguage'] ?? [];
        $langName = $langDoc['name'] ?? 'Unknown';
        $langConf = (float) ($langDoc['confidenceScore'] ?? 0.0);

        return [
            'sentiment'           => $sentLabel,
            'sentiment_scores'    => [
                'positive' => (float) ($scores['positive'] ?? 0.0),
                'neutral'  => (float) ($scores['neutral']  ?? 0.0),
                'negative' => (float) ($scores['negative'] ?? 0.0),
            ],
            'opinions'            => $opinions,
            'key_phrases'         => $phrases,
            'entities'            => $mappedEntities,
            'language'            => $langName,
            'language_confidence' => $langConf,
        ];
    }
}
