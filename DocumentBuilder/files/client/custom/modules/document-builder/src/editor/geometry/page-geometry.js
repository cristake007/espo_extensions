define([], () => {
    const MM_PER_INCH = 25.4;
    const CSS_PIXELS_PER_INCH = 96;
    const MIN_ZOOM = 25;
    const MAX_ZOOM = 200;
    const STANDARD_SIZES = Object.freeze({
        A4: Object.freeze({id: 'A4', label: 'A4', widthMm: 210, heightMm: 297}),
        A3: Object.freeze({id: 'A3', label: 'A3', widthMm: 297, heightMm: 420}),
    });

    const normalizeDefinition = definition => {
        if (!definition || typeof definition.id !== 'string' ||
            !/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(definition.id) ||
            typeof definition.label !== 'string' || !definition.label.trim() ||
            definition.label.length > 100 ||
            typeof definition.widthMm !== 'number' || definition.widthMm < 10 ||
            definition.widthMm > 2000 || typeof definition.heightMm !== 'number' ||
            definition.heightMm < 10 || definition.heightMm > 2000) {
            throw new TypeError('Page-size definitions require bounded millimetre dimensions.');
        }

        return Object.freeze({...definition});
    };

    return class PageGeometry {
        constructor(customSizes = []) {
            this.sizes = {...STANDARD_SIZES};

            customSizes.forEach(definition => {
                const normalized = normalizeDefinition(definition);

                if (normalized.id in this.sizes) {
                    throw new TypeError(`Duplicate page-size definition: ${normalized.id}.`);
                }

                this.sizes[normalized.id] = normalized;
            });
        }

        getSizeList() {
            return Object.values(this.sizes).map(definition => ({...definition}));
        }

        getPage(size, orientation = 'portrait') {
            const definition = this.sizes[size];

            if (!definition || !['portrait', 'landscape'].includes(orientation)) {
                throw new TypeError('The page size or orientation is unsupported.');
            }

            return orientation === 'portrait' ?
                {...definition} :
                {...definition, widthMm: definition.heightMm, heightMm: definition.widthMm};
        }

        clampZoom(zoom) {
            if (!Number.isFinite(zoom)) {
                throw new TypeError('Zoom must be a finite number.');
            }

            return Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, Math.round(zoom)));
        }

        millimetresToPixels(value, zoom = 100) {
            if (!Number.isFinite(value)) {
                throw new TypeError('Millimetres must be a finite number.');
            }

            return value * CSS_PIXELS_PER_INCH / MM_PER_INCH * this.clampZoom(zoom) / 100;
        }

        frame(size, orientation, zoom = 100) {
            const page = this.getPage(size, orientation);
            const normalizedZoom = this.clampZoom(zoom);

            return {
                ...page,
                zoom: normalizedZoom,
                widthPx: this.millimetresToPixels(page.widthMm, normalizedZoom),
                heightPx: this.millimetresToPixels(page.heightMm, normalizedZoom),
            };
        }

        fitWidth(size, orientation, availableWidthPx, gutterPx = 48) {
            const page = this.getPage(size, orientation);
            const usableWidth = Math.max(1, availableWidthPx - gutterPx);

            return this.clampZoom(usableWidth / this.millimetresToPixels(page.widthMm) * 100);
        }

        fitPage(size, orientation, availableWidthPx, availableHeightPx, gutterPx = 48) {
            const page = this.getPage(size, orientation);
            const widthZoom = (Math.max(1, availableWidthPx - gutterPx) /
                this.millimetresToPixels(page.widthMm)) * 100;
            const heightZoom = (Math.max(1, availableHeightPx - gutterPx) /
                this.millimetresToPixels(page.heightMm)) * 100;

            return this.clampZoom(Math.min(widthZoom, heightZoom));
        }
    };
});
