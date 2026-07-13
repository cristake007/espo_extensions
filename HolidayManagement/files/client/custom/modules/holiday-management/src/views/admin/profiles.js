define(['view'], (Dep) => {
    return class extends Dep {
        templateContent = `
            <div class="header page-header"><h3>{{translate 'Holiday Profiles' category='labels' scope='Admin'}}</h3></div>
            <div class="record">
                <div class="button-container margin-bottom">
                    <button class="btn btn-primary" data-action="save">{{translate 'Save'}}</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr>
                            <th>{{translate 'User'}}</th>
                            <th>{{translate 'Annual Entitlement' category='labels' scope='Admin'}}</th>
                            <th>{{translate 'Opening Balance' category='labels' scope='Admin'}}</th>
                            <th>{{translate 'Next Reset Date' category='labels' scope='Admin'}}</th>
                            <th>{{translate 'Status'}}</th>
                        </tr></thead>
                        <tbody>
                        {{#each rows}}
                            <tr data-user-id="{{userId}}">
                                <td>{{userName}}</td>
                                <td><input class="form-control input-sm" type="number" step="0.5" data-name="annualEntitlement" value="{{annualEntitlement}}"></td>
                                <td><input class="form-control input-sm" type="number" step="0.5" data-name="openingBalance" value="{{balance}}"></td>
                                <td><input class="form-control input-sm" type="date" data-name="nextResetDate" value="{{nextResetDate}}"></td>
                                <td>{{#if isInitialized}}{{translate 'Initialized' category='labels' scope='Admin'}}{{else}}{{translate 'Not Initialized' category='labels' scope='Admin'}}{{/if}}</td>
                            </tr>
                        {{/each}}
                        </tbody>
                    </table>
                </div>
            </div>
        `

        events = {
            'click [data-action="save"]': 'actionSave',
        }

        setup() {
            this.rows = [];
            this.wait(this.loadProfiles());
        }

        data() {
            return {rows: this.rows};
        }

        async loadProfiles() {
            const response = await Espo.Ajax.getRequest('HolidayManagement/profiles');
            this.rows = response.list || [];
        }

        async actionSave() {
            const operation = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const items = [];

            this.$el.find('tbody tr[data-user-id]').each((index, element) => {
                const row = $(element);
                const annualEntitlement = row.find('[data-name="annualEntitlement"]').val();
                const openingBalance = row.find('[data-name="openingBalance"]').val();
                const nextResetDate = row.find('[data-name="nextResetDate"]').val();

                if (annualEntitlement === '' && openingBalance === '' && nextResetDate === '') {
                    return;
                }

                items.push({
                    userId: row.data('user-id'),
                    annualEntitlement: Number(annualEntitlement),
                    openingBalance: Number(openingBalance),
                    nextResetDate,
                    idempotencyKey: `bulk:${operation}:${row.data('user-id')}`,
                });
            });

            if (!items.length) {
                Espo.Ui.warning(this.translate('No Data'));
                return;
            }

            const saveButton = this.$el.find('[data-action="save"]');
            saveButton.prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('HolidayManagement/bulkInitialize', {items});
                Espo.Ui.success(this.translate('Saved'));
                await this.loadProfiles();
                await this.reRender();
            } finally {
                saveButton.prop('disabled', false);
            }
        }
    }
});
