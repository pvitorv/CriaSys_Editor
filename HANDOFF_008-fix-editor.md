# Handoff — Branch `008-fix-editor` (Editor + TTS + Integrações)

> Documento de passagem de bastão. Resume **tudo que foi alterado** nesta sessão para que o próximo agente
> (composer 2) continue sem se perder. Última atualização: 08/07/2026.

---

## 1. Contexto / objetivo da branch

O editor estava "bonito mas inútil": busca de imagens não funcionava, geração de áudio (TTS) não
funcionava no Windows/Laragon, salvar dava erro de UTF-8 e não havia forma de o usuário usar a própria voz.

Esta branch entrega:
1. **Salvar/roteiro/busca de imagens** funcionando (UTF-8 seguro, SSL opcional em dev).
2. **TTS Edge (grátis)** funcionando no Windows via subprocesso Node.
3. **Camada de Integrações estilo CMS**: cada usuário conecta a própria chave (ElevenLabs / OpenAI),
   com voz clonada, listagem dinâmica de vozes, saldo de créditos e botão de compra.
4. **ElevenLabs como opção padrão e primeira** na hora de gerar áudio.
5. **FFmpeg/FFprobe** com fallback gracioso quando não instalado.

---

## 2. Como rodar (ambiente que funciona)

> IMPORTANTE: **não** use `composer dev` (o Vite HMR entra em loop de reload de página no ambiente atual).
> Use assets buildados + serve separado:

```bash
npm run build
php artisan serve
php artisan queue:work        # narração roda em job na fila
```

Migrar (tabela nova de integrações):

```bash
php artisan migrate
```

`.env` relevante (dev Windows/Laragon):

```
HTTP_VERIFY_SSL=false                 # evita erro cURL 60 (SSL) na busca de imagens/APIs
NODE_PATH=                            # opcional; NodeBinary resolve node.exe sozinho
FFMPEG_PATH=<caminho completo>/ffmpeg.exe   # opcional (fallback estima duração)
FFPROBE_PATH=<caminho completo>/ffprobe.exe
TTS_DEFAULT_ENGINE=elevenlabs
```

---

## 3. Arquitetura das mudanças

### 3.1 TTS no Windows (o problema mais difícil)
Rodando sob `php artisan serve`, o Node travava/exit 0 sem gerar o MP3. Causa raiz: `php artisan serve`
sobrescreve `$_SERVER`, tirando `SystemRoot`/`windir`/`PATH`, o que fazia o WinSock/TLS do Node
travar no DNS. Solução final:

- **`app/Support/TtsNodeRunner.php`**: roda o Node direto (sem batch), com `set_time_limit(0)`,
  timeout de 90s e **repassa explicitamente as variáveis de ambiente do Windows** (`SystemRoot`, `windir`,
  `PATH`, etc.) para o subprocesso. Também define TEMP/TMP para pasta gravável e loga exit/stdout/stderr.
- **`scripts/generate-tts.cjs`**: script CommonJS que usa `edge-tts-universal`, com timeout de 45s
  na síntese e log opcional via env `TTS_DEBUG_LOG`.
- **`app/Support/NodeBinary.php`** (já existia): resolve `node.exe` mesmo sem PATH.
- **`app/Providers/AppServiceProvider.php`** (já existia): força `storage/framework/process-tmp`
  como temp do Symfony Process (evita `Permission denied` em `C:\WINDOWS`).

**Removidos** (abordagens antigas que não deram certo): `scripts/generate-tts.mjs`,
`scripts/generate-tts-launch.cjs`, `app/Support/ProcessRunner.php`.

### 3.2 UTF-8 seguro (erro "Malformed UTF-8 characters")
- **`app/Support/Utf8.php`** + **`app/Support/SafeJson.php`** (já existiam): limpam strings antes de
  salvar/serializar. Usados em `SlideController` e `NarrationController`.

### 3.3 Integrações por usuário (CMS)
Nova camada para o usuário plugar a própria chave:

- **`database/migrations/2026_07_09_000001_create_user_integrations_table.php`**: tabela `user_integrations`
  (`user_id`, `provider`, `credentials` [encrypted:array], `default_voice`, `enabled`), única por usuário+provider.
- **`app/Models/UserIntegration.php`**: model com cast `encrypted:array`; helpers `apiKey()`, `hasApiKey()`.
- **`app/Models/User.php`**: relação `integrations()` + `integrationFor($provider)`.
- **`app/Services/Tts/TtsCredentials.php`**: resolve chave/voz do usuário logado (fallback pra config global).
- **`app/Services/Tts/TtsVoiceCatalog.php`**: lista vozes por provider. ElevenLabs cacheado 10min, ordena
  vozes `premade` (grátis) primeiro e adiciona rótulos ` (minha voz)`, ` (grátis)`, ` (biblioteca — plano pago)`.
