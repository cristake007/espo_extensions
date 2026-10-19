define(['views/fields/varchar'], (VarcharFieldView) => {
    return class extends VarcharFieldView {
        editTemplateContent = `
            <div class="holiday-test-data-reset">
                <div class="alert alert-danger">
                    <strong>{{translate 'Reset Holiday Test Data' category='labels' scope='Admin'}}</strong>
                    <div>{{translate 'testDataResetWarning' category='messages' scope='Admin'}}</div>
                </div>
                <div class="well" data-role="reset-preview">
                    <span class="fas fa-spinner fa-spin" aria-hidden="true"></span>
                    {{translate 'Loading'}}</div>
                <div class="form-group">
                    <label class="control-label">
                        {{translate 'Reset Confirmation' category='labels' scope='Admin'}}
                    </label>
                    <input type="text" class="form-control" data-role="reset-confirmation"
                        autocomplete="off" placeholder="RESET HOLIDAY DATA">
                </div>
                <button type="button" class="btn btn-danger" data-action="resetHolidayTestData" disabled>
                    <span class="fas fa-trash-alt" aria-hidden="true"></span>
                    {{translate 'Reset Holiday Test Data' category='labels' scope='Admin'}}
                </button>
            </div>
        `;

        setup() {
            super.setup();
            this.preview = null;
            this.resetInProgress = false;
            this.addHandler('input', '[data-role="reset-confirmation"]',
                () => this.updateButtonState());
            this.addHandler('click', '[data-action="resetHolidayTestData"]',
                (event, target) => this.resetData(target));
        }

        afterRender() {
            super.afterRender();
            this.loadPreview();
        }

        async loadPreview() {
            try {
                this.preview = await Espo.Ajax.getRequest(
                    'HolidayManagement/testDataResetPreview'
                );

                if (!this.element) {
                    return;
                }

                const automaticStatus = this.preview.automaticAnnualGrantEnabled
                    ? this.translate('Enabled', 'labels', 'Admin')
                    : this.translate('Disabled', 'labels', 'Admin');
                const preview = $('<dl>').addClass('dl-horizontal').append(
                    this.previewRow('Holiday Profiles', this.preview.profiles),
                    this.previewRow('Holiday Requests', this.preview.requests),
                    this.previewRow('Holiday Ledger Entries', this.preview.ledgerEntries),
                    this.previewRow('Generated Documents', this.preview.documents),
                    this.previewRow('Automatic Annual Grants', automaticStatus),
                );

                this.$el.find('[data-role="reset-preview"]').empty().append(preview);

                if (this.preview.automaticAnnualGrantEnabled) {
                    this.$el.find('[data-role="reset-preview"]').append(
                        $('<div>').addClass('text-danger').text(this.translate(
                            'testDataResetDisableAutomaticGrant',
                            'messages',
                            'Admin',
                        ))
                    );
                }

                this.updateButtonState();
            } catch (error) {
                this.$el.find('[data-role="reset-preview"]')
                    .addClass('text-danger')
                    .text(this.translate('testDataResetPreviewFailed', 'messages', 'Admin'));
            }
        }

        previewRow(labelKey, value) {
            return [
                $('<dt>').text(this.translate(labelKey, 'labels', 'Admin')),
                $('<dd>').text(String(value ?? 0)),
            ];
        }

        updateButtonState() {
            const confirmation = String(
                this.$el.find('[data-role="reset-confirmation"]').val() || ''
            );
            const disabled =
                this.resetInProgress ||
                !this.preview ||
                this.preview.automaticAnnualGrantEnabled === true ||
                confirmation !== 'RESET HOLIDAY DATA';

            this.$el.find('[data-action="resetHolidayTestData"]').prop('disabled', disabled);
        }

        async resetData(target) {
            const confirmation = String(
                this.$el.find('[data-role="reset-confirmation"]').val() || ''
            );

            if (confirmation !== 'RESET HOLIDAY DATA') {
                return;
            }

            await this.confirm({
                message: this.translate('confirmTestDataReset', 'messages', 'Admin'),
                confirmText: this.translate('Reset Holiday Test Data', 'labels', 'Admin'),
                confirmStyle: 'danger',
                backdrop: 'static',
            });

            this.resetInProgress = true;
            const button = $(target);
            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'HolidayManagement/testDataReset',
                    {confirmation},
                );
                Espo.Ui.success(this.translate(
                    'testDataResetCompleted',
                    'messages',
                    'Admin',
                ));
                this.$el.find('[data-role="reset-confirmation"]').val('');
                this.preview = {
                    ...result,
                    profiles: 0,
                    requests: 0,
                    ledgerEntries: 0,
                    documents: 0,
                };
                this.resetInProgress = false;
                await this.loadPreview();
            } catch (error) {
                this.resetInProgress = false;
                Espo.Ui.error(this.translate(
                    'testDataResetFailed',
                    'messages',
                    'Admin',
                ));
                this.updateButtonState();
            }
        }

        fetch() {
            return {};
        }
    };
});
