define([
    'document-builder:editor/state/json',
    'document-builder:editor/content/rich-text',
], (Json, RichText) => {
    const normalizeStaticText = node => {
        if (!Json.isPlainObject(node)) return;
        if (node.type === 'static-text' && typeof node.text === 'string' && !Array.isArray(node.content)) {
            node.content = RichText.fromPlainText(node.text);
            delete node.text;
        }
        (node.children || []).forEach(normalizeStaticText);
    };

    const normalize = layout => {
        const result = Json.clone(layout);

        ['header', 'sections', 'footer'].forEach(region => {
            (result[region] || []).forEach(normalizeStaticText);
        });

        if (!Json.isPlainObject(result.document) ||
            !Json.isPlainObject(result.document.defaults)) {
            return result;
        }

        if (!('timezone' in result.document.defaults)) {
            result.document.defaults.timezone = 'UTC';
        }

        if (!('titlePattern' in result.document)) {
            result.document.titlePattern = 'Document';
        }

        if (!('filenamePattern' in result.document)) {
            result.document.filenamePattern = 'document.pdf';
        }

        if (!Json.isPlainObject(result.document.chrome)) {
            result.document.chrome = {};
        }

        ['header', 'footer'].forEach(region => {
            if (!Json.isPlainObject(result.document.chrome[region])) {
                result.document.chrome[region] = {};
            }

            result.document.chrome[region] = {
                height: result.document.chrome[region].height || {value: 0, unit: 'mm'},
                showOnFirstPage: result.document.chrome[region].showOnFirstPage ?? true,
                disableOnFullPage: result.document.chrome[region].disableOnFullPage ?? true,
            };
        });

        return Json.canonicalize(result);
    };

    return Object.freeze({normalize});
});
