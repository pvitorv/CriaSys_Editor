# Acoplamentos no CriaSys Editor (referência)

## Como o Studio entra no Editor hoje

1. `ProjectWebController::editor` injeta `$imageStudioCatalog` na view.
2. `editor.blade.php` embute JSON em `#criasys-image-studio-*`.
3. `editor.js` faz `...imageStudioMethods()` de `imageStudio.js`.
4. Aba `image_studio` chama `initImageStudio()` ao abrir.
5. Partials: `image_studio_workspace` + `image_studio_sidebar_panels`.

## Config env

```php
// config/criasys.php
'image_studio' => [
    'rembg_python' => env('REMBG_PYTHON'),
],
```

## Capacidades já implementadas (Fases 017–027)

- Canvas Fabric.js, presets redes, texto/fontes, shapes, stickers, ícones
- Filtros, undo/redo, grid/snap, templates
- Export PNG/JPG/SVG/JSON/PSD/PDF
- Remover fundo (browser imgly + Python rembg)
- Electron: monitorar pasta local (opcional; não é MVP web)

## O que o novo app deve IGNORAR

- Slideshow, slides com vídeo, narração, trilha, SFX
- Publish Kit / Project Bundle / quota online
- Busca Mixkit vídeo
