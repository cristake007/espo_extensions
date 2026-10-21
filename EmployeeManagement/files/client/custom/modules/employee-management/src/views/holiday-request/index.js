define(['view'], View => class extends View {
    // Private workspace adapter. Row content follows the original HolidayApprovals dashlet;
    // forms and reasons use the original modals, rather than a second form/widget system.
    templateContent = `
        <main class="employee-holiday-page">
        <header class="employee-holiday-hero">
            <div class="employee-holiday-hero-copy">
                <div class="employee-holiday-hero-icon" aria-hidden="true"><span class="fas fa-umbrella-beach"></span></div>
                <div>
                    <div class="employee-holiday-eyebrow">{{translate 'Holiday Workspace' category='labels' scope='EmployeeHolidayRequest'}}</div>
                    <h3>{{translate 'My Holiday Requests' category='labels' scope='EmployeeHolidayRequest'}}</h3>
                    <p>{{translate 'personalHolidayPageIntro' category='messages' scope='EmployeeHolidayRequest'}}</p>
                </div>
            </div>
            {{#if canBook}}
            <button class="btn btn-primary employee-holiday-primary-action" data-action="create">
                <span class="fas fa-plus" aria-hidden="true"></span>
                {{translate 'Request Time Off' category='labels' scope='EmployeeHolidayRequest'}}
            </button>
            {{/if}}
        </header>

        <section class="employee-holiday-balance" data-name="holiday-balance">
            <div class="employee-holiday-section-heading">
                <div>
                    <span class="employee-holiday-section-icon fas fa-wallet" aria-hidden="true"></span>
                    <div>
                        <h4>{{translate 'Holiday Balance' category='labels' scope='EmployeeHolidayRequest'}}</h4>
                        <p>{{translate 'holidayBalancePanelHelp' category='messages' scope='EmployeeHolidayRequest'}}</p>
                    </div>
                </div>
                {{#if profileInitialized}}
                <div class="employee-holiday-reset-date">
                    <span>{{translate 'Next Reset' category='labels' scope='EmployeeHolidayRequest'}}</span>
                    <strong data-name="next-reset-date">{{nextResetDate}}</strong>
                </div>
                {{/if}}
            </div>
            <div class="employee-holiday-section-body">
                {{#if balanceLoadFailed}}
                    <div class="employee-holiday-state employee-holiday-state-danger">
                        <span class="fas fa-exclamation-circle" aria-hidden="true"></span>
                        <div>{{translate 'Balance Unavailable' category='labels' scope='EmployeeHolidayRequest'}}</div>
                    </div>
                {{else}}
                    {{#if profileInitialized}}
                        <div class="employee-holiday-balance-grid">
                            <div class="employee-holiday-metric employee-holiday-metric-primary">
                                <div class="employee-holiday-metric-icon"><span class="fas fa-calendar-check" aria-hidden="true"></span></div>
                                <div class="employee-holiday-metric-copy">
                                    <span>{{translate 'Current Balance' category='labels' scope='EmployeeHolidayRequest'}}</span>
                                    <strong data-name="balance">{{balance}}</strong>
                                </div>
                            </div>
                            <div class="employee-holiday-metric employee-holiday-metric-success">
                                <div class="employee-holiday-metric-icon"><span class="fas fa-sun" aria-hidden="true"></span></div>
                                <div class="employee-holiday-metric-copy">
                                    <span>{{translate 'Days Available' category='labels' scope='EmployeeHolidayRequest'}}</span>
                                    <strong data-name="available-days">{{availableDays}}</strong>
                                </div>
                            </div>
                            <div class="employee-holiday-metric employee-holiday-metric-warning">
                                <div class="employee-holiday-metric-icon"><span class="fas fa-hourglass-half" aria-hidden="true"></span></div>
                                <div class="employee-holiday-metric-copy">
                                    <span>{{translate 'Reserved Pending' category='labels' scope='EmployeeHolidayRequest'}}</span>
                                    <strong data-name="pending-days">{{pendingDays}}</strong>
                                </div>
                            </div>
                            <div class="employee-holiday-metric employee-holiday-metric-info">
                                <div class="employee-holiday-metric-icon"><span class="fas fa-calendar-alt" aria-hidden="true"></span></div>
                                <div class="employee-holiday-metric-copy">
                                    <span>{{translate 'Annual Entitlement' category='labels' scope='EmployeeHolidayRequest'}}</span>
                                    <strong data-name="annual-entitlement">{{annualEntitlement}}</strong>
                                </div>
                            </div>
                        </div>
                    {{else}}
                        <div class="employee-holiday-state employee-holiday-state-warning">
                            <span class="fas fa-info-circle" aria-hidden="true"></span>
                            <div>{{translate 'Profile Not Ready' category='labels' scope='EmployeeHolidayRequest'}}</div>
                        </div>
                    {{/if}}
                {{/if}}
            </div>
        </section>

        {{#if hasManagerActions}}
        <section class="employee-holiday-manager" data-name="manager-actions">
            <div class="employee-holiday-manager-copy">
                <div class="employee-holiday-manager-icon"><span class="fas fa-user-shield" aria-hidden="true"></span></div>
                <div>
                    <h4>{{translate 'Manager Actions' category='labels' scope='EmployeeHolidayRequest'}}</h4>
                    <p>{{translate 'managerHolidayToolsHelp' category='messages' scope='EmployeeHolidayRequest'}}</p>
                </div>
            </div>
            <div class="employee-holiday-manager-actions">
                {{#if canManage}}<button class="btn btn-default" data-action="managed"><span class="fas fa-user-plus" aria-hidden="true"></span> {{translate 'Book for Employee' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                {{#if canQueue}}{{#unless queueMode}}<button class="btn btn-default" data-action="queue"><span class="fas fa-clipboard-check" aria-hidden="true"></span> {{translate 'Holiday Approvals' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/unless}}{{/if}}
                {{#if canQueue}}{{#unless approvedMode}}<button class="btn btn-default" data-action="approved"><span class="fas fa-calendar-check" aria-hidden="true"></span> {{translate 'Approved Holidays' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/unless}}{{/if}}
                {{#if managerMode}}<button class="btn btn-default" data-action="own"><span class="fas fa-user" aria-hidden="true"></span> {{translate 'My Holiday Requests' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
            </div>
        </section>
        {{/if}}

        <section class="employee-holiday-requests">
            <div class="employee-holiday-list-heading">
                <div>
                    <span class="fas {{listIcon}}" aria-hidden="true"></span>
                    <h4>{{listHeading}}</h4>
                    <span class="employee-holiday-count">{{rowCount}}</span>
                </div>
                {{#if showApprovalInfo}}<p>{{translate 'approvalJobsActive' category='messages' scope='EmployeeHolidayRequest'}}</p>{{/if}}
                {{#if showApprovedInfo}}<p>{{translate 'approvedHolidayManagerHelp' category='messages' scope='EmployeeHolidayRequest'}}</p>{{/if}}
            </div>

            {{#unless hasRows}}
            <div class="employee-holiday-empty" data-name="empty-list">
                <div class="employee-holiday-empty-icon"><span class="far fa-calendar" aria-hidden="true"></span></div>
                <h4>{{emptyListMessage}}</h4>
                <p>{{translate 'holidayEmptyStateHelp' category='messages' scope='EmployeeHolidayRequest'}}</p>
                {{#if canCreateFromEmpty}}<button class="btn btn-primary" data-action="create"><span class="fas fa-plus" aria-hidden="true"></span> {{translate 'Request Time Off' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
            </div>
            {{/unless}}

            <div class="employee-holiday-request-list">{{#each rows}}
                <article class="employee-holiday-request employee-holiday-request-{{statusClass}}" data-id="{{id}}">
                    <div class="employee-holiday-request-topline">
                        <div class="employee-holiday-request-date">
                            <span class="far fa-calendar-alt" aria-hidden="true"></span>
                            <strong>{{dateRange}}</strong>
                        </div>
                        <span class="employee-holiday-status employee-holiday-status-{{statusClass}}">
                            <span class="fas {{statusIcon}}" aria-hidden="true"></span>{{statusLabel}}
                        </span>
                    </div>

                    <div class="employee-holiday-request-content">
                        <div class="employee-holiday-request-main">
                            {{#if showAssignee}}<div class="employee-holiday-assignee"><span class="far fa-user" aria-hidden="true"></span>{{assignedUserName}}</div>{{/if}}
                            {{#if description}}<p class="employee-holiday-description">{{description}}</p>{{/if}}
                            {{#unless description}}<p class="employee-holiday-description employee-holiday-description-empty">{{translate 'No Description' category='labels' scope='EmployeeHolidayRequest'}}</p>{{/unless}}
                        </div>
                        <div class="employee-holiday-days">
                            <strong>{{days}}</strong>
                            <span>{{translate 'days' category='fields' scope='EmployeeHolidayRequest'}}</span>
                        </div>
                    </div>

                    {{#if cancellationReason}}<div class="employee-holiday-notice employee-holiday-notice-warning"><span class="fas fa-comment-alt" aria-hidden="true"></span><div><strong>{{translate 'Cancellation Reason' category='labels' scope='EmployeeHolidayRequest'}}</strong><p>{{cancellationReason}}</p></div></div>{{/if}}
                    {{#if processing}}<div class="employee-holiday-notice employee-holiday-notice-info" data-name="approval-state"><span class="fas fa-cog fa-spin" aria-hidden="true"></span><div>{{approvalState}}</div></div>{{/if}}
                    {{#if approvalError}}<div class="employee-holiday-notice employee-holiday-notice-danger" data-name="approval-error"><span class="fas fa-exclamation-triangle" aria-hidden="true"></span><div>{{approvalError}}</div></div>{{/if}}

                    {{#if hasDocuments}}<div class="employee-holiday-documents"><span class="fas fa-paperclip" aria-hidden="true"></span><div>{{#each documents}}<a data-name="approval-document" href="{{url}}">{{name}}</a>{{/each}}</div></div>{{/if}}

                    <div class="employee-holiday-request-actions">
                        <a class="btn btn-default btn-sm" href="#EmployeeHolidayRequest/view/{{id}}"><span class="far fa-eye" aria-hidden="true"></span> {{translate 'View'}}</a>
                        {{#if approvable}}<button class="btn btn-success btn-sm" data-action="approve"><span class="fas fa-check" aria-hidden="true"></span> {{translate 'Approve Holiday' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                        {{#if retryable}}<button class="btn btn-warning btn-sm" data-action="retryApproval"><span class="fas fa-redo" aria-hidden="true"></span> {{translate 'Retry Approval' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                        {{#if editable}}<button class="btn btn-default btn-sm" data-action="edit"><span class="fas fa-pencil-alt" aria-hidden="true"></span> {{translate 'Edit'}}</button>
                            <button class="btn btn-danger btn-sm" data-action="withdraw"><span class="fas fa-times" aria-hidden="true"></span> {{translate 'Remove'}}</button>{{/if}}
                        {{#if rejectable}}<button class="btn btn-danger btn-sm" data-action="reject"><span class="fas fa-times" aria-hidden="true"></span> {{translate 'Reject Holiday' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                        {{#if requestCancellation}}<button class="btn btn-default btn-sm" data-action="requestCancellation"><span class="far fa-comment-alt" aria-hidden="true"></span> {{translate 'Request Cancellation' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                        {{#if cancellable}}<button class="btn btn-danger btn-sm" data-action="cancel"><span class="fas fa-ban" aria-hidden="true"></span> {{translate 'Cancel Holiday' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                        {{#if cancellationPending}}<button class="btn btn-danger btn-sm" data-action="acceptCancellation"><span class="fas fa-check" aria-hidden="true"></span> {{translate 'Approve Cancellation' category='labels' scope='EmployeeHolidayRequest'}}</button>
                            <button class="btn btn-default btn-sm" data-action="denyCancellation"><span class="fas fa-times" aria-hidden="true"></span> {{translate 'Reject Cancellation' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                    </div>
                </article>
            {{/each}}</div>
        </section>
        </main>
    `;

    events = {
        'click [data-action="create"]': function () { this.openForm(false); },
        'click [data-action="managed"]': function () { this.openForm(true); },
        'click [data-action="queue"]': function () { this.listMode = 'queue'; this.refresh(); },
        'click [data-action="approved"]': function () { this.listMode = 'approved'; this.refresh(); },
        'click [data-action="own"]': function () { this.listMode = 'own'; this.refresh(); },
        'click article [data-action]': 'act',
    };

    setup() {
        this.rows = []; this.listMode = 'own'; this.balanceInfo = null; this.balanceLoadFailed = false; this.wait(this.load());
        this.pollTimer=setInterval(()=>this.pollForUpdates().catch(()=>{}),3000);
        this.on('remove',()=>clearInterval(this.pollTimer));
    }
    data() {
        const balance = this.balanceInfo || {};
        return {
            rows:this.rows,
            hasRows:this.rows.length > 0,
            rowCount:this.rows.length,
            balanceLoadFailed:this.balanceLoadFailed,
            profileInitialized:balance.initialized === true,
            balance:this.formatDays(balance.balance),
            availableDays:this.formatDays(balance.availableDays),
            pendingDays:this.formatDays(balance.pendingDays),
            annualEntitlement:this.formatDays(balance.annualEntitlement),
            nextResetDate:balance.nextResetDate ? this.getDateTime().toDisplayDate(balance.nextResetDate) : this.translate('Not Set', 'labels', 'EmployeeHolidayRequest'),
            canBook:this.canBook,
            canManage:this.canManage,
            canQueue:this.canQueue,
            hasManagerActions:this.canManage || this.canQueue,
            managerMode:this.listMode !== 'own',
            queueMode:this.listMode === 'queue',
            approvedMode:this.listMode === 'approved',
            showApprovalInfo:this.listMode === 'queue' && this.canQueue,
            showApprovedInfo:this.listMode === 'approved' && this.canQueue,
            canCreateFromEmpty:this.canBook && this.listMode === 'own',
            listIcon:this.listMode === 'queue' ? 'fa-clipboard-check' : (this.listMode === 'approved' ? 'fa-calendar-check' : 'fa-history'),
            listHeading:this.listMode === 'queue' ? this.translate('Pending Approvals', 'labels', 'EmployeeHolidayRequest') : (this.listMode === 'approved' ? this.translate('Approved Holidays', 'labels', 'EmployeeHolidayRequest') : this.translate('My Holiday Requests', 'labels', 'EmployeeHolidayRequest')),
            emptyListMessage:this.listMode === 'queue' ? this.translate('No Pending Approvals', 'messages', 'EmployeeHolidayRequest') : (this.listMode === 'approved' ? this.translate('No Approved Holidays', 'messages', 'EmployeeHolidayRequest') : this.translate('No Holiday Requests', 'messages', 'EmployeeHolidayRequest')),
        };
    }

    formatDays(value) {
        if (value === null || value === undefined || value === '') { return '—'; }
        const number = Number(value);
        return Number.isFinite(number) ? String(Math.round(number * 100) / 100) : String(value);
    }

    afterRender() {
        if (this.options.openCreate && !this.createOpened && this.canBook) {
            this.createOpened = true;
            this.openForm(false);
        }
    }

    async load() {
        this.canBook = this.getAcl().checkScope('EmployeeHolidayBooking');
        this.canManage = this.getAcl().checkScope('EmployeeHolidayRequestManagement') && this.getAcl().getLevel('EmployeeHolidayRequest', 'read') === 'all';
        const response = await Espo.Ajax.getRequest(`EmployeeManagement/holiday/${this.listMode === 'own' ? 'requests' : this.listMode}`);
        this.requestSignature = this.makeRequestSignature(response.list || []);
        this.canQueue = response.canDecide === true;
        try {
            this.balanceInfo = await Espo.Ajax.getRequest('EmployeeManagement/holiday/balance');
            this.balanceLoadFailed = false;
        } catch (e) {
            this.balanceInfo = null;
            this.balanceLoadFailed = true;
        }
        this.rows = (response.list || []).map(row => {
            const documents = (row.documents || []).map(document => ({
                ...document,
                url:`${this.getBasePath()}?entryPoint=download&id=${encodeURIComponent(document.id)}`,
            }));

            return {
                ...row,
                dateRange: `${this.getDateTime().toDisplayDate(row.dateStartDate)} – ${this.getDateTime().toDisplayDate(row.dateEndDate)}`,
                statusLabel:this.getLanguage().translateOption(row.status, 'status', 'EmployeeHolidayRequest'),
                statusClass:this.statusClass(row.status),
                statusIcon:this.statusIcon(row.status),
                showAssignee:this.listMode !== 'own',
                editable:row.status === 'Pending' && (row.assignedUserId === this.getUser().id || this.canManage) && this.canBook,
                rejectable:this.canQueue && row.status === 'Pending',
                approvable:this.canQueue && response.approvalEnabled && row.status === 'Pending',
                processing:row.status === 'ApprovalProcessing',
                retryable:this.canQueue && row.status === 'ApprovalProcessing' && row.approvalRetryAvailable,
                documents,
                hasDocuments:documents.length > 0,
                requestCancellation:row.status === 'Approved' && row.assignedUserId === this.getUser().id && row.dateStartDate >= this.getDateTime().getToday(),
                cancellable:this.canQueue && row.status === 'Approved',
                cancellationPending:this.canQueue && row.status === 'CancellationPending',
            };
        });
    }
    makeRequestSignature(rows) {
        return JSON.stringify((rows || []).map(row => [
            row.id,
            row.status,
            row.accountingRevision,
            row.approvalState,
            row.approvalError,
            (row.documents || []).map(document => document.id),
        ]));
    }
    async pollForUpdates() {
        if (this.requestSignature === undefined || this.polling || this.refreshing ||
            (this.listMode !== 'own' && !this.rows.some(row => row.processing))) {
            return;
        }

        this.polling = true;
        try {
            const endpoint = this.listMode === 'own' ? 'requests' : this.listMode;
            const response = await Espo.Ajax.getRequest(`EmployeeManagement/holiday/${endpoint}`);
            const signature = this.makeRequestSignature(response.list || []);

            if (signature === this.requestSignature) {
                return;
            }

            const previousStatuses = new Map(this.rows.map(row => [row.id, row.status]));
            await this.refresh();

            if (this.listMode === 'own') {
                this.rows.forEach(row => {
                    const previous = previousStatuses.get(row.id);
                    if (previous && previous !== row.status && row.status === 'Approved') {
                        Espo.Ui.success(this.translate('holidayApproved', 'messages', 'EmployeeHolidayRequest'));
                    }
                    if (previous && previous !== row.status && row.status === 'Rejected') {
                        Espo.Ui.warning(this.translate('holidayRejected', 'messages', 'EmployeeHolidayRequest'));
                    }
                });
            }
        } finally {
            this.polling = false;
        }
    }
    statusClass(status) {
        return ({Pending:'pending',ApprovalProcessing:'processing',Approved:'approved',Rejected:'rejected',CancellationPending:'cancellation',Cancelled:'cancelled'})[status] || 'default';
    }
    statusIcon(status) {
        return ({Pending:'fa-clock',ApprovalProcessing:'fa-cog',Approved:'fa-check-circle',Rejected:'fa-times-circle',CancellationPending:'fa-exclamation-circle',Cancelled:'fa-ban'})[status] || 'fa-circle';
    }
    async refresh() { if(this.refreshing){return;}this.refreshing=true;try{await this.load();await this.reRender();}finally{this.refreshing=false;} }

    openForm(managed, record) {
        this.createView('booking', 'employee-management:views/modals/managed-booking', {managed, record}).then(modal => {
            this.listenToOnce(modal, 'saved', () => this.refresh()); modal.render();
        });
    }

    async act(event) {
        const button = $(event.currentTarget), row = this.rows.find(item => item.id === button.closest('[data-id]').data('id'));
        if (!row) { return; }
        const operation = button.data('action');
        if (operation === 'edit') { this.openForm(false, row); return; }
        const send = async reason => {
            // Stable key survives network retry; new payload/record revision creates a new command.
            const payload = {revision:row.accountingRevision};
            if (reason !== undefined) { payload.reason = reason; }
            const command = ['acceptCancellation', 'denyCancellation'].includes(operation) ? 'decideCancellation' : operation;
            if (command === 'decideCancellation') { payload.decision = operation === 'acceptCancellation' ? 'Cancelled' : 'Approved'; }
            const key = JSON.stringify([row.id, command, payload]);
            this.commandKeys ??= new Map();
            if (!this.commandKeys.has(key)) { this.commandKeys.set(key, `holiday:${Date.now()}:${Math.random().toString(16).slice(2)}`); }
            payload.idempotencyKey = this.commandKeys.get(key);
            button.prop('disabled', true);
            try { await Espo.Ajax.postRequest(`EmployeeManagement/holiday/${row.id}/${command}`, payload); await this.refresh(); }
            finally { button.prop('disabled', false); }
        };
        if (['requestCancellation', 'cancel'].includes(operation)) {
            const modal = await this.createView('reason', 'employee-management:views/modals/cancellation-reason', {});
            this.listenToOnce(modal, 'submit', send); modal.render(); return;
        }
        await this.confirm(this.translate('confirmation', 'messages'));
        return send();
    }
});
