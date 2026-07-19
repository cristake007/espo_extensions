define([
    'dompurify',
    'document-builder:editor/variables/variable-identity',
    'document-builder:editor/variables/variable-presentation',
], (DOMPurify, VariableIdentity, VariablePresentation) => {
    const MARKS = Object.freeze(['bold', 'italic', 'underline']);
    const normalize = value => DOMPurify.sanitize(
        String(value ?? '').replace(/\r\n?/g, '\n'),
        {ALLOWED_TAGS: [], ALLOWED_ATTR: []},
    );
    const canonicalMarks = marks => MARKS.filter(mark => (marks || []).includes(mark));
    const mapText = (content, callback) => (content || []).map(item => {
        if (item.type === 'list') return {
            ...item,
            items: (item.items || []).map(listItem => mapText(listItem, callback)),
        };

        return item.type === 'text' ? callback(item) : {...item};
    });
    const someText = (content, predicate) => (content || []).some(item =>
        item.type === 'text' ? predicate(item) :
            item.type === 'list' && (item.items || []).some(listItem => someText(listItem, predicate))
    );
    const plainText = content => (content || []).map(item => {
        if (item.type === 'break') return '\n';
        if (item.type === 'variable') return `{{${item.label}}}`;
        if (item.type === 'list') {
            return (item.items || []).map(listItem => plainText(listItem)).join('\n');
        }

        return item.type === 'text' ? item.text : '';
    }).join('');

    const api = {
        fromPlainText(value, marks = []) {
            const result = [];
            normalize(value).split('\n').forEach((text, index) => {
                if (index) result.push({type: 'break'});
                if (text || index === 0) result.push({type: 'text', text, marks: canonicalMarks(marks)});
            });

            return result;
        },

        toPlainText(content) {
            return plainText(content);
        },

        toggleMark(content, mark) {
            if (!MARKS.includes(mark)) throw new TypeError('Unsupported rich-text mark.');
            const enabled = !someText(content, item => item.marks.includes(mark));

            return mapText(content, item => ({
                ...item,
                marks: canonicalMarks(enabled ? [...item.marks, mark] : item.marks.filter(value => value !== mark)),
            }));
        },

        setColor(content, color) {
            if (color !== null && !/^#[0-9A-Fa-f]{6}$/.test(color)) throw new TypeError('Invalid color.');
            return mapText(content, item => color === null ?
                (({color: ignored, ...rest}) => rest)(item) : {...item, color});
        },

        createList(style, items) {
            if (!['bulleted', 'numbered'].includes(style) || !Array.isArray(items) ||
                items.length < 1 || items.length > 100 || items.some(item =>
                    !Array.isArray(item) || item.length > 1000 || item.some(value =>
                        !value || typeof value !== 'object' || Array.isArray(value) ||
                        !['text', 'break', 'variable'].includes(value.type)
                    )
                )) {
                throw new TypeError('Invalid rich-text list.');
            }

            return {
                type: 'list',
                style,
                items: items.map(item => item.map(value => ({...value}))),
            };
        },

        appendVariable(content, tokenId, label, identity, presentation) {
            const normalizedLabel = normalize(label);
            if (!/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(tokenId) ||
                !normalizedLabel || normalizedLabel.length > 100) {
                throw new TypeError('Invalid inline variable token.');
            }
            const normalizedIdentity = VariableIdentity.create(identity);
            const normalizedPresentation = VariablePresentation.create(presentation);

            if (VariableIdentity.usage(normalizedIdentity) !== 'scalar') {
                throw new TypeError('Inline content only accepts scalar variables.');
            }

            return [...(content || []).map(item => ({...item})), {
                type: 'variable', tokenId, label: normalizedLabel, identity: normalizedIdentity,
                presentation: normalizedPresentation,
            }];
        },

        render(host, content, documentRef = document, variableResolver = null) {
            host.replaceChildren();
            const renderSequence = (target, sequence) => (sequence || []).forEach(item => {
                if (item.type === 'break') { target.append(documentRef.createElement('br')); return; }
                if (item.type === 'list') {
                    const list = documentRef.createElement(item.style === 'numbered' ? 'ol' : 'ul');
                    (item.items || []).forEach(listItem => {
                        const entry = documentRef.createElement('li');
                        renderSequence(entry, listItem);
                        list.append(entry);
                    });
                    target.append(list);

                    return;
                }
                if (item.type === 'variable') {
                    const token = documentRef.createElement('span');
                    token.className = 'document-builder-editor__inline-variable';
                    if (token.dataset) {
                        token.dataset.richVariable = '';
                        token.dataset.tokenId = item.tokenId;
                    }
                    token.contentEditable = 'false';
                    const preview = typeof variableResolver === 'function' ? variableResolver(item.identity) : null;
                    if (preview && typeof preview.text === 'string') {
                        token.textContent = preview.text;
                        token.classList.add(`is-${preview.state}`);
                        token.dataset.previewOrigin = preview.origin;
                    } else {
                        token.textContent = `{{${item.label}}}`;
                    }
                    target.append(token); return;
                }
                if (item.type !== 'text') return;
                let node = documentRef.createTextNode(item.text);
                const tags = {bold: 'strong', italic: 'em', underline: 'u'};
                canonicalMarks(item.marks).forEach(mark => {
                    const wrapper = documentRef.createElement(tags[mark]); wrapper.append(node); node = wrapper;
                });
                if (item.color && /^#[0-9A-Fa-f]{6}$/.test(item.color)) {
                    const color = documentRef.createElement('span'); color.style.color = item.color;
                    color.append(node); node = color;
                }
                target.append(node);
            });
            renderSequence(host, content);
        },
    };

    return Object.freeze(api);
});
