import { Rect, Circle, Ellipse, Line, Triangle, Polygon, Path } from 'fabric';

export const EMOJI_FONT_STACK = '"Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif';

export const BLOB_PATHS = {
    1: 'M 78 12 C 95 28 98 52 88 72 C 78 95 52 102 32 92 C 8 78 2 52 14 30 C 28 6 58 2 78 12 Z',
    2: 'M 62 8 C 88 10 102 34 96 58 C 90 88 62 104 38 96 C 12 86 0 58 8 34 C 18 12 42 6 62 8 Z',
    3: 'M 55 5 C 82 8 98 32 94 55 C 88 82 62 98 35 90 C 10 78 2 50 12 28 C 22 8 38 2 55 5 Z',
    4: 'M 70 15 C 92 22 104 48 96 70 C 86 96 58 108 34 98 C 10 84 0 56 10 32 C 22 10 48 8 70 15 Z',
    5: 'M 48 10 C 72 6 96 26 98 50 C 100 78 78 98 52 102 C 24 104 4 82 6 54 C 8 26 28 12 48 10 Z',
    6: 'M 60 6 C 86 12 100 38 92 62 C 82 90 54 106 28 94 C 6 78 0 48 10 26 C 20 8 40 2 60 6 Z',
};

export const SHAPE_PATHS = {
    heart: 'M 50 88 C 20 62 2 42 2 26 C 2 10 14 2 28 2 C 38 2 46 8 50 16 C 54 8 62 2 72 2 C 86 2 98 10 98 26 C 98 42 80 62 50 88 Z',
    burst: 'M 50 2 L 58 34 L 92 22 L 72 50 L 98 72 L 66 66 L 72 98 L 50 76 L 28 98 L 34 66 L 2 72 L 28 50 L 8 22 L 42 34 Z',
    cloud: 'M 75 55 C 88 55 98 45 98 32 C 98 18 86 8 72 8 C 68 8 64 9 61 11 C 54 4 44 0 33 0 C 14 0 0 14 0 32 C 0 48 12 58 28 58 L 75 55 Z',
    speech: 'M 12 8 L 88 8 Q 98 8 98 18 L 98 58 Q 98 68 88 68 L 42 68 L 28 82 L 32 68 L 12 68 Q 2 68 2 58 L 2 18 Q 2 8 12 8 Z',
    arrow: 'M 4 38 L 68 38 L 68 22 L 96 50 L 68 78 L 68 62 L 4 62 Z',
    arrow_outline: 'M 8 42 L 62 42 L 62 28 L 88 50 L 62 72 L 62 58 L 8 58 Z',
    badge: 'M 50 4 L 62 34 L 94 34 L 68 54 L 78 86 L 50 68 L 22 86 L 32 54 L 6 34 L 38 34 Z',
    chevron: 'M 20 25 L 50 55 L 80 25 L 80 45 L 50 75 L 20 45 Z',
    semicircle: 'M 10 50 A 40 40 0 0 1 90 50 L 90 100 L 10 100 Z',
    arc: 'M 15 75 A 35 35 0 0 1 85 75',
    cross: 'M 38 8 L 62 8 L 62 38 L 92 38 L 92 62 L 62 62 L 62 92 L 38 92 L 38 62 L 8 62 L 8 38 L 38 38 Z',
    cube: 'M 50 8 L 88 28 L 88 58 L 50 78 L 12 58 L 12 28 Z M 50 78 L 50 92 L 88 72 L 88 58 M 50 78 L 12 58 L 12 72 L 50 92 Z',
    cube_outline: 'M 50 8 L 88 28 L 88 58 L 50 78 L 12 58 L 12 28 Z M 50 78 L 50 92 L 88 72 M 50 78 L 12 72 L 50 92 Z',
    cylinder: 'M 20 30 C 20 18 80 18 80 30 L 80 70 C 80 82 20 82 20 70 Z M 20 30 C 20 42 80 42 80 30',
    cone: 'M 50 12 L 88 88 L 12 88 Z M 12 88 C 12 88 50 72 88 88',
    pyramid: 'M 50 10 L 90 75 L 10 75 Z M 10 75 L 50 55 L 90 75',
    sphere: 'M 50 8 C 78 8 92 28 92 50 C 92 72 78 92 50 92 C 22 92 8 72 8 50 C 8 28 22 8 50 8 M 50 8 C 62 22 62 78 50 92 M 22 50 C 36 58 64 58 78 50',
    corner_bracket: 'M 12 88 L 12 28 L 28 28 L 28 72 L 72 72 L 72 88 Z',
};

