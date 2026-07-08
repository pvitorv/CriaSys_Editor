# CriaSys Editor

Gerador de **slideshow narrado** 100% desktop. Monte slides, gere narração em PT-BR, adicione trilha, renderize vídeos localmente e exporte pacotes para Premiere/Affinity.

## Requisitos

| Ferramenta | Versão mínima |
|------------|---------------|
| PHP | 8.2+ (Laragon) |
| Composer | 2.x |
| Node.js | 20+ |
| MySQL | 8.x |
| FFmpeg | latest (PATH ou embutido no Electron) |
| Python | 3.x (opcional, para Edge TTS: `pip install edge-tts`) |

## Instalação

```bash
composer install
cp .env.example .env   # configure DB e API keys
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

## Configuração `.env`

```env
APP_NAME=CriaSys_Editor
DB_DATABASE=CriaSysEditor
DB_USERNAME=vitor
DB_PASSWORD=          # preencher

PEXELS_API_KEY=
PIXABAY_API_KEY=
UNSPLASH_ACCESS_KEY=

TTS_DEFAULT_ENGINE=edge
TTS_DEFAULT_VOICE=pt-BR-FranciscaNeural

FFMPEG_PATH=          # vazio = usar PATH
CRIASYS_PROJECTS_PATH=
CRIASYS_EXPORTS_PATH=
```

## Autenticação multi-usuário

- Login por **usuário** ou **e-mail** + senha
- Recuperação de senha por e-mail (`/forgot-password`)
- Cada usuário vê apenas seus projetos
- Admin padrão: **UserDev** (configurável no `.env`)

```env
ADMIN_USERNAME=UserDev
ADMIN_EMAIL=seu@email.com
ADMIN_PASSWORD=sua_senha_segura
```

Após migrar: `php artisan db:seed --class=AdminUserSeeder`

## Desenvolvimento (navegador)

```bash
composer dev              # serve + queue + vite (Windows, sem Pail)
php artisan queue:listen  # se não usar composer dev
```

Acesse: http://127.0.0.1:8000

## Desktop portátil (pendrive / download)

O app empacotado roda **sem instalar MySQL** — usa SQLite na pasta `CriaSysData/` ao lado do executável.

### Preparar runtimes (antes do build)

```bash
# 1. Coloque PHP e FFmpeg em electron/php/{win|linux|mac} e electron/ffmpeg/{win|linux|mac}
#    Ver electron/php/README.md e electron/ffmpeg/README.md

npm run portable:prepare   # verifica estrutura
composer install --no-dev
npm run build
```

### Gerar executáveis

```bash
npm run electron:build:win     # Portable.exe + Setup.exe (Windows 10/11 x64)
npm run electron:build:linux   # AppImage + .deb (Linux x64)
npm run electron:build:mac     # .dmg + .zip (macOS Intel + Apple Silicon)
npm run electron:build:all     # Windows + Linux + macOS (requer SO compatível)
```

Arquivos gerados em `dist-electron/`.

### Pendrive

1. Copie o `.exe` Portable ou a pasta completa do build
2. A pasta **`CriaSysData/`** contém banco, projetos e configurações
3. Leve o pendrive — seus dados vão junto

Credenciais iniciais portátil: editáveis em `CriaSysData/secrets.json`

### Desenvolvimento Electron (Laragon/MySQL)

```bash
npm run electron:dev      # usa .env local + MySQL
```

## Desktop (Electron) — legado dev

### FFmpeg embutido

Coloque `ffmpeg.exe` e `ffprobe.exe` em:

```
electron/ffmpeg/win/
```

## Estrutura de pastas do projeto

```
storage/app/criasys/projetos/{id}/
├── slides/
├── audio/
├── assets/
├── exports/
├── thumbs/
└── project.json
```

## Importar no Premiere / Affinity / Photoshop

1. Use **Exportar → Pacote Premiere/Affinity** no editor
2. Abra a pasta gerada em `storage/app/criasys/exports/`
3. **Premiere:** importe `premiere.xml` + PNGs + WAV + SRT
4. **Photoshop/Affinity:** use os PNGs em `slides/`

## Licenciamento de assets

Assets de bibliotecas externas salvam metadados de licença. O arquivo `credits.txt` é gerado automaticamente no pacote de export quando há atribuições obrigatórias.

## Branches de desenvolvimento

| Branch | Fase |
|--------|------|
| `001` | Fundação MVP |
| `002` | Export social + pacote profissional |
| `003` | Electron + polish |
| `004` | Autenticação multi-usuário |
| `005` | Distribuição portátil multi-SO |

## Roadmap

- **Fase 1** ✅ Projetos, slides, TTS, render 16:9
- **Fase 2** ✅ Presets sociais, SRT, pacote Premiere, Pixabay/Unsplash
- **Fase 3** ✅ Electron, auto-save, atalhos, build Windows
- **Fase 4** Legendas burn-in, Coqui TTS, templates, multi-usuário
