<?php

declare(strict_types=1);

namespace Forge\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TranslationService
{
    private const ENDPOINT = 'https://api.cognitive.microsofttranslator.com';

    private Client $client;
    private string $key;
    private string $region;

    public function __construct()
    {
        $this->key    = $_ENV['AZURE_TRANSLATOR_KEY']    ?? '';
        $this->region = $_ENV['AZURE_TRANSLATOR_REGION'] ?? 'eastus';

        $this->client = new Client([
            'base_uri' => self::ENDPOINT,
            'timeout'  => 20,
            'headers'  => [
                'Ocp-Apim-Subscription-Key'    => $this->key,
                'Ocp-Apim-Subscription-Region' => $this->region,
                'Content-Type'                 => 'application/json',
            ],
        ]);
    }

    /**
     * Translates text into the given target language.
     *
     * Returns the original text unchanged when credentials are absent or the input is empty.
     * Supported language codes include: en, es, fr, de, pt, it, ja, zh-Hans.
     *
     * @param  string $text           Text to translate
     * @param  string $targetLanguage BCP-47 language tag (e.g. 'en', 'es', 'fr')
     * @return string                 Translated text, or original text on unconfigured/empty input
     * @throws \RuntimeException      when the API call fails
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
