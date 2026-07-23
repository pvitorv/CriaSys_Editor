# PROMPT — Novo projeto a partir do Image Studio Kit

> **Instrução ao Agent:** leia este arquivo por completo, depois `DEPENDENCIAS.md`, `INVENTARIO.md` e o código em `source/`. Implemente um **novo produto web**, não continue o CriaSys Editor de slideshow.

---

## 1. Visão do produto

Ferramenta **web gratuita** para:

- criadores de conteúdo
- profissionais de **marketing de vendas**
- **designers iniciantes**

Objetivo de negócio:

1. Atrair público com a ferramenta útil e grátis.
2. Dentro da ferramenta: busca / recomendações / **links afiliados** nichados.
3. Vender (como afiliado) **pacotes de imagens e templates** (especialmente **PSD**) que o usuário abre e edita **nesta** ferramenta.
4. Nome e banco de dados podem ser **familiares** ao CriaSys (definir branding no kickoff; manter schema limpo).

**Núcleo técnico:** o Image Studio copiado deste kit (Fabric.js + Alpine + Laravel configs).

**Não é:** editor de vídeo, narrador TTS, timeline com áudio, render FFmpeg de slideshow.

---

## 2. Regras de produto (obrigatórias)

| Regra | Detalhe |
|-------|---------|
| Login | Usuário **só trabalha logado** |
| Persistência de arte | Trabalhos **NÃO** ficam armazenados no site |
| Fluxo | Trabalhar → **baixar export** → **limpar área** → próximo trabalho |
| UX | Avisos claros antes de limpar / sair sem baixar |
| Landing | Pública (SEO, afiliados, CTA) |
| Studio | Atrás de auth |

---

## 3. Formatos — seja transparente

| Prioridade | Formato | Papel |
|------------|--------|--------|
| **P0** | **PSD** | Único formato realista de **camadas de trabalho** (`ag-psd` já no JS) |
| **P0** | PNG, JPG, WebP | Import / export diário |
| **P1** | SVG | Elementos / vetores simples |
| **P1** | PDF | Export; módulo futuro “reduzir PDF” |
| **Não prometer** | Projeto nativo Canva, Affinity (`.af*`), Corel (`.cdr`) | Usuário traz **export**; packs afiliados podem ser “para Canva/Corel” no marketing, mas o site edita **PSD + rasters** |

---

## 4. Escopo MVP

1. Auth (registro / login).
2. Página `/studio` com o Image Studio (código em `source/resources/js/…` + configs + partials adaptados).
3. Catálogo de presets de **redes sociais** (já em `config/image_studio.php`).
4. Import PSD / imagens; texto; shapes; export PNG/JPG (+ PSD se já suportado).
5. Remover fundo (preferir `@imgly/background-removal` no browser; rembg Python opcional).
6. Botões **Baixar** + **Limpar workspace** (sem “salvar no servidor”).
7. Landing pública + CTA “Entrar / Começar grátis”.
8. Placeholder na UI para **Afiliados / Packs** (lista ou busca stub).

### Explicitamente FORA do MVP

- Vídeo, áudio, narração, legendas
- Google Drive (fase 2)
- Encurtador de links, compressão PDF avançada (fase 2+)
- Abrir `.cdr` / Canva nativo / Affinity nativo
- Electron pasta watch (pode voltar depois como app desktop)

---

## 5. Mini-slides / carrosséis (imagem + texto)

Além da arte única, planejar (MVP leve ou fase 1.5):

- Várias “páginas” / frames só com **imagem + texto**
- Presets: carrossel Instagram, stories sequenciais, etc.
- Export: ZIP de PNGs ou PDF multipágina
- **Sem** vídeo e **sem** áudio

O CriaSys tem slideshow completo com mídia — **não copie** esse módulo; reimplemente o mínimo em cima do canvas do Studio.

---

## 6. Roadmap futuro (mesmo público)

