define(['views/modal'], ModalView => {
    return class extends ModalView {
        scope = 'EmployeeHolidayRequest'

        backdrop = true

        templateContent = `
            {{#if hasUsers}}
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{translate 'Employee' category='labels' scope='EmployeeHolidayRequest'}}</th>
                            <th class="text-right">
                                {{translate 'Days Available' category='labels' scope='EmployeeHolidayRequest'}}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    {{#each users}}
                        <tr>
                            <td>
                                <button type="button" class="btn btn-link btn-sm"
                                    data-action="selectEmployee" data-id="{{id}}">
                                    {{name}}
                                </button>
                            </td>
                            <td class="text-right text-nowrap">{{balance}}</td>
                        </tr>
                    {{/each}}
                    </tbody>
                </table>
            </div>
            {{else}}
            <p class="text-muted">
                {{translate 'No Eligible Employees' category='messages' scope='EmployeeHolidayRequest'}}
            </p>
            {{/if}}
        `

        events = {
            'click [data-action="selectEmployee"]': 'actionSelectEmployee',
        }

        setup() {
            this.headerText = this.translate(
                'Select Employee',
                'labels',
                'EmployeeHolidayRequest',
            );
            this.buttonList = [{name: 'cancel', label: 'Cancel'}];
            this.users = (this.options.users || []).map(user => ({
                id: String(user.id || ''),
                name: String(user.name || ''),
                balance: this.formatBalance(user.balance),
                raw: user,
            }));
        }

        data() {
            return {
                users: this.users,
                hasUsers: this.users.length > 0,
            };
        }

        formatBalance(value) {
            const balance = Number(value);

            if (!Number.isFinite(balance)) {
                return '0';
            }

            return balance.toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            });
        }

        actionSelectEmployee(event) {
            const id = String($(event.currentTarget).data('id') || '');
            const user = this.users.find(item => item.id === id);

            if (!user) {
                return;
            }

            this.trigger('select', user.raw);
        }
    };
});