export function regularPolygonPoints(sides, radius) {
    const points = [];
    for (let i = 0; i < sides; i += 1) {
        const angle = ((Math.PI * 2 * i) / sides) - (Math.PI / 2);
        points.push({
            x: radius * Math.cos(angle),
            y: radius * Math.sin(angle),
        });
    }

    return points;
}

export function starPolygonPoints(outerRadius, innerRadius, spikes = 5) {
    const points = [];
    for (let i = 0; i < spikes * 2; i += 1) {
        const radius = i % 2 === 0 ? outerRadius : innerRadius;
        const angle = (Math.PI * i) / spikes - (Math.PI / 2);
        points.push({
            x: radius * Math.cos(angle),
            y: radius * Math.sin(angle),
        });
    }

    return points;
}

export function canvasShapeSize(designWidth, designHeight, ratio = 0.28) {
    return Math.min(designWidth, designHeight) * ratio;
}

export function centerObjectOnCanvas(object, designWidth, designHeight) {
    object.set({
        originX: 'center',
        originY: 'center',
        left: designWidth / 2,
        top: designHeight / 2,
    });
    object.setCoords();
}

export function addCanvasObject(canvas, object, designWidth, designHeight, configureFn) {
    centerObjectOnCanvas(object, designWidth, designHeight);
    configureFn?.(object);
    canvas.add(object);
    canvas.setActiveObject(object);
    canvas.requestRenderAll();
    return object;
}

function baseMeta(spec, id, baseName, opacity) {
    return {
        opacity,
        name: baseName,
        criasysId: id,
    };
}

function solidPaint(spec, fallbackFill = '#ffffff') {
    return {
        fill: spec.fill ?? fallbackFill,
        stroke: spec.stroke || '',
        strokeWidth: spec.strokeWidth || 0,
    };
}

function outlinePaint(spec, fallbackStroke = '#ffffff', fallbackWidth = 8) {
    return {
        fill: 'transparent',
        stroke: spec.stroke ?? fallbackStroke,
        strokeWidth: spec.strokeWidth ?? fallbackWidth,
    };
}

function pathShape(spec, pathKey, size, id, baseName, opacity, paint) {
    const pathData = spec.path || SHAPE_PATHS[pathKey];
    if (!pathData) {
        return null;
    }
    const scale = size / 100;
    return new Path(pathData, {
        ...paint,
        ...baseMeta(spec, id, baseName, opacity),
        scaleX: scale,
        scaleY: scale,
    });
}

function polygonFromPoints(points, spec, id, baseName, opacity, paint) {
    return new Polygon(points, {
        ...paint,
        ...baseMeta(spec, id, baseName, opacity),
    });
}

