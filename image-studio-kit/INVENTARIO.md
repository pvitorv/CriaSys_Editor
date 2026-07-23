# Inventário — arquivos copiados

Espelho em `source/` (mesmos caminhos relativos ao root do CriaSys).

## Backend

- `app/Http/Controllers/Api/ImageStudioController.php`
- `app/Services/ImageStudio/ImageStudioService.php`
- `app/Services/ImageStudio/BackgroundRemovalService.php`

## Config

- `config/image_studio.php`
- `config/image_studio_fonts.php`
- `config/image_studio_icons.php`
- `config/image_studio_shapes.php`
- `config/image_studio_stickers.php`

## Frontend

- `resources/js/imageStudio.js` (engine Fabric + mixin Alpine)
- `resources/js/imageStudioShapes.js`
- `resources/js/imageStudioTextFonts.js`
- `resources/views/projects/partials/image_studio_workspace.blade.php`
- `resources/views/projects/partials/image_studio_sidebar_panels.blade.php`

## Scripts

- `scripts/sync-bootstrap-icons.mjs`
- `scripts/remove-background.py`

## Handoffs (histórico Image Studio)

- `HANDOFF_017` … `HANDOFF_027` em `source/handoffs/`

## Snippets (referência, não são o app)

- `snippets/routes_api.php` — rotas originais
- `snippets/ACOPLAMENTOS.md` — pontos de integração no Editor

## NÃO copiado de propósito

- `editor.js` / `editor.blade.php` inteiros (slideshow + TTS + mídia)
- Electron main/IPC (exceto menção ao watch de pasta)
- Narration, TTS, FFmpeg render, Mixkit, Media library vídeo
- `ThumbnailRenderer` / módulo Thumbnail completo (só havia ponte `pushThumbnail`)
