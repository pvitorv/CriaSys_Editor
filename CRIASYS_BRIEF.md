================================================================================
                    CRIASYS_EDITOR — DOCUMENTO MESTRE DO PROJETO
================================================================================
Versão: 1.0
Data: 08/07/2026
Autor do conceito: Vitor
Uso: Briefing completo para desenvolvimento com agente de IA (Cursor Agent)
================================================================================
1. VISÃO GERAL
================================================================================
Nome do sistema: CriaSys_Editor
Objetivo:
Aplicativo 100% DESKTOP para uso pessoal (com arquitetura preparada para um
MVP distante no futuro). É um GERADOR DE SLIDESHOW NARRADO — não um editor de
vídeo pesado (não é CapCut, não é Premiere).
O usuário:
- Monta slides com imagens e textos
- Gera narração em português brasileiro (voz natural, gratuita de preferência)
- Adiciona trilha sonora
- Renderiza vídeos LOCALMENTE no PC
- Exporta versões para redes sociais (YouTube, Shorts, Reels, TikTok, etc.)
- Exporta PACOTE DE PROJETO para lapidação em Affinity, Premiere, Photoshop
Público: uso pessoal e equipe de amigos. Produto comercial só em futuro distante.
================================================================================
2. STACK TECNOLÓGICA
================================================================================
Backend/API .............. Laravel 12, PHP
Banco de dados ........... MySQL
Frontend interno ......... Blade + Alpine.js + JavaScript
App desktop .............. Electron
Render de vídeo .......... FFmpeg (embutido no Electron)
Fila de jobs ............. Laravel Queue (database driver no início)
Armazenamento ............ Disco local do usuário
REGRA IMPORTANTE:
- PHP/Laravel NÃO processam vídeo — apenas orquestram jobs, metadados e arquivos
- FFmpeg faz TODO processamento pesado (vídeo, áudio, legendas, resize)
- Electron é o app principal que o usuário abre
- Laravel roda como API/serviço local (php artisan serve ou processo no Electron)
================================================================================
3. CONFIGURAÇÃO DO BANCO (já feita manualmente pelo desenvolvedor)
================================================================================
Nome do banco: CriaSysEditor
Usuário: vitor
Senha: preenchida manualmente no .env pelo desenvolvedor
.env esperado:
  DB_CONNECTION=mysql
  DB_DATABASE=CriaSysEditor
  DB_USERNAME=vitor
  DB_PASSWORD=          <-- desenvolvedor preenche
================================================================================
4. ESTADO INICIAL DO PROJETO
================================================================================
- Laravel 12 já instalado SECO (sem pacotes extras, sem estrutura customizada)
- Banco MySQL já configurado no .env
- NÃO reinstalar Laravel — trabalhar a partir do que já existe
- Desenvolvedor instala Laravel manualmente ANTES de abrir o agente
================================================================================
5. ESCOPO FUNCIONAL COMPLETO
================================================================================
--------------------------------------------------------------------------------
5.1 GESTÃO DE PROJETOS
--------------------------------------------------------------------------------
- Criar, listar, duplicar, arquivar e excluir projetos
- Cada projeto contém: slides, roteiro, áudios, assets, presets, histórico
- Auto-save de rascunho
- Estrutura de pastas local:
  ~/CriaSys_Editor/projetos/{project_id}/
  ├── slides/
  ├── audio/
  ├── assets/
  ├── exports/
  ├── thumbs/
  └── project.json
--------------------------------------------------------------------------------
5.2 EDITOR DE SLIDES (SLIDESHOW)
--------------------------------------------------------------------------------
- Fila ordenada de slides (drag-and-drop)
- Cada slide suporta:
  * Imagem de fundo (upload ou biblioteca)
  * Título, subtítulo, corpo de texto
  * Posição e estilo básico (fonte, tamanho, cor, alinhamento)
  * Duração em segundos (manual ou automática pela narração)
  * Transição (fade, cut, slide simples)
