'use strict';

// The preview is pre-truncated by the server. fullMarkdown is fetched lazily
// from download_url when the user clicks Copy.
var lastResult     = null;        // holds the latest API response
var fullMarkdown   = '';          // populated on demand by fetchFullMarkdown()
var fetchInFlight  = null;        // pending Promise<string> when a fetch is mid-flight

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

// ── i18n helpers ──────────────────────────────────────────────────────────────
function tr(key, fallback) {
    var dict = (window.D2MD && window.D2MD.i18n) || {};
    return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : fallback;
}
function trf(key, fallback) {
    // sprintf-light: replaces successive %s in the looked-up string
    var template = tr(key, fallback);
    var args = Array.prototype.slice.call(arguments, 2);
    var i = 0;
    return template.replace(/%s/g, function () { return args[i++]; });
}

// What this visitor may upload, rendered into the page by their tier.
function maxUploadBytes() {
    var n = window.D2MD && window.D2MD.maxBytes;
    return (typeof n === 'number' && n > 0) ? n : 0;
}

function maxSizeLabel() {
    return (window.D2MD && window.D2MD.maxSizeLabel) || '';
}

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
        var dzSize = document.getElementById('dz-size');
        if (dzSize) dzSize.textContent = formatBytes(file.size);
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

    var MAX_RETRIES = 3;
    var retryCount  = 0;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!fileInput.files.length) return;

        var file  = fileInput.files[0];
        var limit = maxUploadBytes();

        // Refused here rather than after the bytes are on the wire: the server
        // would reject it anyway, and against a one-per-hour tier that upload
        // costs the visitor the whole window for nothing.
        if (limit > 0 && file.size > limit) {
            showErrors([
                trf('app.error.toobig', 'That file is larger than %s.', maxSizeLabel()),
                (window.D2MD && window.D2MD.sizeHint) || ''
            ].filter(Boolean));
            if (fileInput) { fileInput.value = ''; }
            return;
        }

        retryCount = 0;
        sendUpload(file);
    });

    function sendUpload(file) {
        var fakeTimer = null;
        var fakePct   = 65;

        var uploadingMsg = tr('app.loading.uploading', 'Uploading…');

        overlayShow(file.name, file.size);
        overlaySet(uploadingMsg, 0);

        var fd = new FormData();
        fd.append('file', file, file.name);
        fd.append('format', formatInput ? formatInput.value : 'pdf');

        var xhr = new XMLHttpRequest();

        // Real upload progress → 0 to 60 %
        xhr.upload.addEventListener('progress', function (ev) {
            if (!ev.lengthComputable) return;
            overlaySet(uploadingMsg, Math.round((ev.loaded / ev.total) * 60));
        });

        // Upload finished — server is converting → fake crawl 65 → 93 %
        var convertMsgs = [
            tr('app.loading.extracting',  'Extracting text…'),
            tr('app.loading.structuring', 'Structuring document…'),
            tr('app.loading.building',    'Building Markdown…'),
            tr('app.loading.finalising',  'Finalising output…'),
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
            // 503 only. A 429 means the caller's own quota is spent, and against
            // one conversion an hour, retrying is three guaranteed failures and
            // three wasted uploads of the whole file.
            if (xhr.status === 503 && retryCount < MAX_RETRIES) {
                clearInterval(fakeTimer);

                var retryAfter = parseInt(xhr.getResponseHeader('Retry-After'), 10);
                if (!isFinite(retryAfter) || retryAfter < 1) { retryAfter = 5; }
                if (retryAfter > 120) { retryAfter = 120; }

                retryCount++;

                var remaining = retryAfter;
                var tick = function () {
                    overlaySet(trf('app.error.busy', 'The server is busy. Retrying in %s s...', remaining), null);
                };
                tick();

                var ticker = setInterval(function () {
                    remaining--;
                    if (remaining > 0) { tick(); return; }

                    clearInterval(ticker);
                    overlaySet(trf('app.status.retrying', 'Retrying (%s of %s)...', retryCount, MAX_RETRIES), null);
                    sendUpload(file);
                }, 1000);

                return;
            }

            clearInterval(fakeTimer);
            overlaySet(tr('app.loading.done', 'Done'), 100);

            var data;
            try { data = JSON.parse(xhr.responseText); }
            catch (_) { data = null; }

            // Apache rejects an oversized body itself, before PHP runs, so this
            // one arrives as HTML and would otherwise fall through to the
            // generic "conversion failed".
            if (!data && xhr.status === 413) {
                clearInterval(fakeTimer);
                overlayHide();
                showErrors([
                    trf('app.error.toobig', 'That file is larger than %s.', maxSizeLabel()),
                    (window.D2MD && window.D2MD.sizeHint) || ''
                ].filter(Boolean));
                return;
            }

            // Brief pause so the 100% bar is visible
            setTimeout(function () {
                overlayHide();
                if (data && data.success) {
                    showResult(data);
                } else {
                    var errs = (data && (data.errors || data.message))
                        ? (data.errors || [data.message])
                        : [tr('app.error.failed', 'Conversion failed. Please try again.')];
                    showErrors(errs);
                }
            }, 400);
        });

        xhr.addEventListener('error', function () {
            clearInterval(fakeTimer);
            overlayHide();
            showErrors([tr('app.error.network', 'Network error. Please check your connection and try again.')]);
        });

        xhr.addEventListener('timeout', function () {
            clearInterval(fakeTimer);
            overlayHide();
            showErrors([tr('app.error.timeout', 'The request timed out. The file may be too large or the server is busy.')]);
        });

        xhr.timeout = D2MD.timeout;
        xhr.open('POST', '/convert');
        xhr.send(fd);
    }

    // ── Result rendering ──────────────────────────────────────────────────────

    function showResult(data) {
        var hashEl     = document.getElementById('res-hash');
        var charsEl    = document.getElementById('res-chars');
        var outputEl   = document.getElementById('md-output');
        var downloadEl = document.getElementById('res-download');

        // Reset cached state for this conversion
        lastResult    = data;
        fullMarkdown  = '';
        fetchInFlight = null;

        var preview   = data.preview || '';
        var truncated = !!data.preview_truncated;
        var totalChars = data.characters || preview.length;

        if (hashEl)     hashEl.textContent  = data.hash || '';
        if (charsEl)    charsEl.textContent = trf('app.result.preview.chars', '%s chars', totalChars.toLocaleString());
        if (outputEl)   outputEl.textContent = preview;
        if (downloadEl) downloadEl.href     = data.download_url || '#';

        var truncEl  = document.getElementById('preview-truncated');
        var truncMsg = document.getElementById('preview-truncated-msg');
        if (truncEl) {
            truncEl.hidden = !truncated;
            if (truncated && truncMsg) {
                truncMsg.textContent = trf(
                    'app.error.preview_truncated',
                    'Preview limited to the first %s of %s characters. Use Download to get the full file.',
                    preview.length.toLocaleString(),
                    totalChars.toLocaleString()
                );
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

            lastResult    = null;
            fullMarkdown  = '';
            fetchInFlight = null;
            form.reset();
            try { fileInput.value = ''; } catch (_) {}
            if (dzIdle)     dzIdle.hidden     = false;
            if (dzSelected) dzSelected.hidden = true;
            if (convertBtn) convertBtn.disabled = true;
            var dzSize = document.getElementById('dz-size');
            if (dzSize) dzSize.textContent = '';
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
        tr('app.tip.0', 'Markdown uses far fewer tokens than HTML, ideal for large AI context windows.'),
        tr('app.tip.1', 'Claude, ChatGPT and Gemini all read Markdown natively, no extra prompting needed.'),
        tr('app.tip.2', 'A 50 MB PDF can shrink to a tiny plain-text file that every editor can open.'),
        tr('app.tip.3', 'Markdown files diff cleanly in Git, great for tracking document changes over time.'),
        tr('app.tip.4', 'Structured headings help AI models locate and cite specific sections accurately.'),
    ];

    function startTips() {
        stopTips();
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

    function overlayShow(filename, sizeBytes) {
        var overlay = document.getElementById('loading-overlay');
        var fnEl    = document.getElementById('loading-filename');
        var szEl    = document.getElementById('loading-size');
        if (fnEl)    fnEl.textContent  = filename;
        if (szEl)    szEl.textContent  = sizeBytes ? formatBytes(sizeBytes) : '';
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
        if (barFill && pct !== null) barFill.style.width = Math.round(pct) + '%';
    }

})();

// ── Copy markdown ─────────────────────────────────────────────────────────────
// Fetches the full file from download_url on demand; the API response only
// includes a truncated preview.

function fetchFullMarkdown() {
    if (fullMarkdown) {
        return Promise.resolve(fullMarkdown);
    }
    if (fetchInFlight) {
        return fetchInFlight;
    }
    var url = lastResult && lastResult.download_url;
    if (!url) {
        // Fallback: copy whatever is in the preview pane
        var el = document.getElementById('md-output');
        return Promise.resolve(el ? el.textContent : '');
    }
    fetchInFlight = fetch(url, { credentials: 'same-origin' })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(function (text) {
            fullMarkdown  = text;
            fetchInFlight = null;
            return text;
        })
        .catch(function (err) {
            fetchInFlight = null;
            throw err;
        });
    return fetchInFlight;
}

function copyMarkdown() {
    var btn  = document.getElementById('copy-btn');
    if (!btn) return;

    var orig = btn.textContent;
    btn.textContent = tr('app.result.copying', 'Copying…');
    btn.disabled = true;

    fetchFullMarkdown().then(function (text) {
        btn.disabled = false;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
                .then(function () { flashCopied(btn, orig); })
                .catch(function () { legacyCopy(text, btn, orig); });
        } else {
            legacyCopy(text, btn, orig);
        }
    }).catch(function () {
        btn.disabled = false;
        btn.textContent = orig;
        showErrors([tr('app.error.network', 'Network error. Please check your connection and try again.')]);
    });
}

function legacyCopy(text, btn, origLabel) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); flashCopied(btn, origLabel); } catch (_) {}
    document.body.removeChild(ta);
}

function flashCopied(btn, origLabel) {
    var orig = origLabel || btn.textContent;
    btn.textContent = tr('app.result.copied', 'Copied!');
    btn.style.color = '#ea580c';
    setTimeout(function () { btn.textContent = orig; btn.style.color = ''; }, 2000);
}

document.addEventListener('DOMContentLoaded', function () {
    var copyBtn = document.getElementById('copy-btn');
    if (copyBtn) copyBtn.addEventListener('click', copyMarkdown);
});
