define(['views/modal'], ModalView => {
    return class extends ModalView {
        scope = 'EmployeeHolidayProfile'

        backdrop = true

        templateContent = `
            <p class="text-muted">{{userName}}</p>
            {{#if hasEntries}}
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead><tr>
                        <th>{{translate 'createdAt' category='fields' scope='EmployeeHolidayLedger'}}</th>
                        <th>{{translate 'balanceBefore' category='fields' scope='EmployeeHolidayLedger'}}</th>
                        <th>{{translate 'delta' category='fields' scope='EmployeeHolidayLedger'}}</th>
                        <th>{{translate 'balanceAfter' category='fields' scope='EmployeeHolidayLedger'}}</th>
                        <th>{{translate 'actor' category='fields' scope='EmployeeHolidayLedger'}}</th>
                        <th>{{translate 'reason' category='fields' scope='EmployeeHolidayLedger'}}</th>
                    </tr></thead>
                    <tbody>
                    {{#each entries}}
                        <tr>
                            <td class="text-nowrap">{{createdAt}}</td>
                            <td>{{balanceBefore}}</td>
                            <td>{{delta}}</td>
                            <td>{{balanceAfter}}</td>
                            <td>{{actorName}}</td>
                            <td style="min-width: 180px; white-space: normal;">{{reason}}</td>
                        </tr>
                    {{/each}}
                    </tbody>
                </table>
            </div>
            {{else}}
            <p class="text-muted">
                {{translate 'No Corrections' category='messages' scope='EmployeeAdministration'}}
            </p>
            {{/if}}
        `

        setup() {
            this.operationKey = `correction:${Date.now()}:${Math.random().toString(16).slice(2)}`;
            this.profileId = String(this.options.profileId || '');
            this.userName = String(this.options.userName || '');
            this.entries = [];
            this.headerText = this.translate('Correction History', 'labels', 'EmployeeAdministration');
            this.buttonList = [{name: 'cancel', label: 'Close'}];
            this.wait(this.loadHistory());
        }

        data() {
            return {
                userName: this.userName,
                entries: this.entries,
                hasEntries: this.entries.length > 0,
            };
        }

        async loadHistory() {
            try {
                const response = await Espo.Ajax.postRequest(
                    'EmployeeManagement/administration/profiles/corrections', {profileId:this.profileId}
                );
                this.entries = (response.list || []).map(entry => ({
                    ...entry,
                    createdAt: String(entry.createdAt || entry.effectiveDate || ''),
                    actorName: String(entry.actorName || ''),
                    reason: String(entry.reason || ''),
                }));
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'correctionHistoryLoadFailed',
                    'messages',
                    'EmployeeAdministration',
                ));
            }
        }
    }
});
