# Handoff — Branch `027` (Image Studio — tipografia & ícones)

> Base: `026` · **concluída e mergeável**

## Entregue na `027`

### Texto & tipografia (Image Studio)

- Catálogo **91 fontes**: sistema (Windows), Google Fonts, Font Awesome / Material como fontes
- Config `config/image_studio_fonts.php` + loader `resources/js/imageStudioTextFonts.js`
- Painel **Texto & ícones**: negrito, itálico, sublinhado, tachado, cor, contorno, sombra, alinhamento
- Texto editável no canvas (`IText`) — duplo-clique
- **Lista de fontes renderizada no PHP** (não depende de Alpine/API) — corrige lista vazia
- Catálogo embutido na página via `<script type="application/json">` + seed no `init()`

### Ícones como fonte

- **62 ícones** (Font Awesome solid/brands + Material Symbols)
- Grade renderizada no PHP; clique → `imageStudioAddIconGlyphBySlug()`
- CDN Font Awesome + Material Symbols no editor

### Backend

- `ImageStudioService::fontCatalog()` — merge thumbnail + Google + ícones
- `ProjectWebController@editor` — passa `$imageStudioCatalog` para a view
- API `/api/image-studio/catalog` com fontes/ícones expandidos

### Molduras (tentativas — **não estável visualmente**)

- Overlay HTML `<img>` (mesmo padrão Thumbnail), export composto
- **Usuário confirmou que ainda não aparece no canvas** — não priorizar até Opus/revisão dedicada

## Testar o que funciona

```bash
npm run build
php artisan view:clear
php artisan config:clear
php artisan serve
```

1. Image Studio → **Texto & ícones** → ver **91 fontes** na lista
2. Selecionar Montserrat/Roboto → **+ Adicionar** → duplo-clique para editar
3. Clicar no texto no canvas → **B** / **I** / cor
4. Rolar ícones → clicar estrela/coração/YouTube → aparece no canvas

## Branches

| Branch | Estado |
|--------|--------|
| `026` | Estável — rembg, layouts, guias, zoom |
| `027` | **Concluída** — tipografia, ícones, catálogo fontes |
| `028` | **Próxima** — continuar Image Studio |
