define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <div class="header page-header attendance-page-header">
                <h3>{{translate 'Attendance Overview' category='labels' scope='AttendanceRecord'}}</h3>
                <p class="text-muted attendance-page-intro">
                    {{translate 'Overview Page Guide' category='messages' scope='AttendanceRecord'}}
                </p>
            </div>
            <div class="attendance-overview-page">
                <div class="panel panel-default">
                    <div class="panel-heading attendance-overview-toolbar">
                        <label class="attendance-overview-month-selector">
                            <span>{{translate 'monthDate' category='fields' scope='AttendanceRecord'}}</span>
                            <select class="form-control" data-overview-month-select></select>
                        </label>
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
                    <div class="panel-body attendance-overview-dashboard">
                        <div class="attendance-overview-summary"></div>
                    </div>
                    <div class="panel-body attendance-overview-matrix-heading">
                        <strong>{{translate 'Daily Matrix' category='labels' scope='AttendanceRecord'}}</strong>
                        <span class="text-muted small">{{translate 'Daily Matrix Guide' category='messages' scope='AttendanceRecord'}}</span>
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
            'change [data-overview-month-select]': 'actionSelectMonth',
            'click [data-action="send-reminders"]': 'actionSendReminders',
            'click [data-action="download-xlsx"]': 'actionDownloadXlsx',
        }

        setup() {
            this.overview = {users: [], rows: [], missingUsers: []};
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
        }

        renderOverview() {
            const data = this.overview;

            this.renderMonthSelector(data);
            this.renderSummary(data);
            this.renderTable(data.users || [], data.rows || []);
            this.$el.find('[data-action="send-reminders"]')
                .prop('disabled', !(data.missingUsers || []).length || data.monthEditable !== true)
                .attr('title', data.monthEditable === true ? '' : this.translate(
                    'Locked Month Reminder',
                    'messages',
                    'AttendanceRecord'
                ));
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
            const users = data.users || [];
            const missingUsers = data.missingUsers || [];
            const signed = Number(data.signedCount || 0);
            const missing = Number(data.missingCount || 0);
            const elapsed = signed + missing;
            const completion = elapsed ? Math.round((signed / elapsed) * 100) : 0;
            const missingSchedules = users.filter(user => !user.schedule);
            const configuredSchedules = users.length - missingSchedules.length;
            const completionDetails = users.map(user => ({
                name: user.name,
                value: `${Number(user.signedDays || 0)}/${Number(user.signedDays || 0) + Number(user.missingDays || 0)}`,
            }));
            const missingDetails = missingUsers.map(user => ({
                name: user.name,
                value: String(user.missingDays || 0),
            }));
            const scheduleDetails = missingSchedules.map(user => ({name: user.name, value: ''}));
            const readinessReasons = [];

            if (missing > 0) {
                readinessReasons.push(this.formatMessage('Readiness Missing Entries', {count: missing}));
            }
            if (missingSchedules.length > 0) {
                readinessReasons.push(this.formatMessage('Readiness Missing Schedules', {
                    count: missingSchedules.length,
                }));
            }
            if (data.monthEditable !== true) {
                readinessReasons.push(this.translate(
                    'Readiness Month Locked',
                    'messages',
                    'AttendanceRecord'
                ));
            }
            if (!(data.rows || []).length || !users.length || signed === 0) {
                readinessReasons.push(this.translate('Readiness No Data', 'messages', 'AttendanceRecord'));
            }

            summary.append(
                this.createSummaryCard({
                    style: 'success',
                    icon: 'fa-chart-pie',
                    title: 'Completion',
                    value: `${completion}%`,
                    description: this.formatMessage('Completion Detail', {signed, elapsed}),
                    detailTitle: 'Employee Completion',
                    details: completionDetails,
                }),
                this.createSummaryCard({
                    style: missing ? 'danger' : 'success',
                    icon: missing ? 'fa-exclamation-circle' : 'fa-check-circle',
                    title: 'Missing Attendance',
                    value: String(missing),
                    description: missing
                        ? this.formatMessage('Missing Attendance Detail', {
                            entries: missing,
                            employees: missingUsers.length,
                        })
                        : this.translate('Nobody Missing', 'messages', 'AttendanceRecord'),
                    detailTitle: 'Affected Employees',
                    details: missingDetails,
                }),
                this.createSummaryCard({
                    style: missingSchedules.length ? 'warning' : 'success',
                    icon: 'fa-business-time',
                    title: 'Schedule Coverage',
                    value: `${configuredSchedules}/${users.length}`,
                    description: this.formatMessage('Schedule Coverage Detail', {
                        configured: configuredSchedules,
                        total: users.length,
                    }),
                    detailTitle: 'Employees Without Schedule',
                    details: scheduleDetails,
                }),
                this.createSummaryCard({
                    style: data.downloadReady === true ? 'success' : 'warning',
                    icon: data.downloadReady === true ? 'fa-file-excel' : 'fa-lock',
                    title: 'Register Readiness',
                    value: this.translate(
                        data.downloadReady === true ? 'Ready' : 'Needs Attention',
                        'labels',
                        'AttendanceRecord'
                    ),
                    description: this.translate(
                        data.downloadReady === true ? 'Register Ready Detail' : 'Register Blocked Detail',
                        'messages',
                        'AttendanceRecord'
                    ),
                    details: readinessReasons.map(reason => ({name: reason, value: ''})),
                })
            );
        }

        createSummaryCard({style, icon, title, value, description, detailTitle = null, details = []}) {
            const card = $('<section>')
                .addClass(`attendance-summary-card attendance-summary-card-${style}`)
                .append(
                    $('<div>').addClass('attendance-summary-card-heading').append(
                        $('<span>').addClass(`fas ${icon}`).attr('aria-hidden', 'true'),
                        $('<strong>').text(this.translate(title, 'labels', 'AttendanceRecord'))
                    ),
                    $('<div>').addClass('attendance-summary-card-value').text(value),
                    $('<div>').addClass('attendance-summary-card-description text-muted').text(description)
                );

            if (details.length) {
                const detailList = $('<div>').addClass('attendance-summary-detail-list');

                if (detailTitle) {
                    detailList.append(
                        $('<div>').addClass('attendance-summary-detail-title text-muted').text(
                            this.translate(detailTitle, 'labels', 'AttendanceRecord')
                        )
                    );
                }
                details.forEach(item => detailList.append(
                    $('<div>').addClass('attendance-summary-detail').append(
                        $('<span>').text(item.name || ''),
                        item.value ? $('<strong>').text(item.value) : null
                    )
                ));
                card.append(detailList);
            }

            return card;
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

        async actionSelectMonth(event) {
            const select = $(event.currentTarget);
            const month = String(select.val() || '');

            if (!month) {
                return;
            }

            select.prop('disabled', true);

            try {
                await this.loadOverview(month);
                this.renderOverview();
            } catch (error) {
                Espo.Ui.error(this.translate('Attendance Load Failed', 'messages', 'AttendanceRecord'));
                this.renderMonthSelector(this.overview);
            }
        }

        async actionSendReminders() {
            if (!(this.overview.missingUsers || []).length || this.overview.monthEditable !== true) {
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
                button.prop(
                    'disabled',
                    !(this.overview.missingUsers || []).length || this.overview.monthEditable !== true
                );
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

        renderMonthSelector(data) {
            const selectedMonth = String(data.month || data.currentMonth || '');
            const [year, month] = String(data.currentMonth || selectedMonth).split('-').map(Number);
            const language = (this.getPreferences().get('language') ||
                this.getConfig().get('language') || 'en_US').replace('_', '-');
            const select = this.$el.find('[data-overview-month-select]').empty();

            for (let offset = 0; offset < 3; offset++) {
                const date = new Date(Date.UTC(year, month - 1 - offset, 1, 12));
                const value = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
                const label = new Intl.DateTimeFormat(language, {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(date);

                select.append($('<option>', {value, text: label}));
            }

            select.val(selectedMonth).prop('disabled', false);
        }

        formatMessage(key, replacements) {
            let message = this.translate(key, 'messages', 'AttendanceRecord');

            Object.entries(replacements).forEach(([name, value]) => {
                message = message.replace(`{${name}}`, String(value));
            });

            return message;
        }

        displayDate(value) {
            return value ? this.getDateTime().toDisplayDate(String(value)) : '';
        }
    };
});
