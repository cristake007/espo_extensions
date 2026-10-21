define(['views/list'], (ListView) => {
    return class extends ListView {
        setup() {
            super.setup();

            if (this.getAcl().check('HolidayProfileManagement')) {
                this.addMenuItem('buttons', {
                    link: '#Admin/employeeHolidayProfiles',
                    iconHtml: '<span class="fas fa-users-cog fa-sm"></span>',
                    text: this.translate(
                        'Manage Holiday Profiles',
                        'labels',
                        'EmployeeHolidayRequest'
                    ),
                    style: 'default',
                }, true);
            }

            const refreshBalance = () => this.loadHolidayBalance();

            window.addEventListener('employee-management:balance-refresh', refreshBalance);
            this.once('remove', () => {
                window.removeEventListener('employee-management:balance-refresh', refreshBalance);
            });
        }

        afterRender() {
            super.afterRender();
            this.renderManagedBookingAction();
            this.loadHolidayBalance();
            this.loadApprovalQueue();
        }

        renderManagedBookingAction() {
            this.$el.find('.holiday-managed-booking-panel').remove();

            if (!this.getAcl().check('HolidayRequestManagement')) {
                return;
            }

            const panel = $('<div>')
                .addClass('panel panel-default holiday-managed-booking-panel margin-bottom')
                .append(
                    $('<div>').addClass('panel-body').append(
                        $('<div>').addClass('pull-left').append(
                            $('<strong>').text(this.translate(
                                'Managed Booking',
                                'labels',
                                'EmployeeHolidayRequest'
                            )),
                            $('<div>').addClass('text-muted small').text(this.translate(
                                'managedBookingPanelHelp',
                                'messages',
                                'EmployeeHolidayRequest'
                            )),
                        ),
                        $('<button>', {type: 'button'})
                            .addClass('btn btn-primary pull-right')
                            .append(
                                $('<span>').addClass('fas fa-user-plus').attr('aria-hidden', 'true'),
                                ' ',
                                this.translate(
                                    'Book for Employee',
                                    'labels',
                                    'EmployeeHolidayRequest'
                                )
                            )
                            .on('click', () => this.openManagedBookingModal()),
                        $('<div>').addClass('clearfix'),
                    ),
                );

            this.$el.find('.page-header').after(panel);
        }

        openManagedBookingModal() {
            const viewName = `holidayManagedBooking-${Date.now()}`;

            this.createView(
                viewName,
                'employee-management:views/modals/managed-booking',
                {},
            ).then(modal => {
                this.listenToOnce(modal, 'saved', async () => {
                    Espo.Ui.success(this.translate(
                        'managedBookingSaved',
                        'messages',
                        'EmployeeHolidayRequest'
                    ));
                    await this.collection.fetch();
                    this.loadApprovalQueue();
                    window.dispatchEvent(new CustomEvent(
                        'employee-management:balance-refresh'
                    ));
                    window.dispatchEvent(new CustomEvent(
                        'zile-sarbatoare:calendar-refresh'
                    ));
                });
                modal.render();
            });
        }

        async loadApprovalQueue() {
            let response;

            try {
                response = await Espo.Ajax.getRequest('EmployeeManagement/approvalQueue');
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
                        this.translate('Holiday Approvals', 'labels', 'EmployeeHolidayRequest')
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
                            'EmployeeHolidayRequest'
                        )),
                );
            } else {
                const tableBody = $('<tbody>');

                rows.forEach(item => {
                    const isCancellation = item.status === 'CancellationPending';
                    const actionCell = $('<td>').addClass('text-right text-nowrap');
                    const requesterCell = $('<td>').text(item.requesterName || '');

                    if (isCancellation) {
                        requesterCell.append(
                            $('<div>')
                                .addClass('text-warning small')
                                .text(`${this.translate(
                                    'Cancellation Reason',
                                    'labels',
                                    'EmployeeHolidayRequest'
                                )}: ${item.cancellationReason || ''}`),
                        );
                    }

                    const row = $('<tr>')
                        .attr('data-id', item.id)
                        .attr('data-cancellation', isCancellation ? 'true' : 'false')
                        .append(
                        requesterCell,
                        $('<td>').text(item.dateStart || ''),
                        $('<td>').text(item.dateEnd || ''),
                        $('<td>').text(String(item.days ?? '')),
                        actionCell,
                    );

                    $('<button>')
                        .addClass('btn btn-success btn-sm')
                        .text(this.translate(
                            isCancellation ? 'Approve Cancellation' : 'Approve Holiday',
                            'labels',
                            'EmployeeHolidayRequest'
                        ))
                        .on('click', () => this.decideApproval(
                            row,
                            isCancellation ? 'Cancelled' : 'Approved',
                            isCancellation
                        ))
                        .appendTo(actionCell);
                    actionCell.append(' ');
                    $('<button>')
                        .addClass('btn btn-danger btn-sm')
                        .text(this.translate(
                            isCancellation ? 'Reject Cancellation' : 'Reject Holiday',
                            'labels',
                            'EmployeeHolidayRequest'
                        ))
                        .on('click', () => this.decideApproval(
                            row,
                            isCancellation ? 'Approved' : 'Rejected',
                            isCancellation
                        ))
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
                                        'EmployeeHolidayRequest'
                                    )),
                                    $('<th>').text(this.translate(
                                        'dateStartDate',
                                        'fields',
                                        'EmployeeHolidayRequest'
                                    )),
                                    $('<th>').text(this.translate(
                                        'dateEndDate',
                                        'fields',
                                        'EmployeeHolidayRequest'
                                    )),
                                    $('<th>').text(this.translate(
                                        'days',
                                        'fields',
                                        'EmployeeHolidayRequest'
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

        async decideApproval(row, decision, isCancellation = false) {
            const approved = decision === (isCancellation ? 'Cancelled' : 'Approved');
            const messageKey = isCancellation ?
                (approved ? 'confirmApproveCancellation' : 'confirmRejectCancellation') :
                (approved ? 'confirmApproveHoliday' : 'confirmRejectHoliday');
            const labelKey = isCancellation ?
                (approved ? 'Approve Cancellation' : 'Reject Cancellation') :
                (approved ? 'Approve Holiday' : 'Reject Holiday');
            const message = this.translate(messageKey, 'messages', 'EmployeeHolidayRequest');

            await this.confirm({
                message,
                confirmText: this.translate(
                    labelKey,
                    'labels',
                    'EmployeeHolidayRequest',
                ),
            });

            row.find('button').prop('disabled', true);

            try {
                await Espo.Ajax.postRequest(
                    `EmployeeManagement/requests/${row.data('id')}/decision`,
                    {decision}
                );
                Espo.Ui.success(this.translate(
                    isCancellation ?
                        (approved ? 'cancellationApproved' : 'cancellationRejected') :
                        (approved ? 'holidayApproved' : 'holidayRejected'),
                    'messages',
                    'EmployeeHolidayRequest'
                ));
                await this.loadApprovalQueue();
                window.dispatchEvent(new CustomEvent(
                    'employee-management:balance-refresh'
                ));
                window.dispatchEvent(new CustomEvent('zile-sarbatoare:calendar-refresh'));
            } catch (error) {
                row.find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    isCancellation ? 'cancellationActionFailed' : 'approvalDecisionFailed',
                    'messages',
                    'EmployeeHolidayRequest'
                ));
            }
        }

        async loadHolidayBalance() {
            if (this.getUser().isAdmin()) {
                this.$el.find('.holiday-balance-panel').remove();

                return;
            }

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
                                'EmployeeHolidayRequest'
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
                balance = await Espo.Ajax.getRequest('EmployeeManagement/myBalance');
            } catch (error) {
                panel.find('.panel-body')
                    .empty()
                    .append(
                        $('<span>')
                            .addClass('text-danger')
                            .text(this.translate('Balance Unavailable', 'labels', 'EmployeeHolidayRequest')),
                    );
                return;
            }

            const body = panel.find('.panel-body').empty();

            if (!balance.initialized) {
                body.append(
                    $('<span>')
                        .addClass('text-warning')
                        .text(this.translate('Profile Not Ready', 'labels', 'EmployeeHolidayRequest')),
                );
                return;
            }

            const displayDate = balance.nextResetDate
                ? this.getDateTime().toDisplayDate(String(balance.nextResetDate))
                : this.translate('Not Set', 'labels', 'EmployeeHolidayRequest');
            const pendingDays = Number(balance.pendingDays || 0);

            body.append(
                $('<div>').addClass('holiday-balance-card__metric').append(
                    $('<div>')
                        .addClass('holiday-balance-card__number')
                        .text(String(balance.availableDays)),
                    $('<div>')
                        .addClass('holiday-balance-card__metric-label')
                        .text(this.translate('Days Available', 'labels', 'EmployeeHolidayRequest')),
                ),
                $('<div>').addClass('holiday-balance-card__details').append(
                    $('<div>').addClass('holiday-balance-card__detail').append(
                        $('<span>')
                            .addClass('fas fa-hourglass-half holiday-balance-card__detail-icon')
                            .attr('aria-hidden', 'true'),
                        $('<div>').append(
                            $('<div>')
                                .addClass('holiday-balance-card__detail-label')
                                .text(this.translate(
                                    'Pending Requests',
                                    'labels',
                                    'EmployeeHolidayRequest'
                                )),
                            $('<div>')
                                .addClass('holiday-balance-card__detail-value')
                                .text(pendingDays > 0 ? `-${pendingDays}` : '0'),
                        ),
                    ),
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
                                    'EmployeeHolidayRequest'
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
                                    'EmployeeHolidayRequest'
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
