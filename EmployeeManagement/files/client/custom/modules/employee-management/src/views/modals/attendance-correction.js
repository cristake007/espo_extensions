define(['views/modal'], (ModalView) => {
    return class extends ModalView {
        scope = 'EmployeeAdministration'

        backdrop = true

        templateContent = `
            <div class="form-group">
                <label class="control-label" for="holiday-cancellation-reason">
                    {{translate 'Correction Reason' category='labels' scope='EmployeeAdministration'}}
                </label>
                <textarea
                    id="holiday-cancellation-reason"
                    class="form-control"
                    data-name="reason"
                    rows="4"
                    maxlength="2000"
                ></textarea>
                <p class="help-block">
                    {{translate 'correctionReasonRequired' category='messages' scope='EmployeeAdministration'}}
                </p>
            </div>
        `

        setup() {
            this.headerText = this.translate(
                'Attendance Correction',
                'labels',
                'EmployeeAdministration',
            );
            this.buttonList = [
                {
                    name: 'submit',
                    label: 'Save',
                    style: 'danger',
                    onClick: () => this.submit(),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];
        }

        afterRender() {
            this.$el.find('[data-name="reason"]').trigger('focus');
        }

        submit() {
            const reason = String(this.$el.find('[data-name="reason"]').val() || '').trim();

            if (!reason) {
                Espo.Ui.warning(this.translate(
                    'correctionReasonRequired',
                    'messages',
                    'EmployeeAdministration',
                ));

                return;
            }

            this.trigger('submit', reason);
            this.close();
        }
    };
});
