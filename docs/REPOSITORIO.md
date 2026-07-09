# Repositório remoto e publicação por sistema

Este documento explica **como publicar o CriaSys Editor no GitHub** e **como cada sistema operacional obtém o instalador**.

---

## Estrutura do repositório

```
CriaSys_Editor/          ← repositório Git
├── main                 ← branch padrão (releases) — equivale à 006
├── 001 … 012            ← branches históricas por fase (atual dev: 013)
├── docs/
│   ├── INSTALACAO.md    ← guia do usuário final (LEIA ISTO)
│   ├── DESENVOLVIMENTO.md
│   └── REPOSITORIO.md   ← este arquivo
├── .github/workflows/
│   └── release.yml      ← build automático Win/Linux/macOS
└── dist-electron/       ← builds locais (não commitar)
```

---

## Passo 1 — Criar repositório no GitHub

1. Acesse https://github.com/new
2. Nome: **`CriaSys_Editor`**
3. Visibilidade: **Private** (uso pessoal) ou **Public**
4. **Não** marque “Add README” (já existe no projeto)
5. Crie o repositório

---

## Passo 2 — Enviar código (primeira vez)

No PC Windows (Laragon), na pasta do projeto:

```bash
cd C:/laragon/www/CriaSys_Editor

# Enviar branch 005 como main no GitHub (sem alterar branches locais)
git remote add origin https://github.com/pvitorv/CriaSys_Editor.git
git push -u origin 006:main

# Enviar histórico de fases (opcional)
git push origin 001 002 003 004 005 006 master
```

Autenticação: use **Personal Access Token** (GitHub → Settings → Developer settings → Tokens) como senha.

---

## Passo 3 — Publicar Release (instaladores para download)

### Opção A — Automático (GitHub Actions)

1. No GitHub: **Actions → Release build → Run workflow**
2. Ou crie uma tag:

```bash
git tag v1.0.0
git push origin v1.0.0
```

3. O workflow `.github/workflows/release.yml` gera:
   - Windows: Portable + Setup
   - Linux: AppImage + deb
   - macOS: dmg (Intel + ARM)

4. Em **Releases**, anexe os artefatos ou use o workflow com `softprops/action-gh-release`

### Opção B — Manual (Windows agora)

No seu Windows:

```bash
# 1. Baixe PHP TS x64 ZIP → extraia php.exe em electron/php/win/
# 2. Baixe FFmpeg essentials → ffmpeg.exe e ffprobe.exe em electron/ffmpeg/win/

npm run portable:prepare
composer install --no-dev
npm run build
npm run electron:build:win
```

No GitHub: **Releases → New release → tag v1.0.0**  
Anexe os arquivos de `dist-electron/`:

- `CriaSys-Editor-*-Portable-x64.exe` ← **Windows pendrive**
- `CriaSys-Editor-*-Setup-x64.exe` ← **Windows instalador**

> Builds **Linux** e **macOS** precisam ser gerados **naquele sistema** ou via GitHub Actions (runners ubuntu-latest e macos-latest).

---

## O que cada sistema baixa

| SO | Onde baixar | Arquivo | Instruções |
|----|-------------|---------|------------|
| **Windows** | GitHub Releases | `*-Portable-x64.exe` | [INSTALACAO.md#windows](INSTALACAO.md#windows-10--11) |
| **Linux** | GitHub Releases | `*.AppImage` ou `*.deb` | [INSTALACAO.md#linux](INSTALACAO.md#linux-x64) |
| **macOS** | GitHub Releases | `*.dmg` arm64 ou x64 | [INSTALACAO.md#macos](INSTALACAO.md#macos-intel-e-apple-silicon) |

**Link para compartilhar com usuários:**

```
https://github.com/pvitorv/CriaSys_Editor/releases/latest
```

Inclua no pendrive (Windows):

```
pendrive/
├── CriaSys-Editor-1.0.0-Portable-x64.exe
├── CriaSysData/              (criada após 1º uso — levar junto)
└── LEIA-ME.txt               (copie docs/PORTABLE_LEIA-ME.txt)
```

---

## Fluxo recomendado de manutenção

```
Desenvolvimento (Windows + Laragon)
        ↓
git commit na branch de feature
        ↓
merge → main
        ↓
git tag v1.0.x && git push --tags
        ↓
GitHub Actions builda Win + Linux + macOS
        ↓
Release publicada → usuários baixam por SO
```

---

## CI/CD — requisitos dos runners

O workflow baixa PHP e FFmpeg automaticamente no Windows.  
Linux e macOS usam runners nativos do GitHub.

Secrets necessários: **nenhum** para build básico.

---

## Checklist antes de publicar Release

- [ ] `npm run build` sem erro
- [ ] `composer install --no-dev` 
- [ ] Runtimes em `electron/php/` e `electron/ffmpeg/` (ou CI)
- [ ] Testado login UserDev no Portable local
- [ ] Tag semver (`v1.0.0`)
- [ ] Texto da Release linkando `docs/INSTALACAO.md`

---

## Privacidade e credenciais

**Nunca** commite `.env`, senhas ou `CriaSysData/`.  
O `.gitignore` já exclui esses arquivos.

| Cenário | Onde ficam as credenciais |
|---------|---------------------------|
| **Clone do GitHub (dev)** | `.env` local — cada pessoa preenche `DB_*` e `ADMIN_*` | [DESENVOLVIMENTO.md](DESENVOLVIMENTO.md) |
| **App portable/instalador** | `CriaSysData/secrets.json` + `PRIMEIRO_ACESSO.txt` na 1ª execução | [INSTALACAO.md](INSTALACAO.md) |

Quem clona o repo **não** deve usar e-mail/senha do mantenedor — configure o próprio UserDev no `.env`.
