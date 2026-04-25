<?php
/** @var array<string, mixed> $result */

ob_start();
$type     = $result['type']     ?? 'unknown';
$analysis = $result['analysis'] ?? [];
$error    = $result['error']    ?? null;
?>
<div class="results-container">
    <div class="results-topbar">
        <a href="/" class="back-link">&#8592; New analysis</a>
        <span class="results-type-badge type-<?= htmlspecialchars($type) ?>">
            <?= htmlspecialchars(ucfirst($type)) ?>
        </span>
    </div>

    <div class="results-columns">
        <div class="analysis-panel">
            <h2 class="panel-title">Analysis</h2>

            <?php if ($error): ?>
                <div class="error-box">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>

            <?php elseif ($type === 'text'): ?>
                <?php
                $sentiment = $analysis['sentiment'] ?? 'unknown';
                $scores    = $analysis['sentiment_scores'] ?? [];
                $phrases   = $analysis['key_phrases']      ?? [];
                $entities  = $analysis['entities']         ?? [];
                $language  = $analysis['language']         ?? 'Unknown';
                $langConf  = $analysis['language_confidence'] ?? 0.0;
                ?>
                <div class="meta-row">
                    <span class="badge badge-sentiment badge-<?= htmlspecialchars($sentiment) ?>">
                        <?= htmlspecialchars(ucfirst($sentiment)) ?>
                    </span>
                    <span class="badge badge-lang">
                        <?= htmlspecialchars($language) ?>
                        <span class="badge-sub"><?= round($langConf * 100) ?>%</span>
                    </span>
                </div>

                <?php if (!empty($scores)): ?>
                <div class="scores-bar-group">
                    <?php foreach (['positive', 'neutral', 'negative'] as $s): ?>
                    <div class="score-bar-row">
                        <span class="score-label"><?= ucfirst($s) ?></span>
                        <div class="score-track">
                            <div class="score-fill score-<?= $s ?>"
                                 style="width: <?= round(($scores[$s] ?? 0) * 100) ?>%"></div>
                        </div>
                        <span class="score-value"><?= round(($scores[$s] ?? 0) * 100) ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($phrases)): ?>
                <div class="section">
                    <h3 class="section-title">Key phrases</h3>
                    <div class="tag-cloud">
                        <?php foreach ($phrases as $phrase): ?>
                        <span class="tag"><?= htmlspecialchars($phrase) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($entities)): ?>
                <div class="section">
                    <h3 class="section-title">Entities</h3>
                    <ul class="entity-list">
                        <?php foreach ($entities as $entity): ?>
                        <li class="entity-item">
                            <span class="entity-text"><?= htmlspecialchars($entity['text']) ?></span>
                            <span class="entity-cat"><?= htmlspecialchars($entity['category']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

            <?php elseif ($type === 'image'): ?>
                <?php
                $description = $analysis['description']  ?? '';
                $objects     = $analysis['objects']       ?? [];
                $colors      = $analysis['colors']        ?? [];
                $textInImage = $analysis['text_in_image'] ?? null;
                $mood        = $analysis['mood']          ?? '';
                ?>
                <?php if ($description): ?>
                <div class="section">
                    <h3 class="section-title">Description</h3>
                    <p class="description-text"><?= htmlspecialchars($description) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($mood): ?>
                <div class="meta-row">
                    <span class="badge badge-mood">Mood: <?= htmlspecialchars($mood) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($objects)): ?>
                <div class="section">
                    <h3 class="section-title">Objects detected</h3>
                    <div class="tag-cloud">
                        <?php foreach ($objects as $obj): ?>
                        <span class="tag"><?= htmlspecialchars($obj) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($colors)): ?>
                <div class="section">
                    <h3 class="section-title">Colors</h3>
                    <div class="color-list">
                        <?php foreach ($colors as $color): ?>
                        <span class="color-swatch">
                            <span class="color-dot" style="background: <?= htmlspecialchars($color) ?>"></span>
                            <span class="color-name"><?= htmlspecialchars($color) ?></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($textInImage): ?>
                <div class="section">
                    <h3 class="section-title">Text in image</h3>
                    <pre class="code-block"><?= htmlspecialchars($textInImage) ?></pre>
                </div>
                <?php endif; ?>

                <?php
                $lang = $result['language'] ?? [];
                if (!empty($lang)):
                    $langSentiment = $lang['sentiment'] ?? '';
                    $langScores    = $lang['sentiment_scores'] ?? [];
                    $langPhrases   = $lang['key_phrases'] ?? [];
                    $langEntities  = $lang['entities'] ?? [];
                ?>
                <?php if ($langSentiment): ?>
                <div class="meta-row">
                    <span class="badge badge-sentiment badge-<?= htmlspecialchars($langSentiment) ?>">
                        <?= htmlspecialchars(ucfirst($langSentiment)) ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($langScores)): ?>
                <div class="scores-bar-group">
                    <?php foreach (['positive', 'neutral', 'negative'] as $s): ?>
                    <div class="score-bar-row">
                        <span class="score-label"><?= ucfirst($s) ?></span>
                        <div class="score-track">
                            <div class="score-fill score-<?= $s ?>"
                                 style="width: <?= round(($langScores[$s] ?? 0) * 100) ?>%"></div>
                        </div>
                        <span class="score-value"><?= round(($langScores[$s] ?? 0) * 100) ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($langPhrases)): ?>
                <div class="section">
                    <h3 class="section-title">Key phrases</h3>
                    <div class="tag-cloud">
                        <?php foreach ($langPhrases as $phrase): ?>
                        <span class="tag"><?= htmlspecialchars($phrase) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($langEntities)): ?>
                <div class="section">
                    <h3 class="section-title">Entities</h3>
                    <ul class="entity-list">
                        <?php foreach ($langEntities as $entity): ?>
                        <li class="entity-item">
                            <span class="entity-text"><?= htmlspecialchars($entity['text']) ?></span>
                            <span class="entity-cat"><?= htmlspecialchars($entity['category']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($type === 'document'): ?>
                <?php
                $pageCount  = $analysis['page_count'] ?? 0;
                $content    = $analysis['content']    ?? '';
                $tables     = $analysis['tables']     ?? [];
                ?>
                <div class="meta-row">
                    <span class="badge badge-pages"><?= $pageCount ?> page<?= $pageCount !== 1 ? 's' : '' ?></span>
                </div>

                <?php if ($content): ?>
                <div class="section">
                    <h3 class="section-title">Extracted text</h3>
                    <div class="extracted-text"><?= nl2br(htmlspecialchars($content)) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($tables)): ?>
                <div class="section">
                    <h3 class="section-title">Tables (<?= count($tables) ?>)</h3>
                    <?php foreach ($tables as $ti => $table): ?>
                    <div class="table-wrapper">
                        <p class="table-label">Table <?= $ti + 1 ?></p>
                        <div class="table-scroll">
                            <table class="data-table">
                                <?php foreach ($table as $ri => $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                    <?php if ($ri === 0): ?>
                                    <th><?= htmlspecialchars($cell) ?></th>
                                    <?php else: ?>
                                    <td><?= htmlspecialchars($cell) ?></td>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                $lang = $result['language'] ?? [];
                if (!empty($lang)):
                    $langSentiment = $lang['sentiment'] ?? '';
                    $langScores    = $lang['sentiment_scores'] ?? [];
                    $langPhrases   = $lang['key_phrases'] ?? [];
                    $langEntities  = $lang['entities'] ?? [];
                ?>
                <?php if ($langSentiment): ?>
                <div class="meta-row">
                    <span class="badge badge-sentiment badge-<?= htmlspecialchars($langSentiment) ?>">
                        <?= htmlspecialchars(ucfirst($langSentiment)) ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($langScores)): ?>
                <div class="scores-bar-group">
                    <?php foreach (['positive', 'neutral', 'negative'] as $s): ?>
                    <div class="score-bar-row">
                        <span class="score-label"><?= ucfirst($s) ?></span>
                        <div class="score-track">
                            <div class="score-fill score-<?= $s ?>"
                                 style="width: <?= round(($langScores[$s] ?? 0) * 100) ?>%"></div>
                        </div>
                        <span class="score-value"><?= round(($langScores[$s] ?? 0) * 100) ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($langPhrases)): ?>
                <div class="section">
                    <h3 class="section-title">Key phrases</h3>
                    <div class="tag-cloud">
                        <?php foreach ($langPhrases as $phrase): ?>
                        <span class="tag"><?= htmlspecialchars($phrase) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($langEntities)): ?>
                <div class="section">
                    <h3 class="section-title">Entities</h3>
                    <ul class="entity-list">
                        <?php foreach ($langEntities as $entity): ?>
                        <li class="entity-item">
                            <span class="entity-text"><?= htmlspecialchars($entity['text']) ?></span>
                            <span class="entity-cat"><?= htmlspecialchars($entity['category']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <p class="muted">No analysis data available.</p>
            <?php endif; ?>

            <?php if (!$error): ?>
            <div class="section translate-section">
                <h3 class="section-title">Translate</h3>
                <div class="translate-controls">
                    <select id="translate-lang" class="translate-select">
                        <option value="en">English</option>
                        <option value="es">Spanish</option>
                        <option value="fr">French</option>
                        <option value="de">German</option>
                        <option value="pt">Portuguese</option>
                        <option value="it">Italian</option>
                        <option value="ja">Japanese</option>
                        <option value="zh-Hans">Chinese (Simplified)</option>
                        <option value="ar">Arabic</option>
                        <option value="ko">Korean</option>
                        <option value="ru">Russian</option>
                    </select>
                    <button type="button" class="translate-btn" id="translate-btn">Translate content</button>
                </div>
                <div id="translate-output" class="translate-output hidden">
                    <div class="translate-result-label">Translation</div>
                    <div id="translate-text" class="translate-text"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="chat-panel">
            <h2 class="panel-title">Chat</h2>

            <div class="chat-messages" id="chat-messages">
                <div class="chat-bubble assistant">
                    <div class="bubble-content">
                        Ask me anything about the analyzed content.
                    </div>
                </div>
            </div>

            <form class="chat-form" id="chat-form" autocomplete="off">
                <textarea
                    id="chat-input"
                    class="chat-input"
                    placeholder="Ask a question..."
                    rows="1"
                ></textarea>
                <button type="submit" class="chat-send-btn" id="chat-send">
                    <span>Send</span>
                    <span class="send-arrow">&#10148;</span>
                </button>
            </form>
        </div>
    </div>
</div>
<?php
$body  = ob_get_clean();
$title = ucfirst($type) . ' results';
require __DIR__ . '/layout.php';
