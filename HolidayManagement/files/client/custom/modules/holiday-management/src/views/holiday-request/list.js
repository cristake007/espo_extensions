define(['views/list'], (ListView) => {
    return class extends ListView {
        afterRender() {
            super.afterRender();
            this.loadHolidayBalance();
        }

        async loadHolidayBalance() {
            const panel = $('<div>')
                .addClass('panel panel-default holiday-balance-panel margin-bottom')
                .append(
                    $('<div>')
                        .addClass('panel-heading')
                        .text(this.translate('Holiday Balance', 'labels', 'HolidayRequest')),
                    $('<div>')
                        .addClass('panel-body')
                        .append($('<span>').addClass('text-muted').text(this.translate('Loading...'))),
                );

            this.$el.find('.holiday-balance-panel').remove();
            this.$el.find('.page-header').after(panel);

            let balance;

            try {
                balance = await Espo.Ajax.getRequest('HolidayManagement/myBalance');
            } catch (error) {
                panel.find('.panel-body')
                    .empty()
                    .append(
                        $('<span>')
                            .addClass('text-danger')
                            .text(this.translate('Balance Unavailable', 'labels', 'HolidayRequest')),
                    );
                return;
            }

            const body = panel.find('.panel-body').empty();

            if (!balance.initialized) {
                body.append(
                    $('<span>')
                        .addClass('text-warning')
                        .text(this.translate('Profile Not Ready', 'labels', 'HolidayRequest')),
                );
                return;
            }

            const summary = this
                .translate('holidayBalanceSummary', 'messages', 'HolidayRequest')
                .replace('{remaining}', String(balance.balance))
                .replace('{entitlement}', String(balance.annualEntitlement));
            const reset = this
                .translate('holidayBalanceReset', 'messages', 'HolidayRequest')
                .replace('{date}', String(balance.nextResetDate));

            body.append(
                $('<strong>').text(summary),
                $('<span>').addClass('text-muted pull-right').text(reset),
            );
        }
    };
});
