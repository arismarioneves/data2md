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
    'nav.app'           => 'APP',
    'nav.signin'        => 'Sign in',
    'nav.why'           => 'Why Markdown',
    'nav.performance'   => 'Performance',

    // ── Footer (landing) ───────────────────────────────────────
    'footer.text'         => '<strong>%s</strong> — the uploaded file is discarded after conversion; the generated Markdown is kept, with no automatic expiry.',
    'footer.app'        => 'App',
    'footer.api'        => 'API',
    'footer.about_md'   => 'About .md',
    'footer.home'       => 'Home',
    'footer.converter'  => 'Converter',
    'footer.api.text'     => '<strong>%s</strong> API — no account, no API key.',

    // ── Landing — meta ─────────────────────────────────────────
    'landing.title'     => '%s · Documents, distilled into Markdown',
    'landing.meta.desc' => 'Drop a PDF. Get clean, AI-ready Markdown. Built for large files, structure-preserving, and free to use.',
    'og.image.alt'      => 'The data2md logo beside a sample of converted Markdown.',

    // ── Landing — hero ─────────────────────────────────────────
    'landing.hero.eyebrow' => 'Free · No account · Up to %s',
    'landing.hero.title'   => 'Documents, <em>distilled</em> into Markdown.',
    'landing.hero.sub'     => 'Drop a PDF. Get clean, structured Markdown, ready to paste into ChatGPT, Claude, or any model. Large files welcome.',
    'landing.hero.cta'     => 'Convert a file',
    'landing.hero.cta2'    => 'Sign in',
    'landing.hero.meta1'   => 'No account needed',
    'landing.hero.meta2'   => 'Large files, no freezing',
    'landing.hero.meta3'   => 'REST API included',

    // ── Landing — why markdown ─────────────────────────────────
    'landing.why.eyebrow' => '// why markdown',
    'landing.why.title'   => 'Plain text the way <em>machines</em> were meant to read it.',
    'landing.why.lead'    => 'Markdown is the lingua franca of modern AI tools, version control and documentation. Convert once, use everywhere.',

    'landing.why.card1.title' => 'Built for AI models.',
    'landing.why.card1.desc'  => 'LLMs understand Markdown natively. Structured text uses fewer tokens, fits larger context windows, and produces measurably more accurate answers than raw binary.',
    'landing.why.card1.tag'   => '~30% fewer tokens vs raw PDF text',

    'landing.why.card2.title' => 'Structure preserved.',
    'landing.why.card2.desc'  => 'Pages, headings and paragraphs survive the conversion. Your document keeps its logical flow, readable by humans and machines alike, without binary noise or encoding artefacts.',
    'landing.why.card2.tag'   => 'page-aware extraction',

    'landing.why.card3.title' => 'Universal format.',
    'landing.why.card3.desc'  => 'Markdown opens in every text editor. It renders natively in GitHub, Notion, Obsidian and thousands of tools. Store it in Git and get meaningful line-by-line diffs forever.',
    'landing.why.card3.tag'   => '.md · everywhere',

    'landing.why.card4.title' => 'Diff-friendly forever.',
    'landing.why.card4.desc'  => 'Track every change at the line level. Review document edits in a pull request like you would review code. No more comparing PDF revisions side by side.',
    'landing.why.card4.tag'   => 'git-native workflows',

    // ── Landing — performance ──────────────────────────────────
    'landing.perf.eyebrow' => '// big files welcome',
    'landing.perf.title'   => 'A <em>%s</em> PDF doesn\'t scare us.',
    'landing.perf.lead'    => 'Documents of any size are processed as a continuous stream, without exhausting memory or freezing the browser. Upload and extraction progress show in real time, with tips while you wait.',
    'landing.perf.stat1.lbl' => 'max upload',
    'landing.perf.stat2.lbl' => 'memory footprint',
    'landing.perf.stat3.lbl' => 'UI freezes',
    'landing.perf.bench.title' => 'Conversion benchmarks',
    'landing.perf.bench.sub'   => 'measured conversion times',

    // ── Landing — sign in ──────────────────────────────────────
    'landing.signin.eyebrow' => '// coming soon',
    'landing.signin.title'   => 'Save your <em>conversions</em>.',
    'landing.signin.lead'    => 'Sign in to keep a private library of your converted documents, share download links and access them from anywhere.',
    'landing.signin.card.title' => 'Create your library',
    'landing.signin.card.lead'  => 'One click with Google. We\'ll never share your data — you decide when to delete each file.',
    'landing.signin.btn'     => 'Continue with Google',
    'landing.signin.tooltip' => 'Google sign-in is coming soon',
    'landing.signin.soon'    => 'Authentication arriving soon',
    'landing.signin.or'      => 'or',
    'landing.signin.alt'     => 'Continue without an account',

    // ── Converter (/app) ───────────────────────────────────────
    'app.title'           => '%s · Convert',
    'app.meta.desc'       => 'Upload a PDF and get clean, structured Markdown back. No account needed, and the full converted file is always yours to download.',
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

    'app.helper.anon'     => 'No account needed',
    'app.helper.streamed' => 'Large files, no freezing',
    'app.helper.maxsize'  => 'Up to %s',

    'app.result.badge'    => 'Converted',
    'app.result.idlbl'    => 'ID',
    'app.result.copy'     => 'Copy Markdown',
    'app.result.copying'  => 'Copying…',
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
    'app.error.preview_truncated' => 'Preview limited to the first %s of %s characters. Use Download to get the full file.',

    'app.tip.0' => 'Markdown uses far fewer tokens than HTML, ideal for large AI context windows.',
    'app.tip.1' => 'Claude, ChatGPT and Gemini all read Markdown natively, no extra prompting needed.',
    'app.tip.2' => 'A 50 MB PDF can shrink to a tiny plain-text file that every editor can open.',
    'app.tip.3' => 'Markdown files diff cleanly in Git, great for tracking document changes over time.',
    'app.tip.4' => 'Structured headings help AI models locate and cite specific sections accurately.',

    // ── API docs (/api) ────────────────────────────────────────
    'api.title'      => '%s · API Reference',
    'api.meta.desc'  => 'REST endpoint for converting PDFs to Markdown: one POST, a JSON response, and the rate limits documented in full.',
    'api.eyebrow'    => '// rest api',
    'api.heading'    => 'API <em>Reference</em>',
    'api.lead'       => 'Integrate %s into your workflow with a single HTTP POST. Max %s per request.',

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
    'api.success.note'      => '<code>preview</code> contains up to ~8 KB of Markdown trimmed at a line boundary. <code>preview_truncated</code> tells you whether the file is larger; fetch the full document via <code>download_url</code>.',
    'api.section.error'     => 'Error response',
    'api.tag.error'         => '4xx · 5xx',

    'api.section.rate'      => 'Rate limits',
    'api.rate.body'         => '%d requests per minute per IP. Exceeding returns <code>429 Too Many Requests</code> with a <code>Retry-After: %d</code> header.',

    'api.section.examples'  => 'Examples',
    'api.copy'              => 'Copy',
    'api.copied'            => 'Copied',

    // ── Queue / retry ──────────────────────────────────────────
    'app.error.busy'      => 'The server is converting other documents right now. Retrying in %s s…',
    'app.status.queued'   => 'Queued…',
    'app.status.retrying' => 'Retrying (%s of %s)…',

    // ── Legal pages ────────────────────────────────────────────
    'nav.terms'          => 'Terms',
    'nav.privacy'        => 'Privacy',
    'legal.updated'      => 'Last updated 18 August 2026.',

    'legal.terms.title'   => '%s — Terms of use',
    'legal.terms.meta'    => 'Conditions for using the document-to-Markdown conversion service.',
    'legal.terms.heading' => 'Terms of use',

    'legal.terms.s1.h' => 'The service',
    'legal.terms.s1.b' => 'data2md converts uploaded documents into Markdown text. It is free to use and requires no account.',

    'legal.terms.s2.h' => 'Your responsibility',
    'legal.terms.s2.b' => 'You are responsible for what you upload. By using the service you confirm you have the right to process that content. Do not upload unlawful material or content you are not authorised to handle.',

    'legal.terms.s3.h' => 'Usage limits',
    'legal.terms.s3.b' => 'Each file may be at most %s, and there is a limit of %s conversions per minute per source address. These limits keep the service available to everyone and may change without notice.',

    'legal.terms.s4.h' => 'Availability and results',
    'legal.terms.s4.b' => 'The service is provided as is, with no guarantee of continuous availability or of conversion fidelity. Documents with no text layer — scanned images, for instance — cannot be converted. Check the result before relying on it for anything that depends on accuracy.',

    'legal.terms.s5.h' => 'Content removal',
    'legal.terms.s5.b' => 'We may remove any conversion at any time, including for operational reasons, without prior notice.',

    'legal.terms.s6.h' => 'Changes to these terms',
    'legal.terms.s6.b' => 'These terms may change. The version in force is always the one published on this page. Questions: <a href="mailto:%3$s">%3$s</a>.',

    'legal.privacy.title'   => '%s — Privacy',
    'legal.privacy.meta'    => 'What the service keeps, for how long, and how to request deletion.',
    'legal.privacy.heading' => 'Privacy',

    'legal.privacy.s1.h' => 'Who is responsible',
    'legal.privacy.s1.b' => 'data2md is responsible for handling the data described on this page. Contact: <a href="mailto:%3$s">%3$s</a>.',

    'legal.privacy.s2.h' => 'The file you upload',
    'legal.privacy.s2.b' => '<strong>It is deleted from the server right after conversion</strong>, including when the conversion fails. It is used only to extract the text and is not retained.',

    'legal.privacy.s3.h' => 'The Markdown produced',
    'legal.privacy.s3.b' => 'It is kept and reachable through the download link <strong>until it is deleted</strong>. It does not expire on its own.',

    'legal.privacy.s4.h' => 'The file name',
    'legal.privacy.s4.b' => 'It is stored alongside the conversion, because it is what names the file you download. If your file name contains personal data, that data is kept for as long as the conversion exists.',

    'legal.privacy.s5.h' => 'IP address',
    'legal.privacy.s5.b' => 'It is recorded for security and to enforce the usage limit. In the operational record the address is stored in reduced form, without its final part. In the access record the full address is kept for up to 30 days and then deleted.',

    'legal.privacy.s6.h' => 'Who can reach your conversion',
    'legal.privacy.s6.b' => 'Anyone holding the download link. The link contains a random identifier and is not listed anywhere, but <strong>do not share links to sensitive documents</strong>.',

    'legal.privacy.s7.h' => 'Cookies',
    'legal.privacy.s7.b' => 'We use a single cookie, to remember the language you picked. There is no tracking, no behavioural analytics and no advertising.',

    'legal.privacy.s8.h' => 'Requesting deletion',
    'legal.privacy.s8.b' => 'Write to <a href="mailto:%3$s">%3$s</a> with the identifier shown in the download link. The conversion and its record are deleted.',
];
