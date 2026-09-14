define(['controller'], (Controller) => {
    return class extends Controller {
        actionIndex() {
            this.main('attendance-management:views/attendance/my', {
                scope: 'Attendance',
            });
        }
    };
});
