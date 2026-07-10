# Handoff — Branch `026` (Image Studio Fase 10 + estabilização UX)

> Base: `025` — **branch estável para remover fundo, layouts e guias**

## Fase 10 — Electron pasta local / watch ✅
- IPC: `pickWatchFolder`, `watchFolder`, `readLocalFile`, `onFolderChanged`
- Monitora pasta e importa PNG/JPG/WebP/GIF automaticamente no canvas
- UI "Monitorar pasta de imagens" (somente desktop)

## Estabilização UX (commit pós-fase 10) ✅
- Remover fundo: arquivo da pasta **ou** imagem já selecionada no canvas
- Layouts prontos: redimensionam canvas + aplicam conteúdo; guias de corte/sangria visíveis
- Zoom restaurado (slider, botões, scroll) via wrapper CSS — não usa `canvas.setZoom()` nos handles
- Catálogo: ~158 formatos, elementos (formas + Bootstrap Icons), `@imgly/background-removal`
- Arquivos: `resources/js/imageStudio.js`, `editor.blade.php`, `config/image_studio.php`, `config/image_studio_icons.php`, `scripts/sync-bootstrap-icons.mjs`

---

## Roadmap Image Studio

| Fase | Branch | Entrega |
|------|--------|---------|
| 1 | 017 | Canvas, presets, export básico, rembg |
| 2 | 018 | Filtros |
| 3 | 019 | PSD camadas |
| 4 | 020 | PDF |
| 5 | 021 | Biblioteca → canvas |
| 6 | 022 | Templates prontos |
| 7 | 023 | Undo/redo |
| 8 | 024 | Grid/snap/alinhamento |
| 9 | 025 | Molduras thumbnail |
| 10 | 026 | Electron watch + UX estável (rembg, layouts, guias, zoom) |
| 11 | 027 | Próximas melhorias editor visual *(em andamento)* |

## Testar
```bash
npm run build
php artisan config:clear
php artisan serve   # ou npm run electron:dev
```

## Branch
`026` ← `025` — **estável (remover fundo + layouts OK)**  
Próxima: `027` — ver `HANDOFF_027.md`
