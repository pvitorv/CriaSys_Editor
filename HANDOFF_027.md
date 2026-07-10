# Handoff — Branch `027` (Image Studio Fase 11 — UX editor)

> Base: `026` — **branch de trabalho para próximas melhorias**

## Fase 11 — estabilização UX (início em 027)

Itens **estáveis na base `026`** (commit anterior):

- Remover fundo via arquivo **e** da imagem selecionada no canvas (`@imgly/background-removal` no cliente)
- Layouts prontos aplicam preset + conteúdo; canvas redimensiona ao formato do layout
- Guias de formato: contorno violeta (corte), sangria amarela tracejada, área segura em verticais
- Zoom via wrapper CSS (− / slider / + / 100% / Ajustar + scroll) sem quebrar hit-test dos handles
- Catálogo expandido de formatos (~158 presets) e elementos (formas + 176 ícones Bootstrap Icons)
- Script `npm run icons:sync` → `config/image_studio_icons.php` + `public/icons/bootstrap/`

## Pendente (trabalhar em `027`)

- [ ] Redimensionamento pelos cantos 100% confiável em todas as imagens
- [ ] Elementos: validar clique → canvas em todos os tipos (ícone SVG, formas, stickers)
- [ ] Preview visual mais claro ao trocar formato (fora só guias internas)
- [ ] Demais pedidos do editor visual (movimentação livre, zoom persistente, etc.)

## Testar base estável (`026`)

```bash
npm run build
php artisan config:clear
php artisan serve
```

1. Image Studio → upload imagem → remover fundo da seleção
2. Aplicar layout → ver guias e proporção correta
3. Trocar formato na coluna esquerda → canvas muda tamanho
4. Zoom slider + scroll → handles ainda redimensionam

## Branches

| Branch | Estado |
|--------|--------|
| `026` | **Estável** — remover fundo, layouts, guias, zoom, catálogo |
| `027` | **Atual** — próximas correções UX |
