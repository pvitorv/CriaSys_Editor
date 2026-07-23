# PROMPT — MarkCraft (família CriaSys)

> **Instrução ao Agent:** leia este arquivo por completo, depois `DEPENDENCIAS.md`, `INVENTARIO.md` e o código em `source/`. Implemente o produto **MarkCraft** — app web **novo**, não continue o CriaSys Editor de slideshow.

---

## 0. Identidade e ambiente local (obrigatório)

| Item | Valor |
|------|--------|
| **Nome do produto** | **MarkCraft** |
| **Família** | Produto da família **CriaSys** (marca irmã do Editor) |
| **URL** | **Subdomínio** do CriaSys (ex.: `markcraft.<dominio-criasys>`) — **não** domínio próprio separado |
| **Branding** | MarkCraft em destaque + selo/rodapé “CriaSys” |
| **Banco de dados** | `mark_craft` (MySQL/MariaDB — **já criado vazio** pelo dono antes do projeto) |
| **Usuário DB (local)** | `vitor` |
| **Senha DB (local)** | `Da1985790grs` — **somente desenvolvimento local**; o dono troca em produção |
| **`.env` local (referência)** | `DB_DATABASE=mark_craft` · `DB_USERNAME=vitor` · `DB_PASSWORD=Da1985790grs` |

Notas:

- Não criar o database no Agent se já existir vazio — só rodar migrations.
- Em produção: trocar senha; nunca reutilizar a senha local.
- App name / `APP_NAME=MarkCraft`; schema e código podem usar prefixo `mark_craft` / namespace `MarkCraft` onde fizer sentido.

---

## 1. Visão do produto

**MarkCraft** é uma ferramenta **web gratuita** (hub de utilitários + editor visual) para:

- criadores de conteúdo
- profissionais de **marketing de vendas**
- **designers iniciantes / aventureiros** (aprender fazendo, sem suite Adobe)

Objetivo de negócio:

1. Atrair público com ferramentas úteis e grátis (studio + utilitários) sob a marca MarkCraft / CriaSys.
2. Dentro da ferramenta: busca / recomendações / **links afiliados** nichados.
3. Vender (como afiliado) **pacotes de imagens e templates** (especialmente **PSD**) que o usuário abre e edita **nesta** ferramenta.
4. Monetizar também com **publicidade própria**, **ADS de terceiros**, **doações** (Pix/cartão a partir de R$ 2) e, no futuro, afiliados de **hospedagem** (ex.: Hostinger).
5. Permanecer **contido na família CriaSys** (subdomínio + branding cruzado).
6. Estudar com cuidado uma **área de membros** para doadores (comunidade sem risco indevido de direitos autorais).

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
| Landing | Pública (SEO, afiliados, CTA, **slots de ads**) |
| Studio | Atrás de auth |
| Ads | Layout já prevê espaços de **ADS** e **publicidade própria** (mesmo que vazios no início) |

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
9. **Menu / hub “Ferramentas”** (mesmo que só stubs + “em breve”) apontando para:
   - Encurtador de URL
   - Conversor de imagens
   - Conversor de PDF
   - Compressor de PDF
10. **Slots de publicidade** no layout (landing + área logada): ver seção 7b.
11. Tom e navegação pensados para **marketing** e **designers aventureiros** (atalhos claros, presets, copy sem jargão Adobe).
12. Página/modal **Apoiar / Doações** com copy da seção 7c (Pix e cartão a partir de R$ 2; gateway pode ser stub).

### Explicitamente FORA do MVP (implementação completa)

- Lógica real do encurtador / conversores / compressor (podem ser páginas stub)
- Google Drive (fase 2)
- Abrir `.cdr` / Canva nativo / Affinity nativo
- Electron pasta watch (pode voltar depois como app desktop)
- Vídeo, áudio, narração, legendas

---

## 5. Mini-slides / carrosséis (imagem + texto)

Além da arte única, planejar (MVP leve ou fase 1.5):

- Várias “páginas” / frames só com **imagem + texto**
- Presets: carrossel Instagram, stories sequenciais, etc.
- Export: ZIP de PNGs ou PDF multipágina
- **Sem** vídeo e **sem** áudio

O CriaSys tem slideshow completo com mídia — **não copie** esse módulo; reimplemente o mínimo em cima do canvas do Studio.

---

## 6. Roadmap futuro — suite de ferramentas (aceitáveis / prioritárias)

Implementar de verdade, uma a uma, **sempre alinhadas** a marketing e designers aventureiros:

