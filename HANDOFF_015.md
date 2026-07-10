# Handoff — Branch `015` (Bibliotecas de áudio, timeline e busca visual)

> Continuidade pós-014. Última atualização: 09/07/2026.

## O que esta branch entrega

### 1. Biblioteca de trilhas (`media/search?type=audio`)
- **Mixkit Music** — scraping gratuito + catálogo offline
- **Freesound** — trilhas longas (CC, crédito ao autor) via `FREESOUND_API_KEY`
- **Pixabay** — opcional com `PIXABAY_API_KEY`
- Importação para **Trilha 1/2/3** com `track_slot` e licença no `Asset`

### 2. Biblioteca de efeitos sonoros (`media/search?type=sfx`)
- **Mixkit SFX** — scraping gratuito + catálogo offline
- **Freesound** — efeitos curtos (≤15s) com crédito obrigatório
- Importação como `SoundEffect` com `start_at` na timeline

### 3. Créditos e licenças ativas
- `MediaAttribution` — Mixkit, Freesound, Pixabay por tipo (music/sfx)
- `ProjectAttributionCatalog` — inclui **efeitos sonoros** no bloco de créditos
- Publicação automática após import (`ProjectPublishAutoSyncService`)
- UI: badge © nos resultados que exigem crédito

### 4. Editor — aba Biblioteca
- Sub-abas: **Visual | Trilhas | Efeitos**
- Preview de áudio nos cards + botão Inserir
- Aba **Trilhas & FX** com faixas na timeline e atalhos para biblioteca
- `GET /api/media/providers` — status das APIs configuradas

### 5. Busca de imagens (melhoria)
- `MediaSearchQueryTranslator` — extrai palavras-chave visuais do roteiro (PT→EN)
- Não envia parágrafo inteiro às APIs
- `GET /api/media/suggest-query`

### 6. Áudio no preview
- `PreviewAudioMixer` — narração + 3 trilhas + efeitos
- Migrations: `track_slot`, tabela `sound_effects`, `duration_mode` nos slides

## APIs no `.env`
```env
FREESOUND_API_KEY=   # https://freesound.org/apiv2/apply
PIXABAY_API_KEY=     # opcional trilhas
PEXELS_API_KEY=      # imagens/vídeos
```

Mixkit (música, SFX, vídeo) funciona **sem chave**.

## Rotas novas
```
GET  /api/media/providers
GET  /api/media/suggest-query
GET  /api/projects/{id}/sound-effects
POST /api/projects/{id}/sound-effects
POST /api/projects/{id}/media/import  (target: sound_effect | audio_track)
```

## Workflow Git
```bash
git checkout 015
git push -u origin 015
```

## Branch atual de trabalho
- **`015`** — bibliotecas áudio/SFX + timeline áudio *(esta entrega)*

## Como rodar
```bash
npm run build
php artisan migrate
php artisan serve
```

## Pendências conhecidas
- FFmpeg export ainda mixa **1 trilha** (preview já mixa 3 + efeitos)
- Arrastar efeitos na timeline (hoje só campo numérico + faixa visual)
- Freesound: download usa preview HQ (não OAuth de arquivo completo)
