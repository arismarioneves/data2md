<?php

declare(strict_types=1);

/**
 * Page footer plus the sticky-header scroll shadow.
 *
 * Define before requiring:
 *   $footerText  — already-translated line, emitted as HTML: the caller escapes
 *                  whatever it interpolates
 *   $footerLinks — list of ['href' => string, 'label' => string, 'external' => bool]
 *
 * @var string $footerText
 * @var list<array{href:string,label:string,external?:bool}> $footerLinks
 */

require_once __DIR__ . '/helpers.php';
?>
<footer class="l-footer">
    <div class="l-container">
        <div class="l-footer-inner">
            <p class="l-footer-text"><?= $footerText ?></p>
            <nav class="l-footer-links">
                <?php foreach ($footerLinks as $link): ?>
                <a href="<?= e($link['href']) ?>"<?= ($link['external'] ?? false) ? ' target="_blank" rel="noreferrer"' : '' ?>><?= e($link['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</footer>

<script>
(function(){
    var header = document.getElementById('l-header');
    if (!header) return;
    var onScroll = function(){
        header.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    document.addEventListener('scroll', onScroll, { passive:true });
    onScroll();
})();
</script>