- Preview leve (não render pesado em tempo real)
- Gerar THUMBNAIL a partir de slide escolhido ou primeiro slide
--------------------------------------------------------------------------------
5.3 ROTEIRO E NARRAÇÃO (TTS)
--------------------------------------------------------------------------------
- Campo de roteiro por slide ou roteiro contínuo com blocos
- Gerar narração em PORTUGUÊS BRASILEIRO
- Prioridade: solução GRATUITA e LOCAL
- Arquitetura de motor TTS PLUGÁVEL:
  Motor padrão (gratuito):
  - Edge TTS (vozes pt-BR-FranciscaNeural e pt-BR-AntonioNeural)
  - Alternativa local: Coqui XTTS (interface pronta, implementação opcional)
  Motores futuros (interface pronta, implementação depois):
  - ElevenLabs
  - OpenAI TTS
  Sincronização áudio ↔ slides:
  1. Gerar áudio do roteiro
  2. Medir duração real de cada trecho/bloco
  3. Ajustar automaticamente duração dos slides
  4. Permitir ajuste manual fino na timeline simples
--------------------------------------------------------------------------------
5.4 ÁUDIO E TRILHA SONORA
--------------------------------------------------------------------------------
- Importar música/efeitos locais
- Buscar áudio em bibliotecas royalty-free
- Mixagem via FFmpeg:
  * Narração como faixa principal
  * Trilha com volume reduzido (ducking simples)
  * Fade in/out na trilha
- Controles: volume narração, volume trilha, mudo
--------------------------------------------------------------------------------
5.5 LEGENDAS E TEXTOS
--------------------------------------------------------------------------------
Fase 1 (MVP):
- Texto fixo no slide
- Exportar legendas.srt sincronizado com timeline
Fase 2:
- Legendas queimadas no vídeo (burn-in) via FFmpeg
- Estilo básico (fonte, cor, posição)
Fase 3 (futuro):
- Legendas estilo redes sociais (palavra por palavra)
--------------------------------------------------------------------------------
5.6 BIBLIOTECAS DE MÍDIA
--------------------------------------------------------------------------------
Fontes gratuitas (integrar busca):
- Imagens: Pexels, Pixabay, Unsplash
- Vídeos: Pexels, Pixabay
- Áudio: Mixkit, Pixabay, Freesound
Regras de licenciamento (OBRIGATÓRIO para cada asset):
- source (pexels, pixabay, etc.)
- license_type (CC0, CC BY, etc.)
- requires_attribution (boolean)
- attribution_text
- original_url
- downloaded_at
- file_hash
Exibir aviso visual quando asset exige atribuição.
Gerar arquivo CREDITS.txt no export quando necessário.
Bibliotecas pagas:
- Import manual de pasta local "Biblioteca licenciada"
- Marcar assets como license_source: user_purchased
- Sem integração automática de APIs pagas no MVP
--------------------------------------------------------------------------------
5.7 EXPORTAÇÃO DE VÍDEO (REDES SOCIAIS)
--------------------------------------------------------------------------------
Render local via FFmpeg. Presets obrigatórios no MVP:
  Preset                  Proporção   Resolução     Uso
  youtube_landscape       16:9        1920x1080     YouTube
  youtube_shorts          9:16        1080x1920     Shorts
  instagram_reels         9:16        1080x1920     Reels
  instagram_stories       9:16        1080x1920     Stories
  tiktok                  9:16        1080x1920     TikTok
  instagram_feed_square   1:1         1080x1080     Feed
  thumbnail               16:9        1280x720      Thumb JPG
Cada preset aplica:
- resize/crop inteligente (centro por padrão)
- reposicionamento de textos se configurado por preset
- duração máxima configurável (avisar se exceder)
--------------------------------------------------------------------------------
5.8 EXPORTAÇÃO PARA APPS PROFISSIONAIS (DIFERENCIAL)
--------------------------------------------------------------------------------
Gerar pacote de projeto exportável:
  export_{project}_{timestamp}/
  ├── slides/              (001.png, 002.png, ...)
  ├── audio/
  │   ├── narracao.wav
  │   └── trilha.mp3
  ├── legendas.srt
  ├── thumbnail.jpg
  ├── timeline.json        (duração de cada slide, transições)
  ├── premiere.xml         (FCP7 XML para Premiere)
  ├── credits.txt          (atribuições de assets)
  └── README.txt           (instruções de import)
