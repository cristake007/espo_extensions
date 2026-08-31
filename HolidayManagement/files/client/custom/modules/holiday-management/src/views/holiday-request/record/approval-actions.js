define([], () => {
    const selector = '.holiday-approval-actions';

    async function decide(view, decision, buttons) {
        const messageKey = decision === 'Approved' ?
            'confirmApproveHoliday' :
            'confirmRejectHoliday';

        await view.confirm({
            message: view.translate(messageKey, 'messages', 'HolidayRequest'),
            confirmText: view.translate(
                decision === 'Approved' ? 'Approve Holiday' : 'Reject Holiday',
                'labels',
                'HolidayRequest',
            ),
        });

        buttons.prop('disabled', true);

        try {
            const result = await Espo.Ajax.postRequest(
                `HolidayManagement/requests/${view.model.id}/decision`,
                {decision},
            );

            view.model.set(result);
            Espo.Ui.success(view.translate(
                decision === 'Approved' ? 'holidayApproved' : 'holidayRejected',
                'messages',
                'HolidayRequest',
            ));
            window.dispatchEvent(new CustomEvent('zile-sarbatoare:calendar-refresh'));
            await view.reRender();
        } catch (error) {
            buttons.prop('disabled', false);
            Espo.Ui.error(view.translate(
                'approvalDecisionFailed',
                'messages',
                'HolidayRequest',
            ));
        }
    }

    return {
        async render(view) {
            view.$el.find(selector).remove();

            if (!view.model.id || (view.model.get('status') || 'Pending') !== 'Pending') {
                return;
            }

            const state = await Espo.Ajax.getRequest(
                `HolidayManagement/requests/${view.model.id}/approval`,
            );

            if (!state.canDecide) {
                return;
            }

            const approve = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-success')
                .text(view.translate('Approve Holiday', 'labels', 'HolidayRequest'));
            const reject = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-danger')
                .text(view.translate('Reject Holiday', 'labels', 'HolidayRequest'));
            const buttons = approve.add(reject);
            const panel = $('<div>')
                .addClass('alert alert-warning holiday-approval-actions')
                .append(
                    $('<strong>')
                        .addClass('margin-right')
                        .text(view.translate('Approval Required', 'labels', 'HolidayRequest')),
                    approve,
                    ' ',
                    reject,
                );

            approve.on('click', () => decide(view, 'Approved', buttons));
            reject.on('click', () => decide(view, 'Rejected', buttons));

            const record = view.$el.find('.record').first();

            if (record.length) {
                record.before(panel);
            } else {
                view.$el.prepend(panel);
            }
        },
    };
});
