define([
    'document-builder:editor/commands/command',
    'document-builder:editor/state/json',
], (Command, Json) => {
    return class UpdatePageChromeCommand extends Command {
        constructor(region, values, presentation) {
            super();
            if (!['header', 'footer'].includes(region) || !Json.isPlainObject(values)) {
                throw new TypeError('Page chrome update is invalid.');
            }
            this.region = region;
            this.values = Json.clone(values);
            this.presentation = Json.clone(presentation);
        }

        apply(layout, context) {
            const current = layout[this.region].find(node => node.type === 'paragraph');
            const currentIndex = current ? layout[this.region].indexOf(current) : -1;
            const currentPageNumber = current?.content?.find(item =>
                item.type === 'variable' && item.identity?.source === 'system' &&
                item.identity?.path?.[0] === 'pageNumber');
            layout.document.chrome[this.region] = {
                height: {value: this.values.enabled ? this.values.height : 0, unit: 'mm'},
                showOnFirstPage: this.values.showOnFirstPage,
                disableOnFullPage: this.values.disableOnFullPage,
            };

            if (!this.values.enabled) {
                layout[this.region] = [];
            } else {
                let content = Json.clone(current?.content || []);
                if (this.values.updateContent !== false) {
                    content = [];
                    if (this.values.text !== '') content.push({type: 'text', text: this.values.text, marks: []});
                    if (this.values.includePageNumber) {
                        if (content.length) content.push({type: 'text', text: ' · ', marks: []});
                        content.push({
                            type: 'variable',
                            tokenId: currentPageNumber?.tokenId || context.idFactory.create('page-number'),
                            label: 'Page Number',
                            identity: {source: 'system', type: 'system', path: ['pageNumber']},
                            presentation: Json.clone(this.presentation),
                        });
                    }
                }
                const paragraph = {
                    id: current?.id || context.idFactory.create(`${this.region}-paragraph`),
                    type: 'paragraph',
                    content,
                    alignment: this.values.alignment,
                };
                if (currentIndex === -1) layout[this.region].unshift(paragraph);
                else layout[this.region][currentIndex] = paragraph;
            }

            const hasFlow = layout.sections.length || layout.header.length || layout.footer.length;
            layout.capabilities = layout.capabilities.filter(item => item !== 'layout.flow');
            if (hasFlow) layout.capabilities.push('layout.flow');
            layout.capabilities.sort();

            return true;
        }
    };
});
