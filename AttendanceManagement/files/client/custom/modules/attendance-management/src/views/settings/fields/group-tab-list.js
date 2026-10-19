define([
    'attendance-management:views/settings/fields/tab-list',
], (TabListFieldView) => {
    return class extends TabListFieldView {
        setup() {
            this.noGroups = true;
            this.noDelimiters = true;

            super.setup();
        }
    };
});
