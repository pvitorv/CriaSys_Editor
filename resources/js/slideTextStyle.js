/** Estilo de texto visível no slide — padrão 12px branco. */

export const DEFAULT_BODY_SIZE = 12;
export const LEGACY_SIZES = new Set([20, 48]);

export function normalizeTextStyle(style) {
    const raw = style && typeof style === 'object' ? style : {};

    let size = parseInt(raw.body_size, 10);
    if (!Number.isFinite(size) || LEGACY_SIZES.has(size)) {
        size = parseInt(raw.title_size, 10);
    }
    if (!Number.isFinite(size) || LEGACY_SIZES.has(size)) {
        size = DEFAULT_BODY_SIZE;
    }

    const color = raw.body_color || raw.title_color || '#ffffff';

    return {
        body_color: color,
        title_color: color,
        body_size: size,
        title_size: size,
        align: raw.align || 'center',
        vertical_align: raw.vertical_align || 'center',
    };
}

export function slideBodyStyle(style) {
    const normalized = normalizeTextStyle(style);
    const size = Math.min(Math.max(parseInt(normalized.body_size, 10) || DEFAULT_BODY_SIZE, 12), 96);

    return `color:${normalized.body_color};font-size:${size}px`;
}

export function defaultTextStyle() {
    return normalizeTextStyle(null);
}
