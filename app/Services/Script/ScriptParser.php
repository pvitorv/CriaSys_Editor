<?php

namespace App\Services\Script;

class ScriptParser
{
    /**
     * @return array{
     *     blocks: list<array{narration_text: string, title: string, kind: string}>,
     *     formatted_script: string,
     *     stats: array{slides: int, dialogues: int, verses: int, prose: int, refrains: int}
     * }
     */
    public function parse(string $rawText): array
    {
        $normalized = $this->expandWallOfText($this->normalizeRaw($rawText));
        if ($normalized === '') {
            return $this->emptyResult();
        }

        $blocks = $this->parseLines(explode("\n", $normalized));
        $mapped = [];
        foreach ($blocks as $index => $block) {
            $mapped[] = [
                'narration_text' => $block['narration_text'],
                'title' => $block['title'] ?? ('Slide '.($index + 1)),
                'kind' => $block['kind'],
            ];
        }

        return [
            'blocks' => $mapped,
            'formatted_script' => implode("\n\n", array_column($mapped, 'narration_text')),
            'stats' => $this->stats($mapped),
        ];
    }

    public function formatNarrationText(string $text): string
    {
        $parsed = $this->parse($text);
        if ($parsed['blocks'] === []) {
            return $this->formatProseLine(preg_replace('/\s+/', ' ', $this->normalizeRaw($text)) ?? '');
        }

        return implode(' ', array_column($parsed['blocks'], 'narration_text'));
    }

    /**
     * @return array{blocks: list<array<string, mixed>>, formatted_script: string, stats: array<string, int>}
     */
    private function emptyResult(): array
    {
        return [
            'blocks' => [],
            'formatted_script' => '',
            'stats' => ['slides' => 0, 'dialogues' => 0, 'verses' => 0, 'prose' => 0, 'refrains' => 0],
        ];
    }

    /**
     * @param  list<array{narration_text: string, kind: string}>  $blocks
     * @return array{slides: int, dialogues: int, verses: int, prose: int, refrains: int}
     */
    private function stats(array $blocks): array
    {
        return [
            'slides' => count($blocks),
            'dialogues' => count(array_filter($blocks, fn ($b) => $b['kind'] === 'dialogue')),
            'verses' => count(array_filter($blocks, fn ($b) => $b['kind'] === 'verse')),
            'refrains' => count(array_filter($blocks, fn ($b) => $b['kind'] === 'refrain')),
            'prose' => count(array_filter($blocks, fn ($b) => $b['kind'] === 'prose')),
        ];
    }

