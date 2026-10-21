define(['views/record/edit-small'], (EditSmallView) => {
    return class extends EditSmallView {
        setup() {
            if (this.model.isNew()) {
                this.copyCalendarDate('dateStart', 'dateStartDate');
                this.copyCalendarDate('dateEnd', 'dateEndDate');
            }

            super.setup();
        }

        copyCalendarDate(dateTimeField, dateField) {
            const value = this.model.get(dateTimeField);

            if (!this.model.get(dateField) && typeof value === 'string' && value.length >= 10) {
                this.model.set(dateField, value.slice(0, 10));
            }
        }
    };
});
