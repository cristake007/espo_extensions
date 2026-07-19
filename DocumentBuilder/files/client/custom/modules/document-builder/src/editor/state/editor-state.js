define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/json',
    'document-builder:editor/state/node-tree',
    'document-builder:editor/state/stable-id-factory',
], (Command, Json, NodeTree, StableIdFactory) => {
    const HISTORY_LIMIT = 100;

    return class EditorState {
        constructor(layout, idFactory = new StableIdFactory()) {
            this.layout = Json.canonicalize(layout);
            this.idFactory = idFactory;
            this.past = [];
            this.future = [];
            this.selectedId = null;

            NodeTree.index(this.layout);
            this.idFactory.synchronize(this.layout);
            this.savedBaseline = Json.stringify(this.layout);
        }

        getLayout() {
            return Json.clone(this.layout);
        }

        getSelectedId() {
            return this.selectedId;
        }

        select(nodeId) {
            if (nodeId === null) {
                const changed = this.selectedId !== null;

                this.selectedId = null;

                return changed;
            }

            if (!NodeTree.getLocation(this.layout, nodeId)) {
                return false;
            }

            const changed = this.selectedId !== nodeId;

            this.selectedId = nodeId;

            return changed;
        }

        execute(command) {
            if (!(command instanceof Command)) {
                throw new TypeError('Editor state only executes command objects.');
            }

            const before = Json.clone(this.layout);
            const beforeHash = Json.stringify(before);
            const candidate = Json.clone(this.layout);
            const reportedChange = command.execute(candidate, {idFactory: this.idFactory});

            NodeTree.index(candidate);

            const afterHash = Json.stringify(candidate);

            if (!reportedChange || afterHash === beforeHash) {
                return false;
            }

            this.layout = candidate;
            this.past.push(before);

            if (this.past.length > HISTORY_LIMIT) {
                this.past.shift();
            }

            this.future = [];
            this.cleanupSelection();

            return true;
        }

        canUndo() {
            return this.past.length > 0;
        }

        canRedo() {
            return this.future.length > 0;
        }

        undo() {
            const previous = this.past.pop();

            if (!previous) {
                return false;
            }

            this.future.push(Json.clone(this.layout));
            this.layout = previous;
            this.cleanupSelection();

            return true;
        }

        redo() {
            const next = this.future.pop();

            if (!next) {
                return false;
            }

            this.past.push(Json.clone(this.layout));
            this.layout = next;
            this.cleanupSelection();

            return true;
        }

        markSaved() {
            this.savedBaseline = Json.stringify(this.layout);
        }

        acceptSavedLayout(layout) {
            const savedLayout = Json.canonicalize(layout);

            NodeTree.index(savedLayout);
            this.idFactory.synchronize(savedLayout);
            this.layout = savedLayout;
            this.savedBaseline = Json.stringify(savedLayout);
            this.cleanupSelection();
        }

        reloadSavedLayout(layout) {
            this.acceptSavedLayout(layout);
            this.past = [];
            this.future = [];
            this.selectedId = null;
        }

        isDirty() {
            return Json.stringify(this.layout) !== this.savedBaseline;
        }

        cleanupSelection() {
            if (this.selectedId && !NodeTree.getLocation(this.layout, this.selectedId)) {
                this.selectedId = null;
            }
        }
    };
});
