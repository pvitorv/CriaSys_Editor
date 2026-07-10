<?php

namespace Tests\Unit;

use App\Services\Script\ScriptParser;
use PHPUnit\Framework\TestCase;

class ScriptParserTest extends TestCase
{
    private ScriptParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ScriptParser;
    }

    public function test_splits_single_newline_paragraphs(): void
    {
        $result = $this->parser->parse("Primeiro parágrafo.\nSegundo parágrafo.\nTerceiro parágrafo.");

        $this->assertCount(3, $result['blocks']);
    }

    public function test_splits_dialogue_with_dash(): void
    {
        $result = $this->parser->parse("- Pedro: Cuidado!\n- Ana: Por quê?");

        $this->assertCount(2, $result['blocks']);
        $this->assertSame('Pedro: Cuidado!', $result['blocks'][0]['narration_text']);
        $this->assertSame('Ana: Por quê?', $result['blocks'][1]['narration_text']);
    }

    public function test_splits_character_name_on_next_line(): void
    {
        $result = $this->parser->parse("JOÃO\nOlá, tudo bem?");

        $this->assertCount(1, $result['blocks']);
        $this->assertSame('Olá, tudo bem?', $result['blocks'][0]['narration_text']);
        $this->assertSame('dialogue', $result['blocks'][0]['kind']);
    }

    public function test_em_dash_dialogue_never_uses_fala_label(): void
    {
        $result = $this->parser->parse("Narrativa.\n\u{2014} Moça? Precisa de ajuda?");

        $this->assertCount(2, $result['blocks']);
        $this->assertSame('dialogue', $result['blocks'][1]['kind']);
        $this->assertStringContainsString('Moça? Precisa de ajuda?', $result['blocks'][1]['narration_text']);
        $this->assertArrayNotHasKey('title', $result['blocks'][1]);
    }

    public function test_detects_refrain_section(): void
    {
        $text = "Verso um\nLinha dois\n\nREFRÃO\nOh oh oh\nCantamos juntos";

        $result = $this->parser->parse($text);

        $this->assertGreaterThanOrEqual(2, count($result['blocks']));
        $this->assertSame(1, $result['stats']['refrains']);
        $refrain = collect($result['blocks'])->first(fn ($b) => $b['kind'] === 'refrain');
        $this->assertNotNull($refrain);
        $this->assertStringContainsString('Oh oh oh', $refrain['body_text']);
    }

    public function test_repartido_goes_to_body_with_lyric_kind(): void
    {
        $result = $this->parser->parse("REPARTIDO\nPrimeira linha\nSegunda linha");

        $this->assertCount(1, $result['blocks']);
        $this->assertSame('repartido', $result['blocks'][0]['kind']);
        $this->assertStringContainsString('Primeira linha', $result['blocks'][0]['body_text']);
    }

    public function test_parses_continuous_horror_story_with_em_dash_dialogue(): void
    {
        $text = "O silêncio na rodovia abandonada só era quebrado pelo motor engasgado do Celta. No banco de trás, o celular sem sinal marcava meia-noite.De repente, os faróis iluminaram uma figura solitária na beira do asfalto, de costas. Vestia um vestido de noiva encardido e desfiado. O motorista pisou no freio, assustado, mas a estrada estava deserta e o bom coração falou mais alto. Ele abaixou o vidro.\u{2014} Moça? Precisa de ajuda?A mulher não se moveu. O vento frio da noite trouxe um cheiro forte de terra molhada e velas queimadas.\u{2014} Onde você vai? \u{2014} ele insistiu, com um arrepio na espinha.Lentamente, ela virou a cabeça. O pescoço estalou alto, em um ângulo impossível. No lugar do rosto, havia apenas uma superfície de pele lisa, sem olhos, nariz ou boca. Mesmo sem lábios, uma voz ecoou direto na mente do motorista, sussurrando um sotaque antigo:\u{2014} Eu já cheguei.O motor do carro morreu no mesmo instante. No retrovisor, ele viu a noiva sentada no banco de trás.";

        $result = $this->parser->parse($text);

        $this->assertGreaterThanOrEqual(8, count($result['blocks']));
        $this->assertSame(3, $result['stats']['dialogues']);
        $this->assertStringContainsString('Moça? Precisa de ajuda?', $result['blocks'][2]['narration_text']);
        $this->assertStringContainsString('Eu já cheguei', $result['blocks'][6]['narration_text']);
    }
}
