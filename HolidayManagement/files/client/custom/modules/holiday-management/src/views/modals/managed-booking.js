define(['views/modal', 'model'], (ModalView, Model) => {
    return class extends ModalView {
        scope = 'HolidayRequest'

        backdrop = true

        templateContent = `
            <div class="form-group">
                <label class="control-label">
                    {{translate 'Employee' category='labels' scope='HolidayRequest'}}
                </label>
                <div class="field" data-name="employeeField"></div>
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
                <div class="field" data-name="descriptionField"></div>
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
            const today = this.getDateTime().getToday();
            this.formModel = new Model({
                assignedUserId: null,
                assignedUserName: null,
                dateStartDate: today,
                dateEndDate: today,
                description: '',
            });
            this.formModel.entityType = 'HolidayRequest';
            this.eligibleUsers = [];
            this.wait(this.loadUsers());
        }

        async loadUsers() {
            const response = await Espo.Ajax.getRequest(
                'HolidayManagement/managedBookingUsers'
            );
            this.eligibleUsers = response.list || [];
        }

        async afterRender() {
            await Promise.all([
                this.createView(
                    'managedBookingEmployee',
                    'holiday-management:views/holiday-request/fields/managed-employee',
                    {
                        selector: '[data-name="employeeField"]',
                        mode: 'edit',
                        model: this.formModel,
                        name: 'assignedUser',
                        foreignScope: 'User',
                        readOnlyDisabled: true,
                        createDisabled: true,
                        eligibleUsers: this.eligibleUsers,
                    },
                ).then(view => view.render()),
                this.createView(
                    'managedBookingStartDate',
                    'holiday-management:views/holiday-request/fields/start-date',
                    {
                        selector: '[data-name="dateStartField"]',
                        mode: 'edit',
                        model: this.formModel,
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
                        model: this.formModel,
                        name: 'dateEndDate',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()),
                this.createView(
                    'managedBookingDescription',
                    'views/fields/text',
                    {
                        selector: '[data-name="descriptionField"]',
                        mode: 'edit',
                        model: this.formModel,
                        name: 'description',
                        readOnlyDisabled: true,
                    },
                ).then(view => view.render()),
            ]);
        }

        async submit() {
            const userId = String(
                this.getView('managedBookingEmployee')?.fetch().assignedUserId || ''
            );
            const dateStart = String(
                this.getView('managedBookingStartDate')?.fetch().dateStartDate || ''
            );
            const dateEnd = String(
                this.getView('managedBookingEndDate')?.fetch().dateEndDate || ''
            );
            const description = String(
                this.getView('managedBookingDescription')?.fetch().description || ''
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
