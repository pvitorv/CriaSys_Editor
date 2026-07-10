# Handoff — Branch `028` (Image Studio — próxima fase)

> Base: `027` — tipografia e ícones entregues

## Herdado da `027` (OK)

- 91 fontes + 62 ícones no painel Texto & ícones
- Negrito/itálico/cor/contorno no texto selecionado
- Catálogo PHP embutido na view (lista sempre visível)

## Pendente / backlog

- [ ] **Molduras no canvas** — overlay HTML ainda não visível para o usuário; revisão dedicada
- [ ] Redimensionamento pelos cantos 100% confiável
- [ ] Elementos SVG/formas → canvas (validar todos os tipos)
- [ ] Mais ícones / fontes Google sob demanda
- [ ] Demais pedidos do editor visual

## Comandos

```bash
npm run build
php artisan view:clear
php artisan serve
```

## Branch

| Branch | Estado |
|--------|--------|
| `027` | Concluída — tipografia & ícones |
| `028` | **Atual** — próximas melhorias Image Studio |
