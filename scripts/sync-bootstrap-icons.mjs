/**
 * Copia ícones Bootstrap Icons (MIT) para public/icons/bootstrap/
 * e gera config/image_studio_icons.php para o catálogo do Image Studio.
 *
 * Uso: node scripts/sync-bootstrap-icons.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const srcDir = path.join(root, 'node_modules', 'bootstrap-icons', 'icons');
const destDir = path.join(root, 'public', 'icons', 'bootstrap');
const outPhp = path.join(root, 'config', 'image_studio_icons.php');

const CATALOG = {
    bs_social: {
        label: 'Social & marcas',
        icons: [
            ['instagram', 'Instagram'],
            ['facebook', 'Facebook'],
            ['twitter-x', 'X / Twitter'],
            ['youtube', 'YouTube'],
            ['tiktok', 'TikTok'],
            ['whatsapp', 'WhatsApp'],
            ['linkedin', 'LinkedIn'],
            ['telegram', 'Telegram'],
            ['heart', 'Coração'],
            ['heart-fill', 'Coração preenchido'],
            ['star', 'Estrela'],
            ['star-fill', 'Estrela preenchida'],
            ['bookmark', 'Salvar'],
            ['bookmark-fill', 'Salvo'],
            ['share', 'Compartilhar'],
            ['share-fill', 'Compartilhar fill'],
            ['hand-thumbs-up', 'Curtir'],
            ['hand-thumbs-up-fill', 'Curtir fill'],
            ['emoji-smile', 'Emoji'],
            ['emoji-heart-eyes', 'Emoji amor'],
        ],
    },
    bs_media: {
        label: 'Mídia & áudio',
        icons: [
            ['play-fill', 'Play'],
            ['pause-fill', 'Pause'],
            ['stop-fill', 'Stop'],
            ['skip-forward-fill', 'Avançar'],
            ['skip-backward-fill', 'Voltar'],
            ['camera', 'Câmera'],
            ['camera-fill', 'Câmera fill'],
            ['image', 'Imagem'],
            ['image-fill', 'Imagem fill'],
            ['film', 'Filme'],
            ['music-note-beamed', 'Música'],
            ['mic', 'Microfone'],
            ['mic-fill', 'Microfone fill'],
            ['volume-up-fill', 'Volume'],
            ['volume-mute-fill', 'Mudo'],
            ['broadcast', 'Live'],
            ['webcam', 'Webcam'],
        ],
    },
    bs_communication: {
        label: 'Comunicação',
        icons: [
            ['chat', 'Chat'],
            ['chat-dots', 'Chat dots'],
            ['chat-dots-fill', 'Chat fill'],
            ['envelope', 'E-mail'],
            ['envelope-fill', 'E-mail fill'],
            ['telephone', 'Telefone'],
            ['telephone-fill', 'Telefone fill'],
            ['megaphone', 'Megafone'],
            ['megaphone-fill', 'Megafone fill'],
            ['send', 'Enviar'],
            ['send-fill', 'Enviar fill'],
            ['inbox', 'Inbox'],
            ['bell', 'Notificação'],
            ['bell-fill', 'Notificação fill'],
        ],
    },
    bs_commerce: {
        label: 'Comércio',
        icons: [
            ['cart', 'Carrinho'],
            ['cart-fill', 'Carrinho fill'],
            ['bag', 'Sacola'],
            ['bag-fill', 'Sacola fill'],
            ['credit-card', 'Cartão'],
            ['credit-card-fill', 'Cartão fill'],
            ['currency-dollar', 'Dólar'],
            ['shop', 'Loja'],
            ['tag', 'Etiqueta'],
            ['tag-fill', 'Etiqueta fill'],
            ['gift', 'Presente'],
            ['gift-fill', 'Presente fill'],
            ['ticket-perforated', 'Ingresso'],
            ['percent', 'Desconto'],
        ],
    },
    bs_ui: {
        label: 'Interface',
        icons: [
            ['check', 'Check'],
            ['check-circle', 'Check círculo'],
            ['check-circle-fill', 'Check círculo fill'],
            ['x', 'Fechar'],
            ['x-circle', 'X círculo'],
            ['x-circle-fill', 'X círculo fill'],
            ['plus', 'Mais'],
            ['plus-circle', 'Mais círculo'],
            ['dash', 'Menos'],
            ['exclamation-circle', 'Alerta'],
            ['question-circle', 'Ajuda'],
            ['info-circle', 'Info'],
            ['gear', 'Configurações'],
            ['gear-fill', 'Config fill'],
            ['sliders', 'Sliders'],
            ['search', 'Buscar'],
            ['list', 'Lista'],
            ['grid-3x3-gap', 'Grid'],
            ['eye', 'Ver'],
            ['eye-fill', 'Ver fill'],
            ['eye-slash', 'Ocultar'],
            ['trash', 'Lixeira'],
            ['trash-fill', 'Lixeira fill'],
        ],
    },
    bs_arrows: {
        label: 'Setas',
        icons: [
            ['arrow-right', 'Seta direita'],
            ['arrow-left', 'Seta esquerda'],
            ['arrow-up', 'Seta cima'],
            ['arrow-down', 'Seta baixo'],
            ['arrow-up-right', 'Seta diagonal'],
            ['chevron-right', 'Chevron direita'],
            ['chevron-left', 'Chevron esquerda'],
            ['chevron-up', 'Chevron cima'],
            ['chevron-down', 'Chevron baixo'],
            ['caret-right-fill', 'Caret direita'],
            ['caret-left-fill', 'Caret esquerda'],
            ['box-arrow-up-right', 'Link externo'],
            ['arrow-repeat', 'Repetir'],
            ['arrow-clockwise', 'Atualizar'],
        ],
    },
    bs_people: {
        label: 'Pessoas',
        icons: [
            ['person', 'Pessoa'],
            ['person-fill', 'Pessoa fill'],
            ['people', 'Grupo'],
            ['people-fill', 'Grupo fill'],
            ['person-circle', 'Avatar'],
            ['person-badge', 'Crachá'],
            ['person-hearts', 'Fãs'],
            ['person-workspace', 'Workspace'],
        ],
    },
    bs_files: {
        label: 'Arquivos',
        icons: [
            ['file-earmark', 'Arquivo'],
            ['file-earmark-text', 'Documento'],
            ['file-earmark-pdf', 'PDF'],
            ['file-earmark-image', 'Arquivo imagem'],
            ['folder', 'Pasta'],
            ['folder-fill', 'Pasta fill'],
            ['download', 'Download'],
            ['upload', 'Upload'],
            ['link-45deg', 'Link'],
            ['paperclip', 'Anexo'],
            ['clipboard', 'Clipboard'],
            ['save', 'Salvar'],
            ['save-fill', 'Salvar fill'],
        ],
    },
    bs_tech: {
        label: 'Tech',
        icons: [
            ['laptop', 'Notebook'],
            ['phone', 'Celular'],
            ['tablet', 'Tablet'],
            ['display', 'Monitor'],
            ['wifi', 'Wi-Fi'],
            ['cloud', 'Nuvem'],
            ['cloud-fill', 'Nuvem fill'],
            ['bluetooth', 'Bluetooth'],
            ['cpu', 'CPU'],
            ['hdd', 'HD'],
            ['code-slash', 'Código'],
            ['terminal', 'Terminal'],
            ['robot', 'Robô'],
            ['lightning-charge', 'Energia'],
        ],
    },
    bs_location: {
        label: 'Local & tempo',
        icons: [
            ['geo-alt', 'Local'],
            ['geo-alt-fill', 'Local fill'],
            ['pin-map', 'Pin mapa'],
            ['pin-map-fill', 'Pin fill'],
            ['compass', 'Bússola'],
            ['house', 'Casa'],
            ['house-fill', 'Casa fill'],
            ['building', 'Prédio'],
            ['clock', 'Relógio'],
            ['clock-fill', 'Relógio fill'],
            ['calendar-event', 'Calendário'],
            ['alarm', 'Alarme'],
            ['sun', 'Sol'],
            ['moon', 'Lua'],
            ['cloud-sun', 'Clima'],
        ],
    },
    bs_creative: {
        label: 'Criativo',
        icons: [
            ['pencil', 'Lápis'],
            ['pencil-fill', 'Lápis fill'],
            ['brush', 'Pincel'],
            ['palette', 'Paleta'],
            ['palette-fill', 'Paleta fill'],
            ['scissors', 'Tesoura'],
            ['magic', 'Mágica'],
            ['stars', 'Estrelas'],
            ['award', 'Prêmio'],
            ['trophy', 'Troféu'],
            ['trophy-fill', 'Troféu fill'],
            ['lightbulb', 'Ideia'],
            ['lightbulb-fill', 'Ideia fill'],
            ['book', 'Livro'],
            ['mortarboard', 'Formatura'],
        ],
    },
    bs_security: {
        label: 'Segurança',
        icons: [
            ['lock', 'Cadeado'],
            ['lock-fill', 'Cadeado fill'],
            ['unlock', 'Aberto'],
            ['unlock-fill', 'Aberto fill'],
            ['shield', 'Escudo'],
            ['shield-fill', 'Escudo fill'],
            ['shield-check', 'Verificado'],
            ['key', 'Chave'],
            ['fingerprint', 'Digital'],
        ],
    },
};

fs.mkdirSync(destDir, { recursive: true });

const elements = [];
const groups = {};
let copied = 0;
let missing = [];

for (const [groupKey, groupMeta] of Object.entries(CATALOG)) {
    groups[groupKey] = groupMeta.label;
    for (const [iconName, label] of groupMeta.icons) {
        const srcFile = path.join(srcDir, `${iconName}.svg`);
        const destFile = path.join(destDir, `${iconName}.svg`);
        if (!fs.existsSync(srcFile)) {
            missing.push(iconName);
            continue;
        }
        fs.copyFileSync(srcFile, destFile);
        copied += 1;
        elements.push({
            slug: `bs_${iconName.replace(/-/g, '_')}`,
            name: label,
            group: groupKey,
            type: 'svg_icon',
            icon_set: 'bootstrap',
            icon_name: iconName,
            icon_url: `/icons/bootstrap/${iconName}.svg`,
            fill: '#ffffff',
            size: 120,
        });
    }
}

const php = `<?php

/**
 * Ícones Bootstrap Icons (MIT) — gerado por scripts/sync-bootstrap-icons.mjs
 * Não edite manualmente; rode: node scripts/sync-bootstrap-icons.mjs
 */
