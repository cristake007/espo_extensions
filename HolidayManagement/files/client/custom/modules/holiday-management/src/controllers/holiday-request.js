define(['controllers/record'], (RecordController) => {
    return class extends RecordController {
        actionApprovals() {
            this.main('holiday-management:views/holiday-request/approvals', {
                scope: this.name,
            });
        }
    };
});
