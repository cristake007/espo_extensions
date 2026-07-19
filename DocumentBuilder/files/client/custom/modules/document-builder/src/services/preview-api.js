define([], () => {
    return class PreviewApi {
        constructor(ajax = Espo.Ajax) {
            this.ajax = ajax;
        }

        load(templateId, expectedRevision, mode, recordId = null) {
            if (!templateId || !Number.isInteger(expectedRevision) || expectedRevision < 0 ||
                !['sample', 'record'].includes(mode) ||
                (mode === 'sample' && recordId !== null) ||
                (mode === 'record' && !/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(recordId || ''))) {
                throw new TypeError('Preview request identifiers are invalid.');
            }

            const payload = {expectedRevision, mode};
            if (recordId !== null) payload.recordId = recordId;

            return this.ajax.postRequest(`DocumentBuilder/template/${templateId}/preview`, payload);
        }
    };
});
