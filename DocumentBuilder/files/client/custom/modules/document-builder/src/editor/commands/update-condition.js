define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/json',
    'document-builder:editor/state/node-tree',
    'document-builder:editor/conditions/condition-builder',
], (Command, Json, NodeTree, ConditionBuilder) => class extends Command {
    constructor(nodeId,condition){super();this.nodeId=nodeId;this.condition=condition===null?null:Json.clone(ConditionBuilder.create(condition));}
    apply(layout){const location=NodeTree.getLocation(layout,this.nodeId);if(!location)return false;if(this.condition===null)delete location.node.condition;else location.node.condition=Json.clone(this.condition);return true;}
});
