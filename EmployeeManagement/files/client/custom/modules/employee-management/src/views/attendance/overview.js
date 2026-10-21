define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <main class="attendance-overview-page employee-attendance-overview-page">
                <header class="employee-attendance-overview-hero">
                    <div class="employee-attendance-overview-hero-copy">
                        <div class="employee-attendance-overview-hero-icon" aria-hidden="true">
                            <span class="fas fa-users"></span>
                        </div>
                        <div>
                            <div class="employee-attendance-overview-eyebrow">{{translate 'Team Attendance' category='labels' scope='EmployeeAttendanceRecord'}}</div>
                            <h3>{{translate 'Attendance Overview' category='labels' scope='EmployeeAttendanceRecord'}}</h3>
                            <p>{{translate 'Overview Page Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                        </div>
                    </div>
                    <div class="attendance-overview-period">
                        <label class="attendance-overview-month-selector">
                            <span>{{translate 'Month' category='labels' scope='EmployeeAttendanceRecord'}}</span>
                            <select class="form-control" data-overview-month-select></select>
                        </label>
                        <span class="attendance-overview-month-state" data-role="month-state"></span>
                    </div>
                </header>

                <section class="attendance-overview-command-bar">
                    <div class="attendance-overview-command-copy">
                        <span class="fas fa-bolt" aria-hidden="true"></span>
                        <div>
                            <strong>{{translate 'Overview Controls' category='labels' scope='EmployeeAttendanceRecord'}}</strong>
                            <span>{{translate 'Overview Controls Guide' category='messages' scope='EmployeeAttendanceRecord'}}</span>
                        </div>
                    </div>
                    <div class="attendance-overview-actions">
                        {{#if canManageSchedules}}<a class="btn btn-default" href="#EmployeeAdministration/attendanceMetadata">
                            <span class="fas fa-business-time" aria-hidden="true"></span>
                            {{translate 'Employee Schedules' category='labels' scope='EmployeeAttendanceRecord'}}
                        </a>{{/if}}
                        <button class="btn btn-warning" data-action="send-reminders">
                            <span class="fas fa-bell" aria-hidden="true"></span>
                            {{translate 'Send Reminders' category='labels' scope='EmployeeAttendanceRecord'}}
                        </button>
                        <button class="btn btn-success" data-action="download-xlsx">
                            <span class="fas fa-file-excel" aria-hidden="true"></span>
                            {{translate 'Download XLSX' category='labels' scope='EmployeeAttendanceRecord'}}
                        </button>
                        <button class="btn btn-default" data-action="download-pdf">
                            <span class="fas fa-file-pdf" aria-hidden="true"></span>
                            {{translate 'Download PDF' category='labels' scope='EmployeeAttendanceRecord'}}
                        </button>
                    </div>
                </section>

                <section class="attendance-overview-summary-panel">
                    <div class="attendance-overview-section-heading">
                        <div class="attendance-overview-section-copy">
                            <div class="attendance-overview-section-icon"><span class="fas fa-chart-line" aria-hidden="true"></span></div>
                            <div>
                                <h4>{{translate 'Monthly Status' category='labels' scope='EmployeeAttendanceRecord'}}</h4>
                                <p>{{translate 'Monthly Status Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                            </div>
                        </div>
                    </div>
                    <div class="attendance-overview-dashboard">
                        <div class="attendance-overview-summary"></div>
                    </div>
                </section>

                <section class="attendance-overview-matrix-panel">
                    <div class="attendance-overview-matrix-heading">
                        <div class="attendance-overview-matrix-title">
                            <div class="attendance-overview-section-icon"><span class="fas fa-th" aria-hidden="true"></span></div>
                            <div>
                                <h4>{{translate 'Daily Matrix' category='labels' scope='EmployeeAttendanceRecord'}}</h4>
                                <p>{{translate 'Daily Matrix Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                            </div>
                        </div>
                        <div class="attendance-overview-matrix-count">
                            <span class="fas fa-table" aria-hidden="true"></span>
                            <span data-role="matrix-count"></span>
                        </div>
                    </div>
                    <div class="attendance-overview-legend-bar">
                        <span class="attendance-overview-legend-title">{{translate 'Status Legend' category='labels' scope='EmployeeAttendanceRecord'}}</span>
                        <div class="attendance-overview-legend">
                            <span><i class="attendance-legend-dot attendance-legend-work"></i>{{translateOption 'AtWork' field='status' scope='EmployeeAttendanceRecord'}}</span>
                            <span><i class="attendance-legend-dot attendance-legend-trip"></i>{{translateOption 'BusinessTrip' field='status' scope='EmployeeAttendanceRecord'}}</span>
                            <span><i class="attendance-legend-dot attendance-legend-medical"></i>{{translateOption 'MedicalLeave' field='status' scope='EmployeeAttendanceRecord'}}</span>
                            <span><i class="attendance-legend-dot attendance-legend-leave"></i>{{translateOption 'Holiday' field='status' scope='EmployeeAttendanceRecord'}}</span>
                            <span><i class="attendance-legend-dot attendance-legend-leave"></i>{{translateOption 'ChildCareLeave' field='status' scope='EmployeeAttendanceRecord'}}</span>
                            <span><i class="attendance-legend-dot attendance-legend-missing"></i>{{translate 'Missing' category='labels' scope='EmployeeAttendanceRecord'}}</span>
                        </div>
                    </div>
                    <div class="attendance-overview-empty hidden" data-role="matrix-empty">
                        <span class="far fa-calendar-times" aria-hidden="true"></span>
                        <strong>{{translate 'No Attendance Data' category='labels' scope='EmployeeAttendanceRecord'}}</strong>
                        <p>{{translate 'No Overview Data' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                    </div>
                    <div class="table-responsive attendance-overview-table-wrap">
                        <table class="table table-bordered table-condensed attendance-overview-table">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </section>
            </main>
        `

        events = {
            'change [data-overview-month-select]': 'actionSelectMonth',
            'click [data-action="send-reminders"]': 'actionSendReminders',
            'click [data-action="download-xlsx"]': 'actionDownloadXlsx',
            'click [data-action="download-pdf"]': 'actionDownloadPdf',
            'change [data-action="manager-mark-attendance"]': 'actionManagerMarkAttendance',
        }

        setup() {
            this.overview = {users: [], rows: [], missingUsers: []};
            this.wait(this.loadOverview());
        }

        data() {
            return {canManageSchedules: this.overview.canManageSchedules === true};
        }

        async loadOverview(month = null) {
            const url = month
                ? `EmployeeManagement/attendance/overview?month=${encodeURIComponent(month)}`
                : 'EmployeeManagement/attendance/overview';

            this.overview = await Espo.Ajax.getRequest(url);
        }

        afterRender() {
            this.$el.find('[data-action="send-reminders"]').toggle(this.overview.isManager === true);
            this.renderOverview();
        }

        renderOverview() {
            const data = this.overview;

            this.renderMonthSelector(data);
            this.renderSummary(data);
            this.renderTable(data.users || [], data.rows || []);
            this.renderMonthState(data);
            this.$el.find('[data-action="send-reminders"]')
                .prop('disabled', !(data.missingUsers || []).length || data.monthEditable !== true)
                .attr('title', data.monthEditable === true ? '' : this.translate(
                    'Locked Month Reminder',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            for (const format of ['xlsx', 'pdf']) {
                const allowed = format === 'pdf' ? data.pdfDownloadAllowed : data.exportAllowed;
                const reason = format === 'pdf' ? data.pdfDownloadDisabledReason : data.exportDisabledReason;
                this.$el.find(`[data-action="download-${format}"]`)
                    .prop('disabled', !this.isDownloadAllowed(format))
                    .attr('title', allowed !== true
                        ? this.translate(reason || 'Export Permission Required', 'messages', 'EmployeeAttendanceRecord')
                        : data.downloadReady === true ? '' : this.translate(
                            'Download Not Ready', 'messages', 'EmployeeAttendanceRecord'
                        ));
            }
        }

        renderSummary(data) {
            const summary = this.$el.find('.attendance-overview-summary').empty();
            const users = data.users || [];
            const missingUsers = data.missingUsers || [];
            const signed = Number(data.signedCount || 0);
            const missing = Number(data.missingCount || 0);
            const elapsed = signed + missing + Number(data.futureCount || 0);
            const completion = elapsed ? Math.round((signed / elapsed) * 100) : 0;
            const missingSchedules = users.filter(user => user.normConfigured !== true);
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
            if (!(data.rows || []).length || !users.length || signed === 0) {
                readinessReasons.push(this.translate('Readiness No Data', 'messages', 'EmployeeAttendanceRecord'));
            }

            const registerComplete = data.registerComplete === true;
            const incompleteExport = data.downloadReady === true && !registerComplete;

            summary.append(
                this.createSummaryCard({
                    style: 'primary',
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
                        : this.translate('Nobody Missing', 'messages', 'EmployeeAttendanceRecord'),
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
                    style: registerComplete ? 'success' : 'warning',
                    icon: registerComplete ? 'fa-check-circle' :
                        (incompleteExport ? 'fa-file-download' : 'fa-lock'),
                    title: 'Register Readiness',
                    value: this.translate(
                        registerComplete ? 'Ready' :
                            (incompleteExport ? 'Export Available' : 'Needs Attention'),
                        'labels',
                        'EmployeeAttendanceRecord'
                    ),
                    description: this.translate(
                        registerComplete ? 'Register Ready Detail' :
                            (incompleteExport ? 'Incomplete Export Detail' : 'Register Blocked Detail'),
                        'messages',
                        'EmployeeAttendanceRecord'
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
                        $('<span>').addClass('attendance-summary-card-icon').append(
                            $('<span>').addClass(`fas ${icon}`).attr('aria-hidden', 'true')
                        ),
                        $('<strong>').text(this.translate(title, 'labels', 'EmployeeAttendanceRecord'))
                    ),
                    $('<div>').addClass('attendance-summary-card-value').text(value),
                    $('<div>').addClass('attendance-summary-card-description text-muted').text(description)
                );

            if (details.length) {
                const detailList = $('<div>').addClass('attendance-summary-detail-list');
                details.forEach(item => detailList.append(
                    $('<div>').addClass('attendance-summary-detail').append(
                        $('<span>').text(item.name || ''),
                        item.value ? $('<strong>').text(item.value) : null
                    )
                ));
                card.append(
                    $('<details>').addClass('attendance-summary-details').append(
                        $('<summary>').append(
                            $('<span>').text(this.translate(
                                detailTitle || 'Details',
                                'labels',
                                'EmployeeAttendanceRecord'
                            )),
                            $('<span>').addClass('attendance-summary-details-count').text(String(details.length))
                        ),
                        detailList
                    )
                );
            }

            return card;
        }

        renderTable(users, rows) {
            const head = this.$el.find('.attendance-overview-table thead').empty();
            const body = this.$el.find('.attendance-overview-table tbody').empty();
            const isEmpty = !users.length || !rows.length;
            this.$el.find('[data-role="matrix-empty"]').toggleClass('hidden', !isEmpty);
            this.$el.find('.attendance-overview-table-wrap').toggleClass('hidden', isEmpty);
            this.$el.find('[data-role="matrix-count"]').text(this.formatMessage(
                'Overview Matrix Count',
                {employees: users.length, days: rows.length}
            ));

            if (isEmpty) {
                return;
            }

            const header = $('<tr>').append(
                $('<th>').addClass('attendance-sticky-date').text(
                    this.translate('date', 'fields', 'EmployeeAttendanceRecord')
                )
            );

            users.forEach(user => header.append(this.createUserHeader(user)));
            head.append(header);

            rows.forEach(row => {
                const tableRow = $('<tr>')
                    .toggleClass('attendance-future-row', row.isFuture === true)
                    .toggleClass('attendance-overview-current-row', row.date === this.getDateTime().getToday());

                tableRow.append(
                    $('<th>').addClass('attendance-sticky-date attendance-overview-row-date').append(
                        $('<strong>').text(this.displayDate(row.date)),
                        $('<span>').text(this.displayWeekday(row.date))
                    )
                );
                (row.cells || []).forEach(cell => tableRow.append(this.createOverviewCell(cell)));
                body.append(tableRow);
            });
        }

        createUserHeader(user) {
            const schedule = user.schedule || {};
            const patterns = user.schedulePatterns || [];
            const formatRanges = ranges => ranges.map(([start, end]) => `${start}–${end}`).join(' / ');
            const scheduleLabel = patterns.length
                ? patterns.map(pattern => formatRanges(pattern.ranges)).join(' · ') + (schedule.remote === true ? ' (Remote)' : '')
                : this.translate('Schedule Not Set', 'labels', 'EmployeeAttendanceRecord');
            const scheduleDetail = patterns.map(pattern => `${pattern.dates.join(', ')}: ${formatRanges(pattern.ranges)}`).join('\n');

            return $('<th>').append(
                $('<div>').addClass('attendance-employee-name').text(user.name || ''),
                $('<div>').addClass('attendance-schedule-value').text(scheduleLabel).attr('title', scheduleDetail)
            );
        }

        createOverviewCell(cell) {
            if (cell.isWorkingDay === false || (cell.isFuture && !cell.status)) {
                return $('<td>').addClass('attendance-cell-future').text('—');
            }

            const status = cell.status || '';
            const cellElement = $('<td>').addClass(
                status ? `attendance-cell attendance-cell-${status}` : 'attendance-cell-missing'
            ).attr('data-status', status || 'Missing');

            if (!cell.canManage || cell.source === 'ApprovedHoliday') {
                return cellElement.append(
                    $('<span>').addClass('attendance-overview-cell-label').text(status
                        ? this.getLanguage().translateOption(status, 'status', 'EmployeeAttendanceRecord')
                        : this.translate('Not Marked', 'labels', 'EmployeeAttendanceRecord'))
                );
            }

            const select = $('<select>')
                .addClass('form-control input-sm attendance-manager-status-select')
                .attr({
                    'data-action': 'manager-mark-attendance',
                    'data-user-id': cell.userId || '',
                    'data-date': cell.date || '',
                    'data-revision': cell.revision || 0,
                    'aria-label': this.translate(
                        'Manual Attendance',
                        'labels',
                        'EmployeeAttendanceRecord'
                    ),
                });

            select.append($('<option>', {
                value: '',
                text: this.translate('Not Marked', 'labels', 'EmployeeAttendanceRecord'),
                disabled: true,
            }));
            ['AtWork', 'BusinessTrip', 'MedicalLeave'].filter(s => !cell.cicActive || s === 'MedicalLeave').forEach(option => select.append(
                $('<option>', {
                    value: option,
                    text: this.getLanguage().translateOption(
                        option,
                        'status',
                        'EmployeeAttendanceRecord'
                    ),
                })
            ));
            select.val(status);

            return cellElement.append(select);
        }

        renderMonthState(data) {
            const editable = data.monthEditable === true;
            const state = this.$el.find('[data-role="month-state"]')
                .removeClass('attendance-overview-month-state-editable attendance-overview-month-state-locked')
                .addClass(editable
                    ? 'attendance-overview-month-state-editable'
                    : 'attendance-overview-month-state-locked')
                .empty();

            state.append(
                $('<span>').addClass(`fas ${editable ? 'fa-pen' : 'fa-lock'}`).attr('aria-hidden', 'true'),
                $('<span>').text(this.translate(
                    editable ? 'Editable Month' : 'Read-only Month',
                    'labels',
                    'EmployeeAttendanceRecord'
                ))
            );
        }

        async actionManagerMarkAttendance(event) {
            const select = $(event.currentTarget);
            const status = String(select.val() || '');
            const userId = String(select.attr('data-user-id') || '');
            const date = String(select.attr('data-date') || '');

            if (!status || !userId || !date) {
                return;
            }

            select.prop('disabled', true);
            const reason = await new Promise(resolve => this.createView('attendanceReason', 'employee-management:views/modals/attendance-correction', {}).then(modal => {
                this.listenToOnce(modal, 'submit', resolve); this.listenToOnce(modal, 'close', () => resolve(null)); modal.render();
            }));
            if (!reason) { select.prop('disabled', false); return; }
            try {
                await Espo.Ajax.postRequest('EmployeeManagement/attendance/managerMark', {
                    userId,
                    date,
                    status, reason, revision:Number(select.attr('data-revision')),
                });
                Espo.Ui.success(this.translate(
                    'Manual Attendance Saved',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
                await this.loadOverview(this.overview.month);
                this.renderOverview();
            } catch (error) {
                Espo.Ui.error(this.translate(
                    'Manual Attendance Save Failed',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
                this.renderOverview();
            }
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
                Espo.Ui.error(this.translate('Attendance Load Failed', 'messages', 'EmployeeAttendanceRecord'));
                this.renderMonthSelector(this.overview);
            }
        }

        async actionSendReminders() {
            if (!(this.overview.missingUsers || []).length || this.overview.monthEditable !== true) {
                return;
            }

            await this.confirm({
                message: this.translate('Confirm Reminders', 'messages', 'EmployeeAttendanceRecord'),
                confirmText: this.translate('Send Reminders', 'labels', 'EmployeeAttendanceRecord'),
            });
            const button = this.$el.find('[data-action="send-reminders"]');

            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    'EmployeeManagement/attendance/remind',
                    {month: this.overview.month}
                );
                Espo.Ui.success(this.translate(
                    'Reminders Sent',
                    'messages',
                    'EmployeeAttendanceRecord'
                ).replace('{count}', String(result.sent || 0)));
            } catch (error) {
                Espo.Ui.error(this.translate('Reminder Failed', 'messages', 'EmployeeAttendanceRecord'));
            } finally {
                button.prop(
                    'disabled',
                    !(this.overview.missingUsers || []).length || this.overview.monthEditable !== true
                );
            }
        }

        async actionDownloadXlsx() {
            await this.downloadFile('xlsx');
        }

        async actionDownloadPdf() {
            await this.downloadFile('pdf');
        }

        isDownloadAllowed(format) {
            const allowed = format === 'pdf' ? this.overview.pdfDownloadAllowed : this.overview.exportAllowed;
            return this.overview.downloadReady === true && allowed === true;
        }

        async downloadFile(format) {
            if (!this.isDownloadAllowed(format)) {
                return;
            }

            const button = this.$el.find(`[data-action="download-${format}"]`);
            button.prop('disabled', true);

            try {
                const result = await Espo.Ajax.postRequest(
                    `EmployeeManagement/attendance/${format}`,
                    {month: this.overview.month}
                );
                const binary = atob(result.content || '');
                const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
                const url = URL.createObjectURL(new Blob([bytes], {type: result.mediaType}));
                const link = document.createElement('a');

                link.href = url;
                link.download = result.filename || `condica-de-prezenta.${format}`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            } catch (error) {
                Espo.Ui.error(this.translate('Download Failed', 'messages', 'EmployeeAttendanceRecord'));
            } finally {
                button.prop('disabled', !this.isDownloadAllowed(format));
            }
        }

        renderMonthSelector(data) {
            const selectedMonth = String(data.month || data.currentMonth || '');
            const currentMonth = String(data.currentMonth || selectedMonth);
            const availableFromMonth = String(data.availableFromMonth || currentMonth);
            const [year, month] = currentMonth.split('-').map(Number);
            const language = (this.getPreferences().get('language') ||
                this.getConfig().get('language') || 'en_US').replace('_', '-');
            const select = this.$el.find('[data-overview-month-select]').empty();

            let date = new Date(Date.UTC(year, month - 1, 1, 12));

            while (true) {
                const value = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;

                if (value < availableFromMonth) {
                    break;
                }

                const label = new Intl.DateTimeFormat(language, {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(date);

                select.append($('<option>', {value, text: label}));
                date = new Date(Date.UTC(
                    date.getUTCFullYear(),
                    date.getUTCMonth() - 1,
                    1,
                    12
                ));
            }

            select.val(selectedMonth).prop('disabled', false);
        }

        formatMessage(key, replacements) {
            let message = this.translate(key, 'messages', 'EmployeeAttendanceRecord');

            Object.entries(replacements).forEach(([name, value]) => {
                message = message.replace(`{${name}}`, String(value));
            });

            return message;
        }

        displayDate(value) {
            return value ? this.getDateTime().toDisplayDate(String(value)) : '';
        }

        displayWeekday(value) {
            if (!value) {
                return '';
            }

            const language = this.getPreferences().get('language') ||
                this.getConfig().get('language') || 'en_US';

            return new Intl.DateTimeFormat(language.replace('_', '-'), {weekday: 'short'})
                .format(new Date(`${value}T12:00:00`));
        }
    };
});
