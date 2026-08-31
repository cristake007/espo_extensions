define(['crm:views/calendar/calendar'], (CalendarView) => {
    return class extends CalendarView {
        setup() {
            super.setup();

            const refresh = () => this.actionRefresh({suppressLoadingAlert: true});

            window.addEventListener('zile-sarbatoare:calendar-refresh', refresh);
            this.once('remove', () => {
                window.removeEventListener('zile-sarbatoare:calendar-refresh', refresh);
                this.nonWorkingDayObserver?.disconnect();

                if (this.nonWorkingDayRenderTimeout) {
                    window.clearTimeout(this.nonWorkingDayRenderTimeout);
                }
            });
        }

        afterRender() {
            super.afterRender();

            const calendarElement = this.$calendar?.get(0);

            if (!calendarElement || typeof MutationObserver === 'undefined') {
                return;
            }

            this.nonWorkingDayObserver?.disconnect();
            this.nonWorkingDayObserver = new MutationObserver(() => {
                this.scheduleNonWorkingDayDecoration();
            });
            this.nonWorkingDayObserver.observe(calendarElement, {
                childList: true,
                subtree: true,
            });
            this.scheduleNonWorkingDayDecoration();
        }

        scheduleNonWorkingDayDecoration() {
            if (this.nonWorkingDayRenderTimeout) {
                window.clearTimeout(this.nonWorkingDayRenderTimeout);
            }

            this.nonWorkingDayRenderTimeout = window.setTimeout(() => {
                this.nonWorkingDayRenderTimeout = null;
                this.decorateNonWorkingDays();
            }, 0);
        }

        decorateNonWorkingDays() {
            if (!this.$calendar || !this.calendar) {
                return;
            }

            this.$calendar
                .find('.zile-sarbatoare-non-working-day')
                .removeClass('zile-sarbatoare-non-working-day');

            this.getNonWorkingDateList().forEach(date => {
                this.$calendar
                    .find(`[data-date="${date}"]`)
                    .addClass('zile-sarbatoare-non-working-day');
            });
        }

        getNonWorkingDateList() {
            if (!this.calendar) {
                return [];
            }

            const dates = new Set();

            this.calendar.getEvents().forEach(event => {
                if (event.extendedProps.scope !== 'ZileLibere') {
                    return;
                }

                const date = event.extendedProps.dateStartDate;

                if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
                    dates.add(date);
                }
            });

            return [...dates].sort();
        }
    };
});
