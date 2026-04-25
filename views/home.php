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
            </div>

            <div class="tab-panel hidden" id="tab-text">
                <textarea
                    name="text"
                    id="text-input"
                    class="text-area"
                    placeholder="Paste your text here - articles, reviews, documents, code..."
                    rows="10"
                ></textarea>
            </div>

            <button type="submit" class="submit-btn" id="submit-btn">
                <span class="submit-label">Analyze</span>
                <span class="submit-spinner hidden" id="submit-spinner">
                    <span class="spinner"></span>
                </span>
            </button>
        </form>
    </div>
</div>
<?php
$body  = ob_get_clean();
$title = 'Home';
require __DIR__ . '/layout.php';
