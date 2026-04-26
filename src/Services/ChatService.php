<?php

declare(strict_types=1);

namespace Forge\Services;

use Forge\Exceptions\AnalysisException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ChatService
{
    private const MODEL          = 'gpt-4o-mini';
    private const ENDPOINT_BASE  = 'https://models.inference.ai.azure.com';
    private const CURL_TIMEOUT   = 30;
    private const STREAM_TIMEOUT = 60;

    private Client $client;
    private string $token;

    public function __construct()
    {
        $this->token  = $_ENV['GITHUB_TOKEN'] ?? '';
        $this->client = new Client([
            'base_uri' => self::ENDPOINT_BASE,
            'timeout'  => self::CURL_TIMEOUT,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    /**
     * Sends a chat completion request and returns the assistant reply.
     *
     * @param  array<int, array{role: string, content: mixed}> $messages Chat history including system prompt
     * @return string                                                     Assistant message content
     * @throws AnalysisException when the API call fails
     */
    public function chat(array $messages): string
    {
        try {
            $response = $this->client->post('/chat/completions', [
                'json' => [
                    'model'    => self::MODEL,
                    'messages' => $messages,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['choices'][0]['message']['content'] ?? '';
        } catch (GuzzleException $e) {
            throw new AnalysisException('ChatService error: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Analyzes an image file and returns structured visual metadata.
     *
     * Sends the image as a base64-encoded data URI to the vision-capable model
     * and parses the JSON response into a typed array.
     *
     * @param  string $imagePath Absolute path to the image file (jpeg, png, gif, webp)
     * @return array{description: string, objects: string[], colors: string[], text_in_image: string|null, mood: string}
     * @throws AnalysisException when the file cannot be read or the API call fails
     */
    public function analyzeImage(string $imagePath): array
    {
        try {
            $imageData = file_get_contents($imagePath);
            if ($imageData === false) {
                throw new AnalysisException('Unable to read image file: ' . $imagePath);
            }

            $base64 = base64_encode($imageData);
            $ext    = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $mime   = match ($ext) {
                'png'   => 'image/png',
                'gif'   => 'image/gif',
                'webp'  => 'image/webp',
                default => 'image/jpeg',
            };

            $messages = [
                [
                    'role'    => 'system',
                    'content' => 'You are a visual content analyzer. Analyze this image and return a structured JSON with these fields: description (string), objects (array of strings), colors (array of strings), text_in_image (string or null), mood (string). Return ONLY valid JSON.',
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Analyze this image and return structured JSON.'],
                        [
                            'type'      => 'image_url',
                            'image_url' => ['url' => "data:{$mime};base64,{$base64}"],
                        ],
                    ],
                ],
            ];

            $raw = $this->chat($messages);
            $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
            $raw = preg_replace('/\s*```$/', '', $raw);

            $result = json_decode($raw, true);
            if (!is_array($result)) {
                return [
                    'description'   => $raw,
                    'objects'       => [],
                    'colors'        => [],
                    'text_in_image' => null,
                    'mood'          => 'unknown',
                ];
            }

            return [
                'description'   => $result['description']  ?? '',
                'objects'       => $result['objects']       ?? [],
                'colors'        => $result['colors']        ?? [],
                'text_in_image' => $result['text_in_image'] ?? null,
                'mood'          => $result['mood']          ?? '',
            ];
        } catch (AnalysisException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AnalysisException('analyzeImage error: ' . $e->getMessage(), $e);
        }
    }
}
