<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (empty($_SESSION['result'])) {
    http_response_code(400);
    echo 'No analysis to export.';
    exit;
}

$result   = $_SESSION['result'];
$type     = $result['type']     ?? 'unknown';
$analysis = $result['analysis'] ?? [];
$pipeline = $result['pipeline'] ?? [];
$language = $result['language'] ?? [];
$date     = date('Y-m-d H:i');

$entities  = $analysis['entities']          ?? $language['entities']          ?? [];
$phrases   = $analysis['key_phrases']       ?? $language['key_phrases']       ?? [];
$sentiment = $analysis['sentiment']         ?? $language['sentiment']         ?? '';
$summary   = $pipeline['summary']           ?? '';
$piiFound  = $pipeline['pii_found']         ?? [];
$redacted  = $pipeline['redacted']          ?? '';
$filename  = $result['file']                ?? '';
$content   = $analysis['content']           ?? $result['content'] ?? '';

ob_start();
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Forge Report</title></head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; max-width: 750px; margin: 0 auto; padding: 2rem;">

<div style="border-bottom: 3px solid #f97316; padding-bottom: 1rem; margin-bottom: 2rem;">
    <h1 style="font-size: 1.8rem; margin: 0; color: #f97316;">Forge Analysis Report</h1>
    <p style="margin: 0.4rem 0 0; color: #666; font-size: 0.9rem;">
        Generated: <?= htmlspecialchars($date) ?>
        <?= $filename ? ' &mdash; ' . htmlspecialchars($filename) : '' ?>
        &mdash; Type: <?= htmlspecialchars(ucfirst($type)) ?>
    </p>
</div>

<?php if ($sentiment): ?>
<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.5rem;">Sentiment</h2>
    <span style="background: #f97316; color: white; padding: 0.2rem 0.75rem; border-radius: 99px; font-size: 0.85rem; font-weight: bold;">
        <?= htmlspecialchars(ucfirst($sentiment)) ?>
    </span>
</div>
<?php endif; ?>

<?php if (!empty($phrases)): ?>
<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.5rem;">Key Phrases</h2>
    <p style="margin: 0; color: #333;"><?= htmlspecialchars(implode(', ', $phrases)) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($entities)): ?>
<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.75rem;">Entities</h2>
    <table style="width:100%; border-collapse: collapse; font-size: 0.875rem;">
        <?php foreach ($entities as $e): ?>
        <tr>
            <td style="padding: 0.3rem 0.5rem; border-bottom: 1px solid #eee;"><?= htmlspecialchars($e['text']) ?></td>
            <td style="padding: 0.3rem 0.5rem; border-bottom: 1px solid #eee; color: #888; text-align: right;"><?= htmlspecialchars($e['category']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if ($summary): ?>
<div style="margin-bottom: 1.5rem; background: #fff8f3; border-left: 4px solid #f97316; padding: 1rem 1.25rem; border-radius: 0 6px 6px 0;">
    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: #f97316; margin: 0 0 0.5rem;">Intelligence Summary</h2>
    <p style="margin: 0; color: #333; line-height: 1.6;"><?= htmlspecialchars($summary) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($piiFound)): ?>
<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.5rem;">PII Detected</h2>
    <p style="margin: 0; color: #e53e3e;"><?= htmlspecialchars(implode(', ', array_map(fn($p) => $p['category'], $piiFound))) ?></p>
</div>
<?php endif; ?>

<?php if ($redacted && !empty($piiFound)): ?>
<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.5rem;">Redacted Content</h2>
    <div style="background: #f5f5f5; padding: 0.875rem; border-radius: 6px; font-family: monospace; font-size: 0.82rem; white-space: pre-wrap; word-break: break-word;">
        <?= htmlspecialchars(mb_substr($redacted, 0, 1000)) ?><?= mb_strlen($redacted) > 1000 ? '…' : '' ?>
    </div>
</div>
<?php endif; ?>

<?php if ($content): ?>
<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.5rem;">Content Extract</h2>
    <div style="background: #f9f9f9; padding: 0.875rem; border-radius: 6px; font-size: 0.875rem; line-height: 1.6; white-space: pre-wrap; word-break: break-word;">
        <?= htmlspecialchars(mb_substr($content, 0, 2000)) ?><?= mb_strlen($content) > 2000 ? '…' : '' ?>
    </div>
</div>
<?php endif; ?>

<p style="margin-top: 3rem; color: #aaa; font-size: 0.78rem; border-top: 1px solid #eee; padding-top: 1rem;">
    Generated by Forge &mdash; AI-powered content analyzer
</p>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Dompdf\Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'forge-report-' . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $dompdf->output();
