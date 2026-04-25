<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

ob_start();

$search  = new Forge\Services\SearchService();
$entries = $search->isConfigured() ? $search->all(30) : [];
$query   = trim($_GET['q'] ?? '');

if ($query !== '' && $search->isConfigured()) {
    $entries = $search->search($query, 20);
}
?>
<div class="history-container">
    <div class="results-topbar">
        <a href="/" class="back-link">&#8592; New analysis</a>
        <span class="results-type-badge">History</span>
    </div>

    <form class="history-search-form" method="GET" action="/history">
        <input
            type="text"
            name="q"
            class="history-search-input"
            placeholder="Search past analyses..."
            value="<?= htmlspecialchars($query) ?>"
        >
        <button type="submit" class="chat-send-btn">Search</button>
    </form>

    <?php if (!$search->isConfigured()): ?>
    <div class="error-box">
        Azure AI Search is not configured. Add <code>AZURE_SEARCH_ENDPOINT</code>, <code>AZURE_SEARCH_KEY</code>, and <code>AZURE_SEARCH_INDEX</code> to your <code>.env</code> file.
    </div>
    <?php elseif (empty($entries)): ?>
    <p class="muted" style="margin-top: 2rem; text-align: center;">No analyses found<?= $query ? ' for "' . htmlspecialchars($query) . '"' : '' ?>.</p>
    <?php else: ?>
    <div class="history-list">
        <?php foreach ($entries as $entry): ?>
        <div class="history-card">
            <div class="history-card-meta">
                <span class="results-type-badge type-<?= htmlspecialchars($entry['type']) ?>">
                    <?= htmlspecialchars(ucfirst($entry['type'])) ?>
                </span>
                <?php if ($entry['filename']): ?>
                <span class="history-filename"><?= htmlspecialchars($entry['filename']) ?></span>
                <?php endif; ?>
                <?php if ($entry['language']): ?>
                <span class="badge badge-lang"><?= htmlspecialchars($entry['language']) ?></span>
                <?php endif; ?>
                <span class="history-date"><?= htmlspecialchars(substr($entry['timestamp'], 0, 10)) ?></span>
            </div>
            <?php if ($entry['summary']): ?>
            <p class="history-summary"><?= htmlspecialchars($entry['summary']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
$body  = ob_get_clean();
$title = 'History';
require __DIR__ . '/layout.php';
