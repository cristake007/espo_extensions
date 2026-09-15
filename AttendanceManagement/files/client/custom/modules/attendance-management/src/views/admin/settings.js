define(['views/settings/record/edit'], (SettingsEditView) => {
    return class extends SettingsEditView {
        detailLayout = [
            {
                rows: [
                    [
                        {name: 'attendanceManagementManagers'},
                        false,
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
