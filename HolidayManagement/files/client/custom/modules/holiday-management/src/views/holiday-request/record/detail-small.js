define([
    'views/record/detail-small',
    'holiday-management:views/holiday-request/record/approval-actions',
], (DetailSmallView, ApprovalActions) => {
    return class extends DetailSmallView {
        afterRender() {
            super.afterRender();
            ApprovalActions.render(this).catch(() => {});
        }
    };
});
