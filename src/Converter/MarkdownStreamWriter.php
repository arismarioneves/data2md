<?php

declare(strict_types=1);

namespace Data2Md\Converter;

/**
 * Writes markdown line by line, applying MarkdownSanitizer's line-level rules
 * without holding more than one line in memory.
 *
 * Blank line runs collapse to a single blank line, leading and trailing
 * blanks are dropped, and a run of 3+ consecutive dropped lines is replaced
 * by one "[unreadable section]" marker, never repeated back to back.
 */
final class MarkdownStreamWriter
{
    private const MARKER = '_[unreadable section]_';

    private int $skipRun = 0;
    private int $pendingBlanks = 0;
    private bool $wroteAnything = false;
    private bool $lastEmittedWasMarker = false;

    private int $lines = 0;
    private int $characters = 0;

    /** @param resource $out */
    public function __construct(
        private $out,
        private readonly MarkdownSanitizer $sanitizer,
    ) {
    }

    /**
     * Feed one raw line from the extractor. Control characters are stripped,
     * the garbage heuristic is applied, and blanks are collapsed.
     */
    public function writeLine(string $line): void
    {
        $line = $this->sanitizer->stripControlChars($line);

        if ($this->sanitizer->isGarbage($line)) {
            $this->skipRun++;
            return;
        }

        if ($this->skipRun >= 3) {
            $this->emitMarker();
        }
        $this->skipRun = 0;

        $line = rtrim($line);

        if (trim($line) === '') {
            $this->pendingBlanks++;
            return;
        }

        $this->emit($line);
    }

    /** Write a line verbatim, bypassing the garbage heuristic. */
    public function writeRaw(string $line): void
    {
        if ($this->skipRun >= 3) {
            $this->emitMarker();
        }
        $this->skipRun = 0;

        $this->emit(rtrim($this->sanitizer->stripControlChars($line)));
    }

    public function blankLine(): void
    {
        $this->pendingBlanks++;
    }

    public function finish(): SanitizeStats
    {
        if ($this->skipRun >= 3) {
            $this->emitMarker();
        }
        $this->skipRun = 0;

        // Pending blanks at the end are never flushed, so output never ends
        // with a blank line.

        return new SanitizeStats($this->lines, $this->characters);
    }

    private function emitMarker(): void
    {
        if (!$this->wroteAnything || $this->lastEmittedWasMarker) {
            return;
        }

        $this->pendingBlanks = max($this->pendingBlanks, 1);
        $this->emit(self::MARKER);
        $this->pendingBlanks = 1;
        $this->lastEmittedWasMarker = true;
    }

    private function emit(string $line): void
    {
        if ($this->wroteAnything) {
            // Any run of blanks collapses to a single blank line.
            $separator = $this->pendingBlanks > 0 ? "\n\n" : "\n";
            $this->write($separator);
        }

        $this->write($line);

        $this->pendingBlanks = 0;
        $this->wroteAnything = true;
        $this->lastEmittedWasMarker = ($line === self::MARKER);
    }

    private function write(string $text): void
    {
        if (fwrite($this->out, $text) === false) {
            throw new \RuntimeException('Failed to write markdown output.');
        }

        $this->characters += mb_strlen($text);
        $this->lines      += substr_count($text, "\n");

        if ($this->lines === 0 && $this->characters > 0) {
            $this->lines = 1;
        }
    }
}
