<?php

declare(strict_types=1);

return [
    // ── Common ─────────────────────────────────────────────────
    'lang.code'         => 'en',
    'lang.label.en'     => 'EN',
    'lang.label.pt'     => 'PT',
    'lang.aria'         => 'Language',

    'nav.about'         => 'About',
    'nav.api'           => 'API',
    'nav.app'           => 'Open app',
    'nav.signin'        => 'Sign in',
    'nav.why'           => 'Why Markdown',
    'nav.performance'   => 'Performance',

    // ── Footer (landing) ───────────────────────────────────────
    'footer.text'       => '<strong>data2md</strong> — files are stored under anonymous identifiers.',
    'footer.app'        => 'App',
    'footer.api'        => 'API',
    'footer.about_md'   => 'About .md',
    'footer.home'       => 'Home',
    'footer.converter'  => 'Converter',
    'footer.api.text'   => '<strong>data2md</strong> API — anonymous by design.',

    // ── Landing — meta ─────────────────────────────────────────
    'landing.title'     => 'data2md — Documents, distilled into Markdown',
    'landing.meta.desc' => 'Drop a PDF. Get clean, AI-ready Markdown. Built for large files, structure-preserving, and free to use.',

    // ── Landing — hero ─────────────────────────────────────────
    'landing.hero.eyebrow' => 'Free · No account · Up to %s',
    'landing.hero.title'   => 'Documents, <em>distilled</em> into Markdown.',
    'landing.hero.sub'     => 'Drop a PDF. Get clean, structured Markdown — ready to paste into ChatGPT, Claude, or any model. Large files welcome.',
    'landing.hero.cta'     => 'Convert a file',
    'landing.hero.cta2'    => 'Sign in',
    'landing.hero.meta1'   => 'Anonymous by design',
    'landing.hero.meta2'   => 'Streamed via pdftotext',
    'landing.hero.meta3'   => 'REST API included',

    // ── Landing — why markdown ─────────────────────────────────
    'landing.why.eyebrow' => '// why markdown',
    'landing.why.title'   => 'Plain text the way <em>machines</em> were meant to read it.',
    'landing.why.lead'    => 'Markdown is the lingua franca of modern AI tools, version control and documentation. Convert once — use everywhere.',

    'landing.why.card1.title' => 'Built for AI models.',
    'landing.why.card1.desc'  => 'LLMs understand Markdown natively. Structured text uses fewer tokens, fits larger context windows, and produces measurably more accurate answers than raw binary.',
    'landing.why.card1.tag'   => '~30% fewer tokens vs raw PDF text',

    'landing.why.card2.title' => 'Structure preserved.',
    'landing.why.card2.desc'  => 'Pages, headings and paragraphs survive the conversion. Your document keeps its logical flow — readable by humans and machines alike, without binary noise or encoding artefacts.',
    'landing.why.card2.tag'   => 'page-aware extraction',

    'landing.why.card3.title' => 'Universal format.',
    'landing.why.card3.desc'  => 'Markdown opens in every text editor. It renders natively in GitHub, Notion, Obsidian and thousands of tools. Store it in Git and get meaningful line-by-line diffs forever.',
    'landing.why.card3.tag'   => '.md · everywhere',

    'landing.why.card4.title' => 'Diff-friendly forever.',
    'landing.why.card4.desc'  => 'Track every change at the line level. Review document edits in a pull request like you would review code. No more comparing PDF revisions side by side.',
    'landing.why.card4.tag'   => 'git-native workflows',

    // ── Landing — performance ──────────────────────────────────
    'landing.perf.eyebrow' => '// big files welcome',
    'landing.perf.title'   => 'A <em>500&nbsp;MB</em> PDF doesn\'t scare us.',
    'landing.perf.lead'    => 'Streaming conversion via <code>pdftotext</code> means constant memory regardless of file size. The browser never freezes — upload progress and server-side extraction are tracked in real time, with friendly tips while you wait.',
    'landing.perf.stat1.lbl' => 'max upload',
    'landing.perf.stat2.lbl' => 'memory footprint',
    'landing.perf.stat3.lbl' => 'UI freezes',
    'landing.perf.bench.title' => 'Conversion benchmarks',
    'landing.perf.bench.sub'   => 'pdftotext · streamed',

    // ── Landing — sign in ──────────────────────────────────────
    'landing.signin.eyebrow' => '// coming soon',
    'landing.signin.title'   => 'Save your <em>conversions</em>.',
    'landing.signin.lead'    => 'Sign in to keep a private library of your converted documents, share download links and access them from anywhere.',
    'landing.signin.card.title' => 'Create your library',
    'landing.signin.card.lead'  => 'One click with Google. We\'ll never share your data — files remain anonymous.',
    'landing.signin.btn'     => 'Continue with Google',
    'landing.signin.tooltip' => 'Google sign-in is coming soon',
    'landing.signin.soon'    => 'Authentication arriving soon',
    'landing.signin.or'      => 'or',
    'landing.signin.alt'     => 'Continue without an account',

    // ── Converter (/app) ───────────────────────────────────────
    'app.title'           => 'data2md — Convert',
    'app.eyebrow'         => '// new conversion',
    'app.heading'         => 'Drop a file. Get <em>Markdown</em>.',

    'app.dz.idle.title'   => 'Drop your file here',
    'app.dz.idle.sub'     => 'or %s',
    'app.dz.idle.browse'  => 'browse to upload',
    'app.dz.idle.hint'    => 'PDF · MAX %s',
    'app.dz.sel.sub'      => 'Ready · %s',
    'app.dz.sel.change'   => 'change file',

    'app.format.label'    => 'Format',
    'app.cta'             => 'Convert to Markdown',

    'app.helper.anon'     => 'Anonymous by design',
    'app.helper.streamed' => 'Streamed via pdftotext',
    'app.helper.maxsize'  => 'Up to %s',

    'app.result.badge'    => 'Converted',
    'app.result.idlbl'    => 'ID',
    'app.result.copy'     => 'Copy Markdown',
    'app.result.copied'   => 'Copied!',
    'app.result.download' => 'Download .md',
    'app.result.preview.label' => 'Markdown preview',
    'app.result.preview.chars' => '%s chars',
    'app.result.another'  => 'Convert another file',

    // ── Loading overlay (server-side fallback) ────────────────
    'app.loading.processing' => 'Processing',

    // ── JS bundle (window.D2MD.i18n) ──────────────────────────
    'app.loading.uploading'   => 'Uploading…',
    'app.loading.extracting'  => 'Extracting text…',
    'app.loading.structuring' => 'Structuring document…',
    'app.loading.building'    => 'Building Markdown…',
    'app.loading.finalising'  => 'Finalising output…',
    'app.loading.done'        => 'Done',

    'app.error.network'  => 'Network error. Please check your connection and try again.',
    'app.error.timeout'  => 'The request timed out. The file may be too large or the server is busy.',
    'app.error.failed'   => 'Conversion failed. Please try again.',
    'app.error.preview_truncated' => 'Preview limited to the first %s of %s characters — use Download to get the full file.',

    'app.tip.0' => 'Markdown uses far fewer tokens than HTML — ideal for large AI context windows.',
    'app.tip.1' => 'Claude, ChatGPT and Gemini all read Markdown natively, no extra prompting needed.',
    'app.tip.2' => 'A 50 MB PDF can shrink to a tiny plain-text file that every editor can open.',
    'app.tip.3' => 'Markdown files diff cleanly in Git — great for tracking document changes over time.',
    'app.tip.4' => 'Structured headings help AI models locate and cite specific sections accurately.',

    // ── API docs (/api) ────────────────────────────────────────
    'api.title'      => 'data2md — API Reference',
    'api.eyebrow'    => '// rest api',
    'api.heading'    => 'API <em>Reference</em>',
    'api.lead'       => 'Integrate data2md in your workflow with a single HTTP POST. Streamed conversion via <code>pdftotext</code>, max %s per request.',

    'api.section.endpoint' => 'Endpoint',
    'api.endpoint.ct'      => 'Content-Type: <code>multipart/form-data</code>',

    'api.section.fields'   => 'Request fields',
    'api.field.col.field'  => 'Field',
    'api.field.col.type'   => 'Type',
    'api.field.col.required' => 'Required',
    'api.field.col.desc'   => 'Description',
    'api.field.required.yes' => 'Yes',
    'api.field.required.no'  => 'No',
    'api.field.file.desc'  => 'The file to convert. Max %s.',
    'api.field.format.desc' => 'File format. Default: <code>pdf</code>. Currently supported: <code>pdf</code>.',

    'api.section.success'   => 'Success response',
    'api.tag.success'       => '201 Created',
    'api.section.error'     => 'Error response',
    'api.tag.error'         => '4xx · 5xx',

    'api.section.rate'      => 'Rate limits',
    'api.rate.body'         => '%d requests per minute per IP. Exceeding returns <code>429 Too Many Requests</code> with a <code>Retry-After: %d</code> header.',

    'api.section.examples'  => 'Examples',
    'api.copy'              => 'Copy',
    'api.copied'            => 'Copied',
];
