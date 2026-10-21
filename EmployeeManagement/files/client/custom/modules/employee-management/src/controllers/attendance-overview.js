define(['controller'], (Controller) => {
    return class extends Controller {
        actionIndex() {
            if (!this.getUser().isRegular() || !this.getUser().get('isActive') || !this.getAcl().checkScope('EmployeeAttendanceOverview') || !this.getAcl().checkScope('EmployeeManagement')) { this.baseController.error403(); return; }
            this.main('employee-management:views/attendance/overview', {
                scope: 'EmployeeAttendanceOverview',
            });
        }
    };
});
