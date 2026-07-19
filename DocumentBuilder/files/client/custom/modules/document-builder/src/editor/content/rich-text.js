define(['dompurify'], DOMPurify => {
    const MARKS = Object.freeze(['bold', 'italic', 'underline']);
    const normalize = value => DOMPurify.sanitize(
        String(value ?? '').replace(/\r\n?/g, '\n'),
        {ALLOWED_TAGS: [], ALLOWED_ATTR: []},
    );
    const canonicalMarks = marks => MARKS.filter(mark => (marks || []).includes(mark));

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
            return (content || []).map(item => {
                if (item.type === 'break') return '\n';
                if (item.type === 'variable') return `{{${item.label}}}`;
                return item.type === 'text' ? item.text : '';
            }).join('');
        },

        toggleMark(content, mark) {
            if (!MARKS.includes(mark)) throw new TypeError('Unsupported rich-text mark.');
            const enabled = !(content || []).some(item => item.type === 'text' && item.marks.includes(mark));

            return (content || []).map(item => item.type !== 'text' ? {...item} : {
                ...item,
                marks: canonicalMarks(enabled ? [...item.marks, mark] : item.marks.filter(value => value !== mark)),
            });
        },

        setColor(content, color) {
            if (color !== null && !/^#[0-9A-Fa-f]{6}$/.test(color)) throw new TypeError('Invalid color.');
            return (content || []).map(item => item.type !== 'text' ? {...item} :
                color === null ? (({color: ignored, ...rest}) => rest)(item) : {...item, color});
        },

        appendVariable(content, tokenId, label) {
            const normalizedLabel = normalize(label);
            if (!/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(tokenId) ||
                !normalizedLabel || normalizedLabel.length > 100) {
                throw new TypeError('Invalid inline variable token.');
            }
            return [...(content || []).map(item => ({...item})), {
                type: 'variable', tokenId, label: normalizedLabel,
            }];
        },

        render(host, content, documentRef = document) {
            host.replaceChildren();
            (content || []).forEach(item => {
                if (item.type === 'break') { host.append(documentRef.createElement('br')); return; }
                if (item.type === 'variable') {
                    const token = documentRef.createElement('span');
                    token.className = 'document-builder-editor__inline-variable';
                    token.textContent = `{{${item.label}}}`;
                    host.append(token); return;
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
                host.append(node);
            });
        },
    };

    return Object.freeze(api);
});
