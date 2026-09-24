define(['view', 'content-factory:analytics-mock-data'], (View, data) => {
    return class extends View {
        templateContent = `
            <div class="header page-header content-factory-page-header">
                <h3><span class="fas fa-chart-line" aria-hidden="true"></span> <span data-title></span></h3>
                <p class="text-muted" data-description></p>
            </div>
            <div class="content-factory-analytics">
                <div class="content-factory-metric-grid" data-metrics></div>
                <section class="panel panel-default">
                    <div class="panel-heading">
                        <strong data-performance-title></strong>
                        <div class="small text-muted" data-performance-description></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover content-factory-analytics-table">
                            <thead><tr data-table-head></tr></thead>
                            <tbody data-table-body></tbody>
                        </table>
                    </div>
                </section>
                <p class="small text-muted" data-mock-notice></p>
            </div>
        `

        afterRender() {
            this.$el.find('[data-title]').text(this.translate('Content Factory Analytics', 'labels'));
            this.$el.find('[data-description]').text(
                this.translate('Analytics Description', 'messages')
            );
            this.$el.find('[data-performance-title]').text(
                this.translate('Channel Performance', 'labels')
            );
            this.$el.find('[data-performance-description]').text(
                this.translate('Channel Performance Description', 'messages')
            );
            this.$el.find('[data-mock-notice]').text(
                this.translate('Analytics Mock Notice', 'messages')
            );
            this.renderMetrics();
            this.renderTable();
        }

        renderMetrics() {
            const formatter = this.numberFormatter();
            const container = this.$el.find('[data-metrics]').empty();

            data.metrics.forEach(metric => container.append(
                $('<section>').addClass('panel panel-default content-factory-metric').append(
                    $('<div>').addClass('panel-body').append(
                        $('<span>').addClass('text-muted').text(this.translate(metric.key, 'labels')),
                        $('<strong>').text(metric.percentage
                            ? `${metric.value}%`
                            : formatter.format(metric.value)),
                        $('<small>').addClass('text-muted').text(
                            `${metric.change > 0 ? '+' : ''}${metric.change}% ${
                                this.translate('Previous Period', 'labels')
                            }`
                        )
                    )
                )
            ));
        }

        renderTable() {
            const columns = ['Channel', 'Reach', 'Engagement', 'Clicks', 'Views', 'Retention'];
            const formatter = this.numberFormatter();
            const head = this.$el.find('[data-table-head]').empty();
            const body = this.$el.find('[data-table-body]').empty();

            columns.forEach(column => head.append(
                $('<th>').text(this.translate(column, 'labels'))
            ));

            data.channels.forEach(channel => body.append(
                $('<tr>').append(
                    $('<th>').text(this.translate(channel.channel, 'labels')),
                    $('<td>').text(formatter.format(channel.reach)),
                    $('<td>').text(`${channel.engagement}%`),
                    $('<td>').text(formatter.format(channel.clicks)),
                    $('<td>').text(channel.views === undefined ? '—' : formatter.format(channel.views)),
                    $('<td>').text(channel.retention === undefined ? '—' : `${channel.retention}%`)
                )
            ));
        }

        numberFormatter() {
            const locale = String(
                this.getPreferences().get('language') || this.getConfig().get('language') || 'en_US'
            ).replace('_', '-');

            return new Intl.NumberFormat(locale);
        }
    };
});
