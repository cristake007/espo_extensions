define(['crm:views/calendar/calendar'], (CalendarView) => {
    return class extends CalendarView {
        setup() {
            super.setup();

            const refresh = () => this.actionRefresh({suppressLoadingAlert: true});

            window.addEventListener('zile-sarbatoare:calendar-refresh', refresh);
            this.once('remove', () => {
                window.removeEventListener('zile-sarbatoare:calendar-refresh', refresh);
            });
        }
    };
});
