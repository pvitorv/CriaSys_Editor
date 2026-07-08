================================================================================
                    CRIASYS_EDITOR — DOCUMENTO MESTRE DO PROJETO
================================================================================
Versão: 1.1
Data: 08/07/2026
Autor do conceito: Vitor
Uso: Briefing completo para desenvolvimento com agente de IA (Cursor Agent)
Nota v1.1: Adicionada autenticação multi-usuário com admin UserDev (branch 004)
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
- Cada projeto pertence a UM usuário (user_id) — isolamento total
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
--------------------------------------------------------------------------------
5.10 AUTENTICAÇÃO E MULTI-USUÁRIO
--------------------------------------------------------------------------------
Login e senha obrigatórios para acessar o sistema.
Recuperação de senha via e-mail (link de reset).
Cadastro de novos usuários (self-service).
Cada usuário possui dados isolados — projetos, slides, assets e exports
pertencem exclusivamente ao usuário logado (campo user_id em projects).
Login aceita USUÁRIO ou E-MAIL + senha.
Conta pausada não consegue acessar o sistema (middleware active).
Troca de e-mail e senha (tela Minha conta):
- Exige senha atual para confirmar alteração
- Alternativa: fluxo "Esqueci minha senha" por e-mail
Administrador padrão:
- Username: UserDev (desenvolvedor — Vitor)
- E-mail: pontodeimpacto790@gmail.com
- Configurável via .env (ADMIN_USERNAME, ADMIN_EMAIL, ADMIN_PASSWORD)
- Seed: php artisan db:seed --class=AdminUserSeeder
Poderes do administrador (painel /admin/users):
- Listar todos os usuários
- Pausar / reativar conta de qualquer usuário
- Excluir usuário (exceto admins e a própria conta)
- Enviar mensagem de ALERTA para qualquer usuário
Alertas ao usuário:
- Banner amarelo no topo da aplicação
- Tabela user_alerts (from_user_id, to_user_id, subject, message, read_at)
- Usuário pode dispensar (marcar como lido)
Projetos existentes sem dono são atribuídos ao UserDev no seed.
Branch Git: 004
--------------------------------------------------------------------------------
5.11 DISTRIBUIÇÃO PORTÁTIL MULTI-PLATAFORMA (branch 005)
--------------------------------------------------------------------------------
App copiável via pendrive ou download — executável único por SO.
Plataformas alvo:
- Windows 10/11 (x64): .exe Portable (sem instalar) + Setup NSIS
- Linux (x64): AppImage + .deb
- macOS (Intel + Apple Silicon): .dmg + .zip
Modo portátil (empacotado):
- NÃO exige MySQL — SQLite em CriaSysData/database/criasys.sqlite
- Dados graváveis ao lado do executável: pasta CriaSysData/
  (projetos, exports, storage, secrets.json, app.key)
