# CriaSys Editor

Gerador de **slideshow narrado** — desktop, portátil (pendrive) e multi-plataforma.

---

## Baixar e instalar (usuário final)

| Sistema | O que baixar | Guia completo |
|---------|--------------|---------------|
| **Windows 10/11** | `*-Portable-x64.exe` nas [Releases](https://github.com/SEU_USUARIO/CriaSys_Editor/releases) | [Instalação Windows](docs/INSTALACAO.md#windows-10--11) |
| **Linux** | `*.AppImage` ou `*.deb` | [Instalação Linux](docs/INSTALACAO.md#linux-x64) |
| **macOS** | `*.dmg` (arm64 ou x64) | [Instalação macOS](docs/INSTALACAO.md#macos-intel-e-apple-silicon) |

> Substitua `SEU_USUARIO` pelo dono do repositório GitHub após publicar.

**Pendrive (Windows):** copie o `.exe` + pasta `CriaSysData/` — [instruções](docs/INSTALACAO.md#opção-a--portable-pendrive-sem-instalar-recomendado)

---

## Documentação

| Documento | Público |
|-----------|---------|
| [docs/INSTALACAO.md](docs/INSTALACAO.md) | Usuário final — Windows, Linux, macOS |
| [docs/DESENVOLVIMENTO.md](docs/DESENVOLVIMENTO.md) | Clone GitHub — `.env`, MySQL, UserDev **por máquina** |
| [docs/REPOSITORIO.md](docs/REPOSITORIO.md) | Publicar no GitHub, Releases, CI/CD |
| [CRIASYS_BRIEF.md](CRIASYS_BRIEF.md) | Especificação completa do projeto |

---

## Desenvolvimento rápido (Windows + Laragon)

```bash
composer install && cp .env.example .env
# Edite .env: DB_* (seu MySQL) + ADMIN_EMAIL + ADMIN_PASSWORD (seu admin)
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
composer dev
```

Login: **UserDev** com a senha que **você** definiu em `ADMIN_PASSWORD` — [guia completo do .env](docs/DESENVOLVIMENTO.md)

---

## Publicar repositório remoto (primeira vez)

```bash
# Você está na branch 005 localmente — envie como main no GitHub:
git remote add origin https://github.com/SEU_USUARIO/CriaSys_Editor.git
git push -u origin 005:main
git push origin 001 002 003 004 005
```

Detalhes: **[docs/REPOSITORIO.md](docs/REPOSITORIO.md)**

---

## Branches

| Branch | Fase |
|--------|------|
| `main` | Release estável |
| `001`–`005` | Histórico (MVP → portátil multi-SO) |

---

## Roadmap

- **Fases 1–3** ✅ MVP, export social, Electron
- **Fase 2B** ✅ Autenticação multi-usuário (UserDev admin)
- **Fase 005** ✅ Portátil pendrive + builds Win/Linux/macOS
- **Fase 4** Legendas burn-in, Coqui TTS, templates
