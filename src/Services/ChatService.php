<?php

namespace Forge\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ChatService
{
    private Client $client;
    private string $token;
    private string $endpoint = 'https://models.inference.ai.azure.com';

    public function __construct()
    {
        $this->token = $_ENV['GITHUB_TOKEN'] ?? '';
        $this->client = new Client([
            'base_uri' => $this->endpoint,
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    /**
     * @param  array<int, array{role: string, content: mixed}> $messages
     * @throws \RuntimeException
     */
    public function chat(array $messages): string
    {
        try {
            $response = $this->client->post('/chat/completions', [
                'json' => [
                    'model'    => 'gpt-4o-mini',
                    'messages' => $messages,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            return $data['choices'][0]['message']['content'] ?? '';
        } catch (GuzzleException $e) {
            throw new \RuntimeException('ChatService error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{description: string, objects: string[], colors: string[], text_in_image: string|null, mood: string}
     * @throws \RuntimeException
     */
    public function analyzeImage(string $imagePath): array
    {
        try {
            $imageData = file_get_contents($imagePath);
            if ($imageData === false) {
                throw new \RuntimeException('Unable to read image file: ' . $imagePath);
            }

            $base64 = base64_encode($imageData);
            $ext    = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $mime   = match($ext) {
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
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
                'description'   => $result['description']   ?? '',
                'objects'       => $result['objects']        ?? [],
                'colors'        => $result['colors']         ?? [],
                'text_in_image' => $result['text_in_image']  ?? null,
                'mood'          => $result['mood']            ?? '',
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('analyzeImage error: ' . $e->getMessage(), 0, $e);
        }
    }
}