return [
    'groups' => ${phpExport(groups)},

    'elements' => ${phpExport(elements)},
];
`;

function phpExport(obj, indent = 0) {
    const pad = '    '.repeat(indent);
    if (Array.isArray(obj)) {
        if (obj.length === 0) {
            return '[]';
        }
        const items = obj.map((v) => `${pad}    ${phpExport(v, indent + 1)}`).join(',\n');
        return `[\n${items},\n${pad}]`;
    }
    if (obj !== null && typeof obj === 'object') {
        const entries = Object.entries(obj);
        if (entries.length === 0) {
            return '[]';
        }
        const items = entries.map(([k, v]) => {
            const key = /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(k) ? `'${k}'` : `'${k.replace(/'/g, "\\'")}'`;
            return `${pad}    ${key} => ${phpExport(v, indent + 1)}`;
        }).join(',\n');
        return `[\n${items},\n${pad}]`;
    }
    if (typeof obj === 'string') {
        return `'${obj.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
    }
    if (typeof obj === 'number') {
        return String(obj);
    }
    if (typeof obj === 'boolean') {
        return obj ? 'true' : 'false';
    }
    return 'null';
}

fs.writeFileSync(outPhp, php, 'utf8');

console.log(`Copiados ${copied} ícones para public/icons/bootstrap/`);
console.log(`Gerado ${outPhp} (${elements.length} elementos)`);
if (missing.length) {
    console.warn('Ícones não encontrados:', missing.join(', '));
}
