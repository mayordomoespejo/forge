/* ============================================================
   Forge - frontend behaviour
   ============================================================ */

(function () {
  'use strict';

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

  /* Analysis progress overlay */
  var analyzeFormProg = $('#analyze-form');
  if (analyzeFormProg) {
    analyzeFormProg.addEventListener('submit', function() {
      var overlay = document.createElement('div');
      overlay.id = 'progress-overlay';
      overlay.className = 'progress-overlay';
      overlay.innerHTML = '<div class="progress-box">' +
        '<div class="progress-title">Analyzing content</div>' +
        '<ul class="progress-steps" id="progress-steps"></ul>' +
        '</div>';
      document.body.appendChild(overlay);

      var steps = [
        'Running safety check',
        'Analyzing language and entities',
        'Detecting PII',
        'Generating summary',
        'Finalizing'
      ];
      var delays = [0, 1200, 2800, 4500, 7000];
      var ul = document.getElementById('progress-steps');

      steps.forEach(function(step, i) {
        var li = document.createElement('li');
        li.className = 'progress-step progress-step--pending';
        li.textContent = step;
        ul.appendChild(li);

        setTimeout(function() {
          var items = ul.querySelectorAll('.progress-step');
          if (i > 0 && items[i - 1]) {
            items[i - 1].className = 'progress-step progress-step--done';
          }
          items[i].className = 'progress-step progress-step--active';
        }, delays[i]);
      });
    });
  }

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
    var assistantBubble = null;

    fetch('/ajax/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: message, history: history.slice(0, -1) }),
    })
      .then(function(res) {
        typingBubble.remove();
        assistantBubble = appendBubble('assistant', '');
        var content = assistantBubble.querySelector('.bubble-content');
        var accumulated = '';

        var reader = res.body.getReader();
        var decoder = new TextDecoder();

        function read() {
          return reader.read().then(function(result) {
            if (result.done) {
              history.push({ role: 'assistant', content: accumulated });
              if (chatSendBtn) chatSendBtn.disabled = false;
              if (chatInput) chatInput.focus();
              return;
            }
            var chunk = decoder.decode(result.value, { stream: true });
            var lines = chunk.split('\n');
            lines.forEach(function(line) {
              if (!line.startsWith('data: ')) return;
              var data = line.slice(6).trim();
              if (data === '[DONE]') return;
              try {
                var parsed = JSON.parse(data);
                var delta = parsed.choices && parsed.choices[0] && parsed.choices[0].delta && parsed.choices[0].delta.content;
                if (delta) {
                  accumulated += delta;
                  content.textContent = accumulated;
                  scrollToBottom();
                }
              } catch(e) {}
            });
            return read();
          });
        }
        return read();
      })
      .catch(function(err) {
        if (typingBubble.parentNode) typingBubble.remove();
        appendBubble('assistant', 'Network error. Please try again.');
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

/* ── Text-to-speech ──────────────────────────────────────────────── */
(function () {
  var ttsBtn = document.getElementById('tts-btn');
  if (!ttsBtn) return;

  var audio = null;

  ttsBtn.addEventListener('click', function () {
    var summary = document.querySelector('.summary-text');
    if (!summary) { alert('No summary to read.'); return; }
    var text = summary.innerText.trim();
    if (!text) { alert('No summary to read.'); return; }

    if (audio && !audio.paused) {
      audio.pause();
      ttsBtn.textContent = 'Listen';
      return;
    }

    ttsBtn.disabled = true;
    ttsBtn.textContent = 'Loading...';

    fetch('/ajax/tts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: text }),
    })
      .then(function(res) {
        if (!res.ok) throw new Error('TTS failed');
        return res.blob();
      })
      .then(function(blob) {
        var url = URL.createObjectURL(blob);
        audio = new Audio(url);
        audio.play();
        ttsBtn.textContent = 'Stop';
        audio.onended = function() {
          ttsBtn.textContent = 'Listen';
          URL.revokeObjectURL(url);
        };
      })
      .catch(function(err) {
        alert('TTS error: ' + err.message);
      })
      .finally(function() {
        ttsBtn.disabled = false;
      });
  });
}());
