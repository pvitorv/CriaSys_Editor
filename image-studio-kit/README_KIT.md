# Image Studio Kit → **MarkCraft**

**Origem:** CriaSys Editor (branch `040`)  
**Produto destino:** **MarkCraft** (família CriaSys, subdomínio)  
**Tipo:** cópia segura — **não** remove nada do projeto original  
**Apagar este kit depois:** **sim, sem dano** ao CriaSys Editor

## O que é

Pacote de viagem com o código-fonte do **Image Studio** + documentação + prompt para um Agent criar o **MarkCraft**, baseado em:

- **PSD** (formato de trabalho com camadas)
- **Slides / artes para redes sociais** (só **imagem + texto**)
- Hub futuro: encurtador, conversores, compressor PDF + **slots ADS / publicidade própria**
- **Sem** vídeo, áudio, TTS ou render de slideshow

## Ambiente local (ver prompt §0)

- DB: `mark_craft` (criar vazio antes)
- User: `vitor` · senha local no `PROMPT_NOVO_PROJETO.md`

## Conteúdo

| Caminho | Descrição |
|---------|-----------|
| `PROMPT_NOVO_PROJETO.md` | **Leia primeiro** — MarkCraft + tarefas |
| `DEPENDENCIAS.md` | npm/PHP/Python e acoplamentos |
| `INVENTARIO.md` | Lista de arquivos copiados |
| `source/` | Espelho dos arquivos do CriaSys |
| `snippets/` | Rotas e notas de integração |

## Como usar (novo projeto MarkCraft)

1. Crie o DB MySQL `mark_craft` vazio (user `vitor`).
2. Copie a pasta `image-studio-kit/` para o **novo** repositório.
3. Abra o Agent e diga:  
   *“Leia `image-studio-kit/PROMPT_NOVO_PROJETO.md` e implemente o MVP do MarkCraft.”*
4. Depois pode **apagar** o kit — o Editor CriaSys continua intacto.

## O que NÃO fazer

- Não apague `resources/js/imageStudio.js` (etc.) do CriaSys só porque o kit existe.
- Não prometa abrir Canva / Affinity / Corel nativos — só PSD + exports (PNG/JPG/PDF/SVG).
- Não leve TTS, FFmpeg slideshow, Mixkit vídeo, narração.
- Não use domínio separado — MarkCraft fica em **subdomínio CriaSys**.

## Checkpoint CriaSys

- Branch: `040`
- Handoffs: `HANDOFF_029` (TTS/vídeos), `HANDOFF_030` (checkpoint pré-kit), este kit
