define(['views/settings/record/edit'], (Dep) => {
    return class extends Dep {
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
                        {name: 'holidayManagementAnnualEntitlementDays'},
                        {name: 'holidayManagementResetDate'},
                    ],
                    [
                        {name: 'holidayManagementAutomaticAnnualGrantEnabled'},
                        false,
                    ],
                    [
                        {name: 'holidayManagementCarryOverLimitDays'},
                        {name: 'holidayManagementNegativeBalanceLimitDays'},
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'Approval',
                rows: [
                    [
                        {name: 'holidayManagementApprovers'},
                        false,
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'Printed Approval Blocks',
                rows: [
                    [
                        {name: 'holidayManagementApprovalBlock1Title'},
                        {name: 'holidayManagementApprovalBlock1Name'},
                    ],
                    [
                        {name: 'holidayManagementApprovalBlock2Title'},
                        {name: 'holidayManagementApprovalBlock2Name'},
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'RESET',
                rows: [
                    [
                        {name: 'holidayManagementTestDataReset', fullWidth: true},
                    ],
                ],
            },
        ];
    };
});
