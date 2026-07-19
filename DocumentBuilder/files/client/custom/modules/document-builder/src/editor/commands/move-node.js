define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/node-tree',
], (Command, NodeTree) => {
    return class MoveNodeCommand extends Command {
        constructor(nodeId, target) {
            super();
            this.nodeId = nodeId;
            this.target = target;
        }

        apply(layout) {
            const source = NodeTree.getLocation(layout, this.nodeId);

            if (!source) {
                return false;
            }

            if (this.target.parentId && NodeTree.contains(source.node, this.target.parentId)) {
                throw new TypeError('A node cannot be moved into its own subtree.');
            }

            source.container.splice(source.index, 1);

            const container = NodeTree.getContainer(layout, this.target);
            const index = NodeTree.normalizeIndex(container, this.target.index);

            container.splice(index, 0, source.node);

            return true;
        }
    };
});
