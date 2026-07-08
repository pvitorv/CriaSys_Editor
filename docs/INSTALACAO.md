# Instalação do CriaSys Editor

Este guia é para **quem vai usar o app** (download ou pendrive).  
Desenvolvedores que clonam o código: veja [DESENVOLVIMENTO.md](DESENVOLVIMENTO.md).

---

## Onde baixar

Releases oficiais ficam no repositório remoto:

**GitHub → Releases → escolha o arquivo do seu sistema operacional**

| Sistema | Arquivo recomendado | Alternativa |
|---------|---------------------|-------------|
| **Windows 10/11** | `CriaSys-Editor-*-Portable-x64.exe` | `CriaSys-Editor-*-Setup-x64.exe` |
| **Linux (x64)** | `CriaSys-Editor-*-Linux-x64.AppImage` | `.deb` (Debian/Ubuntu) |
| **macOS** | `CriaSys-Editor-*-macOS-arm64.dmg` (Apple Silicon) | `.dmg` Intel (x64) |

> Se ainda não houver Release publicada, peça ao mantenedor ou siga [REPOSITORIO.md](REPOSITORIO.md) para gerar o build.

---

## Windows 10 / 11

### Opção A — Portable (pendrive, sem instalar) **recomendado**

1. Baixe `CriaSys-Editor-*-Portable-x64.exe` na página **Releases**
2. Copie o arquivo para o PC ou pendrive
3. Dê duplo clique no `.exe`
4. Na **primeira execução**, o app cria a pasta `CriaSysData/` ao lado do executável
5. Faça login com as credenciais **desta instalação**:
   - Na **primeira execução**, o app cria `CriaSysData/secrets.json` e `CriaSysData/PRIMEIRO_ACESSO.txt`
   - Abra `PRIMEIRO_ACESSO.txt` para ver usuário e senha gerados (únicos deste PC)
   - **Não** use credenciais de outra pessoa ou do desenvolvedor do repositório

**Antes de distribuir um pendrive:** edite `CriaSysData/secrets.json` (modelo em [secrets.json.example](secrets.json.example)) ou altere a senha em **Conta** após login.

**Pendrive:** copie o `.exe` **e** a pasta `CriaSysData/` juntos — seus projetos vão no pendrive.

### Opção B — Instalador (Setup)

1. Baixe `CriaSys-Editor-*-Setup-x64.exe`
2. Execute e siga o assistente
3. Abra **CriaSys Editor** pelo menu Iniciar
4. Dados ficam em `CriaSysData/` na pasta onde o portable foi instalado ou ao lado do exe

### Windows — requisitos

- Windows 10 ou 11 (64 bits)
- Conexão internet (opcional — só para buscar imagens Pexels etc.)
- **Não precisa** instalar MySQL, Laragon ou PHP

### Windows — problemas comuns

| Problema | Solução |
|----------|---------|
| SmartScreen bloqueia | Clique **Mais informações** → **Executar assim mesmo** |
| Antivírus bloqueia | Adicione exceção para a pasta do CriaSys |
| App não abre | Verifique se `CriaSysData/` tem permissão de escrita |
| Narração não funciona | Instale Python 3 e: `pip install edge-tts` |

---

## Linux (x64)

### AppImage (funciona na maioria das distros)

```bash
# 1. Baixe o AppImage da página Releases
chmod +x CriaSys-Editor-*-Linux-x64.AppImage

# 2. Execute
./CriaSys-Editor-*-Linux-x64.AppImage
```

Na primeira execução, `CriaSysData/` é criada **no mesmo diretório** do AppImage.

**Pendrive:** copie o AppImage + pasta `CriaSysData/`.

### Debian / Ubuntu (.deb)

```bash
sudo dpkg -i CriaSys-Editor-*-Linux-amd64.deb
# Se faltar dependência:
sudo apt-get install -f
criasys-editor   # ou abra pelo menu de aplicativos
```

### Linux — requisitos

- Ubuntu 20.04+, Debian 11+, Fedora 38+ ou equivalente (64 bits)
- `libfuse2` (para AppImage): `sudo apt install libfuse2`
- Opcional: `python3-pip` + `pip install edge-tts` para narração

### Linux — problemas comuns

| Problema | Solução |
|----------|---------|
| AppImage não abre | `chmod +x` no arquivo; instale `libfuse2` |
| Permissão negada em pendrive | Monte pendrive com permissão de escrita; não use NTFS somente leitura |
| Narração falha | Instale Edge TTS (Python) |

---

## macOS (Intel e Apple Silicon)

### Instalação via DMG

1. Baixe o `.dmg` correto:
   - **Mac M1/M2/M3:** `*-macOS-arm64.dmg`
   - **Mac Intel:** `*-macOS-x64.dmg`
2. Abra o `.dmg` e arraste **CriaSys Editor** para **Aplicativos**
3. Na primeira abertura, o macOS pode bloquear:

   **Ajustes do Sistema → Privacidade e Segurança → Abrir mesmo assim**

   Ou: clique direito no app → **Abrir** → confirmar

4. Dados em `CriaSysData/` ao lado do `.app` ou no diretório de execução

### macOS — requisitos

- macOS 11 (Big Sur) ou superior
- Apple Silicon (M1+) ou Intel x64
- Opcional: Python 3 + `pip install edge-tts`

### macOS — problemas comuns

| Problema | Solução |
|----------|---------|
| “App de desenvolvedor não identificado” | Preferências → Segurança → Abrir mesmo assim |
| App não abre em M1 | Use build **arm64**, não x64 |
| Microfone/TTS | Permissões em Ajustes → Privacidade |

---

## Login (versão portátil / instalador)

Cada instalação tem **credenciais próprias** — não compartilhe as do mantenedor do GitHub.

| Onde | O que fazer |
|------|-------------|
| **Primeira execução** | Leia `CriaSysData/PRIMEIRO_ACESSO.txt` (senha gerada automaticamente) |
| **Depois** | Login com usuário/senha de `CriaSysData/secrets.json` |
| **Personalizar antes** | Copie [secrets.json.example](secrets.json.example) → `CriaSysData/secrets.json` **antes** de abrir o app (apague `.initialized` se já tiver rodado uma vez) |
| **Alterar depois** | Menu **Conta** no app ou edite `secrets.json` + apague `CriaSysData/.initialized` e reinicie (recria admin) |

Usuário padrão: `UserDev` — e-mail e senha são **seus**, definidos em `secrets.json` ou gerados na 1ª execução.

---

## Modo desenvolvimento (clone do GitHub)

Se você **clonou o repositório** para desenvolver (não é o instalador):

```bash
composer install
cp .env.example .env
# Configure DB_* e ADMIN_* com dados do SEU PC — veja guia completo
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
composer dev
```

**Obrigatório no `.env`:** banco (`DB_*`) + `ADMIN_EMAIL` + `ADMIN_PASSWORD` — credenciais do **seu** ambiente.

Detalhes passo a passo: **[DESENVOLVIMENTO.md](DESENVOLVIMENTO.md)**

---

## Suporte

Abra uma **Issue** no repositório GitHub do projeto.
