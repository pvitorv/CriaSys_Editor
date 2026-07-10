# Handoff — Branch `026` (Image Studio Fase 10 — Electron pasta local)

> Base: `025`

## Fase 10 — Electron pasta local / watch ✅
- IPC: `pickWatchFolder`, `watchFolder`, `readLocalFile`, `onFolderChanged`
- Monitora pasta e importa PNG/JPG/WebP/GIF automaticamente no canvas
- UI "Monitorar pasta de imagens" (somente desktop)

---

## Roadmap Image Studio — COMPLETO (Fases 1–10)

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
| 10 | 026 | Electron watch pasta |

## Testar
```bash
npm run build
php artisan serve   # ou npm run electron:dev
```

## Branch
`026` ← `025` — **branch final para testes**
