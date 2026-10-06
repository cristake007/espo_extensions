define(['views/modal'], (ModalView) => {
    return class extends ModalView {
        scope = 'HolidayRequest'

        backdrop = true

        templateContent = `
            <div class="form-group">
                <label class="control-label" for="holiday-cancellation-reason">
                    {{translate 'Cancellation Reason' category='labels' scope='HolidayRequest'}}
                </label>
                <textarea
                    id="holiday-cancellation-reason"
                    class="form-control"
                    data-name="reason"
                    rows="4"
                    maxlength="2000"
                ></textarea>
                <p class="help-block">
                    {{translate 'cancellationReasonHelp' category='messages' scope='HolidayRequest'}}
                </p>
            </div>
        `

        setup() {
            this.headerText = this.translate(
                this.options.headerLabel || 'Request Cancellation',
                'labels',
                'HolidayRequest',
            );
            this.buttonList = [
                {
                    name: 'submit',
                    label: this.options.submitLabel || 'Request Cancellation',
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
                    'cancellationReasonEmpty',
                    'messages',
                    'HolidayRequest',
                ));

                return;
            }

            this.trigger('submit', reason);
            this.close();
        }
    };
});
