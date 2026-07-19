define([
    'document-builder:editor/validation/layout-precheck',
    'document-builder:editor/state/node-tree',
], (LayoutPrecheck, NodeTree) => {
    const textFromContent = content => (content || []).map(item => {
        if (item.type === 'break') return '\n';
        if (item.type === 'variable') return item.label || '';
        return item.type === 'text' ? item.text : '';
    }).join('');

    return class EditorValidator {
        constructor(customPageSizes = [], flowLimits = {}) {
            this.precheck = new LayoutPrecheck(customPageSizes, flowLimits);
        }

        validate(layout) {
            const issues = this.precheck.check(layout).errors.map((code, index) => ({
                id: `error-${index}-${code}`,
                code,
                messageKey: 'editorValidationSchemaError',
                severity: 'error',
                severityLabel: 'Error',
                blocking: true,
                nodeId: this.nodeIdForPath(layout, code),
            }));

            try {
                const locations = NodeTree.index(layout);

                if (locations.size === 0) {
                    issues.push(this.warning('layout-empty', 'editorValidationEmptyLayout'));
                }

                locations.forEach(({node}) => {
                    if (['flow-section', 'flow-container'].includes(node.type) &&
                        node.children.length === 0) {
                        issues.push(this.warning(
                            `${node.id}-empty-container`,
                            'editorValidationEmptyContainer',
                            node.id,
                        ));
                    }
                    const text = node.type === 'static-text' ? node.text :
                        ['heading', 'paragraph'].includes(node.type) ? textFromContent(node.content) : null;

                    if (text !== null && text.trim() === '') {
                        issues.push(this.warning(
                            `${node.id}-empty-content`,
                            'editorValidationEmptyContent',
                            node.id,
                        ));
                    }
                });
            } catch (error) {
                // The structural precheck already exposes this as a blocking issue.
            }

            return Object.freeze({
                issues,
                errorCount: issues.filter(issue => issue.severity === 'error').length,
                warningCount: issues.filter(issue => issue.severity === 'warning').length,
                blocking: issues.some(issue => issue.blocking),
            });
        }

        warning(id, messageKey, nodeId = null) {
            return {
                id,
                code: id,
                messageKey,
                severity: 'warning',
                severityLabel: 'Warning',
                blocking: false,
                nodeId,
            };
        }

        nodeIdForPath(layout, code) {
            const match = /^flow\.sections\.(\d+)((?:\.children\.\d+)*)/.exec(code);

            if (!match || !Array.isArray(layout.sections)) return null;
            let node = layout.sections[Number(match[1])];
            const children = [...match[2].matchAll(/\.children\.(\d+)/g)];

            children.forEach(item => {
                node = node && Array.isArray(node.children) ? node.children[Number(item[1])] : null;
            });

            return node && typeof node.id === 'string' ? node.id : null;
        }
    };
});
