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

            if (!preview) host.append(this.hoverToolbar(documentRef, translate));
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
            element.draggable = !preview && !node.isHeading && !node.isParagraph;
            element.tabIndex = preview ? -1 : 0;
            if (!preview) {
                element.dataset.action = 'selectFlowNode';
                element.setAttribute('aria-pressed', node.selected ? 'true' : 'false');
                element.setAttribute('aria-keyshortcuts', 'ArrowUp ArrowDown Home End');
            }
            element.setAttribute('aria-label', translate(node.label, 'labels'));

            if (CONTAINER_TYPES.includes(node.type)) {
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

            if (node.isHeading || node.isParagraph) {
                const editor = documentRef.createElement(node.isHeading ? 'span' : 'div');
                editor.className = 'document-builder-editor__rich-editor';
                if (!preview) {
                    editor.dataset.richEditor = '';
                    editor.dataset.nodeId = node.id;
                }
                editor.contentEditable = preview ? 'false' : 'true';
                editor.draggable = false;
                editor.spellcheck = true;
                if (!preview) editor.setAttribute('aria-label', translate('Edit Content', 'labels'));
                if (node.isEmpty && !preview) {
                    element.classList.add('is-sample');
                    editor.dataset.placeholder = translate(node.sampleKey, 'messages');
                } else {
                    RichText.render(editor, node.content, documentRef, variableResolver);
                }
                element.append(editor);
            } else if (node.isStaticText) {
                element.textContent = node.text;
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

        hoverToolbar(documentRef, translate) {
            const toolbar = documentRef.createElement('div');
            toolbar.className = 'document-builder-editor__hover-toolbar';
            toolbar.dataset.hoverToolbar = '';
            toolbar.hidden = true;
            toolbar.setAttribute('role', 'toolbar');
            toolbar.setAttribute('aria-label', translate('Element Actions', 'labels'));
            [
                ['editFlowNode', 'Edit'],
                ['duplicateFlowNode', 'Duplicate'],
                ['removeFlowNode', 'Remove'],
            ].forEach(([action, label]) => {
                const button = documentRef.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-default btn-xs';
                button.dataset.action = action;
                button.dataset.hoverAction = '';
                button.textContent = translate(label, 'actions');
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
