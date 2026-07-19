define(['document-builder:editor/state/json'], (Json) => {
    const normalize = layout => {
        const result = Json.clone(layout);

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

        return Json.canonicalize(result);
    };

    return Object.freeze({normalize});
});
