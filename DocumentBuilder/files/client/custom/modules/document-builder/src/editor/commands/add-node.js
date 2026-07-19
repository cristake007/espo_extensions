define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/node-tree',
], (Command, NodeTree) => {
    return class AddNodeCommand extends Command {
        constructor(node, target) {
            super();
            this.node = node;
            this.target = target;
            this.addedId = null;
        }

        apply(layout, context) {
            const node = NodeTree.prepareNewSubtree(this.node, context.idFactory);
            const container = NodeTree.getContainer(layout, this.target);
            const index = NodeTree.normalizeIndex(container, this.target.index);

            container.splice(index, 0, node);
            this.addedId = node.id;

            return true;
        }
    };
});
