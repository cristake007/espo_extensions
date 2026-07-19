define(['document-builder:editor/content/rich-text'], RichText => {
    const CONTAINER_TYPES = Object.freeze(['flow-section', 'flow-container']);

    return class DocumentCanvas {
        render(host, tree, {translate = value => value, variableResolver = null} = {}) {
            if (!host) return;
            const documentRef = host.ownerDocument || document;

            host.replaceChildren();
            this.renderChildren(host, tree, null, 'sections', documentRef, translate, variableResolver);

            if (tree.length === 0) {
                const empty = documentRef.createElement('div');
                empty.className = 'document-builder-editor__canvas-empty';
                empty.textContent = translate('editorFlowEmpty', 'messages');
                host.insertBefore(empty, host.firstChild);
            }
        }

        renderChildren(host, children, parentId, region, documentRef, translate, variableResolver) {
            children.forEach((child, index) => {
                host.append(this.dropTarget(documentRef, region, parentId, index, 'before'));
                host.append(this.node(documentRef, child, translate, variableResolver));
            });
            host.append(this.dropTarget(documentRef, region, parentId, children.length, 'after'));
        }

        node(documentRef, node, translate, variableResolver) {
            const element = documentRef.createElement(this.tag(node));
            element.className = [
                'document-builder-editor__flow-node',
                `is-${node.type}`,
                node.selected ? 'is-selected' : '',
                CONTAINER_TYPES.includes(node.type) ? 'is-container-node' : 'is-content-node',
            ].filter(Boolean).join(' ');
            element.style.cssText = node.flowStyle || '';
            element.dataset.nodeId = node.id;
            element.dataset.action = 'selectFlowNode';
            element.dataset.page = String(node.pageNumber || 1);
            element.draggable = true;
            element.tabIndex = 0;
            element.setAttribute('aria-pressed', node.selected ? 'true' : 'false');
            element.setAttribute('aria-label', translate(node.label, 'labels'));
            element.setAttribute('aria-keyshortcuts', 'ArrowUp ArrowDown Home End');

            if (CONTAINER_TYPES.includes(node.type)) {
                this.renderChildren(
                    element,
                    node.children || [],
                    node.id,
                    node.region,
                    documentRef,
                    translate,
                    variableResolver,
                );
                if ((node.children || []).length === 0) {
                    const placeholder = documentRef.createElement('span');
                    placeholder.className = 'document-builder-editor__container-placeholder';
                    placeholder.textContent = translate('Drop Inside', 'labels');
                    element.insertBefore(placeholder, element.lastChild);
                }

                return element;
            }

            if (node.isHeading || node.isParagraph) {
                if (node.isEmpty) {
                    element.classList.add('is-sample');
                    element.textContent = translate(node.sampleKey, 'messages');
                } else {
                    RichText.render(element, node.content, documentRef, variableResolver);
                }
            } else if (node.isStaticText) {
                element.textContent = node.text;
            } else if (node.isVariable) {
                element.textContent = node.variableText;
            } else if (node.isDivider) {
                element.style.cssText += `;${node.dividerStyle || ''}`;
            } else if (node.isSpacer) {
                element.setAttribute('aria-label', translate('Spacer', 'labels'));
            } else if (node.isPageBreak) {
                element.setAttribute('role', 'separator');
                element.textContent = translate('Manual Page Break', 'labels');
            }

            return element;
        }

        dropTarget(documentRef, region, parentId, index, position) {
            const target = documentRef.createElement('div');
            target.className = 'document-builder-editor__drop';
            target.dataset.flowDrop = position;
            target.dataset.dropRegion = region;
            target.dataset.dropParent = parentId || '';
            target.dataset.dropIndex = String(index);
            target.setAttribute('aria-hidden', 'true');

            return target;
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
