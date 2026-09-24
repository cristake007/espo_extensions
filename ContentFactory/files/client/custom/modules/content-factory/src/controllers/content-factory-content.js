define(['controllers/record'], RecordController => {
    return class extends RecordController {
        actionView(options) {
            this.main('content-factory:views/generator/index', {
                scope: 'ContentFactoryContent',
                historyId: options.id,
                readOnly: true,
            });
        }
    };
});
