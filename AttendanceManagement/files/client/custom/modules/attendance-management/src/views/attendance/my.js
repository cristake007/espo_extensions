define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <div class="header page-header">
                <h3>{{translate 'Attendance' category='labels' scope='AttendanceRecord'}}</h3>
            </div>
            <div class="attendance-page">
                <div class="panel panel-default attendance-today-panel">
                    <div class="panel-heading">
                        <strong>{{translate 'Today' category='labels' scope='AttendanceRecord'}}</strong>
                        <span class="attendance-today-date text-muted"></span>
                    </div>
                    <div class="panel-body">
                        <div class="attendance-state-actions">
                            <button class="btn btn-success btn-lg" data-action="mark-attendance" data-status="AtWork">
                                <span class="fas fa-building" aria-hidden="true"></span>
                                {{translate 'AtWork' category='options' scope='AttendanceRecord' field='status'}}
                            </button>
                            <button class="btn btn-warning btn-lg" data-action="mark-attendance" data-status="BusinessTrip">
                                <span class="fas fa-car" aria-hidden="true"></span>
                                {{translate 'BusinessTrip' category='options' scope='AttendanceRecord' field='status'}}
                            </button>
                            <button class="btn btn-info btn-lg" data-status-indicator="Holiday" disabled>
                                <span class="fas fa-umbrella-beach" aria-hidden="true"></span>
                                {{translate 'Holiday' category='options' scope='AttendanceRecord' field='status'}}
                            </button>
                        </div>
                        <div class="attendance-current-status text-muted"></div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading attendance-month-heading">
                        <strong>{{translate 'Working Days' category='labels' scope='AttendanceRecord'}}</strong>
                        <input class="form-control input-sm attendance-month-input" type="month">
                    </div>
                    <div class="list-group attendance-day-list"></div>
                </div>
            </div>
        `

        events = {
            'click [data-action="mark-attendance"]': 'actionMarkAttendance',
            'change .attendance-month-input': 'actionChangeMonth',
        }

        setup() {
            this.attendance = {days: []};
            this.wait(this.loadAttendance());
        }

        async loadAttendance(month = null) {
            const url = month
                ? `AttendanceManagement/myAttendance?month=${encodeURIComponent(month)}`
                : 'AttendanceManagement/myAttendance';

            this.attendance = await Espo.Ajax.getRequest(url);
        }

        afterRender() {
            this.renderAttendance();
        }

        renderAttendance() {
            const data = this.attendance;
            const input = this.$el.find('.attendance-month-input');

            input.val(data.month || '');
            input.attr('max', data.currentMonth || '');
            this.$el.find('.attendance-today-date').text(this.displayDate(data.today));
            this.renderToday(data);
            this.renderDays(data.days || []);
        }

        renderToday(data) {
            const status = data.todayStatus || null;
            const actions = this.$el.find('.attendance-state-actions');

            actions.find('[data-action="mark-attendance"]')
                .attr('data-date', data.today)
                .prop('disabled', !data.todayCanMark)
                .removeClass('attendance-state-active');
            actions.find('[data-status-indicator="Holiday"]')
                .toggleClass('attendance-state-active', status === 'Holiday');

            if (status && status !== 'Holiday') {
                actions.find(`[data-status="${status}"]`).addClass('attendance-state-active');
            }

            this.$el.find('.attendance-current-status').text(
                status
                    ? this.translate('Current Status', 'labels', 'AttendanceRecord') + ': ' +
                        this.translate(status, 'options', 'AttendanceRecord', 'status')
                    : this.translate('Not Marked', 'labels', 'AttendanceRecord')
            );
        }

        renderDays(days) {
            const container = this.$el.find('.attendance-day-list').empty();

            if (!days.length) {
                container.append(
                    $('<div>').addClass('list-group-item text-muted').text(
                        this.translate('No Working Days', 'messages', 'AttendanceRecord')
                    )
                );

                return;
            }

            days.forEach(day => {
                const actions = $('<div>').addClass('attendance-day-actions');
                const item = $('<div>').addClass('list-group-item attendance-day-item').append(
                    $('<div>').addClass('attendance-day-date').text(this.displayDate(day.date)),
                    $('<div>').addClass('attendance-day-status').append(
                        this.createStatusBadge(day.status, day.source)
                    ),
                    actions,
                );

                for (const status of ['AtWork', 'BusinessTrip']) {
                    const button = $('<button>')
                        .addClass(`btn btn-sm ${status === 'AtWork' ? 'btn-success' : 'btn-warning'}`)
                        .attr({
                            'data-action': 'mark-attendance',
                            'data-date': day.date,
                            'data-status': status,
                        })
                        .prop('disabled', !day.canMark)
                        .toggleClass('attendance-state-active', day.status === status)
                        .text(this.translate(status, 'options', 'AttendanceRecord', 'status'));

                    button.appendTo(actions);
                }

                container.append(item);
            });
        }

        createStatusBadge(status, source) {
            if (!status) {
                return $('<span>').addClass('text-muted').text(
                    this.translate('Not Marked', 'labels', 'AttendanceRecord')
                );
            }

            const style = {
                AtWork: 'success',
                Holiday: 'info',
                BusinessTrip: 'warning',
            }[status] || 'default';
            const badge = $('<span>')
                .addClass(`label label-${style}`)
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
                await this.loadAttendance(this.$el.find('.attendance-month-input').val());
                this.renderAttendance();
            } catch (error) {
                Espo.Ui.error(this.translate('Attendance Save Failed', 'messages', 'AttendanceRecord'));
                this.renderAttendance();
            }
        }

        async actionChangeMonth(event) {
            const month = String($(event.currentTarget).val() || '');

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

        displayDate(value) {
            return value ? this.getDateTime().toDisplayDate(String(value)) : '';
        }
    };
});
