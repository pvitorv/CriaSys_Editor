<?php

namespace App\Services\MediaLibrary;

class MediaSearchQueryTranslator
{
    /** @var array{phrases: array<string, string>, words: array<string, string>} */
    private array $dictionary;

    public function __construct()
    {
        $this->dictionary = config('media_search_pt_en', ['phrases' => [], 'words' => []]);
    }

    /**
     * Termos para buscar nas APIs (inglês primeiro, original como fallback).
     *
     * @return list<string>
     */
    public function termsFor(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $normalized = $this->normalize($query);
        $terms = [];

        $phrase = $this->translatePhrase($normalized);
        if ($phrase !== null) {
            $terms[] = $phrase;
        }

        $wordTranslation = $this->translateWords($normalized);
        if ($wordTranslation !== null) {
            $terms[] = $wordTranslation;
        }

        $terms[] = $query;
        $terms[] = $normalized;

        return array_values(array_unique(array_filter($terms)));
    }

    /** Termo principal (preferencialmente em inglês). */
    public function primaryTerm(string $query): string
    {
        $terms = $this->termsFor($query);

        return $terms[0] ?? trim($query);
    }

    public function wasTranslated(string $query): bool
    {
        $primary = $this->primaryTerm($query);
        $original = mb_strtolower(trim($query));

        return $this->normalize($primary) !== $this->normalize($original);
    }

    /**
     * @return array{query: string, primary: string, terms: list<string>, translated: bool}
     */
    public function meta(string $query): array
    {
        $terms = $this->termsFor($query);
        $primary = $terms[0] ?? trim($query);
        $translated = $this->wasTranslated($query);

        return [
            'query' => trim($query),
            'primary' => $primary,
            'terms' => $terms,
            'translated' => $translated,
            'hint' => $translated
                ? 'Buscando também como: '.$primary
                : null,
        ];
    }

    private function translatePhrase(string $normalized): ?string
    {
        $phrases = $this->dictionary['phrases'] ?? [];

        if (isset($phrases[$normalized])) {
            return $phrases[$normalized];
        }

        // tenta frases dentro da query (ex.: "cidade de noite")
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