| Ordem | Ferramenta | Notas |
|-------|------------|--------|
| 1 | Upload PC + **Google Drive** (OAuth) | Puxar imagens para o studio |
| 2 | **Conversor de imagens** | PNG ↔ JPG ↔ WebP ↔ (opcional) SVG rasterizado; lote simples |
| 3 | **Conversor de PDF** | PDF → imagens (páginas) e/ou imagens → PDF; útil para posts e apresentações leves |
| 4 | **Compressor de PDF** | Reduzir tamanho mantendo legibilidade (campanhas, e-books leves, propostas) |
| 5 | **Encurtador de URL** | Próprio (links `site/x/abc`) **ou** integração aceitável (ex.: Bitly/API própria); UTM opcional para marketers |
| 6 | Carrossel / mini-slides polido | Só imagem + texto |
| 7 | Afiliados (packs + **hospedagem**) | Packs PSD/templates; depois **Hostinger** e similares (site/domínio/e-mail) — separado da doação |
| 8 | Extras marketing (fase seguinte) | Gerador de UTM, checklist de formatos de rede, contador de caracteres de legenda, QR Code simples — só se couber no público |
| 9 | Área de membros (apoiadores) | Só após estudo de copyright; começar com badge/comunidade, **sem** dump de arquivos |

Critério de “aceitável”: gratuito ou freemium para o usuário final; rápido; sem exigir software instalado; privacidade (arquivos temporários apagados após download).

Toda feature nova deve servir criadores / marketing / designers aventureiros — **não** virar editor de vídeo.

---

## 7. Monetização / afiliados

- Ferramenta grátis = atrator de tráfego.
- Dentro do app logado: área de **packs e links afiliados** alinhados ao uso (templates PSD, elementos, estoque visual, cursos/design tools, etc.).
- Usuário compra pack (fora/afiliado) → importa PSD/PNG no Studio → edita → baixa.
- Não precisa hospedar os arquivos dos packs no MVP (links externos).
- **Hospedagem (futuro):** nicho forte para afiliados **Hostinger** e outras (site, e-mail, domínio) — quem cria arte muitas vezes precisa publicar. Tratar como recomendação comercial separada da mensagem de **doação** (não misturar os dois tons).

---

## 7b. Publicidade própria + ADS (obrigatório no layout desde o MVP)

Reservar **slots nomeados** no layout (mesmo vazios / com placeholder “Seu anúncio aqui”):

| Slot ID (sugerido) | Onde | Tipo |
|--------------------|------|------|
| `ad_landing_hero` | Landing, abaixo do hero ou lateral | ADS terceiros (AdSense/ equivalente) **ou** campanha própria |
| `ad_landing_mid` | Landing, meio da página | ADS / própria |
| `ad_app_sidebar` | Área logada (studio / ferramentas), coluna lateral | ADS / própria |
| `ad_app_top` | Barra superior da área logada | Banner próprio (promo packs afiliados) preferencial |
| `ad_app_between_tools` | Hub de ferramentas, entre cards | ADS / própria |
| `ad_packs_inline` | Página de packs/afiliados | Mistura ads + cards afiliados |

Regras:

1. **MVP:** componentes Blade/HTML com `data-ad-slot="..."` + config (`config/ads.php` ou `.env`) para ligar/desligar e escolher provedor.
2. Separar claramente:
   - **Publicidade própria** — banners internos (packs, afiliados, upsell).
   - **ADS** — rede de anúncios (Google AdSense ou similar); carregar script só se `ADS_ENABLED=true`.
3. Não deixar ads cobrirem o canvas do Studio (ruim para UX); preferir sidebar / landing / hub.
4. Respeitar login: landing pode ter mais ADS; área de trabalho prioriza utilidade + 1–2 slots discretos.
5. Documentar no README do novo repo onde trocar IDs de publisher / imagens próprias.

---

## 7c. Doações (Pix / cartão) + área de membros (estudar com cuidado)

### Objetivo

Manter o MarkCraft vivo: custos de servidor, ferramentas e evolução. Contribuição **opcional**, sem pressão.

### MVP (layout + copy)

- Página ou modal **“Ajude o MarkCraft a continuar”**
- Contribuição a partir de **R$ 2,00** via **Pix** e **cartão**
- Botão: `Contribuir a partir de R$ 2`
- Rodapé: `Feito com carinho na família CriaSys · Contribuições opcionais mantêm o projeto vivo`
- Integração de pagamento: stub no MVP (links Pix / gateway depois); não bloquear o uso gratuito

### Copy oficial (elegante — usar no site)

**Título:** Ajude o MarkCraft a continuar

