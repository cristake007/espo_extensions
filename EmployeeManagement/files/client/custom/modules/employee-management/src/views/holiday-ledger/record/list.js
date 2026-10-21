define(['views/record/list'], ListRecordView => class extends ListRecordView {
    afterRender() {
        super.afterRender();

        this.$el.find('.list-row').each((index, element) => {
            const row = $(element);
            const model = this.collection.get(String(row.attr('data-id') || ''));

            if (!model) {
                return;
            }

            const delta = Number(model.get('delta') || 0);
            const type = String(model.get('type') || '').replace(/[^a-zA-Z0-9_-]/g, '');

            row.addClass(delta > 0
                ? 'employee-holiday-ledger-row-positive'
                : (delta < 0
                    ? 'employee-holiday-ledger-row-negative'
                    : 'employee-holiday-ledger-row-neutral'));

            if (type) {
                row.addClass(`employee-holiday-ledger-row-${type}`);
            }
        });
    }
});
