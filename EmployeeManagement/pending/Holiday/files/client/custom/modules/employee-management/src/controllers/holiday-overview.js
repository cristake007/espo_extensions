define(['controller'], (Controller) => {
    return class extends Controller {
        actionIndex(options = {}) {
            if (!this.getAcl().check('HolidayOverview')) {
                throw new Espo.Exceptions.AccessDenied();
            }

            this.main('employee-management:views/holiday-overview/index', {
                scope: 'HolidayOverview',
                month: options.month || null,
            });
        }
    };
});
