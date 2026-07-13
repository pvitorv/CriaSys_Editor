# Handoff 029 — TTS / narração + busca de vídeos

**Branch salva:** `038` (commit deste handoff)  
**Próxima branch:** `039` (checkout para continuidade)  
**Data:** 2026-07-12

## Contexto

Continuidade da branch `038` (pós Publish Kit / Finalizar online). Foco desta sessão:

1. Corrigir **buscador de vídeos** (resultados irrelevantes)
2. Corrigir **gerador de vozes** (teste OK, narração completa falhava)
3. UX de narração para usuário final (sem depender de Cursor/VS Code/fila manual)

## O que foi feito

### Busca de vídeos (Mixkit)
- `MixkitVideoService` prioriza páginas de **categoria** (`/free-stock-video/{term}/`) em vez de `/search/` genérico
- Filtra clipes “search UI” (buscar na internet, scrolling, etc.)
- `MediaSearchQueryTranslator::termsForVideo()` — termo objetivo PT→EN (`gato`→`cat`, `chuva`→`rain`)
- Frontend: modo **vídeo** não auto-preenche busca com texto do slide
- `suggest-query` / search com `type=video`

### TTS / narração
- Mapeamento de voz por motor (`onyx` → Piper/Edge válidos)
- Piper: resolve FFmpeg embutido (`electron/ffmpeg/win/`); fallback WAV se MP3 falhar
- Fallback: Piper prioritário após falha de OpenAI/ElevenLabs
- Default recomendado: **Piper** (`pt-br-faber-calmo`)
- Narração completa **assíncrona** via `App::terminating` + polling no editor (não precisa `queue:listen` no Laragon)
- Seleção de slides na UI: Marcar todos / Com texto / Limpar
- Concatenação de áudio robusta (normalize MP3 + reencode se `concat` falhar)
- Status API prioriza narração `processing` sobre `completed` antiga
- Progresso na tela: “Gerando slide X de Y…”

### Electron / env
- `portable-env.js`: `TTS_DEFAULT_ENGINE=piper`, paths Piper absolutos
- `laravel.js` (dev): injeta `FFMPEG_PATH` / default Piper

## Arquivos principais

```
app/Services/MediaLibrary/MixkitVideoService.php
app/Services/MediaLibrary/MediaSearchQueryTranslator.php
app/Http/Controllers/Api/MediaLibraryController.php
app/Services/NarrationService.php
app/Http/Controllers/Api/NarrationController.php
app/Jobs/GenerateNarrationJob.php
app/Services/Tts/TtsEngineFactory.php
app/Services/Tts/PiperTtsEngine.php
app/Support/TtsNodeRunner.php
config/criasys.php
resources/js/editor.js
resources/views/projects/editor.blade.php
electron/portable-env.js
electron/ipc/laravel.js
tests/Unit/MediaSearchQueryTranslatorTest.php
```

## Como testar (ao retornar)

1. `npm run build` se necessário + **Ctrl+F5** no editor
2. **Vídeos:** Biblioteca → Vídeos → buscar `gato`, `cachorro`, `chuva`
3. **Narração:** Roteiro → motor **Piper** → marcar slides (Com texto) → Testar voz (1 slide) → Gerar narração completa (aguardar progresso)
4. Opcional `.env`:
   ```
   TTS_DEFAULT_ENGINE=piper
   TTS_DEFAULT_VOICE=pt-br-faber-calmo
   ```

## Pendências / próximo

- [ ] Confirmar narração completa na UI com o usuário (último feedback: teste OK; completa ainda falhava — correções async + seleção + concat feitas, **validar no browser**)
- [ ] Migration `creator_profile` se ainda não aplicada no ambiente do usuário
- [ ] **Não** versionar binários `electron/php/win/*` nem `electron/ffmpeg/win/*.exe` (ficam locais)
- [ ] Publicação direta YouTube/TikTok/Instagram: **fora de escopo** até pedido explícito
- [ ] Se `App::terminating` falhar em algum host PHP (timeout/kill), considerar fila `database` + worker automático no Electron

## Notas

- OpenAI / ElevenLabs só com chave em Integrações; Piper é o caminho grátis local.
- Binários PHP/FFmpeg do Electron **não** entram no git.
- Branch histórico recente: `033`…`038` (deployment → bundle → creator → publish kit → finalizar).
