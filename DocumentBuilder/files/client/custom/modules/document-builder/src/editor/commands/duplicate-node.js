define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/node-tree',
], (Command, NodeTree) => {
    return class DuplicateNodeCommand extends Command {
        constructor(nodeId, target = null) {
            super();
            this.nodeId = nodeId;
            this.target = target;
            this.duplicateId = null;
        }

        apply(layout, context) {
            const source = NodeTree.getLocation(layout, this.nodeId);

            if (!source) {
                return false;
            }

            const target = this.target || {
                region: source.region,
                parentId: source.parentId,
                index: source.index + 1,
            };
            const duplicate = NodeTree.prepareNewSubtree(source.node, context.idFactory, true);
            const container = NodeTree.getContainer(layout, target);
            const index = NodeTree.normalizeIndex(container, target.index);

            container.splice(index, 0, duplicate);
            this.duplicateId = duplicate.id;

            return true;
        }
    };
});
