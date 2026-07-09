# Handoff — Branch `012` (Créditos/licenças unificados + busca PT + licenças pagas)

> Continuidade pós-011. Última atualização: 09/07/2026.

## O que esta branch entrega

### 1. Publicação automática — toda mídia com crédito ou licença
- `MediaAttribution` — textos oficiais por plataforma (Openverse, Pexels, Pixabay, Mixkit, Unsplash)
- `ProjectPublishAutoSyncService` — regenera descrições após import, upload ou render
- `ProjectAttributionCatalog` — só materiais **usados nos slides/trilha**; bloco `CRÉDITOS E LICENÇAS`
- `AssetAttributionRepairService` — repara imports antigos sem `source`/`attribution_text`
- UI Exportar: painel unificado; descrições YouTube/TikTok/Instagram com créditos no final
- Removida mensagem enganosa «Nenhum material de terceiros registrado»

### 2. Busca de mídia em português
- `MediaSearchQueryTranslator` + `config/media_search_pt_en.php`
- Termos PT traduzidos automaticamente para EN nas APIs (Openverse, Mixkit, etc.)

### 3. Render e preview
- `SlideshowBuilder` — transições xfade/fade/slide no concat FFmpeg
- `FfmpegRenderService` — fix `-shortest` truncando vídeo
- Editor: botão **Reproduzir slideshow** com timing por `duration_seconds`

### 4. Licenças de assinatura paga (Envato, Storyblocks, Artgrid, custom)
- Migration `2026_07_09_180001` — `project_stock_licenses` + colunas em `assets`
- `ProjectStockLicense` + API CRUD + `apply-local` (vincular uploads já usados)
- Upload manual herda licença padrão do projeto CriaSys
- Texto gerado: `Licensed via Envato Elements. Project: «nome» — Item: arquivo`
- UI na aba **Exportar** → painel «Licença de assinatura»
- `ENVATO_API_TOKEN` documentado no `.env.example` (integração API futura)

## Workflow Git (sempre ao fechar seção)
```bash
git add -A && git commit -m "..." && git push origin <branch-atual>
git checkout -b <proxima-branch>
git push -u origin <proxima-branch>
```

## Como rodar
```bash
php artisan migrate
npm run build
php artisan serve
php artisan queue:work
```

## Chaves úteis
```env
PEXELS_API_KEY=          # opcional — vídeos/imagens
PIXABAY_API_KEY=         # opcional
ENVATO_API_TOKEN=        # opcional — futuro; cadastro local de licença já funciona
```

## Fluxo Envato (operador)
1. Na Envato: crie projeto «Nome do vídeo» (uma vez por publicação)
2. No CriaSys (Exportar): cadastre a mesma licença como padrão
3. Baixe arquivos da Envato e faça upload no editor — crédito entra sozinho
4. Se já tinha uploads: **Vincular uploads já usados**

## Limitações conhecidas
- Registro do projeto na Envato na hora de **publicar** o vídeo ainda é manual (regra da plataforma)
- API Envato para auto-registro de projeto não implementada (só token preparado)

## Próximos passos sugeridos (branch 013+)
- Export `.txt` com lista de itens Envato do projeto (registro em lote na plataforma)
- Integração API Envato (se token disponível)
- Projetos arquivados — listar/restaurar
- Texto queimado sobre clip de vídeo no render final (B-roll)
