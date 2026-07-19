define([], () => {
    const parseBody = xhr => {
        if (xhr && xhr.responseJSON && typeof xhr.responseJSON === 'object') {
            return xhr.responseJSON;
        }

        if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim()) {
            try {
                return JSON.parse(xhr.responseText);
            } catch (error) {
                return {};
            }
        }

        return {};
    };

    return class DraftApi {
        constructor(ajax = Espo.Ajax) {
            this.ajax = ajax;
        }

        save(templateId, layout, expectedRevision, confirmSourceChange = false) {
            if (!templateId || !Number.isInteger(expectedRevision) || expectedRevision < 0) {
                throw new TypeError('Draft save identifiers are invalid.');
            }

            if (typeof confirmSourceChange !== 'boolean') {
                throw new TypeError('Source-change confirmation must be boolean.');
            }

            return this.ajax.putRequest(
                `DocumentBuilder/template/${templateId}/draft`,
                {
                    layout: JSON.stringify(layout),
                    expectedRevision,
                    confirmSourceChange,
                    changeNote: null,
                },
            );
        }

        getRevisionConflict(xhr) {
            if (!xhr || xhr.status !== 409) {
                return null;
            }

            const body = parseBody(xhr);

            if (!Number.isInteger(body.actualRevision) || body.actualRevision < 0) {
                return null;
            }

            return {
                expectedRevision: Number.isInteger(body.expectedRevision) ? body.expectedRevision : null,
                actualRevision: body.actualRevision,
            };
        }

        getErrorMessage(xhr) {
            if (xhr && xhr.status === 403) {
                return 'editorSaveAccessDenied';
            }

            if (xhr && xhr.status === 400) {
                return 'editorServerValidationFailed';
            }

            if (xhr && xhr.status === 409) {
                return 'editorSaveConflictUnsupported';
            }

            return 'editorSaveFailed';
        }
    };
});
