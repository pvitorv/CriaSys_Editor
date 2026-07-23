# Handoff — Branch `017` (Image Studio + FFmpeg trim)

> Continuidade pós-016. Criada: 10/07/2026.

## Entregue nesta branch (Fase 1 — Image Studio)

### Nova aba **Image Studio** (estilo Canva)
- **40+ presets** — Instagram, Facebook, LinkedIn, X, YouTube, TikTok, Pinterest, WhatsApp, banners web, stories, impressão
- **Canvas Fabric.js** — camadas, texto, retângulo, círculo, upload de imagem
- **Painel de camadas** — visibilidade, lock, ordem, excluir
- **Fundo do canvas** — cor + transparência %
- **Opacidade** por objeto selecionado
- **Auto-save** do design JSON em `projetos/{id}/designs/{preset}.json`
- **Export:** PNG, JPG, SVG, JSON (projeto reeditável)
- **Remover fundo** — Python `rembg` local (`scripts/remove-background.py`)
- **Integração CriaSys:**
  - Enviar para **Thumbnail** (plataforma atual)
  - Salvar na **biblioteca** do projeto (tabela `assets`)

### APIs novas
| Método | Rota |
|--------|------|
| GET | `/api/image-studio/catalog` |
| GET | `/api/projects/{id}/image-studio?preset=` |
| PUT | `/api/projects/{id}/image-studio` |
| POST | `/api/projects/{id}/image-studio/export` |
| POST | `/api/projects/{id}/image-studio/remove-background` |
| POST | `/api/projects/{id}/image-studio/push-thumbnail` |
| POST | `/api/projects/{id}/image-studio/push-library` |

### Arquivos principais
| Área | Arquivo |
|------|---------|
| Presets | `config/image_studio.php` |
| Backend | `app/Services/ImageStudio/ImageStudioService.php` |
| Remover fundo | `app/Services/ImageStudio/BackgroundRemovalService.php` |
| API | `app/Http/Controllers/Api/ImageStudioController.php` |
| Canvas UI | `resources/js/imageStudio.js` (Fabric.js 6) |
| Aba | `resources/views/projects/editor.blade.php` |

### Setup rembg (remover fundo)
```bash
pip install rembg pillow
# .env opcional:
REMBG_PYTHON=C:\Python311\python.exe
```

### Build
```bash
npm install
npm run build
```

---

## Herança da 016
- Timeline com cortes (marcas In/Out) — trim ainda não no FFmpeg
- Thumbnails completos (modelos, molduras, biblioteca pessoal)

## Roadmap Fase 2+ (Image Studio)
- [ ] Filtros avançados (blur, brilho, contraste, vinheta) com transparência
- [ ] Export **PSD** camadas (Photoshop/Affinity)
- [ ] Export **PDF** (CorelDRAW/Affinity)
- [ ] Arrastar da biblioteca de mídia direto no canvas
- [ ] Templates prontos (posts, stories, ads)
- [ ] Histórico undo/redo
- [ ] Alinhamento inteligente / grids / snap
- [ ] Molduras do catálogo thumbnail no canvas
- [ ] Electron: atalhos de pasta local / watch de arquivos

## Roadmap paralelo
- [ ] FFmpeg respeitar `trim_in`/`trim_out`
- [ ] PreviewAudioMixer com cortes
- [ ] Handles de trim arrastáveis na timeline

## Branch
`017` — baseada em `016` @ `c11214b`
