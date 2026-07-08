# Desenvolvimento local (código-fonte)

Para quem **clona o repositório no GitHub** e roda o projeto na própria máquina.

> **Usuário final** (app instalado/portable): veja [INSTALACAO.md](INSTALACAO.md)  
> **Publicar no GitHub**: veja [REPOSITORIO.md](REPOSITORIO.md)

---

## Pré-requisitos

| Ferramenta | Versão | Observação |
|------------|--------|------------|
| PHP | 8.2+ | Laragon já inclui |
| Composer | 2.x | |
| Node.js | 20+ | |
| MySQL/MariaDB | 8+ | Recomendado no Laragon |
| FFmpeg | qualquer recente | No PATH |
| Python 3 | 3.10+ | `pip install edge-tts` (narração) |

---

## 1. Clonar o repositório

```bash
git clone https://github.com/SEU_USUARIO/CriaSys_Editor.git
cd CriaSys_Editor
git checkout main
```

Substitua `SEU_USUARIO` pelo dono do repositório.

---

## 2. Criar o arquivo `.env`

O `.env` **não vai para o GitHub** — cada pessoa cria o seu com banco e admin **próprios**.

```bash
cp .env.example .env
php artisan key:generate
```

Abra `.env` no editor e configure as seções abaixo.

---

## 3. Configurar o banco de dados

Escolha **uma** opção conforme seu ambiente.

### Opção A — MySQL/MariaDB (Laragon, XAMPP, etc.) **recomendado**

**3.1 — Criar o banco vazio**

No Laragon: **Menu → MySQL → HeidiSQL** (ou phpMyAdmin) e execute:

```sql
CREATE DATABASE criasys_editor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Crie um usuário dedicado (opcional mas recomendado):

```sql
CREATE USER 'criasys'@'localhost' IDENTIFIED BY 'sua_senha_aqui';
GRANT ALL PRIVILEGES ON criasys_editor.* TO 'criasys'@'localhost';
FLUSH PRIVILEGES;
```

**3.2 — Ajustar o `.env`**

Comente SQLite e use MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=criasys_editor
DB_USERNAME=criasys
DB_PASSWORD=sua_senha_aqui
```

No Laragon com usuário `root` sem senha (padrão local):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=criasys_editor
DB_USERNAME=root
DB_PASSWORD=
```

**3.3 — Testar conexão**

```bash
php artisan migrate:status
```

Se der erro de conexão, confira se o MySQL está ligado (Laragon → Start All).

---

### Opção B — SQLite (teste rápido, sem MySQL)

Útil para experimentar sem instalar servidor de banco.

```env
DB_CONNECTION=sqlite
# DB_HOST, DB_PORT, DB_USERNAME e DB_PASSWORD não são usados
```

Crie o arquivo do banco:

```bash
touch database/database.sqlite
```

No Windows (PowerShell):

```powershell
New-Item -Path database/database.sqlite -ItemType File -Force
```

---

## 4. Configurar o administrador (UserDev)

O usuário **UserDev** é o admin inicial do **seu** ambiente. **Não use** e-mail ou senha de outra pessoa ou do mantenedor do repositório.

No `.env`:

```env
ADMIN_USERNAME=UserDev
ADMIN_EMAIL=seu-email@exemplo.com
ADMIN_PASSWORD=SuaSenhaSegura123
```

| Variável | Descrição |
|----------|-----------|
| `ADMIN_USERNAME` | Login (padrão `UserDev`; pode mudar) |
| `ADMIN_EMAIL` | **Seu** e-mail — recuperação de senha, perfil |
| `ADMIN_PASSWORD` | **Sua** senha — mínimo 8 caracteres recomendado |

> O `.env` está no `.gitignore`. Nunca commite senhas ou `.env` real.

---

## 5. Instalar dependências e migrar

```bash
composer install
php artisan migrate --seed
npm install
npm run build
```

O `--seed` cria o UserDev com os valores `ADMIN_*` do **seu** `.env`.

**Login:** http://127.0.0.1:8000 — use `ADMIN_USERNAME` e `ADMIN_PASSWORD`.

---

## 6. Rodar o projeto

```bash
composer dev
```

Em outro terminal (se a fila de render não subir sozinha):

```bash
php artisan queue:listen
```

---

## Alterar senha ou e-mail do admin depois

**Opção 1 — Pelo app:** faça login → **Conta** → altere e-mail/senha.

**Opção 2 — Pelo `.env` + seeder** (recria/atualiza o UserDev):

```bash
# Edite ADMIN_EMAIL e/ou ADMIN_PASSWORD no .env
php artisan db:seed --class=AdminUserSeeder
```

O seeder usa `updateOrCreate` — atualiza senha e e-mail sem duplicar usuário.

---

## APIs de mídia (opcional)

```env
PEXELS_API_KEY=sua_chave
PIXABAY_API_KEY=sua_chave
UNSPLASH_ACCESS_KEY=sua_chave
```

Sem chaves, a busca de imagens externas não funciona; o resto do app funciona normalmente.

---

## Electron em desenvolvimento

```bash
npm run electron:dev
```

Usa MySQL/SQLite do **seu** `.env` (não o modo portátil `CriaSysData/`).

---

## Gerar build Windows (mantenedor)

```bash
# Copie php.exe e ffmpeg para electron/php/win e electron/ffmpeg/win
npm run portable:prepare
composer install --no-dev
npm run build
npm run electron:build:win
```

Saída: `dist-electron/CriaSys-Editor-*-Portable-x64.exe`

Quem **baixar o .exe** não precisa de `.env` — credenciais ficam em `CriaSysData/secrets.json` (geradas na primeira execução). Veja [INSTALACAO.md](INSTALACAO.md).

---

## Checklist rápido (clone → rodando)

- [ ] `git clone` + `git checkout main`
- [ ] `cp .env.example .env`
- [ ] Banco criado (MySQL) ou `database/database.sqlite` (SQLite)
- [ ] `DB_*` preenchido no `.env`
- [ ] `ADMIN_EMAIL` e `ADMIN_PASSWORD` preenchidos (**seus**, não de terceiros)
- [ ] `php artisan key:generate`
- [ ] `composer install && php artisan migrate --seed`
- [ ] `npm install && npm run build`
- [ ] `composer dev` → login com UserDev

---

## Branches

| Branch | Conteúdo |
|--------|----------|
| `main` | Release estável |
| `001`–`005` | Histórico por fase |

---

## Problemas comuns

| Erro | Causa provável | Solução |
|------|----------------|---------|
| `ADMIN_PASSWORD não definida` | `.env` sem admin | Preencha `ADMIN_PASSWORD` |
| `ADMIN_EMAIL não definida` | `.env` sem e-mail | Preencha `ADMIN_EMAIL` com **seu** e-mail |
| `SQLSTATE[1049] Unknown database` | Banco não existe | Crie `criasys_editor` no MySQL |
| `Access denied for user` | Usuário/senha errados | Confira `DB_USERNAME` / `DB_PASSWORD` |
| Fila de render parada | Worker não rodando | `php artisan queue:listen` |
| Narração falha | Edge TTS ausente | `pip install edge-tts` |
