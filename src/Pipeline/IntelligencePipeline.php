<?php

declare(strict_types=1);

namespace Forge\Pipeline;

use Forge\Services\ContentSafetyService;
use Forge\Services\PiiService;
use Forge\Services\SearchService;
use Forge\Services\SummaryService;

class IntelligencePipeline
{
    private ContentSafetyService $safety;
    private PiiService           $pii;
    private SearchService        $search;
    private SummaryService       $summary;

    public function __construct()
    {
        $this->safety  = new ContentSafetyService();
        $this->pii     = new PiiService();
        $this->search  = new SearchService();
        $this->summary = new SummaryService();
    }

    /**
     * Executes the full intelligence pipeline on a piece of text.
     *
     * Stages (in order):
     *  1. Safety gate  — Azure Content Safety blocks severity >= 4
     *  2. Entity reuse — passes through pre-computed NER entities
     *  3. PII censor   — Azure Language PII detection and redaction
     *  4. Judge        — gpt-4o-mini consistency check
     *  5. Summariser   — Azure extractive with gpt-4o-mini fallback
     *
     * @param  string                                            $text             Input text (trimmed internally)
     * @param  array<int, array{text: string, category: string}> $existingEntities Pre-computed NER entities to reuse
     * @return array{
     *   blocked:    bool,
     *   safety:     array{safe: bool, flags: array<int, array{category: string, severity: int}>},
     *   entities:   array<int, array{text: string, category: string}>,
     *   redacted:   string,
     *   pii_found:  array<int, array{text: string, category: string}>,
     *   consistent: bool,
     *   confidence: float,
     *   reason:     string,
     *   summary:    string|null,
     *   method:     string
     * }
     */
    public function run(string $text, array $existingEntities = []): array
    {
        $text = trim($text);

        if ($text === '') {
            return [
                'blocked'    => false,
                'safety'     => ['safe' => true, 'flags' => []],
                'entities'   => [],
                'redacted'   => '',
                'pii_found'  => [],
                'consistent' => false,
                'confidence' => 0.0,
                'reason'     => 'No content to process.',
                'summary'    => null,
                'method'     => 'abstractive',
            ];
        }

        // Safety gate — check text content
        $safetyResult = $this->safety->analyzeText($text);
        if (!$safetyResult['safe']) {
            return [
                'blocked'    => true,
                'safety'     => $safetyResult,
                'entities'   => [],
                'redacted'   => '',
                'pii_found'  => [],
                'consistent' => false,
                'confidence' => 0.0,
                'reason'     => 'Content blocked by safety filter.',
                'summary'    => null,
                'method'     => 'abstractive',
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

        // Agents 3 + 4 — Consistency judge + Summariser
        try {
            $report = $this->summary->process($censored['redacted_text'], $entities);
        } catch (\Throwable) {
            $report = ['consistent' => false, 'confidence' => 0.0, 'reason' => 'Summary failed.', 'summary' => null, 'method' => 'abstractive'];
        }

        return [
            'blocked'    => false,
            'safety'     => ['safe' => true, 'flags' => []],
            'entities'   => $entities,
            'redacted'   => $censored['redacted_text'],
            'pii_found'  => $censored['pii_found'],
            'consistent' => $report['consistent'],
            'confidence' => $report['confidence'],
            'reason'     => $report['reason'],
            'summary'    => $report['summary'],
            'method'     => $report['method'] ?? 'abstractive',
        ];
    }
}