- PHP + FFmpeg embutidos em resources/php e resources/ffmpeg
- Primeira execução: migrate + seed UserDev automaticamente
- secrets.json editável no pendrive (login/senha admin)
Modo desenvolvimento (electron:dev):
- Usa .env local + MySQL Laragon (comportamento anterior)
Build:
- npm run electron:build:win | :linux | :mac | :all
- npm run portable:prepare (verifica runtimes)
Pendrive: copiar pasta do build + CriaSysData/ com os dados do usuário
Branch Git: 005
================================================================================
6. O QUE NÃO FAZER NO MVP
================================================================================
- Edição pesada de vídeo (cortes complexos, multicam, chroma key)
- Auto-post em redes sociais
- Billing / assinaturas / cobrança por usuário
- Render em nuvem
- Integração automática com bibliotecas pagas (Shutterstock, Envato API)
- Legendas palavra por palavra estilo TikTok (Fase 4)
- OAuth social (Google/Facebook login) — apenas login/senha local
================================================================================
7. ESTRUTURA TÉCNICA ESPERADA
================================================================================
--------------------------------------------------------------------------------
7.1 BACKEND LARAVEL — MÓDULOS
--------------------------------------------------------------------------------
app/
├── Models/
│   ├── User.php
│   ├── UserAlert.php
│   ├── Project.php
│   ├── Slide.php
│   ├── Asset.php
│   ├── Narration.php
│   ├── AudioTrack.php
│   ├── RenderJob.php
│   ├── ExportPreset.php
│   └── ExportPackage.php
├── Http/Controllers/
│   ├── Auth/
│   │   ├── LoginController.php
│   │   ├── RegisterController.php
│   │   ├── ForgotPasswordController.php
│   │   └── ResetPasswordController.php
│   ├── Admin/
│   │   └── UserController.php
│   ├── ProfileController.php
│   └── Api/
│       ├── AlertController.php
│       ├── ProjectController.php
│       ├── SlideController.php
│       ├── AssetController.php
│       ├── MediaLibraryController.php
│       ├── NarrationController.php
│       ├── RenderController.php
│       └── ExportController.php
├── Http/Middleware/
│   ├── EnsureUserIsActive.php
│   └── EnsureUserIsAdmin.php
├── Policies/
│   └── ProjectPolicy.php
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
├── layouts/
│   ├── app.blade.php
│   └── guest.blade.php
├── auth/
│   ├── login.blade.php
│   ├── register.blade.php
│   ├── forgot-password.blade.php
│   └── reset-password.blade.php
├── profile/
│   └── edit.blade.php
├── admin/users/
│   └── index.blade.php
├── dashboard.blade.php
├── projects/
│   ├── create.blade.php
│   └── editor.blade.php
resources/js/
├── app.js
├── editor.js
├── alerts.js
└── bootstrap.js
================================================================================
8. BANCO DE DADOS — MIGRATIONS
================================================================================
users
- id, name, username (unique), email (unique), password
- is_admin (boolean), status (active/paused)
- email_verified_at, remember_token, created_at, updated_at
user_alerts
- id, from_user_id (nullable), to_user_id, subject, message
- read_at, created_at, updated_at
projects
- id, user_id (FK), name, description, status, settings (json)
- created_at, updated_at
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
FFPROBE_PATH=
# Administrador principal (seed UserDev)
ADMIN_USERNAME=UserDev
ADMIN_EMAIL=
ADMIN_PASSWORD=
# E-mail (recuperação de senha)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
================================================================================
11. FLUXO DO USUÁRIO (UX) — TELAS
================================================================================
TELA 0 — Autenticação (guest)
- Login (usuário ou e-mail + senha)
- Cadastro (nome, usuário, e-mail, senha)
- Esqueci minha senha → e-mail com link de reset
- Redefinir senha (via link do e-mail)
TELA 1 — Dashboard (auth required)
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
TELA 8 — Minha conta (/profile)
- Alterar e-mail (exige senha atual)
- Alterar senha (exige senha atual)
- Link para recuperação por e-mail
TELA 9 — Administração (/admin/users) — somente admin UserDev
- Listar usuários (status, projetos, e-mail)
- Pausar / reativar usuário
- Excluir usuário
- Enviar alerta (assunto + mensagem)
- Banner de alertas visível para o usuário destinatário
================================================================================
12. FASES DE IMPLEMENTAÇÃO (ORDEM OBRIGATÓRIA)
================================================================================
Branches Git sequenciais: 001, 002, 003, 004 ...
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
FASE 2B — AUTENTICAÇÃO MULTI-USUÁRIO (branch 004)
--------------------------------------------------------------------------------
Implementar ANTES ou JUNTO com Fase 3 — prioridade do desenvolvedor.
1.  Login / logout (usuário ou e-mail + senha)
2.  Cadastro self-service
3.  Recuperação de senha por e-mail
4.  user_id em projects + isolamento total de dados
5.  Troca de e-mail e senha com senha atual
6.  Admin UserDev (seed) + painel /admin/users
7.  Pausar / excluir usuários + enviar alertas
8.  Banner de alertas na UI
CRITÉRIO DE PRONTO FASE 2B:
Dois usuários não veem projetos um do outro; UserDev gerencia usuários
e envia alerta; recuperação de senha funciona; troca de senha exige atual.
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
6.  Permissões granulares por equipe (roles além de admin)
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
10. Roadmap Fase 1–4 + Fase 2B (auth)
================================================================================
15. REGRAS PARA O AGENTE DE IA
================================================================================
1.  NÃO reinstalar Laravel — trabalhar sobre instalação existente
2.  NÃO alterar DB_PASSWORD — desenvolvedor preenche
3.  Implementar FASE POR FASE — confirmar cada fase antes da próxima
4.  Criar branch Git sequencial por fase (001, 002, 003, 004 ...)
5.  Priorizar funcionar localmente no WINDOWS (SO principal)
6.  Commits pequenos e descritivos em português
7.  Ao terminar cada fase: listar o que foi feito, o que testar, pendências
8.  Se dependência externa falhar (API, TTS, FFmpeg), documentar fallback
9.  Manter escopo enxuto — não adicionar features fora deste documento
10. NÃO alterar ADMIN_PASSWORD no .env — desenvolvedor preenche
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
FASE 2B (AUTH — branch 004):
[ ] Login com UserDev funciona
[ ] Cadastro de novo usuário funciona
[ ] Dois usuários NÃO veem projetos um do outro
[ ] Recuperação de senha envia e-mail (ou log em dev)
[ ] Troca de senha exige senha atual
[ ] Troca de e-mail exige senha atual
[ ] UserDev acessa /admin/users
[ ] Admin pausa usuário — usuário pausado não loga
[ ] Admin envia alerta — usuário vê banner
[ ] Admin exclui usuário (não exclui a si nem outros admins)
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
- Multi-usuário com login/senha e admin UserDev (Fase 2B — branch 004)
- Uso pessoal/equipe; caminho aberto para MVP futuro
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