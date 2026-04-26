<?php
ob_start();
?>
<div class="home-container">
    <div class="home-hero">
        <h1 class="hero-title">Analyze anything.</h1>
        <p class="hero-subtitle">Drop a file or paste text - get structured AI insights and an interactive chat.</p>
    </div>

    <div class="upload-card">
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="file">File upload</button>
            <button class="tab-btn" data-tab="text">Paste text</button>
            <button class="tab-btn" data-tab="audio">Audio</button>
        </div>

        <form id="analyze-form" action="/analyze" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="mode" id="mode-input" value="file">

            <div class="tab-panel" id="tab-file">
                <div class="drop-zone" id="drop-zone">
                    <input type="file" name="file" id="file-input"
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"
                           class="file-input-hidden">
                    <div class="drop-zone-content" id="drop-zone-content">
                        <div class="drop-icon">&#8679;</div>
                        <p class="drop-primary">Drag &amp; drop a file here</p>
                        <p class="drop-secondary">or <label for="file-input" class="browse-link">browse to upload</label></p>
                        <p class="drop-hint">PDF, JPG, PNG, GIF, WEBP</p>
                    </div>
                    <div class="drop-zone-selected hidden" id="drop-zone-selected">
                        <div class="selected-icon">&#10003;</div>
                        <p class="selected-name" id="selected-name"></p>
                        <button type="button" class="remove-file-btn" id="remove-file">&#10005; Remove</button>
                    </div>
                </div>
                <div id="doc-model-wrap">
                    <div class="doc-model-selector">
                        <label class="doc-model-label" for="doc-model">Document type</label>
                        <select name="doc_model" id="doc-model" class="translate-select">
                            <option value="prebuilt-read">General document</option>
                            <option value="prebuilt-invoice">Invoice</option>
                            <option value="prebuilt-receipt">Receipt</option>
                            <option value="prebuilt-idDocument">ID Document</option>
                            <option value="prebuilt-businessCard">Business card</option>
                        </select>
                    </div>
                </div>
                <label class="medical-toggle" style="padding: 0 1.5rem 1rem;">
                    <input type="checkbox" name="medical_mode" value="1" id="medical-mode-file">
                    <span class="medical-toggle-label">Medical mode</span>
                    <span class="medical-toggle-hint">Detects medications, diagnoses, symptoms</span>
                </label>
            </div>

            <div class="tab-panel hidden" id="tab-text">
                <textarea
                    name="text"
                    id="text-input"
                    class="text-area"
                    placeholder="Paste your text here - articles, reviews, documents, code..."
                    rows="10"
                ></textarea>
                <label class="medical-toggle">
                    <input type="checkbox" name="medical_mode" value="1" id="medical-mode">
                    <span class="medical-toggle-label">Medical mode</span>
                    <span class="medical-toggle-hint">Detects medications, diagnoses, symptoms</span>
                </label>
            </div>

            <div class="tab-panel hidden" id="tab-audio">
                <div class="drop-zone" id="drop-zone-audio">
                    <input type="file" name="file" id="audio-input"
                           accept=".mp3,.wav,.ogg,.flac,.m4a,.webm"
                           class="file-input-hidden">
                    <div class="drop-zone-content" id="drop-zone-audio-content">
                        <div class="drop-icon">&#9654;</div>
                        <p class="drop-primary">Drag &amp; drop an audio file</p>
                        <p class="drop-secondary">or <label for="audio-input" class="browse-link">browse to upload</label></p>
                        <p class="drop-hint">MP3, WAV, OGG, FLAC &mdash; max ~60 seconds</p>
                    </div>
                    <div class="drop-zone-selected hidden" id="drop-zone-audio-selected">
                        <div class="selected-icon">&#10003;</div>
                        <p class="selected-name" id="audio-selected-name"></p>
                        <button type="button" class="remove-file-btn" id="remove-audio">&#10005; Remove</button>
                    </div>
                </div>
                <div class="doc-model-selector" style="margin-top: 1rem;">
                    <label class="doc-model-label" for="speech-language">Audio language</label>
                    <select name="speech_language" id="speech-language" class="translate-select">
                        <option value="en-US">English (US)</option>
                        <option value="es-ES">Spanish (ES)</option>
                        <option value="fr-FR">French</option>
                        <option value="de-DE">German</option>
                        <option value="pt-BR">Portuguese (BR)</option>
                        <option value="it-IT">Italian</option>
                        <option value="ja-JP">Japanese</option>
                        <option value="zh-CN">Chinese (Simplified)</option>
                        <option value="ar-SA">Arabic</option>
                        <option value="ko-KR">Korean</option>
                        <option value="ru-RU">Russian</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="submit-btn" id="submit-btn">
                <span class="submit-label">Analyze</span>
                <span class="submit-spinner hidden" id="submit-spinner">
                    <span class="spinner"></span>
                </span>
            </button>
        </form>
        <script>
        (function() {
            var docModelWrap = document.getElementById('doc-model-wrap');
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (docModelWrap) docModelWrap.style.display = btn.dataset.tab === 'file' ? '' : 'none';
                });
            });
        }());
        </script>
    </div>
</div>
<?php
$body  = ob_get_clean();
$title = 'Home';
require __DIR__ . '/layout.php';
