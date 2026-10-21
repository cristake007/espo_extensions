define(['views/list'], ListView => class extends ListView {
    templateContent = `
        <main class="employee-holiday-ledger-page">
            <header class="employee-holiday-ledger-hero">
                <div class="employee-holiday-ledger-hero-copy">
                    <div class="employee-holiday-ledger-hero-icon" aria-hidden="true">
                        <span class="fas fa-history"></span>
                    </div>
                    <div>
                        <div class="employee-holiday-ledger-eyebrow">{{translate 'Holiday Ledger' category='labels' scope='EmployeeHolidayLedger'}}</div>
                        <h3>{{translate 'Holiday History' category='labels' scope='EmployeeHolidayLedger'}}</h3>
                        <p>{{translate 'holidayHistoryIntro' category='messages' scope='EmployeeHolidayLedger'}}</p>
                    </div>
                </div>

                {{#if canFilterEmployees}}
                <div class="employee-holiday-ledger-filter" data-role="employee-filter">
                    <label>{{translate 'Filter Employee' category='labels' scope='EmployeeHolidayLedger'}}</label>
                    <div class="employee-holiday-ledger-user-field field" data-role="employee-selector"></div>
                    <p>{{translate 'managerEmployeeFilterHelp' category='messages' scope='EmployeeHolidayLedger'}}</p>
                    <div class="employee-holiday-ledger-filter-actions">
                        <button class="btn btn-default btn-sm" data-action="ledger-show-all">
                            <span class="fas fa-users" aria-hidden="true"></span>
                            {{translate 'All Employees' category='labels' scope='EmployeeHolidayLedger'}}
                        </button>
                        <button class="btn btn-default btn-sm" data-action="ledger-show-own">
                            <span class="fas fa-user" aria-hidden="true"></span>
                            {{translate 'My History' category='labels' scope='EmployeeHolidayLedger'}}
                        </button>
                    </div>
                </div>
                {{/if}}
            </header>

            <section class="employee-holiday-ledger-panel">
                <div class="employee-holiday-ledger-heading">
                    <div class="employee-holiday-ledger-heading-copy">
                        <div class="employee-holiday-ledger-section-icon" aria-hidden="true">
                            <span class="fas fa-exchange-alt"></span>
                        </div>
                        <div>
                            <h4>{{translate 'Holiday History' category='labels' scope='EmployeeHolidayLedger'}}</h4>
                            <p>{{#if canFilterEmployees}}{{translate 'holidayLedgerHelp' category='messages' scope='EmployeeHolidayLedger'}}{{else}}{{translate 'employeeLedgerHelp' category='messages' scope='EmployeeHolidayLedger'}}{{/if}}</p>
                        </div>
                    </div>
                    <div class="employee-holiday-ledger-count" data-role="entry-count"></div>
                </div>

                <div class="employee-holiday-ledger-state employee-holiday-ledger-loading" data-state="loading">
                    <span class="fas fa-circle-notch fa-spin" aria-hidden="true"></span>
                    <strong>{{translate 'Ledger Loading' category='labels' scope='EmployeeHolidayLedger'}}</strong>
                </div>
                <div class="employee-holiday-ledger-state employee-holiday-ledger-empty hidden" data-state="empty">
                    <span class="far fa-calendar-times" aria-hidden="true"></span>
                    <strong>{{translate 'No Ledger Entries' category='labels' scope='EmployeeHolidayLedger'}}</strong>
                    <p>{{translate 'ledgerEmptyHelp' category='messages' scope='EmployeeHolidayLedger'}}</p>
                </div>
                <div class="employee-holiday-ledger-state employee-holiday-ledger-error hidden" data-state="error">
                    <span class="fas fa-exclamation-circle" aria-hidden="true"></span>
                    <strong>{{translate 'Ledger Unavailable' category='labels' scope='EmployeeHolidayLedger'}}</strong>
                    <p>{{translate 'ledgerErrorHelp' category='messages' scope='EmployeeHolidayLedger'}}</p>
                    <button class="btn btn-default btn-sm" data-action="ledger-retry">
                        <span class="fas fa-redo" aria-hidden="true"></span>
                        {{translate 'Retry' category='labels' scope='EmployeeHolidayLedger'}}
                    </button>
                </div>
                <div class="employee-holiday-ledger-list list-container"></div>
            </section>
        </main>
    `;

    events = {
        'click [data-action="ledger-show-all"]': 'actionShowAll',
        'click [data-action="ledger-show-own"]': 'actionShowOwn',
        'click [data-action="ledger-retry"]': 'actionRetry',
    };

    setupHeader() {}

    setupSearchPanel() {}

    setup() {
        this.scope = this.options.scope || 'EmployeeHolidayLedger';
        this.canFilterEmployees = this.getAcl().getLevel(this.scope, 'read') === 'all';
        this.filterModel = this.collection.prepareModel();

        super.setup();

        this.restoreEmployeeFilter();
        this.listenTo(this.filterModel, 'change:assignedUserId', () => {
            if (!this.employeeFilterSyncDisabled) {
                this.applyEmployeeFilter(
                    this.filterModel.get('assignedUserId'),
                    this.filterModel.get('assignedUserName')
                );
            }
        });
        this.listenTo(this.collection, 'request', () => this.showLedgerState('loading'));
        this.listenTo(this.collection, 'sync', () => this.showCollectionState());
        this.listenTo(this.collection, 'error', () => this.showLedgerState('error'));
    }

    data() {
        return {
            canFilterEmployees: this.canFilterEmployees,
        };
    }

    prepareRecordViewOptions(options) {
        super.prepareRecordViewOptions(options);
        options.noDataDisabled = true;
        options.rowActionsDisabled = true;
    }

    async afterRender() {
        super.afterRender();
        this.setPageTitle(this.translate('Holiday History', 'labels', this.scope));

        if (this.canFilterEmployees) {
            const selector = await this.createView('employeeSelector', 'views/fields/user', {
                selector: '[data-role="employee-selector"]',
                model: this.filterModel,
                name: 'assignedUser',
                mode: 'edit',
                readOnlyDisabled: true,
            });
            await selector.render();
        }

        this.showCollectionState();
        this.updateFilterButtons();
    }

    restoreEmployeeFilter() {
        if (!this.canFilterEmployees || !this.searchManager) {
            return;
        }

        const advanced = this.searchManager.get().advanced || {};
        const filter = advanced.assignedUser;

        if (!filter || !filter.value) {
            return;
        }

        this.employeeFilterSyncDisabled = true;
        this.filterModel.set({
            assignedUserId: filter.value,
            assignedUserName: filter.data && filter.data.nameValue
                ? filter.data.nameValue
                : filter.value,
        });
        this.employeeFilterSyncDisabled = false;
    }

    async applyEmployeeFilter(id, name) {
        if (!this.canFilterEmployees || !this.searchManager) {
            return;
        }

        const data = Espo.Utils.clone(this.searchManager.get());
        data.advanced = data.advanced || {};

        if (id) {
            data.advanced.assignedUser = {
                type: 'equals',
                attribute: 'assignedUserId',
                value: id,
                data: {
                    type: 'equals',
                    idValue: id,
                    nameValue: name || id,
                },
            };
        } else {
            delete data.advanced.assignedUser;
        }

        this.searchManager.set(data);
        this.collection.where = this.searchManager.getWhere();
        this.collection.offset = 0;
        this.updateFilterButtons();

        Espo.Ui.notifyWait();
        try {
            await this.collection.fetch({main: true});
            Espo.Ui.notify(false);
        } catch (error) {
            Espo.Ui.notify(false);
        }
    }

    setEmployeeFilter(id, name) {
        this.filterModel.set({
            assignedUserId: id || null,
            assignedUserName: name || null,
        });
    }

    actionShowAll() {
        this.setEmployeeFilter(null, null);
    }

    actionShowOwn() {
        this.setEmployeeFilter(this.getUser().id, this.getUser().get('name'));
    }

    async actionRetry() {
        Espo.Ui.notifyWait();
        try {
            await this.collection.fetch({main: true});
            Espo.Ui.notify(false);
        } catch (error) {
            Espo.Ui.notify(false);
        }
    }

    showLedgerState(state) {
        if (!this.isRendered()) {
            return;
        }

        this.$el.find('[data-state]').addClass('hidden');
        this.$el.find(`[data-state="${state}"]`).removeClass('hidden');
        this.$el.find('.employee-holiday-ledger-list').toggleClass(
            'employee-holiday-ledger-list-hidden',
            state === 'empty' || state === 'error'
        );
    }

    showCollectionState() {
        if (!this.isRendered()) {
            return;
        }

        const count = Number.isFinite(Number(this.collection.total))
            ? Number(this.collection.total)
            : this.collection.length;
        const label = this.translate('ledgerEntryCount', 'messages', this.scope)
            .replace('{count}', String(count));

        this.$el.find('[data-role="entry-count"]').text(label);
        this.showLedgerState(this.collection.length ? 'ready' : 'empty');
    }

    updateFilterButtons() {
        if (!this.isRendered() || !this.canFilterEmployees) {
            return;
        }

        const selectedId = this.filterModel.get('assignedUserId') || null;
        this.$el.find('[data-action="ledger-show-all"]')
            .toggleClass('btn-primary', !selectedId)
            .toggleClass('btn-default', Boolean(selectedId))
            .attr('aria-pressed', !selectedId ? 'true' : 'false');
        this.$el.find('[data-action="ledger-show-own"]')
            .toggleClass('btn-primary', selectedId === this.getUser().id)
            .toggleClass('btn-default', selectedId !== this.getUser().id)
            .attr('aria-pressed', selectedId === this.getUser().id ? 'true' : 'false');
    }
});
