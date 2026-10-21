define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <main class="attendance-page employee-attendance-page">
                <header class="employee-attendance-hero">
                    <div class="employee-attendance-hero-icon" aria-hidden="true">
                        <span class="fas fa-clipboard-check"></span>
                    </div>
                    <div>
                        <div class="employee-attendance-eyebrow">{{translate 'My Attendance' category='labels' scope='EmployeeAttendanceRecord'}}</div>
                        <h3>{{translate 'Attendance' category='labels' scope='EmployeeAttendanceRecord'}}</h3>
                        <p>{{translate 'Attendance Page Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                    </div>
                </header>

                <section class="attendance-today-panel">
                    <div class="attendance-today-heading">
                        <div class="attendance-section-heading-copy">
                            <div class="attendance-section-icon attendance-section-icon-today">
                                <span class="far fa-calendar-check" aria-hidden="true"></span>
                            </div>
                            <div>
                                <h4>{{translate 'Today Attendance' category='labels' scope='EmployeeAttendanceRecord'}}</h4>
                                <p>{{translate 'Today Attendance Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                            </div>
                        </div>
                        <div class="attendance-today-date"></div>
                    </div>
                    <div class="attendance-today-body">
                        <div class="attendance-today-summary">
                            <span class="attendance-summary-label">{{translate 'Current Status' category='labels' scope='EmployeeAttendanceRecord'}}</span>
                            <div class="attendance-current-status"></div>
                            <p class="attendance-today-guidance"></p>
                        </div>
                        <div class="attendance-today-action-column">
                            <div class="attendance-today-action-heading">
                                <span class="fas fa-hand-pointer" aria-hidden="true"></span>
                                <strong>{{translate 'Choose Attendance' category='labels' scope='EmployeeAttendanceRecord'}}</strong>
                            </div>
                            <div class="attendance-state-actions" aria-label="{{translate 'Choose Attendance' category='labels' scope='EmployeeAttendanceRecord'}}">
                            <button class="btn attendance-action-card attendance-action-work" data-action="mark-attendance" data-status="AtWork">
                                <span class="attendance-action-icon fas fa-building" aria-hidden="true"></span>
                                <span class="attendance-action-copy">
                                    <strong>{{translateOption 'AtWork' field='status' scope='EmployeeAttendanceRecord'}}</strong>
                                    <small>{{translate 'At Work Help' category='messages' scope='EmployeeAttendanceRecord'}}</small>
                                </span>
                                <span class="attendance-action-check fas fa-check-circle" aria-hidden="true"></span>
                            </button>
                            <button class="btn attendance-action-card attendance-action-trip" data-action="mark-attendance" data-status="BusinessTrip">
                                <span class="attendance-action-icon fas fa-car" aria-hidden="true"></span>
                                <span class="attendance-action-copy">
                                    <strong>{{translateOption 'BusinessTrip' field='status' scope='EmployeeAttendanceRecord'}}</strong>
                                    <small>{{translate 'Business Trip Help' category='messages' scope='EmployeeAttendanceRecord'}}</small>
                                </span>
                                <span class="attendance-action-check fas fa-check-circle" aria-hidden="true"></span>
                            </button>
                            <button class="btn attendance-action-card attendance-action-medical" data-action="mark-attendance" data-status="MedicalLeave">
                                <span class="attendance-action-icon fas fa-briefcase-medical" aria-hidden="true"></span>
                                <span class="attendance-action-copy">
                                    <strong>{{translateOption 'MedicalLeave' field='status' scope='EmployeeAttendanceRecord'}}</strong>
                                    <small>{{translate 'Medical Leave Help' category='messages' scope='EmployeeAttendanceRecord'}}</small>
                                </span>
                                <span class="attendance-action-check fas fa-check-circle" aria-hidden="true"></span>
                            </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="attendance-month-panel">
                    <div class="attendance-month-heading">
                        <div class="attendance-month-title">
                            <div class="attendance-section-icon">
                                <span class="far fa-calendar-alt" aria-hidden="true"></span>
                            </div>
                            <div>
                                <h4>{{translate 'Monthly Register' category='labels' scope='EmployeeAttendanceRecord'}}</h4>
                                <p>{{translate 'Monthly Register Guide' category='messages' scope='EmployeeAttendanceRecord'}}</p>
                            </div>
                        </div>
                        <label class="attendance-month-selector">
                            <span>{{translate 'Month' category='labels' scope='EmployeeAttendanceRecord'}}</span>
                            <select class="form-control" data-month-select></select>
                        </label>
                    </div>
                    <div class="attendance-month-lock-message hidden">
                        <span class="fas fa-lock" aria-hidden="true"></span>
                        <span data-role="month-lock-copy"></span>
                    </div>
                    <div class="attendance-month-summary"></div>
                    <div class="table-responsive attendance-register-wrap">
                        <table class="table attendance-my-table">
                            <thead>
                                <tr>
                                    <th>{{translate 'date' category='fields' scope='EmployeeAttendanceRecord'}}</th>
                                    <th>{{translate 'status' category='fields' scope='EmployeeAttendanceRecord'}}</th>
                                    <th>{{translate 'Choose Attendance' category='labels' scope='EmployeeAttendanceRecord'}}</th>
                                </tr>
                            </thead>
                            <tbody class="attendance-day-list"></tbody>
                        </table>
                    </div>
                </section>
            </main>
        `

        events = {
            'click [data-action="mark-attendance"]': 'actionMarkAttendance',
            'change [data-month-select]': 'actionSelectMonth',
        }

        setup() {
            this.attendance = {days: []};
            this.wait(this.loadAttendance(this.options.month || null));
        }

        async loadAttendance(month = null) {
            const url = month
                ? `EmployeeManagement/attendance/myAttendance?month=${encodeURIComponent(month)}`
                : 'EmployeeManagement/attendance/myAttendance';

            this.attendance = await Espo.Ajax.getRequest(url);
        }

        afterRender() {
            this.renderAttendance();
        }

        renderAttendance() {
            const data = this.attendance;

            this.renderMonthSelector(data);
            this.$el.find('.attendance-today-date').text(this.displayDate(data.today));
            this.renderToday(data);
            if (data.normConfigured === false) { this.$el.find('.attendance-today-guidance').text(this.translate('Schedule Not Set','labels','EmployeeAttendanceRecord')); }
            this.renderMonthSummary(data.days || [], data.today);
            this.renderDays(data.days || [], data.today);
        }

        renderToday(data) {
            const status = data.todayStatus || null;
            const actions = this.$el.find('.attendance-state-actions');

            actions.find('[data-action="mark-attendance"]')
                .attr('data-date', data.today)
                .attr('aria-pressed', 'false')
                .prop('disabled', !data.todayCanMark)
                .removeClass('attendance-state-active');
            actions.find('[data-status="MedicalLeave"]').prop('disabled', !data.todayCanMarkMedical);
            if (status && !['Holiday', 'ChildCareLeave'].includes(status)) {
                actions.find(`[data-status="${status}"]`)
                    .addClass('attendance-state-active')
                    .attr('aria-pressed', 'true');
            }

            this.$el.find('.attendance-current-status')
                .empty()
                .append(this.createStatusBadge(status, data.todaySource, true));
            this.$el.find('.attendance-today-guidance').text(this.translate(
                data.todayCanMark
                    ? 'Today Marking Guide'
                    : (status === 'Holiday'
                        ? 'Holiday Locked Guide'
                        : (status === 'ChildCareLeave'
                            ? 'CIC Locked Guide'
                            : 'Today Unavailable Guide')),
                'messages',
                'EmployeeAttendanceRecord'
            ));
        }

        renderMonthSummary(days, today) {
            const completed = days.filter(day => day.date <= today && day.status).length;
            const notMarked = days.filter(day => day.date <= today && !day.status).length;
            const future = days.filter(day => day.date > today).length;
            const container = this.$el.find('.attendance-month-summary').empty();

            for (const item of [
                ['Completed Days', completed, 'success', 'fa-check-circle'],
                ['Not Marked Days', notMarked, 'danger', 'fa-exclamation-circle'],
                ['Upcoming Days', future, 'default', 'fa-clock'],
            ]) {
                container.append(
                    $('<div>').addClass(`attendance-progress-item attendance-progress-${item[2]}`).append(
                        $('<div>').addClass('attendance-progress-icon').append(
                            $('<span>').addClass(`fas ${item[3]}`).attr('aria-hidden', 'true')
                        ),
                        $('<div>').addClass('attendance-progress-copy').append(
                            $('<span>').text(this.translate(item[0], 'labels', 'EmployeeAttendanceRecord')),
                            $('<strong>').text(String(item[1]))
                        )
                    )
                );
            }
        }

        renderDays(days, today) {
            const container = this.$el.find('.attendance-day-list').empty();

            if (!days.length) {
                container.append(
                    $('<tr>').append($('<td>', {colspan: 3})
                        .addClass('text-muted text-center attendance-empty-row')
                        .text(this.translate('No Working Days', 'messages', 'EmployeeAttendanceRecord')))
                );

                return;
            }

            days.forEach(day => {
                const actions = $('<div>').addClass('attendance-day-actions');
                const presentation = this.statusPresentation(day.status);
                const item = $('<tr>')
                    .addClass(`attendance-day-row attendance-day-row-${presentation.className}`)
                    .toggleClass('attendance-day-current', day.date === today)
                    .toggleClass('attendance-day-future', day.date > today)
                    .append(
                    $('<td>').addClass('attendance-day-date').append(
                        $('<strong>').text(this.displayDate(day.date)),
                        $('<span>').addClass('text-muted').text(this.displayWeekday(day.date))
                    ),
                    $('<td>').addClass('attendance-day-status').append(
                        this.createStatusBadge(day.status, day.source)
                    )
                );

                if (day.canMark || day.canMarkMedical) {
                    for (const status of ['AtWork', 'BusinessTrip', 'MedicalLeave'].filter(
                        status => status === 'MedicalLeave' ? day.canMarkMedical : day.canMark
                    )) {
                        const buttonDefs = {
                            AtWork: {icon: 'fa-building', style: 'btn-success'},
                            BusinessTrip: {icon: 'fa-car', style: 'btn-warning'},
                            MedicalLeave: {icon: 'fa-briefcase-medical', style: 'btn-danger'},
                        }[status];
                        const button = $('<button>')
                            .addClass(`btn btn-sm ${buttonDefs.style} attendance-day-action attendance-day-action-${this.statusPresentation(status).className}`)
                            .attr({
                                'data-action': 'mark-attendance',
                                'data-date': day.date,
                                'data-status': status,
                                'aria-pressed': day.status === status ? 'true' : 'false',
                            })
                            .toggleClass('attendance-state-active', day.status === status)
                            .append(
                                $('<span>').addClass(`fas ${buttonDefs.icon}`).attr('aria-hidden', 'true'),
                                ' ',
                                this.getLanguage().translateOption(status, 'status', 'EmployeeAttendanceRecord')
                            );

                        button.appendTo(actions);
                    }
                } else {
                    const locked = Boolean(day.isLocked);
                    actions.append(
                        $('<span>').addClass('text-muted attendance-day-locked').append(
                            $('<span>').addClass('fas fa-lock').attr('aria-hidden', 'true'),
                            ' ',
                            this.translate(
                                ['Holiday', 'ChildCareLeave'].includes(day.status)
                                    ? 'Managed Automatically'
                                    : (locked ? 'Locked' : 'Upcoming'),
                                'labels',
                                'EmployeeAttendanceRecord'
                            )
                        )
                    );
                }

                item.append($('<td>').append(actions));
                container.append(item);
            });
        }

        createStatusBadge(status, source, large = false) {
            const presentation = this.statusPresentation(status);
            const badge = $('<span>')
                .addClass(`attendance-status-pill attendance-status-${presentation.className}${large ? ' attendance-status-large' : ''}`)
                .append(
                    $('<span>').addClass(`fas ${presentation.icon}`).attr('aria-hidden', 'true'),
                    $('<span>').text(status
                        ? this.getLanguage().translateOption(status, 'status', 'EmployeeAttendanceRecord')
                        : this.translate('Not Marked', 'labels', 'EmployeeAttendanceRecord'))
                );

            if (source === 'ApprovedHoliday') {
                badge.attr('title', this.translate(
                    'Automatically Added Holiday',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            }

            if (source === 'CicPeriod') {
                badge.attr('title', this.translate(
                    'Automatically Added CIC',
                    'messages',
                    'EmployeeAttendanceRecord'
                ));
            }

            return badge;
        }

        statusPresentation(status) {
            return {
                AtWork: {className: 'work', icon: 'fa-building'},
                Holiday: {className: 'holiday', icon: 'fa-umbrella-beach'},
                BusinessTrip: {className: 'trip', icon: 'fa-car'},
                MedicalLeave: {className: 'medical', icon: 'fa-briefcase-medical'},
                ChildCareLeave: {className: 'cic', icon: 'fa-child'},
            }[status] || {className: 'unmarked', icon: 'fa-minus-circle'};
        }

        async actionMarkAttendance(event) {
            const button = $(event.currentTarget);
            const date = String(button.attr('data-date') || '');
            const status = String(button.attr('data-status') || '');

            if (!date || !status || button.prop('disabled')) {
                return;
            }

            this.$el.find(`[data-date="${date}"]`).prop('disabled', true);

            try {
                await Espo.Ajax.postRequest('EmployeeManagement/attendance/mark', {date, status, revision:this.attendance.days.find(d=>d.date===date)?.revision ?? (date===this.attendance.today ? this.attendance.todayRevision : 0)});
                Espo.Ui.success(this.translate('Attendance Saved', 'messages', 'EmployeeAttendanceRecord'));
                await this.loadAttendance(this.attendance.month);
                this.renderAttendance();
            } catch (error) {
                Espo.Ui.error(this.translate('Attendance Save Failed', 'messages', 'EmployeeAttendanceRecord'));
                this.renderAttendance();
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
                await this.loadAttendance(month);
                this.renderAttendance();
            } catch (error) {
                Espo.Ui.error(this.translate('Attendance Load Failed', 'messages', 'EmployeeAttendanceRecord'));
                this.renderMonthSelector(this.attendance);
            }
        }

        renderMonthSelector(data) {
            const selectedMonth = String(data.month || data.currentMonth || '');
            const currentMonth = String(data.currentMonth || selectedMonth);
            const availableFromMonth = String(data.availableFromMonth || currentMonth);
            const select = this.$el.find('[data-month-select]').empty();
            const [year, month] = currentMonth.split('-').map(Number);
            const language = (this.getPreferences().get('language') ||
                this.getConfig().get('language') || 'en_US').replace('_', '-');
            const options = new Map();

            let date = new Date(Date.UTC(year, month - 1, 1, 12));

            while (true) {
                const value = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;

                if (value < availableFromMonth) {
                    break;
                }

                options.set(value, new Intl.DateTimeFormat(language, {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(date));
                date = new Date(Date.UTC(
                    date.getUTCFullYear(),
                    date.getUTCMonth() - 1,
                    1,
                    12
                ));
            }

            if (selectedMonth && !options.has(selectedMonth)) {
                const [selectedYear, selectedMonthNumber] = selectedMonth.split('-').map(Number);
                const date = new Date(Date.UTC(selectedYear, selectedMonthNumber - 1, 1, 12));
                options.set(selectedMonth, new Intl.DateTimeFormat(language, {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(date));
            }

            [...options.entries()]
                .sort(([left], [right]) => right.localeCompare(left))
                .forEach(([value, label]) => select.append($('<option>', {value, text: label})));

            select.val(selectedMonth).prop('disabled', false);
            this.$el.find('.attendance-month-lock-message')
                .toggleClass('hidden', !data.monthLocked)
                .find('[data-role="month-lock-copy"]')
                .text(this.translate('Month Locked Guide', 'messages', 'EmployeeAttendanceRecord'));
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

            return new Intl.DateTimeFormat(language.replace('_', '-'), {weekday: 'long'})
                .format(new Date(`${value}T12:00:00`));
        }
    };
});
