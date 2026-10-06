define(['controller'], (Controller) => {
    return class extends Controller {
        async actionIndex() {
            if (this.getUser().isAdmin()) {
                this.getRouter().dispatch('Admin', 'index');

                return;
            }

            this.assertManagerAccess();

            this.main('attendance-management:views/admin/manager-index');
        }

        async actionOpen() {
            this.assertManagerAccess();

            this.main('attendance-management:views/attendance/schedules', {
                scope: 'AttendanceOverview',
            });
        }

        assertManagerAccess() {
            if (!this.getAcl().check('AttendanceOverview')) {
                throw new Espo.Exceptions.AccessDenied();
            }
        }
    };
});
