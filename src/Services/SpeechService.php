<?php

declare(strict_types=1);

namespace Forge\Services;

use Forge\Exceptions\TranscriptionException;

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
     * Transcribes an audio file using the Azure Speech-to-Text REST API.
     *
     * Supports wav, mp3, ogg, flac, and webm formats (max ~60 seconds recommended).
     *
     * @param  string $filePath Absolute path to the audio file
     * @param  string $language BCP-47 language tag (e.g. 'en-US', 'es-ES')
     * @return string           Transcribed text
     * @throws TranscriptionException when credentials are missing, file cannot be read, or recognition fails
     */
    public function transcribe(string $filePath, string $language = 'en-US'): string
    {
        if ($this->key === '') {
            throw new TranscriptionException('Azure Speech key not configured.');
        }

        if (!is_file($filePath)) {
            throw new TranscriptionException('Audio file not found: ' . $filePath);
        }

        $audio = file_get_contents($filePath);
        if ($audio === false) {
            throw new TranscriptionException('Unable to read audio file.');
        }

        $ext         = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'wav'   => 'audio/wav',
            'mp3'   => 'audio/mpeg',
            'ogg'   => 'audio/ogg',
            'flac'  => 'audio/flac',
            'webm'  => 'audio/webm',
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
            throw new TranscriptionException('Speech transcription failed (HTTP ' . $httpCode . ').');
        }

        $data   = json_decode($raw, true) ?? [];
        $status = $data['RecognitionStatus'] ?? 'Error';

        if ($status !== 'Success') {
            throw new TranscriptionException('Speech recognition status: ' . $status);
        }

        return $data['DisplayText'] ?? '';
    }
}
