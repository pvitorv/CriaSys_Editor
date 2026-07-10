<?php

namespace Tests\Unit;

use App\Services\MediaLibrary\MediaSearchQueryTranslator;
use Tests\TestCase;

class MediaSearchQueryTranslatorTest extends TestCase
{
    private MediaSearchQueryTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translator = new MediaSearchQueryTranslator;
    }

    public function test_extracts_visual_keywords_from_narration_sentence(): void
    {
        $extracted = $this->translator->extractVisualKeywords(
            'João disse que o mundo não é mais o mesmo'
        );

        $this->assertSame('world', $extracted);
    }

    public function test_extracts_keywords_from_dialogue_line(): void
    {
        $extracted = $this->translator->extractVisualKeywords(
            '— E eu vou seguir em frente, mesmo com medo'
        );

        $this->assertSame('fear', $extracted);
    }

    public function test_extracts_city_at_night_from_phrase(): void
    {
        $extracted = $this->translator->extractVisualKeywords('cidade de noite com luzes');

        $this->assertStringContainsString('city', $extracted);
        $this->assertStringContainsString('night', $extracted);
    }

    public function test_terms_do_not_include_full_portuguese_paragraph(): void
    {
        $query = 'Naquela manhã chuvosa, Maria caminhava pela praia pensando no futuro';
        $terms = $this->translator->termsFor($query);

        $this->assertNotContains($query, $terms);
        $this->assertNotEmpty($terms);
        $this->assertStringContainsString('beach', $terms[0]);
        $this->assertLessThanOrEqual(3, count(explode(' ', $terms[0])));
    }

    public function test_short_manual_query_is_preserved(): void
    {
        $terms = $this->translator->termsFor('praia');

        $this->assertContains('beach', $terms);
    }

    public function test_meta_includes_extracted_field(): void
    {
        $meta = $this->translator->meta('futebol no estádio');

        $this->assertArrayHasKey('extracted', $meta);
        $this->assertStringContainsString('football', $meta['extracted']);
        $this->assertNotEmpty($meta['primary']);
    }
}
