define(['view', 'model'], (View, Model) => {
    return class extends View {
        templateContent = `
            <div class="header page-header">
                <h3>{{translate 'Attendance Overview' category='labels' scope='AttendanceRecord'}}</h3>
            </div>
            <div class="attendance-overview-page">
                <div class="panel panel-default">
                    <div class="panel-heading attendance-overview-toolbar">
                        <div class="attendance-month-control">
                            <div class="field attendance-month-field" data-month-field></div>
                            <button class="btn btn-default btn-sm" data-action="open-month">
                                {{translate 'Open Month' category='labels' scope='AttendanceRecord'}}
                            </button>
                        </div>
                        <div class="attendance-overview-actions">
                            <button class="btn btn-warning" data-action="send-reminders">
                                <span class="fas fa-bell" aria-hidden="true"></span>
                                {{translate 'Send Reminders' category='labels' scope='AttendanceRecord'}}
                            </button>
                            <button class="btn btn-primary" data-action="download-xlsx">
                                <span class="fas fa-file-excel" aria-hidden="true"></span>
                                {{translate 'Download XLSX' category='labels' scope='AttendanceRecord'}}
                            </button>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="attendance-overview-summary"></div>
                        <div class="attendance-missing-users"></div>
                    </div>
                    <div class="table-responsive attendance-overview-table-wrap">
                        <table class="table table-bordered table-condensed attendance-overview-table">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        `

        events = {
            'click [data-action="open-month"]': 'actionOpenMonth',
            'click [data-action="send-reminders"]': 'actionSendReminders',
            'click [data-action="download-xlsx"]': 'actionDownloadXlsx',
        }

        setup() {
            this.overview = {users: [], rows: [], missingUsers: []};
            this.monthModel = new Model();
            this.monthModel.entityType = 'AttendanceRecord';
            this.wait(this.loadOverview());
        }

        async loadOverview(month = null) {
            const url = month
                ? `AttendanceManagement/overview?month=${encodeURIComponent(month)}`
                : 'AttendanceManagement/overview';

            this.overview = await Espo.Ajax.getRequest(url);
        }

        afterRender() {
            this.renderOverview();
            this.renderMonthField();
        }

        renderOverview() {
            const data = this.overview;

            this.monthModel.set('monthDate', data.month ? `${data.month}-01` : null);
            this.renderSummary(data);
            this.renderMissingUsers(data.missingUsers || []);
            this.renderTable(data.users || [], data.rows || []);
            this.$el.find('[data-action="send-reminders"]')
                .prop('disabled', !(data.missingUsers || []).length);
            this.$el.find('[data-action="download-xlsx"]')
                .prop('disabled', data.downloadReady !== true)
                .attr('title', data.downloadReady === true ? '' : this.translate(
                    'Download Not Ready',
                    'messages',
                    'AttendanceRecord'
                ));
        }

        renderSummary(data) {
            const summary = this.$el.find('.attendance-overview-summary').empty();

            for (const item of [
                ['Signed Entries', data.signedCount || 0, 'success'],
                ['Missing Entries', data.missingCount || 0, 'danger'],
                ['Future Entries', data.futureCount || 0, 'default'],
                ['Missing Schedules', data.scheduleMissingCount || 0, 'warning'],
            ]) {
                summary.append(
                    $('<div>').addClass(`attendance-summary-card attendance-summary-card-${item[2]}`).append(
                        $('<strong>').text(String(item[1])),
                        $('<span>').text(this.translate(item[0], 'labels', 'AttendanceRecord')),
                    )
                );
            }
        }

        renderMissingUsers(users) {
            const container = this.$el.find('.attendance-missing-users').empty();

            if (!users.length) {
                container.append(
                    $('<span>').addClass('text-success').text(
                        this.translate('Nobody Missing', 'messages', 'AttendanceRecord')
                    )
                );

                return;
            }

            container.append(
                $('<strong>').text(this.translate(
                    'Employees Missing Signatures',
                    'labels',
                    'AttendanceRecord'
                ) + ': ')
            );
            users.forEach((user, index) => {
                if (index) {
                    container.append(', ');
                }

                container.append(
                    $('<span>').text(`${user.name} (${user.missingDays})`)
                );
            });
        }

        renderTable(users, rows) {
            const head = this.$el.find('.attendance-overview-table thead').empty();
            const body = this.$el.find('.attendance-overview-table tbody').empty();
            const header = $('<tr>').append(
                $('<th>').addClass('attendance-sticky-date').text(
                    this.translate('date', 'fields', 'AttendanceRecord')
                )
            );

            users.forEach(user => header.append(this.createUserHeader(user)));
            head.append(header);

            rows.forEach(row => {
                const tableRow = $('<tr>').toggleClass('attendance-future-row', row.isFuture === true);

                tableRow.append(
                    $('<th>').addClass('attendance-sticky-date').text(this.displayDate(row.date))
                );
                (row.cells || []).forEach(cell => tableRow.append(this.createOverviewCell(cell)));
                body.append(tableRow);
            });
        }

        createUserHeader(user) {
            const schedule = user.schedule || {};
            const scheduleLabel = schedule.startTime && schedule.endTime
                ? `${schedule.startTime}–${schedule.endTime}`
                : this.translate('Schedule Not Set', 'labels', 'AttendanceRecord');

            return $('<th>').append(
                $('<div>').addClass('attendance-employee-name').text(user.name || ''),
                $('<div>').addClass('attendance-schedule-value').text(scheduleLabel)
            );
        }

        createOverviewCell(cell) {
            if (cell.isFuture) {
                return $('<td>').addClass('attendance-cell-future').text('—');
            }

            if (cell.isMissing) {
                return $('<td>').addClass('attendance-cell-missing').text(
                    this.translate('Not Marked', 'labels', 'AttendanceRecord')
                );
            }

            const status = cell.status || '';

            return $('<td>')
                .addClass(`attendance-cell attendance-cell-${status}`)
                .text(this.translate(status, 'options', 'AttendanceRecord', 'status'));
        }

        async actionOpenMonth() {
            const field = this.getView('attendanceOverviewMonth');
            const date = field ? field.fetch().monthDate : null;
            const month = typeof date === 'string' ? date.slice(0, 7) : '';

            if (!month) {
                return;
            }

            try {
                await this.loadOverview(month);
                this.renderOverview();
            } catch (error) {
                Espo.Ui.error(this.translate('Attendance Load Failed', 'messages', 'AttendanceRecord'));
            }
        }

        async actionSendReminders() {
            if (!(this.overview.missingUsers || []).length) {
                return;
            }

            await this.confirm({
                message: this.translate('Confirm Reminders', 'messages', 'AttendanceRecord'),
                confirmText: this.translate('Send Reminders', 'labels', 'AttendanceRecord'),
            });
            const button = this.$el.find('[data-action="send-reminders"]');

            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'AttendanceManagement/overview/remind',
                    {month: this.overview.month}
                );
                Espo.Ui.success(this.translate(
                    'Reminders Sent',
                    'messages',
                    'AttendanceRecord'
                ).replace('{count}', String(result.sent || 0)));
            } catch (error) {
                Espo.Ui.error(this.translate('Reminder Failed', 'messages', 'AttendanceRecord'));
            } finally {
                button.prop('disabled', !(this.overview.missingUsers || []).length);
            }
        }

        async actionDownloadXlsx() {
            if (this.overview.downloadReady !== true) {
                return;
            }

            const button = this.$el.find('[data-action="download-xlsx"]');
            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'AttendanceManagement/overview/xlsx',
                    {month: this.overview.month}
                );
                const binary = atob(result.content || '');
                const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
                const url = URL.createObjectURL(new Blob([bytes], {type: result.mediaType}));
                const link = document.createElement('a');

                link.href = url;
                link.download = result.filename || 'condica-de-prezenta.xlsx';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            } catch (error) {
                Espo.Ui.error(this.translate('Download Failed', 'messages', 'AttendanceRecord'));
            } finally {
                button.prop('disabled', this.overview.downloadReady !== true);
            }
        }

        async renderMonthField() {
            const view = await this.createView(
                'attendanceOverviewMonth',
                'views/fields/date',
                {
                    selector: '[data-month-field]',
                    mode: 'edit',
                    model: this.monthModel,
                    name: 'monthDate',
                    readOnlyDisabled: true,
                },
            );

            await view.render();
        }

        displayDate(value) {
            return value ? this.getDateTime().toDisplayDate(String(value)) : '';
        }
    };
});
