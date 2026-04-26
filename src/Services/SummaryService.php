<?php

declare(strict_types=1);

namespace Forge\Services;

class SummaryService
{
    private ChatService         $chat;
    private AzureSummaryService $azureSummary;

    public function __construct()
    {
        $this->chat         = new ChatService();
        $this->azureSummary = new AzureSummaryService();
    }

    /**
     * Runs a two-step consistency check and summarisation pipeline.
     *
     * Step 1 — asks gpt-4o-mini to judge whether the text is coherent enough
     * to warrant a summary. Step 2 — if consistent, tries Azure extractive
     * summarisation first and falls back to gpt-4o-mini abstractive summarisation.
     *
     * @param  string                                        $text     Content to summarise
     * @param  array<int, array{text: string, category: string}> $entities Named entities already extracted from the text
     * @return array{consistent: bool, confidence: float, reason: string, summary: string|null, method: string}
     */
    public function process(string $text, array $entities = []): array
    {
        if (trim($text) === '') {
            return ['consistent' => false, 'confidence' => 0.0, 'reason' => 'Empty content.', 'summary' => null, 'method' => 'abstractive'];
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
            return ['consistent' => false, 'confidence' => 0.0, 'reason' => 'Judge failed.', 'summary' => null, 'method' => 'abstractive'];
        }

        $consistent = (bool) ($judgeResult['consistent'] ?? false);
        $confidence = (float) ($judgeResult['confidence'] ?? 0.0);
        $reason     = (string) ($judgeResult['reason'] ?? '');

        if (!$consistent || $confidence < 0.5) {
            return ['consistent' => false, 'confidence' => $confidence, 'reason' => $reason, 'summary' => null, 'method' => 'abstractive'];
        }

        // Step 2 — Try Azure extractive first, fallback to gpt-4o-mini
        $extractive = $this->azureSummary->extractive($text);
        if ($extractive !== null) {
            return [
                'consistent' => true,
                'confidence' => $confidence,
                'reason'     => $reason,
                'summary'    => $extractive,
                'method'     => 'extractive',
            ];
        }

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
            'method'     => 'abstractive',
        ];
    }
}
