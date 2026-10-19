define(['views/modal', 'model'], (ModalView, Model) => {
    return class extends ModalView {
        scope = 'HolidayRequest'

        backdrop = true

        templateContent = `
            <div class="form-group">
                <label class="control-label" for="managed-holiday-user">
                    {{translate 'Employee' category='labels' scope='HolidayRequest'}}
                </label>
                <select id="managed-holiday-user" class="form-control" data-name="userId"></select>
            </div>
            <div class="row">
                <div class="col-sm-6 form-group">
                    <label class="control-label" for="managed-holiday-start">
                        {{translate 'dateStartDate' category='fields' scope='HolidayRequest'}}
                    </label>
                    <div class="field" data-name="dateStartField"></div>
                </div>
                <div class="col-sm-6 form-group">
                    <label class="control-label" for="managed-holiday-end">
                        {{translate 'dateEndDate' category='fields' scope='HolidayRequest'}}
                    </label>
                    <div class="field" data-name="dateEndField"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label" for="managed-holiday-description">
                    {{translate 'description' category='fields' scope='HolidayRequest'}}
                </label>
                <textarea id="managed-holiday-description" class="form-control"
                    data-name="description" rows="3" maxlength="2000"></textarea>
            </div>
            <p class="help-block">
                {{translate 'managedBookingHelp' category='messages' scope='HolidayRequest'}}
            </p>
        `

        setup() {
            this.headerText = this.translate(
                'Book for Employee',
                'labels',
                'HolidayRequest',
            );
            this.buttonList = [
                {
                    name: 'submit',
                    label: 'Create Pending Request',
                    style: 'primary',
                    onClick: () => this.submit(),
                },
                {name: 'cancel', label: 'Cancel'},
            ];
            this.users = [];
            const today = this.getDateTime().getToday();
            this.dateModel = new Model({
                dateStartDate: today,
                dateEndDate: today,
            });
            this.dateModel.entityType = 'HolidayRequest';
            this.wait(this.loadUsers());
        }

        async loadUsers() {
            const response = await Espo.Ajax.getRequest(
                'HolidayManagement/managedBookingUsers'
            );
            this.users = response.list || [];
        }

        async afterRender() {
            const select = this.$el.find('[data-name="userId"]').empty();

            select.append($('<option>', {
                value: '',
                text: this.translate('Select Employee', 'labels', 'HolidayRequest'),
            }));
            this.users.forEach(user => select.append($('<option>', {
                value: user.id,
                text: `${user.name} (${this.translate(
                    'Available Balance',
                    'labels',
                    'HolidayRequest'
                )}: ${user.balance})`,
            })));

            await Promise.all([
                this.createView(
                    'managedBookingStartDate',
                    'holiday-management:views/holiday-request/fields/start-date',
                    {
                        selector: '[data-name="dateStartField"]',
                        mode: 'edit',
                        model: this.dateModel,
                        name: 'dateStartDate',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()),
                this.createView(
                    'managedBookingEndDate',
                    'views/fields/date',
                    {
                        selector: '[data-name="dateEndField"]',
                        mode: 'edit',
                        model: this.dateModel,
                        name: 'dateEndDate',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()),
            ]);
        }

        async submit() {
            const userId = String(this.$el.find('[data-name="userId"]').val() || '');
            const dateStart = String(
                this.getView('managedBookingStartDate')?.fetch().dateStartDate || ''
            );
            const dateEnd = String(
                this.getView('managedBookingEndDate')?.fetch().dateEndDate || ''
            );
            const description = String(
                this.$el.find('[data-name="description"]').val() || ''
            ).trim();

            if (!userId || !dateStart || !dateEnd) {
                Espo.Ui.warning(this.translate(
                    'managedBookingFieldsRequired',
                    'messages',
                    'HolidayRequest',
                ));
                return;
            }

            this.$el.closest('.modal').find('button').prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'HolidayManagement/managedBookings',
                    {userId, dateStart, dateEnd, description},
                );
                this.trigger('saved', result);
                this.close();
            } catch (error) {
                this.$el.closest('.modal').find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'managedBookingFailed',
                    'messages',
                    'HolidayRequest',
                ));
            }
        }
    };
});