    private function normalizeRaw(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{00AB}", "\u{00BB}"], '"', $text);
        $text = str_replace(["\u{2018}", "\u{2019}"], "'", $text);
        $text = str_replace(["\u{2013}", "\u{2015}"], "\u{2014}", $text);
        $text = str_replace("\u{2026}", '...', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/s', '$1', $text) ?? $text;
        $text = preg_replace('/\*(.+?)\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/_(.+?)_/s', '$1', $text) ?? $text;
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n[ \t]+/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{narration_text: string, title: ?string, kind: string}>
     */
    private function parseLines(array $lines): array
    {
        $blocks = [];
        $proseBuffer = [];
        $verseBuffer = [];
        $pendingSpeaker = null;
        $currentTitle = null;
        $currentKind = 'prose';

        $flushProse = function () use (&$blocks, &$proseBuffer, &$currentTitle, &$currentKind): void {
            if ($proseBuffer === []) {
                return;
            }
            $kind = $currentKind === 'refrain' ? 'refrain' : 'prose';
            $text = implode(' ', array_map('trim', $proseBuffer));
            $proseBuffer = [];
            if ($text !== '') {
                $blocks[] = [
                    'narration_text' => $this->formatProseLine($text),
                    'title' => $currentTitle ?? $this->truncateTitle($text),
                    'kind' => $kind,
                ];
            }
        };

        $flushVerse = function () use (&$blocks, &$verseBuffer, &$currentTitle, &$currentKind): void {
            if ($verseBuffer === []) {
                return;
            }
            $kind = $currentKind === 'refrain' ? 'refrain' : 'verse';
            $lines = $verseBuffer;
            $verseBuffer = [];
            $blocks[] = [
                'narration_text' => $this->formatVerseLines($lines),
                'title' => $currentTitle ?? $this->truncateTitle($lines[0] ?? ''),
                'kind' => $kind,
            ];
        };

        $flushAll = function () use (&$flushProse, &$flushVerse, &$pendingSpeaker): void {
            $flushProse();
            $flushVerse();
            $pendingSpeaker = null;
        };

        foreach ($lines as $i => $raw) {
            $line = trim($raw);
            if ($line === '') {
                $flushAll();
                $currentTitle = null;
                $currentKind = 'prose';

                continue;
            }

            if ($this->isStageDirection($line)) {
                continue;
            }

            $section = $this->parseSectionHeader($line);
            if ($section) {
                $flushAll();
                $currentTitle = $section['title'];
                $currentKind = preg_match('/^(REFRÃO|REFRAO|CORO)$/iu', $section['label']) ? 'refrain'
                    : (preg_match('/^(VERSO|ESTROFE|ESTRÓFE)$/iu', $section['label']) ? 'verse' : 'prose');

                continue;
            }

            if ($pendingSpeaker) {
                $speaker = $pendingSpeaker;
                $pendingSpeaker = null;
                $flushProse();
                $flushVerse();
                $blocks[] = [
                    'narration_text' => $this->formatDialogue($speaker, $line),
                    'title' => $this->formatSpeaker($speaker) ?? $currentTitle,
                    'kind' => 'dialogue',
                ];

                continue;
            }

            if ($this->isCharacterNameLine($line)) {
                $flushAll();
                $pendingSpeaker = $line;

                continue;
            }

            if ($this->isEmDashLine($line)) {
                $flushProse();
                $flushVerse();
                $blocks[] = [
                    'narration_text' => $this->formatEmDashDialogue($line),
                    'title' => 'Fala',
                    'kind' => 'dialogue',
                ];

                continue;
            }

            $speaker = $this->parseSpeakerLine($line);
            if ($speaker) {
                $flushAll();
                $blocks[] = [
                    'narration_text' => $this->formatDialogue($speaker['speaker'], $speaker['text']),
                    'title' => $this->formatSpeaker($speaker['speaker']) ?? $currentTitle,
                    'kind' => 'dialogue',
                ];

                continue;
            }

            $prevLine = $i > 0 ? trim($lines[$i - 1]) : '';
            if ($proseBuffer !== [] && $this->shouldBreakProse($proseBuffer[array_key_last($proseBuffer)], $line)) {
                $flushProse();
            }

            if (in_array($currentKind, ['refrain', 'verse'], true) || $this->isVerseLine($line)) {
                $flushProse();
                $verseBuffer[] = $line;

                continue;
            }

            if ($verseBuffer !== []) {
                $flushVerse();
            }

            $proseBuffer[] = $line;
        }

        $flushAll();

        return $blocks;
    }

    /**
     * @return array{label: string, title: string}|null
     */
    private function parseSectionHeader(string $line): ?array
    {
        if ($this->isSceneMarker($line)) {
            return ['label' => $line, 'title' => $this->titleFromMarker($line) ?? $line];
        }

        if (! preg_match('/^(?:\[\s*)?(REFRÃO|REFRAO|CORO|VERSO|ESTROFE|ESTRÓFE|INTRO|PONTE|PARTE|CENA|NARRADOR|NARRAÇÃO|NARRACAO|LOCUÇÃO|LOCUCAO|FALA|SLIDE)\s*\d*\s*(?:[-–—:]\s*([^\]\n]+))?\s*(?:\])?\s*$/iu', $line, $m)) {
            return null;
        }

        $label = $m[1];
        $title = trim($m[2] ?? '') ?: (mb_strtoupper(mb_substr($label, 0, 1)).mb_strtolower(mb_substr($label, 1)));

        return ['label' => $label, 'title' => $title];
    }

    private function isSceneMarker(string $line): bool
    {
        return (bool) preg_match('/^(?:[-–—=*_]{3,}|#{1,3}\s*(?:Slide|Cena|Parte|Ato)\s+\d+.*|(?:Slide|Cena|Parte|Ato)\s+\d+\s*[:.\-–—].*)$/iu', $line);
    }

    private function isStageDirection(string $line): bool
    {
        return (bool) preg_match('/^(?:\([^)]{1,120}\)|\[[^\]]{1,120}\])$/u', $line)
            && ! $this->parseSectionHeader($line);
    }

    /**
     * @return array{speaker: string, text: string}|null
     */
    private function parseSpeakerLine(string $line): ?array
    {
        $patterns = [
            '/^(?:[-–—•*]\s*)?([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸa-zàáâãäåæçèéêëìíîïñòóôõöùúûüýÿ\s.\'-]{0,40}?)\s*:\s*(.+)$/u',
            '/^(?:[-–—•*]\s*)?([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸa-zàáâãäåæçèéêëìíîïñòóôõöùúûüýÿ\s.\'-]{0,40}?)\s*[-–—]\s+(.+)$/u',
            '/^\(([^)()]{1,40})\)\s*(.+)$/u',
            '/^(.+?),\s*(?:disse|falou|perguntou|respondeu|exclamou|murmurou|gritou|sussurrou)\s+([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][\w\s.\'-]{1,40})\.?\s*$/iu',
        ];

        foreach ($patterns as $index => $pattern) {
            if (! preg_match($pattern, $line, $m)) {
                continue;
            }
            if ($index === 2 && ! $this->looksLikeName($m[1])) {
                continue;
            }
            if ($index === 3) {
                return ['speaker' => trim($m[2]), 'text' => trim($m[1])];
            }

            return ['speaker' => trim($m[1]), 'text' => trim($m[2])];
        }

        return null;
    }

    private function isCharacterNameLine(string $line): bool
    {
        if (! preg_match('/^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ\s.\'-]{1,35}$/u', $line)) {
            return false;
        }

        return ! $this->parseSectionHeader($line) && ! $this->isSceneMarker($line) && ! $this->parseSpeakerLine($line);
    }

    private function isVerseLine(string $line): bool
    {
        if ($line === '' || mb_strlen($line) > 80) {
            return false;
        }
        if ($this->parseSpeakerLine($line) || $this->isCharacterNameLine($line)) {
            return false;
        }
        if ($this->isSceneMarker($line) || $this->parseSectionHeader($line)) {
            return false;
        }

        return ! preg_match('/[.!?;:]$/u', $line) || (mb_strlen($line) <= 52 && preg_match('/[,;]$/u', $line));
    }

    private function shouldBreakProse(string $prevLine, string $nextLine): bool
    {
        if ($prevLine === '' || $nextLine === '') {
            return false;
        }
        if ($this->parseSectionHeader($nextLine) || $this->isSceneMarker($nextLine)) {
            return true;
        }
        if ($this->parseSpeakerLine($nextLine) || $this->isCharacterNameLine($nextLine)) {
            return true;
        }

        return (bool) preg_match('/[.!?…"»]$/u', $prevLine) && (bool) preg_match('/^[A-ZÀÁ"«([]/u', $nextLine);
    }

    private function looksLikeName(string $name): bool
    {
        $n = trim($name);
        if ($n === '' || mb_strlen($n) > 40 || preg_match('/^\d+$/', $n)) {
            return false;
        }

        return (bool) preg_match('/^(?:narra(?:dor|ção|cao)|voz|off|locu(?:ção|cao|tor))$/iu', $n)
            || (bool) preg_match('/^[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ]/u', $n);
    }

    private function formatSpeaker(?string $speaker): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $speaker) ?? '');
        if ($name === '') {
            return null;
        }
        if ($name === mb_strtoupper($name) && mb_strlen($name) > 2) {
            $name = mb_strtoupper(mb_substr($name, 0, 1)).mb_strtolower(mb_substr($name, 1));
        }
        if (preg_match('/^(?:narra(?:dor|ção|cao)|voz|off|locu(?:ção|cao|tor))$/iu', $name)) {
            return null;
        }

