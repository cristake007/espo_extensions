define(['views/settings/record/edit'], (Dep) => {
    return class extends Dep {
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
                        {name: 'holidayManagementResetCeilingDays'},
                        {name: 'holidayManagementNegativeBalanceLimitDays'},
                    ],
                    [
                        {name: 'holidayManagementResetWarningDays'},
                        {name: 'holidayManagementResetWarningRepeatDays'},
                    ],
                ],
            },
            {
                tabBreak: true,
                tabLabel: 'Approval',
                rows: [
                    [
                        {name: 'holidayManagementApproverRole'},
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
        ];
    };
});