1. Upload do PC + **Google Drive** (OAuth) para puxar imagens  
2. Conversão de formatos de imagem  
3. Redução / compressão de PDF  
4. Encurtador de links  
5. Carrossel / mini-slides polido  
6. Afiliados: busca, nichos, produtos recomendados (packs PSD/templates)

Toda feature nova deve servir criadores / marketing / designers iniciantes.

---

## 7. Monetização / afiliados

- Ferramenta grátis = atrator de tráfego.
- Dentro do app logado: área de **packs e links afiliados** alinhados ao uso (templates PSD, elementos, estoque visual, cursos/design tools, etc.).
- Usuário compra pack (fora/afiliado) → importa PSD/PNG no Studio → edita → baixa.
- Não precisa hospedar os arquivos dos packs no MVP (links externos).

---

## 8. Arquitetura sugerida

```
Landing (público)
  → Auth
    → /studio  (Alpine + Fabric; estado só no browser)
         → Export download
         → Limpar canvas
    → /packs   (afiliados — stub no MVP)
```

- Backend Laravel: auth, catalog API, opcional rembg upload **temporário** (apagar após resposta).
- **Não** salvar `canvas JSON` em `projects` como no CriaSys (lá persistia em `designs/` e `settings`).
- Desacoplar `ImageStudioController` de `Project` / `pushThumbnail` / `pushLibrary`.

---

## 9. Como integrar o código deste kit

1. Copiar `source/config/image_studio*.php` → `config/` do novo app.
2. Adaptar `ImageStudioService` (catalog sim; saveDesign/loadDesign → memória ou remover).
3. Adaptar controller para rotas autenticadas sem `{project}` de slideshow.
4. Montar página Blade/`/studio` com partials; embutir catalog JSON (como `#criasys-image-studio-*` no Editor).
5. Criar entry JS que só carrega `imageStudioMethods` + Alpine (não o `editor.js` inteiro).
6. Instalar deps de `DEPENDENCIAS.md`; `npm run build`.
7. Rodar `icons:sync` se for usar Bootstrap Icons gerados.

Arquivos-fonte: ver `INVENTARIO.md`. Histórico de features: `source/handoffs/HANDOFF_017`…`027`.

---

## 10. Critérios de aceite do MVP

- [ ] Visitante vê landing; não edita sem login  
- [ ] Logado abre studio com presets de redes  
- [ ] Importa PNG e (se possível) PSD com camadas  
- [ ] Exporta PNG/JPG para o disco do usuário  
- [ ] Limpar workspace descarta o trabalho (nada reaparece ao recarregar, a menos que esteja só em memória da aba)  
- [ ] Zero vídeo/áudio no produto  
- [ ] Placeholder afiliados visível  
- [ ] README do novo repo explica formatos suportados com honestidade (PSD sim; Canva/Corel nativo não)

---

## 11. Tom e UX

- Direto, para iniciantes: poucos cliques, presets oficiais de redes, textos de ajuda curtos.
- Evitar jargão de “editor profissional Adobe”.
- Avisos de “baixe antes de limpar” em linguagem clara.

---

## 12. Origem / legalidade do kit

- Extraído por **cópia** do CriaSys Editor branch `040` (projeto do mesmo autor).
- Apagar a pasta `image-studio-kit` no CriaSys **não quebra** o Editor.
- Este prompt é a fonte da verdade do **novo** produto; o histórico longo do Editor de vídeo é irrelevante daqui pra frente.

---

## 13. Primeira mensagem sugerida ao Agent (novo repo)

```
Leia image-studio-kit/PROMPT_NOVO_PROJETO.md, DEPENDENCIAS.md e INVENTARIO.md.
Crie um app Laravel + Vite + Alpine com landing pública, auth obrigatória para /studio,
Image Studio baseado em source/ (PSD + presets redes, só imagem/texto),
sem persistir artes no servidor, com Baixar + Limpar, e stub de afiliados.
Não implemente vídeo, áudio nem TTS.
```

---

*Gerado em 2026-07-22 a partir do plano acordado no CriaSys Editor (Image Studio Kit).*
