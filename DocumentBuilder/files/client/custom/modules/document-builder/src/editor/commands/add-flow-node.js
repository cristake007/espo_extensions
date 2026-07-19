define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/node-tree',
], (Command, NodeTree) => {
    return class AddFlowNodeCommand extends Command {
        constructor(flowStructure, type, target, options = {}) {
            super();
            this.flowStructure = flowStructure;
            this.type = type;
            this.target = target;
            this.options = options;
            this.addedId = null;
        }

        apply(layout, context) {
            const node = NodeTree.prepareNewSubtree(
                this.flowStructure.createNode(this.type, this.options),
                context.idFactory,
            );

            this.flowStructure.assertTarget(layout, node, this.target);
            const container = NodeTree.getContainer(layout, this.target);
            container.splice(NodeTree.normalizeIndex(container, this.target.index), 0, node);
            this.flowStructure.ensureCapability(layout);
            this.addedId = node.id;

            return true;
        }
    };
});
