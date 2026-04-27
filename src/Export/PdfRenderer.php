<?php

declare(strict_types=1);

namespace Forge\Export;

/**
 * Renders analysis results as an HTML document for PDF export.
 */
class PdfRenderer
{
    private const PDF_COLOR_ACCENT  = '#f97316';
    private const PDF_COLOR_DANGER  = '#e53e3e';
    private const PDF_COLOR_MUTED   = '#888888';
    private const PDF_COLOR_BG      = '#f9f9f9';
    private const PDF_MAX_CONTENT   = 2000;
    private const PDF_MAX_REDACTED  = 1000;

    /**
     * @param array<string, mixed> $result
     */
    public function render(array $result): string
    {
        $type      = $result['type']     ?? 'unknown';
        $analysis  = $result['analysis'] ?? [];
        $pipeline  = $result['pipeline'] ?? [];
        $language  = $result['language'] ?? [];
        $date      = date('Y-m-d H:i');
        $filename  = $result['file']                            ?? '';
        $entities  = $analysis['entities']                      ?? $language['entities']       ?? [];
        $phrases   = $analysis['key_phrases']                   ?? $language['key_phrases']    ?? [];
        $sentiment = $analysis['sentiment']                     ?? $language['sentiment']      ?? '';
        $summary   = $pipeline['summary']                       ?? '';
        $piiFound  = $pipeline['pii_found']                     ?? [];
        $redacted  = $pipeline['redacted']                      ?? '';
        $content   = $analysis['content']                       ?? $result['content']          ?? '';

        return $this->buildHtml(
            $type, $filename, $date,
            $sentiment, $phrases, $entities,
            $summary, $piiFound, $redacted, $content
        );
    }

    /**
     * @param array<int, array{text: string, category: string}> $entities
     * @param array<int, array{text: string, category: string}> $piiFound
     * @param string[]                                           $phrases
     */
    private function buildHtml(
        string $type,
        string $filename,
        string $date,
        string $sentiment,
        array  $phrases,
        array  $entities,
        string $summary,
        array  $piiFound,
        string $redacted,
        string $content
    ): string {
        $html  = $this->renderHeader($type, $filename, $date);
        $html .= $this->renderSentiment($sentiment);
        $html .= $this->renderPhrases($phrases);
        $html .= $this->renderEntities($entities);
        $html .= $this->renderSummary($summary);
        $html .= $this->renderPii($piiFound, $redacted);
        $html .= $this->renderContent($content);
        $html .= $this->renderFooter();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Forge Report</title></head>'
            . '<body style="font-family:Arial,sans-serif;color:#1a1a1a;max-width:750px;margin:0 auto;padding:2rem;">'
            . $html
            . '</body></html>';
    }

    private function renderHeader(string $type, string $filename, string $date): string
    {
        $meta = htmlspecialchars($date);
        if ($filename) {
            $meta .= ' &mdash; ' . htmlspecialchars($filename);
        }
        $meta .= ' &mdash; Type: ' . htmlspecialchars(ucfirst($type));

        return '<div style="border-bottom:3px solid ' . self::PDF_COLOR_ACCENT . ';padding-bottom:1rem;margin-bottom:2rem;">'
            . '<h1 style="font-size:1.8rem;margin:0;color:' . self::PDF_COLOR_ACCENT . ';">Forge Analysis Report</h1>'
            . '<p style="margin:0.4rem 0 0;color:' . self::PDF_COLOR_MUTED . ';font-size:0.9rem;">' . $meta . '</p>'
            . '</div>';
    }

    private function renderSentiment(string $sentiment): string
    {
        if ($sentiment === '') return '';

        return '<div style="margin-bottom:1.5rem;">'
            . '<h2 style="font-size:1rem;text-transform:uppercase;letter-spacing:0.05em;color:' . self::PDF_COLOR_MUTED . ';margin-bottom:0.5rem;">Sentiment</h2>'
            . '<span style="background:' . self::PDF_COLOR_ACCENT . ';color:white;padding:0.2rem 0.75rem;border-radius:99px;font-size:0.85rem;font-weight:bold;">'
            . htmlspecialchars(ucfirst($sentiment))
            . '</span></div>';
    }

    /** @param string[] $phrases */
    private function renderPhrases(array $phrases): string
    {
        if (empty($phrases)) return '';

        return '<div style="margin-bottom:1.5rem;">'
            . '<h2 style="font-size:1rem;text-transform:uppercase;letter-spacing:0.05em;color:' . self::PDF_COLOR_MUTED . ';margin-bottom:0.5rem;">Key Phrases</h2>'
            . '<p style="margin:0;color:#333;">' . htmlspecialchars(implode(', ', $phrases)) . '</p>'
            . '</div>';
    }

