<?php

declare(strict_types=1);

namespace Forge\Pipeline;

use Forge\Services\PiiService;
use Forge\Services\SummaryService;

class IntelligencePipeline
{
    private PiiService     $pii;
    private SummaryService $summary;

    public function __construct()
    {
        $this->pii     = new PiiService();
        $this->summary = new SummaryService();
    }

    /**
     * @param  array<int, array{text: string, category: string}> $existingEntities
     * @return array{
     *   entities:   array<int, array{text: string, category: string}>,
     *   redacted:   string,
     *   pii_found:  array<int, array{text: string, category: string}>,
     *   consistent: bool,
     *   confidence: float,
     *   reason:     string,
     *   summary:    string|null
     * }
     */
    public function run(string $text, array $existingEntities = []): array
    {
        $text = trim($text);

        if ($text === '') {
            return [
                'entities'   => [],
                'redacted'   => '',
                'pii_found'  => [],
                'consistent' => false,
                'confidence' => 0.0,
                'reason'     => 'No content to process.',
                'summary'    => null,
            ];
        }

        // Agent 1 — Entity extraction (reuses already-computed entities, no extra API call)
        $entities = $existingEntities;

        // Agent 2 — PII censorship
        try {
            $censored = $this->pii->redact($text);
        } catch (\Throwable) {
            $censored = ['redacted_text' => $text, 'pii_found' => []];
        }

        // Agents 3 + 4 — Consistency judge + Summarizer
        try {
            $report = $this->summary->process($censored['redacted_text'], $entities);
        } catch (\Throwable) {
            $report = ['consistent' => false, 'confidence' => 0.0, 'reason' => 'Summary failed.', 'summary' => null];
        }

        return [
            'entities'   => $entities,
            'redacted'   => $censored['redacted_text'],
            'pii_found'  => $censored['pii_found'],
            'consistent' => $report['consistent'],
            'confidence' => $report['confidence'],
            'reason'     => $report['reason'],
            'summary'    => $report['summary'],
        ];
    }
}
