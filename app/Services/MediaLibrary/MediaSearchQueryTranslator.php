<?php

namespace App\Services\MediaLibrary;

class MediaSearchQueryTranslator
{
    /** @var array{phrases: array<string, string>, words: array<string, string>, stop_words: list<string>, abstract_words: list<string>} */
    private array $dictionary;

    public function __construct()
    {
        $this->dictionary = config('media_search_pt_en', ['phrases' => [], 'words' => [], 'stop_words' => []]);
    }

    /**
     * Extrai 1–4 palavras-chave visuais de texto de slide/narração (ignora conectivos e falas).
     */
    public function extractVisualKeywords(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        $query = preg_replace('/^[—–\-]+\s*/u', '', $query) ?? $query;
        $query = preg_replace('/^["\'«»]+|["\'«»]+$/u', '', trim($query)) ?? $query;
        $query = preg_replace('/^[A-Za-zÀ-ÿ]{2,30}\s+(disse|falou|perguntou|respondeu|gritou|sussurrou)\s*:?\s*/ui', '', $query) ?? $query;
        $query = trim($query);

        $normalized = $this->normalize($query);
        if ($normalized === '') {
            return '';
        }

        $phrases = $this->dictionary['phrases'] ?? [];
        if (isset($phrases[$normalized])) {
            return $phrases[$normalized];
        }

        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = array_flip($this->dictionary['stop_words'] ?? []);
        $map = $this->dictionary['words'] ?? [];

        $visual = [];
        $fallback = [];

        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? $word;
            if ($clean === '' || mb_strlen($clean) < 2) {
                continue;
            }
            if (isset($stopWords[$clean])) {
                continue;
            }

            if (isset($map[$clean])) {
                $visual[] = $map[$clean];
            } elseif (mb_strlen($clean) >= 4) {
                $fallback[] = $clean;
            }
        }

        if ($visual !== []) {
            return $this->compactVisualTerms(array_values(array_unique($visual)));
        }

        if ($fallback !== []) {
            $translated = [];
            foreach ($fallback as $word) {
                $translated[] = $map[$word] ?? $word;
            }

            return $this->compactVisualTerms(array_values(array_unique($translated)));
        }

        $plain = [];
        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? $word;
            if ($clean !== '' && ! isset($stopWords[$clean])) {
                $plain[] = $map[$clean] ?? $clean;
            }
        }

        $compact = $this->compactVisualTerms($plain);

        return $compact !== '' ? $compact : mb_substr($normalized, 0, 40);
    }

    /**
     * Termos para buscar nas APIs (inglês compacto; sem parágrafo original).
     *
     * @return list<string>
     */
    public function termsFor(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $extracted = $this->extractVisualKeywords($query);
        $terms = [];

        if ($extracted !== '') {
            $terms[] = $extracted;
        }

        $normalizedExtracted = $this->normalize($extracted);
        $wordTranslation = $this->translateWords($normalizedExtracted);
        if ($wordTranslation !== null && ! in_array($wordTranslation, $terms, true)) {
            $terms[] = $wordTranslation;
        }

        $wordCount = count(preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($wordCount <= 3) {
            $direct = $this->translateWords($this->normalize($query));
            if ($direct !== null && ! in_array($direct, $terms, true)) {
                array_unshift($terms, $direct);
            }
            if ($wordCount <= 2 && ! in_array($query, $terms, true)) {
                $terms[] = $query;
            }
        }

        return array_values(array_unique(array_filter($terms)));
    }

    /** Termo principal (preferencialmente em inglês). */
    public function primaryTerm(string $query): string
    {
        $terms = $this->termsFor($query);

        return $terms[0] ?? $this->extractVisualKeywords($query) ?: trim($query);
    }

    public function wasTranslated(string $query): bool
    {
        $primary = $this->primaryTerm($query);
        $original = mb_strtolower(trim($query));

        return $this->normalize($primary) !== $this->normalize($original);
    }

    /**
     * @return array{query: string, extracted: string, primary: string, terms: list<string>, translated: bool, hint: ?string}
     */
    public function meta(string $query): array
    {
        $extracted = $this->extractVisualKeywords($query);
        $terms = $this->termsFor($query);
        $primary = $terms[0] ?? $extracted ?: trim($query);
        $translated = $this->wasTranslated($query);

        $hint = null;
        if ($extracted !== '' && $this->normalize($extracted) !== $this->normalize($query)) {
            $hint = 'Termos visuais: '.$primary;
        } elseif ($translated) {
            $hint = 'Buscando como: '.$primary;
        }

        return [
            'query' => trim($query),
            'extracted' => $extracted,
            'primary' => $primary,
            'terms' => $terms,
            'translated' => $translated,
            'hint' => $hint,
        ];
    }

    /**
     * @param  list<string>  $terms
     */
    private function compactVisualTerms(array $terms, int $max = 3): string
    {
        if ($terms === []) {
            return '';
        }

        $abstract = array_flip($this->dictionary['abstract_words'] ?? []);
        $concrete = [];
        $fallback = [];

        foreach ($terms as $term) {
            $key = $this->normalize($term);
            if (isset($abstract[$key])) {
                $fallback[] = $term;
            } else {
                $concrete[] = $term;
            }
        }

        $picked = array_slice(array_values(array_unique([...$concrete, ...$fallback])), 0, $max);

        return implode(' ', $picked);
    }

    private function translatePhrase(string $normalized): ?string
    {
        $phrases = $this->dictionary['phrases'] ?? [];

        if (isset($phrases[$normalized])) {
            return $phrases[$normalized];
        }

        $best = null;
        $bestLen = 0;
        foreach ($phrases as $pt => $en) {
            $ptNorm = $this->normalize($pt);
            if (str_contains($normalized, $ptNorm) && strlen($ptNorm) > $bestLen) {
                $best = str_replace($ptNorm, $en, $normalized);
                $bestLen = strlen($ptNorm);
            }
        }

        return $best;
    }

    private function translateWords(string $normalized): ?string
    {
        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return null;
        }

        $map = $this->dictionary['words'] ?? [];
        $translated = [];
        $changed = false;

        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? $word;
            $key = $this->normalize($clean);

            if ($key !== '' && isset($map[$key])) {
                $translated[] = $map[$key];
                $changed = true;
            } else {
                $translated[] = $clean !== '' ? $clean : $word;
            }
        }

        if (! $changed) {
            return null;
        }

        return implode(' ', $translated);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $from = ['á', 'à', 'ã', 'â', 'ä', 'é', 'ê', 'ë', 'í', 'î', 'ï', 'ó', 'ô', 'õ', 'ö', 'ú', 'ü', 'ç', 'ñ'];
        $to = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'u', 'u', 'c', 'n'];

        return str_replace($from, $to, $text);
    }
}
