<?php

declare(strict_types=1);

namespace Forge\Services;

class SpeechService
{
    private string $key;
    private string $region;

    public function __construct()
    {
        $this->key    = $_ENV['AZURE_SPEECH_KEY']    ?? '';
        $this->region = $_ENV['AZURE_SPEECH_REGION'] ?? 'eastus';
    }

    /**
     * Transcribes audio file using Azure Speech-to-Text REST API.
     * Supports: wav, mp3, ogg, flac, webm (max ~60 seconds recommended).
     *
     * @throws \RuntimeException
     */
    public function transcribe(string $filePath, string $language = 'en-US'): string
    {
        if ($this->key === '') {
            throw new \RuntimeException('Azure Speech key not configured.');
        }

        if (!is_file($filePath)) {
            throw new \RuntimeException('Audio file not found: ' . $filePath);
        }

        $audio = file_get_contents($filePath);
        if ($audio === false) {
            throw new \RuntimeException('Unable to read audio file.');
        }

        $ext         = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentType = match($ext) {
            'wav'  => 'audio/wav',
            'mp3'  => 'audio/mpeg',
            'ogg'  => 'audio/ogg',
            'flac' => 'audio/flac',
            'webm' => 'audio/webm',
            default => 'audio/wav',
        };

        $url = sprintf(
            'https://%s.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?language=%s&format=simple',
            $this->region,
            urlencode($language)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $audio,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $this->key,
                'Content-Type: ' . $contentType,
                'Accept: application/json',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            throw new \RuntimeException('Speech transcription failed (HTTP ' . $httpCode . ').');
        }

        $data   = json_decode($raw, true) ?? [];
        $status = $data['RecognitionStatus'] ?? 'Error';

        if ($status !== 'Success') {
            throw new \RuntimeException('Speech recognition status: ' . $status);
        }

        return $data['DisplayText'] ?? '';
    }
}
