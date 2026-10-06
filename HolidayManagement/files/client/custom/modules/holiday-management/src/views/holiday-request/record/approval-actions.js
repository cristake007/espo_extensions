define([], () => {
    const selector = '.holiday-approval-actions';

    function dispatchRefreshEvents() {
        window.dispatchEvent(new CustomEvent('holiday-management:balance-refresh'));
        window.dispatchEvent(new CustomEvent('zile-sarbatoare:calendar-refresh'));
    }

    async function refresh(view, result) {
        view.model.set(result);
        await view.model.fetch();
        dispatchRefreshEvents();
        await view.reRender();
    }

    async function decide(view, decision, buttons, isCancellation) {
        const approve = decision === (isCancellation ? 'Cancelled' : 'Approved');
        const messageKey = isCancellation ?
            (approve ? 'confirmApproveCancellation' : 'confirmRejectCancellation') :
            (approve ? 'confirmApproveHoliday' : 'confirmRejectHoliday');
        const labelKey = isCancellation ?
            (approve ? 'Approve Cancellation' : 'Reject Cancellation') :
            (approve ? 'Approve Holiday' : 'Reject Holiday');

        await view.confirm({
            message: view.translate(messageKey, 'messages', 'HolidayRequest'),
            confirmText: view.translate(labelKey, 'labels', 'HolidayRequest'),
        });

        buttons.prop('disabled', true);

        try {
            const result = await Espo.Ajax.postRequest(
                `HolidayManagement/requests/${view.model.id}/decision`,
                {decision},
            );
            const successKey = isCancellation ?
                (approve ? 'cancellationApproved' : 'cancellationRejected') :
                (approve ? 'holidayApproved' : 'holidayRejected');

            Espo.Ui.success(view.translate(successKey, 'messages', 'HolidayRequest'));
            await refresh(view, result);
        } catch (error) {
            buttons.prop('disabled', false);
            Espo.Ui.error(view.translate(
                isCancellation ? 'cancellationActionFailed' : 'approvalDecisionFailed',
                'messages',
                'HolidayRequest',
            ));
        }
    }

    async function askReason(view, headerLabel, submitLabel) {
        return new Promise(resolve => {
            const viewName = `holidayCancellationReason-${Date.now()}`;

            view.createView(
                viewName,
                'holiday-management:views/modals/cancellation-reason',
                {headerLabel, submitLabel},
            ).then(modal => {
                let settled = false;

                view.listenToOnce(modal, 'submit', reason => {
                    settled = true;
                    resolve(reason);
                });
                modal.once('remove', () => {
                    if (!settled) {
                        resolve(null);
                    }
                });
                modal.render();
            });
        });
    }

    async function runCancellationAction(view, action, successKey) {
        const direct = action === 'cancel';
        const reason = await askReason(
            view,
            direct ? 'Cancel Holiday' : 'Request Cancellation',
            direct ? 'Cancel Holiday' : 'Request Cancellation',
        );

        if (!reason) {
            return;
        }

        const buttons = view.$el.find(`${selector} button`);
        buttons.prop('disabled', true);

        try {
            const endpoint = direct ? 'cancel' : 'cancellation-request';
            const result = await Espo.Ajax.postRequest(
                `HolidayManagement/requests/${view.model.id}/${endpoint}`,
                {reason},
            );

            Espo.Ui.success(view.translate(successKey, 'messages', 'HolidayRequest'));
            await refresh(view, result);
        } catch (error) {
            buttons.prop('disabled', false);
            Espo.Ui.error(view.translate(
                'cancellationActionFailed',
                'messages',
                'HolidayRequest',
            ));
        }
    }

    function addButton(container, label, style, callback) {
        const button = $('<button>')
            .attr('type', 'button')
            .addClass(`btn ${style} margin-right`)
            .text(label)
            .on('click', callback);

        container.append(button);

        return button;
    }

    return {
        async render(view) {
            view.$el.find(selector).remove();

            if (!view.model.id) {
                return;
            }

            let state;

            try {
                state = await Espo.Ajax.getRequest(
                    `HolidayManagement/requests/${view.model.id}/approval`,
                );
            } catch (error) {
                Espo.Ui.error(view.translate(
                    'approvalStateLoadFailed',
                    'messages',
                    'HolidayRequest',
                ));

                return;
            }

            if (
                !state.canDecide &&
                !state.canRequestCancellation &&
                !state.canCancelDirectly
            ) {
                return;
            }

            const status = state.status || view.model.get('status') || 'Pending';
            const panel = $('<div>').addClass(
                'alert alert-warning holiday-approval-actions'
            );
            const title = status === 'CancellationPending' ?
                'Cancellation Request' :
                (status === 'Approved' ? 'Cancellation Actions' : 'Approval Required');

            panel.append(
                $('<strong>')
                    .addClass('margin-right')
                    .text(view.translate(title, 'labels', 'HolidayRequest')),
            );

            if (state.canDecide && status === 'CancellationPending') {
                let reject;
                const approve = addButton(
                    panel,
                    view.translate('Approve Cancellation', 'labels', 'HolidayRequest'),
                    'btn-success',
                    () => decide(view, 'Cancelled', approve.add(reject), true),
                );
                reject = addButton(
                    panel,
                    view.translate('Reject Cancellation', 'labels', 'HolidayRequest'),
                    'btn-danger',
                    () => decide(view, 'Approved', approve.add(reject), true),
                );
            } else if (state.canDecide) {
                let reject;
                const approve = addButton(
                    panel,
                    view.translate('Approve Holiday', 'labels', 'HolidayRequest'),
                    'btn-success',
                    () => decide(view, 'Approved', approve.add(reject), false),
                );
                reject = addButton(
                    panel,
                    view.translate('Reject Holiday', 'labels', 'HolidayRequest'),
                    'btn-danger',
                    () => decide(view, 'Rejected', approve.add(reject), false),
                );
            }

            if (state.canRequestCancellation) {
                addButton(
                    panel,
                    view.translate('Request Cancellation', 'labels', 'HolidayRequest'),
                    'btn-warning',
                    () => runCancellationAction(
                        view,
                        'request',
                        'cancellationRequested',
                    ),
                );
            }

            if (state.canCancelDirectly) {
                addButton(
                    panel,
                    view.translate('Cancel Holiday', 'labels', 'HolidayRequest'),
                    'btn-danger',
                    () => runCancellationAction(view, 'cancel', 'holidayCancelled'),
                );
            }

            const record = view.$el.find('.record').first();

            if (record.length) {
                record.before(panel);
            } else {
                view.$el.prepend(panel);
            }
        },
    };
});
