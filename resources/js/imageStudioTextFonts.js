const loadedStylesheets = new Set();
const loadedGoogleFamilies = new Set();

export function fontCssFamily(fontMeta) {
    if (!fontMeta) {
        return 'Impact, Arial Black, sans-serif';
    }
    const family = fontMeta.family || fontMeta.label || 'sans-serif';
    if (String(family).includes('"') || String(family).includes(',')) {
        return family;
    }
    return `"${family}", sans-serif`;
}

export function injectStylesheet(url) {
    if (!url || loadedStylesheets.has(url)) {
        return;
    }
    loadedStylesheets.add(url);
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    document.head.appendChild(link);
}

function googleFontUrl(fontMeta, { bold = false, italic = false } = {}) {
    const family = encodeURIComponent(fontMeta.family).replace(/%20/g, '+');
    const weights = [...new Set(fontMeta.weights || [400, 700])].slice(0, 6);
    const pairs = new Set();
    weights.forEach((w) => {
        pairs.add(`0,${w}`);
        if (fontMeta.italic !== false) {
            pairs.add(`1,${w}`);
        }
    });
    if (italic) {
        const w = bold ? Math.max(...weights.filter((x) => x >= 600), 700) : (weights.includes(400) ? 400 : weights[0]);
        pairs.add(`1,${w}`);
    }
    if (bold) {
        const w = Math.max(...weights.filter((x) => x >= 600), 700);
        pairs.add(`0,${w}`);
    }
    return `https://fonts.googleapis.com/css2?family=${family}:ital,wght@${[...pairs].join(';')}&display=swap`;
}

export async function ensureFontLoaded(fontMeta, { bold = false, italic = false } = {}) {
    if (!fontMeta) {
        return;
    }

    if (fontMeta.source === 'cdn' && fontMeta.css) {
        injectStylesheet(fontMeta.css);
        await document.fonts.ready;
        return;
    }

    if (fontMeta.source === 'google') {
        const family = encodeURIComponent(fontMeta.family).replace(/%20/g, '+');
        const cacheKey = `${family}:full`;
        if (!loadedGoogleFamilies.has(cacheKey)) {
            loadedGoogleFamilies.add(cacheKey);
            injectStylesheet(googleFontUrl(fontMeta, { bold, italic }));
        } else if (italic || bold) {
            injectStylesheet(googleFontUrl(fontMeta, { bold, italic }));
        }
        await document.fonts.ready;
        return;
    }

    await document.fonts.ready;
}

export function resolveFontWeight(fontMeta, bold = false) {
    if (fontMeta?.source === 'icon') {
        return fontMeta.weight ?? 400;
    }
    if (!bold) {
        return 400;
    }
    const weights = fontMeta?.weights || [400, 700];
    const boldish = weights.filter((w) => w >= 600);
    return boldish.length ? Math.max(...boldish) : 700;
}

export function buildTextStyleFromFont(fontMeta, { bold = false, italic = false } = {}) {
    const isIcon = fontMeta?.source === 'icon';
    const style = {
        fontFamily: fontCssFamily(fontMeta),
        fontWeight: resolveFontWeight(fontMeta, bold),
        fontStyle: !isIcon && italic ? 'italic' : 'normal',
    };
    if (fontMeta?.material) {
        style.fontVariationSettings = "'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24";
    }
    return style;
}

export function preloadIconFontCdns(iconFonts = []) {
    iconFonts.forEach((f) => {
        if (f.css) {
            injectStylesheet(f.css);
        }
    });
}

export function preloadStarterGoogleFonts(fonts = [], limit = 12) {
    const google = fonts.filter((f) => f.source === 'google').slice(0, limit);
    if (!google.length) {
        return;
    }
    const parts = google.map((f) => {
        const family = encodeURIComponent(f.family).replace(/%20/g, '+');
        const weights = (f.weights || [400, 700]).slice(0, 4).join(';');
        return `family=${family}:wght@${weights}`;
    });
    const url = `https://fonts.googleapis.com/css2?${parts.join('&')}&display=swap`;
    injectStylesheet(url);
    google.forEach((f) => loadedGoogleFamilies.add(`${encodeURIComponent(f.family)}:preload`));
}

export function findFontBySlug(fonts, slug) {
    return (fonts || []).find((f) => f.slug === slug) || null;
}

/** Fallback mínimo se a API falhar */
export const FALLBACK_FONTS = [
    { slug: 'impact', label: 'Impact', group_label: 'Sistema', family: 'Impact', source: 'system', weights: [400, 700], italic: false },
    { slug: 'arial', label: 'Arial', group_label: 'Sistema', family: 'Arial', source: 'system', weights: [400, 700], italic: false },
    { slug: 'bebas_neue', label: 'Bebas Neue', group_label: 'Google', family: 'Bebas Neue', source: 'google', weights: [400], italic: false },
    { slug: 'montserrat', label: 'Montserrat', group_label: 'Google', family: 'Montserrat', source: 'google', weights: [400, 700], italic: true },
    { slug: 'oswald', label: 'Oswald', group_label: 'Google', family: 'Oswald', source: 'google', weights: [400, 700], italic: false },
    { slug: 'poppins', label: 'Poppins', group_label: 'Google', family: 'Poppins', source: 'google', weights: [400, 700], italic: true },
    { slug: 'roboto', label: 'Roboto', group_label: 'Google', family: 'Roboto', source: 'google', weights: [400, 700], italic: true },
    { slug: 'playfair_display', label: 'Playfair Display', group_label: 'Google', family: 'Playfair Display', source: 'google', weights: [400, 700], italic: true },
    { slug: 'fa_solid', label: 'Font Awesome Solid', group_label: 'Ícones', family: '"Font Awesome 6 Free"', source: 'icon', weight: 900, italic: false },
];

export function isTextLikeType(type) {
    const t = String(type || '').toLowerCase();
    return t === 'text' || t === 'i-text' || t === 'itext' || t === 'textbox';
}
