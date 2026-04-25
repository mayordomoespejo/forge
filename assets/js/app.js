/* ============================================================
   Forge - frontend behaviour
   ============================================================ */

(function () {
  'use strict';

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

  /* Tab switching */
  var tabBtns   = $$('.tab-btn');
  var modeInput = $('#mode-input');

  tabBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.dataset.tab;
      tabBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      $$('.tab-panel').forEach(function (p) { p.classList.add('hidden'); });
      var panel = $('#tab-' + tab);
      if (panel) panel.classList.remove('hidden');
      if (modeInput) modeInput.value = tab;
    });
  });

  /* Drag & drop */
  var dropZone      = $('#drop-zone');
  var fileInput     = $('#file-input');
  var dropContent   = $('#drop-zone-content');
  var dropSelected  = $('#drop-zone-selected');
  var selectedName  = $('#selected-name');
  var removeFileBtn = $('#remove-file');

  function showSelectedFile(name) {
    if (!dropContent || !dropSelected) return;
    dropContent.classList.add('hidden');
    dropSelected.classList.remove('hidden');
    if (selectedName) selectedName.textContent = name;
  }

  function clearSelectedFile() {
    if (!dropContent || !dropSelected) return;
    dropSelected.classList.add('hidden');
    dropContent.classList.remove('hidden');
    if (fileInput) fileInput.value = '';
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      if (this.files && this.files[0]) showSelectedFile(this.files[0].name);
    });
  }

  if (removeFileBtn) {
    removeFileBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      clearSelectedFile();
    });
  }

  if (dropZone) {
    dropZone.addEventListener('dragover', function (e) {
      e.preventDefault();
      dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', function () {
      dropZone.classList.remove('dragover');
    });
    dropZone.addEventListener('drop', function (e) {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      var files = e.dataTransfer && e.dataTransfer.files;
      if (files && files[0] && fileInput) {
        try {
          var dt = new DataTransfer();
          dt.items.add(files[0]);
          fileInput.files = dt.files;
        } catch (ex) {}
        showSelectedFile(files[0].name);
      }
    });
  }

  /* Audio drag & drop */
  var audioDropZone     = $('#drop-zone-audio');
  var audioInput        = $('#audio-input');
  var audioDropContent  = $('#drop-zone-audio-content');
  var audioDropSelected = $('#drop-zone-audio-selected');
  var audioSelectedName = $('#audio-selected-name');
  var removeAudioBtn    = $('#remove-audio');

  function showSelectedAudio(name) {
    if (!audioDropContent || !audioDropSelected) return;
    audioDropContent.classList.add('hidden');
    audioDropSelected.classList.remove('hidden');
    if (audioSelectedName) audioSelectedName.textContent = name;
  }

  function clearSelectedAudio() {
    if (!audioDropContent || !audioDropSelected) return;
    audioDropSelected.classList.add('hidden');
    audioDropContent.classList.remove('hidden');
    if (audioInput) audioInput.value = '';
  }

  if (audioInput) {
    audioInput.addEventListener('change', function () {
      if (this.files && this.files[0]) showSelectedAudio(this.files[0].name);
    });
  }

  if (removeAudioBtn) {
    removeAudioBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      clearSelectedAudio();
    });
  }

  if (audioDropZone) {
    audioDropZone.addEventListener('dragover', function (e) {
      e.preventDefault();
      audioDropZone.classList.add('dragover');
    });
    audioDropZone.addEventListener('dragleave', function () {
      audioDropZone.classList.remove('dragover');
    });
    audioDropZone.addEventListener('drop', function (e) {
      e.preventDefault();
      audioDropZone.classList.remove('dragover');
      var files = e.dataTransfer && e.dataTransfer.files;
      if (files && files[0] && audioInput) {
        try {
          var dt = new DataTransfer();
          dt.items.add(files[0]);
          audioInput.files = dt.files;
        } catch (ex) {}
        showSelectedAudio(files[0].name);
      }
    });
  }

  /* Submit spinner */
  var analyzeForm   = $('#analyze-form');
  var submitBtn     = $('#submit-btn');
  var submitLabel   = submitBtn && submitBtn.querySelector('.submit-label');
  var submitSpinner = $('#submit-spinner');

  if (analyzeForm) {
    analyzeForm.addEventListener('submit', function () {
      if (submitBtn) submitBtn.disabled = true;
      if (submitLabel) submitLabel.textContent = 'Analyzing...';
      if (submitSpinner) submitSpinner.classList.remove('hidden');
    });
  }

  /* Auto-resize textarea */
  function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
  }

  $$('textarea').forEach(function (ta) {
    ta.addEventListener('input', function () { autoResize(ta); });
  });

  /* Chat */
  var chatForm     = $('#chat-form');
  var chatInput    = $('#chat-input');
  var chatMessages = $('#chat-messages');
  var chatSendBtn  = $('#chat-send');

  if (!chatForm) return;

  var history = [];

  function scrollToBottom() {
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function appendBubble(role, text) {
    var bubble  = document.createElement('div');
    bubble.className = 'chat-bubble ' + role;
    var content = document.createElement('div');
    content.className = 'bubble-content';
    content.textContent = text;
    bubble.appendChild(content);
    chatMessages.appendChild(bubble);
    scrollToBottom();
    return bubble;
  }

  function appendTyping() {
    var bubble  = document.createElement('div');
    bubble.className = 'chat-bubble assistant typing';
    var content = document.createElement('div');
    content.className = 'bubble-content';
    for (var i = 0; i < 3; i++) {
      var dot = document.createElement('span');
      dot.className = 'typing-dot';
      content.appendChild(dot);
    }
    bubble.appendChild(content);
    chatMessages.appendChild(bubble);
    scrollToBottom();
    return bubble;
  }

  chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var message = chatInput ? chatInput.value.trim() : '';
    if (!message) return;

    appendBubble('user', message);
    history.push({ role: 'user', content: message });

    if (chatInput) { chatInput.value = ''; autoResize(chatInput); }
    if (chatSendBtn) chatSendBtn.disabled = true;

    var typingBubble = appendTyping();

    fetch('/ajax/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: message, history: history.slice(0, -1) }),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        typingBubble.remove();
        if (data.success && data.message) {
          appendBubble('assistant', data.message);
          history.push({ role: 'assistant', content: data.message });
        } else {
          appendBubble('assistant', data.error || 'An error occurred.');
        }
      })
      .catch(function (err) {
        typingBubble.remove();
        appendBubble('assistant', 'Network error. Please try again.');
        console.error('Chat error:', err);
      })
      .finally(function () {
        if (chatSendBtn) chatSendBtn.disabled = false;
        if (chatInput) chatInput.focus();
      });
  });

  if (chatInput) {
    chatInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });
  }
}());

// ── Translation ─────────────────────────────────────────────
(function () {
    const btn    = document.getElementById('translate-btn');
    const select = document.getElementById('translate-lang');
    const output = document.getElementById('translate-output');
    const target = document.getElementById('translate-text');

    if (!btn) return;

    // Collect the main text content from the page to translate
    function getContentToTranslate() {
        const extracted = document.querySelector('.extracted-text');
        if (extracted) return extracted.innerText;

        const desc = document.querySelector('.description-text');
        if (desc) return desc.innerText;

        const tags = document.querySelectorAll('.tag');
        if (tags.length > 0) return Array.from(tags).map(t => t.innerText).join(', ');

        const summary = document.querySelector('.summary-text');
        if (summary) return summary.innerText;

        return '';
    }

    btn.addEventListener('click', async function () {
        const text     = getContentToTranslate();
        const language = select.value;

        if (!text) {
            alert('No translatable content found.');
            return;
        }

        btn.disabled    = true;
        btn.textContent = 'Translating...';

        try {
            const res  = await fetch('/ajax/translate.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ text, language }),
            });
            const data = await res.json();

            if (data.success) {
                target.textContent = data.translated;
                output.classList.remove('hidden');
                output.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                alert('Translation error: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Translate content';
        }
    });
}());
