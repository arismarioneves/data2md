<?php

declare(strict_types=1);

namespace Data2Md\Converter;

/**
 * Cleans up the noise that pdftotext / smalot/pdfparser sometimes produces:
 *
 *   - control characters and zero-widths
 *   - "CMap garbage" lines: long runs of mixed-case letters with almost no
 *     vowels (e.g. `eJERFGMEMfMfeJNFEQ`) — usually mis-decoded glyph indices
 *   - punctuation/symbol noise (e.g. `;<='& >??#A`) — failed font tables
 *   - dense numeric/punctuation streams from broken column layouts
 *     (e.g. `2! D!2A2 D      D!2A2 >D2A D`)
 *
 * Heuristics are line-level so legitimate paragraphs survive intact. Three or
 * more consecutive dropped lines collapse into a single "[unreadable section]"
 * marker so the reader (and any downstream LLM) can tell the gap is intentional.
 *
 * clean() holds the whole document in memory and is used by tests and one-off
 * calls. MarkdownStreamWriter applies the same per-line rules for conversion,
 * holding at most one line at a time.
 */
final class MarkdownSanitizer
{
    /** Marker used to replace runs of garbage lines. */
    private const MARKER = '_[unreadable section]_';

    /** Don't even consider lines shorter than this for garbage detection. */
    private const MIN_LEN = 6;

    /** Below this letter-count we can't reliably measure vowel ratio. */
    private const MIN_LETTER_COUNT_FOR_VOWELS = 8;

    /** Vowels in the languages we expect (en + pt). */
    private const VOWEL_RE = '/[aeiouAEIOUáéíóúàâêôûãõÁÉÍÓÚÀÂÊÔÛÃÕ]/u';

    public function clean(string $markdown): string
    {
        $markdown = $this->stripControlChars($markdown);
        $markdown = $this->dropGarbageLines($markdown);
        $markdown = $this->collapseBlankLines($markdown);
        return trim($markdown);
    }

