define(['views/modal', 'model'], (ModalView, Model) => {
    return class extends ModalView {
        scope = 'EmployeeHolidayRequest'

        backdrop = true

        events = {
            'change [data-name="dateStartField"] input': 'scheduleCalculation',
            'change [data-name="dateEndField"] input': 'scheduleCalculation',
        }

        templateContent = `
            <div class="form-group">
                <label class="control-label">
                    {{translate 'Employee' category='labels' scope='EmployeeHolidayRequest'}}
                </label>
                <div class="field" data-name="employeeField"></div>
            </div>
            <div class="row">
                <div class="col-sm-6 form-group">
                    <label class="control-label" for="managed-holiday-start">
                        {{translate 'dateStartDate' category='fields' scope='EmployeeHolidayRequest'}}
                    </label>
                    <div class="field" data-name="dateStartField"></div>
                </div>
                <div class="col-sm-6 form-group">
                    <label class="control-label" for="managed-holiday-end">
                        {{translate 'dateEndDate' category='fields' scope='EmployeeHolidayRequest'}}
                    </label>
                    <div class="field" data-name="dateEndField"></div>
                </div>
            </div>
            <div class="employee-holiday-live-calculation is-idle" data-name="daysCalculation" aria-live="polite">
                <span class="employee-holiday-live-calculation-icon fas fa-calculator" aria-hidden="true"></span>
                <div>
                    <span>{{translate 'Calculated Working Days' category='labels' scope='EmployeeHolidayRequest'}}</span>
                    <small data-name="daysCalculationMessage">{{translate 'holidayDaysCalculationWaiting' category='messages' scope='EmployeeHolidayRequest'}}</small>
                </div>
                <strong data-name="calculatedDays">—</strong>
            </div>
            <div class="form-group">
                <label class="control-label" for="managed-holiday-description">
                    {{translate 'description' category='fields' scope='EmployeeHolidayRequest'}}
                </label>
                <div class="field" data-name="descriptionField"></div>
            </div>
            <p class="help-block">
                {{translate 'managedBookingHelp' category='messages' scope='EmployeeHolidayRequest'}}
            </p>
        `

        setup() {
            this.headerText = this.translate(
                'Book for Employee',
                'labels',
                'EmployeeHolidayRequest',
            );
            this.buttonList = [
                {
                    name: 'submit',
                    label: this.options.record ? 'Save' : 'Create Pending Request',
                    style: 'primary',
                    onClick: () => this.submit(),
                },
                {name: 'cancel', label: 'Cancel'},
            ];
            this.operationKey = `booking:${Date.now()}:${Math.random().toString(16).slice(2)}`;
            const today = this.getDateTime().getToday();
            this.formModel = new Model({
                assignedUserId: this.options.managed ? null : this.getUser().id,
                assignedUserName: this.options.managed ? null : this.getUser().get('name'),
                dateStartDate: today,
                dateEndDate: today,
                description: '',
            });
            this.formModel.entityType = 'EmployeeHolidayRequest';
            if (this.options.record) { this.formModel.set({...this.options.record}); }
            if (!this.options.managed) { this.headerText = this.translate('EmployeeHolidayRequest', 'scopeNames'); }
            this.eligibleUsers = [];
            this.calculationSequence = 0;
            this.listenTo(
                this.formModel,
                'change:assignedUserId change:dateStartDate change:dateEndDate',
                () => this.scheduleCalculation(),
            );
            this.on('remove', () => clearTimeout(this.calculationTimer));
            this.wait(this.loadUsers());
        }

        async loadUsers() {
            if (!this.options.managed) { this.eligibleUsers = [{id:this.getUser().id, name:this.getUser().get('name')}]; return; }
            const response = await Espo.Ajax.getRequest(
                'EmployeeManagement/holiday/employees'
            );
            this.eligibleUsers = response.list || [];
        }

        async afterRender() {
            await Promise.all([
                this.createView(
                    'managedBookingEmployee',
                    'employee-management:views/holiday-request/fields/managed-employee',
                    {
                        selector: '[data-name="employeeField"]',
                        mode: 'edit',
                        model: this.formModel,
                        name: 'assignedUser',
                        foreignScope: 'User',
                        readOnlyDisabled: true,
                        readOnly: !this.options.managed,
                        createDisabled: true,
                        eligibleUsers: this.eligibleUsers,
                    },
                ).then(view => view.render()),
                this.createView(
                    'managedBookingStartDate',
                    this.options.managed ? 'views/fields/date' : 'employee-management:views/holiday-request/fields/start-date',
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

            this.scheduleCalculation();
        }

        scheduleCalculation() {
            clearTimeout(this.calculationTimer);
            this.calculationTimer = setTimeout(() => this.calculateDays(), 250);
        }

        bookingValues() {
            return {
                userId: String(
                    this.getView('managedBookingEmployee')?.fetch().assignedUserId || ''
                ),
                dateStart: String(
                    this.getView('managedBookingStartDate')?.fetch().dateStartDate || ''
                ),
                dateEnd: String(
                    this.getView('managedBookingEndDate')?.fetch().dateEndDate || ''
                ),
                description: String(
                    this.getView('managedBookingDescription')?.fetch().description || ''
                ).trim(),
            };
        }

        async calculateDays() {
            const {userId, dateStart, dateEnd} = this.bookingValues();

            if (!userId || !dateStart || !dateEnd) {
                this.showCalculation(
                    'idle',
                    null,
                    this.translate(
                        'holidayDaysCalculationWaiting',
                        'messages',
                        'EmployeeHolidayRequest',
                    ),
                );
                return;
            }

            const sequence = ++this.calculationSequence;
            this.showCalculation(
                'loading',
                null,
                this.translate(
                    'holidayDaysCalculating',
                    'messages',
                    'EmployeeHolidayRequest',
                ),
            );

            const payload = {
                userId,
                managed: this.options.managed === true || userId !== this.getUser().id,
                dateStart,
                dateEnd,
            };

            if (this.options.record?.id) {
                payload.requestId = this.options.record.id;
            }

            try {
                const result = await Espo.Ajax.postRequest(
                    'EmployeeManagement/holiday/calculate',
                    payload,
                );

                if (sequence !== this.calculationSequence) {
                    return;
                }

                this.formModel.set('days', result.days, {silent: true});
                this.showCalculation(
                    'ready',
                    result.days,
                    this.translate(
                        'holidayDaysCalculationReady',
                        'messages',
                        'EmployeeHolidayRequest',
                    ),
                );
            } catch (error) {
                if (sequence !== this.calculationSequence) {
                    return;
                }

                this.showCalculation(
                    'error',
                    null,
                    this.translate(
                        'holidayDaysCalculationFailed',
                        'messages',
                        'EmployeeHolidayRequest',
                    ),
                );
            }
        }

        showCalculation(state, days, message) {
            const box = this.$el.find('[data-name="daysCalculation"]');
            const icon = box.find('.employee-holiday-live-calculation-icon');

            box.removeClass('is-idle is-loading is-ready is-error').addClass(`is-${state}`);
            icon.toggleClass('fa-calculator', state !== 'loading' && state !== 'error');
            icon.toggleClass('fa-spinner fa-spin', state === 'loading');
            icon.toggleClass('fa-exclamation-circle', state === 'error');
            box.find('[data-name="calculatedDays"]').text(days === null ? '—' : String(days));
            box.find('[data-name="daysCalculationMessage"]').text(message);
        }

        async submit() {
            const {userId, dateStart, dateEnd, description} = this.bookingValues();

            if (!userId || !dateStart || !dateEnd) {
                Espo.Ui.warning(this.translate(
                    'managedBookingFieldsRequired',
                    'messages',
                    'EmployeeHolidayRequest',
                ));
                return;
            }

            this.$el.closest('.modal').find('button').prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    this.options.record ? `EmployeeManagement/holiday/${this.options.record.id}/update` : 'EmployeeManagement/holiday/create',
                    this.options.record ? {dateStart, dateEnd, description, revision:this.options.record.accountingRevision, idempotencyKey:this.operationKey}
                        : {userId, managed:!!this.options.managed, dateStart, dateEnd, description, idempotencyKey:this.operationKey},
                );
                this.trigger('saved', result);
                this.close();
            } catch (error) {
                this.$el.closest('.modal').find('button').prop('disabled', false);
                Espo.Ui.error(this.translate(
                    'managedBookingFailed',
                    'messages',
                    'EmployeeHolidayRequest',
                ));
            }
        }
    };
});
