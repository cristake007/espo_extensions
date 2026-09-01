define(['views/list'], (ListView) => {
    return class extends ListView {
        afterRender() {
            super.afterRender();
            this.loadHolidayBalance();
            this.loadApprovalQueue();
        }

        async loadApprovalQueue() {
            let response;

            try {
                response = await Espo.Ajax.getRequest('HolidayManagement/approvalQueue');
            } catch (error) {
                return;
            }

            this.$el.find('.holiday-approval-panel').remove();

            if (response.isApprover !== true) {
                return;
            }

            const rows = response.list || [];
            const heading = $('<div>')
                .addClass('panel-heading')
                .append(
                    $('<strong>').text(
                        this.translate('Holiday Approvals', 'labels', 'HolidayRequest')
                    ),
                    $('<span>').addClass('badge margin-left').text(String(rows.length)),
                );
            const body = $('<div>').addClass('panel-body');
            const panel = $('<div>')
                .addClass('panel panel-warning holiday-approval-panel margin-bottom')
                .append(heading, body);

            if (!rows.length) {
                body.append(
                    $('<span>')
                        .addClass('text-muted')
                        .text(this.translate(
                            'No Pending Approvals',
                            'messages',
                            'HolidayRequest'
                        )),
                );
            } else {
                const tableBody = $('<tbody>');

                rows.forEach(item => {
                    const actionCell = $('<td>').addClass('text-right text-nowrap');
                    const row = $('<tr>').attr('data-id', item.id).append(
                        $('<td>').text(item.requesterName || ''),
                        $('<td>').text(item.dateStart || ''),
                        $('<td>').text(item.dateEnd || ''),
                        $('<td>').text(String(item.days ?? '')),
                        actionCell,
                    );

                    $('<button>')
                        .addClass('btn btn-success btn-sm')
                        .text(this.translate('Approve Holiday', 'labels', 'HolidayRequest'))
                        .on('click', () => this.decideApproval(row, 'Approved'))
                        .appendTo(actionCell);
                    actionCell.append(' ');
                    $('<button>')
                        .addClass('btn btn-danger btn-sm')
                        .text(this.translate('Reject Holiday', 'labels', 'HolidayRequest'))
                        .on('click', () => this.decideApproval(row, 'Rejected'))
                        .appendTo(actionCell);
                    tableBody.append(row);
                });

                body.append(
                    $('<div>').addClass('table-responsive').append(
                        $('<table>').addClass('table table-striped table-condensed').append(
                            $('<thead>').append(
                                $('<tr>').append(
                                    $('<th>').text(this.translate(
                                        'Requester',
                                        'labels',
                                        'HolidayRequest'
                                    )),
                                    $('<th>').text(this.translate(
                                        'dateStartDate',
                                        'fields',
                                        'HolidayRequest'
                                    )),
                                    $('<th>').text(this.translate(
                                        'dateEndDate',
                                        'fields',
                                        'HolidayRequest'
                                    )),
                                    $('<th>').text(this.translate(
                                        'days',
                                        'fields',
                                        'HolidayRequest'
                                    )),
                                    $('<th>'),
                                ),
                            ),
                            tableBody,
                        ),
                    ),
                );
            }

            const balancePanel = this.$el.find('.holiday-balance-panel');

            if (balancePanel.length) {
                balancePanel.after(panel);
            } else {
                this.$el.find('.page-header').after(panel);
            }
        }

        async decideApproval(row, decision) {
            const message = decision === 'Approved'
                ? this.translate('confirmApproveHoliday', 'messages', 'HolidayRequest')
                : this.translate('confirmRejectHoliday', 'messages', 'HolidayRequest');

            await this.confirm({
                message,
                confirmText: this.translate(
                    decision === 'Approved' ? 'Approve Holiday' : 'Reject Holiday',
                    'labels',
                    'HolidayRequest',
                ),
            });

            row.find('button').prop('disabled', true);

            try {
                await Espo.Ajax.postRequest(
                    `HolidayManagement/requests/${row.data('id')}/decision`,
                    {decision}
                );
                Espo.Ui.success(this.translate(
                    decision === 'Approved' ? 'holidayApproved' : 'holidayRejected',
                    'messages',
                    'HolidayRequest'
                ));
                await this.loadApprovalQueue();
                window.dispatchEvent(new CustomEvent('zile-sarbatoare:calendar-refresh'));
            } catch (error) {
                row.find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'approvalDecisionFailed',
                    'messages',
                    'HolidayRequest'
                ));
            }
        }

        async loadHolidayBalance() {
            const panel = $('<div>')
                .addClass('panel panel-default holiday-balance-panel holiday-balance-card margin-bottom')
                .append(
                    $('<div>')
                        .addClass('panel-heading holiday-balance-card__heading')
                        .append(
                            $('<span>')
                                .addClass('fas fa-umbrella-beach holiday-balance-card__icon')
                                .attr('aria-hidden', 'true'),
                            $('<span>').text(this.translate(
                                'Holiday Balance',
                                'labels',
                                'HolidayRequest'
                            )),
                        ),
                    $('<div>')
                        .addClass('panel-body holiday-balance-card__body')
                        .append($('<span>').addClass('text-muted').text(this.translate('Loading...'))),
                );

            this.$el.find('.holiday-balance-panel').remove();
            this.$el.find('.page-header').after(panel);

            let balance;

            try {
                balance = await Espo.Ajax.getRequest('HolidayManagement/myBalance');
            } catch (error) {
                panel.find('.panel-body')
                    .empty()
                    .append(
                        $('<span>')
                            .addClass('text-danger')
                            .text(this.translate('Balance Unavailable', 'labels', 'HolidayRequest')),
                    );
                return;
            }

            const body = panel.find('.panel-body').empty();

            if (!balance.initialized) {
                body.append(
                    $('<span>')
                        .addClass('text-warning')
                        .text(this.translate('Profile Not Ready', 'labels', 'HolidayRequest')),
                );
                return;
            }

            const displayDate = balance.nextResetDate
                ? this.getDateTime().toDisplayDate(String(balance.nextResetDate))
                : this.translate('Not Set', 'labels', 'HolidayRequest');

            body.append(
                $('<div>').addClass('holiday-balance-card__metric').append(
                    $('<div>')
                        .addClass('holiday-balance-card__number')
                        .text(String(balance.balance)),
                    $('<div>')
                        .addClass('holiday-balance-card__metric-label')
                        .text(this.translate('Days Available', 'labels', 'HolidayRequest')),
                ),
                $('<div>').addClass('holiday-balance-card__details').append(
                    $('<div>').addClass('holiday-balance-card__detail').append(
                        $('<span>')
                            .addClass('fas fa-calendar-check holiday-balance-card__detail-icon')
                            .attr('aria-hidden', 'true'),
                        $('<div>').append(
                            $('<div>')
                                .addClass('holiday-balance-card__detail-label')
                                .text(this.translate(
                                    'Annual Entitlement',
                                    'labels',
                                    'HolidayRequest'
                                )),
                            $('<div>')
                                .addClass('holiday-balance-card__detail-value')
                                .text(String(balance.annualEntitlement)),
                        ),
                    ),
                    $('<div>').addClass('holiday-balance-card__detail').append(
                        $('<span>')
                            .addClass('fas fa-redo-alt holiday-balance-card__detail-icon')
                            .attr('aria-hidden', 'true'),
                        $('<div>').append(
                            $('<div>')
                                .addClass('holiday-balance-card__detail-label')
                                .text(this.translate(
                                    'Next Reset',
                                    'labels',
                                    'HolidayRequest'
                                )),
                            $('<div>')
                                .addClass('holiday-balance-card__detail-value')
                                .text(displayDate),
                        ),
                    ),
                ),
            );
        }
    };
});
