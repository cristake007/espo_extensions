define(['views/fields/link'], Dep => {
    return class extends Dep {
        setup() {
            this.eligibleUserIds = this.options.eligibleUserIds || [];
            super.setup();
        }

        getSelectPrimaryFilterName() {
            return 'active';
        }

        getSelectFilters() {
            return {
                id: {
                    type: 'in',
                    attribute: 'id',
                    value: this.eligibleUserIds,
                },
            };
        }
    };
});
