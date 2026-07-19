define(['document-builder:editor/content/rich-text'], RichText => {
    const CONTAINER_TYPES = Object.freeze(['flow-section', 'flow-container']);

    return class DocumentCanvas {
        render(host, tree, {translate = value => value, variableResolver = null, preview = false} = {}) {
            if (!host) return;
            const documentRef = host.ownerDocument || document;

            host.replaceChildren();
            this.renderChildren(host, tree, null, 'sections', documentRef, translate, variableResolver, preview);

            if (tree.length === 0 && !preview) {
                const empty = documentRef.createElement('div');
                empty.className = 'document-builder-editor__canvas-empty';
                empty.textContent = translate('editorFlowEmpty', 'messages');
                host.insertBefore(empty, host.firstChild);
            }

        }

        renderChildren(host, children, parentId, region, documentRef, translate, variableResolver, preview) {
            children.forEach((child, index) => {
                if (!preview) host.append(this.dropTarget(documentRef, region, parentId, index, 'before'));
                host.append(this.node(documentRef, child, translate, variableResolver, preview));
            });
            if (!preview) host.append(this.dropTarget(
                documentRef,
                region,
                parentId,
                children.length,
                parentId && children.length === 0 ? 'inside' : 'after',
            ));
        }

        node(documentRef, node, translate, variableResolver, preview) {
            const element = documentRef.createElement(this.tag(node));
            element.className = [
                'document-builder-editor__flow-node',
                `is-${node.type}`,
                node.selected ? 'is-selected' : '',
                CONTAINER_TYPES.includes(node.type) ? 'is-container-node' : 'is-content-node',
            ].filter(Boolean).join(' ');
            element.style.cssText = node.flowStyle || '';
            element.dataset.nodeId = node.id;
            element.dataset.page = String(node.pageNumber || 1);
            element.dataset.flowDepth = String(node.depth || 0);
            element.draggable = !preview && !node.isHeading && !node.isStaticText && !node.isParagraph;
            element.tabIndex = preview ? -1 : 0;
            if (!preview) {
                element.dataset.action = 'selectFlowNode';
                element.setAttribute('aria-pressed', node.selected ? 'true' : 'false');
                element.setAttribute('aria-keyshortcuts', 'ArrowUp ArrowDown Home End');
            }
            element.setAttribute('aria-label', translate(node.label, 'labels'));
            if (!preview && node.selected) {
                element.append(this.selectionToolbar(documentRef, node.id, translate));
            }

            if (CONTAINER_TYPES.includes(node.type)) {
                if (!preview) element.dataset.flowContainerDrop = '';
                this.renderChildren(
                    element,
                    node.children || [],
                    node.id,
                    node.region,
                    documentRef,
                    translate,
                    variableResolver,
                    preview,
                );
                if ((node.children || []).length === 0 && !preview) {
                    const placeholder = documentRef.createElement('span');
                    placeholder.className = 'document-builder-editor__container-placeholder';
                    placeholder.textContent = translate('Drop Inside', 'labels');
                    element.insertBefore(placeholder, element.lastChild);
                }

                return element;
            }

            if (node.isContent || node.isHeading || node.isStaticText || node.isParagraph) {
                if (!preview && node.selected) {
                    const mount = documentRef.createElement('div');
                    mount.className = 'document-builder-editor__native-wysiwyg';
                    mount.dataset.wysiwygMount = '';
                    mount.dataset.nodeId = node.id;
                    element.append(mount);

                    return element;
                }
                const editor = documentRef.createElement(node.isHeading ? 'span' : 'div');
                editor.className = 'document-builder-editor__rich-editor';
                if (node.isEmpty && !preview) {
                    element.classList.add('is-sample');
                    editor.dataset.placeholder = translate(node.sampleKey, 'messages');
                } else {
                    RichText.render(editor, node.content, documentRef, variableResolver);
                }
                element.append(editor);
            } else if (node.isVariable) {
                element.textContent = node.variableText;
            } else if (node.isDivider) {
                element.style.cssText += `;${node.dividerStyle || ''}`;
            } else if (node.isSpacer) {
                element.setAttribute('aria-label', translate('Spacer', 'labels'));
            } else if (node.isPageBreak) {
                if (preview) element.hidden = true;
                else {
                    element.setAttribute('role', 'separator');
                    element.textContent = translate('Manual Page Break', 'labels');
                }
            }

            return element;
        }

        dropTarget(documentRef, region, parentId, index, position) {
            const target = documentRef.createElement('div');
            target.className = `document-builder-editor__drop is-${position}`;
            target.dataset.flowDrop = position;
            target.dataset.dropRegion = region;
            target.dataset.dropParent = parentId || '';
            target.dataset.dropIndex = String(index);
            target.setAttribute('aria-hidden', 'true');

            return target;
        }

        selectionToolbar(documentRef, nodeId, translate) {
            const toolbar = documentRef.createElement('div');
            toolbar.className = 'document-builder-editor__selection-toolbar';
            toolbar.dataset.selectionToolbar = '';
            toolbar.setAttribute('role', 'toolbar');
            toolbar.setAttribute('aria-label', translate('Element Actions', 'labels'));
            [
                [null, 'Move', 'fa-grip-vertical', true],
                ['editFlowNode', 'Edit', 'fa-pen', false],
                ['duplicateFlowNode', 'Duplicate', 'fa-copy', false],
                ['removeFlowNode', 'Remove', 'fa-trash-alt', false],
            ].forEach(([action, label, iconName, draggable]) => {
                const button = documentRef.createElement('button');
                const icon = documentRef.createElement('span');
                button.type = 'button';
                button.className = 'btn btn-default btn-xs document-builder-editor__selection-action';
                if (action) button.dataset.action = action;
                button.dataset.nodeId = nodeId;
                button.draggable = draggable;
                button.title = translate(label, 'actions');
                button.setAttribute('aria-label', translate(label, 'actions'));
                icon.className = `fas ${iconName}`;
                icon.setAttribute('aria-hidden', 'true');
                button.append(icon);
                toolbar.append(button);
            });

            return toolbar;
        }

        tag(node) {
            if (node.isSection) return 'section';
            if (node.isContainer) return 'div';
            if (node.isHeading) {
                return `h${Number.isInteger(node.level) && node.level >= 1 && node.level <= 6 ? node.level : 2}`;
            }
            if (node.isParagraph) {
                return (node.content || []).some(item => item.type === 'list') ? 'div' : 'p';
            }
            if (node.isStaticText) return 'p';
            if (node.isDivider) return 'hr';

            return 'div';
        }
    };
});
