define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/node-tree',
], (Command, NodeTree) => {
    return class MoveFlowNodeCommand extends Command {
        constructor(flowStructure, nodeId, target) {
            super();
            this.flowStructure = flowStructure;
            this.nodeId = nodeId;
            this.target = target;
        }

        apply(layout) {
            const source = NodeTree.getLocation(layout, this.nodeId);

            if (!source) return false;

            this.flowStructure.assertTarget(layout, source.node, this.target, this.nodeId);
            source.container.splice(source.index, 1);
            const container = NodeTree.getContainer(layout, this.target);
            let index = NodeTree.normalizeIndex(container, this.target.index);

            if (container === source.container && source.index < index) index--;
            container.splice(index, 0, source.node);

            return true;
        }
    };
});
