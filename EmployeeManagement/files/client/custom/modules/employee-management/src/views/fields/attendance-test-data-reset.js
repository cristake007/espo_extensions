define(['views/fields/varchar'], VarcharFieldView => class extends VarcharFieldView {
    editTemplateContent = `
        <div class="employee-attendance-test-data-reset">
            <div class="alert alert-danger">
                <strong>{{translate 'Reset Attendance Test Data' category='labels' scope='EmployeeAdministration'}}</strong>
                <div>{{translate 'attendanceTestDataResetWarning' category='messages' scope='EmployeeAdministration'}}</div>
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
                    autocomplete="off"
                    placeholder="{{translate 'Attendance Reset Confirmation Phrase' category='labels' scope='EmployeeAdministration'}}">
            </div>
            <button type="button" class="btn btn-danger" data-action="resetAttendanceTestData" disabled>
                <span class="fas fa-trash-alt" aria-hidden="true"></span>
                {{translate 'Reset Attendance Test Data' category='labels' scope='EmployeeAdministration'}}
            </button>
        </div>
    `;

    setup() {
        super.setup();
        this.preview = null;
        this.resetInProgress = false;
        this.addHandler('input', '[data-role="reset-confirmation"]', () => this.updateButtonState());
        this.addHandler(
            'click',
            '[data-action="resetAttendanceTestData"]',
            (event, target) => this.resetData(target),
        );
    }

    afterRender() {
        super.afterRender();
        this.loadPreview();
    }

    async loadPreview() {
        try {
            this.preview = await Espo.Ajax.getRequest(
                'EmployeeManagement/administration/attendance/testDataResetPreview'
            );
            if (!this.element) {
                return;
            }

            const preview = $('<dl>').addClass('dl-horizontal').append(
                this.previewRow('Attendance Records', this.preview.attendanceRecords),
                this.previewRow('Attendance Month States', this.preview.monthStates),
                this.previewRow('Frozen Attendance Months', this.preview.frozenMonths),
                this.previewRow(
                    'Attendance Reminder Notifications',
                    this.preview.reminderNotifications,
                ),
                this.previewRow(
                    'Remote / CIC Configurations Preserved',
                    this.preview.workSchedulesPreserved,
                ),
                this.previewRow(
                    'Current Archive Start Month',
                    this.preview.archiveStartMonth || this.translate('Not Set'),
                ),
            );
            this.$el.find('[data-role="reset-preview"]').empty().append(preview);
            this.updateButtonState();
        } catch (error) {
            this.$el.find('[data-role="reset-preview"]')
                .addClass('text-danger')
                .text(this.translate(
                    'attendanceTestDataResetPreviewFailed',
                    'messages',
                    'EmployeeAdministration',
                ));
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
        this.$el.find('[data-action="resetAttendanceTestData"]').prop(
            'disabled',
            this.resetInProgress || !this.preview || confirmation !== 'RESET ATTENDANCE DATA',
        );
    }

    async resetData(target) {
        const confirmation = String(this.$el.find('[data-role="reset-confirmation"]').val() || '');

        if (confirmation !== 'RESET ATTENDANCE DATA') {
            return;
        }

        await this.confirm({
            message: this.translate(
                'confirmAttendanceTestDataReset',
                'messages',
                'EmployeeAdministration',
            ),
            confirmText: this.translate(
                'Reset Attendance Test Data',
                'labels',
                'EmployeeAdministration',
            ),
            confirmStyle: 'danger',
            backdrop: 'static',
        });

        this.resetInProgress = true;
        const button = $(target);
        button.prop('disabled', true);

        try {
            const result = await Espo.Ajax.postRequest(
                'EmployeeManagement/administration/attendance/testDataReset',
                {confirmation},
            );
            Espo.Ui.success(this.translate(
                'attendanceTestDataResetCompleted',
                'messages',
                'EmployeeAdministration',
            ));
            this.resetInProgress = false;
            this.trigger('reset', result);
        } catch (error) {
            this.resetInProgress = false;
            Espo.Ui.error(this.translate(
                'attendanceTestDataResetFailed',
                'messages',
                'EmployeeAdministration',
            ));
            this.updateButtonState();
        }
    }

    fetch() { return {}; }
});
