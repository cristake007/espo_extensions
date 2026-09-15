define(['views/settings/record/edit'], (SettingsEditView) => {
    return class extends SettingsEditView {
        detailLayout = [
            {
                rows: [
                    [
                        {name: 'attendanceManagementManagers'},
                        {name: 'attendanceManagementEditablePastMonths'},
                    ],
                    [
                        {name: 'attendanceManagementScheduleEditor'},
                        false,
                    ],
                ],
            },
        ];
    };
});