        return $name;
    }

    private function formatDialogue(?string $speaker, string $text): string
    {
        $name = $this->formatSpeaker($speaker);
        $clean = $this->formatProseLine($text, false);
        if ($clean === '') {
            return '';
        }
        if (! $name) {
            return $clean;
        }
        $verb = preg_match('/^(?:ele|ela|eles|elas)$/iu', $name) ? 'continuou' : 'disse';

        return "{$name} {$verb}: {$clean}";
    }

    private function formatProseLine(string $text, bool $forceEnd = true): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($t === '') {
            return '';
        }
        $t = preg_replace('/\s+([,.!?;:])/', '$1', $t) ?? $t;
        $t = preg_replace('/([,.!?;:])([^\s\d])/', '$1 $2', $t) ?? $t;
        if ($forceEnd && ! preg_match('/[.!?…]$/u', $t) && mb_strlen($t) > 3) {
            $t .= '.';
        }

        return mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
    }

    /**
     * @param  list<string>  $lines
     */
    private function formatVerseLines(array $lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            $t = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($t === '') {
                continue;
            }
            $t = preg_replace('/^[-–—•*]\s*/', '', $t) ?? $t;
            $t = preg_replace('/[,;]+$/', '', $t) ?? $t;
            if (! preg_match('/[.!?…]$/u', $t)) {
                $t .= '.';
            }
            $parts[] = mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
        }

        return implode(' ', $parts);
    }

    private function titleFromMarker(string $marker): ?string
    {
        if (preg_match('/\[(?:CENA|SLIDE|PARTE|ATO|SCENE)\s*\d*\s*[-–—:]?\s*([^\]]+)\]/iu', $marker, $m)) {
            return $this->truncateTitle($m[1]);
        }
        if (preg_match('/(?:Slide|Cena|Parte|Ato)\s+\d+\s*[-–—:.]\s*(.+)/iu', $marker, $m)) {
            return $this->truncateTitle($m[1]);
        }

        return null;
    }

    private function truncateTitle(string $text, int $max = 60): ?string
    {
        $t = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($t === '') {
            return null;
        }

        return mb_strlen($t) <= $max ? $t : mb_substr($t, 0, $max - 1).'…';
    }

    private function expandWallOfText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $t = str_contains($text, "\n") ? $text : trim(preg_replace('/\s+/', ' ', $text) ?? '');
        $t = preg_replace('/([.!?,:;])([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ])/u', '$1 $2', $t) ?? $t;
        $t = preg_replace('/:\s*(\x{2014})/u', ":\n\n$1", $t) ?? $t;

        $segments = [];
        $cursor = 0;
        $len = mb_strlen($t);

        while ($cursor < $len) {
            $dashIdx = mb_strpos($t, "\u{2014}", $cursor);
            if ($dashIdx === false) {
                $prose = trim(mb_substr($t, $cursor));
                if ($prose !== '') {
                    $segments[] = ['type' => 'prose', 'text' => $prose];
                }
                break;
            }

            if ($dashIdx > $cursor) {
                $prose = trim(mb_substr($t, $cursor, $dashIdx - $cursor));
                if ($prose !== '') {
                    $segments[] = ['type' => 'prose', 'text' => $prose];
                }
            }

            $end = $this->findDialogueEnd($t, $dashIdx);
            $segments[] = ['type' => 'dialogue', 'text' => trim(mb_substr($t, $dashIdx, $end - $dashIdx))];
            $cursor = $end;
        }

        $lines = [];
        foreach ($segments as $seg) {
            if ($seg['type'] === 'dialogue') {
                $lines[] = $seg['text'];
                continue;
            }

            $p = $seg['text'];
            $p = preg_replace('/([.!?])\s+((?:De repente|Subitamente|Naquele momento|Por fim|Finalmente),)/iu', "$1\n\n$2", $p) ?? $p;
            $p = preg_replace('/([.!?])\s+(Lentamente,)/iu', "$1\n\n$2", $p) ?? $p;
            $p = preg_replace('/([.!?])\s+(O motor\b|No retrovisor\b)/iu', "$1\n\n$2", $p) ?? $p;

            foreach (preg_split("/\n\n+/", $p) ?: [] as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $lines[] = $trimmed;
                }
            }
        }

        return implode("\n\n", $lines);
    }

    private function findDialogueEnd(string $t, int $dashIdx): int
    {
        $len = mb_strlen($t);
        for ($i = $dashIdx + 1; $i < $len; $i++) {
            $ch = mb_substr($t, $i, 1);
            if (! preg_match('/[.!?]/u', $ch)) {
                continue;
            }

            $next = $i + 1;
            while ($next < $len && mb_substr($t, $next, 1) === ' ') {
                $next++;
            }

            if ($next >= $len) {
                return $len;
            }

            if (mb_substr($t, $next, 1) === "\u{2014}") {
                if (preg_match('/^\x{2014}\s*(ele|ela)\s+/iu', mb_substr($t, $next))) {
                    continue;
                }
            }

            $word = $this->nextWordAt($t, $next);
            if ($word && ! $this->isDialogueContinuation($word)) {
                return $i + 1;
            }
        }

        return $len;
    }

    private function nextWordAt(string $text, int $pos): ?string
    {
        if (! preg_match('/^([A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝŸ][A-Za-zÀ-ÿ]{0,30})/u', mb_substr($text, $pos), $m)) {
            return null;
        }

        return $m[1];
    }

    private function isDialogueContinuation(string $word): bool
    {
        $w = mb_strtolower($word);

        return in_array($w, [
            'precisa', 'onde', 'moça', 'moca', 'como', 'quem', 'por', 'quê', 'que', 'eu', 'não', 'nao', 'sim',
            'você', 'voce', 'me', 'te', 'se', 'já', 'ja', 'olá', 'ola', 'oi', 'de', 'do', 'da', 'um', 'uma',
        ], true);
    }

    private function isEmDashLine(string $line): bool
    {
        return (bool) preg_match('/^[\x{2014}—]\s*.+/u', trim($line));
    }

    private function formatEmDashDialogue(string $line): string
    {
        $t = preg_replace('/^[\x{2014}—]\s*/u', '', trim($line)) ?? trim($line);

        if (preg_match('/^(.+?[.!?])\s+[\x{2014}—]\s+(ele|ela)\s+(.+)$/iu', $t, $m)) {
            $tag = mb_strtoupper(mb_substr($m[2], 0, 1)).mb_strtolower(mb_substr($m[2], 1)).' '.rtrim($m[3], '.');

            return $this->formatProseLine("{$tag}: {$m[1]}", false);
        }

        $t = preg_replace('/\s+[\x{2014}—]\s+/u', ' ', $t) ?? $t;

        return $this->formatProseLine($t, false);
    }
}
