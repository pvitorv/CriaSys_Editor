import axios from 'axios';
import { formatNarrationText, parseScript } from './scriptParser';
import { applyAutomaticDurations, probeVideoFileDuration, DURATION_MIN, DURATION_MAX } from './slideDuration';
import {
    normalizeTextStyle as normalizeSlideTextStyle,
    slideBodyStyle as buildSlideBodyStyle,
    defaultTextStyle,
} from './slideTextStyle';
import { PreviewAudioMixer } from './previewAudio';
import { imageStudioMethods } from './imageStudio.js';

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

window.api = axios.create({
    baseURL: '/api',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

window.api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

window.editorApp = function (projectId, projectMeta = {}) {
    return {
        ...imageStudioMethods(),
        projectId,
        projectDescription: projectMeta.description || '',
        projectStatus: projectMeta.status || 'active',
        deployment: projectMeta.deployment || { is_online: false, is_desktop: true },
        slides: [],
        selectedSlide: null,
        activeTab: 'roteiro',
        editorTabs: [
            { id: 'roteiro', label: 'Roteiro' },
            { id: 'audio', label: 'Trilhas & FX' },
            { id: 'biblioteca', label: 'Biblioteca' },
            { id: 'image_studio', label: 'Image Studio' },
            { id: 'exportar', label: 'Exportar' },
            { id: 'thumbnail', label: 'Thumbnail' },
        ],
        selectedSoundEffectId: null,
        fullScript: '',
        scriptStats: null,
        scriptParseTimeout: null,
        voice: projectMeta.defaultVoice || 'onyx',
        ttsEngine: projectMeta.defaultTtsEngine || 'openai',
        ttsEngines: [],
        voices: [],
        voicesLoading: false,
        narration: null,
        narrationLoading: false,
        previewLoading: false,
        previewAudioUrl: null,
        previewPlaying: false,
        previewIndex: 0,
        previewTimer: null,
        previewTransitioning: false,
        previewTransitionKind: 'fade',
        previewPlayToken: 0,
        previewIgnoreVideoPause: false,
        previewPlayStartedPerf: null,
        previewPlayStartedAtSec: 0,
        previewSlideStartedPerf: null,
        previewSyncRaf: null,
        mediaQuery: '',
        mediaSource: 'all',
        mediaType: 'image',
        mediaPanel: 'search',
        mediaImportUrl: '',
        mediaImportPreview: null,
        mediaImportLoading: false,
        mediaUploadMeta: {
            item_title: '',
            author: '',
            attribution_text: '',
            requires_attribution: false,
            original_url: '',
            license_type: '',
            stock_license_id: null,
        },
        mediaUploadAttachToSlide: true,
        _mediaSearchSeq: 0,
        _mediaAutoSearchToken: 0,
        mediaLibraryProviders: null,
        mediaSfxStartAt: 0,
        mediaResults: [],
        mediaSearchPage: 1,
        mediaHasMore: false,
        mediaSearchExpanded: false,
        projectLibraryExpanded: false,
        projectLibraryAssets: [],
        projectLibraryLoading: false,
        mediaErrors: [],
        mediaSearching: false,
        exportPresets: [],
        exportPackages: [],
        downloads: [],
        selectedDownloadIds: [],
        platformDescriptions: {},
        platformDescDraft: '',
        platformDescKeys: ['youtube', 'youtube_shorts', 'tiktok', 'instagram_reels', 'instagram_feed'],
        selectedPlatformDesc: 'youtube',
        projectCreditsText: '',
        projectCreditsCount: 0,
        publishAuto: false,
        publishFiles: {},
        stockLicenses: [],
        stockLicenseProviders: [],
        stockLicenseForm: {
            provider: 'envato',
            project_title: '',
            license_url: '',
            license_note: '',
            is_default: true,
        },
        attachPaidLicenseOnUpload: true,
        audioTrack: { volume: 0.35, ducking_enabled: true },
        audioTracks: [],
        selectedMusicSlot: 0,
        soundEffects: [],
        mixVolumes: { narration: 1, music: 1, sfx: 1 },
        previewMixer: null,
        renderJobs: [],
        saving: false,
        message: '',
        error: '',
        pollInterval: null,
        saveTimeout: null,
        descriptionSaveTimeout: null,
        dragFromIndex: null,
        dragOverIndex: null,
        dragJustDropped: false,
        audioDragPayload: null,
        audioDropHover: null,
        audioDragMime: 'application/x-criasys-audio',
        timelinePointerDrag: null,
        timelineSnapStep: 0.5,
        burnSubtitles: false,
        previewShowSubtitles: true,
        timelineZoom: 18,
        timelineZoomManual: false,
        timelineExpanded: false,
        timelineZoomBeforeExpand: null,
        topPanelHeight: 0,
        timelineWidthRatio: 0.7,
        timelinePlayheadSec: 0,
        timelineTool: 'select',
        timelineSelectedClip: null,
        timelineCutMarkIn: null,
        timelineCutMarkOut: null,
        timelineSelectedClipLabel: '',
        thumbnailTemplates: [],
        thumbnailPlatforms: [],
        thumbnailFrames: [],
        thumbnailFrameCategories: {},
        selectedFrameCategory: 'all',
        thumbnailFrameSearch: '',
        frameManageMode: false,
        frameLibraryHiddenFrames: [],
        frameLibraryHiddenCategories: [],
        frameCustomCategories: {},
        newFrameCollectionName: '',
        newCustomFrameName: '',
        newCustomFrameCategory: 'personalizado',
        thumbnailPreviewPanY: 0,
        thumbnailFonts: [],
        selectedThumbnailPlatform: 'youtube_landscape',
        thumbnailSettingsByPlatform: {},
        thumbnailPreviewUrls: {},
        thumbnailSettings: {
            template: 'youtube_pro',
            platform_preset: 'youtube_landscape',
            image_source: 'slide',
            custom_image_path: null,
            slide_index: 0,
            title_text: '',
            subtitle_text: '',
            title_color: '#ffffff',
            subtitle_color: '#f4f4f5',
            accent_color: '#ef4444',
            accent_opacity: 0,
            background_color: '#09090b',
            background_opacity: 0,
            font_family: 'impact',
            title_size: 72,
            subtitle_size: 34,
            brightness: 0,
            contrast: 5,
            overlay_opacity: 50,
            text_align: 'left',
            vertical_align: 'bottom',
            frame_slug: 'none',
            frame_color: '#ffffff',
            frame_secondary_color: '#ef4444',
            frame_width: 28,
            frame_opacity: 100,
            frame_inset: 12,
        },
        thumbnailPreviewUrl: null,
        thumbnailSaving: false,
        thumbnailPreviewTimeout: null,
        thumbnailTextPreviewTimeout: null,
        thumbnailTextEditing: false,

        get thumbnailPreviewAspectClass() {
            const platform = this.thumbnailPlatforms.find((p) => p.slug === this.selectedThumbnailPlatform);
            if (!platform) return 'aspect-video w-full';
            if (platform.aspect === '9:16') return 'aspect-[9/16] w-full';
            if (platform.aspect === '1:1') return 'aspect-square w-full max-w-[360px] mx-auto';

            return 'aspect-video w-full';
        },

        isCustomFrameCategory(slug) {
            return Boolean(slug && String(slug).startsWith('custom_cat_'));
        },

        canDeleteFrameCategory(slug) {
            if (!slug || slug === 'all' || slug === 'basico' || slug === 'personalizado') {
                return false;
            }

            return this.isCustomFrameCategory(slug) || this.frameManageMode;
        },

        frameCategoryLabel(slug) {
            if (this.frameCustomCategories[slug]?.label) {
                return this.frameCustomCategories[slug].label;
            }

            return this.thumbnailFrameCategories[slug] || slug;
        },

        get filteredThumbnailFrames() {
            let list = this.thumbnailFrames;

            if (this.selectedFrameCategory !== 'all') {
                list = list.filter((f) => f.category === this.selectedFrameCategory);
            }

            const q = (this.thumbnailFrameSearch || '').trim().toLowerCase();
            if (q) {
                list = list.filter(
                    (f) =>
                        (f.name || '').toLowerCase().includes(q)
                        || (f.description || '').toLowerCase().includes(q)
                        || (f.category_label || '').toLowerCase().includes(q)
                        || (f.creator || '').toLowerCase().includes(q)
                );
            }

            return list;
        },

        get thumbnailFontGroups() {
            const groups = {};
            this.thumbnailFonts.forEach((font) => {
                const name = font.group || 'Outras';
                if (!groups[name]) {
                    groups[name] = [];
                }
                groups[name].push(font);
            });

            const order = ['Destaque', 'Sans-serif', 'Serif', 'Pop & criativo', 'Tech & código', 'Outras'];

            return order
                .filter((name) => groups[name]?.length)
                .concat(Object.keys(groups).filter((k) => !order.includes(k)))
                .map((name) => ({ name, fonts: groups[name] }));
        },

        framePreviewStyle(frame) {
            if (frame?.type === 'overlay_image' && frame?.preview_url) {
                return `background-image: url('${frame.preview_url}'); background-size: contain; background-repeat: no-repeat; background-position: center; background-color: #18181b`;
            }

            const primary = frame?.default_color || this.thumbnailSettings.frame_color || '#ffffff';
            const secondary = this.thumbnailSettings.frame_secondary_color || '#ef4444';
            const style = frame?.style || 'solid';
            const slug = frame?.slug || 'none';

            if (slug === 'none') {
                return 'border: 1px dashed #52525b; background: linear-gradient(135deg, #27272a 25%, #18181b 25%, #18181b 50%, #27272a 50%, #27272a 75%, #18181b 75%); background-size: 8px 8px';
            }

            const byStyle = {
                solid: `border: ${style === 'solid' ? '3px' : '2px'} solid ${primary}`,
                double: `border: 3px double ${primary}`,
                triple: `border: 2px solid ${primary}; outline: 2px solid ${secondary}; outline-offset: 2px`,
                dashed: `border: 2px dashed ${primary}`,
                dotted: `border: 3px dotted ${primary}`,
                inset_mat: `box-shadow: inset 0 0 0 12px ${primary}33; border: 2px solid ${primary}`,
                rounded: `border: 2px solid ${primary}; border-radius: 12px`,
                rounded_thick: `border: 5px solid ${primary}; border-radius: 16px`,
                pill_inset: `border: 3px solid ${primary}; border-radius: 999px`,
                offset_shadow: `border: 2px solid ${primary}; box-shadow: 4px 4px 0 ${secondary}`,
                side_bars: `border-left: 8px solid ${primary}; border-right: 8px solid ${secondary}`,
                top_bottom_bars: `border-top: 6px solid ${primary}; border-bottom: 6px solid ${secondary}`,
                gradient_border: `border: 3px solid transparent; background: linear-gradient(#18181b,#18181b) padding-box, linear-gradient(135deg,${primary},${secondary}) border-box`,
                split_duotone: `border: 3px solid ${primary}; background: linear-gradient(180deg, ${primary}22 50%, ${secondary}22 50%)`,
                letterbox: 'border-top: 14px solid #000; border-bottom: 14px solid #000',
                film_strip: 'border-top: 10px solid #333; border-bottom: 10px solid #333; background-image: radial-gradient(circle, #111 30%, transparent 30%); background-size: 12px 10px; background-position: 0 0, 0 100%',
                broadcast: 'border-top: 8px solid #1e1e28; border-bottom: 8px solid #1e1e28; box-shadow: inset 0 0 0 2px #ef4444',
                viewfinder: `box-shadow: inset 0 0 0 1px ${primary}88; border: 2px solid ${primary}44`,
                rec_dot: `border: 2px solid ${primary}; box-shadow: inset 12px 12px 0 -8px #dc2626`,
                scope_bars: 'border-top: 16px solid #000; border-bottom: 16px solid #000',
                polaroid: 'border: 6px solid #fafaf9; border-bottom-width: 22px',
                ornate_corners: `border: 2px solid ${primary}; box-shadow: inset 8px 8px 0 -6px ${primary}, inset -8px -8px 0 -6px ${primary}`,
                ticket: `border: 3px dashed ${primary}`,
                scotch_tape: 'border: 2px solid #d4d4d8; box-shadow: 8px -4px 0 #f5f0dc88',
                newspaper: 'border: 2px solid #404040; border-top-width: 6px',
                vignette_warm: 'box-shadow: inset 0 0 24px 8px #78350f88',
                neon_glow: `border: 2px solid ${primary}; box-shadow: 0 0 10px ${primary}, inset 0 0 6px ${primary}44`,
                neon_double: `border: 2px solid ${primary}; box-shadow: 0 0 8px ${primary}, 0 0 0 3px ${secondary}`,
                cyber_grid: `background-image: linear-gradient(${primary}33 1px, transparent 1px), linear-gradient(90deg, ${primary}33 1px, transparent 1px); background-size: 10px 10px; border: 1px solid ${primary}`,
                rgb_segments: 'border: 2px solid; border-image: linear-gradient(90deg,#ff0050,#00ff88,#0088ff) 1',
                pulse_ring: `border: 2px solid ${primary}; outline: 2px solid ${secondary}66; outline-offset: 3px`,
                gold_double: 'border: 3px solid #d4af37; box-shadow: inset 0 0 0 1px #fff8dc',
                gold_ornate: 'border: 3px solid #c9a227; box-shadow: inset 0 0 0 1px #fde68a',
                chrome: 'border: 3px solid; border-image: linear-gradient(135deg,#e5e5e5,#888,#e5e5e5) 1',
                marble_mat: 'border: 3px solid #d4d4d8; background: linear-gradient(135deg,#fafafa,#e5e5e5)',
                luxury_inset: `border: 2px solid ${primary}; box-shadow: inset 0 0 20px #00000088`,
                ig_gradient_ring: 'border: 3px solid transparent; background: linear-gradient(#18181b,#18181b) padding-box, linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888) border-box',
                tiktok_offset: `border: 2px solid ${primary}; box-shadow: 3px 3px 0 ${secondary}`,
                yt_accent: `border: 2px solid #fff; border-bottom: 5px solid ${primary}`,
                stories_gradient: 'border: 3px solid transparent; background: linear-gradient(#18181b,#18181b) padding-box, linear-gradient(45deg,#833ab4,#fd1d1d,#fcb045) border-box; border-radius: 12px',
                safe_zone: `border: 2px dashed ${primary}88`,
                corner_brackets: `box-shadow: inset 10px 10px 0 -8px ${primary}, inset -10px -10px 0 -8px ${primary}`,
                crosshair: `background: radial-gradient(circle, transparent 40%, ${primary}22 41%, transparent 42%)`,
                scanlines: 'background: repeating-linear-gradient(0deg, transparent, transparent 2px, #00000044 2px, #00000044 4px)',
                circuit_corners: `border: 1px solid ${primary}66; box-shadow: inset 0 0 0 2px ${secondary}44`,
                data_hud: `border: 1px solid ${primary}; box-shadow: inset 0 0 0 1px ${primary}44`,
                diagonal_stripes: `background: repeating-linear-gradient(45deg, ${primary}33, ${primary}33 4px, transparent 4px, transparent 8px); border: 2px solid ${secondary}`,
                zigzag: `border-bottom: 4px solid ${primary}`,
                star_corners: `border: 2px solid ${primary}`,
                diamond_corners: `border: 2px solid ${primary}`,
                brush_edges: `border-top: 4px solid ${primary}; border-bottom: 4px solid ${secondary}`,
                torn_paper: 'border-top: 3px wavy #faf8f0; border-bottom: 3px wavy #faf8f0',
                comic: 'border: 5px solid #000',
                rainbow: 'border: 3px solid; border-image: linear-gradient(90deg,red,orange,yellow,green,blue,violet) 1',
                magazine_bleed: `border-left: 5px solid ${primary}`,
                headline_bar: `border-top: 10px solid ${primary}; border-bottom: 2px solid ${secondary}`,
                column_gutter: `border: 2px solid ${primary}; background: linear-gradient(90deg, transparent 32%, ${primary}22 33%, transparent 34%, transparent 65%, ${primary}22 66%, transparent 67%)`,
                photo_credit: `border: 2px solid ${primary}; box-shadow: inset -20px -8px 0 -4px #00000066`,
                gallery_white: 'border: 8px solid #fff; box-shadow: inset 0 0 0 1px #d4d4d8',
                beveled_3d: `border: 3px solid ${primary}; box-shadow: inset 2px 2px 0 #ffffff44, inset -2px -2px 0 #00000044`,
                depth_shadow: `border: 2px solid ${primary}; box-shadow: 4px 4px 0 #00000088, 8px 8px 0 #00000044`,
                thin_hairline: `border: 1px solid ${primary}`,
                corner_dots: `border: 1px solid ${primary}; background: radial-gradient(circle at 8px 8px, ${secondary} 3px, transparent 3px)`,
                spotlight_vignette: `box-shadow: inset 0 0 30px 10px ${primary}66`,
                glass_border: `border: 2px solid ${primary}66; border-radius: 10px; backdrop-filter: blur(4px); background: #ffffff11`,
                nested_frame: `border: 3px solid ${primary}; outline: 2px solid ${secondary}; outline-offset: 4px`,
                glow_soft: `border: 1px solid ${primary}; box-shadow: 0 0 16px ${primary}88`,
                gradient_vignette: `box-shadow: inset 0 0 30px 8px ${primary}55, inset 0 0 50px 20px ${secondary}33`,
                holographic_border: 'border: 3px solid; border-image: linear-gradient(90deg,#ff0080,#00c8ff,#ffcc00,#8000ff) 1',
                dual_corner_accent: `border: 1px solid ${primary}; background: linear-gradient(135deg, ${secondary}44 8px, transparent 8px)`,
                cinematic_ultra: 'border-top: 18px solid #000; border-bottom: 18px solid #000',
                monitor_bezel: 'border: 10px solid #1e1e23; box-shadow: inset 0 -6px 0 -4px #3cb371',
                breaking_news: `border-top: 12px solid ${primary}; border-bottom: 2px solid ${secondary}`,
                vhs_retro: 'border: 2px solid #888; background: repeating-linear-gradient(0deg, transparent, transparent 2px, #00000033 2px, #00000033 3px)',
                stamp_seal: `border: 2px solid ${primary}; background: radial-gradient(circle at 90% 10%, ${primary}44 0, transparent 30%)`,
                glitch_chroma: `border: 2px solid ${primary}; box-shadow: -2px 0 0 #00ffff88, 2px 0 0 #ff00ff88`,
                corporate_accent: `border: 1px solid ${primary}; border-left: 4px solid ${secondary}; border-bottom: 4px solid ${primary}`,
                sport_diagonal: `background: linear-gradient(135deg, ${primary} 0, ${primary} 12%, transparent 12%); border: 2px solid ${primary}`,
                ribbon_corner: `border: 1px solid #ccc; background: linear-gradient(135deg, ${primary} 0, ${primary} 30%, transparent 30%)`,
                barcode_strip: `border: 1px solid ${primary}; background: repeating-linear-gradient(90deg, ${primary} 0 1px, transparent 1px 3px) bottom / 40% 8px no-repeat`,
                podcast_wave: `border: 2px solid ${primary}; border-radius: 8px; background: linear-gradient(to top, ${primary}44 0, transparent 20%)`,
                confetti_dots: `border: 1px solid #fff3; background: radial-gradient(circle, #ef4444 2px, transparent 2px) 4px 4px / 12px 12px`,
                halftone_edge: `border: 2px solid ${primary}; background: radial-gradient(circle, ${primary} 1.5px, transparent 1.5px) 0 0 / 6px 6px`,
                frost_ice: `border: 2px solid ${primary}; box-shadow: inset 0 8px 12px -6px #e0f2fe88`,
                fire_warm: `border: 2px solid #ff8c00; box-shadow: 0 0 12px #ff450088, inset 0 0 8px #ff660044`,
                comic_yellow_red: 'border: 6px solid #000; box-shadow: inset 0 0 0 3px #fbbf24; border-bottom: 8px solid #dc2626',
                ray_burst: `background: repeating-conic-gradient(from -90deg at 50% 50%, ${primary}cc 0deg 6deg, #ffffffcc 6deg 12deg, ${secondary}aa 12deg 18deg, transparent 18deg 24deg); border: 6px solid #000`,
                vs_diagonal_split: `background: linear-gradient(135deg, ${primary}88 50%, ${secondary}88 50%); border: 3px solid #fff`,
                speech_bubble_corner: `border: 5px solid #000; border-radius: 40% 40% 40% 10%; background: radial-gradient(ellipse at 20% 20%, #fff 30%, transparent 31%)`,
                comic_bubble_round: 'border: 5px solid #000; background: radial-gradient(ellipse at 18% 22%, #fff 35%, transparent 36%)',
                comic_bubble_shout: 'border: 5px solid #000; background: radial-gradient(circle at 28% 22%, #fff 22%, transparent 23%), repeating-conic-gradient(from 0deg at 28% 22%, #fff 0 8deg, transparent 8deg 16deg)',
                comic_bubble_thought: 'border: 4px solid #000; background: radial-gradient(circle at 20% 18%, #fff 18%, transparent 19%), radial-gradient(circle at 14% 28%, #fff 8%, transparent 9%)',
                manga_bubble: 'border: 6px solid #000; background: radial-gradient(ellipse at 70% 18%, #fff 28%, transparent 29%)',
                manga_scream: 'border: 7px solid #000; background: radial-gradient(circle at 72% 28%, #fff 20%, transparent 21%), repeating-conic-gradient(from 0deg at 72% 28%, #fff 0 6deg, transparent 6deg 12deg)',
                comic_bubble_double: 'border: 5px solid #000; background: radial-gradient(ellipse at 15% 20%, #fff 22%, transparent 23%), radial-gradient(ellipse at 65% 28%, #fff 24%, transparent 25%)',
                comic_narrator_box: 'border: 5px solid #000; border-top: 16px solid #fff; box-shadow: inset 0 12px 0 #fff',
                comic_panel_bubbles: 'border: 6px solid #000; background: linear-gradient(#0002 1px, transparent 1px), linear-gradient(90deg, #0002 1px, transparent 1px); background-size: 50% 50%',
                ndn_navy_brand: `border: 1px solid #fff3; border-bottom: 10px solid ${primary}; box-shadow: inset 4px 0 0 ${secondary}`,
                ndn_growth_stripe: `border-left: 4px solid ${secondary}; border-bottom: 6px solid ${primary}`,
                ide_titlebar: 'border-top: 14px solid #27272a; border: 2px solid #38bdf8; box-shadow: inset 16px 6px 0 -10px #ff5f57, inset 32px 6px 0 -10px #febc2e',
                cftv_orange_brackets: `border: 2px solid ${primary}; box-shadow: inset 10px 10px 0 -8px ${primary}, inset -10px -10px 0 -8px ${secondary}`,
                horror_crimson: `box-shadow: inset 0 0 40px 15px #450a0a; border: 3px solid ${primary}`,
                chalkboard_edu: 'border: 8px solid #78350f; box-shadow: inset 0 0 0 4px #166534',
            };

            return byStyle[style] || `border: 3px solid ${primary}; outline: 1px solid ${secondary}40`;
        },

        get filteredThumbnailTemplates() {
            const platform = this.thumbnailPlatforms.find((p) => p.slug === this.selectedThumbnailPlatform);
            const isVertical = platform?.aspect === '9:16';
            const isSquare = platform?.aspect === '1:1';

            return this.thumbnailTemplates.filter((tpl) => {
                if (isVertical) {
                    return tpl.category === 'vertical' || tpl.category === 'profissional' || tpl.category === 'básico';
                }
                if (isSquare) {
                    return tpl.category === 'quadrado' || tpl.category === 'profissional' || tpl.category === 'básico';
                }

                return tpl.category !== 'vertical' && tpl.category !== 'quadrado';
            });
        },

        get previewSlide() {
            if (this.previewPlaying && this.slides.length) {
                return this.slides[this.previewIndex] ?? this.selectedSlide;
            }
            return this.selectedSlide;
        },

        get previewDisplayText() {
            const slide = this.previewSlide;

            if (slide) {
                const body = (slide.body_text || '').trim();
                if (body) return body;

                const narr = (slide.narration_text || '').trim();
                if (narr) return narr;

                return '';
            }

            if (this.fullScript?.trim()) return this.fullScript.trim();
            if (this.narration?.full_script?.trim()) return this.narration.full_script.trim();

            return '';
        },

        get previewVisibleText() {
            if (!this.previewShowSubtitles) {
                return '';
            }

            return this.previewDisplayText;
        },

        get previewHasMediaBackground() {
            const slide = this.previewSlide;

            return !!(slide?.video_url || slide?.image_url);
        },

        get canPlayPreview() {
            if (this.slides.length > 0) return true;

            return !!this.fullScript?.trim() || !!this.narration?.audio_url;
        },

        get previewModeLabel() {
            if (!this.slides.length) return 'Roteiro / narração';
            if (this.previewPlaying) {
                return `Slide ${this.previewIndex + 1}/${this.slides.length} · ${this.formatTimelineTime(this.timelinePlayheadSec)}`;
            }
            return '';
        },

        get defaultStockLicense() {
            return this.stockLicenses.find((r) => r.is_default) || this.stockLicenses[0] || null;
        },

        get stockLicenseProviderHint() {
            const slug = this.stockLicenseForm.provider;
            const meta = this.stockLicenseProviders.find((p) => p.slug === slug);

            return meta?.project_hint || '';
        },

        get selectedTtsEngineMeta() {
            return this.ttsEngines.find((e) => e.slug === this.ttsEngine) || null;
        },

        get timelineTotalSeconds() {
            return this.slides.reduce((sum, s) => sum + parseFloat(s.duration_seconds || 5), 0);
        },

        get timelineTrackWidthPx() {
            const gaps = Math.max(0, this.slides.length - 1) * 8;
            const clipsWidth = this.slides.reduce(
                (sum, s) => sum + this.timelineClipWidth(s),
                0,
            ) + gaps + 16;
            const timeWidth = this.timelineTotalSeconds * this.timelineZoom + gaps + 16;
            const content = Math.max(clipsWidth, timeWidth);

            if (this.timelineExpanded) {
                return content;
            }

            return Math.max(content, this.timelineViewportWidthPx());
        },

        get timelineTicks() {
            const total = this.timelineTotalSeconds;
            const step = total > 120 ? 30 : total > 60 ? 15 : total > 20 ? 5 : 2;
            const ticks = [];
            for (let sec = 0; sec <= total + 0.01; sec += step) {
                ticks.push({
                    sec,
                    px: sec * this.timelineZoom,
                    label: this.formatTimelineTime(sec),
                });
            }
            return ticks;
        },

        get audioModulesCount() {
            const tracks = this.audioTracks.filter((t) => t.file_path).length;

            return tracks + this.soundEffects.length;
        },

        get showTimelineAudioLanes() {
            return this.slides.length > 0
                || this.audioTracks.some((t) => t.file_path)
                || this.soundEffects.length > 0
                || !!this.narration?.audio_url;
        },

        async init() {
            if (typeof this.seedImageStudioFromEmbedded === 'function') {
                this.seedImageStudioFromEmbedded({});
            }
            await Promise.all([
                this.loadSlides(),
                this.loadNarration(),
                this.loadRenderJobs(),
                this.loadDownloads(),
                this.loadExportPresets(),
                this.loadExportPackages(),
                this.loadAudioTracks(),
                this.loadSoundEffects(),
                this.loadMediaProviders(),
                this.loadTtsEngines(),
                this.loadProjectCredits(),
                this.loadPlatformDescriptions(),
                this.loadStockLicenses(),
                this.loadProjectLibraryAssets(),
            ]);
            await this.loadThumbnailCatalog();
            await this.loadThumbnailSettings();
            await this.loadVoices();
            await this.syncPublish();
            this.previewMixer = new PreviewAudioMixer();
            this.pollInterval = setInterval(() => {
                this.loadRenderJobs();
                this.loadExportPackages();
                this.loadDownloads();
            }, 3000);

            document.addEventListener('keydown', (e) => this.handleShortcut(e));
            this._boundTimelinePointerMove = (e) => this.handleTimelinePointerMove(e);
            this._boundTimelinePointerUp = (e) => this.handleTimelinePointerUp(e);
            this.syncTimelineZoomToViewport();
            this.initTopPanelHeightSync();
            this.initTimelineResizeObserver();
        },

        initTimelineResizeObserver() {
            if (typeof ResizeObserver === 'undefined') {
                return;
            }

            this._timelineRo?.disconnect();
            this._timelineRo = new ResizeObserver(() => {
                if (this.timelineExpanded) {
                    this.syncTimelineFitAll();
                } else if (!this.timelineZoomManual) {
                    this.syncTimelineZoomToViewport();
                }
            });

            this.$nextTick(() => {
                const el = this.$refs.timelineScroll;
                if (el) {
                    this._timelineRo.observe(el);
                }
            });
        },

        syncTopPanelHeight() {
            this.$nextTick(() => {
                const el = this.$refs.previewColumn;
                if (el) {
                    this.topPanelHeight = Math.round(el.getBoundingClientRect().height);
                }
            });
        },

        initTopPanelHeightSync() {
            this.syncTopPanelHeight();
            const el = this.$refs.previewColumn;
            if (!el || typeof ResizeObserver === 'undefined') {
                return;
            }
            this._topPanelRo?.disconnect();
            this._topPanelRo = new ResizeObserver(() => this.syncTopPanelHeight());
            this._topPanelRo.observe(el);
        },

        timelineViewportWidthPx() {
            if (typeof window === 'undefined') {
                return 960;
            }
            const root = this.$el?.closest?.('[x-data]') || this.$el;
            const measured = root?.clientWidth;
            const fromViewport = Math.floor(window.innerWidth * this.timelineWidthRatio) - 48;

            return Math.max(320, measured || fromViewport);
        },

        syncTimelineZoomToViewport() {
            if (this.timelineZoomManual || !this.slides.length) {
                return;
            }

            this.$nextTick(() => {
                const viewport = this.timelineViewportWidthPx();
                const gaps = Math.max(0, this.slides.length - 1) * 8;
                const total = this.timelineTotalSeconds;
                if (total <= 0 || viewport <= 0) {
                    return;
                }

                const fit = (viewport - gaps - 32) / total;
                this.timelineZoom = Math.min(72, Math.max(6, Math.round(fit * 10) / 10));
            });
        },

        resetTimelineZoom() {
            if (this.timelineExpanded) {
                this.timelineExpanded = false;
                this.timelineZoomBeforeExpand = null;
            }
            this.timelineZoomManual = false;
            this.syncTimelineZoomToViewport();
        },

        timelineClipsRowWidthPx(zoom = null) {
            const z = zoom ?? this.timelineZoom;
            if (!this.slides.length) {
                return 16;
            }

            const gaps = Math.max(0, this.slides.length - 1) * 8;
            let width = 16;
            for (const slide of this.slides) {
                const dur = parseFloat(slide.duration_seconds || 5);
                width += Math.max(80, dur * z) + 8;
            }

            return width - 8;
        },

        syncTimelineFitAll() {
            if (!this.timelineExpanded || !this.slides.length) {
                return;
            }

            this.$nextTick(() => {
                const scroller = this.$refs.timelineScroll;
                if (!scroller) {
                    return;
                }

                const viewport = Math.max(200, scroller.clientWidth - 32);
                let lo = 4;
                let hi = 72;

                for (let i = 0; i < 28; i++) {
                    const mid = (lo + hi) / 2;
                    if (this.timelineClipsRowWidthPx(mid) <= viewport) {
                        lo = mid;
                    } else {
                        hi = mid;
                    }
                }

                this.timelineZoom = Math.max(4, Math.round(lo * 10) / 10);
                this.timelineZoomManual = true;
                scroller.scrollLeft = 0;
                scroller.scrollTop = 0;
            });
        },

        toggleTimelineExpanded() {
            if (this.timelineExpanded) {
                this.timelineExpanded = false;
                if (this.timelineZoomBeforeExpand != null) {
                    this.timelineZoom = this.timelineZoomBeforeExpand;
                }
                this.timelineZoomBeforeExpand = null;
                this.timelineZoomManual = false;
                this.syncTimelineZoomToViewport();
                return;
            }

            this.timelineZoomBeforeExpand = this.timelineZoom;
            this.timelineExpanded = true;
            this.syncTimelineFitAll();
        },

        togglePreviewSubtitles() {
            this.previewShowSubtitles = !this.previewShowSubtitles;
            if (this.previewShowSubtitles) {
                this.burnSubtitles = true;
                this.message = 'Preview com legendas na tela — ao exportar, deixe «Queimar legendas» marcado para baixar igual.';
            } else {
                this.burnSubtitles = false;
                this.message = 'Preview sem legendas — vídeo limpo. Ao exportar, desmarque «Queimar legendas» para baixar sem texto queimado.';
            }
        },

        previewOverlayStyle() {
            if (!this.previewShowSubtitles && this.previewHasMediaBackground) {
                return 'background: transparent';
            }
            if (this.previewHasMediaBackground) {
                return 'background: rgba(0,0,0,0.45)';
            }

            return 'background: #000';
        },

        afterSlidesLayoutChanged() {
            if (this.timelineExpanded) {
                this.syncTimelineFitAll();
            } else if (!this.timelineZoomManual) {
                this.syncTimelineZoomToViewport();
            }
        },

        handleShortcut(e) {
            const tag = e.target?.tagName?.toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || tag === 'button' || e.target?.isContentEditable) {
                return;
            }

            if (e.code === 'Space' || e.key === ' ') {
                if (this.canPlayPreview && this.slides.length) {
                    e.preventDefault();
                    if (this.previewPlaying) {
                        this.stopSlideshow();
                    } else {
                        this.playSlideshow();
                    }
                }
                return;
            }

            if (this.slides.length && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
                e.preventDefault();
                this.seekTimelineBy(e.key === 'ArrowLeft' ? -1 : 1);
                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveSlide();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                this.addSlide();
            }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                this.syncNarration();
            }
        },

        seekTimelineBy(deltaSec) {
            const wasPlaying = this.previewPlaying;
            if (wasPlaying) {
                this.syncTimelinePlayheadFromPreview();
                this.cleanupPreviewPlayback();
                this.previewPlaying = false;
                this.previewPlayStartedPerf = null;
            }

            const next = Math.max(
                0,
                Math.min(this.timelineTotalSeconds, this.timelinePlayheadSec + deltaSec),
            );
            this.timelinePlayheadSec = Math.round(next * 100) / 100;
            this.seekPreviewToTimelineSec(this.timelinePlayheadSec);
            this.scrollTimelineToPlayhead('smooth');

            if (wasPlaying) {
                this.previewPlaying = true;
                this.previewPlayStartedAtSec = this.timelinePlayheadSec;
                this.previewPlayStartedPerf = performance.now();
                const { offsetInSlide } = this.timelineSecToSlideLocation(this.timelinePlayheadSec);
                this.schedulePreviewAdvance(offsetInSlide);
                this.startPreviewAudioMix();
                this.startPreviewTimelineSync();
            }
        },

        dragStart(index) {
            this.dragFromIndex = index;
            this.dragOverIndex = index;
        },

        dragEnterSlide(index) {
            if (this.dragFromIndex === null) {
                return;
            }
            this.dragOverIndex = index;
        },

        dragEndSlide() {
            this.dragFromIndex = null;
            this.dragOverIndex = null;
        },

        dropSlide(toIndex) {
            if (this.dragFromIndex === null || this.dragFromIndex === toIndex) {
                this.dragEndSlide();
                return;
            }
            const moved = this.slides.splice(this.dragFromIndex, 1)[0];
            this.slides.splice(toIndex, 0, moved);
            this.dragJustDropped = true;
            setTimeout(() => {
                this.dragJustDropped = false;
            }, 200);
            this.dragEndSlide();
            this.persistSlideOrder();
        },

        timelineSlideClick(slide) {
            if (this.dragJustDropped) {
                return;
            }
            this.selectSlide(slide);
        },

        async persistSlideOrder() {
            try {
                const { data } = await api.put(`/projects/${this.projectId}/slides/reorder`, {
                    slide_ids: this.slides.map(s => s.id),
                });
                this.slides = data.map(s => this.enrichSlide(s));
                this.syncSelection();
                this.afterSlidesLayoutChanged();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao reordenar slides';
                await this.loadSlides();
            }
        },

        scheduleSave() {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => this.saveSlide(), 600);
        },

        syncSelection() {
            if (this.selectedSlide?.id) {
                const fresh = this.slides.find(s => s.id === this.selectedSlide.id);
                this.selectedSlide = fresh ?? this.slides[0] ?? null;
            } else {
                this.selectedSlide = this.slides[0] ?? null;
            }
        },

        slidePreviewText(slide, index = 0) {
            if (!slide) return `Slide ${index + 1}`;
            const body = (slide.body_text || '').trim();
            if (body) {
                const oneLine = body.replace(/\s+/g, ' ');
                return oneLine.length > 72 ? `${oneLine.slice(0, 72)}…` : oneLine;
            }
            const narr = (slide.narration_text || '').trim();
            if (narr) {
                const oneLine = narr.replace(/\s+/g, ' ');
                return oneLine.length > 72 ? `${oneLine.slice(0, 72)}…` : oneLine;
            }
            return slide.title?.trim() || `Slide ${index + 1}`;
        },

        slideSearchQuery(slide) {
            const body = (slide?.body_text || '').trim();
            if (body) return body.replace(/\s+/g, ' ').slice(0, 120);
            const narr = (slide?.narration_text || '').trim();
            if (narr) return narr.replace(/\s+/g, ' ').slice(0, 120);
            return (slide?.title || '').trim();
        },

        slidePayload(slide) {
            return {
                title: slide.title ?? '',
                subtitle: slide.subtitle ?? '',
                body_text: slide.body_text ?? '',
                narration_text: slide.narration_text ?? '',
                duration_seconds: slide.duration_seconds ?? 5,
                duration_mode: slide.duration_mode ?? 'narration',
                video_duration_seconds: slide.video_duration_seconds ?? null,
                transition_type: slide.transition_type ?? 'fade',
                text_style: this.normalizeTextStyle(slide.text_style),
                image_path: slide.image_path ?? null,
                video_path: slide.video_path ?? null,
            };
        },

        async loadTtsEngines() {
            const { data } = await api.get('/tts/engines');
            this.ttsEngines = data;

            const recommended = data.find((e) => e.recommended && e.available);
            const preferred = ['openai', 'piper', 'edge', 'elevenlabs'];
            const current = data.find((e) => e.slug === this.ttsEngine && e.available);

            if (!current) {
                const pick = recommended
                    || preferred.map((slug) => data.find((e) => e.slug === slug && e.available)).find(Boolean);
                if (pick) this.ttsEngine = pick.slug;
            } else if (this.ttsEngine === 'edge') {
                const better = data.find((e) => e.slug === 'piper' && e.available);
                if (better) this.ttsEngine = better.slug;
            }
        },

        async loadVoices() {
            this.voicesLoading = true;
            try {
                const { data } = await api.get(`/tts/engines/${this.ttsEngine}/voices`);
                this.voices = data;
                if (data.length && !data.some(v => v.id === this.voice)) {
                    this.voice = data[0].id;
                }
            } catch (e) {
                this.voices = [];
            } finally {
                this.voicesLoading = false;
            }
        },

        async onEngineChange() {
            this.voice = '';
            await this.loadVoices();
        },

        async loadExportPresets() {
            const { data } = await api.get('/export-presets');
            this.exportPresets = data;
        },

        async loadDownloads() {
            const { data } = await api.get(`/projects/${this.projectId}/downloads`);
            this.downloads = data;
        },

        toggleDownload(id) {
            if (this.selectedDownloadIds.includes(id)) {
                this.selectedDownloadIds = this.selectedDownloadIds.filter(x => x !== id);
            } else {
                this.selectedDownloadIds.push(id);
            }
        },

        selectAllReadyDownloads() {
            this.selectedDownloadIds = this.downloads
                .filter(d => d.status === 'ready' && d.url)
                .map(d => d.id);
        },

        downloadSelected() {
            const items = this.downloads.filter(
                d => this.selectedDownloadIds.includes(d.id) && d.url
            );
            if (!items.length) {
                this.error = 'Selecione ao menos um arquivo pronto para download.';
                return;
            }
            items.forEach(item => {
                const a = document.createElement('a');
                a.href = item.url;
                a.download = item.filename || '';
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                a.remove();
            });
            this.message = `${items.length} arquivo(s) baixado(s)`;
        },

        formatBytes(bytes) {
            if (!bytes) return '—';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        async loadStockLicenses() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/stock-licenses`);
                this.stockLicenses = data.registrations || [];
                this.stockLicenseProviders = data.providers || [];
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar licenças';
            }
        },

        providerLabel(slug) {
            const meta = this.stockLicenseProviders.find((p) => p.slug === slug);

            return meta?.name || slug;
        },

        async saveStockLicense() {
            const title = this.stockLicenseForm.project_title.trim();
            if (!title) {
                this.error = 'Informe o nome do projeto na plataforma (ex.: nome do projeto Envato).';

                return;
            }

            try {
                await api.post(`/projects/${this.projectId}/stock-licenses`, this.stockLicenseForm);
                this.stockLicenseForm = {
                    provider: this.stockLicenseForm.provider,
                    project_title: '',
                    license_url: '',
                    license_note: '',
                    is_default: !this.stockLicenses.length,
                };
                await this.loadStockLicenses();
                await this.syncPublish();
                this.message = 'Licença cadastrada — uploads manuais usarão este registro.';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao cadastrar licença';
            }
        },

        async setDefaultStockLicense(reg) {
            try {
                await api.put(`/projects/${this.projectId}/stock-licenses/${reg.id}`, { is_default: true });
                await this.loadStockLicenses();
                this.message = `${this.providerLabel(reg.provider)} definido como licença padrão.`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao definir licença padrão';
            }
        },

        async applyStockLicenseToLocal(reg) {
            try {
                const { data } = await api.post(
                    `/projects/${this.projectId}/stock-licenses/${reg.id}/apply-local`
                );
                await this.syncPublish();
                this.message = data.message || 'Licença aplicada aos uploads.';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao vincular licença';
            }
        },

        async removeStockLicense(reg) {
            if (!confirm(`Remover licença «${reg.project_title}»?`)) return;

            try {
                await api.delete(`/projects/${this.projectId}/stock-licenses/${reg.id}`);
                await this.loadStockLicenses();
                await this.syncPublish();
                this.message = 'Licença removida.';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao remover licença';
            }
        },

        async loadProjectCredits() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/credits`);
                this.projectCreditsText = data.text || '';
                this.projectCreditsCount = data.count || 0;
                this.publishAuto = this.projectCreditsCount > 0;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar créditos';
            }
        },

        applyPublish(publish) {
            if (!publish) return;
            this.publishAuto = publish.auto !== false && (publish.materials_count || 0) > 0;
            this.projectCreditsText = publish.credits_text || '';
            this.projectCreditsCount = publish.materials_count || 0;
            if (publish.descriptions) {
                this.platformDescriptions = publish.descriptions;
                this.syncPlatformDescDraft();
            }
            if (publish.files) {
                this.publishFiles = publish.files;
            }
        },

        async syncPublish() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/publish/sync`);
                this.applyPublish(data);
                await this.loadDownloads();
            } catch (e) {
                // silencioso — sync é complementar
            }
        },

        async loadPlatformDescriptions() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/platform-descriptions`);
                this.platformDescriptions = data;
                this.syncPlatformDescDraft();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar descrições';
            }
        },

        syncPlatformDescDraft() {
            const d = this.platformDescriptions[this.selectedPlatformDesc];
            this.platformDescDraft = d?.description || '';
        },

        async saveCustomPlatformDescription() {
            try {
                const { data } = await api.put(`/projects/${this.projectId}/platform-descriptions/custom`, {
                    platform: this.selectedPlatformDesc,
                    description: this.platformDescDraft,
                });
                this.platformDescriptions = data.descriptions || this.platformDescriptions;
                this.syncPlatformDescDraft();
                this.message = 'Descrição salva';
                await this.syncPublish();
                await this.loadDownloads();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar descrição';
            }
        },

        async resetCustomPlatformDescription() {
            try {
                const { data } = await api.put(`/projects/${this.projectId}/platform-descriptions/custom`, {
                    platform: this.selectedPlatformDesc,
                    description: null,
                });
                this.platformDescriptions = data.descriptions || this.platformDescriptions;
                this.syncPlatformDescDraft();
                this.message = 'Descrição automática restaurada';
                await this.syncPublish();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao restaurar descrição';
            }
        },

        async exportPublishKit() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/publish-kit`);
                this.message = data.message || 'Publish Kit gerado';
                if (data.url) window.open(data.url, '_blank');
                await this.loadDownloads();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar Publish Kit';
            }
        },

        async markProjectExported() {
            if (!confirm('Marcar este projeto como exportado? Você poderá excluí-lo no dashboard para criar outro (modo online).')) return;
            try {
                const { data } = await api.post(`/projects/${this.projectId}/mark-exported`);
                this.projectStatus = data.project?.status || 'exported';
                this.message = data.message || 'Projeto marcado como exportado';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao marcar exportado';
            }
        },

        copyAllCredits() {
            if (!this.projectCreditsText) return;
            navigator.clipboard.writeText(this.projectCreditsText).then(() => {
                this.message = 'Créditos copiados — cole na descrição da plataforma';
            }).catch(() => {
                this.error = 'Não foi possível copiar — selecione e copie manualmente';
            });
        },

        scheduleDescriptionSave() {
            clearTimeout(this.descriptionSaveTimeout);
            this.descriptionSaveTimeout = setTimeout(() => this.saveProjectDescription(), 800);
        },

        async saveProjectDescription() {
            try {
                await api.put(`/projects/${this.projectId}`, { description: this.projectDescription });
                await this.syncPublish();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar descrição';
            }
        },

        playSlideshow(fromStart = false) {
            if (!this.canPlayPreview) return;

            let startSec = fromStart
                ? 0
                : Math.max(0, parseFloat(this.timelinePlayheadSec) || 0);
            const total = this.timelineTotalSeconds;
            if (total > 0 && startSec >= total - 0.05) {
                startSec = 0;
            }

            this.cleanupPreviewPlayback();

            this.previewPlaying = true;
            this.previewTransitioning = false;
            this.timelinePlayheadSec = startSec;
            this.previewPlayStartedAtSec = startSec;
            this.previewPlayStartedPerf = performance.now();

            if (this.slides.length) {
                if (startSec > 0.01) {
                    const { index, offsetInSlide } = this.timelineSecToSlideLocation(startSec);
                    this.previewIndex = index;
                    this.timelinePlayheadSec = Math.round(startSec * 100) / 100;
                    this.seekPreviewToTimelineSec(startSec);
                    this.schedulePreviewAdvance(offsetInSlide);
                } else {
                    this.previewIndex = 0;
                    this.resetPreviewSlideClock(0);
                    this.scrollTimelineToPlayhead('smooth');
                    this.schedulePreviewAdvance(0);
                }
            }

            this.startPreviewAudioMix();
            this.startPreviewTimelineSync();
        },

        cleanupPreviewPlayback() {
            if (this.previewMixer) {
                this.previewMixer.stop();
            }
            if (this.previewTimer) {
                clearTimeout(this.previewTimer);
                this.previewTimer = null;
            }
            this.stopPreviewTimelineSync();
            this.previewTransitioning = false;
        },

        stopSlideshow() {
            this.syncTimelinePlayheadFromPreview();
            this.cleanupPreviewPlayback();
            this.previewPlaying = false;
            this.previewPlayStartedPerf = null;
            this.previewSlideStartedPerf = null;

            if (this.slides.length) {
                const { index } = this.timelineSecToSlideLocation(this.timelinePlayheadSec);
                this.previewIndex = index;
            }

            const video = this.$refs.previewVideo;
            if (video && !video.paused) {
                video.pause();
            }

            this.$nextTick(() => {
                const slide = this.slides[this.previewIndex];
                const videoEl = this.$refs.previewVideo;
                if (videoEl && slide?.video_url) {
                    const slideStart = this.timelineSlideStartSec(this.previewIndex);
                    const offset = Math.max(0, this.timelinePlayheadSec - slideStart);
                    const dur = parseFloat(slide.duration_seconds || 5);
                    videoEl.currentTime = Math.min(offset, dur);
                }
                this.scrollTimelineToPlayhead('smooth');
            });
        },

        resetPreviewSlideClock(offsetInSlideSec = 0) {
            this.previewSlideStartedPerf = performance.now() - (Math.max(0, offsetInSlideSec) * 1000);
        },

        getPreviewElapsedSec() {
            if (!this.previewPlaying || !this.slides.length) {
                return Math.max(0, parseFloat(this.timelinePlayheadSec) || 0);
            }

            const slideStart = this.timelineSlideStartSec(this.previewIndex);
            const slideElapsed = this.previewSlideStartedPerf != null
                ? (performance.now() - this.previewSlideStartedPerf) / 1000
                : 0;

            return Math.min(
                this.timelineTotalSeconds,
                slideStart + Math.max(0, slideElapsed),
            );
        },

        syncTimelinePlayheadFromPreview() {
            this.timelinePlayheadSec = Math.round(this.getPreviewElapsedSec() * 100) / 100;
        },

        timelineSlideStartSec(index) {
            let sec = 0;
            for (let i = 0; i < index && i < this.slides.length; i++) {
                sec += parseFloat(this.slides[i].duration_seconds || 5);
            }

            return sec;
        },

        timelineSecToSlideLocation(sec) {
            const total = this.timelineTotalSeconds;
            const clamped = Math.max(0, Math.min(total, parseFloat(sec) || 0));
            let acc = 0;

            for (let i = 0; i < this.slides.length; i++) {
                const dur = parseFloat(this.slides[i].duration_seconds || 5);
                const end = acc + dur;
                if (clamped < end || i === this.slides.length - 1) {
                    return { index: i, offsetInSlide: Math.max(0, clamped - acc) };
                }
                acc = end;
            }

            return { index: 0, offsetInSlide: 0 };
        },

        seekPreviewToTimelineSec(sec) {
            const { index, offsetInSlide } = this.timelineSecToSlideLocation(sec);
            this.previewIndex = index;
            this.timelinePlayheadSec = Math.round(sec * 100) / 100;

            if (this.previewPlaying) {
                this.resetPreviewSlideClock(offsetInSlide);
            }

            this.$nextTick(() => {
                const slide = this.slides[index];
                const video = this.$refs.previewVideo;
                if (video && slide?.video_url) {
                    const apply = () => {
                        const dur = parseFloat(slide.duration_seconds || 5);
                        video.currentTime = Math.min(offsetInSlide, dur);
                        if (this.previewPlaying) {
                            video.play().catch(() => {});
                        }
                    };

                    if (video.readyState >= 1) {
                        apply();
                    } else {
                        video.addEventListener('loadedmetadata', apply, { once: true });
                    }
                }

                this.scrollTimelineToPlayhead('auto');
            });
        },

        startPreviewTimelineSync() {
            this.stopPreviewTimelineSync();

            const tick = () => {
                if (!this.previewPlaying) {
                    return;
                }

                this.syncTimelinePlayheadFromPreview();
                this.scrollTimelineToPlayhead('auto');
                this.previewSyncRaf = requestAnimationFrame(tick);
            };

            this.previewSyncRaf = requestAnimationFrame(tick);
        },

        stopPreviewTimelineSync() {
            if (this.previewSyncRaf) {
                cancelAnimationFrame(this.previewSyncRaf);
                this.previewSyncRaf = null;
            }
        },

        scrollTimelineToPlayhead(behavior = 'auto') {
            if (this.timelineExpanded) {
                return;
            }

            const scroller = this.$refs.timelineScroll;
            if (!scroller) {
                return;
            }

            const playheadPx = this.timelineSecondsToPx(this.timelinePlayheadSec);
            const viewW = scroller.clientWidth || 400;
            const margin = Math.min(80, viewW * 0.15);
            const scrollL = scroller.scrollLeft;

            if (playheadPx < scrollL + margin) {
                scroller.scrollTo({ left: Math.max(0, playheadPx - margin), behavior });
            } else if (playheadPx > scrollL + viewW - margin) {
                scroller.scrollTo({ left: playheadPx - viewW + margin, behavior });
            }
        },

        onPreviewVideoPause(event) {
            if (!this.previewPlaying || this.previewIgnoreVideoPause) {
                return;
            }

            const video = this.$refs.previewVideo;
            if (!video || event?.target !== video) {
                return;
            }

            if (video.ended) {
                return;
            }

            this.stopSlideshow();
        },

        slideDurationSec(slide) {
            const dur = parseFloat(slide?.duration_seconds);

            return Number.isFinite(dur) && dur > 0 ? dur : 5;
        },

        schedulePreviewAdvance(offsetInSlide = 0) {
            if (!this.previewPlaying) {
                return;
            }

            const slide = this.slides[this.previewIndex];
            if (!slide) {
                this.finishPreviewPlayback();
                return;
            }

            const offset = Math.max(0, parseFloat(offsetInSlide) || 0);
            this.resetPreviewSlideClock(offset);
            const dur = this.slideDurationSec(slide);
            const remainingMs = Math.max(500, (dur - offset) * 1000);
            this.previewTimer = setTimeout(() => this.advancePreviewSlide(), remainingMs);
        },

        finishPreviewPlayback() {
            this.timelinePlayheadSec = this.timelineTotalSeconds;
            this.stopSlideshow();
        },

        advancePreviewSlide() {
            if (!this.previewPlaying || !this.slides.length) {
                return;
            }

            if (this.previewIndex >= this.slides.length - 1) {
                this.finishPreviewPlayback();
                return;
            }

            const slide = this.slides[this.previewIndex];
            const trans = slide?.transition_type || 'fade';

            const goToNextSlide = () => {
                this.previewIndex += 1;
                this.previewTransitioning = false;
                this.previewIgnoreVideoPause = false;
                this.schedulePreviewAdvance(0);
            };

            if (trans === 'cut') {
                this.previewIgnoreVideoPause = true;
                goToNextSlide();
                this.$nextTick(() => {
                    this.previewIgnoreVideoPause = false;
                });
                return;
            }

            this.previewTransitionKind = trans === 'slide' ? 'slide' : 'fade';
            this.previewTransitioning = true;
            this.previewIgnoreVideoPause = true;
            setTimeout(goToNextSlide, 500);
        },

        startPreviewAudioMix() {
            if (!this.previewMixer) {
                this.previewMixer = new PreviewAudioMixer();
            }

            this.previewMixer.beginFromUserGesture();

            const musicTracks = [];
            this.audioTracks
                .filter((t) => t?.audio_url)
                .forEach((t, slot) => {
                    musicTracks.push({
                        audio_url: t.audio_url,
                        volume: parseFloat(t.volume) >= 0 ? parseFloat(t.volume) : 0.35,
                        start_at: parseFloat(t.start_at) || 0,
                        trim_in: parseFloat(t.trim_in) || 0,
                        loop_enabled: t.loop_enabled !== false,
                        slot,
                    });

                    (t.clips || []).forEach((clip) => {
                        const clipUrl = this.musicClipAudioUrl(clip);
                        if (!clipUrl) {
                            return;
                        }
                        musicTracks.push({
                            audio_url: clipUrl,
                            volume: parseFloat(t.volume) >= 0 ? parseFloat(t.volume) : 0.35,
                            start_at: parseFloat(clip.start_at) || 0,
                            trim_in: parseFloat(clip.trim_in) || 0,
                            loop_enabled: false,
                            slot,
                        });
                    });
                });

            const soundEffects = this.soundEffects
                .map((fx) => ({
                    id: fx.id,
                    audio_url: this.resolveSoundEffectUrl(fx),
                    volume: parseFloat(fx.volume) >= 0 ? parseFloat(fx.volume) : 1,
                    start_at: parseFloat(fx.start_at) || 0,
                    trim_in: parseFloat(fx.trim_in) || 0,
                }))
                .filter((fx) => fx.audio_url);

            const startOffsetSec = this.previewPlaying
                ? this.getPreviewElapsedSec()
                : (this.previewPlayStartedAtSec || this.timelinePlayheadSec || 0);

            this.previewMixer.play({
                narrationUrl: this.narration?.audio_url || null,
                musicTracks,
                soundEffects,
                totalDuration: this.timelineTotalSeconds,
                startOffsetSec,
                mixVolumes: { ...this.mixVolumes },
            });
        },

        updatePreviewMixVolumes() {
            this.previewMixer?.setMixVolumes({ ...this.mixVolumes });
        },

        onMusicVolumeInput(slot) {
            const vol = parseFloat(this.audioTracks[slot]?.volume);
            if (Number.isFinite(vol)) {
                this.previewMixer?.updateTrackVolume('music', slot, vol);
            }
        },

        onSfxVolumeInput(fx) {
            const vol = parseFloat(fx?.volume);
            if (Number.isFinite(vol) && fx?.id) {
                this.previewMixer?.updateSfxVolume(fx.id, vol);
            }
        },

        async testSoundEffect(fx) {
            const url = fx?.audio_url || this.resolveSoundEffectUrl(fx);
            if (!url) {
                this.error = 'Efeito sem arquivo de áudio — reimporte o som.';
                return;
            }
            if (!this.previewMixer) {
                this.previewMixer = new PreviewAudioMixer();
            }
            const vol = (parseFloat(fx.volume) >= 0 ? parseFloat(fx.volume) : 1)
                * (this.mixVolumes.sfx ?? 1);
            const ok = await this.previewMixer.playSfxNow(url, vol, parseFloat(fx.trim_in) || 0);
            if (!ok) {
                this.error = `Não foi possível tocar "${fx.label || 'Efeito'}". O arquivo pode estar corrompido — remova o efeito e importe novamente da biblioteca.`;
            } else {
                this.message = `Testando: ${fx.label || 'Efeito'}`;
            }
        },

        resolveSoundEffectUrl(fx) {
            if (fx?.audio_url) {
                return fx.audio_url;
            }
            if (fx?.asset_id) {
                return `/api/projects/${this.projectId}/assets/${fx.asset_id}`;
            }
            const path = fx?.file_path || fx?.asset?.file_path;
            if (path) {
                return this.fileUrl('assets', path.split(/[/\\]/).pop());
            }

            return null;
        },

        async previewLibraryAudio(item) {
            const url = item?.preview_url || item?.download_url;
            if (!url) {
                this.error = 'Preview indisponível para este item.';
                return;
            }
            if (!this.previewMixer) {
                this.previewMixer = new PreviewAudioMixer();
            }
            this.previewMixer.beginFromUserGesture();
            const isSfx = item.type === 'sfx' || this.mediaType === 'sfx';
            const mix = isSfx ? (this.mixVolumes.sfx ?? 1) : (this.mixVolumes.music ?? 1);
            const audio = new Audio(url);
            audio.volume = Math.min(1, Math.max(0, mix));
            try {
                await audio.play();
            } catch (_) {
                this.error = 'Não foi possível tocar o preview — tente Inserir e use ▶ Testar na timeline.';
            }
        },

        musicTrackSegments(track) {
            const segments = [];

            if (track?.file_path) {
                segments.push({
                    start_at: parseFloat(track.start_at) || 0,
                    duration: this.timelineEffectiveDuration(track, track.source_duration || 30),
                    audio_url: track.audio_url,
                    label: track.label || 'Trilha',
                });
            }

            (track?.clips || []).forEach((clip, i) => {
                if (!clip?.file_path) {
                    return;
                }
                segments.push({
                    start_at: parseFloat(clip.start_at) || 0,
                    duration: this.timelineEffectiveDuration(clip, clip.source_duration || 30),
                    audio_url: this.musicClipAudioUrl(clip),
                    label: clip.label || `Parte ${i + 2}`,
                });
            });

            return segments.sort((a, b) => a.start_at - b.start_at);
        },

        musicClipAudioUrl(clip) {
            if (clip?.audio_url) {
                return clip.audio_url;
            }
            if (clip?.asset_id) {
                return `/api/projects/${this.projectId}/assets/${clip.asset_id}`;
            }
            if (clip?.file_path) {
                return this.fileUrl('assets', clip.file_path.split(/[/\\]/).pop());
            }

            return null;
        },

        musicTrackCoverageEnd(track) {
            const segments = this.musicTrackSegments(track);
            if (!segments.length) {
                return 0;
            }

            return Math.max(...segments.map((s) => s.start_at + s.duration));
        },

        musicTrackNeedsLoop(track) {
            if (track.loop_enabled === false) {
                return false;
            }
            const start = parseFloat(track.start_at) || 0;

            return this.musicTrackCoverageEnd(track) < this.timelineTotalSeconds - start - 0.05;
        },

        previewTextStyle() {
            if (this.previewSlide?.text_style) {
                return this.slideBodyStyle(this.previewSlide);
            }

            return buildSlideBodyStyle(defaultTextStyle());
        },

        previewVerticalAlignClass() {
            const slide = this.previewSlide;
            if (slide?.text_style?.vertical_align) {
                return this.slideVerticalAlignClass(slide);
            }
            return 'justify-center';
        },

        copyPlatformDescription() {
            const text = this.platformDescDraft || this.platformDescriptions[this.selectedPlatformDesc]?.description;
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                this.message = 'Descrição copiada para a área de transferência';
            }).catch(() => {
                this.error = 'Não foi possível copiar — selecione e copie manualmente';
            });
        },

        async loadExportPackages() {
            const { data } = await api.get(`/projects/${this.projectId}/export-packages`);
            this.exportPackages = data.map(pkg => ({
                ...pkg,
                download_url: pkg.status === 'completed' && pkg.package_path
                    ? this.fileUrl('exports', pkg.package_path.split(/[/\\]/).pop())
                    : null,
            }));
        },

        async loadMediaProviders() {
            try {
                const { data } = await api.get('/media/providers');
                this.mediaLibraryProviders = data;
            } catch {
                this.mediaLibraryProviders = null;
            }
        },

        mediaSearchType() {
            if (this.mediaType === 'music') return 'audio';
            return this.mediaType;
        },

        setMediaLibraryMode(mode) {
            this.mediaType = mode === 'visual' ? 'image' : mode;
            this.mediaSearchPage = 1;
            this.mediaHasMore = false;
            this.mediaSearchExpanded = false;
            if (mode === 'music' || mode === 'sfx') {
                this.mediaSource = 'all';
            }
            if (mode === 'visual' || mode === 'image') {
                this.mediaSource = 'all';
            }
        },

        toggleMediaSearchExpanded() {
            this.mediaSearchExpanded = !this.mediaSearchExpanded;
        },

        toggleProjectLibraryExpanded() {
            this.projectLibraryExpanded = !this.projectLibraryExpanded;
        },

        mediaResultsScrollClass() {
            return this.mediaSearchExpanded
                ? 'max-h-[min(70vh,720px)] overflow-y-auto overscroll-contain'
                : 'max-h-72 overflow-y-auto overscroll-contain';
        },

        projectLibraryScrollClass() {
            return this.projectLibraryExpanded
                ? 'max-h-[min(60vh,560px)] overflow-y-auto overscroll-contain'
                : 'max-h-56 overflow-y-auto overscroll-contain';
        },

        mergeMediaResults(existing, incoming) {
            const keyFor = (item) => {
                const source = item?.source || '';
                const id = item?.id != null ? String(item.id) : '';
                if (id) {
                    return `${source}-${id}`;
                }
                const url = item?.download_url || item?.preview_url || '';
                const match = String(url).match(/\/videos\/(\d+)\//);
                if (match) {
                    return `${source}-${match[1]}`;
                }

                return `${source}-${url}`;
            };

            const seen = new Set(existing.map(keyFor));
            const merged = [...existing];
            for (const item of incoming) {
                const key = keyFor(item);
                if (!seen.has(key)) {
                    merged.push(item);
                    seen.add(key);
                }
            }

            return merged;
        },

        async loadMoreMediaResults() {
            if (this.mediaSearching || !this.mediaHasMore) {
                return;
            }
            await this.searchMedia(true);
        },

        openLibraryForMusic(slot = 0) {
            this.selectedMusicSlot = slot;
            this.setMediaLibraryMode('music');
            this.activeTab = 'biblioteca';
        },

        openLibraryForSfx(startAt = null) {
            this.mediaSfxStartAt = startAt ?? Math.round(this.timelinePlayheadSec * 10) / 10;
            this.setMediaLibraryMode('sfx');
            this.activeTab = 'biblioteca';
        },

        async loadAudioTracks() {
            const { data } = await api.get(`/projects/${this.projectId}/audio-tracks`);
            const music = data.filter((t) => t.type === 'music');
            this.audioTracks = [0, 1, 2].map((slot) => {
                const track = music.find((t) => (t.track_slot ?? 0) === slot);
                return track ? this.enrichAudioTrack({ ...track, track_slot: slot }) : this.emptyMusicSlot(slot);
            });
            this.audioTrack = this.audioTracks[0];
        },

        emptyMusicSlot(slot) {
            return {
                track_slot: slot,
                id: null,
                type: 'music',
                volume: 0.35,
                start_at: 0,
                trim_in: 0,
                trim_out: null,
                source_duration: null,
                ducking_enabled: slot === 0,
                loop_enabled: true,
                clips: [],
                file_path: null,
                audio_url: null,
                label: `Trilha ${slot + 1}`,
            };
        },

        enrichAudioTrack(track) {
            if (track?.asset_id) {
                track.audio_url = `/api/projects/${this.projectId}/assets/${track.asset_id}`;
            } else if (track?.file_path) {
                track.audio_url = this.fileUrl('assets', track.file_path.split(/[/\\]/).pop());
            }
            track.label = track.label || `Trilha ${(track.track_slot ?? 0) + 1}`;
            track.volume = track.volume != null && Number.isFinite(parseFloat(track.volume))
                ? parseFloat(track.volume)
                : 0.35;
            track.trim_in = parseFloat(track.trim_in) || 0;
            track.trim_out = track.trim_out != null ? parseFloat(track.trim_out) : null;
            track.source_duration = track.source_duration != null ? parseFloat(track.source_duration) : null;
            track.loop_enabled = track.loop_enabled !== false;
            track.clips = Array.isArray(track.clips) ? track.clips.map((clip) => ({
                ...clip,
                trim_in: parseFloat(clip.trim_in) || 0,
                trim_out: clip.trim_out != null ? parseFloat(clip.trim_out) : null,
                source_duration: clip.source_duration != null ? parseFloat(clip.source_duration) : null,
                start_at: parseFloat(clip.start_at) || 0,
                audio_url: clip.asset_id
                    ? `/api/projects/${this.projectId}/assets/${clip.asset_id}`
                    : (clip.file_path
                        ? this.fileUrl('assets', clip.file_path.split(/[/\\]/).pop())
                        : null),
            })) : [];

            return track;
        },

        async loadSoundEffects() {
            const { data } = await api.get(`/projects/${this.projectId}/sound-effects`);
            this.soundEffects = data.map((fx) => this.enrichSoundEffect(fx));
        },

        enrichSoundEffect(fx) {
            fx.audio_url = this.resolveSoundEffectUrl(fx);
            fx.volume = fx.volume != null && Number.isFinite(parseFloat(fx.volume))
                ? parseFloat(fx.volume)
                : 1;
            fx.start_at = parseFloat(fx.start_at) || 0;
            fx.trim_in = parseFloat(fx.trim_in) || 0;
            fx.trim_out = fx.trim_out != null ? parseFloat(fx.trim_out) : null;
            const metaDur = parseFloat(fx?.asset?.metadata?.duration_seconds);
            fx.source_duration = fx.source_duration != null
                ? parseFloat(fx.source_duration)
                : (Number.isFinite(metaDur) && metaDur > 0 ? metaDur : null);
            fx.clip_duration = fx.clip_duration != null
                ? parseFloat(fx.clip_duration)
                : (fx.source_duration || 2);

            return fx;
        },

        async saveMusicTrack(slot) {
            const track = this.audioTracks[slot];
            if (!track?.id) return;
            try {
                const { data } = await api.put(
                    `/projects/${this.projectId}/audio-tracks/${track.id}`,
                    {
                        volume: track.volume,
                        start_at: track.start_at,
                        trim_in: track.trim_in,
                        trim_out: track.trim_out,
                        source_duration: track.source_duration,
                        ducking_enabled: track.ducking_enabled,
                        loop_enabled: track.loop_enabled !== false,
                        clips: track.clips || [],
                    },
                );
                this.audioTracks[slot] = this.enrichAudioTrack({ ...track, ...data });
                if (slot === 0) this.audioTrack = this.audioTracks[0];
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar trilha';
            }
        },

        async removeMusicTrack(slot) {
            const track = this.audioTracks[slot];
            if (!track?.id) return;
            if (!confirm(`Remover ${track.label}?`)) return;
            await api.delete(`/projects/${this.projectId}/audio-tracks/${track.id}`);
            this.audioTracks[slot] = this.emptyMusicSlot(slot);
            if (slot === 0) this.audioTrack = this.audioTracks[0];
            this.message = 'Trilha removida';
        },

        async saveSoundEffect(fx) {
            if (!fx?.id) return;
            try {
                const { data } = await api.put(
                    `/projects/${this.projectId}/sound-effects/${fx.id}`,
                    {
                        label: fx.label,
                        start_at: fx.start_at,
                        volume: fx.volume,
                        trim_in: fx.trim_in,
                        trim_out: fx.trim_out,
                        source_duration: fx.source_duration,
                        clip_duration: fx.clip_duration,
                    }
                );
                const idx = this.soundEffects.findIndex((e) => e.id === fx.id);
                if (idx >= 0) {
                    this.soundEffects[idx] = this.enrichSoundEffect({ ...fx, ...data });
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar efeito';
            }
        },

        async removeSoundEffect(fx) {
            if (!fx?.id) return;
            if (!confirm('Remover este efeito?')) return;
            await api.delete(`/projects/${this.projectId}/sound-effects/${fx.id}`);
            this.soundEffects = this.soundEffects.filter((e) => e.id !== fx.id);
        },

        async uploadSoundEffect(event) {
            const file = event.target.files[0];
            if (!file) return;
            const startAt = Math.round(this.timelinePlayheadSec * 10) / 10;
            try {
                const asset = await this.uploadAsset(file, 'audio', false);
                const { data } = await api.post(`/projects/${this.projectId}/sound-effects`, {
                    asset_id: asset.id,
                    file_path: asset.file_path,
                    label: file.name.replace(/\.[^.]+$/, ''),
                    start_at: startAt,
                    volume: 1,
                });
                this.soundEffects.push(this.enrichSoundEffect(data));
                this.message = `Efeito adicionado aos ${startAt}s`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar efeito';
            }
        },

        async loadSlides() {
            const { data } = await api.get(`/projects/${this.projectId}/slides`);
            this.slides = data.map(s => this.enrichSlide(s));
            this.syncSelection();
            this.buildFullScriptFromSlides();
            this.afterSlidesLayoutChanged();
        },

        enrichSlide(slide) {
            slide.text_style = this.normalizeTextStyle(slide.text_style);
            slide.duration_mode = slide.duration_mode || 'narration';
            if (!(slide.body_text || '').trim() && (slide.narration_text || '').trim()) {
                slide.body_text = slide.narration_text;
            }
            if (slide.image_path) {
                slide.image_url = this.fileUrl('assets', slide.image_path.split(/[/\\]/).pop());
            }
            if (slide.video_path) {
                slide.video_url = this.fileUrl('assets', slide.video_path.split(/[/\\]/).pop());
            }
            return slide;
        },

        normalizeTextStyle(style) {
            return normalizeSlideTextStyle(style);
        },

        slideBodyStyle(slide) {
            return buildSlideBodyStyle(slide?.text_style);
        },

        durationModeLabel(mode) {
            return {
                manual: 'Manual',
                video: 'Vídeo',
                narration: 'Narração',
            }[mode] || 'Narração';
        },

        onManualDurationChange() {
            if (this.selectedSlide) {
                this.selectedSlide.duration_mode = 'manual';
            }
            this.scheduleSave();
        },

        async onDurationModeChange() {
            const slide = this.selectedSlide;
            if (!slide) return;

            if (slide.duration_mode === 'narration') {
                await this.recalculateNarrationDurations();
                return;
            }

            if (slide.duration_mode === 'video' && slide.video_duration_seconds > 0) {
                slide.duration_seconds = Math.round(slide.video_duration_seconds * 10) / 10;
            }

            this.scheduleSave();
        },

        async recalculateNarrationDurations() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/slides/recalculate-durations`);
                this.slides = data.map((s) => this.enrichSlide(s));
                this.syncSelection();
                this.message = `Tempos recalculados pela narração (${DURATION_MIN}–${DURATION_MAX}s)`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao recalcular tempos';
            }
        },

        applyLocalAutomaticDurations() {
            applyAutomaticDurations(this.slides);
        },

        slideVerticalAlignClass(slide) {
            const align = slide?.text_style?.vertical_align || 'center';
            if (align === 'top') return 'justify-start';
            if (align === 'bottom') return 'justify-end';

            return 'justify-center';
        },

        syncBodyTextStyle() {
            const slide = this.selectedSlide;
            if (!slide?.text_style) return;
            slide.text_style.title_color = slide.text_style.body_color;
            slide.text_style.title_size = slide.text_style.body_size;
            this.scheduleSave();
        },

        fileUrl(type, filename) {
            return `/api/projects/${this.projectId}/files/${type}/${filename}`;
        },

        selectSlide(slide) {
            if (this.previewPlaying) {
                this.stopSlideshow();
            }
            this.selectedSlide = this.slides.find(s => s.id === slide.id) ?? slide;
            const index = this.slides.findIndex(s => s.id === slide.id);
            if (index >= 0) {
                this.scrollTimelineToSlide(index);
            }
        },

        formatTimelineTime(seconds) {
            const s = Math.max(0, parseFloat(seconds) || 0);
            const m = Math.floor(s / 60);
            const r = Math.round(s % 60);
            if (m > 0) {
                return `${m}:${String(r).padStart(2, '0')}`;
            }

            return `${Math.round(s * 10) / 10}s`;
        },

        timelineClipWidth(slide) {
            const dur = parseFloat(slide?.duration_seconds || 5);

            return Math.max(80, dur * this.timelineZoom);
        },

        timelineSecondsToPx(seconds) {
            return Math.max(0, parseFloat(seconds) || 0) * this.timelineZoom;
        },

        timelineAudioSpanWidth(startAt) {
            const start = Math.max(0, parseFloat(startAt) || 0);
            const span = Math.max(0, this.timelineTotalSeconds - start);

            return Math.max(24, span * this.timelineZoom);
        },

        timelineFxClipWidth() {
            return Math.max(36, this.timelineZoom * 1.5);
        },

        selectTimelineSfx(fx) {
            if (!fx) {
                return;
            }

            this.selectedSoundEffectId = fx.id;
            this.timelineSelectedClip = { kind: 'sfx', id: fx.id };
            this.timelineSelectedClipLabel = fx.label || 'Efeito';
        },

        selectSoundEffect(fx) {
            this.selectTimelineSfx(fx);
            this.activeTab = 'audio';
        },

        selectTimelineNarration() {
            if (!this.narration?.audio_url) return;
            this.timelineSelectedClip = { kind: 'narration' };
            this.timelineSelectedClipLabel = 'Narração';
        },

        selectTimelineMusic(slot) {
            const track = this.audioTracks[slot];
            if (!track?.file_path) return;
            this.timelineSelectedClip = { kind: 'music', slot };
            this.timelineSelectedClipLabel = track.label;
        },

        timelinePxToSeconds(px) {
            return Math.max(0, (parseFloat(px) || 0) / this.timelineZoom);
        },

        timelineEffectiveDuration(item, fallback = 30) {
            const source = parseFloat(item?.source_duration)
                || parseFloat(item?.clip_duration)
                || parseFloat(item?.duration_seconds)
                || fallback;
            const trimIn = parseFloat(item?.trim_in) || 0;
            const rawTrimOut = item?.trim_out != null ? parseFloat(item.trim_out) : null;
            const trimOut = rawTrimOut != null && rawTrimOut > trimIn ? rawTrimOut : source;

            return Math.max(0.25, trimOut - trimIn);
        },

        timelineMusicClipWidth(track) {
            const start = parseFloat(track?.start_at) || 0;
            const span = Math.max(0, this.timelineTotalSeconds - start);

            return Math.max(24, span * this.timelineZoom);
        },

        timelineMusicSegmentWidth(segment) {
            const dur = segment?.duration
                || this.timelineEffectiveDuration(segment, segment?.source_duration || 30);

            return Math.max(24, dur * this.timelineZoom);
        },

        timelineSfxSegmentWidth(fx) {
            const dur = this.timelineEffectiveDuration(
                fx,
                fx?.clip_duration || fx?.source_duration || 2,
            );

            return Math.max(36, Math.min(160, dur * this.timelineZoom));
        },

        timelineFxDisplayWidth(fx) {
            return this.timelineSfxSegmentWidth(fx);
        },

        timelineNarrationWidthPx() {
            const narr = this.narration || {};
            const dur = this.timelineEffectiveDuration(narr, narr.duration_seconds || this.timelineTotalSeconds);

            return Math.max(24, Math.min(this.timelineSecondsToPx(this.timelineTotalSeconds), dur * this.timelineZoom));
        },

        setPlayheadFromTimelineEvent(event) {
            const scroller = this.$refs.timelineScroll;
            const area = this.$refs.timelineTrackArea;
            if (!scroller || !area) return;

            const rect = area.getBoundingClientRect();
            const x = event.clientX - rect.left + scroller.scrollLeft;
            const sec = Math.max(0, Math.min(this.timelineTotalSeconds, this.timelinePxToSeconds(x)));
            this.timelinePlayheadSec = Math.round(sec * 100) / 100;
        },

        markTimelineCutIn() {
            this.timelineCutMarkIn = this.timelinePlayheadSec;
        },

        markTimelineCutOut() {
            this.timelineCutMarkOut = this.timelinePlayheadSec;
        },

        clearTimelineCutMarks() {
            this.timelineCutMarkIn = null;
            this.timelineCutMarkOut = null;
        },

        async applyTimelineTrim() {
            const clip = this.timelineSelectedClip;
            if (!clip) {
                this.error = 'Selecione uma faixa de áudio, efeito ou narração na timeline.';
                return;
            }

            const markIn = this.timelineCutMarkIn ?? this.timelinePlayheadSec;
            const markOut = this.timelineCutMarkOut ?? this.timelinePlayheadSec;
            if (markOut <= markIn) {
                this.error = 'Marca de saída deve ser depois da entrada.';
                return;
            }

            const span = Math.round((markOut - markIn) * 100) / 100;

            try {
                if (clip.kind === 'narration' && this.narration?.id) {
                    this.narration.trim_in = markIn;
                    this.narration.trim_out = markOut;
                    await api.put(`/projects/${this.projectId}/narration`, {
                        trim_in: markIn,
                        trim_out: markOut,
                    });
                } else if (clip.kind === 'music' && clip.slot != null) {
                    const track = this.audioTracks[clip.slot];
                    if (!track?.id) return;
                    track.start_at = markIn;
                    track.trim_out = (parseFloat(track.trim_in) || 0) + span;
                    if (track.source_duration != null) {
                        track.trim_out = Math.min(track.trim_out, track.source_duration);
                    }
                    await this.saveMusicTrack(clip.slot);
                } else if (clip.kind === 'sfx' && clip.id) {
                    const fx = this.soundEffects.find((e) => e.id === clip.id);
                    if (!fx) return;
                    fx.start_at = markIn;
                    fx.clip_duration = span;
                    fx.trim_out = (parseFloat(fx.trim_in) || 0) + span;
                    await this.saveSoundEffect(fx);
                }
                this.message = `Corte aplicado (${this.formatTimelineTime(markIn)} → ${this.formatTimelineTime(markOut)})`;
                this.clearTimelineCutMarks();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao aplicar corte';
            }
        },

        async saveNarrationTrim() {
            if (!this.narration?.id) return;
            try {
                const { data } = await api.put(`/projects/${this.projectId}/narration`, {
                    trim_in: this.narration.trim_in ?? 0,
                    trim_out: this.narration.trim_out,
                });
                this.narration = { ...this.narration, ...data };
                this.message = 'Corte da narração salvo';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar corte da narração';
            }
        },

        openAudioTab() {
            this.activeTab = 'audio';
        },

        startAudioDrag(payload, event) {
            this.audioDragPayload = payload;
            if (event?.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData(this.audioDragMime, JSON.stringify(payload));
            }
        },

        endAudioDrag() {
            this.audioDragPayload = null;
            this.audioDropHover = null;
        },

        parseAudioDragPayload(event) {
            if (this.audioDragPayload) {
                return this.audioDragPayload;
            }

            try {
                const raw = event.dataTransfer?.getData(this.audioDragMime)
                    || event.dataTransfer?.getData('text/plain');
                if (raw) {
                    return JSON.parse(raw);
                }
            } catch (_) {
                //
            }

            return null;
        },

        timelineSecFromDropEvent(event) {
            const lane = event?.currentTarget?.dataset?.timelineLane
                ? event.currentTarget
                : null;

            if (lane) {
                return this.timelineSecFromLanePointer(event, lane);
            }

            const area = this.$refs.timelineTrackArea;
            const scroller = this.$refs.timelineScroll;
            if (!area || !scroller) {
                return 0;
            }

            const rect = area.getBoundingClientRect();
            const x = event.clientX - rect.left + scroller.scrollLeft;
            const sec = Math.max(0, Math.min(this.timelineTotalSeconds, this.timelinePxToSeconds(x)));

            return this.snapTimelineSec(sec, event?.shiftKey);
        },

        snapTimelineSec(sec, fine = false) {
            const step = fine ? 0.1 : this.timelineSnapStep;

            return Math.round(Math.max(0, sec) / step) * step;
        },

        timelineLaneContentOffset(lane) {
            const scroller = this.$refs.timelineScroll;
            if (!lane || !scroller) {
                return 0;
            }

            let offset = 0;
            let node = lane;

            while (node && node !== scroller) {
                offset += node.offsetLeft || 0;
                node = node.parentElement;
            }

            return offset;
        },

        timelineSecFromLanePointer(event, lane) {
            const scroller = this.$refs.timelineScroll;
            if (!lane || !scroller) {
                return 0;
            }

            const scrollerRect = scroller.getBoundingClientRect();
            const contentX = event.clientX - scrollerRect.left + scroller.scrollLeft
                - this.timelineLaneContentOffset(lane);
            const sec = this.timelinePxToSeconds(Math.max(0, contentX));

            return this.snapTimelineSec(
                Math.max(0, Math.min(this.timelineTotalSeconds, sec)),
                event?.shiftKey,
            );
        },

        getAudioSegmentStartAt(payload) {
            if (payload?.type === 'music-segment') {
                const seg = this.musicTrackSegments(this.audioTracks[payload.slot])?.[payload.segmentIndex];

                return seg?.start_at ?? 0;
            }

            if (payload?.type === 'sfx') {
                return this.soundEffects.find((f) => f.id === payload.fxId)?.start_at ?? 0;
            }

            return 0;
        },

        timelinePointerDragActive(slot = null, segmentIndex = null, fxId = null) {
            const drag = this.timelinePointerDrag;
            if (!drag) {
                return false;
            }

            if (drag.payload?.type === 'music-segment') {
                return drag.payload.slot === slot && drag.payload.segmentIndex === segmentIndex;
            }

            if (drag.payload?.type === 'sfx') {
                return fxId != null && drag.payload.fxId === fxId;
            }

            return false;
        },

        timelinePointerDragSec(slot, segmentIndex) {
            if (this.timelinePointerDragActive(slot, segmentIndex)) {
                return this.timelinePointerDrag.hoverSec;
            }

            return this.musicTrackSegments(this.audioTracks[slot])?.[segmentIndex]?.start_at ?? 0;
        },

        timelinePointerDragFxSec(fxId) {
            if (this.timelinePointerDragActive(null, null, fxId)) {
                return this.timelinePointerDrag.hoverSec;
            }

            return this.soundEffects.find((f) => f.id === fxId)?.start_at ?? 0;
        },

        startTimelinePointerDrag(event, payload) {
            if (event.button !== 0) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const lane = event.currentTarget.closest('[data-timeline-lane]');
            if (!lane) {
                return;
            }

            const laneKey = lane.dataset.timelineLane;
            const pointerSec = this.timelineSecFromLanePointer(event, lane);
            const startAt = this.getAudioSegmentStartAt(payload);

            this.timelinePointerDrag = {
                payload,
                laneKey,
                clickOffsetSec: pointerSec - startAt,
                hoverSec: startAt,
                moved: false,
            };

            this.audioDropHover = { lane: laneKey, sec: startAt };
            document.body.style.cursor = 'grabbing';
            document.addEventListener('mousemove', this._boundTimelinePointerMove);
            document.addEventListener('mouseup', this._boundTimelinePointerUp);
        },

        handleTimelinePointerMove(event) {
            const drag = this.timelinePointerDrag;
            if (!drag) {
                return;
            }

            drag.moved = true;

            const under = document.elementFromPoint(event.clientX, event.clientY);
            const lane = under?.closest?.('[data-timeline-lane]')
                || this.$refs.timelineScroll?.querySelector(`[data-timeline-lane="${drag.laneKey}"]`);

            if (!lane) {
                return;
            }

            const laneKey = lane.dataset.timelineLane;
            if (drag.payload?.type === 'music-segment' && !laneKey.startsWith('music:')) {
                return;
            }

            if (drag.payload?.type === 'sfx' && laneKey !== 'sfx') {
                return;
            }

            drag.laneKey = laneKey;

            const pointerSec = this.timelineSecFromLanePointer(event, lane);
            const nextSec = Math.max(
                0,
                Math.min(this.timelineTotalSeconds, pointerSec - drag.clickOffsetSec),
            );

            drag.hoverSec = this.snapTimelineSec(nextSec, event.shiftKey);
            this.audioDropHover = { lane: drag.laneKey, sec: drag.hoverSec };
        },

        async handleTimelinePointerUp(event) {
            const drag = this.timelinePointerDrag;
            document.removeEventListener('mousemove', this._boundTimelinePointerMove);
            document.removeEventListener('mouseup', this._boundTimelinePointerUp);
            document.body.style.cursor = '';

            if (!drag) {
                return;
            }

            if (!drag.moved) {
                this.timelinePointerDrag = null;
                this.audioDropHover = null;
                return;
            }

            const lane = this.$refs.timelineScroll?.querySelector(`[data-timeline-lane="${drag.laneKey}"]`);
            let sec = drag.hoverSec;

            if (lane) {
                const pointerSec = this.timelineSecFromLanePointer(event, lane);
                sec = this.snapTimelineSec(
                    Math.max(0, Math.min(this.timelineTotalSeconds, pointerSec - drag.clickOffsetSec)),
                    event.shiftKey,
                );
            }

            this.timelinePointerDrag = null;
            this.audioDropHover = null;

            await this.applyAudioTimelineDrop(drag.payload, drag.laneKey, sec);
        },

        timelineAudioDragOver(event, laneKey) {
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }

            const lane = event.currentTarget;
            this.audioDropHover = {
                lane: laneKey,
                sec: this.timelineSecFromLanePointer(event, lane),
            };
        },

        timelineAudioDragLeave(event, laneKey) {
            if (this.audioDropHover?.lane === laneKey) {
                this.audioDropHover = null;
            }
        },

        async timelineAudioDrop(event, laneKey) {
            event.preventDefault();
            event.stopPropagation();

            const lane = event.currentTarget;
            const sec = this.timelineSecFromLanePointer(event, lane);
            const payload = this.parseAudioDragPayload(event);
            this.audioDropHover = null;
            this.audioDragPayload = null;

            if (!payload) {
                return;
            }

            await this.applyAudioTimelineDrop(payload, laneKey, sec);
        },

        async applyAudioTimelineDrop(payload, laneKey, sec) {
            const [laneKind, laneSlot] = laneKey.split(':');
            const musicSlot = laneKind === 'music' ? parseInt(laneSlot, 10) : null;

            try {
                if (payload.type === 'library-music') {
                    if (laneKind !== 'music' || Number.isNaN(musicSlot)) {
                        this.error = 'Solte trilhas nas faixas âmbar (Trilha 1–3).';
                        return;
                    }
                    const item = payload.item ?? this.mediaResults[payload.itemIndex];
                    if (!item) {
                        return;
                    }
                    await this.importMedia(item, {
                        track_slot: musicSlot,
                        start_at: sec,
                        place_at: true,
                    });
                    return;
                }

                if (payload.type === 'library-sfx') {
                    if (laneKind !== 'sfx') {
                        this.error = 'Solte efeitos na faixa FX (rosa).';
                        return;
                    }
                    const item = payload.item ?? this.mediaResults[payload.itemIndex];
                    if (!item) {
                        return;
                    }
                    await this.importMedia(item, { start_at: sec, place_at: true });
                    return;
                }

                if (payload.type === 'music-segment') {
                    if (laneKind !== 'music' || Number.isNaN(musicSlot)) {
                        this.error = 'Solte trilhas nas faixas âmbar (Trilha 1–3).';
                        return;
                    }
                    await this.moveMusicSegmentToLane(payload.slot, payload.segmentIndex, musicSlot, sec);
                    return;
                }

                if (payload.type === 'sfx') {
                    if (laneKind !== 'sfx') {
                        this.error = 'Solte efeitos na faixa FX (rosa).';
                        return;
                    }
                    await this.moveSoundEffectToTime(payload.fxId, sec);
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao posicionar áudio na timeline';
            }
        },

        async moveMusicSegmentToLane(fromSlot, segmentIndex, toSlot, sec) {
            const source = this.audioTracks[fromSlot];
            if (!source?.file_path) {
                return;
            }

            const segments = this.musicTrackSegments(source);
            const seg = segments[segmentIndex];
            if (!seg) {
                return;
            }

            if (fromSlot === toSlot) {
                if (segmentIndex === 0) {
                    source.start_at = sec;
                } else {
                    const clip = source.clips?.[segmentIndex - 1];
                    if (clip) {
                        clip.start_at = sec;
                    }
                }
                await this.saveMusicTrack(fromSlot);
                this.message = `${seg.label} em ${this.formatTimelineTime(sec)}`;
                return;
            }

            const target = this.audioTracks[toSlot];
            const clipPayload = {
                asset_id: segmentIndex === 0 ? source.asset_id : source.clips?.[segmentIndex - 1]?.asset_id,
                file_path: segmentIndex === 0 ? source.file_path : source.clips?.[segmentIndex - 1]?.file_path,
                source_duration: seg.duration,
                start_at: sec,
                label: seg.label,
            };

            if (!target?.file_path) {
                const { data } = await api.post(`/projects/${this.projectId}/audio-tracks`, {
                    asset_id: clipPayload.asset_id,
                    file_path: clipPayload.file_path,
                    track_slot: toSlot,
                    source_duration: clipPayload.source_duration,
                    start_at: sec,
                    volume: source.volume ?? 0.35,
                    ducking_enabled: toSlot === 0,
                    loop_enabled: source.loop_enabled !== false,
                });
                this.audioTracks[toSlot] = this.enrichAudioTrack(data);
            } else {
                target.clips = [...(target.clips || []), clipPayload];
                await this.saveMusicTrack(toSlot);
            }

            if (segmentIndex > 0) {
                source.clips = (source.clips || []).filter((_, i) => i !== segmentIndex - 1);
                await this.saveMusicTrack(fromSlot);
            }

            this.message = `${seg.label} movido para ${this.audioTracks[toSlot]?.label} em ${this.formatTimelineTime(sec)}`;
        },

        async moveSoundEffectToTime(fxId, sec) {
            const fx = this.soundEffects.find((e) => e.id === fxId);
            if (!fx) {
                return;
            }

            fx.start_at = sec;
            await this.saveSoundEffect(fx);
            this.timelineSelectedClip = { kind: 'sfx', id: fx.id };
            this.timelineSelectedClipLabel = fx.label || 'Efeito';
            this.message = `${fx.label || 'Efeito'} em ${this.formatTimelineTime(sec)}`;
        },

        musicSegmentDragPayload(slot, segmentIndex) {
            const segments = this.musicTrackSegments(this.audioTracks[slot]);

            return {
                type: 'music-segment',
                slot,
                segmentIndex,
                label: segments[segmentIndex]?.label || 'Trilha',
            };
        },

        sfxDragPayload(fx) {
            return {
                type: 'sfx',
                fxId: fx.id,
                label: fx.label || 'Efeito',
            };
        },

        libraryMusicDragPayload(item, itemIndex) {
            return {
                type: 'library-music',
                itemIndex,
                item,
                label: item.title || 'Trilha',
            };
        },

        librarySfxDragPayload(item, itemIndex) {
            return {
                type: 'library-sfx',
                itemIndex,
                item,
                label: item.title || 'Efeito',
            };
        },

        timelineOffsetPx(index) {
            let offset = 0;
            for (let i = 0; i < index; i++) {
                offset += this.timelineClipWidth(this.slides[i]) + 8;
            }

            return offset;
        },

        scrollTimelineToSlide(index) {
            this.$nextTick(() => {
                const scroller = this.$refs.timelineScroll;
                if (!scroller || index < 0) return;
                const offset = this.timelineOffsetPx(index);
                const clipW = this.timelineClipWidth(this.slides[index]);
                const viewW = scroller.clientWidth || 400;
                const target = offset - (viewW / 2) + (clipW / 2);
                scroller.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
            });
        },

        adjustTimelineZoom(delta) {
            if (this.timelineExpanded) {
                this.timelineExpanded = false;
                this.timelineZoomBeforeExpand = null;
            }
            this.timelineZoomManual = true;
            this.timelineZoom = Math.min(72, Math.max(6, this.timelineZoom + delta));
        },

        switchTab(tab) {
            this.activeTab = tab;
            if (tab === 'biblioteca') {
                this.loadProjectLibraryAssets();
                this.loadStockLicenses();
                this.prepareMediaSearch();
            }
            if (tab === 'exportar') {
                this.loadProjectCredits();
                this.loadPlatformDescriptions();
            }
            if (tab === 'image_studio') {
                this.$nextTick(() => this.initImageStudio());
            }
        },

        prepareMediaSearch() {
            const slide = this.selectedSlide;
            const raw = this.slideSearchQuery(slide);
            if (!raw) return;
            if (this.mediaQuery.trim() && this.mediaQuery !== this._lastSlideSearchRaw) {
                return;
            }
            if (!this.mediaQuery.trim() || this.mediaQuery === this._lastSlideSearchRaw) {
                const token = ++this._mediaAutoSearchToken;
                this.resolveMediaQuery(raw).then((query) => {
                    if (token !== this._mediaAutoSearchToken) return;
                    if (this.mediaQuery.trim() && this.mediaQuery !== this._lastSlideSearchRaw) return;
                    this.mediaQuery = query;
                    this._lastSlideSearchRaw = raw;
                    this.searchMedia();
                });
            }
        },

        resetMediaUploadMeta() {
            this.mediaUploadMeta = {
                item_title: '',
                author: '',
                attribution_text: '',
                requires_attribution: false,
                original_url: '',
                license_type: '',
                stock_license_id: this.defaultStockLicense?.id ?? null,
            };
        },

        mediaUploadAcceptTypes() {
            if (this.mediaType === 'music' || this.mediaType === 'sfx') {
                return 'audio/*';
            }
            if (this.mediaType === 'video') {
                return 'video/*';
            }
            return 'image/*';
        },

        async submitMediaUpload(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            event.target.value = '';

            const meta = { ...this.mediaUploadMeta };
            if (!meta.item_title?.trim()) {
                meta.item_title = file.name.replace(/\.[^.]+$/, '');
            }

            try {
                const assetType = (this.mediaType === 'music' || this.mediaType === 'sfx') ? 'audio' : this.mediaType;
                const asset = await this.uploadAsset(file, assetType, false, meta);
                await this.attachUploadedAsset(asset, file.name);
                this.resetMediaUploadMeta();
            } catch (e) {
                this.error = e.response?.data?.message || e.message || 'Erro ao enviar arquivo';
            }
        },

        async attachUploadedAsset(asset, fileName = '') {
            const label = asset.item_title || fileName.replace(/\.[^.]+$/, '') || 'Mídia';

            if (this.mediaType === 'music') {
                const slot = this.selectedMusicSlot ?? 0;
                const track = this.audioTracks[slot] ?? this.emptyMusicSlot(slot);
                const append = !!track.file_path;
                const { data } = await api.post(`/projects/${this.projectId}/audio-tracks`, {
                    asset_id: asset.id,
                    file_path: asset.file_path,
                    track_slot: slot,
                    volume: track.volume ?? 0.35,
                    start_at: track.start_at ?? 0,
                    ducking_enabled: track.ducking_enabled ?? true,
                    loop_enabled: track.loop_enabled !== false,
                    append,
                    label,
                });
                this.audioTracks[slot] = this.enrichAudioTrack(data);
                if (slot === 0) this.audioTrack = this.audioTracks[0];
                this.message = append
                    ? `Trilha encadeada na ${this.audioTracks[slot].label}`
                    : `${this.audioTracks[slot].label} cadastrada com licença/créditos`;
                return;
            }

            if (this.mediaType === 'sfx') {
                const startAt = Math.round(this.timelinePlayheadSec * 10) / 10;
                const { data } = await api.post(`/projects/${this.projectId}/sound-effects`, {
                    asset_id: asset.id,
                    file_path: asset.file_path,
                    label,
                    start_at: startAt,
                    volume: 1,
                });
                this.soundEffects.push(this.enrichSoundEffect(data));
                this.message = `Efeito "${label}" adicionado aos ${startAt}s`;
                return;
            }

            if (this.mediaUploadAttachToSlide && this.selectedSlide) {
                if (asset.type === 'video') {
                    this.selectedSlide.video_path = asset.file_path;
                    this.selectedSlide.video_url = asset.url || this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                    this.selectedSlide.duration_mode = 'video';
                } else {
                    this.selectedSlide.image_path = asset.file_path;
                    this.selectedSlide.image_url = asset.url || `/api/projects/${this.projectId}/assets/${asset.id}`;
                }
                await this.saveSlide();
                this.message = `"${label}" inserido no slide`;
            } else {
                this.message = `"${label}" salvo na biblioteca do projeto`;
            }
        },

        async resolveMediaUrl() {
            const url = (this.mediaImportUrl || '').trim();
            if (!url) {
                this.error = 'Cole o link da mídia.';
                return;
            }
            this.mediaImportLoading = true;
            this.mediaImportPreview = null;
            this.error = '';
            try {
                const typeHint = this.mediaType === 'music' ? 'audio' : this.mediaType;
                const { data } = await api.post('/media/resolve-url', { url, type: typeHint });
                this.mediaImportPreview = data.item;
                this.message = 'Link reconhecido — confira os dados e clique em Importar';
            } catch (e) {
                this.error = e.response?.data?.message || 'Não foi possível ler este link';
            } finally {
                this.mediaImportLoading = false;
            }
        },

        async importMediaFromUrl() {
            const url = (this.mediaImportUrl || '').trim();
            if (!url) {
                this.error = 'Cole o link da mídia.';
                return;
            }
            this.mediaImportLoading = true;
            this.error = '';
            try {
                const isMusic = this.mediaType === 'music';
                const isSfx = this.mediaType === 'sfx';
                const payload = {
                    url,
                    type: isSfx ? 'sfx' : (isMusic ? 'audio' : this.mediaType),
                    target: isSfx ? 'sound_effect' : (isMusic ? 'audio_track' : 'slide'),
                    slide_id: this.selectedSlide?.id,
                };
                if (isMusic) {
                    payload.track_slot = this.selectedMusicSlot ?? 0;
                }
                if (isSfx) {
                    payload.start_at = Math.round(this.timelinePlayheadSec * 10) / 10;
                    payload.label = this.mediaImportPreview?.title || 'Efeito';
                }
                const { data } = await api.post(`/projects/${this.projectId}/media/import-url`, payload);
                this.applyPublish(data.publish);
                if (data.audio_track) {
                    const slot = data.audio_track.track_slot ?? 0;
                    this.audioTracks[slot] = this.enrichAudioTrack(data.audio_track);
                    if (slot === 0) this.audioTrack = this.audioTracks[0];
                    this.message = data.publish?.message || 'Trilha importada por link';
                } else if (data.sound_effect) {
                    this.soundEffects.push(this.enrichSoundEffect({
                        ...data.sound_effect,
                        asset: data.asset || data.sound_effect?.asset,
                    }));
                    this.message = data.publish?.message || 'Efeito importado por link';
                } else if (data.slide && this.selectedSlide) {
                    const idx = this.slides.findIndex((s) => s.id === data.slide.id);
                    if (idx >= 0) {
                        this.slides[idx] = this.enrichSlide({ ...this.slides[idx], ...data.slide });
                        if (this.selectedSlide?.id === data.slide.id) {
                            this.selectedSlide = this.slides[idx];
                        }
                    }
                    this.message = data.publish?.message || 'Mídia inserida no slide';
                } else {
                    this.message = data.publish?.message || 'Mídia importada';
                }
                await this.loadProjectLibraryAssets();
                this.mediaImportPreview = null;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar por link';
            } finally {
                this.mediaImportLoading = false;
            }
        },

        async searchFromSlideBody() {
            const slide = this.selectedSlide;
            const raw = this.slideSearchQuery(slide);
            if (!raw) {
                this.error = 'Escreva o corpo do slide antes de buscar imagens.';
                return;
            }
            this.activeTab = 'biblioteca';
            this.mediaQuery = await this.resolveMediaQuery(raw);
            this._lastSlideSearchRaw = raw;
            this.searchMedia();
        },

        async resolveMediaQuery(raw) {
            try {
                const { data } = await api.get('/media/suggest-query', { params: { query: raw } });
                return data.primary || data.extracted || raw;
            } catch {
                return raw.replace(/\s+/g, ' ').slice(0, 80);
            }
        },

        buildFullScriptFromSlides() {
            this.fullScript = this.slides
                .map(s => (s.narration_text || '').trim())
                .filter(Boolean)
                .join('\n\n');
        },

        async addSlide() {
            const { data } = await api.post(`/projects/${this.projectId}/slides`, {
                title: `Slide ${this.slides.length + 1}`,
                text_style: defaultTextStyle(),
                duration_mode: 'narration',
            });
            this.slides.push(this.enrichSlide(data));
            this.selectSlide(data);
        },

        async removeSlide(slide) {
            if (!confirm('Remover este slide?')) return;
            await api.delete(`/projects/${this.projectId}/slides/${slide.id}`);
            this.slides = this.slides.filter(s => s.id !== slide.id);
            this.syncSelection();
        },

        async saveSlide() {
            const slide = this.selectedSlide;
            if (!slide) return;
            this.saving = true;
            this.error = '';
            try {
                const { data } = await api.put(
                    `/projects/${this.projectId}/slides/${slide.id}`,
                    this.slidePayload(slide)
                );
                const idx = this.slides.findIndex(s => s.id === data.id);
                if (idx >= 0) {
                    this.slides[idx] = this.enrichSlide({ ...this.slides[idx], ...data });
                    if (this.selectedSlide?.id === data.id) {
                        this.selectedSlide = this.slides[idx];
                    }
                }
                this.message = 'Salvo';
                setTimeout(() => this.message = '', 2000);
                this.syncPublish();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar';
            } finally {
                this.saving = false;
            }
        },

        copyBodyToNarration() {
            const slide = this.selectedSlide;
            if (!slide) return;
            const text = (slide.body_text || '').trim();
            if (!text) {
                this.error = 'Escreva o corpo do slide primeiro.';
                return;
            }
            slide.narration_text = formatNarrationText(text);
            this.scheduleSave();
            this.message = 'Corpo copiado para narração';
        },

        onFullScriptInput() {
            clearTimeout(this.scriptParseTimeout);
            this.scriptParseTimeout = setTimeout(() => this.refreshScriptPreview(), 400);
        },

        refreshScriptPreview() {
            const text = this.fullScript.trim();
            if (!text) {
                this.scriptStats = null;
                return;
            }
            this.scriptStats = parseScript(text).stats;
        },

        onFullScriptPaste(event) {
            const pasted = event.clipboardData?.getData('text') ?? '';
            if (!pasted.trim()) return;

            event.preventDefault();
            const textarea = event.target;
            const start = textarea.selectionStart ?? 0;
            const end = textarea.selectionEnd ?? 0;
            const merged = (this.fullScript.slice(0, start) + pasted + this.fullScript.slice(end)).trim();
            const parsed = parseScript(merged);

            this.fullScript = parsed.formattedScript || merged;
            this.scriptStats = parsed.stats;
            this.applyParsedScript(parsed, { silent: false, fromPaste: true });
        },

        onNarrationPaste(event) {
            const pasted = event.clipboardData?.getData('text') ?? '';
            if (!pasted.trim() || !this.selectedSlide) return;

            const looksLikeFullScript = pasted.length > 200
                || (pasted.includes('\n') && (parseScript(pasted).stats.slides > 1));

            if (looksLikeFullScript) {
                event.preventDefault();
                const parsed = parseScript(pasted);
                this.fullScript = parsed.formattedScript || pasted;
                this.scriptStats = parsed.stats;
                this.activeTab = 'roteiro';
                this.applyParsedScript(parsed, { fromPaste: true });
                this.message = `Roteiro inteiro detectado — ${parsed.stats.slides} slide(s) criado(s)`;
                return;
            }

            event.preventDefault();
            const textarea = event.target;
            const start = textarea.selectionStart ?? 0;
            const end = textarea.selectionEnd ?? 0;
            const merged = (this.selectedSlide.narration_text || '').slice(0, start)
                + pasted
                + (this.selectedSlide.narration_text || '').slice(end);

            this.selectedSlide.narration_text = formatNarrationText(merged);
            this.scheduleSave();
            this.message = 'Texto formatado para narração';
            setTimeout(() => { if (this.message === 'Texto formatado para narração') this.message = ''; }, 2500);
        },

        async applyParsedScript(parsed, { silent = false, fromPaste = false, trimExtra = false } = {}) {
            if (!parsed?.blocks?.length) {
                if (!silent) this.error = 'Nenhum bloco de narração detectado.';
                return;
            }

            try {
                const { data } = await api.post(`/projects/${this.projectId}/slides/apply-script`, {
                    blocks: parsed.blocks.map(({ narration_text, body_text, kind, section_title }) => ({
                        narration_text,
                        body_text,
                        kind,
                        section_title,
                    })),
                    trim_extra_slides: fromPaste || trimExtra,
                });
                this.slides = data.map(s => this.enrichSlide(s));
                this.syncSelection();
                if (!silent) {
                    this.message = `Roteiro aplicado: ${parsed.stats.slides} slide(s) — tempos ajustados automaticamente`;
                }
            } catch (e) {
                if (!silent) this.error = e.response?.data?.message || 'Erro ao aplicar roteiro';
            }
        },

        async applyFullScript() {
            const text = this.fullScript.trim();
            if (!text) {
                this.error = 'Cole ou escreva o roteiro completo primeiro.';
                return;
            }
            const parsed = parseScript(text);
            if (!parsed.blocks.length) {
                this.error = 'Não foi possível detectar blocos de narração no texto.';
                return;
            }
            this.fullScript = parsed.formattedScript;
            this.scriptStats = parsed.stats;
            await this.applyParsedScript(parsed, { trimExtra: true });
        },

        async uploadImage(event) {
            const file = event.target.files[0];
            if (!file || !this.selectedSlide) return;
            await this.uploadAsset(file, 'image');
        },

        async uploadVideo(event) {
            const file = event.target.files[0];
            if (!file || !this.selectedSlide) return;
            try {
                const duration = await probeVideoFileDuration(file);
                const asset = await this.uploadAsset(file, 'video', false);
                this.selectedSlide.video_path = asset.file_path;
                this.selectedSlide.video_url = this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                this.selectedSlide.duration_mode = 'video';
                if (duration && duration > 0) {
                    this.selectedSlide.video_duration_seconds = duration;
                    this.selectedSlide.duration_seconds = Math.round(duration * 10) / 10;
                }
                await this.saveSlide();
                this.message = duration
                    ? `Vídeo inserido — duração ${Math.round(duration * 10) / 10}s (corrido)`
                    : 'Vídeo inserido no slide';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enviar vídeo';
            }
        },

        clearSlideVideo() {
            if (!this.selectedSlide) return;
            this.selectedSlide.video_path = null;
            this.selectedSlide.video_url = null;
            this.selectedSlide.video_duration_seconds = null;
            if (this.selectedSlide.duration_mode === 'video') {
                this.selectedSlide.duration_mode = 'narration';
            }
            this.scheduleSave();
        },

        async uploadAudio(event) {
            const file = event.target.files[0];
            if (!file) return;
            const slot = this.selectedMusicSlot ?? 0;
            const track = this.audioTracks[slot] ?? this.emptyMusicSlot(slot);
            const append = !!track.file_path;

            try {
                const asset = await this.uploadAsset(file, 'audio', false);
                const { data } = await api.post(`/projects/${this.projectId}/audio-tracks`, {
                    asset_id: asset.id,
                    file_path: asset.file_path,
                    track_slot: slot,
                    volume: track.volume ?? 0.35,
                    start_at: track.start_at ?? 0,
                    ducking_enabled: track.ducking_enabled ?? true,
                    loop_enabled: track.loop_enabled !== false,
                    append,
                    label: file.name.replace(/\.[^.]+$/, ''),
                });
                this.audioTracks[slot] = this.enrichAudioTrack(data);
                if (slot === 0) this.audioTrack = this.audioTracks[0];
                this.message = append
                    ? `Trilha adicionada em sequência na ${this.audioTracks[slot].label}`
                    : `${this.audioTracks[slot].label} importada`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar áudio';
            }
        },

        async uploadAsset(file, type, attachToSlide = true, meta = {}) {
            const form = new FormData();
            form.append('file', file);
            form.append('type', type);

            const title = meta.item_title || file.name.replace(/\.[^.]+$/, '');
            form.append('item_title', title);

            if (meta.stock_license_id) {
                form.append('stock_license_id', meta.stock_license_id);
            } else if (this.attachPaidLicenseOnUpload && this.defaultStockLicense && !meta.attribution_text && !meta.author) {
                form.append('stock_license_id', this.defaultStockLicense.id);
            }

            if (meta.author) form.append('author', meta.author);
            if (meta.attribution_text) form.append('attribution_text', meta.attribution_text);
            if (meta.requires_attribution) form.append('requires_attribution', '1');
            if (meta.original_url) form.append('original_url', meta.original_url);
            if (meta.license_type) form.append('license_type', meta.license_type);
            if (meta.item_external_id) form.append('item_external_id', meta.item_external_id);

            const { data: asset } = await api.post(
                `/projects/${this.projectId}/assets/upload`,
                form,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );

            if (attachToSlide && this.selectedSlide && type === 'image') {
                this.selectedSlide.image_path = asset.file_path;
                this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                await this.saveSlide();
            }

            if (asset.stock_license_id) {
                await this.syncPublish();
            }

            if (type === 'image' || type === 'video') {
                this.upsertProjectLibraryAsset({
                    id: asset.id,
                    type: asset.type,
                    source: asset.source,
                    item_title: asset.item_title,
                    file_path: asset.file_path,
                    file_hash: asset.file_hash,
                    url: asset.url || `/api/projects/${this.projectId}/assets/${asset.id}`,
                    preview_url: asset.preview_url || asset.url || `/api/projects/${this.projectId}/assets/${asset.id}`,
                });
            }

            return asset;
        },

        async saveAudioTrack() {
            await this.saveMusicTrack(0);
        },

        async loadProjectLibraryAssets() {
            this.projectLibraryLoading = true;
            try {
                const { data } = await api.get(`/projects/${this.projectId}/assets`);
                this.projectLibraryAssets = this.dedupeProjectLibraryAssets(data.assets || []);
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao carregar biblioteca do projeto';
            } finally {
                this.projectLibraryLoading = false;
            }
        },

        projectLibraryAssetKey(asset) {
            if (asset?.file_hash) {
                return `hash:${asset.file_hash}`;
            }
            if (asset?.file_path) {
                return `path:${asset.file_path}`;
            }

            return `id:${asset?.id}`;
        },

        dedupeProjectLibraryAssets(assets) {
            const seen = new Set();
            const unique = [];
            for (const asset of assets || []) {
                const key = this.projectLibraryAssetKey(asset);
                if (seen.has(key)) {
                    continue;
                }
                seen.add(key);
                unique.push(asset);
            }

            return unique;
        },

        upsertProjectLibraryAsset(asset) {
            if (!asset?.id) {
                return;
            }
            const key = this.projectLibraryAssetKey(asset);
            const rest = (this.projectLibraryAssets || []).filter((a) => this.projectLibraryAssetKey(a) !== key);
            this.projectLibraryAssets = [asset, ...rest];
        },

        async deleteProjectLibraryAsset(asset) {
            if (!asset?.id) {
                return;
            }
            const title = asset.item_title || 'este arquivo';
            if (!window.confirm(`Remover "${title}" da biblioteca deste projeto?\n\nSe o arquivo estiver em um slide, ele continua lá — só some da lista.`)) {
                return;
            }

            try {
                const { data } = await api.delete(`/projects/${this.projectId}/assets/${asset.id}`);
                const key = this.projectLibraryAssetKey(asset);
                this.projectLibraryAssets = (this.projectLibraryAssets || []).filter(
                    (a) => this.projectLibraryAssetKey(a) !== key,
                );
                this.message = data.message || 'Removido da biblioteca do projeto';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao remover da biblioteca';
            }
        },

        projectLibraryVisualAssets() {
            const wantVideo = this.mediaType === 'video';
            return (this.projectLibraryAssets || []).filter((asset) => {
                if (wantVideo) {
                    return asset.type === 'video';
                }
                return asset.type === 'image';
            });
        },

        async insertProjectAssetToSlide(asset) {
            if (!asset?.file_path) {
                return;
            }
            if (!this.selectedSlide) {
                this.error = 'Selecione um slide antes de inserir.';
                return;
            }
            if (asset.type === 'video') {
                this.selectedSlide.video_path = asset.file_path;
                this.selectedSlide.video_url = asset.url || this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                this.selectedSlide.duration_mode = 'video';
            } else {
                this.selectedSlide.image_path = asset.file_path;
                this.selectedSlide.image_url = asset.url || `/api/projects/${this.projectId}/assets/${asset.id}`;
            }
            await this.saveSlide();
            this.message = `"${asset.item_title || 'Arquivo'}" inserido no slide`;
        },

        async searchMedia(append = false) {
            const query = this.mediaQuery.trim();
            if (query.length < 2) {
                this.error = 'Digite pelo menos 2 caracteres para buscar.';
                return;
            }

            const nextPage = append ? (this.mediaSearchPage + 1) : 1;
            const seq = ++this._mediaSearchSeq;
            this.mediaSearching = true;
            if (!append) {
                this.mediaErrors = [];
                this.mediaResults = [];
                this.mediaSearchPage = 1;
                this.mediaHasMore = false;
            }
            this.error = '';
            try {
                const { data } = await api.get('/media/search', {
                    params: {
                        query,
                        source: this.mediaSource,
                        type: this.mediaSearchType(),
                        page: nextPage,
                    },
                });
                if (seq !== this._mediaSearchSeq) {
                    return;
                }

                const incoming = data.results || [];
                if (append) {
                    const before = this.mediaResults.length;
                    this.mediaResults = this.mergeMediaResults(this.mediaResults, incoming);
                    this.mediaSearchPage = nextPage;
                    this.mediaHasMore = this.mediaResults.length > before && !!data.has_more;
                } else {
                    this.mediaResults = incoming;
                    this.mediaSearchPage = 1;
                    this.mediaHasMore = !!data.has_more;
                }
                this.mediaErrors = data.errors || [];

                if (this.mediaResults.length) {
                    const search = data.search || {};
                    const hint = search.hint
                        ? ` — ${search.hint}`
                        : search.translated
                            ? ` (${search.query} → ${search.primary})`
                            : '';
                    const moreHint = this.mediaHasMore ? ' · use Ver mais ou Expande' : '';
                    this.message = `${this.mediaResults.length} resultado(s)${hint}${moreHint} — clique para inserir`;
                } else if (data.search?.hint || data.search?.translated) {
                    this.message = (data.search.hint || `Buscamos como "${data.search.primary}"`) + ' — nenhum resultado ainda.';
                } else {
                    this.message = append
                        ? 'Não há mais resultados para esta busca.'
                        : 'Nenhum resultado — tente outro termo ou use Meu arquivo / Por link.';
                    this.mediaHasMore = false;
                }
            } catch (e) {
                if (seq === this._mediaSearchSeq) {
                    this.error = e.response?.data?.message || 'Erro na busca';
                }
            } finally {
                if (seq === this._mediaSearchSeq) {
                    this.mediaSearching = false;
                }
            }
        },

        async importMedia(item, options = {}) {
            const isMusic = item.type === 'audio' || this.mediaType === 'music';
            const isSfx = item.type === 'sfx' || this.mediaType === 'sfx';

            if (!isMusic && !isSfx && !this.selectedSlide) {
                this.error = 'Selecione um slide antes de inserir mídia.';
                return;
            }

            const target = isSfx ? 'sound_effect' : (isMusic ? 'audio_track' : 'slide');
            const payload = {
                item,
                target,
                slide_id: this.selectedSlide?.id,
            };

            if (isMusic) {
                payload.track_slot = options.track_slot ?? this.selectedMusicSlot ?? 0;
                if (options.start_at != null) {
                    payload.start_at = options.start_at;
                    payload.place_at = options.place_at !== false;
                }
            }

            if (isSfx) {
                const startAt = options.start_at ?? this.mediaSfxStartAt ?? Math.round(this.timelinePlayheadSec * 10) / 10;
                payload.start_at = startAt;
                payload.place_at = options.place_at !== false;
                payload.label = item.title || 'Efeito';
            }

            try {
                const { data } = await api.post(`/projects/${this.projectId}/media/import`, payload);

                this.applyPublish(data.publish);

                if (target === 'slide' && this.selectedSlide) {
                    const asset = data.asset;
                    const slideFromServer = data.slide;

                    if (slideFromServer) {
                        const idx = this.slides.findIndex(s => s.id === slideFromServer.id);
                        const merged = this.enrichSlide({ ...(idx >= 0 ? this.slides[idx] : {}), ...slideFromServer });
                        if (idx >= 0) {
                            this.slides[idx] = merged;
                        }
                        if (this.selectedSlide?.id === merged.id) {
                            this.selectedSlide = merged;
                        }
                    } else if (asset) {
                        if (item.type === 'video') {
                            this.selectedSlide.video_path = asset.file_path;
                            this.selectedSlide.video_url = this.fileUrl('assets', asset.file_path.split(/[/\\]/).pop());
                            this.selectedSlide.duration_mode = 'video';
                            if (item.duration_seconds && item.duration_seconds > 0) {
                                this.selectedSlide.video_duration_seconds = item.duration_seconds;
                                this.selectedSlide.duration_seconds = Math.round(item.duration_seconds * 10) / 10;
                            }
                        } else {
                            this.selectedSlide.image_path = asset.file_path;
                            this.selectedSlide.image_url = `/api/projects/${this.projectId}/assets/${asset.id}`;
                        }
                        await this.saveSlide();
                        const libAsset = {
                            id: asset.id,
                            type: asset.type || (item.type === 'video' ? 'video' : 'image'),
                            source: asset.source || item.source,
                            item_title: asset.item_title || item.title,
                            file_path: asset.file_path,
                            file_hash: asset.file_hash,
                            url: `/api/projects/${this.projectId}/assets/${asset.id}`,
                            preview_url: `/api/projects/${this.projectId}/assets/${asset.id}`,
                        };
                        this.upsertProjectLibraryAsset(libAsset);
                    }

                    this.message = data.publish?.message || 'Mídia inserida — créditos atualizados na exportação';
                } else if (data.audio_track) {
                    const slot = data.audio_track.track_slot ?? 0;
                    const hadTrack = !!this.audioTracks[slot]?.file_path;
                    this.audioTracks[slot] = this.enrichAudioTrack(data.audio_track);
                    if (slot === 0) this.audioTrack = this.audioTracks[0];
                    if (options.start_at != null) {
                        this.message = data.publish?.message
                            || `Trilha em ${this.formatTimelineTime(options.start_at)} · ${this.audioTracks[slot].label}`;
                    } else {
                        this.message = data.publish?.message || (hadTrack
                            ? `Trilha encadeada na ${this.audioTracks[slot].label} — licença registrada`
                            : `${this.audioTracks[slot].label} importada — licença registrada`);
                    }
                    this.activeTab = 'audio';
                } else if (data.sound_effect) {
                    this.soundEffects.push(this.enrichSoundEffect({
                        ...data.sound_effect,
                        asset: data.asset || data.sound_effect?.asset,
                    }));
                    if (options.start_at != null) {
                        this.message = data.publish?.message
                            || `Efeito em ${this.formatTimelineTime(options.start_at)} — crédito na descrição`;
                    } else {
                        this.message = data.publish?.message || `Efeito "${data.sound_effect.label}" adicionado — crédito na descrição`;
                    }
                    this.activeTab = 'audio';
                }

                await this.loadDownloads();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar';
            }
        },

        async loadNarration() {
            const { data } = await api.get(`/projects/${this.projectId}/narration`);
            if (data?.audio_path) {
                data.audio_url = this.fileUrl('audio', data.audio_path.split(/[/\\]/).pop());
            }
            if (data?.id) {
                data.trim_in = parseFloat(data.trim_in) || 0;
                data.trim_out = data.trim_out != null ? parseFloat(data.trim_out) : null;
            }
            this.narration = data?.id ? data : null;
        },

        slideNarrationText() {
            const slide = this.selectedSlide;
            if (!slide) return '';
            const text = (slide.narration_text || '').trim();
            if (text) return formatNarrationText(text);
            return formatNarrationText((slide.body_text || '').trim() || (slide.narration_text || '').trim());
        },

        async testNarration() {
            const text = this.slideNarrationText();
            if (!text) {
                this.error = 'Escreva narração ou preencha o corpo do slide.';
                return;
            }
            this.previewLoading = true;
            this.error = '';
            try {
                const { data } = await api.post(`/projects/${this.projectId}/narration/preview`, {
                    text,
                    voice: this.voice,
                    engine: this.ttsEngine,
                });
                this.previewAudioUrl = data.audio_url;
                if (data.engine_used && data.engine_used !== this.ttsEngine) {
                    this.ttsEngine = data.engine_used;
                    await this.loadVoices();
                    this.message = `Teste gerado com ${data.engine_used} (Edge falhou — troca automática)`;
                } else {
                    this.message = `Teste gerado (${Math.round(data.duration_seconds || 0)}s) — ouça abaixo`;
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao testar narração';
            } finally {
                this.previewLoading = false;
            }
        },

        async generateNarration() {
            this.narrationLoading = true;
            this.error = '';
            try {
                if (this.fullScript.trim()) {
                    await this.applyFullScript();
                }
                await this.saveSlide();
                const { data } = await api.post(`/projects/${this.projectId}/narration/generate`, {
                    voice: this.voice,
                    engine: this.ttsEngine,
                });
                if (data?.audio_path) {
                    data.audio_url = this.fileUrl('audio', data.audio_path.split(/[/\\]/).pop());
                }
                this.narration = data;
                this.previewAudioUrl = data.audio_url;
                this.message = 'Narração completa gerada — ouça abaixo';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar narração';
            } finally {
                this.narrationLoading = false;
            }
        },

        async syncNarration() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/narration/sync`);
                this.slides = data.map(s => this.enrichSlide(s));
                this.syncSelection();
                this.message = 'Slides sincronizados';
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao sincronizar';
            }
        },

        async loadRenderJobs() {
            const { data } = await api.get(`/projects/${this.projectId}/render-jobs`);
            this.renderJobs = data.map(job => ({
                ...job,
                output_url: job.output_path
                    ? this.fileUrl('exports', job.output_path.split(/[/\\]/).pop())
                    : null,
            }));
        },

        async renderVideo(preset) {
            try {
                await api.post(`/projects/${this.projectId}/render-jobs`, {
                    preset,
                    burn_subtitles: this.burnSubtitles,
                });
                this.message = `Render ${preset} enfileirado`;
                await this.loadRenderJobs();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao enfileirar render';
            }
        },

        async retryRender(job) {
            try {
                await api.post(`/projects/${this.projectId}/render-jobs/${job.id}/retry`);
                this.message = 'Render reenfileirado';
                await this.loadRenderJobs();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao reenfileirar';
            }
        },

        async generateThumb() {
            await this.generateThumbnailFinal(false);
        },

        switchThumbnailPlatform(slug) {
            this.thumbnailSettingsByPlatform[this.selectedThumbnailPlatform] = { ...this.thumbnailSettings };
            this.selectedThumbnailPlatform = slug;
            const saved = this.thumbnailSettingsByPlatform[slug];
            this.thumbnailSettings = {
                ...this.thumbnailSettings,
                ...(saved || {}),
                platform_preset: slug,
            };
            this.thumbnailPreviewUrl = this.thumbnailPreviewUrls[slug] || null;
            clearTimeout(this.thumbnailPreviewTimeout);
            clearTimeout(this.thumbnailTextPreviewTimeout);
            this.thumbnailTextEditing = false;
            this.resetThumbnailPreviewScroll();
        },

        resetThumbnailPreviewScroll() {
            this.thumbnailPreviewPanY = 0;
            this.$nextTick(() => {
                const el = this.$refs.thumbnailPreviewScroll;
                if (el) {
                    el.scrollTop = 0;
                }
            });
        },

        syncThumbnailPreviewPanFromScroll() {
            const el = this.$refs.thumbnailPreviewScroll;
            if (!el) {
                return;
            }
            const max = el.scrollHeight - el.clientHeight;
            if (max <= 0) {
                this.thumbnailPreviewPanY = 0;
                return;
            }
            this.thumbnailPreviewPanY = Math.round((el.scrollTop / max) * 100);
        },

        onThumbnailPreviewPanInput() {
            const el = this.$refs.thumbnailPreviewScroll;
            if (!el) {
                return;
            }
            const max = el.scrollHeight - el.clientHeight;
            if (max <= 0) {
                return;
            }
            el.scrollTop = (this.thumbnailPreviewPanY / 100) * max;
        },

        /** Mescla settings do servidor sem sobrescrever texto que o usuário está editando. */
        applyThumbnailSettingsPatch(patch, { includeText = false } = {}) {
            if (!patch || typeof patch !== 'object') {
                return;
            }

            const textKeys = ['title_text', 'subtitle_text'];

            Object.keys(patch).forEach((key) => {
                if (!includeText && textKeys.includes(key)) {
                    return;
                }
                this.thumbnailSettings[key] = patch[key];
            });
        },

        syncThumbnailPlatformCache() {
            this.thumbnailSettingsByPlatform[this.selectedThumbnailPlatform] = { ...this.thumbnailSettings };
        },

        onThumbnailTextInput() {
            this.thumbnailTextEditing = true;
            clearTimeout(this.thumbnailTextPreviewTimeout);
            this.thumbnailTextPreviewTimeout = setTimeout(() => {
                this.thumbnailTextEditing = false;
                this.saveAndPreviewThumbnail();
            }, 1400);
        },

        flushThumbnailTextSave() {
            clearTimeout(this.thumbnailTextPreviewTimeout);
            this.thumbnailTextEditing = false;
            this.saveAndPreviewThumbnail();
        },

        async loadThumbnailCatalog() {
            try {
                const { data } = await api.get('/thumbnail/templates');
                this.thumbnailTemplates = data.templates || [];
                this.thumbnailPlatforms = data.platforms || [];
                this.thumbnailFrames = data.frames || [];
                this.thumbnailFrameCategories = data.frame_categories || {};
                if (data.frame_library) {
                    this.frameCustomCategories = data.frame_library.custom_categories || {};
                }
                this.thumbnailFonts = data.fonts || [];
                await this.loadFrameLibraryDetails();
            } catch (_) {
                /* opcional */
            }
        },

        async loadThumbnailSettings() {
            try {
                const { data } = await api.get(`/projects/${this.projectId}/thumbnail`, {
                    params: { platform: this.selectedThumbnailPlatform },
                });
                this.thumbnailSettingsByPlatform = data.all || {};
                if (data.platform) {
                    this.selectedThumbnailPlatform = data.platform;
                }
                if (data.settings) {
                    this.thumbnailSettings = { ...this.thumbnailSettings, ...data.settings };
                }
            } catch (_) {
                /* primeiro uso */
            }
        },

        scheduleThumbnailPreview() {
            clearTimeout(this.thumbnailPreviewTimeout);
            this.thumbnailPreviewTimeout = setTimeout(() => this.saveAndPreviewThumbnail(), 700);
        },

        async saveThumbnailSettings() {
            this.thumbnailSaving = true;
            try {
                const slideIndex = this.resolveThumbnailSlideIndex();
                const payload = {
                    ...this.thumbnailSettings,
                    platform_preset: this.selectedThumbnailPlatform,
                    slide_index: slideIndex,
                    slide_id: this.thumbnailSettings.image_source === 'slide'
                        ? (this.slides[slideIndex]?.id ?? null)
                        : null,
                };
                const { data } = await api.put(`/projects/${this.projectId}/thumbnail`, payload);
                this.applyThumbnailSettingsPatch(data.settings);
                this.syncThumbnailPlatformCache();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao salvar thumbnail';
            } finally {
                this.thumbnailSaving = false;
            }
        },

        async uploadThumbnailImage(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            const form = new FormData();
            form.append('image', file);
            form.append('platform_preset', this.selectedThumbnailPlatform);

            try {
                const { data } = await api.post(`/projects/${this.projectId}/thumbnail/upload`, form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                if (data.settings) {
                    this.applyThumbnailSettingsPatch(data.settings, { includeText: true });
                }
                this.syncThumbnailPlatformCache();
                this.message = 'Imagem de capa importada';
                await this.saveAndPreviewThumbnail();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar imagem';
            } finally {
                event.target.value = '';
            }
        },

        onThumbnailImageSourceChange() {
            if (this.thumbnailSettings.image_source !== 'upload') {
                this.scheduleThumbnailPreview();
            }
        },

        resolveThumbnailSlideIndex() {
            const index = Number(this.thumbnailSettings.slide_index);
            if (!Number.isInteger(index) || index < 0) {
                return 0;
            }

            return Math.min(index, Math.max(0, this.slides.length - 1));
        },

        resolveThumbnailSlideId() {
            const index = this.resolveThumbnailSlideIndex();
            return this.slides[index]?.id ?? null;
        },

        onThumbnailSlideChange() {
            this.thumbnailSettings.slide_index = this.resolveThumbnailSlideIndex();
            this.thumbnailSettings.image_source = 'slide';
            this.thumbnailSettings.custom_image_path = null;
            this.syncThumbnailPlatformCache();
            clearTimeout(this.thumbnailPreviewTimeout);
            this.saveAndPreviewThumbnail();
        },

        buildThumbnailRenderPayload(preview = false) {
            const slideIndex = this.resolveThumbnailSlideIndex();
            const useSlide = this.thumbnailSettings.image_source === 'slide';

            return {
                platform_preset: this.selectedThumbnailPlatform,
                preview,
                template: this.thumbnailSettings.template,
                image_source: this.thumbnailSettings.image_source,
                custom_image_path: this.thumbnailSettings.custom_image_path,
                slide_index: slideIndex,
                slide_id: useSlide ? (this.slides[slideIndex]?.id ?? null) : null,
                title_text: this.thumbnailSettings.title_text,
                subtitle_text: this.thumbnailSettings.subtitle_text,
                title_color: this.thumbnailSettings.title_color,
                subtitle_color: this.thumbnailSettings.subtitle_color,
                accent_color: this.thumbnailSettings.accent_color,
                accent_opacity: this.thumbnailSettings.accent_opacity,
                background_color: this.thumbnailSettings.background_color,
                background_opacity: this.thumbnailSettings.background_opacity,
                font_family: this.thumbnailSettings.font_family,
                title_size: this.thumbnailSettings.title_size,
                subtitle_size: this.thumbnailSettings.subtitle_size,
                brightness: this.thumbnailSettings.brightness,
                contrast: this.thumbnailSettings.contrast,
                overlay_opacity: this.thumbnailSettings.overlay_opacity,
                text_align: this.thumbnailSettings.text_align,
                vertical_align: this.thumbnailSettings.vertical_align,
                frame_slug: this.thumbnailSettings.frame_slug,
                frame_color: this.thumbnailSettings.frame_color,
                frame_secondary_color: this.thumbnailSettings.frame_secondary_color,
                frame_width: this.thumbnailSettings.frame_width,
                frame_opacity: this.thumbnailSettings.frame_opacity,
                frame_inset: this.thumbnailSettings.frame_inset,
            };
        },

        async saveAndPreviewThumbnail() {
            await this.saveThumbnailSettings();
            await this.generateThumbnailFinal(true, { skipSave: true });
        },

        async generateThumbnailFinal(preview = false, { skipSave = false } = {}) {
            try {
                if (!skipSave) {
                    await this.saveThumbnailSettings();
                }
                const { data } = await api.post(
                    `/projects/${this.projectId}/thumbnail/generate`,
                    this.buildThumbnailRenderPayload(preview)
                );
                if (data.url) {
                    this.thumbnailPreviewUrl = data.url;
                    this.thumbnailPreviewUrls[this.selectedThumbnailPlatform] = data.url;
                }
                if (!preview) {
                    const platform = this.thumbnailPlatforms.find((p) => p.slug === this.selectedThumbnailPlatform);
                    this.message = `Capa ${platform?.name || ''} gerada`;
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar thumbnail';
            }
        },

        async generateAllPlatformThumbnails() {
            try {
                await this.saveThumbnailSettings();
                const { data } = await api.post(`/projects/${this.projectId}/thumbnail/generate`, {
                    all_platforms: true,
                });
                (data.generated || []).forEach((item) => {
                    this.thumbnailPreviewUrls[item.platform] = item.url;
                });
                this.thumbnailPreviewUrl = this.thumbnailPreviewUrls[this.selectedThumbnailPlatform] || this.thumbnailPreviewUrl;
                this.message = `${data.count} capa(s) gerada(s) para as plataformas`;
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao gerar capas';
            }
        },

        setThumbnailTextAlign(value) {
            this.thumbnailSettings.text_align = value;
            this.syncThumbnailPlatformCache();
            this.scheduleThumbnailPreview();
        },

        setThumbnailVerticalAlign(value) {
            this.thumbnailSettings.vertical_align = value;
            this.syncThumbnailPlatformCache();
            this.scheduleThumbnailPreview();
        },

        disableThumbnailAccent() {
            this.thumbnailSettings.accent_opacity = 0;
            this.syncThumbnailPlatformCache();
            this.scheduleThumbnailPreview();
        },

        disableThumbnailBackground() {
            this.thumbnailSettings.background_opacity = 0;
            this.syncThumbnailPlatformCache();
            this.scheduleThumbnailPreview();
        },

        enableThumbnailAccent() {
            if ((this.thumbnailSettings.accent_opacity ?? 0) <= 0) {
                this.thumbnailSettings.accent_opacity = 70;
            }
            this.syncThumbnailPlatformCache();
            this.scheduleThumbnailPreview();
        },

        enableThumbnailBackground() {
            if ((this.thumbnailSettings.background_opacity ?? 0) <= 0) {
                this.thumbnailSettings.background_opacity = 45;
            }
            this.syncThumbnailPlatformCache();
            this.scheduleThumbnailPreview();
        },

        selectThumbnailTemplate(slug) {
            this.thumbnailSettings.template = slug;
            this.syncThumbnailPlatformCache();
            this.scheduleThumbnailPreview();
        },

        selectThumbnailFrame(slug) {
            this.thumbnailSettings.frame_slug = slug;
            this.syncThumbnailPlatformCache();
            const frame = this.thumbnailFrames.find((f) => f.slug === slug);
            if (frame?.default_color) {
                this.thumbnailSettings.frame_color = frame.default_color;
            }
            this.scheduleThumbnailPreview();
        },

        clearThumbnailFrame() {
            this.thumbnailSettings.frame_slug = 'none';
            this.scheduleThumbnailPreview();
        },

        applyFrameCatalog(catalog) {
            if (!catalog) return;
            this.thumbnailFrames = catalog.frames || [];
            this.thumbnailFrameCategories = catalog.categories || {};
            if (catalog.library) {
                this.frameCustomCategories = catalog.library.custom_categories || {};
            }
        },

        async loadFrameLibraryDetails() {
            try {
                const { data } = await api.get('/thumbnail/frames/library');
                this.applyFrameCatalog(data.catalog);
                this.frameLibraryHiddenFrames = data.hidden_frames || [];
                this.frameLibraryHiddenCategories = data.hidden_categories || [];
                this.frameCustomCategories = data.custom_categories || {};
            } catch (_) {
                /* opcional */
            }
        },

        async toggleFrameManageMode() {
            this.frameManageMode = !this.frameManageMode;
            if (this.frameManageMode) {
                await this.loadFrameLibraryDetails();
            }
        },

        async createFrameCollection() {
            const label = (this.newFrameCollectionName || '').trim();
            if (!label) {
                this.error = 'Informe o nome do conjunto de molduras';
                return;
            }
            try {
                const { data } = await api.post('/thumbnail/frames/categories', { label });
                this.applyFrameCatalog(data.catalog);
                this.newFrameCollectionName = '';
                this.newCustomFrameCategory = data.slug;
                this.message = `Conjunto «${label}» criado`;
                await this.loadFrameLibraryDetails();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao criar conjunto';
            }
        },

        async uploadCustomFrame(event) {
            const file = event?.target?.files?.[0];
            if (!file) return;

            const name = (this.newCustomFrameName || '').trim() || file.name.replace(/\.[^.]+$/, '');
            const form = new FormData();
            form.append('image', file);
            form.append('name', name);
            form.append('category', this.newCustomFrameCategory || 'personalizado');

            try {
                const { data } = await api.post('/thumbnail/frames', form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                this.applyFrameCatalog(data.catalog);
                this.newCustomFrameName = '';
                this.selectedFrameCategory = data.frame?.category || 'personalizado';
                this.message = `Moldura «${name}» adicionada às suas molduras`;
                await this.loadFrameLibraryDetails();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao importar moldura';
            } finally {
                if (event?.target) event.target.value = '';
            }
        },

        async deleteThumbnailFrame(slug, event) {
            event?.stopPropagation?.();
            if (slug === 'none') return;
            const frame = this.thumbnailFrames.find((f) => f.slug === slug);
            const label = frame?.name || slug;
            if (!confirm(`Remover moldura «${label}»?${frame?.is_custom ? '' : ' Ela sairá da sua lista (pode restaurar depois).'}`)) {
                return;
            }
            try {
                const { data } = await api.delete(`/thumbnail/frames/${encodeURIComponent(slug)}`);
                this.applyFrameCatalog(data.catalog);
                if (this.thumbnailSettings.frame_slug === slug) {
                    this.thumbnailSettings.frame_slug = 'none';
                    this.scheduleThumbnailPreview();
                }
                this.message = 'Moldura removida';
                await this.loadFrameLibraryDetails();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao remover moldura';
            }
        },

        async deleteFrameCategory(slug) {
            const label = this.frameCategoryLabel(slug);
            const isCustom = this.isCustomFrameCategory(slug);
            const msg = isCustom
                ? `Excluir permanentemente a pasta «${label}» e todas as molduras dentro dela?`
                : `Ocultar o conjunto «${label}» e suas molduras? (você pode restaurar depois em Gerenciar molduras)`;

            if (!confirm(msg)) {
                return;
            }
            try {
                const { data } = await api.delete(`/thumbnail/frames/categories/${encodeURIComponent(slug)}`);
                this.applyFrameCatalog(data.catalog);
                if (this.selectedFrameCategory === slug) {
                    this.selectedFrameCategory = 'all';
                }
                if (this.newCustomFrameCategory === slug) {
                    this.newCustomFrameCategory = 'personalizado';
                }
                this.message = isCustom ? 'Pasta removida' : 'Conjunto oculto';
                await this.loadFrameLibraryDetails();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao remover conjunto';
            }
        },

        async restoreHiddenFrame(slug) {
            try {
                const { data } = await api.post(`/thumbnail/frames/${encodeURIComponent(slug)}/restore`);
                this.applyFrameCatalog(data.catalog);
                this.message = 'Moldura restaurada';
                await this.loadFrameLibraryDetails();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao restaurar moldura';
            }
        },

        async restoreHiddenCategory(slug) {
            try {
                const { data } = await api.post(`/thumbnail/frames/categories/${encodeURIComponent(slug)}/restore`);
                this.applyFrameCatalog(data.catalog);
                this.message = 'Conjunto restaurado';
                await this.loadFrameLibraryDetails();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao restaurar conjunto';
            }
        },

        thumbnailPlatformHint() {
            return this.thumbnailPlatforms.find((p) => p.slug === this.selectedThumbnailPlatform)?.hint || '';
        },

        async exportSubtitles() {
            try {
                this.error = '';
                const { data } = await api.post(`/projects/${this.projectId}/subtitles`);
                this.message = 'legendas.srt gerado';
                if (data.url) window.open(data.url, '_blank');
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar legendas';
            }
        },

        async exportPsd() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/export-psd`);
                this.message = 'ZIP PSD/PNG gerado';
                if (data.url) window.open(data.url, '_blank');
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar PSD';
            }
        },

        async exportPackage() {
            try {
                await api.post(`/projects/${this.projectId}/export-packages`, { preset: 'youtube_landscape' });
                this.message = 'Pacote enfileirado';
                await this.loadExportPackages();
                await this.loadDownloads();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar pacote';
            }
        },

        async exportBundle() {
            try {
                const { data } = await api.post(`/projects/${this.projectId}/export-bundle`);
                this.message = data.message || 'Bundle gerado';
                if (data.url) window.open(data.url, '_blank');
                await this.loadDownloads();
            } catch (e) {
                this.error = e.response?.data?.message || 'Erro ao exportar bundle';
            }
        },
    };
};
