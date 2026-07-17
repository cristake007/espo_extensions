define(['views/modals/detail'], (DetailModalView) => {
    return class extends DetailModalView {
        setup() {
            this.fullFormDisabled = true;

            super.setup();
        }
    };
});
