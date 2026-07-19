define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/json',
], (Command, Json) => {
    return class UpdateDocumentCommand extends Command {
        constructor(document) {
            super();

            if (!Json.isPlainObject(document)) {
                throw new TypeError('Document settings must be a plain object.');
            }

            this.document = Json.clone(document);
        }

        apply(layout) {
            layout.document = Json.clone(this.document);

            return true;
        }
    };
});
