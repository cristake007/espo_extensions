define(['controller'], (Controller) => {
    return class extends Controller {
        actionIndex(options = {}) {
            this.main('attendance-management:views/attendance/my', {
                scope: 'Attendance',
                month: options.month || null,
            });
        }
    };
});
