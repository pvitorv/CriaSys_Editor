# Image Studio Kit — README

**Origem:** CriaSys Editor (branch `040`)  
**Tipo:** cópia segura — **não** remove nada do projeto original  
**Apagar este kit depois:** **sim, sem dano** ao CriaSys Editor

## O que é

Pacote de viagem com o código-fonte do **Image Studio** + documentação + prompt para um Agent criar um **novo site** (ferramenta gratuita para criadores / marketing / designers iniciantes), baseado em:

- **PSD** (formato de trabalho com camadas)
- **Slides / artes para redes sociais** (só **imagem + texto**)
- Hub futuro: encurtador, conversores, compressor PDF + **slots ADS / publicidade própria**
- **Sem** vídeo, áudio, TTS ou render de slideshow

## Conteúdo

| Caminho | Descrição |
|---------|-----------|
| `PROMPT_NOVO_PROJETO.md` | **Leia primeiro** no novo repo — visão de produto + tarefas |
| `DEPENDENCIAS.md` | npm/PHP/Python e acoplamentos |
| `INVENTARIO.md` | Lista de arquivos copiados |
| `source/` | Espelho dos arquivos do CriaSys |
| `snippets/` | Rotas e notas de integração |

## Como usar (novo projeto)

1. Copie a pasta `image-studio-kit/` para o **novo** repositório (ou clone e mova só o kit).
2. Abra o Agent no novo projeto e diga:  
   *“Leia `image-studio-kit/PROMPT_NOVO_PROJETO.md` e implemente o MVP.”*
3. Depois que o novo app estiver de pé, você pode **apagar** `image-studio-kit/` no CriaSys e/ou no novo repo — o Editor original continua intacto.

## O que NÃO fazer

- Não apague `resources/js/imageStudio.js` (etc.) do CriaSys só porque o kit existe.
- Não prometa abrir Canva / Affinity / Corel nativos — só PSD + exports (PNG/JPG/PDF/SVG).
- Não leve TTS, FFmpeg slideshow, Mixkit vídeo, narração.

## Checkpoint CriaSys

- Branch: `040`
- Handoffs: `HANDOFF_029` (TTS/vídeos), `HANDOFF_030` (checkpoint pré-kit), este kit
