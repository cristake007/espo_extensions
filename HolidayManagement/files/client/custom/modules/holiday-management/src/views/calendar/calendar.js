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

        convertToFcEvents(list) {
            const events = super.convertToFcEvents(list);

            events.forEach(event => {
                if (event.scope !== 'HolidayRequest') {
                    return;
                }

                const userName = event.title.replace(/^Holiday - /, '');
                event.title = `\u2602 ${userName}`;

                if (this.mode === 'listWeek') {
                    event.className = ['holiday-request-list-event'];
                }
            });

            return events;
        }

        addModel(model) {
            if (model.entityType === 'HolidayRequest') {
                this.actionRefresh({suppressLoadingAlert: true});

                return;
            }

            super.addModel(model);
        }

        updateModel(model) {
            if (model.entityType === 'HolidayRequest') {
                this.actionRefresh({suppressLoadingAlert: true});

                return;
            }

            super.updateModel(model);
        }

        removeModel(model) {
            if (model.entityType === 'HolidayRequest') {
                this.actionRefresh({suppressLoadingAlert: true});

                return;
            }

            super.removeModel(model);
        }
    };
});
