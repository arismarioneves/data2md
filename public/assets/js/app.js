'use strict';

// ── Upload form ───────────────────────────────────────────────────────────────
(function () {
    const dropZone   = document.getElementById('drop-zone');
    const fileInput  = document.getElementById('file-input');
    const formatInput = document.getElementById('format-input');
    const convertBtn = document.getElementById('convert-btn');
    const dzIdle     = document.getElementById('dz-idle');
    const dzSelected = document.getElementById('dz-selected');
    const dzFilename = document.getElementById('dz-filename');
    const dzChange   = document.getElementById('dz-change');

    if (!dropZone || !fileInput) return;

    // Click on drop zone → open file picker (unless clicking the label/input directly)
    dropZone.addEventListener('click', function (e) {
        if (e.target === fileInput) return;
        if (e.target.closest('label[for="file-input"]')) return;
        if (e.target === dzChange) { fileInput.click(); return; }
        fileInput.click();
    });

    // Keyboard support
    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });

    // File input change
    fileInput.addEventListener('change', function () {
        if (fileInput.files.length > 0) applyFile(fileInput.files[0]);
    });

    // Drag & drop
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    ['dragleave', 'dragend'].forEach(function (evt) {
        dropZone.addEventListener(evt, function () {
            dropZone.classList.remove('drag-over');
        });
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (!files.length) return;

        // Inject dragged file into the input
        try {
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
        } catch (_) {
            // DataTransfer not supported — fall back: just show the name
        }
        applyFile(files[0]);
    });

    function applyFile(file) {
        if (dzFilename) dzFilename.textContent = file.name;
        if (dzIdle)     dzIdle.hidden     = true;
        if (dzSelected) dzSelected.hidden = false;
        if (convertBtn) convertBtn.disabled = false;

        // Auto-select matching format pill
        const ext = file.name.split('.').pop().toLowerCase();
        if (formatInput) formatInput.value = ext;
        document.querySelectorAll('.pill').forEach(function (btn) {
            btn.classList.toggle('pill--active', btn.dataset.format === ext);
        });
    }

    // Format pill selection
    document.querySelectorAll('.pill').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.pill').forEach(function (b) {
                b.classList.remove('pill--active');
            });
            btn.classList.add('pill--active');
            if (formatInput) formatInput.value = btn.dataset.format;
        });
    });
})();

// ── Copy markdown ──────────────────────────────────────────────────────────────
function copyMarkdown() {
    var output = document.getElementById('md-output');
    var btn    = document.getElementById('copy-btn');
    if (!output || !btn) return;

    var text = output.textContent || output.innerText;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () {
            flashCopied(btn);
        }).catch(function () {
            legacyCopy(text, btn);
        });
    } else {
        legacyCopy(text, btn);
    }
}

function legacyCopy(text, btn) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); flashCopied(btn); } catch (_) { /* silent */ }
    document.body.removeChild(ta);
}

function flashCopied(btn) {
    var original = btn.textContent;
    btn.textContent = 'Copied!';
    btn.style.color = '#86efac';
    setTimeout(function () {
        btn.textContent = original;
        btn.style.color = '';
    }, 2000);
}

// Attach copy button listener once DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    var copyBtn = document.getElementById('copy-btn');
    if (copyBtn) copyBtn.addEventListener('click', copyMarkdown);
});
