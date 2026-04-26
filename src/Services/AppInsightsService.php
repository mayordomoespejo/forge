<?php

declare(strict_types=1);

namespace Forge\Services;

class AppInsightsService
{
    private string $ikey;
    private string $endpoint = 'https://dc.services.visualstudio.com/v2/track';

    public function __construct()
    {
        $this->ikey = $_ENV['AZURE_APPINSIGHTS_IKEY'] ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->ikey !== '';
    }

    /**
     * Tracks a custom event (fire-and-forget, non-blocking).
     *
     * @param array<string, string|int|float|bool> $properties
     */
    public function trackEvent(string $name, array $properties = []): void
    {
        if (!$this->isConfigured()) return;

        $payload = [[
            'name' => 'Microsoft.ApplicationInsights.' . $this->ikey . '.Event',
            'time' => gmdate('Y-m-d\TH:i:s.000\Z'),
            'iKey' => $this->ikey,
            'data' => [
                'baseType' => 'EventData',
                'baseData' => [
                    'ver'        => 2,
                    'name'       => $name,
                    'properties' => array_map('strval', $properties),
                ],
            ],
        ]];

        $this->send($payload);
    }

    /**
     * Tracks an exception (fire-and-forget).
     */
    public function trackException(\Throwable $e): void
    {
        if (!$this->isConfigured()) return;

        $payload = [[
            'name' => 'Microsoft.ApplicationInsights.' . $this->ikey . '.Exception',
            'time' => gmdate('Y-m-d\TH:i:s.000\Z'),
            'iKey' => $this->ikey,
            'data' => [
                'baseType' => 'ExceptionData',
                'baseData' => [
                    'ver'        => 2,
                    'exceptions' => [[
                        'typeName' => get_class($e),
                        'message'  => $e->getMessage(),
                        'hasFullStack' => false,
                    ]],
                ],
            ],
        ]];

        $this->send($payload);
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     */
    private function send(array $payload): void
    {
        $body = json_encode($payload);
        $ch   = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
