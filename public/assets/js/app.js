'use strict';

var fullMarkdown  = '';
var PREVIEW_LIMIT = 8000;

(function () {
    var form        = document.getElementById('upload-form');
    var fileInput   = document.getElementById('file-input');
    var formatInput = document.getElementById('format-input');
    var convertBtn  = document.getElementById('convert-btn');
    var dropZone    = document.getElementById('drop-zone');
    var dzIdle      = document.getElementById('dz-idle');
    var dzSelected  = document.getElementById('dz-selected');
    var dzFilename  = document.getElementById('dz-filename');
    var dzChange    = document.getElementById('dz-change');

    if (!form || !fileInput || !dropZone) return;

    // ── File selection ────────────────────────────────────────────────────────

    dropZone.addEventListener('click', function (e) {
        if (e.target === fileInput) return;
        if (e.target.closest('label[for="file-input"]')) return;
        if (e.target === dzChange) { fileInput.click(); return; }
        fileInput.click();
    });

    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    fileInput.addEventListener('change', function () {
        if (fileInput.files.length) applyFile(fileInput.files[0]);
    });

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    ['dragleave', 'dragend'].forEach(function (evt) {
        dropZone.addEventListener(evt, function () { dropZone.classList.remove('drag-over'); });
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        if (!e.dataTransfer.files.length) return;
        try {
            var dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            fileInput.files = dt.files;
        } catch (_) {}
        applyFile(e.dataTransfer.files[0]);
    });

    function applyFile(file) {
        if (dzFilename) dzFilename.textContent = file.name;
        if (dzIdle)     dzIdle.hidden     = true;
        if (dzSelected) dzSelected.hidden = false;
        if (convertBtn) convertBtn.disabled = false;

        var ext = file.name.split('.').pop().toLowerCase();
        if (formatInput) formatInput.value = ext;
        document.querySelectorAll('.pill').forEach(function (btn) {
            btn.classList.toggle('pill--active', btn.dataset.format === ext);
        });
    }

    document.querySelectorAll('.pill').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.pill').forEach(function (b) { b.classList.remove('pill--active'); });
            btn.classList.add('pill--active');
            if (formatInput) formatInput.value = btn.dataset.format;
        });
    });

    // ── AJAX upload with real progress ────────────────────────────────────────

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!fileInput.files.length) return;

        var file      = fileInput.files[0];
        var fakeTimer = null;
        var fakePct   = 65;

        overlayShow(file.name);
        overlaySet('Uploading…', 0);

        var fd = new FormData();
        fd.append('file', file, file.name);
        fd.append('format', formatInput ? formatInput.value : 'pdf');

        var xhr = new XMLHttpRequest();

        // Real upload progress → 0 to 60 %
        xhr.upload.addEventListener('progress', function (ev) {
            if (!ev.lengthComputable) return;
            overlaySet('Uploading…', Math.round((ev.loaded / ev.total) * 60));
        });

        // Upload finished — server is converting → fake crawl 65 → 93 %
        var convertMsgs = [
            'Extracting text…',
            'Structuring document…',
            'Building Markdown…',
            'Finalising output…',
        ];
        xhr.upload.addEventListener('load', function () {
            overlaySet(convertMsgs[0], 65);
            fakeTimer = setInterval(function () {
                fakePct = Math.min(fakePct + 0.4, 93);
                var msgIdx = Math.min(
                    Math.floor(((fakePct - 65) / 28) * convertMsgs.length),
                    convertMsgs.length - 1
                );
                overlaySet(convertMsgs[msgIdx], fakePct);
            }, 700);
        });

        // Response received
        xhr.addEventListener('load', function () {
            clearInterval(fakeTimer);
            overlaySet('Done', 100);

            var data;
            try { data = JSON.parse(xhr.responseText); }
            catch (_) { data = null; }

            // Brief pause so the 100% bar is visible
            setTimeout(function () {
                overlayHide();
                if (data && data.success) {
                    showResult(data);
                } else {
                    var errs = (data && (data.errors || data.message))
                        ? (data.errors || [data.message])
                        : ['Conversion failed. Please try again.'];
                    showErrors(errs);
                }
            }, 400);
        });

        xhr.addEventListener('error', function () {
            clearInterval(fakeTimer);
            overlayHide();
            showErrors(['Network error. Please check your connection and try again.']);
        });

        xhr.addEventListener('timeout', function () {
            clearInterval(fakeTimer);
            overlayHide();
            showErrors(['The request timed out. The file may be too large or the server is busy.']);
        });

        xhr.timeout = (window.D2MD && D2MD.timeout) ? D2MD.timeout : 420000;
        xhr.open('POST', '/api');
        xhr.send(fd);
    });

    // ── Result rendering ──────────────────────────────────────────────────────

    function showResult(data) {
        var hashEl     = document.getElementById('res-hash');
        var charsEl    = document.getElementById('res-chars');
        var outputEl   = document.getElementById('md-output');
        var downloadEl = document.getElementById('res-download');

        fullMarkdown = data.markdown || '';

        // Truncate preview at a clean line boundary
        var truncated = fullMarkdown.length > PREVIEW_LIMIT;
        var preview   = fullMarkdown;
        if (truncated) {
            var breakAt = fullMarkdown.lastIndexOf('\n', PREVIEW_LIMIT);
            preview = fullMarkdown.slice(0, breakAt > 0 ? breakAt : PREVIEW_LIMIT);
        }

        if (hashEl)     hashEl.textContent  = data.hash || '';
        if (charsEl)    charsEl.textContent = (data.characters || 0).toLocaleString() + ' chars';
        if (outputEl)   outputEl.textContent = preview;
        if (downloadEl) downloadEl.href     = data.download_url || '#';

        var truncEl  = document.getElementById('preview-truncated');
        var truncMsg = document.getElementById('preview-truncated-msg');
        if (truncEl) {
            truncEl.hidden = !truncated;
            if (truncated && truncMsg) {
                truncMsg.textContent =
                    'Preview limited to the first ' + preview.length.toLocaleString() +
                    ' of ' + fullMarkdown.length.toLocaleString() +
                    ' characters — use Download to get the full file.';
            }
        }

        document.getElementById('error-section').hidden  = true;
        document.getElementById('upload-section').hidden = true;

        var resultSection = document.getElementById('result-section');
        resultSection.hidden = false;
        resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── "Convert another" reset ───────────────────────────────────────────────

    var btnAnother = document.getElementById('btn-convert-another');
    if (btnAnother) {
        btnAnother.addEventListener('click', function () {
            document.getElementById('result-section').hidden = true;
            document.getElementById('error-section').hidden  = true;
            document.getElementById('upload-section').hidden = false;

            fullMarkdown = '';
            form.reset();
            try { fileInput.value = ''; } catch (_) {}
            if (dzIdle)     dzIdle.hidden     = false;
            if (dzSelected) dzSelected.hidden = true;
            if (convertBtn) convertBtn.disabled = true;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ── Error display ─────────────────────────────────────────────────────────

    function showErrors(errors) {
        var section = document.getElementById('error-section');
        var list    = document.getElementById('error-list');
        if (!section || !list) return;
        list.innerHTML = '';
        (errors || []).forEach(function (msg) {
            var li = document.createElement('li');
            li.textContent = msg;
            list.appendChild(li);
        });
        section.hidden = false;
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── Overlay helpers ───────────────────────────────────────────────────────

    var tipTimer = null;

    var tips = [
        'Markdown uses far fewer tokens than HTML — ideal for large AI context windows.',
        'Claude, ChatGPT and Gemini all read Markdown natively, no extra prompting needed.',
        'A 50 MB PDF can shrink to a tiny plain-text file that every editor can open.',
        'Markdown files diff cleanly in Git — great for tracking document changes over time.',
        'Structured headings help AI models locate and cite specific sections accurately.',
    ];

    function startTips() {
        var tipEl = document.getElementById('loading-tip');
        if (!tipEl) return;
        var idx = Math.floor(Math.random() * tips.length);
        tipEl.style.opacity = '0';
        setTimeout(function () {
            tipEl.textContent = tips[idx];
            tipEl.style.opacity = '1';
        }, 50);
        tipTimer = setInterval(function () {
            tipEl.style.opacity = '0';
            setTimeout(function () {
                idx = (idx + 1) % tips.length;
                tipEl.textContent = tips[idx];
                tipEl.style.opacity = '1';
            }, 350);
        }, 4500);
    }

    function stopTips() {
        if (tipTimer) { clearInterval(tipTimer); tipTimer = null; }
    }

    function overlayShow(filename) {
        var overlay = document.getElementById('loading-overlay');
        var fnEl    = document.getElementById('loading-filename');
        if (fnEl)    fnEl.textContent  = filename;
        if (overlay) overlay.hidden    = false;
        document.body.style.overflow   = 'hidden';
        startTips();
    }

    function overlayHide() {
        var overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.hidden    = true;
        document.body.style.overflow   = '';
        stopTips();
    }

    function overlaySet(statusText, pct) {
        var statusEl = document.getElementById('loading-status');
        var barFill  = document.getElementById('loading-bar-fill');
        if (statusEl) statusEl.textContent = statusText;
        if (barFill)  barFill.style.width  = Math.round(pct) + '%';
    }

})();

// ── Copy markdown ─────────────────────────────────────────────────────────────
// Uses the full (untruncated) markdown stored in fullMarkdown.

function copyMarkdown() {
    var btn  = document.getElementById('copy-btn');
    if (!btn) return;

    var text = fullMarkdown || (function () {
        var el = document.getElementById('md-output');
        return el ? el.textContent : '';
    }());

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text)
            .then(function () { flashCopied(btn); })
            .catch(function () { legacyCopy(text, btn); });
    } else {
        legacyCopy(text, btn);
    }
}

function legacyCopy(text, btn) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); flashCopied(btn); } catch (_) {}
    document.body.removeChild(ta);
}

function flashCopied(btn) {
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    btn.style.color = '#86efac';
    setTimeout(function () { btn.textContent = orig; btn.style.color = ''; }, 2000);
}

document.addEventListener('DOMContentLoaded', function () {
    var copyBtn = document.getElementById('copy-btn');
    if (copyBtn) copyBtn.addEventListener('click', copyMarkdown);
});
