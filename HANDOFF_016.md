# Handoff — Branch `016` (Timeline cortes + Thumbnails profissionais)

> Continuidade pós-015. Última atualização: 10/07/2026 (sessão final).

## O que esta branch entrega

### 1. Timeline — régua dupla e ferramentas de corte
- Régua de tempo inferior + playhead violeta
- Marcas In / Out + Aplicar corte (narração, trilhas, FX)
- Campos `trim_in`, `trim_out`, `source_duration`, `clip_duration`

### 2. Thumbnails — plataformas, modelos, molduras, biblioteca pessoal
- **Plataformas:** YouTube, Shorts, TikTok, Reels, Stories, Feed quadrado
- **Modelos profissionais** (~15 layouts) em `config/thumbnail_templates.php`
- **Molduras separadas** (~200+) em `config/thumbnail_frames.php` + categoria `youtube_br`
- **32 fontes** agrupadas no select
- **Biblioteca pessoal:** upload PNG/WebP, conjuntos/pastas, remover pasta, gerenciar ocultas
- **Preview sticky** com rolagem vertical sobre a capa
- **Slide de origem:** `slide_id` + `slide_index`; extrai frame de vídeo via FFmpeg quando slide só tem vídeo
- **Geração/preview** envia todas as settings da UI (modelo, moldura, slide, texto, cores…)
- **Destaque / Fundo:** cor + transparência % + botão **Nenhum** (opacity 0)
- **Alinhamento:** horizontal + vertical (botões ao lado de Fonte)

### 3. APIs
- `GET /api/thumbnail/templates`
- `GET|PUT /api/projects/{id}/thumbnail`
- `POST /api/projects/{id}/thumbnail/upload`
- `POST /api/projects/{id}/thumbnail/generate`
- `GET|POST|DELETE /api/thumbnail/frames/*` (biblioteca de molduras)
- `PUT /api/projects/{id}/narration` — trim narração

### 4. Arquivos principais
| Área | Arquivos |
|------|----------|
| Molduras config | `config/thumbnail_frames.php` |
| Modelos/fontes | `config/thumbnail_templates.php` |
| Render molduras | `app/Services/Render/ThumbnailFrameDrawer.php` |
| Render capa | `app/Services/Render/ThumbnailRenderer.php` |
| Biblioteca user | `app/Services/ThumbnailFrameLibraryService.php` |
| API frames | `app/Http/Controllers/Api/ThumbnailFrameController.php` |
| UI | `resources/views/projects/editor.blade.php`, `resources/js/editor.js` |

## Migration
```bash
php artisan migrate
npm run build
```

## Pendente (branch 017+)
- Aplicar `trim_in`/`trim_out` no mix FFmpeg e no `PreviewAudioMixer`
- Arrastar handles de trim na timeline
- Probe automático de `source_duration` na importação de áudio

## Branch
`016` — baseada em `015` @ `4e952d7`
