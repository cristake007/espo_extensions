define(['acl'], (Acl) => {
    return class extends Acl {
        checkModelDelete(model, data, precise) {
            const status = model.get('status') || 'Pending';

            if (status === 'Approved') {
                return false;
            }

            return super.checkModelDelete(model, data, precise);
        }
    };
});
