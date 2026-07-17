define(['crm:views/calendar/timeline'], (TimelineView) => {
    return class extends TimelineView {
        setup() {
            super.setup();

            const refresh = () => this.actionRefresh();

            window.addEventListener('zile-sarbatoare:calendar-refresh', refresh);
            this.once('remove', () => {
                window.removeEventListener('zile-sarbatoare:calendar-refresh', refresh);
            });
        }
    };
});
