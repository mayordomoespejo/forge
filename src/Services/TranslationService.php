<?php

namespace Forge\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TranslationService
{
    private Client $client;
    private string $key;
    private string $region;
    private string $endpoint = 'https://api.cognitive.microsofttranslator.com';

    public function __construct()
    {
        $this->key    = $_ENV['AZURE_TRANSLATOR_KEY']    ?? '';
        $this->region = $_ENV['AZURE_TRANSLATOR_REGION'] ?? 'eastus';

        $this->client = new Client([
            'base_uri' => $this->endpoint,
            'timeout'  => 20,
            'headers'  => [
                'Ocp-Apim-Subscription-Key'    => $this->key,
                'Ocp-Apim-Subscription-Region' => $this->region,
                'Content-Type'                 => 'application/json',
            ],
        ]);
    }

    /**
     * Translate text to the given language code (e.g. 'en', 'es', 'fr', 'de', 'pt', 'it', 'ja', 'zh-Hans').
     *
     * @throws \RuntimeException
     */
    public function translate(string $text, string $targetLanguage): string
    {
        if ($text === '' || $this->key === '') {
            return $text;
        }

        try {
            $response = $this->client->post('/translate?api-version=3.0&to=' . urlencode($targetLanguage), [
                'json' => [['Text' => $text]],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data[0]['translations'][0]['text'] ?? $text;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('TranslationService error: ' . $e->getMessage(), 0, $e);
        }
    }
}