Compatibilidade alvo:
- Premiere Pro: PNG sequence + WAV + SRT + XML
- Photoshop: PNG por slide; thumb em JPG/PNG
- Affinity Photo/Designer: PNG/PSD quando possível
- Affinity Video/Publisher: pasta organizada + timeline.json
NÃO gerar formato nativo fechado (.prproj, .afphoto).
Usar pacote de assets padrão da indústria.
--------------------------------------------------------------------------------
5.9 FILA DE RENDER LOCAL
--------------------------------------------------------------------------------
- Jobs em background sem travar a UI
- Status: pending, processing, completed, failed
- Barra de progresso e log de erro
- Reprocessar job falho
- Laravel Queue + worker local
================================================================================
6. O QUE NÃO FAZER NO MVP
================================================================================
- Edição pesada de vídeo (cortes complexos, multicam, chroma key)
- Auto-post em redes sociais
- Multi-tenant / billing / assinaturas
- Render em nuvem
- Login complexo (usuário único local basta no início)
- Integração automática com bibliotecas pagas (Shutterstock, Envato API)
- Legendas palavra por palavra estilo TikTok (Fase 3)
================================================================================
7. ESTRUTURA TÉCNICA ESPERADA
================================================================================
--------------------------------------------------------------------------------
7.1 BACKEND LARAVEL — MÓDULOS
--------------------------------------------------------------------------------
app/
├── Models/
│   ├── Project.php
│   ├── Slide.php
│   ├── Asset.php
│   ├── AssetLicense.php
│   ├── Narration.php
│   ├── AudioTrack.php
│   ├── RenderJob.php
│   ├── ExportPreset.php
│   └── ExportPackage.php
├── Http/Controllers/Api/
│   ├── ProjectController.php
│   ├── SlideController.php
│   ├── AssetController.php
│   ├── MediaLibraryController.php
│   ├── NarrationController.php
│   ├── RenderController.php
│   └── ExportController.php
├── Services/
│   ├── Tts/
│   │   ├── TtsEngineInterface.php
│   │   ├── EdgeTtsEngine.php
│   │   └── CoquiTtsEngine.php (stub)
│   ├── MediaLibrary/
│   │   ├── PexelsService.php
│   │   ├── PixabayService.php
│   │   └── UnsplashService.php
│   ├── Render/
│   │   ├── FfmpegRenderService.php
│   │   ├── SlideshowBuilder.php
│   │   └── SocialPresetExporter.php
│   └── Export/
│       ├── ProjectPackageExporter.php
│       └── PremiereXmlGenerator.php
├── Jobs/
│   ├── GenerateNarrationJob.php
│   ├── RenderVideoJob.php
│   └── ExportPackageJob.php
└── Enums/
    ├── RenderStatus.php
    ├── LicenseType.php
    └── ExportPresetType.php
--------------------------------------------------------------------------------
7.2 ELECTRON — ESTRUTURA
--------------------------------------------------------------------------------
electron/
├── main.js
├── preload.js
├── ffmpeg/              (binário embutido por SO)
├── tts/                 (script Edge TTS ou wrapper)
└── ipc/
    ├── laravel.js       (iniciar/parar artisan serve)
    ├── render.js        (comunicação com fila)
    └── filesystem.js    (acesso a pastas do projeto)
--------------------------------------------------------------------------------
7.3 FRONTEND (BLADE + ALPINE)
--------------------------------------------------------------------------------
resources/views/
├── layouts/app.blade.php
├── dashboard.blade.php
├── projects/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── editor.blade.php
└── components/
    ├── slide-list.blade.php
    ├── slide-preview.blade.php
    ├── narration-panel.blade.php
    ├── media-library-modal.blade.php
    ├── timeline.blade.php
    ├── render-queue.blade.php
    └── export-panel.blade.php
