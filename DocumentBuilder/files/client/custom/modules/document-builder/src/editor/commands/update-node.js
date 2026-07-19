define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/json',
    'document-builder:editor/state/node-tree',
], (Command, Json, NodeTree) => {
    return class UpdateNodeCommand extends Command {
        constructor(nodeId, patch) {
            super();

            if (!Json.isPlainObject(patch)) {
                throw new TypeError('A node update must be a property object.');
            }

            this.nodeId = nodeId;
            this.patch = Json.clone(patch);

            if ('id' in this.patch || 'children' in this.patch) {
                throw new TypeError('Node IDs and children must be changed through structural commands.');
            }
        }

        apply(layout) {
            const location = NodeTree.getLocation(layout, this.nodeId);

            if (!location) {
                return false;
            }

            Object.entries(this.patch).forEach(([key, value]) => {
                location.node[key] = Json.clone(value);
            });

            return true;
        }
    };
});
