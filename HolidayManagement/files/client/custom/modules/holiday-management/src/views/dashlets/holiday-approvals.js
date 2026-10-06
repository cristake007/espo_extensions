define(['views/dashlets/abstract/base'], (BaseDashletView) => {
    return class extends BaseDashletView {
        name = 'HolidayApprovals';
        noPadding = true;

        templateContent = `
            <div class="holiday-approvals-dashlet">
                {{#if unavailable}}
                    <div class="alert alert-danger holiday-approvals-dashlet__message">
                        {{translate 'Approval Queue Unavailable' category='messages' scope='HolidayRequest'}}
                    </div>
                {{else}}
                    {{#unless isApprover}}
                        <div class="alert alert-warning holiday-approvals-dashlet__message">
                            {{translate 'Not Holiday Approver' category='messages' scope='HolidayRequest'}}
                        </div>
                    {{else}}
                        {{#if hasRows}}
                            <div class="holiday-approvals-dashlet__list">
                                {{#each rows}}
                                    <article class="holiday-approvals-dashlet__item" data-id="{{id}}" data-cancellation="{{isCancellation}}">
                                        <div class="holiday-approvals-dashlet__summary">
                                            <strong>{{requesterName}}</strong>
                                            <span class="badge" title="{{translate 'days' category='fields' scope='HolidayRequest'}}">
                                                {{days}}
                                            </span>
                                        </div>
                                        <div class="holiday-approvals-dashlet__dates">
                                            <span class="far fa-calendar" aria-hidden="true"></span>
                                            {{dateRange}}
                                        </div>
                                        {{#if description}}
                                            <div class="holiday-approvals-dashlet__description">
                                                {{description}}
                                            </div>
                                        {{/if}}
                                        {{#if isCancellation}}
                                            <div class="holiday-approvals-dashlet__description text-warning">
                                                <strong>{{translate 'Cancellation Reason' category='labels' scope='HolidayRequest'}}:</strong>
                                                {{cancellationReason}}
                                            </div>
                                        {{/if}}
                                        <div class="holiday-approvals-dashlet__actions">
                                            <button class="btn btn-success btn-sm" data-action="approve">
                                                {{#if isCancellation}}
                                                    {{translate 'Approve Cancellation' category='labels' scope='HolidayRequest'}}
                                                {{else}}
                                                    {{translate 'Approve Holiday' category='labels' scope='HolidayRequest'}}
                                                {{/if}}
                                            </button>
                                            <button class="btn btn-danger btn-sm" data-action="reject">
                                                {{#if isCancellation}}
                                                    {{translate 'Reject Cancellation' category='labels' scope='HolidayRequest'}}
                                                {{else}}
                                                    {{translate 'Reject Holiday' category='labels' scope='HolidayRequest'}}
                                                {{/if}}
                                            </button>
                                        </div>
                                    </article>
                                {{/each}}
                            </div>
                        {{else}}
                            <div class="holiday-approvals-dashlet__empty text-muted">
                                <span class="fas fa-check-circle" aria-hidden="true"></span>
                                {{translate 'No Pending Approvals' category='messages' scope='HolidayRequest'}}
                            </div>
                        {{/if}}
                    {{/unless}}
                {{/if}}
            </div>
        `;

        events = {
            'click [data-action="approve"]': 'actionApprove',
            'click [data-action="reject"]': 'actionReject',
        };

        setup() {
            this.rows = [];
            this.isApprover = false;
            this.unavailable = false;
            this.wait(this.loadQueue());
        }

        data() {
            return {
                rows: this.rows,
                isApprover: this.isApprover,
                hasRows: this.rows.length > 0,
                unavailable: this.unavailable,
            };
        }

        async loadQueue() {
            let response;

            this.unavailable = false;

            try {
                response = await Espo.Ajax.getRequest('HolidayManagement/approvalQueue');
            } catch (error) {
                this.rows = [];
                this.isApprover = false;
                this.unavailable = true;

                return;
            }

            this.isApprover = response.isApprover === true;
            this.rows = (response.list || []).map(item => {
                const start = this.getDateTime().toDisplayDate(String(item.dateStart));
                const end = this.getDateTime().toDisplayDate(String(item.dateEnd));

                return {
                    ...item,
                    dateRange: start === end ? start : `${start} – ${end}`,
                    isCancellation: item.status === 'CancellationPending',
                };
            });
        }

        actionApprove(event) {
            const button = $(event.currentTarget);
            const isCancellation = button.closest('[data-id]').data('cancellation') === true;

            this.decide(button, isCancellation ? 'Cancelled' : 'Approved', isCancellation);
        }

        actionReject(event) {
            const button = $(event.currentTarget);
            const isCancellation = button.closest('[data-id]').data('cancellation') === true;

            this.decide(button, isCancellation ? 'Approved' : 'Rejected', isCancellation);
        }

        async decide(button, decision, isCancellation) {
            const item = button.closest('[data-id]');
            const approved = decision === (isCancellation ? 'Cancelled' : 'Approved');
            const messageKey = isCancellation ?
                (approved ? 'confirmApproveCancellation' : 'confirmRejectCancellation') :
                (approved ? 'confirmApproveHoliday' : 'confirmRejectHoliday');
            const labelKey = isCancellation ?
                (approved ? 'Approve Cancellation' : 'Reject Cancellation') :
                (approved ? 'Approve Holiday' : 'Reject Holiday');

            await this.confirm({
                message: this.translate(messageKey, 'messages', 'HolidayRequest'),
                confirmText: this.translate(
                    labelKey,
                    'labels',
                    'HolidayRequest',
                ),
            });

            item.find('button').prop('disabled', true);

            try {
                await Espo.Ajax.postRequest(
                    `HolidayManagement/requests/${item.data('id')}/decision`,
                    {decision},
                );
                Espo.Ui.success(this.translate(
                    isCancellation ?
                        (approved ? 'cancellationApproved' : 'cancellationRejected') :
                        (approved ? 'holidayApproved' : 'holidayRejected'),
                    'messages',
                    'HolidayRequest',
                ));
                await this.loadQueue();
                await this.reRender();
                window.dispatchEvent(new CustomEvent(
                    'holiday-management:balance-refresh'
                ));
                window.dispatchEvent(new CustomEvent('zile-sarbatoare:calendar-refresh'));
            } catch (error) {
                item.find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    isCancellation ? 'cancellationActionFailed' : 'approvalDecisionFailed',
                    'messages',
                    'HolidayRequest',
                ));
            }
        }

        async actionRefresh() {
            await this.loadQueue();
            await this.reRender();
        }
    };
});