resources/js/
├── app.js
├── editor.js
├── slide-manager.js
├── narration.js
├── media-library.js
└── render-status.js
================================================================================
8. BANCO DE DADOS — MIGRATIONS
================================================================================
projects
- id, name, description, status, settings (json), created_at, updated_at
slides
- id, project_id, order, title, subtitle, body_text, image_path
- text_style (json), duration_seconds, transition_type
- narration_text, created_at, updated_at
assets
- id, project_id (nullable), type (image/audio/video)
- file_path, file_hash, source, license_type
- requires_attribution, attribution_text, original_url
- metadata (json), created_at, updated_at
narrations
- id, project_id, engine, voice, full_script
- audio_path, duration_seconds, segments (json), status, created_at, updated_at
audio_tracks
- id, project_id, type (music/sfx), asset_id, file_path
- volume, start_at, ducking_enabled, created_at, updated_at
render_jobs
- id, project_id, preset, status, progress
- output_path, error_log, started_at, completed_at, created_at, updated_at
export_packages
- id, project_id, package_path, includes (json), status, created_at, updated_at
export_presets (seed)
- name, slug, width, height, aspect_ratio, max_duration, platform
================================================================================
9. PACOTES E DEPENDÊNCIAS (instalar após Laravel seco)
================================================================================
Composer:
  composer require laravel/sanctum
NPM (raiz Laravel + Electron):
  npm install alpinejs @alpinejs/sort
  npm install electron electron-builder concurrently wait-on cross-env --save-dev
  npm install axios
Ferramentas externas (documentar no README):
- FFmpeg — binários para Windows/Linux/Mac em electron/ffmpeg/
- Edge TTS — via edge-tts (Python) ou wrapper Node
- Python 3 (se usar edge-tts CLI) — opcional
================================================================================
10. VARIÁVEIS .ENV ADICIONAIS
================================================================================
APP_NAME=CriaSys_Editor
# APIs de mídia (desenvolvedor preenche quando tiver chaves)
PEXELS_API_KEY=
PIXABAY_API_KEY=
UNSPLASH_ACCESS_KEY=
# TTS
TTS_DEFAULT_ENGINE=edge
TTS_DEFAULT_VOICE=pt-BR-FranciscaNeural
# Paths locais
CRIASYS_PROJECTS_PATH=
CRIASYS_EXPORTS_PATH=
# FFmpeg
FFMPEG_PATH=
================================================================================
11. FLUXO DO USUÁRIO (UX) — TELAS
================================================================================
TELA 1 — Dashboard
- Listar projetos recentes
- Botões: Novo projeto, Abrir, Duplicar, Arquivar
TELA 2 — Novo projeto
- Nome, descrição, preset base (16:9 ou 9:16), template em branco
TELA 3 — Editor principal (layout 3 colunas)
  ┌──────────────┬─────────────────────┬──────────────┐
  │ Lista slides │ Preview do slide    │ Propriedades │
  │ (reordenar)  │ selecionado         │ texto/estilo │
  ├──────────────┴─────────────────────┴──────────────┤
  │ Timeline simples (duração por slide + áudio)    │
  ├─────────────────────────────────────────────────┤
  │ Abas: Roteiro | Áudio | Biblioteca | Exportar   │
  └─────────────────────────────────────────────────┘
