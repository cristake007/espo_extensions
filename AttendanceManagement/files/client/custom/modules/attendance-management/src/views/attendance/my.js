define(['view', 'model'], (View, Model) => {
    return class extends View {
        templateContent = `
            <div class="header page-header attendance-page-header">
                <div>
                    <h3>
                        <span class="fas fa-clipboard-check" aria-hidden="true"></span>
                        {{translate 'Attendance' category='labels' scope='AttendanceRecord'}}
                    </h3>
                    <p class="text-muted attendance-page-intro">
                        {{translate 'Attendance Page Guide' category='messages' scope='AttendanceRecord'}}
                    </p>
                </div>
            </div>
            <div class="attendance-page">
                <div class="panel panel-default attendance-today-panel">
                    <div class="panel-heading attendance-today-heading">
                        <div class="attendance-section-icon attendance-section-icon-today">
                            <span class="far fa-calendar-check" aria-hidden="true"></span>
                        </div>
                        <div>
                            <strong>{{translate 'Today Attendance' category='labels' scope='AttendanceRecord'}}</strong>
                            <div class="attendance-today-date text-muted"></div>
                        </div>
                    </div>
                    <div class="panel-body attendance-today-body">
                        <div class="attendance-today-summary">
                            <span class="text-muted">{{translate 'Current Status' category='labels' scope='AttendanceRecord'}}</span>
                            <div class="attendance-current-status"></div>
                            <p class="attendance-today-guidance text-muted"></p>
                        </div>
                        <div class="attendance-state-actions">
                            <button class="btn btn-success btn-lg" data-action="mark-attendance" data-status="AtWork">
                                <span class="attendance-action-icon fas fa-building" aria-hidden="true"></span>
                                <span class="attendance-action-copy">
                                    <strong>{{translate 'AtWork' category='options' scope='AttendanceRecord' field='status'}}</strong>
                                    <small>{{translate 'At Work Help' category='messages' scope='AttendanceRecord'}}</small>
                                </span>
                            </button>
                            <button class="btn btn-warning btn-lg" data-action="mark-attendance" data-status="BusinessTrip">
                                <span class="attendance-action-icon fas fa-car" aria-hidden="true"></span>
                                <span class="attendance-action-copy">
                                    <strong>{{translate 'BusinessTrip' category='options' scope='AttendanceRecord' field='status'}}</strong>
                                    <small>{{translate 'Business Trip Help' category='messages' scope='AttendanceRecord'}}</small>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="panel panel-default attendance-month-panel">
                    <div class="panel-heading attendance-month-heading">
                        <div class="attendance-month-title">
                            <div class="attendance-section-icon">
                                <span class="far fa-calendar-alt" aria-hidden="true"></span>
                            </div>
                            <div>
                                <strong>{{translate 'Monthly Register' category='labels' scope='AttendanceRecord'}}</strong>
                                <div class="text-muted small">{{translate 'Monthly Register Guide' category='messages' scope='AttendanceRecord'}}</div>
                            </div>
                        </div>
                        <div class="attendance-month-control">
                            <div class="field attendance-month-field" data-month-field></div>
                            <button class="btn btn-primary btn-sm" data-action="open-month">
                                <span class="fas fa-search" aria-hidden="true"></span>
                                {{translate 'Open Month' category='labels' scope='AttendanceRecord'}}
                            </button>
                        </div>
                    </div>
                    <div class="panel-body attendance-month-summary"></div>
                    <div class="table-responsive">
                        <table class="table table-hover attendance-my-table">
                            <thead>
                                <tr>
                                    <th>{{translate 'date' category='fields' scope='AttendanceRecord'}}</th>
                                    <th>{{translate 'status' category='fields' scope='AttendanceRecord'}}</th>
                                    <th>{{translate 'Choose Attendance' category='labels' scope='AttendanceRecord'}}</th>
                                </tr>
                            </thead>
                            <tbody class="attendance-day-list"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        `

        events = {
            'click [data-action="mark-attendance"]': 'actionMarkAttendance',
            'click [data-action="open-month"]': 'actionOpenMonth',
        }

        setup() {
            this.attendance = {days: []};
            this.monthModel = new Model();
            this.monthModel.entityType = 'AttendanceRecord';
            this.wait(this.loadAttendance(this.options.month || null));
        }

        async loadAttendance(month = null) {
            const url = month
                ? `AttendanceManagement/myAttendance?month=${encodeURIComponent(month)}`
                : 'AttendanceManagement/myAttendance';

            this.attendance = await Espo.Ajax.getRequest(url);
        }

        afterRender() {
            this.renderAttendance();
            this.renderMonthField();
        }

        renderAttendance() {
            const data = this.attendance;

            this.monthModel.set('monthDate', data.month ? `${data.month}-01` : null);
            this.$el.find('.attendance-today-date').text(this.displayDate(data.today));
            this.renderToday(data);
            this.renderMonthSummary(data.days || [], data.today);
            this.renderDays(data.days || [], data.today);
        }

        renderToday(data) {
            const status = data.todayStatus || null;
            const actions = this.$el.find('.attendance-state-actions');

            actions.find('[data-action="mark-attendance"]')
                .attr('data-date', data.today)
                .prop('disabled', !data.todayCanMark)
                .removeClass('attendance-state-active');
            if (status && status !== 'Holiday') {
                actions.find(`[data-status="${status}"]`).addClass('attendance-state-active');
            }

            this.$el.find('.attendance-current-status')
                .empty()
                .append(this.createStatusBadge(status, data.todaySource, true));
            this.$el.find('.attendance-today-guidance').text(this.translate(
                data.todayCanMark
                    ? 'Today Marking Guide'
                    : (status === 'Holiday' ? 'Holiday Locked Guide' : 'Today Unavailable Guide'),
                'messages',
                'AttendanceRecord'
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
                        $('<span>').addClass(`fas ${item[3]}`).attr('aria-hidden', 'true'),
                        $('<strong>').text(String(item[1])),
                        $('<span>').text(this.translate(item[0], 'labels', 'AttendanceRecord'))
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
                        .text(this.translate('No Working Days', 'messages', 'AttendanceRecord')))
                );

                return;
            }

            days.forEach(day => {
                const actions = $('<div>').addClass('attendance-day-actions');
                const item = $('<tr>').toggleClass('attendance-day-future', day.date > today).append(
                    $('<td>').addClass('attendance-day-date').append(
                        $('<strong>').text(this.displayDate(day.date)),
                        $('<span>').addClass('text-muted').text(this.displayWeekday(day.date))
                    ),
                    $('<td>').addClass('attendance-day-status').append(
                        this.createStatusBadge(day.status, day.source)
                    )
                );

                if (day.canMark) {
                    for (const status of ['AtWork', 'BusinessTrip']) {
                        const icon = status === 'AtWork' ? 'fa-building' : 'fa-car';
                        const button = $('<button>')
                            .addClass(`btn btn-sm ${status === 'AtWork' ? 'btn-success' : 'btn-warning'}`)
                            .attr({
                                'data-action': 'mark-attendance',
                                'data-date': day.date,
                                'data-status': status,
                            })
                            .toggleClass('attendance-state-active', day.status === status)
                            .append(
                                $('<span>').addClass(`fas ${icon}`).attr('aria-hidden', 'true'),
                                ' ',
                                this.translate(status, 'options', 'AttendanceRecord', 'status')
                            );

                        button.appendTo(actions);
                    }
                } else {
                    actions.append(
                        $('<span>').addClass('text-muted attendance-day-locked').append(
                            $('<span>').addClass('fas fa-lock').attr('aria-hidden', 'true'),
                            ' ',
                            this.translate(
                                day.status === 'Holiday' ? 'Managed Automatically' : 'Upcoming',
                                'labels',
                                'AttendanceRecord'
                            )
                        )
                    );
                }

                item.append($('<td>').append(actions));
                container.append(item);
            });
        }

        createStatusBadge(status, source, large = false) {
            if (!status) {
                return $('<span>')
                    .addClass(`label label-default${large ? ' attendance-status-large' : ''}`)
                    .text(this.translate('Not Marked', 'labels', 'AttendanceRecord'));
            }

            const style = {
                AtWork: 'success',
                Holiday: 'info',
                BusinessTrip: 'warning',
            }[status] || 'default';
            const badge = $('<span>')
                .addClass(`label label-${style}${large ? ' attendance-status-large' : ''}`)
                .text(this.translate(status, 'options', 'AttendanceRecord', 'status'));

            if (source === 'ApprovedHoliday') {
                badge.attr('title', this.translate(
                    'Automatically Added Holiday',
                    'messages',
                    'AttendanceRecord'
                ));
            }

            return badge;
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
                await Espo.Ajax.postRequest('AttendanceManagement/mark', {date, status});
                Espo.Ui.success(this.translate('Attendance Saved', 'messages', 'AttendanceRecord'));
                await this.loadAttendance(this.attendance.month);
                this.renderAttendance();
            } catch (error) {
                Espo.Ui.error(this.translate('Attendance Save Failed', 'messages', 'AttendanceRecord'));
                this.renderAttendance();
            }
        }

        async actionOpenMonth() {
            const field = this.getView('attendanceMonth');
            const date = field ? field.fetch().monthDate : null;
            const month = typeof date === 'string' ? date.slice(0, 7) : '';

            if (!month) {
                return;
            }

            try {
                await this.loadAttendance(month);
                this.renderAttendance();
            } catch (error) {
                Espo.Ui.error(this.translate('Attendance Load Failed', 'messages', 'AttendanceRecord'));
            }
        }

        async renderMonthField() {
            const view = await this.createView(
                'attendanceMonth',
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
