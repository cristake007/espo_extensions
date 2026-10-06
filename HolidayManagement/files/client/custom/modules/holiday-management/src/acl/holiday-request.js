define(['acl'], (Acl) => {
    return class extends Acl {
        checkModelDelete(model, data, precise) {
            const status = model.get('status') || 'Pending';

            if (['Approved', 'CancellationPending', 'Cancelled'].includes(status)) {
                return false;
            }

            return super.checkModelDelete(model, data, precise);
        }
    };
});
