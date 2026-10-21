define(['controller'], (Controller) => {
    return class extends Controller {
        actionOpen() {
            if (!this.getAcl().check('HolidayProfileManagement')) {
                throw new Espo.Exceptions.AccessDenied();
            }

            this.main('employee-management:views/admin/profiles', {
                scope: 'HolidayProfileManagement',
            });
        }
    };
});