> O MarkCraft é gratuito para quem cria, vende e experimenta. Manter servidores, ferramentas e melhorias tem custo.
>
> Se esta ferramenta te ajudou e você quiser que ela continue existindo, considere uma contribuição a partir de **R$ 2,00** — via **Pix** ou **cartão**. Qualquer valor faz diferença.
>
> Não é obrigatório. É um “obrigado” opcional de quem acredita no projeto.
>
> No futuro, queremos estudar uma **área de membros** para quem contribui — um espaço de troca entre criadores. Isso será feito com cuidado, respeitando direitos autorais: cada pessoa responde pelo material que compartilha; o MarkCraft não hospeda nem endossa conteúdo protegido sem autorização.

### Área de membros (roadmap — NÃO implementar dump de arquivos no MVP)

Ideia: doadores viram **membros** com badge / acesso a comunidade. **Estudar antes de abrir upload de arquivos**, para não gerar responsabilidade por violação de direitos autorais.

| Faça | Evite |
|------|--------|
| Fórum, dicas, presets **próprios**, links públicos | Upload livre de PSD/packs de terceiros |
| Termo: usuário declara ser dono ou ter licença | “Biblioteca compartilhada” sem moderação |
| Moderação, denúncia, remoção rápida | Dump anônimo de arquivos |
| Começar com **badge de apoiador** + links | Storage de arquivos de terceiros no dia 1 |

Frase segura: *“espaço de comunidade entre apoiadores — sem troca de arquivos protegidos por direitos autorais de terceiros.”*

---

## 8. Arquitetura sugerida

```
Landing (público)  [slots ads + CTA + link doações]
  → Auth
    → /studio          (Alpine + Fabric; estado só no browser)  [ad_app_sidebar opcional]
         → Export download
         → Limpar canvas
    → /ferramentas     (hub: encurtador, conversores, compressor — stubs no MVP)
    → /packs           (afiliados — stub no MVP)  [ad_packs_inline]
    → /apoiar          (doações Pix/cartão a partir de R$ 2 — copy §7c)
```

- Backend Laravel: auth, catalog API, opcional rembg upload **temporário** (apagar após resposta).
- **Não** salvar `canvas JSON` em `projects` como no CriaSys (lá persistia em `designs/` e `settings`).
- Desacoplar `ImageStudioController` de `Project` / `pushThumbnail` / `pushLibrary`.
- Módulos futuros de ferramentas em rotas `/ferramentas/{slug}` com upload temporário + download + purge.
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
- [ ] Hub **Ferramentas** com stubs: encurtador, conversor de imagens, conversor de PDF, compressor de PDF  
- [ ] Slots de **ADS** e **publicidade própria** no layout (IDs documentados; podem estar vazios)  
- [ ] Página/modal **Apoiar** com copy §7c (Pix/cartão a partir de R$ 2; gateway pode ser stub)  
- [ ] README do novo repo explica formatos (PSD sim; Canva/Corel nativo não) + ads + doações + aviso de copyright da futura área de membros  

---

## 11. Tom e UX

- Direto, para **marketing** e **designers aventureiros**: poucos cliques, presets oficiais de redes, textos de ajuda curtos.
- Evitar jargão de “editor profissional Adobe”; preferir “monte seu post”, “exporte e baixe”.
- Avisos de “baixe antes de limpar” em linguagem clara.
- Ferramentas utilitárias no mesmo visual do studio (uma família de produto, não sites soltos).

---

## 12. Origem / legalidade do kit

- Extraído por **cópia** do CriaSys Editor branch `040` (projeto do mesmo autor).
- Apagar a pasta `image-studio-kit` no CriaSys **não quebra** o Editor.
- Este prompt é a fonte da verdade do **novo** produto; o histórico longo do Editor de vídeo é irrelevante daqui pra frente.

---

## 13. Primeira mensagem sugerida ao Agent (novo repo)

```
Leia image-studio-kit/PROMPT_NOVO_PROJETO.md, DEPENDENCIAS.md e INVENTARIO.md.
Crie o app **MarkCraft** (família CriaSys, subdomínio) com Laravel + Vite + Alpine:
APP_NAME=MarkCraft, DB mark_craft / user vitor / senha local do prompt (seção 0),
landing pública, auth obrigatória para /studio,
Image Studio baseado em source/ (PSD + presets redes, só imagem/texto),
sem persistir artes no servidor, com Baixar + Limpar,
hub /ferramentas (stubs: encurtador URL, conversor imagens, conversor PDF, compressor PDF),
stub de afiliados, slots ADS + publicidade própria (seção 7b),
página Apoiar com doações Pix/cartão a partir de R$ 2 (copy seção 7c; gateway stub ok).
Não implemente vídeo, áudio nem TTS. Não crie o database — já existe vazio.
Não abra upload livre de arquivos na área de membros (copyright — ver §7c).
```

---

*Atualizado em 2026-07-22 — MarkCraft; doações; membros cautelosos; Hostinger/afiliados; utilitários; ads.*
*Origem: plano acordado no CriaSys Editor (Image Studio Kit).*