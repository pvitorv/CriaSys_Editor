# Handoff — Branch `017` (próxima fase)

> Continuidade pós-016. Criada: 10/07/2026.

## Herança da 016
- Timeline com cortes (marcas In/Out) — trim ainda não aplicado no FFmpeg/preview de áudio
- Thumbnails completos: plataformas, modelos, molduras, biblioteca pessoal, slide/vídeo, alinhamento, destaque/fundo

## Sugestões para 017
1. **FFmpeg respeitar trim** — `trim_in`/`trim_out` na exportação e no preview de áudio
2. **PreviewAudioMixer** — cortes de narração/trilhas/FX na timeline
3. **Handles de trim** arrastáveis na timeline (UX)
4. **Probe `source_duration`** automático ao importar áudio

## Setup
```bash
git checkout 017
composer install
npm install && npm run build
php artisan migrate
```

## Branch
`017` — baseada em `016`
