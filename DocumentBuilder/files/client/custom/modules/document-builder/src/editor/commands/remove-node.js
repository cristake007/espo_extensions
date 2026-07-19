define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/node-tree',
], (Command, NodeTree) => {
    return class RemoveNodeCommand extends Command {
        constructor(nodeId) {
            super();
            this.nodeId = nodeId;
        }

        apply(layout) {
            const location = NodeTree.getLocation(layout, this.nodeId);

            if (!location) {
                return false;
            }

            location.container.splice(location.index, 1);

            return true;
        }
    };
});
