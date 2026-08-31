define([
    'views/record/detail',
    'holiday-management:views/holiday-request/record/approval-actions',
], (DetailView, ApprovalActions) => {
    return class extends DetailView {
        afterRender() {
            super.afterRender();
            ApprovalActions.render(this).catch(() => {});
        }
    };
});
