<?php

declare(strict_types=1);

namespace Forge\Services;

class TtsService
{
    private string $key;
    private string $region;
    private string $voice;

    public function __construct()
    {
        $this->key    = $_ENV['AZURE_SPEECH_KEY']    ?? '';
        $this->region = $_ENV['AZURE_SPEECH_REGION'] ?? 'eastus';
        $this->voice  = $_ENV['AZURE_TTS_VOICE']     ?? 'en-US-JennyNeural';
    }

    /**
     * Converts text to MP3 audio bytes using Azure Speech TTS.
     *
     * @throws \RuntimeException
     */
    public function synthesize(string $text): string
    {
        if ($this->key === '') {
            throw new \RuntimeException('Azure Speech key not configured.');
        }

        $ssml = sprintf(
            '<speak version="1.0" xml:lang="en-US"><voice name="%s">%s</voice></speak>',
            htmlspecialchars($this->voice, ENT_XML1),
            htmlspecialchars(mb_substr($text, 0, 3000), ENT_XML1)
        );

        $url = sprintf('https://%s.tts.speech.microsoft.com/cognitiveservices/v1', $this->region);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $ssml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $this->key,
                'Content-Type: application/ssml+xml',
                'X-Microsoft-OutputFormat: audio-16khz-128kbitrate-mono-mp3',
                'User-Agent: Forge',
            ],
        ]);

        $audio    = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($audio === false || $httpCode !== 200) {
            throw new \RuntimeException('TTS synthesis failed (HTTP ' . $httpCode . ').');
        }

        return $audio;
    }
}