TELA 4 — Aba Roteiro
- Texto por slide ou roteiro contínuo
- Selecionar voz (Francisca / Antonio)
- Botão "Gerar narração"
- Ouvir preview
- Botão "Sincronizar slides com áudio"
TELA 5 — Aba Áudio
- Trilha sonora (biblioteca ou importar)
- Volume, ducking, fade
TELA 6 — Aba Biblioteca
- Busca unificada (imagens/áudio)
- Filtro por fonte e licença
- Preview e inserir no slide ou trilha
- Badge: "Requer atribuição"
TELA 7 — Aba Exportar
- Checkboxes de presets sociais
- Gerar thumb
- Renderizar vídeos
- Exportar pacote Premiere/Affinity
- Fila de renders com progresso
================================================================================
12. FASES DE IMPLEMENTAÇÃO (ORDEM OBRIGATÓRIA)
================================================================================
--------------------------------------------------------------------------------
FASE 1 — FUNDAÇÃO (MVP USÁVEL)
--------------------------------------------------------------------------------
1.  Configurar estrutura de pastas e .env
2.  Criar migrations, models, seed de export_presets
3.  CRUD de projetos e slides
4.  UI do editor com Alpine (lista, preview, propriedades)
5.  Upload de imagens local
6.  Integrar busca Pexels (imagens) com metadados de licença
7.  Integrar Edge TTS para narração PT-BR
8.  Sincronização básica duração slide ↔ áudio
9.  FFmpeg: slideshow → MP4 (um preset 16:9)
10. Export thumb JPG
11. Fila de render local com status
CRITÉRIO DE PRONTO FASE 1:
Criar projeto, montar 5 slides, narrar, renderizar MP4 16:9 e thumb.
--------------------------------------------------------------------------------
FASE 2 — EXPORT SOCIAL E PACOTE PROFISSIONAL
--------------------------------------------------------------------------------
1.  Presets 9:16 (Reels/TikTok/Shorts) e 1:1
2.  Crop/resize por preset
3.  Mix trilha + narração com ducking
4.  Export legendas.srt
5.  Pacote de export (PNG sequence + WAV + SRT + timeline.json)
6.  Gerador premiere.xml
7.  credits.txt automático
8.  Integrar Pixabay e Unsplash
9.  Busca de áudio (Mixkit/Pixabay)
CRITÉRIO DE PRONTO FASE 2:
Um projeto gera MP4 em 3 formatos + pacote para Premiere.
--------------------------------------------------------------------------------
FASE 3 — ELECTRON E POLISH
--------------------------------------------------------------------------------
1.  Empacotar Laravel + frontend no Electron
2.  Embutir FFmpeg no Electron
3.  Iniciar/parar Laravel automaticamente
4.  IPC para filesystem e render
5.  Auto-save
6.  Atalhos de teclado básicos
7.  Logs de erro amigáveis
8.  Build instalador Windows (electron-builder)
CRITÉRIO DE PRONTO FASE 3:
App abre como desktop sem terminal manual.
--------------------------------------------------------------------------------
FASE 4 — EVOLUÇÃO (SOMENTE APÓS FASES 1–3 ESTÁVEIS)
--------------------------------------------------------------------------------
1.  Legendas burn-in
2.  PSD export por slide
3.  Coqui TTS local como alternativa
4.  Motores TTS pagos (interface)
5.  Templates de projeto
6.  Múltiplos usuários / equipe
7.  Legendas estilo TikTok
================================================================================
13. REQUISITOS DE QUALIDADE E CONVENÇÕES
================================================================================
- UI e mensagens em PORTUGUÊS BR
- Identificadores de código (variáveis, classes) em INGLÊS
- UI limpa, escura ou neutra, foco em produtividade
- Validação de inputs em todo formulário
- Logs de erro de render em render_jobs.error_log
- Não hardcodar paths — usar config e storage/
- Services desacoplados (TTS, FFmpeg, MediaLibrary, Export)
- Testes mínimos nos services críticos
================================================================================
14. README OBRIGATÓRIO AO FINAL
================================================================================
Gerar README.md com:
1.  O que é o CriaSys_Editor
2.  Requisitos (PHP, Node, FFmpeg, Python se necessário)
3.  Instalação passo a passo
4.  Configuração do .env e chaves de API
5.  Como rodar em desenvolvimento (Laravel + Electron)
6.  Como gerar build desktop
7.  Estrutura de pastas do projeto
8.  Como importar export no Premiere/Affinity/Photoshop
9.  Licenciamento de assets e credits.txt
10. Roadmap Fase 1–4
================================================================================
15. REGRAS PARA O AGENTE DE IA
================================================================================
1.  NÃO reinstalar Laravel — trabalhar sobre instalação existente
2.  NÃO alterar DB_PASSWORD — desenvolvedor preenche
3.  Implementar FASE POR FASE — confirmar Fase 1 antes de Fase 2
4.  Priorizar funcionar localmente no WINDOWS (SO principal)
5.  Commits pequenos e descritivos em português
6.  Ao terminar cada fase: listar o que foi feito, o que testar, pendências
7.  Se dependência externa falhar (API, TTS, FFmpeg), documentar fallback
8.  Manter escopo enxuto — não adicionar features fora deste documento
================================================================================
16. COMANDO INICIAL PARA O AGENTE (copiar ao abrir nova sessão)
================================================================================
Comece pela FASE 1, item 1: configure a estrutura base do CriaSys_Editor
sobre o Laravel 12 já instalado, crie as migrations, models, seed de presets,
layout base com Alpine, e o CRUD de projetos. Depois prossiga na ordem da
FASE 1 sem pular etapas.
Banco: CriaSysEditor | Usuário: vitor | Senha: já no .env do desenvolvedor.
================================================================================
17. CHECKLIST DE VALIDAÇÃO POR FASE (para o desenvolvedor testar)
================================================================================
FASE 1:
[ ] Laravel sobe sem erro (php artisan serve)
[ ] Migrations rodam (php artisan migrate)
[ ] Consigo criar um projeto
[ ] Consigo adicionar/remover/reordenar slides
[ ] Consigo fazer upload de imagem no slide
[ ] Consigo buscar imagem no Pexels e inserir no slide
[ ] Metadados de licença são salvos no asset
[ ] Consigo escrever roteiro e gerar narração PT-BR
[ ] Áudio de narração toca no preview
[ ] Slides sincronizam duração com o áudio
[ ] Consigo renderizar MP4 16:9 localmente
[ ] Thumb JPG é gerada
[ ] Fila de render mostra status (pending → completed)
FASE 2:
[ ] Export 9:16 (Reels/TikTok) funciona
[ ] Export 1:1 (feed) funciona
[ ] Trilha sonora mixa com narração (ducking)
[ ] legendas.srt é gerado corretamente
[ ] Pacote de export contém PNG + WAV + SRT + timeline.json
[ ] premiere.xml importa no Premiere sem erro grave
[ ] credits.txt lista assets que exigem atribuição
[ ] Busca Pixabay e Unsplash funcionam
[ ] Busca de áudio funciona
FASE 3:
[ ] App abre pelo ícone (Electron) sem terminal manual
[ ] Laravel inicia automaticamente ao abrir o app
[ ] FFmpeg embutido funciona no Electron
[ ] Auto-save funciona
[ ] Build gera instalador .exe para Windows
[ ] App funciona após instalação (não só em dev)
================================================================================
18. DECISÕES DE ARQUITETURA JÁ TOMADAS (NÃO REDISCUTIR NO MVP)
================================================================================
- 100% desktop (Electron), não web pública
- Slideshow narrado apenas, sem edição pesada
- Render 100% local no PC do usuário
- TTS gratuito como padrão (Edge TTS)
- Export profissional via PACOTE DE ASSETS, não formato nativo fechado
- Bibliotecas pagas via import manual, não API automática
- Uso pessoal primeiro; caminho aberto para MVP futuro
- MySQL com banco CriaSysEditor, usuário vitor
================================================================================
19. COMO USAR ESTE DOCUMENTO
================================================================================
1. Instalar Laravel 12 seco
2. Configurar .env (CriaSysEditor, vitor, senha)
3. Salvar este arquivo na raiz do projeto como CRIASYS_BRIEF.md
4. Abrir Cursor no modo AGENT
5. Colar a seção 16 (Comando inicial) OU pedir: "Leia CRIASYS_BRIEF.md e comece"
6. Agente implementa Fase 1 item por item
================================================================================
                              FIM DO DOCUMENTO
================================================================================