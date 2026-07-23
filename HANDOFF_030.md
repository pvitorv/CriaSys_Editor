# Handoff 030 — Checkpoint antes do Image Studio Kit

**Branch anterior:** `039` (commit `a8892a1` — TTS/narração + Mixkit + `HANDOFF_029`)  
**Branch deste checkpoint:** `040`  
**Data:** 2026-07-22

## Estado

- Código do CriaSys Editor: **salvo** (sem diff de fonte pendente).
- Binários `electron/php/win/*` e `electron/ffmpeg/win/*.exe`: **locais, fora do git** (não versionar).
- Próximo trabalho combinado: montar **kit de cópia** do Image Studio + prompt do novo produto (**não** remover nada do CriaSys).

## Plano do novo produto (resumo para o Agent)

- Extrair Image Studio como kit autocontido (cópia).
- Novo site: ferramenta grátis, **login obrigatório**, trabalhos **não** salvos no servidor (baixar → limpar → próximo).
- Público: criadores, marketing de vendas, designers iniciantes.
- Base técnica: **PSD** (camadas) + **slides/carrossel para redes** (só imagem + texto).
- **Sem** vídeo, áudio, TTS, render FFmpeg de slideshow.
- Não prometer abrir nativo Canva / Affinity / Corel `.cdr` — só exports (PNG/PDF/PSD).
- Futuro: Drive, conversão de imagens, PDF, encurtador, afiliados/packs.

## Ao retomar neste repo

1. Checkout `040`
2. Montar `image-studio-kit/` + `PROMPT_NOVO_PROJETO.md` + `DEPENDENCIAS.md` + `README_KIT.md`
3. Cópia segura; CriaSys intacto; kit apagável depois

## Referências

- `HANDOFF_029.md` — TTS / vídeos
- `HANDOFF_017.md` … `HANDOFF_026.md` — Image Studio
