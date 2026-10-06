define(['views/fields/varchar'], (VarcharFieldView) => {
    return class extends VarcharFieldView {
        editTemplateContent = `
            <div class="attendance-archive-initializer">
                <strong data-role="archive-start-month"></strong>
                <div class="text-muted small">
                    {{translate 'Initialize Attendance Help' category='messages' scope='AttendanceRecord'}}
                </div>
                <button type="button" class="btn btn-default"
                    data-action="initialize-attendance-archive">
                    <span class="fas fa-sync-alt" aria-hidden="true"></span>
                    {{translate 'Initialize Attendance' category='labels' scope='AttendanceRecord'}}
                </button>
            </div>
        `;

        setup() {
            super.setup();
            this.archiveStartMonth = '';
            this.addHandler('click', '[data-action="initialize-attendance-archive"]',
                (event, target) => this.initializeArchive(target));
        }

        afterRender() {
            super.afterRender();
            this.loadStatus();
        }

        async loadStatus() {
            try {
                const settings = await Espo.Ajax.getRequest('AttendanceManagement/settings');

                if (!this.element) {
                    return;
                }

                this.archiveStartMonth = settings.archiveStartMonth || '';
                this.renderStatus();
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Settings Load Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            }
        }

        renderStatus() {
            const text = this.archiveStartMonth
                ? this.translate('Archive Initialized From', 'messages', 'AttendanceRecord')
                    .replace('{month}', this.archiveStartMonth)
                : this.translate('Archive Automatic Detection', 'messages', 'AttendanceRecord');

            this.$el.find('[data-role="archive-start-month"]').text(text);
        }

        async initializeArchive(target) {
            await this.confirm({
                message: this.translate(
                    'Confirm Initialize Attendance',
                    'messages',
                    'AttendanceRecord'
                ),
                confirmText: this.translate(
                    'Initialize Attendance',
                    'labels',
                    'AttendanceRecord'
                ),
            });

            const button = $(target);
            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'AttendanceManagement/settings/initialize'
                );
                this.archiveStartMonth = result.archiveStartMonth || '';
                this.renderStatus();
                Espo.Ui.success(this.translate(
                    'Attendance Initialized',
                    'messages',
                    'AttendanceRecord'
                ));
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Attendance Initialize Failed',
                    'messages',
                    'AttendanceRecord'
                ));
            } finally {
                button.prop('disabled', false);
            }
        }

        fetch() {
            return {};
        }
    };
});
