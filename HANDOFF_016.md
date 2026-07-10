# Handoff — Branch `016` (Timeline cortes + Thumbnails personalizáveis)

> Continuidade pós-015. Última atualização: 10/07/2026.

## O que esta branch entrega

### 1. Timeline — régua dupla e ferramentas de corte
- **Régua de tempo inferior** espelhando a superior
- **Marcador (playhead)** violeta — clique na timeline para posicionar
- **Marcas In / Out** (verde / âmbar) + botão **Aplicar corte**
- Seleção de faixas: **Narração**, **Trilhas 1–3**, **Efeitos**
- Campos `trim_in`, `trim_out`, `source_duration` (trilhas) e `clip_duration` (FX)

### 2. Thumbnails — 8 modelos + personalização
- Aba **Thumbnail** no editor
- Modelos: Clássico, Título forte, Split, Minimal, Cinematográfico, Vibrante, Moldura, Gradiente topo
- Cores (título, subtítulo, destaque, fundo), fontes, tamanhos
- Filtros: **clarear/escurecer** (%), contraste, overlay
- Preview ao vivo + gerar `thumbnail.jpg` final
- Config salva em `project.settings.thumbnail`

### 3. APIs novas
- `GET /api/thumbnail/templates`
- `GET|PUT /api/projects/{id}/thumbnail`
- `POST /api/projects/{id}/thumbnail/generate`
- `PUT /api/projects/{id}/narration` — trim narração

### 4. Layout 015 (commit anterior na mesma linha)
- Preview 16:9, scroll da página, painéis alinhados à altura do preview

## Migration
```bash
php artisan migrate
```

## Pendente / próximos passos
- Aplicar `trim_in`/`trim_out` no mix FFmpeg e no `PreviewAudioMixer`
- Arrastar handles de trim na timeline (hoje: marcas + aplicar)
- Probe automático de `source_duration` na importação de áudio

## Branch
`016` — baseada em `015` @ `4e952d7`
