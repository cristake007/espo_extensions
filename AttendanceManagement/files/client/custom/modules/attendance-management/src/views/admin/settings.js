define(['views/settings/record/edit'], (SettingsEditView) => {
    return class extends SettingsEditView {
        detailLayout = [
            {
                tabBreak: true,
                tabLabel: 'Access and Editing',
                rows: [
                    [
                        {name: 'attendanceManagementManagers'},
                        {name: 'attendanceManagementEditablePastMonths'},
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'Employee Schedules',
                rows: [
                    [
                        {name: 'attendanceManagementScheduleEditor'},
                        false,
                    ],
                ],
            },
        ];

        afterRender() {
            super.afterRender();
            this.$el.addClass('attendance-management-settings-page');
        }
    };
});
