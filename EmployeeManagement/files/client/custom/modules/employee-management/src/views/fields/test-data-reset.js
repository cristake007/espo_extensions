define(['views/fields/varchar'], VarcharFieldView => class extends VarcharFieldView {
    editTemplateContent = `
        <div class="employee-holiday-test-data-reset">
            <div class="alert alert-danger">
                <strong>{{translate 'Reset Holiday Test Data' category='labels' scope='EmployeeAdministration'}}</strong>
                <div>{{translate 'testDataResetWarning' category='messages' scope='EmployeeAdministration'}}</div>
            </div>
            <div class="well" data-role="reset-preview">
                <span class="fas fa-spinner fa-spin" aria-hidden="true"></span>
                {{translate 'Loading'}}
            </div>
            <div class="form-group">
                <label class="control-label">
                    {{translate 'Reset Confirmation' category='labels' scope='EmployeeAdministration'}}
                </label>
                <input type="text" class="form-control" data-role="reset-confirmation"
                    autocomplete="off" placeholder="{{translate 'Reset Confirmation Phrase' category='labels' scope='EmployeeAdministration'}}">
            </div>
            <button type="button" class="btn btn-danger" data-action="resetHolidayTestData" disabled>
                <span class="fas fa-trash-alt" aria-hidden="true"></span>
                {{translate 'Reset Holiday Test Data' category='labels' scope='EmployeeAdministration'}}
            </button>
        </div>
    `;

    setup() {
        super.setup();
        this.preview = null;
        this.resetInProgress = false;
        this.addHandler('input', '[data-role="reset-confirmation"]', () => this.updateButtonState());
        this.addHandler('click', '[data-action="resetHolidayTestData"]', (event, target) => this.resetData(target));
    }

    afterRender() {
        super.afterRender();
        this.loadPreview();
    }

    async loadPreview() {
        try {
            this.preview = await Espo.Ajax.getRequest(
                'EmployeeManagement/administration/holiday/testDataResetPreview'
            );
            if (!this.element) { return; }

            const automaticStatus = this.preview.automaticAnnualGrantEnabled
                ? this.translate('Enabled', 'labels', 'EmployeeAdministration')
                : this.translate('Disabled', 'labels', 'EmployeeAdministration');
            const preview = $('<dl>').addClass('dl-horizontal').append(
                this.previewRow('Holiday Profiles', this.preview.profiles),
                this.previewRow('Holiday Requests', this.preview.requests),
                this.previewRow('Holiday Ledger Entries', this.preview.ledgerEntries),
                this.previewRow('Generated Documents', this.preview.documents),
                this.previewRow('Balance Warning Notifications', this.preview.notifications),
                this.previewRow('Calendar Absences', this.preview.absences),
                this.previewRow('Approval Jobs', this.preview.approvalJobs),
                this.previewRow('Active Approval Jobs', this.preview.activeApprovalJobs),
                this.previewRow('Automatic Annual Grants', automaticStatus),
            );
            this.$el.find('[data-role="reset-preview"]').empty().append(preview);

            if (this.preview.automaticAnnualGrantEnabled) {
                this.$el.find('[data-role="reset-preview"]').append(
                    $('<div>').addClass('text-danger').text(this.translate(
                        'testDataResetDisableAutomaticGrant', 'messages', 'EmployeeAdministration'
                    ))
                );
            }
            if (Number(this.preview.activeApprovalJobs || 0) > 0) {
                this.$el.find('[data-role="reset-preview"]').append(
                    $('<div>').addClass('text-danger').text(this.translate(
                        'testDataResetWaitForJobs', 'messages', 'EmployeeAdministration'
                    ))
                );
            }
            this.updateButtonState();
        } catch (error) {
            this.$el.find('[data-role="reset-preview"]')
                .addClass('text-danger')
                .text(this.translate('testDataResetPreviewFailed', 'messages', 'EmployeeAdministration'));
        }
    }

    previewRow(labelKey, value) {
        return [
            $('<dt>').text(this.translate(labelKey, 'labels', 'EmployeeAdministration')),
            $('<dd>').text(String(value ?? 0)),
        ];
    }

    updateButtonState() {
        const confirmation = String(this.$el.find('[data-role="reset-confirmation"]').val() || '');
        const disabled = this.resetInProgress || !this.preview ||
            this.preview.automaticAnnualGrantEnabled === true ||
            Number(this.preview.activeApprovalJobs || 0) > 0 ||
            confirmation !== 'RESET HOLIDAY DATA';
        this.$el.find('[data-action="resetHolidayTestData"]').prop('disabled', disabled);
    }

    async resetData(target) {
        const confirmation = String(this.$el.find('[data-role="reset-confirmation"]').val() || '');
        if (confirmation !== 'RESET HOLIDAY DATA') { return; }

        await this.confirm({
            message: this.translate('confirmTestDataReset', 'messages', 'EmployeeAdministration'),
            confirmText: this.translate('Reset Holiday Test Data', 'labels', 'EmployeeAdministration'),
            confirmStyle: 'danger',
            backdrop: 'static',
        });

        this.resetInProgress = true;
        const button = $(target);
        button.prop('disabled', true);
        try {
            await Espo.Ajax.postRequest(
                'EmployeeManagement/administration/holiday/testDataReset',
                {confirmation},
            );
            Espo.Ui.success(this.translate(
                'testDataResetCompleted', 'messages', 'EmployeeAdministration'
            ));
            this.resetInProgress = false;
            this.trigger('reset');
        } catch (error) {
            this.resetInProgress = false;
            Espo.Ui.error(this.translate(
                'testDataResetFailed', 'messages', 'EmployeeAdministration'
            ));
            this.updateButtonState();
        }
    }

    fetch() { return {}; }
});
