define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <div class="header page-header">
                <h3>{{translate 'Holiday Approvals' category='labels' scope='HolidayRequest'}}</h3>
            </div>
            <div class="record">
                {{#unless isApprover}}
                    <div class="alert alert-warning">
                        {{translate 'Not Holiday Approver' category='messages' scope='HolidayRequest'}}
                    </div>
                {{else}}
                    {{#if hasRows}}
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{translate 'Requester' category='labels' scope='HolidayRequest'}}</th>
                                        <th>{{translate 'dateStartDate' category='fields' scope='HolidayRequest'}}</th>
                                        <th>{{translate 'dateEndDate' category='fields' scope='HolidayRequest'}}</th>
                                        <th>{{translate 'days' category='fields' scope='HolidayRequest'}}</th>
                                        <th>{{translate 'description' category='fields' scope='HolidayRequest'}}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{#each rows}}
                                        <tr data-id="{{id}}">
                                            <td>{{requesterName}}</td>
                                            <td>{{dateStart}}</td>
                                            <td>{{dateEnd}}</td>
                                            <td>{{days}}</td>
                                            <td>{{description}}</td>
                                            <td class="text-right text-nowrap">
                                                <button class="btn btn-success btn-sm" data-action="approve">
                                                    {{translate 'Approve Holiday' category='labels' scope='HolidayRequest'}}
                                                </button>
                                                <button class="btn btn-danger btn-sm" data-action="reject">
                                                    {{translate 'Reject Holiday' category='labels' scope='HolidayRequest'}}
                                                </button>
                                            </td>
                                        </tr>
                                    {{/each}}
                                </tbody>
                            </table>
                        </div>
                    {{else}}
                        <div class="alert alert-info">
                            {{translate 'No Pending Approvals' category='messages' scope='HolidayRequest'}}
                        </div>
                    {{/if}}
                {{/unless}}
            </div>
        `;

        events = {
            'click [data-action="approve"]': 'actionApprove',
            'click [data-action="reject"]': 'actionReject',
        };

        setup() {
            this.rows = [];
            this.isApprover = false;
            this.wait(this.loadQueue());
        }

        data() {
            return {
                rows: this.rows,
                isApprover: this.isApprover,
                hasRows: this.rows.length > 0,
            };
        }

        async loadQueue() {
            const response = await Espo.Ajax.getRequest('HolidayManagement/approvalQueue');

            this.isApprover = response.isApprover === true;
            this.rows = response.list || [];
        }

        actionApprove(event) {
            this.decide($(event.currentTarget), 'Approved');
        }

        actionReject(event) {
            this.decide($(event.currentTarget), 'Rejected');
        }

        async decide(button, decision) {
            const message = decision === 'Approved'
                ? this.translate('confirmApproveHoliday', 'messages', 'HolidayRequest')
                : this.translate('confirmRejectHoliday', 'messages', 'HolidayRequest');

            if (!window.confirm(message)) {
                return;
            }

            const row = button.closest('tr[data-id]');
            const id = row.data('id');

            row.find('button').prop('disabled', true);

            try {
                await Espo.Ajax.postRequest(`HolidayManagement/requests/${id}/decision`, {decision});
                Espo.Ui.success(this.translate(
                    decision === 'Approved' ? 'holidayApproved' : 'holidayRejected',
                    'messages',
                    'HolidayRequest'
                ));
                await this.loadQueue();
                await this.reRender();
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
    };
});
