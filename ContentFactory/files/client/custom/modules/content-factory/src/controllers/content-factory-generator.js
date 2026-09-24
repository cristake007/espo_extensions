define(['controller'], (Controller) => {
    return class extends Controller {
        actionIndex() {
            this.main('content-factory:views/generator/index', {
                scope: 'ContentFactoryGenerator',
            });
        }
    };
});