export function createShapeFromSpec(spec, designWidth, designHeight) {
    const type = spec.type || spec.kind;
    const size = canvasShapeSize(designWidth, designHeight, spec.sizeRatio || 0.28);
    const opacity = (spec.opacity ?? 100) / 100;
    const id = (spec.slug || type) + '_' + Date.now();
    const baseName = spec.name || 'Elemento';
    const meta = baseMeta(spec, id, baseName, opacity);

    if (type === 'rect') {
        return new Rect({
            width: size * 1.4,
            height: size * 0.75,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'square') {
        return new Rect({
            width: size,
            height: size,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'rounded_rect') {
        const rx = spec.rx || 24;
        return new Rect({
            width: size * 1.4,
            height: size * 0.75,
            rx,
            ry: spec.ry ?? rx,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'pill') {
        const h = size * 0.45;
        return new Rect({
            width: size * 1.5,
            height: h,
            rx: h / 2,
            ry: h / 2,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'rect_outline') {
        return new Rect({
            width: size * 1.4,
            height: size * 0.75,
            ...outlinePaint(spec),
            ...meta,
        });
    }

    if (type === 'square_outline') {
        return new Rect({
            width: size,
            height: size,
            ...outlinePaint(spec),
            ...meta,
        });
    }

    if (type === 'rounded_rect_outline') {
        const rx = spec.rx || 24;
        return new Rect({
            width: size * 1.4,
            height: size * 0.75,
            rx,
            ry: spec.ry ?? rx,
            ...outlinePaint(spec),
            ...meta,
        });
    }

    if (type === 'pill_outline') {
        const h = size * 0.45;
        return new Rect({
            width: size * 1.5,
            height: h,
            rx: h / 2,
            ry: h / 2,
            ...outlinePaint(spec),
            ...meta,
        });
    }

    if (type === 'frame_rect' || type === 'frame_rounded') {
        const rx = type === 'frame_rounded' ? (spec.rx || 32) : 0;
        return new Rect({
            width: size,
            height: size * (designHeight / designWidth),
            rx,
            ry: spec.ry ?? rx,
            ...outlinePaint(spec, '#ffffff', spec.strokeWidth || 24),
            ...meta,
        });
    }

    if (type === 'bar_h') {
        return new Rect({
            width: size * 2.2,
            height: size * 0.18,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'bar_v') {
        return new Rect({
            width: size * 0.18,
            height: size * 2.2,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'letterbox') {
        return new Rect({
            width: size * 2.5,
            height: size * 0.2,
            ...solidPaint(spec, '#000000'),
            ...meta,
        });
    }

    if (type === 'circle') {
        return new Circle({
            radius: size / 2,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'ellipse') {
        return new Ellipse({
            rx: size * (spec.rxRatio ?? 0.55),
            ry: size * (spec.ryRatio ?? 0.35),
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'ring') {
        return new Circle({
            radius: size / 2,
            fill: 'transparent',
            stroke: spec.stroke ?? spec.fill ?? '#ffffff',
            strokeWidth: spec.strokeWidth || 10,
            ...meta,
        });
    }

    if (type === 'triangle') {
        return new Triangle({
            width: size,
            height: size,
            ...solidPaint(spec),
            ...meta,
        });
    }

    if (type === 'triangle_outline') {
        return new Triangle({
            width: size,
            height: size,
            ...outlinePaint(spec),
            ...meta,
        });
    }

    if (type === 'polygon') {
        const sides = spec.sides || 6;
        const points = regularPolygonPoints(sides, size / 2);
        return polygonFromPoints(points, spec, id, baseName, opacity, solidPaint(spec));
    }

    if (type === 'polygon_outline') {
        const sides = spec.sides || 6;
        const points = regularPolygonPoints(sides, size / 2);
        return polygonFromPoints(points, spec, id, baseName, opacity, outlinePaint(spec));
    }

    if (type === 'star') {
        const points = starPolygonPoints(size / 2, size / 4, spec.spikes || 5);
        return polygonFromPoints(points, spec, id, baseName, opacity, solidPaint(spec));
    }

    if (type === 'star_outline') {
        const points = starPolygonPoints(size / 2, size / 4, spec.spikes || 5);
        return polygonFromPoints(points, spec, id, baseName, opacity, outlinePaint(spec, '#ffffff', 6));
    }

    if (type === 'parallelogram') {
        const w = size * 1.2;
        const h = size * 0.7;
        const skew = size * 0.22;
        const points = [
            { x: -w / 2 + skew, y: -h / 2 },
            { x: w / 2 + skew, y: -h / 2 },
            { x: w / 2 - skew, y: h / 2 },
            { x: -w / 2 - skew, y: h / 2 },
        ];
        return polygonFromPoints(points, spec, id, baseName, opacity, solidPaint(spec));
    }

    if (type === 'parallelogram_outline') {
        const w = size * 1.2;
        const h = size * 0.7;
        const skew = size * 0.22;
        const points = [
            { x: -w / 2 + skew, y: -h / 2 },
            { x: w / 2 + skew, y: -h / 2 },
            { x: w / 2 - skew, y: h / 2 },
            { x: -w / 2 - skew, y: h / 2 },
        ];
        return polygonFromPoints(points, spec, id, baseName, opacity, outlinePaint(spec));
    }

    if (type === 'trapezoid') {
        const w = size * 1.2;
        const h = size * 0.75;
        const inset = size * 0.22;
        const points = [
            { x: -w / 2 + inset, y: -h / 2 },
            { x: w / 2 - inset, y: -h / 2 },
            { x: w / 2, y: h / 2 },
            { x: -w / 2, y: h / 2 },
        ];
        return polygonFromPoints(points, spec, id, baseName, opacity, solidPaint(spec));
    }

    if (type === 'trapezoid_outline') {
        const w = size * 1.2;
        const h = size * 0.75;
        const inset = size * 0.22;
        const points = [
            { x: -w / 2 + inset, y: -h / 2 },
            { x: w / 2 - inset, y: -h / 2 },
            { x: w / 2, y: h / 2 },
            { x: -w / 2, y: h / 2 },
        ];
        return polygonFromPoints(points, spec, id, baseName, opacity, outlinePaint(spec));
    }

    if (type === 'diamond') {
        const points = [
            { x: 0, y: -size / 2 },
            { x: size / 2, y: 0 },
            { x: 0, y: size / 2 },
            { x: -size / 2, y: 0 },
        ];
        return polygonFromPoints(points, spec, id, baseName, opacity, solidPaint(spec));
    }

    if (type === 'diamond_outline') {
        const points = [
            { x: 0, y: -size / 2 },
            { x: size / 2, y: 0 },
            { x: 0, y: size / 2 },
            { x: -size / 2, y: 0 },
        ];
        return polygonFromPoints(points, spec, id, baseName, opacity, outlinePaint(spec));
    }

    if (type === 'line') {
        const y = designHeight / 2;
        return new Line(
            [designWidth * 0.12, y, designWidth * 0.88, y],
            {
                stroke: spec.stroke ?? spec.fill ?? '#ffffff',
                strokeWidth: spec.strokeWidth || 6,
                fill: '',
                ...meta,
            },
        );
    }

    if (type === 'line_vertical') {
        const x = designWidth / 2;
        return new Line(
            [x, designHeight * 0.15, x, designHeight * 0.85],
            {
                stroke: spec.stroke ?? spec.fill ?? '#ffffff',
                strokeWidth: spec.strokeWidth || 6,
                fill: '',
                ...meta,
            },
        );
    }

    if (type === 'line_dashed') {
        const y = designHeight / 2;
        return new Line(
            [designWidth * 0.12, y, designWidth * 0.88, y],
            {
                stroke: spec.stroke ?? spec.fill ?? '#ffffff',
                strokeWidth: spec.strokeWidth || 6,
                strokeDashArray: [size * 0.08, size * 0.06],
                fill: '',
                ...meta,
            },
        );
    }

    const pathTypes = {
        arrow: { key: 'arrow', paint: solidPaint(spec) },
        arrow_outline: { key: 'arrow_outline', paint: outlinePaint(spec, '#ffffff', 4) },
        heart: { key: 'heart', paint: solidPaint(spec) },
        burst: { key: 'burst', paint: solidPaint(spec) },
        cloud: { key: 'cloud', paint: solidPaint(spec) },
        speech: { key: 'speech', paint: solidPaint(spec) },
        badge: { key: 'badge', paint: solidPaint(spec) },
        chevron: { key: 'chevron', paint: solidPaint(spec) },
        semicircle: { key: 'semicircle', paint: solidPaint(spec) },
        arc: { key: 'arc', paint: { fill: '', stroke: spec.stroke ?? '#ffffff', strokeWidth: spec.strokeWidth || 10, strokeLineCap: 'round' } },
        cross: { key: 'cross', paint: solidPaint(spec) },
        cube: { key: 'cube', paint: solidPaint(spec) },
        cube_outline: { key: 'cube_outline', paint: outlinePaint(spec, '#ffffff', 4) },
        cylinder: { key: 'cylinder', paint: solidPaint(spec) },
        cone: { key: 'cone', paint: solidPaint(spec) },
        pyramid: { key: 'pyramid', paint: solidPaint(spec) },
        sphere: { key: 'sphere', paint: solidPaint(spec) },
        corner_bracket: { key: 'corner_bracket', paint: outlinePaint(spec, '#ffffff', 10) },
    };

    if (pathTypes[type]) {
        const cfg = pathTypes[type];
        return pathShape(spec, cfg.key, size, id, baseName, opacity, cfg.paint);
    }

    if (type === 'blob') {
        const variant = spec.variant || 1;
        const pathData = spec.path || BLOB_PATHS[variant] || BLOB_PATHS[1];
        const scale = size / 100;
        return new Path(pathData, {
            ...solidPaint(spec),
            ...meta,
            scaleX: scale,
            scaleY: scale,
        });
    }

    return null;
}
