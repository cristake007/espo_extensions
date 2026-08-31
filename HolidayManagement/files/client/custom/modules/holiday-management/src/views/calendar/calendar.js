define(['crm:views/calendar/calendar'], (CalendarView) => {
    return class extends CalendarView {
        eventAttributes = ['assignedUserName'];

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
            const renderedEvents = [];

            events.forEach(event => {
                if (event.scope !== 'HolidayRequest') {
                    renderedEvents.push(event);

                    return;
                }

                const userName = event.assignedUserName || event.title;
                event.title = `\u2602 ${userName}`;

                if (this.mode === 'listWeek') {
                    event.className = ['holiday-request-list-event'];
                    renderedEvents.push(event);

                    return;
                }

                let date = this.dateToMoment(event.dateStartDate);
                const lastDate = this.dateToMoment(event.dateEndDate);

                while (!date.isAfter(lastDate, 'day')) {
                    const dateString = date.format('YYYY-MM-DD');

                    renderedEvents.push({
                        ...event,
                        id: `${event.id}-marker-${dateString}`,
                        start: dateString,
                        end: date.clone().add(1, 'day').format('YYYY-MM-DD'),
                        allDay: true,
                        display: 'block',
                        editable: false,
                        className: ['holiday-request-marker'],
                    });

                    date = date.add(1, 'day');
                }
            });

            return renderedEvents;
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
