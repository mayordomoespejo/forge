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
        <?php
        $pipeline = $result['pipeline'] ?? [];
        $safetyBlocked = $pipeline['blocked'] ?? false;
        $safetyFlags   = $pipeline['safety']['flags'] ?? [];
        if ($safetyBlocked): ?>
        <div class="safety-warning">
            <strong>Content blocked</strong> — This content was flagged by the safety filter and has not been processed.
            <?php if (!empty($safetyFlags)): ?>
            <div class="safety-flags">
                <?php foreach ($safetyFlags as $flag): ?>
                <span class="tag tag-pii"><?= htmlspecialchars($flag['category']) ?> (severity <?= (int)$flag['severity'] ?>)</span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
                $entityLinks = $analysis['entity_links'] ?? [];
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
                        <?php foreach ($entities as $entity):
                            $link = null;
                            foreach ($entityLinks as $el) {
                                if (strcasecmp($el['match'], $entity['text']) === 0 && $el['url'] !== '') {
                                    $link = $el['url'];
                                    break;
                                }
                            }
                        ?>
                        <li class="entity-item">
                            <?php if ($link): ?>
                            <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="entity-link">
                                <?= htmlspecialchars($entity['text']) ?>
                                <span class="entity-link-icon">&#8599;</span>
                            </a>
                            <?php else: ?>
                            <span class="entity-text"><?= htmlspecialchars($entity['text']) ?></span>
                            <?php endif; ?>
                            <span class="entity-cat"><?= htmlspecialchars($entity['category']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php
                $opinions = $analysis['opinions'] ?? [];
                if (!empty($opinions)): ?>
                <div class="section">
                    <h3 class="section-title">Opinion Mining</h3>
                    <ul class="entity-list">
                        <?php foreach ($opinions as $op): ?>
                        <li class="entity-item">
                            <span class="entity-text"><?= htmlspecialchars($op['target']) ?></span>
                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                <?php if (!empty($op['assessments'])): ?>
                                <span class="entity-cat"><?= htmlspecialchars(implode(', ', $op['assessments'])) ?></span>
                                <?php endif; ?>
                                <span class="badge badge-sentiment badge-<?= htmlspecialchars($op['sentiment']) ?>"><?= htmlspecialchars(ucfirst($op['sentiment'])) ?></span>
                            </div>
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
                    $langEntityLinks = $lang['entity_links'] ?? [];
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
                        <?php foreach ($langEntities as $entity):
                            $link = null;
                            foreach ($langEntityLinks as $el) {
                                if (strcasecmp($el['match'], $entity['text']) === 0 && $el['url'] !== '') {
                                    $link = $el['url'];
                                    break;
                                }
                            }
                        ?>
                        <li class="entity-item">
                            <?php if ($link): ?>
                            <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="entity-link">
                                <?= htmlspecialchars($entity['text']) ?>
                                <span class="entity-link-icon">&#8599;</span>
                            </a>
                            <?php else: ?>
                            <span class="entity-text"><?= htmlspecialchars($entity['text']) ?></span>
                            <?php endif; ?>
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
                $fields     = $analysis['fields']     ?? [];
                $docModel   = $_SESSION['result']['doc_model'] ?? '';
                ?>
                <div class="meta-row">
                    <span class="badge badge-pages"><?= $pageCount ?> page<?= $pageCount !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (!empty($fields)): ?>
                <div class="section">
                    <h3 class="section-title">Extracted fields</h3>
                    <ul class="entity-list">
                        <?php foreach ($fields as $fieldName => $fieldValue): ?>
                        <?php if (is_array($fieldValue)): ?>
                        <li class="entity-item" style="flex-direction: column; align-items: flex-start; gap: 0.3rem;">
                            <span class="entity-cat"><?= htmlspecialchars($fieldName) ?></span>
                            <div class="table-scroll" style="width:100%;">
                                <table class="data-table">
                                    <?php foreach ($fieldValue as $ri => $row): ?>
                                    <tr>
                                        <?php foreach ($row as $k => $v): ?>
                                        <?php if ($ri === 0): ?>
                                        <th><?= htmlspecialchars($k) ?></th>
                                        <?php else: ?>
                                        <td><?= htmlspecialchars((string)$v) ?></td>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </li>
                        <?php else: ?>
                        <li class="entity-item">
                            <span class="entity-text"><?= htmlspecialchars((string)$fieldValue) ?></span>
                            <span class="entity-cat"><?= htmlspecialchars($fieldName) ?></span>
                        </li>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

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
                    $langEntityLinks = $lang['entity_links'] ?? [];
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
                        <?php foreach ($langEntities as $entity):
                            $link = null;
                            foreach ($langEntityLinks as $el) {
                                if (strcasecmp($el['match'], $entity['text']) === 0 && $el['url'] !== '') {
                                    $link = $el['url'];
                                    break;
                                }
                            }
                        ?>
                        <li class="entity-item">
                            <?php if ($link): ?>
                            <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="entity-link">
                                <?= htmlspecialchars($entity['text']) ?>
                                <span class="entity-link-icon">&#8599;</span>
                            </a>
                            <?php else: ?>
                            <span class="entity-text"><?= htmlspecialchars($entity['text']) ?></span>
                            <?php endif; ?>
                            <span class="entity-cat"><?= htmlspecialchars($entity['category']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($type === 'audio'): ?>
                <?php
                $transcript   = $result['content']  ?? '';
                $langAnalysis = $result['analysis'] ?? [];
                $sentiment    = $langAnalysis['sentiment']           ?? '';
                $scores       = $langAnalysis['sentiment_scores']    ?? [];
                $phrases      = $langAnalysis['key_phrases']         ?? [];
                $entities     = $langAnalysis['entities']            ?? [];
                $language     = $langAnalysis['language']            ?? '';
                $langConf     = $langAnalysis['language_confidence'] ?? 0.0;
                $opinions     = $langAnalysis['opinions']            ?? [];
                $entityLinks  = $langAnalysis['entity_links']        ?? [];
                ?>

                <div class="meta-row">
                    <span class="badge badge-lang">
                        <?= htmlspecialchars($language ?: 'Unknown') ?>
                        <?php if ($langConf > 0): ?>
                        <span class="badge-sub"><?= round($langConf * 100) ?>%</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($sentiment): ?>
                    <span class="badge badge-sentiment badge-<?= htmlspecialchars($sentiment) ?>">
                        <?= htmlspecialchars(ucfirst($sentiment)) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($transcript): ?>
                <div class="section">
                    <h3 class="section-title">Transcript</h3>
                    <div class="extracted-text"><?= nl2br(htmlspecialchars($transcript)) ?></div>
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
                        <?php foreach ($entities as $entity):
                            $link = null;
                            foreach ($entityLinks as $el) {
                                if (strcasecmp($el['match'], $entity['text']) === 0 && $el['url'] !== '') {
                                    $link = $el['url'];
                                    break;
                                }
                            }
                        ?>
                        <li class="entity-item">
                            <?php if ($link): ?>
                            <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener" class="entity-link">
                                <?= htmlspecialchars($entity['text']) ?>
                                <span class="entity-link-icon">&#8599;</span>
                            </a>
                            <?php else: ?>
                            <span class="entity-text"><?= htmlspecialchars($entity['text']) ?></span>
                            <?php endif; ?>
                            <span class="entity-cat"><?= htmlspecialchars($entity['category']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($opinions)): ?>
                <div class="section">
                    <h3 class="section-title">Opinion Mining</h3>
                    <ul class="entity-list">
                        <?php foreach ($opinions as $op): ?>
                        <li class="entity-item">
                            <span class="entity-text"><?= htmlspecialchars($op['target']) ?></span>
                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                <?php if (!empty($op['assessments'])): ?>
                                <span class="entity-cat"><?= htmlspecialchars(implode(', ', $op['assessments'])) ?></span>
                                <?php endif; ?>
                                <span class="badge badge-sentiment badge-<?= htmlspecialchars($op['sentiment']) ?>"><?= htmlspecialchars(ucfirst($op['sentiment'])) ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <p class="muted">No analysis data available.</p>
            <?php endif; ?>

            <?php if (!$error): ?>
            <?php
            $pipeline = $result['pipeline'] ?? [];
            if (!empty($pipeline)):
                $pipEntities  = $pipeline['entities']  ?? [];
                $pipPiiFound  = $pipeline['pii_found'] ?? [];
                $pipRedacted  = $pipeline['redacted']  ?? '';
                $pipConsistent = $pipeline['consistent'] ?? false;
                $pipSummary   = $pipeline['summary']   ?? null;
                $pipConf      = $pipeline['confidence'] ?? 0.0;
            ?>
            <div class="section intelligence-section">
                <h3 class="section-title">Intelligence Report</h3>

                <?php if (!empty($pipPiiFound)): ?>
                <div class="intel-block">
                    <span class="intel-label">PII detected</span>
                    <div class="tag-cloud">
                        <?php foreach ($pipPiiFound as $pii): ?>
                        <span class="tag tag-pii">
                            <span class="pii-dot"></span>
                            <?= htmlspecialchars($pii['category']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($pipRedacted && !empty($pipPiiFound)): ?>
                <div class="intel-block">
                    <span class="intel-label">Redacted preview</span>
                    <div class="redacted-preview"><?= nl2br(htmlspecialchars(mb_substr($pipRedacted, 0, 400))) ?><?= mb_strlen($pipRedacted) > 400 ? '…' : '' ?></div>
                </div>
                <?php endif; ?>

                <?php if ($pipConsistent && $pipSummary): ?>
                <div class="intel-block">
                    <span class="intel-label">
                        Summary
                        <span class="intel-confidence"><?= round($pipConf * 100) ?>% confidence</span>
                        <?php $method = $pipeline['method'] ?? 'abstractive'; ?>
                        <span class="intel-method"><?= $method === 'extractive' ? 'Azure extractive' : 'AI generated' ?></span>
                    </span>
                    <p class="summary-text"><?= htmlspecialchars($pipSummary) ?></p>
                </div>
                <?php elseif (!$pipConsistent): ?>
                <div class="intel-block">
                    <span class="intel-label muted">Not enough content to summarize</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
