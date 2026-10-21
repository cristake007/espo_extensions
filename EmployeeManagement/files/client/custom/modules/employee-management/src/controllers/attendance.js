define(['controller'], (Controller) => {
    return class extends Controller {
        actionIndex(options = {}) {
            if (!this.getUser().isRegular() || !this.getUser().get('isActive') || !this.getAcl().checkScope('EmployeeAttendance') || !this.getAcl().checkScope('EmployeeManagement')) { this.baseController.error403(); return; }
            this.main('employee-management:views/attendance/my', {
                scope: 'EmployeeAttendance',
                month: options.month || null,
            });
        }
    };
});