    /**
     * Strip control characters except common whitespace (tab, newline).
     * Also removes zero-width spaces and BOMs.
     */
    public function stripControlChars(string $text): string
    {
        // Normalize line endings first
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Drop C0 controls except \t (\x09) and \n (\x0A); drop DEL (\x7F)
        $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text) ?? $text;
        // Drop zero-width and BOM
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
        return $text;
    }

    private function dropGarbageLines(string $text): string
    {
        $lines  = explode("\n", $text);
        $out    = [];
        $skipRun = 0;

        $emitMarker = function () use (&$out): void {
            // Walk back through trailing blanks to see whether the last
            // non-blank emitted line is already the marker; if so, skip.
            for ($i = count($out) - 1; $i >= 0; $i--) {
                if (trim($out[$i]) === '') {
                    continue;
                }
                if ($out[$i] === self::MARKER) {
                    return;
                }
                break;
            }
            $out[] = '';
            $out[] = self::MARKER;
            $out[] = '';
        };

        foreach ($lines as $line) {
            if ($this->isGarbage($line)) {
                $skipRun++;
                continue;
            }
            if ($skipRun >= 3) {
                $emitMarker();
            }
            $skipRun = 0;
            $out[] = rtrim($line);
        }

        if ($skipRun >= 3) {
            $emitMarker();
        }

        return implode("\n", $out);
    }

    public function isGarbage(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return false; // keep blanks as paragraph separators
        }

        // Always preserve markdown structural tokens (headings, lists, hr, etc.)
        if ($this->looksStructural($trimmed)) {
            return false;
        }

        // For analysis, ignore inner whitespace
        $compact = preg_replace('/\s+/u', '', $trimmed) ?? $trimmed;
        $len = mb_strlen($compact);

        $letters  = (int) preg_match_all('/\p{L}/u', $compact);
        $digits   = (int) preg_match_all('/\p{N}/u', $compact);
        $specials = (int) preg_match_all('/[\p{P}\p{S}]/u', $compact);

        // 0. Short symbol-heavy noise (3–5 chars). Drops PJe seal fragments
        //    like `0&*E'` while preserving real values like `12.3%`, `$12M`,
        //    `Q3`. The rule: specials must outnumber letters + digits combined.
        if ($len >= 3 && $len < self::MIN_LEN && $letters <= 1 && $specials >= ($letters + $digits)) {
            return true;
        }

        if ($len < self::MIN_LEN) {
            return false;
        }

        $specialRatio = $specials / $len;
        $letterRatio  = $letters  / $len;

        // 1. Mostly punctuation / symbols (e.g. `;<='& >??#A`, `,"#$%&'()`,
        //    `YZ[\]^_`^abcd`). Real text rarely exceeds ~25% non-letter chars.
        if ($specialRatio >= 0.45 && $letterRatio < 0.55) {
            return true;
        }

        // 2. Heavy numeric / symbol noise with sparse letters
        //    e.g. `2! D!2A2 D      D!2A2 >D2A D                 >D2A`
        if ($len >= 12 && $letterRatio < 0.30 && ($digits + $specials) >= ($letters * 2)) {
            return true;
        }

        // 3. Vowel-poor letter run — typical CMap mis-decode
        //    e.g. `eJERFGMEMfMfeJNFEQ`, `GSVWJJGTGMUEN OJPNQ`
        if ($letters >= self::MIN_LETTER_COUNT_FOR_VOWELS && $letterRatio >= 0.55) {
            $vowels = (int) preg_match_all(self::VOWEL_RE, $compact);
            $vowelRatio = $vowels / $letters;
            // Real text (en + pt): ~38–48% vowels. Below 22% is suspicious.
            if ($vowelRatio < 0.22) {
                return true;
            }
        }

        // 4. Long unbroken token with vowel-poor letters (garbage "words")
        //    e.g. `eJERFGMEMfMfeJNFEQ` (18 chars, mixed-case, scattered E's)
        $tokens = preg_split('/\s+/u', $trimmed) ?: [];
        foreach ($tokens as $tok) {
            if (mb_strlen($tok) < 16) {
                continue;
            }
            $tletters = (int) preg_match_all('/\p{L}/u', $tok);
            if ($tletters < 12) {
                continue;
            }
            $tvowels = (int) preg_match_all(self::VOWEL_RE, $tok);
            if (($tvowels / $tletters) < 0.25) {
                return true;
            }
        }

        // 5. Long consonant run — real words rarely have 6+ consonants in a row
        //    Catches things like `GSVWJJGTGM` and `RFGMEMfMf`
        if (preg_match('/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]{6,}/u', $compact) === 1) {
            return true;
        }

        // 6. Tiny vocabulary — real text uses a wide alphabet; CMap garbage
        //    typically repeats a small set (e.g. only `D`, `A`, `2`, `!`, `>`).
        if ($len >= 24) {
            $unique = count(array_unique(mb_str_split($compact)));
            if ($unique <= 8) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recognise lines we never want to drop:
     *   - Markdown headings, hr, list bullets, blockquotes, fenced code
     *   - Page markers we emit ourselves (`## Page N`)
     *   - Lines that are *mostly* a single URL (>=70% of trimmed length)
     */
    private function looksStructural(string $line): bool
    {
        if (preg_match('/^(#{1,6}\s|[-*]\s|>\s|```|---\s*$|\*\*\*\s*$)/u', $line) === 1) {
            return true;
        }
        // A line that is dominated by a URL is fine; a line that *contains*
        // a URL but is mostly garbage should still be checked.
        if (preg_match('#https?://\S+#iu', $line, $m) === 1) {
            $urlLen  = mb_strlen($m[0]);
            $lineLen = mb_strlen(trim($line));
            if ($lineLen > 0 && ($urlLen / $lineLen) >= 0.70) {
                return true;
            }
        }
        return false;
    }

    private function collapseBlankLines(string $text): string
    {
        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }
}
