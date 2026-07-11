# Modos Desktop e Online — CriaSys Editor

## Visão geral

O CriaSys Editor roda no **mesmo código** em dois modos, controlados por `CRIASYS_DEPLOYMENT` no `.env`:

| | Desktop (Electron) | Online (SaaS) |
|---|-------------------|---------------|
| Variável | `CRIASYS_DEPLOYMENT=desktop` | `CRIASYS_DEPLOYMENT=online` |
| Projetos ativos | Ilimitados (limite = disco) | **1 por usuário** |
| Continuidade | Projetos ficam no PC | Exporte e **exclua** para o próximo |
| Importar projeto | Dashboard → Importar ZIP | Após excluir o atual |
| Duplicar projeto | Sim | Não |

## Fluxo online (1 projeto por vez)

1. Crie ou importe **um** projeto
2. Edite, renderize e gere exports na aba **Exportar**
3. Baixe:
   - **Publish Kit** — descrições, checklist, vídeos renderizados
   - **Bundle completo** — backup portátil para importar depois
4. Clique em **Marcar como exportado**
5. No **dashboard**, exclua o projeto
6. Crie ou importe o próximo

## Publish Kit vs Bundle

| Arquivo | Conteúdo |
|---------|----------|
| `publish_kit_*.zip` | Descrições por plataforma, CHECKLIST.md, vídeos prontos, thumbnail |
| `bundle_*.zip` | Projeto completo (slides, áudio, assets, DB JSON) para retomar edição |

## Perfil de creator (CTAs)

Em **Minha conta → Perfil de creator**, preencha links opcionais (YouTube, Instagram, TikTok, site).  
Eles entram **automaticamente** nas descrições de publicação — só aparecem se preenchidos.

## Descrições editáveis

Na aba Exportar, cada plataforma pode ser editada manualmente. Use **Restaurar automática** para voltar ao texto gerado com créditos e CTAs.

## Configuração

```env
CRIASYS_DEPLOYMENT=desktop
CRIASYS_ONLINE_MAX_ACTIVE_PROJECTS=1
```

No Electron portable, `desktop` é definido automaticamente em `electron/portable-env.js`.

## API útil

- `GET /api/deployment` — modo atual
- `POST /api/projects/{id}/publish-kit` — gera Publish Kit
- `POST /api/projects/{id}/export-bundle` — gera bundle portátil
- `POST /api/projects/import-bundle` — importa ZIP
- `POST /api/projects/{id}/mark-exported` — libera exclusão consciente (online)
- `PUT /api/creator-profile` — links do creator

## Migração

Após atualizar o código, execute:

```bash
php artisan migrate
```

Isso adiciona a coluna `creator_profile` na tabela `users`.
