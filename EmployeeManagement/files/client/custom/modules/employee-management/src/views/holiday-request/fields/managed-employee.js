define(['views/fields/link'], Dep => {
    return class extends Dep {
        setup() {
            this.eligibleUsers = this.options.eligibleUsers || [];
            this.foreignScope = 'User';
            this.autocompleteDisabled = true;
            super.setup();
        }

        async actionSelect() {
            const view = await this.createView(
                'modal',
                'employee-management:views/modals/managed-employee-select',
                {users: this.eligibleUsers},
            );

            this.listenToOnce(view, 'select', async user => {
                const model = await this.getModelFactory().create('User');

                model.set({id: user.id, name: user.name});
                await this.select(model);
                view.close();
            });

            await view.render();
        }
    };
});
