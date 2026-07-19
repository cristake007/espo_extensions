define(['document-builder:editor/content/rich-text'], RichText => {
    const MARK_TAGS = Object.freeze({STRONG: 'bold', B: 'bold', EM: 'italic', I: 'italic', U: 'underline'});
    const clone = value => JSON.parse(JSON.stringify(value));
    const tokenMap = content => {
        const result = new Map();
        const visit = sequence => (sequence || []).forEach(item => {
            if (item.type === 'variable') result.set(item.tokenId, item);
            if (item.type === 'list') (item.items || []).forEach(visit);
        });
        visit(content);

        return result;
    };
    const colorValue = value => {
        const hex = String(value || '').match(/^#([0-9a-f]{6})$/i);
        if (hex) return `#${hex[1].toUpperCase()}`;
        const rgb = String(value || '').match(/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i);
        if (!rgb || rgb.slice(1).some(part => Number(part) > 255)) return null;

        return `#${rgb.slice(1).map(part => Number(part).toString(16).padStart(2, '0')).join('').toUpperCase()}`;
    };
    const sameTextStyle = (left, right) => left.type === 'text' && right.type === 'text' &&
        JSON.stringify(left.marks) === JSON.stringify(right.marks) && left.color === right.color;
    const compact = sequence => sequence.reduce((result, item) => {
        const previous = result[result.length - 1];
        if (previous && sameTextStyle(previous, item)) previous.text += item.text;
        else result.push(item);

        return result;
    }, []);

    const read = (host, originalContent = []) => {
        const variables = tokenMap(originalContent);
        const inline = (parent, inheritedMarks = [], inheritedColor = null) => {
            const result = [];
            [...(parent.childNodes || [])].forEach(node => {
                if (node.nodeType === 3) {
                    const items = RichText.fromPlainText(node.nodeValue || '', inheritedMarks);
                    items.forEach(item => result.push(
                        item.type === 'text' && inheritedColor ? {...item, color: inheritedColor} : item,
                    ));

                    return;
                }
                if (node.nodeType !== 1) return;
                if (node.matches?.('[data-rich-variable]')) {
                    const variable = variables.get(node.dataset.tokenId);
                    if (variable) result.push(clone(variable));

                    return;
                }
                if (node.tagName === 'BR') {
                    result.push({type: 'break'});

                    return;
                }
                if (node.tagName === 'UL' || node.tagName === 'OL') {
                    const items = [...node.children]
                        .filter(child => child.tagName === 'LI')
                        .map(child => compact(inline(child, inheritedMarks, inheritedColor)));
                    if (items.length) result.push(RichText.createList(
                        node.tagName === 'OL' ? 'numbered' : 'bulleted',
                        items,
                    ));

                    return;
                }
                const styledMarks = [
                    MARK_TAGS[node.tagName],
                    /^(bold|[6-9]00)$/.test(node.style?.fontWeight || '') ? 'bold' : null,
                    node.style?.fontStyle === 'italic' ? 'italic' : null,
                    String(node.style?.textDecoration || '').includes('underline') ? 'underline' : null,
                ].filter(Boolean);
                const marks = [...new Set([...inheritedMarks, ...styledMarks])];
                const color = colorValue(node.style?.color || node.getAttribute?.('color')) || inheritedColor;
                const children = inline(node, marks, color);
                if (['DIV', 'P'].includes(node.tagName) && result.length &&
                    result[result.length - 1].type !== 'break') result.push({type: 'break'});
                result.push(...children);
            });

            return result;
        };
        const result = compact(inline(host));

        return result.length ? result : RichText.fromPlainText('');
    };

    const createVariableElement = (documentRef, variable) => {
        const token = documentRef.createElement('span');
        token.className = 'document-builder-editor__inline-variable';
        token.dataset.richVariable = '';
        token.dataset.tokenId = variable.tokenId;
        token.contentEditable = 'false';
        token.textContent = `{{${variable.label}}}`;

        return token;
    };

    return Object.freeze({
        read,

        toHtml(content, documentRef = document) {
            const host = documentRef.createElement('div');
            RichText.render(host, content, documentRef);

            return host.innerHTML;
        },

        captureRange(host, selection) {
            if (!host || !selection || selection.rangeCount < 1) return null;
            const range = selection.getRangeAt(0);
            const common = range.commonAncestorContainer;
            const owner = common.nodeType === 1 ? common : common.parentNode;

            return owner && host.contains(owner) ? range.cloneRange() : null;
        },

        restoreRange(selection, range) {
            if (!selection || !range) return false;
            selection.removeAllRanges();
            selection.addRange(range);

            return true;
        },

        insertVariable(host, range, variable, originalContent = [], documentRef = document) {
            const token = createVariableElement(documentRef, variable);
            const insertion = range || documentRef.createRange();
            if (!range) {
                insertion.selectNodeContents(host);
                insertion.collapse(false);
            }
            insertion.deleteContents();
            insertion.insertNode(token);
            insertion.setStartAfter(token);
            insertion.collapse(true);

            return {content: read(host, [...originalContent, variable]), range: insertion};
        },

        applyCommand(documentRef, command, value = null) {
            const allowed = ['bold', 'italic', 'underline', 'foreColor',
                'justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull',
                'insertUnorderedList', 'insertOrderedList'];
            if (!allowed.includes(command) || typeof documentRef.execCommand !== 'function') return false;

            return documentRef.execCommand(command, false, value);
        },
    });
});
