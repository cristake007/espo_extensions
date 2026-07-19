define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/node-tree',
], (Command, NodeTree) => {
    return class RemoveFlowNodeCommand extends Command {
        constructor(flowStructure, nodeId) {
            super();
            this.flowStructure = flowStructure;
            this.nodeId = nodeId;
        }

        apply(layout) {
            const source = NodeTree.getLocation(layout, this.nodeId);

            if (!source) return false;

            source.container.splice(source.index, 1);
            this.flowStructure.removeUnusedCapability(layout);

            return true;
        }
    };
});
