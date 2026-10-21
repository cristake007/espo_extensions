define(['views/fields/date'], (DateFieldView) => {
    return class extends DateFieldView {
        getStartDateForDatePicker() {
            if (!this.model.isNew()) {
                return super.getStartDateForDatePicker();
            }

            return this.getDateTime().toDisplayDate(this.getDateTime().getToday());
        }
    };
});