- **`app/Services/Tts/TtsEngineFactory.php`**: injeta `TtsCredentials`; `isAvailable()` de elevenlabs/openai
  passa a depender da chave do usuário, não mais do `.env` global.
- **`app/Services/Tts/ElevenLabsTtsEngine.php` / `OpenAiTtsEngine.php`**: usam `TtsCredentials` pra chave/voz.
- **`app/Jobs/GenerateNarrationJob.php`**: `auth()->setUser($project->user)` pra job usar as credenciais certas.
- **`app/Http/Controllers/IntegrationController.php`** (novo): CRUD das integrações, teste de conexão,
  saldo de créditos ElevenLabs e link de billing.
- **`resources/views/integrations/edit.blade.php`** (novo): tela de Integrações (chave, voz padrão, toggle,
  testar conexão, **barra de créditos** com aviso de saldo baixo e **botão "Comprar créditos / Gerenciar plano"**).
- **`resources/views/layouts/app.blade.php`**: link "Integrações" no nav.
- **Rotas**: `routes/web.php` (`/integrations`...), `routes/api.php` (`/tts/engines/{provider}/voices`).
- **`app/Http/Controllers/Api/TtsController.php`**: método `voices()`.

### 3.4 Editor dinâmico + ElevenLabs padrão
- **`config/criasys.php`**: `default_engine = elevenlabs`; array `engines` com elevenlabs em 1º; notas
  de indisponível apontam para a página de Integrações.
- **`resources/js/editor.js`**: `ttsEngine` inicia `elevenlabs`, `voice` vazio; `loadVoices()` +
  `onEngineChange()` buscam vozes dinamicamente por motor.
- **`resources/views/projects/editor.blade.php`**: seletor de voz dinâmico + dica pra Integrações.

### 3.5 FFmpeg gracioso
- **`app/Services/Render/FfmpegRenderService.php`**: `getAudioDuration()` estima duração pelo tamanho do
  arquivo quando `ffprobe` não existe (em vez de quebrar).

---

## 4. Limitações conhecidas / decisões

- **Compra de crédito ElevenLabs dentro do CMS: NÃO é possível.** Eles não têm API de compra; o top-up é
  manual no painel deles (ou Auto Top Up). O CMS oferece apenas botão de deep-link para o billing +
  aviso de saldo baixo. Revender crédito a usuários exige contrato **OEM/Enterprise** (proibido em conta comum).
- Free plan da ElevenLabs **não** usa vozes da "biblioteca" via API (erro `paid_plan_required`). Por isso
  o catálogo rotula e ordena as vozes grátis primeiro.
- App mobile "Voz do Narrador - TTS": **não** tem API pública documentada; não foi integrado.

---

## 5. Arquivos alterados nesta sessão (resumo git)

```
Novos:
  app/Http/Controllers/IntegrationController.php
  app/Models/UserIntegration.php
  app/Services/Tts/TtsCredentials.php
  app/Services/Tts/TtsVoiceCatalog.php
  database/migrations/2026_07_09_000001_create_user_integrations_table.php
  resources/views/integrations/edit.blade.php

Modificados:
  app/Http/Controllers/Api/TtsController.php
  app/Jobs/GenerateNarrationJob.php
  app/Models/User.php
  app/Services/Render/FfmpegRenderService.php
  app/Services/Tts/ElevenLabsTtsEngine.php
  app/Services/Tts/OpenAiTtsEngine.php
  app/Services/Tts/TtsEngineFactory.php
  app/Support/TtsNodeRunner.php
  config/criasys.php
  resources/js/editor.js
  resources/views/layouts/app.blade.php
  resources/views/projects/editor.blade.php
  routes/api.php
  routes/web.php
  scripts/generate-tts.cjs
  .gitignore

Removidos:
  app/Support/ProcessRunner.php
  scripts/generate-tts-launch.cjs
  scripts/generate-tts.mjs
```

---

## 6. Próximos passos sugeridos (para o composer 2)

1. (Opcional) Integrar o app "Voz do Narrador - TTS" só se surgir API pública dele.
2. (Opcional grande) Sistema de créditos próprios (carteira + gateway Pix/Mercado Pago) — exige
   acordo OEM com a ElevenLabs antes de virar produção.
3. Testar renderização final de vídeo com FFmpeg instalado (hoje há fallback de estimativa).
4. Rodar `php artisan migrate` em qualquer ambiente novo (tabela `user_integrations`).
