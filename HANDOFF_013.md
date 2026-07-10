# Handoff — Branch `013` (Roteiro inteligente + TTS multi-motor)

> Continuidade pós-012. Última atualização: 09/07/2026.

## O que esta branch entrega

### 1. Parser de roteiro (`ScriptParser` + `scriptParser.js`)
- Texto contínuo colado do Word (sem quebras) — detecta parágrafos, falas com travessão (`—`), versos, refrões e cenas
- Colar no **Roteiro completo** ou texto grande na narração → distribui automaticamente nos slides
- `POST /slides/apply-script` e `POST /slides/parse-script`
- Testes: `tests/Unit/ScriptParserTest.php`

### 2. TTS — motores e fallback
- **OpenAI TTS** (recomendado, uso comercial) — chave em Integrações
- **Piper** local (grátis) — `scripts/setup-piper.ps1`
- **Edge TTS** + fallback Python (`generate-tts-edge.py`)
- **ElevenLabs** — integração por usuário
- **Coqui XTTS removido** (pesado e instável no Windows)
- `TtsEngineFactory::synthesizeWithFallback`

### 3. CMS Integrações
- OpenAI: cadastro de chave, tentativa de saldo, link de billing
- ElevenLabs: créditos/plano (já existia, mantido)

## Workflow Git
```bash
git add -A && git commit -m "..." && git push origin 013
git checkout -b 014
git push -u origin 014
```

## Branch atual de trabalho
- **`015`** — bibliotecas áudio/SFX + timeline (ver `HANDOFF_015.md`)

## Como rodar
```bash
npm run build
php artisan serve
php artisan queue:work
```

## TTS local (Piper)
```powershell
powershell -File scripts/setup-piper.ps1
```

## Chaves úteis
```env
TTS_DEFAULT_ENGINE=openai
TTS_DEFAULT_VOICE=onyx
PIPER_PATH=bin/piper/piper/piper.exe
```
