<?php

declare(strict_types=1);

namespace Forge\Services;

class SummaryService
{
    private ChatService $chat;

    public function __construct()
    {
        $this->chat = new ChatService();
    }

    /**
     * @param  array<int, array{text: string, category: string}> $entities
     * @return array{consistent: bool, confidence: float, reason: string, summary: string|null}
     */
    public function process(string $text, array $entities = []): array
    {
        if (trim($text) === '') {
            return ['consistent' => false, 'confidence' => 0.0, 'reason' => 'Empty content.', 'summary' => null];
        }

        // Step 1 — Consistency judge
        $judgeMessages = [
            [
                'role'    => 'system',
                'content' => 'You are a content consistency evaluator. Analyze the provided text and determine if it contains enough coherent, meaningful information to warrant a summary. Return ONLY valid JSON with no markdown: {"consistent": true or false, "confidence": number between 0 and 1, "reason": "brief explanation"}',
            ],
            [
                'role'    => 'user',
                'content' => 'Evaluate this content: ' . mb_substr($text, 0, 2000),
            ],
        ];

        try {
            $judgeRaw    = $this->chat->chat($judgeMessages);
            $judgeRaw    = preg_replace('/^```(?:json)?\s*/i', '', trim($judgeRaw));
            $judgeRaw    = preg_replace('/\s*```$/', '', $judgeRaw);
            $judgeResult = json_decode($judgeRaw, true) ?? [];
        } catch (\Throwable) {
            return ['consistent' => false, 'confidence' => 0.0, 'reason' => 'Judge failed.', 'summary' => null];
        }

        $consistent = (bool) ($judgeResult['consistent'] ?? false);
        $confidence = (float) ($judgeResult['confidence'] ?? 0.0);
        $reason     = (string) ($judgeResult['reason'] ?? '');

        if (!$consistent || $confidence < 0.5) {
            return ['consistent' => false, 'confidence' => $confidence, 'reason' => $reason, 'summary' => null];
        }

        // Step 2 — Summarizer
        $entityList = implode(', ', array_map(fn($e) => $e['text'] . ' (' . $e['category'] . ')', $entities));

        $summaryMessages = [
            [
                'role'    => 'system',
                'content' => 'You are a professional content summarizer. Create a concise 3-5 sentence executive summary. Focus on key facts, entities, and main points. Be objective and precise.',
            ],
            [
                'role'    => 'user',
                'content' => "Content:\n" . mb_substr($text, 0, 3000) . ($entityList ? "\n\nKey entities: " . $entityList : ''),
            ],
        ];

        try {
            $summary = $this->chat->chat($summaryMessages);
        } catch (\Throwable) {
            $summary = null;
        }

        return [
            'consistent' => true,
            'confidence' => $confidence,
            'reason'     => $reason,
            'summary'    => $summary,
        ];
    }
}
