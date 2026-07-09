# Handoff — Branch `010` (Vídeos curtos + Central de downloads)

> Continuidade pós-009. Última atualização: 09/07/2026.

## O que esta branch entrega

### 1. Vídeos curtos nos slides (B-roll)
- Coluna `video_path` em `slides` (migration `2026_07_09_120001`)
- Busca de **vídeos curtos** via **Pexels** e **Pixabay** (tipo `video` na biblioteca)
- Importação com `MediaImportService::importVideo()`
- Upload local de vídeo nas propriedades do slide
- Preview com `<video>` no editor (loop mudo)
- Render FFmpeg: slides com vídeo geram segmento MP4 (loop/trim pela duração do slide)

### 2. Central de mídias / downloads unificada
- API: `GET /api/projects/{id}/downloads` (`ProjectDownloadCatalog`)
- Aba **Exportar**: tabela com **todos** os arquivos (MP4, ZIP, SRT, narração, thumb, PSD…)
- Operador **marca checkboxes** e clica **Baixar selecionados**

## Workflow Git (sempre ao fechar seção)
```bash
git add -A && git commit -m "..." && git push origin <branch-atual>
git checkout -b <proxima-branch>
```

## Como rodar
```bash
php artisan migrate
npm run build
php artisan serve
php artisan queue:work
```

## Chaves para vídeos
```env
PEXELS_API_KEY=...
PIXABAY_API_KEY=...
```

## Limitações conhecidas
- Texto sobre vídeo aparece no **preview** do editor; no render final o vídeo B-roll ainda não queima texto (só imagem estática compõe texto)
- Openverse/Unsplash não têm vídeo stock na API

## Próximos passos sugeridos (branch 011+)
- Transições reais (fade/xfade) no concat FFmpeg
- Texto queimado sobre clip de vídeo no render
- Projetos arquivados — listar/restaurar
