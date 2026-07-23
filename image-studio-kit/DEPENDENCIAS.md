# Dependências e acoplamentos — Image Studio Kit

## npm (obrigatórias para o canvas)

Do `package.json` do CriaSys Editor:

```json
{
  "@imgly/background-removal": "^1.7.0",
  "ag-psd": "^31.0.2",
  "alpinejs": "^3.15.12",
  "@alpinejs/sort": "^3.15.12",
  "bootstrap-icons": "^1.13.1",
  "fabric": "^6.9.1",
  "jspdf": "^4.2.1"
}
```

Scripts úteis:

```bash
npm run icons:sync   # node scripts/sync-bootstrap-icons.mjs
```

## PHP / Laravel (origem)

- Controller: `ImageStudioController`
- Services: `ImageStudioService`, `BackgroundRemovalService`
- Configs: `image_studio.php`, `image_studio_fonts.php`, `image_studio_icons.php`, `image_studio_shapes.php`, `image_studio_stickers.php`
- Env opcional: `REMBG_PYTHON` (ver `config/criasys.php` → `image_studio.rembg_python`)

## Python (opcional — remover fundo server-side)

- `scripts/remove-background.py` (rembg)
- Alternativa no browser: `@imgly/background-removal` (já no JS)

## Acoplamentos a REMOVER / REESCREVER no novo produto

O código atual assume o **CriaSys Editor**:

| Acoplamento | Onde | No novo produto |
|-------------|------|-----------------|
| `Project` model + `projects/{id}/image-studio` | Controller, Service | Workspace de usuário logado **sem** slideshow; ou sessão efêmera |
| Salvar design em `project.settings['image_studio']` / pasta `designs/` | `ImageStudioService` | **Não persistir artes** — só export download; limpar canvas |
| `pushThumbnail` / `pushLibrary` | Controller | Remover ou substituir por “baixar PNG” |
| `editor.js` mixin `imageStudioMethods()` | `resources/js/editor.js` | App Alpine/página `/studio` própria |
| `editor.blade.php` + JSON embutido `#criasys-image-studio-*` | View | Landing + studio autenticado |
| Electron `pickWatchFolder` | `imageStudio.js` | MVP web: upload/drag-drop; Drive depois |
| `AssetController::formatAsset` | pushLibrary | Não levar |
| Thumbnail frames / `framePreview` | Controller | Opcional; só se fizer sentido para redes |

## Stack sugerida do novo site

- Laravel 11/12 + Vite + Alpine + Tailwind (familiar ao código)
- Auth (Breeze/Fortify/simples) — **obrigatório** para usar o studio
- Storage: só conta/usuário; **designs não ficam no servidor**
- Domínio/nome: pode ser familiar ao CriaSys (definir branding)

## Formatos

| Formato | Papel |
|--------|--------|
| PSD | Camadas de trabalho (`ag-psd`) — prioridade |
| PNG/JPG/WebP | Import/export diário |
| SVG | Elementos |
| PDF | Export (`jspdf`) / módulo “reduzir PDF” futuro |
| Canva / Affinity / Corel nativos | **Não suporte** — usuário traz export |
