define(['views/settings/record/edit'], (Dep) => {
    return class extends Dep {
        setup() {
            if (!this.getUser().isAdmin()) {
                this.detailLayout = this.detailLayout.filter(
                    panel => panel.tabLabel !== 'RESET',
                );
            }

            super.setup();
        }

        async actionSave(data) {
            try {
                await super.actionSave(data);
            } catch (reason) {
                if (reason === 'notModified' || this.lastSaveCancelReason === 'notModified') {
                    return;
                }

                throw reason;
            }
        }

        detailLayout = [
            {
                tabBreak: true,
                tabLabel: 'Defaults',
                rows: [
                    [
                        {name: 'employeeHolidayAnnualEntitlementDays'},
                        {name: 'employeeHolidayResetDate'},
                    ],
                    [
                        {name: 'employeeHolidayAutomaticAnnualGrantEnabled'},
                        false,
                    ],
                    [
                        {name: 'employeeHolidayCarryOverLimitDays'},
                        {name: 'employeeHolidayNegativeBalanceLimitDays'},
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'Approval',
                rows: [
                    [
                        {name: 'employeeHolidayApprovers'},
                        false,
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'Printed Approval Blocks',
                rows: [
                    [
                        {name: 'employeeHolidayApprovalBlock1Title'},
                        {name: 'employeeHolidayApprovalBlock1Name'},
                    ],
                    [
                        {name: 'employeeHolidayApprovalBlock2Title'},
                        {name: 'employeeHolidayApprovalBlock2Name'},
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'RESET',
                rows: [
                    [
                        {name: 'employeeHolidayTestDataReset', fullWidth: true},
                    ],
                ],
            },
        ];
    };
});