    /** @param array<int, array{text: string, category: string}> $entities */
    private function renderEntities(array $entities): string
    {
        if (empty($entities)) return '';

        $rows = '';
        foreach ($entities as $e) {
            $rows .= '<tr>'
                . '<td style="padding:0.3rem 0.5rem;border-bottom:1px solid #eee;">' . htmlspecialchars($e['text'] ?? '') . '</td>'
                . '<td style="padding:0.3rem 0.5rem;border-bottom:1px solid #eee;color:' . self::PDF_COLOR_MUTED . ';text-align:right;">' . htmlspecialchars($e['category'] ?? '') . '</td>'
                . '</tr>';
        }

        return '<div style="margin-bottom:1.5rem;">'
            . '<h2 style="font-size:1rem;text-transform:uppercase;letter-spacing:0.05em;color:' . self::PDF_COLOR_MUTED . ';margin-bottom:0.75rem;">Entities</h2>'
            . '<table style="width:100%;border-collapse:collapse;font-size:0.875rem;">' . $rows . '</table>'
            . '</div>';
    }

    private function renderSummary(string $summary): string
    {
        if ($summary === '') return '';

        return '<div style="margin-bottom:1.5rem;background:#fff8f3;border-left:4px solid ' . self::PDF_COLOR_ACCENT . ';padding:1rem 1.25rem;border-radius:0 6px 6px 0;">'
            . '<h2 style="font-size:1rem;text-transform:uppercase;letter-spacing:0.05em;color:' . self::PDF_COLOR_ACCENT . ';margin:0 0 0.5rem;">Intelligence Summary</h2>'
            . '<p style="margin:0;color:#333;line-height:1.6;">' . htmlspecialchars($summary) . '</p>'
            . '</div>';
    }

    /** @param array<int, array{text: string, category: string}> $piiFound */
    private function renderPii(array $piiFound, string $redacted): string
    {
        if (empty($piiFound)) return '';

        $categories = implode(', ', array_map(fn($p) => $p['category'] ?? '', $piiFound));
        $html = '<div style="margin-bottom:1.5rem;">'
            . '<h2 style="font-size:1rem;text-transform:uppercase;letter-spacing:0.05em;color:' . self::PDF_COLOR_MUTED . ';margin-bottom:0.5rem;">PII Detected</h2>'
            . '<p style="margin:0;color:' . self::PDF_COLOR_DANGER . ';">' . htmlspecialchars($categories) . '</p>'
            . '</div>';

        if ($redacted !== '') {
            $preview = mb_substr($redacted, 0, self::PDF_MAX_REDACTED);
            $html .= '<div style="margin-bottom:1.5rem;">'
                . '<h2 style="font-size:1rem;text-transform:uppercase;letter-spacing:0.05em;color:' . self::PDF_COLOR_MUTED . ';margin-bottom:0.5rem;">Redacted Content</h2>'
                . '<div style="background:#f5f5f5;padding:0.875rem;border-radius:6px;font-family:monospace;font-size:0.82rem;white-space:pre-wrap;word-break:break-word;">'
                . htmlspecialchars($preview) . (mb_strlen($redacted) > self::PDF_MAX_REDACTED ? '…' : '')
                . '</div></div>';
        }

        return $html;
    }

    private function renderContent(string $content): string
    {
        if ($content === '') return '';

        $preview = mb_substr($content, 0, self::PDF_MAX_CONTENT);
        return '<div style="margin-bottom:1.5rem;">'
            . '<h2 style="font-size:1rem;text-transform:uppercase;letter-spacing:0.05em;color:' . self::PDF_COLOR_MUTED . ';margin-bottom:0.5rem;">Content Extract</h2>'
            . '<div style="background:' . self::PDF_COLOR_BG . ';padding:0.875rem;border-radius:6px;font-size:0.875rem;line-height:1.6;white-space:pre-wrap;word-break:break-word;">'
            . htmlspecialchars($preview) . (mb_strlen($content) > self::PDF_MAX_CONTENT ? '…' : '')
            . '</div></div>';
    }

    private function renderFooter(): string
    {
        return '<p style="margin-top:3rem;color:#aaa;font-size:0.78rem;border-top:1px solid #eee;padding-top:1rem;">'
            . 'Generated by Forge &mdash; AI-powered content analyzer'
            . '</p>';
    }
}
