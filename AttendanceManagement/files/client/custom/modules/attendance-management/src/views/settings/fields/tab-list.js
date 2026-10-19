define(['views/settings/fields/tab-list'], (TabListFieldView) => {
    return class extends TabListFieldView {
        setupOptions() {
            super.setupOptions();

            const scope = 'Attendance';
            const scopeDefs = this.getMetadata().get(`scopes.${scope}`) || {};

            if (
                this.params.options.includes(scope) ||
                scopeDefs.disabled ||
                !scopeDefs.tab
            ) {
                return;
            }

            const delimiters = this.params.options.filter(item => item.startsWith('_delimiter'));
            const scopes = this.params.options.filter(item => !item.startsWith('_delimiter'));

            scopes.push(scope);
            scopes.sort((first, second) => {
                return this.translate(first, 'scopeNamesPlural')
                    .localeCompare(this.translate(second, 'scopeNamesPlural'));
            });

            this.params.options = [...scopes, ...delimiters];
            this.translatedOptions[scope] = this.translate(scope, 'scopeNamesPlural');
        }

        editGroup(id) {
            const item = Espo.Utils.cloneDeep(this.getGroupValueById(id) || {});
            const index = this.getGroupIndexById(id);
            const tabList = Espo.Utils.cloneDeep(this.selected);
            const view = {
                divider: 'views/settings/modals/edit-tab-divider',
                url: 'views/settings/modals/edit-tab-url',
            }[item.type] || 'views/settings/modals/edit-tab-group';

            this.createView('dialog', view, {
                itemData: item,
                parentType: this.model.entityType,
            }, dialog => {
                if (item.type === 'group') {
                    dialog.model.defs.fields.itemList.view =
                        'attendance-management:views/settings/fields/group-tab-list';
                }

                dialog.render();

                this.listenToOnce(dialog, 'apply', itemData => {
                    for (const attribute in itemData) {
                        tabList[index][attribute] = itemData[attribute];
                    }

                    this.model.set(this.name, tabList);
                    dialog.close();
                });
            });
        }
    };
});
