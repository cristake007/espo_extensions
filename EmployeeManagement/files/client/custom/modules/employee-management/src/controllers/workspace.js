define(['controller', 'ajax'], (Controller, Ajax) => class extends Controller {
    actionIndex() {
        return this.openWorkspace('workspace');
    }

    actionHoliday() {
        if (!this.getUser().isRegular() || !this.getUser().get('isActive') || !this.getAcl().checkScope('EmployeeManagement')) {
            this.baseController.error403(); return;
        }
        return this.main('employee-management:views/holiday-request/index');
    }

    actionHolidayManagement(options = {}) {
        if (!this.getUser().isRegular() || !this.getUser().get('isActive') ||
            !this.getAcl().checkScope('EmployeeManagement') ||
            this.getAcl().getLevel('EmployeeHolidayRequest', 'read') !== 'all') {
            this.baseController.error403(); return;
        }

        const canManagedBooking = this.getAcl().checkScope('EmployeeHolidayBooking') &&
            this.getAcl().checkScope('EmployeeHolidayRequestManagement') &&
            this.getAcl().getLevel('EmployeeHolidayProfile', 'read') === 'all';
        const canApprove = this.getAcl().checkScope('EmployeeHolidayApproval');

        if (!canManagedBooking && !canApprove) {
            this.baseController.error403(); return;
        }

        let section = options.section || (canApprove ? 'approvals' : 'book');
        const approvalSections = ['approvals', 'cancellations', 'approved'];

        if (!['approvals', 'cancellations', 'approved', 'book'].includes(section) ||
            (approvalSections.includes(section) && !canApprove) ||
            (section === 'book' && !canManagedBooking)) {
            this.baseController.error403(); return;
        }

        return this.main('employee-management:views/holiday-management/index', {
            section,
            canManagedBooking,
            canApprove,
            canVerify:canApprove,
        });
    }

    actionVerifyHolidayDocument() {
        if (!this.getUser().isRegular() || !this.getUser().get('isActive') ||
            !this.getAcl().checkScope('EmployeeManagement') ||
            !this.getAcl().checkScope('EmployeeHolidayApproval')) {
            this.baseController.error403(); return;
        }
        return this.main('employee-management:views/holiday-document/verification');
    }

    actionAdministration() {
        return this.openAdministration();
    }

    actionCreateHoliday() {
        if (!this.getUser().isRegular() || !this.getUser().get('isActive') ||
            !this.getAcl().checkScope('EmployeeManagement') || !this.getAcl().checkScope('EmployeeHolidayBooking') ||
            !this.getAcl().checkScope('EmployeeHolidayRequest', 'read')) {
            this.baseController.error403(); return;
        }
        // The native create URL must use the existing accounting command, never generic Record.create.
        return this.main('employee-management:views/holiday-request/index', {openCreate: true});
    }

    actionSection(options) {
        return this.openAdministration(options.section);
    }

    openAdministration(section) {
        if (!this.getUser().isRegular() || !this.getUser().get('isActive') || !this.getAcl().checkScope('EmployeeAdministration')) {
            this.baseController.error403();
            return;
        }
        return Ajax.getRequest('EmployeeManagement/administration/status').then(status => {
            if (!section) {
                return this.main('employee-management:views/administration/index', {sections: status.administrationSections});
            }
            if (!status.administrationSections.includes(section)) {
                this.baseController.error403();
                return;
            }
            if (section === 'attendanceMetadata') { return this.main('employee-management:views/attendance/schedules'); }
            return this.main(section === 'profiles' ? 'employee-management:views/administration/profiles'
                : 'employee-management:views/administration/settings', {section});
        });
    }

    openWorkspace(area) {
        const scope = area === 'administration' ? 'EmployeeAdministration' : 'EmployeeManagement';

        if (!this.getUser().isRegular() || !this.getUser().get('isActive') || !this.getAcl().checkScope(scope)) {
            // Render the native error view also on an already-open SPA route.
            this.baseController.error403();
            return;
        }

        return Ajax.getRequest('EmployeeManagement/' + area + '/status').then(status => {
            this.main('employee-management:views/workspace', {status, area});
        });
    }
});
