define(['acl'], (Acl) => {
    return class extends Acl {
        checkModelEdit(model, data, precise) {
            if (model.get('managed') || model.get('source') === 'nager-date') {
                return false;
            }

            return super.checkModel(model, data, 'edit', precise);
        }

        checkModelDelete(model, data, precise) {
            if (model.get('managed') || model.get('source') === 'nager-date') {
                return false;
            }

            return super.checkModelDelete(model, data, precise);
        }
    };
});
