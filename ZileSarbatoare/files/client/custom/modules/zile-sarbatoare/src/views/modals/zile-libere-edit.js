define(['views/modals/edit'], (EditModalView) => {
    return class extends EditModalView {
        setup() {
            super.setup();

            this.once('after:save', () => {
                window.dispatchEvent(new CustomEvent('zile-sarbatoare:calendar-refresh'));
            });
        }
    };
});
