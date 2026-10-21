define(['view'], View => class extends View {
    template = 'employee-management:workspace';

    data() {
        const status = this.options.status;

        return {
            version: status.version,
            administration: this.options.area === 'administration',
            holidayBookingEnabled: status.holidayBookingEnabled,
            attendanceEnabled: status.attendanceEnabled,
            attendanceOverviewEnabled: status.attendanceOverviewEnabled,
            links: status.readableScopes.map(scope => ({
                scope,
                label: this.translate(scope, 'scopeNamesPlural'),
            })),
        };
    }
});
