# Desenvolvimento local (código-fonte)

Para quem clona o repositório e desenvolve no **Windows com Laragon** (seu caso atual).

## Pré-requisitos

- PHP 8.2+, Composer, Node 20+, MySQL 8
- Laragon (recomendado no Windows)
- FFmpeg no PATH
- Python 3 + `pip install edge-tts` (narração)

## Setup inicial

```bash
git clone https://github.com/SEU_USUARIO/CriaSys_Editor.git
cd CriaSys_Editor
git checkout main          # ou branch 005 — última estável

composer install
cp .env.example .env
# Edite .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD, ADMIN_*

php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

## Rodar

```bash
# Terminal 1
composer dev

# Terminal 2 (se não usar composer dev)
php artisan queue:listen
```

Acesse: http://127.0.0.1:8000  
Login admin: **UserDev** (credenciais no `.env`)

## Electron em desenvolvimento

```bash
npm run electron:dev
```

Usa MySQL do `.env` (não SQLite portátil).

## Gerar build Windows (mantenedor)

```bash
# Copie php.exe e ffmpeg para electron/php/win e electron/ffmpeg/win
npm run portable:prepare
composer install --no-dev
npm run build
npm run electron:build:win
```

Saída: `dist-electron/CriaSys-Editor-*-Portable-x64.exe`

## Branches

| Branch | Conteúdo |
|--------|----------|
| `main` | Release estável (merge da 005) |
| `001`–`005` | Histórico por fase |
