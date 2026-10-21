define(['views/settings/record/edit'], (SettingsEditView) => {
    return class extends SettingsEditView {
        detailLayout = [
            {
                tabBreak: true,
                tabLabel: 'Access and Editing',
                rows: [
                    [
                        {name: 'employeeAttendanceEditablePastMonths'},
                        {name: 'employeeAttendanceAllowIncompleteExports'},
                    ],
                    [
                        {name: 'employeeAttendanceArchiveInitializer'},
                        false,
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'Employee Schedules',
                rows: [
                    [
                        {name: 'employeeAttendanceScheduleEditor'},
                        false,
                    ],
                ],
            },
        ];

        afterRender() {
            super.afterRender();
            this.$el.addClass('employee-management-settings-page');
        }
    };
});
