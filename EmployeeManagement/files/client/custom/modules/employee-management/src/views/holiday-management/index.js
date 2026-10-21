define([
    'employee-management:views/holiday-request/index',
], PersonalHolidayView => class extends PersonalHolidayView {
    templateContent = `
        <main class="employee-holiday-page employee-holiday-management-page">
            <header class="employee-holiday-hero employee-holiday-management-hero">
                <div class="employee-holiday-hero-copy">
                    <div class="employee-holiday-hero-icon" aria-hidden="true"><span class="fas fa-user-shield"></span></div>
                    <div>
                        <div class="employee-holiday-eyebrow">Employee Management</div>
                        <h3>{{translate 'Holiday Management' category='labels' scope='EmployeeHolidayRequest'}}</h3>
                        <p>{{translate 'holidayManagementIntro' category='messages' scope='EmployeeHolidayRequest'}}</p>
                    </div>
                </div>
                <a class="btn btn-default" href="#EmployeeHolidayRequest">
                    <span class="fas fa-user" aria-hidden="true"></span>
                    {{translate 'My Holiday Requests' category='labels' scope='EmployeeHolidayRequest'}}
                </a>
            </header>

            <nav class="employee-holiday-management-options" aria-label="{{translate 'Holiday Management' category='labels' scope='EmployeeHolidayRequest'}}">
                {{#if canApprove}}
                <a class="employee-holiday-management-option{{#if approvalsActive}} is-active{{/if}}" href="#EmployeeHolidayManagement/approvals" data-section="approvals"{{#if approvalsActive}} aria-current="page"{{/if}}>
                    <span class="employee-holiday-management-option-icon fas fa-clipboard-check" aria-hidden="true"></span>
                    <span><strong>{{translate 'Holiday Approvals' category='labels' scope='EmployeeHolidayRequest'}}</strong><small>{{translate 'holidayApprovalsCardHelp' category='messages' scope='EmployeeHolidayRequest'}}</small></span>
                </a>
                <a class="employee-holiday-management-option{{#if cancellationsActive}} is-active{{/if}}" href="#EmployeeHolidayManagement/cancellations" data-section="cancellations"{{#if cancellationsActive}} aria-current="page"{{/if}}>
                    <span class="employee-holiday-management-option-icon fas fa-calendar-times" aria-hidden="true"></span>
                    <span><strong>{{translate 'Cancellation Requests' category='labels' scope='EmployeeHolidayRequest'}}</strong><small>{{translate 'holidayCancellationsCardHelp' category='messages' scope='EmployeeHolidayRequest'}}</small></span>
                </a>
                <a class="employee-holiday-management-option{{#if approvedActive}} is-active{{/if}}" href="#EmployeeHolidayManagement/approved" data-section="approved"{{#if approvedActive}} aria-current="page"{{/if}}>
                    <span class="employee-holiday-management-option-icon fas fa-calendar-check" aria-hidden="true"></span>
                    <span><strong>{{translate 'Approved Holidays' category='labels' scope='EmployeeHolidayRequest'}}</strong><small>{{translate 'approvedHolidaysCardHelp' category='messages' scope='EmployeeHolidayRequest'}}</small></span>
                </a>
                {{/if}}
                {{#if canManagedBooking}}
                <a class="employee-holiday-management-option{{#if bookingActive}} is-active{{/if}}" href="#EmployeeHolidayManagement/book" data-section="book"{{#if bookingActive}} aria-current="page"{{/if}}>
                    <span class="employee-holiday-management-option-icon fas fa-user-plus" aria-hidden="true"></span>
                    <span><strong>{{translate 'Book for Employee' category='labels' scope='EmployeeHolidayRequest'}}</strong><small>{{translate 'managedBookingPanelHelp' category='messages' scope='EmployeeHolidayRequest'}}</small></span>
                </a>
                {{/if}}
                {{#if canVerify}}
                <a class="employee-holiday-management-option" href="#EmployeeHolidayDocumentVerification" data-section="verify">
                    <span class="employee-holiday-management-option-icon fas fa-shield-alt" aria-hidden="true"></span>
                    <span><strong>{{translate 'Verify Holiday Document' category='labels' scope='EmployeeHolidayRequest'}}</strong><small>{{translate 'holidayVerificationCardHelp' category='messages' scope='EmployeeHolidayRequest'}}</small></span>
                </a>
                {{/if}}
            </nav>

            {{#if bookingActive}}
            <section class="employee-holiday-requests employee-holiday-management-booking">
                <div class="employee-holiday-list-heading">
                    <div>
                        <span class="fas fa-user-plus" aria-hidden="true"></span>
                        <h4>{{translate 'Book for Employee' category='labels' scope='EmployeeHolidayRequest'}}</h4>
                    </div>
                    <p>{{translate 'managedBookingPanelHelp' category='messages' scope='EmployeeHolidayRequest'}}</p>
                </div>
                <div class="employee-holiday-management-booking-body">
                    <span class="employee-holiday-management-booking-icon fas fa-calendar-plus" aria-hidden="true"></span>
                    <div>
                        <h4>{{translate 'Create Managed Holiday Request' category='labels' scope='EmployeeHolidayRequest'}}</h4>
                        <p>{{translate 'managedBookingHelp' category='messages' scope='EmployeeHolidayRequest'}}</p>
                    </div>
                    <button class="btn btn-primary" data-action="managed">
                        <span class="fas fa-plus" aria-hidden="true"></span>
                        {{translate 'Book for Employee' category='labels' scope='EmployeeHolidayRequest'}}
                    </button>
                </div>
            </section>
            {{/if}}

            {{#if showList}}
            <section class="employee-holiday-requests" data-name="management-list">
                <div class="employee-holiday-list-heading">
                    <div>
                        <span class="fas {{listIcon}}" aria-hidden="true"></span>
                        <h4>{{listHeading}}</h4>
                        <span class="employee-holiday-count">{{rowCount}}</span>
                    </div>
                    <p>{{sectionHelp}}</p>
                </div>

                {{#unless hasRows}}
                <div class="employee-holiday-empty" data-name="empty-list">
                    <div class="employee-holiday-empty-icon"><span class="far fa-calendar" aria-hidden="true"></span></div>
                    <h4>{{emptyListMessage}}</h4>
                    <p>{{emptyListHelp}}</p>
                </div>
                {{/unless}}

                <div class="employee-holiday-request-list">{{#each rows}}
                    <article class="employee-holiday-request employee-holiday-request-{{statusClass}}" data-id="{{id}}">
                        <div class="employee-holiday-request-topline">
                            <div class="employee-holiday-request-date"><span class="far fa-calendar-alt" aria-hidden="true"></span><strong>{{dateRange}}</strong></div>
                            <span class="employee-holiday-status employee-holiday-status-{{statusClass}}"><span class="fas {{statusIcon}}" aria-hidden="true"></span>{{statusLabel}}</span>
                        </div>
                        <div class="employee-holiday-request-content">
                            <div class="employee-holiday-request-main">
                                <div class="employee-holiday-assignee"><span class="far fa-user" aria-hidden="true"></span>{{assignedUserName}}</div>
                                {{#if description}}<p class="employee-holiday-description">{{description}}</p>{{/if}}
                                {{#unless description}}<p class="employee-holiday-description employee-holiday-description-empty">{{translate 'No Description' category='labels' scope='EmployeeHolidayRequest'}}</p>{{/unless}}
                            </div>
                            <div class="employee-holiday-days"><strong>{{days}}</strong><span>{{translate 'days' category='fields' scope='EmployeeHolidayRequest'}}</span></div>
                        </div>
                        {{#if cancellationReason}}<div class="employee-holiday-notice employee-holiday-notice-warning"><span class="fas fa-comment-alt" aria-hidden="true"></span><div><strong>{{translate 'Cancellation Reason' category='labels' scope='EmployeeHolidayRequest'}}</strong><p>{{cancellationReason}}</p></div></div>{{/if}}
                        {{#if processing}}<div class="employee-holiday-notice employee-holiday-notice-info" data-name="approval-state"><span class="fas fa-cog fa-spin" aria-hidden="true"></span><div>{{approvalState}}</div></div>{{/if}}
                        {{#if approvalError}}<div class="employee-holiday-notice employee-holiday-notice-danger" data-name="approval-error"><span class="fas fa-exclamation-triangle" aria-hidden="true"></span><div>{{approvalError}}</div></div>{{/if}}
                        {{#if hasDocuments}}<div class="employee-holiday-documents"><span class="fas fa-paperclip" aria-hidden="true"></span><div>{{#each documents}}<a data-name="approval-document" href="{{url}}">{{name}}</a>{{/each}}</div></div>{{/if}}
                        <div class="employee-holiday-request-actions">
                            <a class="btn btn-default btn-sm" href="#EmployeeHolidayRequest/view/{{id}}"><span class="far fa-eye" aria-hidden="true"></span> {{translate 'View'}}</a>
                            {{#if approvable}}<button class="btn btn-success btn-sm" data-action="approve"><span class="fas fa-check" aria-hidden="true"></span> {{translate 'Approve Holiday' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                            {{#if retryable}}<button class="btn btn-warning btn-sm" data-action="retryApproval"><span class="fas fa-redo" aria-hidden="true"></span> {{translate 'Retry Approval' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                            {{#if editable}}<button class="btn btn-default btn-sm" data-action="edit"><span class="fas fa-pencil-alt" aria-hidden="true"></span> {{translate 'Edit'}}</button><button class="btn btn-danger btn-sm" data-action="withdraw"><span class="fas fa-times" aria-hidden="true"></span> {{translate 'Remove'}}</button>{{/if}}
                            {{#if rejectable}}<button class="btn btn-danger btn-sm" data-action="reject"><span class="fas fa-times" aria-hidden="true"></span> {{translate 'Reject Holiday' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                            {{#if cancellable}}<button class="btn btn-danger btn-sm" data-action="cancel"><span class="fas fa-ban" aria-hidden="true"></span> {{translate 'Cancel Holiday' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                            {{#if cancellationPending}}<button class="btn btn-danger btn-sm" data-action="acceptCancellation"><span class="fas fa-check" aria-hidden="true"></span> {{translate 'Approve Cancellation' category='labels' scope='EmployeeHolidayRequest'}}</button><button class="btn btn-default btn-sm" data-action="denyCancellation"><span class="fas fa-times" aria-hidden="true"></span> {{translate 'Reject Cancellation' category='labels' scope='EmployeeHolidayRequest'}}</button>{{/if}}
                        </div>
                    </article>
                {{/each}}</div>
            </section>
            {{/if}}
        </main>
    `;

    events = {
        'click [data-action="managed"]': 'openManagedBooking',
        'click article [data-action]': 'act',
    };

    setup() {
        this.section = this.options.section;
        this.canApprove = this.options.canApprove === true;
        this.canManagedBooking = this.options.canManagedBooking === true;
        this.canVerify = this.options.canVerify === true;
        this.showList = ['approvals', 'cancellations', 'approved'].includes(this.section);

        if (!this.showList) {
            this.rows = [];
            this.pollStopped = false;
            return;
        }

        this.initialListMode = this.section === 'approved' ? 'approved' : 'queue';
        this.loadBalanceEnabled = false;
        super.setup();
    }

    data() {
        const data = this.showList ? super.data() : {rows:[], hasRows:false, rowCount:0};
        const sectionLabels = {
            approvals: this.translate('Holiday Approvals', 'labels', 'EmployeeHolidayRequest'),
            cancellations: this.translate('Cancellation Requests', 'labels', 'EmployeeHolidayRequest'),
            approved: this.translate('Approved Holidays', 'labels', 'EmployeeHolidayRequest'),
        };
        const sectionIcons = {
            approvals: 'fa-clipboard-check',
            cancellations: 'fa-calendar-times',
            approved: 'fa-calendar-check',
        };
        const sectionHelp = {
            approvals: this.translate('holidayApprovalsSectionHelp', 'messages', 'EmployeeHolidayRequest'),
            cancellations: this.translate('holidayCancellationsSectionHelp', 'messages', 'EmployeeHolidayRequest'),
            approved: this.translate('approvedHolidayManagerHelp', 'messages', 'EmployeeHolidayRequest'),
        };
        const emptyMessages = {
            approvals: this.translate('No Pending Approvals', 'messages', 'EmployeeHolidayRequest'),
            cancellations: this.translate('No Pending Cancellations', 'messages', 'EmployeeHolidayRequest'),
            approved: this.translate('No Approved Holidays', 'messages', 'EmployeeHolidayRequest'),
        };

        return {
            ...data,
            canApprove:this.canApprove,
            canManagedBooking:this.canManagedBooking,
            canVerify:this.canVerify,
            approvalsActive:this.section === 'approvals',
            cancellationsActive:this.section === 'cancellations',
            approvedActive:this.section === 'approved',
            bookingActive:this.section === 'book',
            showList:this.showList,
            listHeading:sectionLabels[this.section] || '',
            listIcon:sectionIcons[this.section] || 'fa-calendar',
            sectionHelp:sectionHelp[this.section] || '',
            emptyListMessage:emptyMessages[this.section] || '',
            emptyListHelp:this.translate('holidayManagementEmptyHelp', 'messages', 'EmployeeHolidayRequest'),
        };
    }

    filterResponseRows(rows) {
        if (this.section === 'approvals') {
            return rows.filter(row => ['Pending', 'ApprovalProcessing'].includes(row.status));
        }
        if (this.section === 'cancellations') {
            return rows.filter(row => row.status === 'CancellationPending');
        }
        return rows;
    }

    async openManagedBooking() {
        const modal = await this.createView(
            'booking',
            'employee-management:views/modals/managed-booking',
            {managed:true},
        );
        this.listenToOnce(modal, 'saved', () => {
            Espo.Ui.success(this.translate('managedBookingSaved', 'messages', 'EmployeeHolidayRequest'));
        });
        modal.render();
    }
});
