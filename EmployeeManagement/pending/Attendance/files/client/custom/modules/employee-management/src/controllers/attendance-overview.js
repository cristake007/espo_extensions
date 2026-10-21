define(['controller'], (Controller) => {
    return class extends Controller {
        actionIndex() {
            this.main('employee-management:views/attendance/overview', {
                scope: 'AttendanceOverview',
            });
        }
    };
});
