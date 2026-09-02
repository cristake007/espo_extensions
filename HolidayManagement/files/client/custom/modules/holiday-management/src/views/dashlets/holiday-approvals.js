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
                                    <article class="holiday-approvals-dashlet__item" data-id="{{id}}">
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
                                        <div class="holiday-approvals-dashlet__actions">
                                            <button class="btn btn-success btn-sm" data-action="approve">
                                                {{translate 'Approve Holiday' category='labels' scope='HolidayRequest'}}
                                            </button>
                                            <button class="btn btn-danger btn-sm" data-action="reject">
                                                {{translate 'Reject Holiday' category='labels' scope='HolidayRequest'}}
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
                };
            });
        }

        actionApprove(event) {
            this.decide($(event.currentTarget), 'Approved');
        }

        actionReject(event) {
            this.decide($(event.currentTarget), 'Rejected');
        }

        async decide(button, decision) {
            const item = button.closest('[data-id]');
            const messageKey = decision === 'Approved'
                ? 'confirmApproveHoliday'
                : 'confirmRejectHoliday';

            await this.confirm({
                message: this.translate(messageKey, 'messages', 'HolidayRequest'),
                confirmText: this.translate(
                    decision === 'Approved' ? 'Approve Holiday' : 'Reject Holiday',
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
                    decision === 'Approved' ? 'holidayApproved' : 'holidayRejected',
                    'messages',
                    'HolidayRequest',
                ));
                await this.loadQueue();
                await this.reRender();
                window.dispatchEvent(new CustomEvent('zile-sarbatoare:calendar-refresh'));
            } catch (error) {
                item.find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'approvalDecisionFailed',
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
