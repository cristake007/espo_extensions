define(['views/admin/index'], (AdminIndexView) => {
    return class extends AdminIndexView {
        setup() {
            super.setup();

            if (this.getUser().isAdmin()) {
                return;
            }

            this.panelDataList = this.panelDataList
                .map(panel => ({
                    ...panel,
                    itemList: (panel.itemList || []).filter(item => {
                        if (
                            item.url === '#Admin/employeeAttendanceSettings' &&
                            this.getAcl().check('AttendanceOverview')
                        ) {
                            return true;
                        }

                        return Array.isArray(item.accessDataList) &&
                            Espo.Utils.checkAccessDataList(
                            item.accessDataList,
                            this.getAcl(),
                            this.getUser(),
                        );
                    }),
                }))
                .filter(panel => panel.itemList.length > 0);

            this.clearView('notificationsPanel');
            this.iframeDisabled = true;
        }
    };
});
