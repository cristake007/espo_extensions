define(['view'], (View) => {
    return class extends View {
        templateContent = `
            <div class="header page-header holiday-overview-header">
                <div>
                    <h3>{{translate 'Holiday Overview' category='labels' scope='HolidayOverview'}}</h3>
                    <p class="text-muted">{{translate 'Overview Guide' category='messages' scope='HolidayOverview'}}</p>
                </div>
                <a class="btn btn-default holiday-settings-link" href="#Admin/employeeHolidaySettings">
                    <span class="fas fa-cog" aria-hidden="true"></span>
                    {{translate 'Holiday Settings' category='labels' scope='HolidayOverview'}}
                </a>
            </div>
            <div class="panel panel-default holiday-overview-panel">
                <div class="panel-heading holiday-overview-toolbar">
                    <div class="holiday-overview-month-control">
                        <span>{{translate 'Month' category='labels' scope='HolidayOverview'}}</span>
                        <select class="form-control holiday-overview-month-select"
                            data-overview-month-select></select>
                    </div>
                    <div class="holiday-overview-legend">
                        <span class="label label-warning">{{translateOption 'Pending' field='status' scope='EmployeeHolidayRequest'}}</span>
                        <span class="label label-success">{{translateOption 'Approved' field='status' scope='EmployeeHolidayRequest'}}</span>
                        <span class="label label-info">{{translateOption 'CancellationPending' field='status' scope='EmployeeHolidayRequest'}}</span>
                    </div>
                </div>
                <div class="panel-body holiday-overview-summary"></div>
                <div class="table-responsive holiday-overview-table-wrap">
                    <table class="table table-bordered table-condensed holiday-overview-table">
                        <thead></thead><tbody></tbody>
                    </table>
                </div>
            </div>
        `

        events = {'change [data-overview-month-select]': 'actionSelectMonth'}

        setup() {
            this.overview = {users: [], rows: [], summary: {}};
            this.wait(this.loadOverview(this.options.month));
        }

        async loadOverview(month = null) {
            const url = month
                ? `EmployeeManagement/overview?month=${encodeURIComponent(month)}`
                : 'EmployeeManagement/overview';
            this.overview = await Espo.Ajax.getRequest(url);
        }

        afterRender() {
            this.renderOverview();
        }

        renderOverview() {
            this.renderMonthSelector();
            this.$el.find('.holiday-settings-link').toggle(
                this.getAcl().check('EmployeeManagementSettings'),
            );
            this.renderSummary();
            this.renderTable();
        }

        renderMonthSelector() {
            const selectedMonth = String(this.overview.month || this.overview.currentMonth || '');
            const [selectedYear, selectedMonthNumber] = selectedMonth.split('-').map(Number);
            const language = (this.getPreferences().get('language') ||
                this.getConfig().get('language') || 'en_US').replace('_', '-');
            const select = this.$el.find('[data-overview-month-select]').empty();

            for (let offset = 24; offset >= -24; offset--) {
                const date = new Date(Date.UTC(
                    selectedYear,
                    selectedMonthNumber - 1 - offset,
                    1,
                    12,
                ));
                const value = `${date.getUTCFullYear()}-${String(
                    date.getUTCMonth() + 1,
                ).padStart(2, '0')}`;
                const label = new Intl.DateTimeFormat(language, {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(date);

                select.append($('<option>', {value, text: label}));
            }

            select.val(selectedMonth);
        }

        renderSummary() {
            const data = this.overview.summary || {};
            const target = this.$el.find('.holiday-overview-summary').empty();
            [
                ['Employees', data.employeeCount || 0, 'fa-users', 'primary'],
                ['Away Today', data.awayToday || 0, 'fa-person-walking-luggage', 'success'],
                ['Pending Requests', data.pendingRequests || 0, 'fa-clock', 'warning'],
                ['Missing Profiles', data.missingProfiles || 0, 'fa-user-slash', data.missingProfiles ? 'danger' : 'success'],
            ].forEach(([label, value, icon, style]) => target.append(
                $('<section>').addClass(`holiday-summary-card holiday-summary-${style}`).append(
                    $('<span>').addClass(`fas ${icon}`).attr('aria-hidden', 'true'),
                    $('<strong>').text(value),
                    $('<div>').text(this.translate(label, 'labels', 'HolidayOverview')),
                ),
            ));
        }

        renderTable() {
            const users = this.overview.users || [];
            const dateRows = this.overview.rows || [];
            const head = this.$el.find('.holiday-overview-table thead').empty();
            const body = this.$el.find('.holiday-overview-table tbody').empty();
            const header = $('<tr>').append(
                $('<th>').addClass('holiday-sticky-employee').text(
                    this.translate('Employee', 'labels', 'HolidayOverview'),
                ),
            );

            dateRows.forEach(row => header.append(
                $('<th>')
                    .addClass('holiday-day-heading')
                    .toggleClass('holiday-non-working-column', row.isWeekend || row.isPublicHoliday)
                    .toggleClass('holiday-today-column', row.date === this.overview.today)
                    .attr('title', this.displayDate(row.date))
                    .append(
                        $('<span>').text(row.date.slice(8, 10)),
                    ),
            ));
            head.append(header);

            users.forEach(user => {
                const tableRow = $('<tr>').append(
                    $('<th>').addClass('holiday-sticky-employee').append(
                    $('<div>').addClass('holiday-employee-name').text(user.name || ''),
                    $('<div>').addClass('text-muted small').text(user.profileInitialized
                        ? this.formatMessage('Balance Detail', {
                            balance: this.formatNumber(user.balance),
                            pending: this.formatNumber(user.pendingDays),
                        })
                        : this.translate('Profile Missing', 'labels', 'HolidayOverview')),
                    ),
                );

                dateRows.forEach(row => {
                    const cell = (row.cells || []).find(item => item.userId === user.id) || {};
                    const tableCell = this.createCell(cell)
                        .toggleClass('holiday-non-working-column', row.isWeekend || row.isPublicHoliday)
                        .toggleClass('holiday-today-column', row.date === this.overview.today);

                    tableRow.append(tableCell);
                });
                body.append(tableRow);
            });
        }

        createCell(cell) {
            const td = $('<td>').addClass('holiday-overview-cell');

            if (!cell.status) {
                return td;
            }

            const styles = {Pending: 'warning', Approved: 'success', CancellationPending: 'info'};
            const icons = {
                Pending: 'fa-clock',
                Approved: 'fa-check',
                CancellationPending: 'fa-undo-alt',
            };
            const statusLabel = this.getLanguage().translateOption(
                cell.status,
                'status',
                'EmployeeHolidayRequest',
            );
            const label = $('<span>')
                .addClass(`label label-${styles[cell.status] || 'default'}`)
                .attr('title', statusLabel)
                .attr('aria-label', statusLabel)
                .append($('<span>').addClass(`fas ${icons[cell.status] || 'fa-circle'}`));

            if (cell.requestId) {
                td.append($('<a>').attr('href', `#EmployeeHolidayRequest/view/${cell.requestId}`).append(label));
            } else {
                td.append(label);
            }

            return td;
        }

        async actionSelectMonth(event) {
            const month = String($(event.currentTarget).val() || '');

            if (!/^\d{4}-\d{2}$/.test(month) || month === this.overview.month) {
                return;
            }

            await this.loadOverview(month);
            this.getRouter().navigate(`#HolidayOverview?month=${encodeURIComponent(month)}`, {trigger: false});
            this.renderOverview();
        }

        displayDate(date) {
            return this.getDateTime().toDisplayDate(date) || date;
        }

        formatNumber(value) {
            return Number(value || 0).toLocaleString(undefined, {maximumFractionDigits: 2});
        }

        formatMessage(label, values) {
            let text = this.translate(label, 'messages', 'HolidayOverview');
            Object.entries(values).forEach(([key, value]) => {
                text = text.replace(`{${key}}`, value);
            });
            return text;
        }
    };
});
