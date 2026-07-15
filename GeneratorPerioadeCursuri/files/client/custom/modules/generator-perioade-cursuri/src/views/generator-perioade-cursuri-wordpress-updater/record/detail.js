define(
    'generator-perioade-cursuri:views/generator-perioade-cursuri-wordpress-updater/record/detail',
    ['views/record/detail'],
    function (DetailRecordView) {
        const SCOPE = 'GeneratorPerioadeCursuriWordPressUpdater';

        return class extends DetailRecordView {
            setup() {
                super.setup();

                this.wpUpdaterPreview = null;
                this.wpUpdaterConnected = false;
                this.wpUpdaterUser = null;
                this.wpUpdaterPassword = '';
                this.wpUpdaterBaseUrl = this.model.get('wpBaseUrl') || '';
                this.wpUpdaterUsername = this.model.get('wpUsername') || '';
                this.wpUpdaterBusy = null;
                this.wpUpdaterRowBusy = {};
                this.wpUpdaterGlobalError = null;
                this.wpUpdaterGlobalSuccess = null;
                this.wpUpdaterApplyingVerifiedSettings = false;

                this.addButton({
                    name: 'buildWordPressPreview',
                    label: 'wpUpdaterBuildPreview',
                    style: 'primary',
                    iconClass: 'fas fa-table'
                }, true);

                this.listenTo(this.model, 'change:wpScheduleFileId', () => {
                    this.invalidateWordPressPreview();
                });
                this.listenTo(this.model, 'change:wpBaseUrl change:wpUsername', () => {
                    this.wpUpdaterBaseUrl = this.model.get('wpBaseUrl') || '';
                    this.wpUpdaterUsername = this.model.get('wpUsername') || '';

                    if (this.wpUpdaterApplyingVerifiedSettings) {
                        return;
                    }

                    this.invalidateWordPressConnection();
                });
            }

            afterRender() {
                super.afterRender();
                this.renderWordPressWorkspace();
                this.updateWordPressPreviewButtonState();
            }

            remove() {
                this.clearWordPressPassword();
                this.wpUpdaterPreview = null;
                this.wpUpdaterUser = null;
                this.wpUpdaterRowBusy = {};

                return super.remove();
            }

            async actionBuildWordPressPreview() {
                if (this.wpUpdaterBusy || this.hasWordPressRowBusy() || !this.hasWordPressPreviewInput()) {
                    if (!this.wpUpdaterBusy && !this.hasWordPressRowBusy()) {
                        Espo.Ui.warning(this.translateMessage('wpUpdaterPreviewUnavailable'));
                    }

                    return;
                }

                this.wpUpdaterBusy = 'preview';
                this.wpUpdaterPreview = null;
                this.wpUpdaterGlobalError = null;
                this.wpUpdaterGlobalSuccess = null;
                this.renderWordPressWorkspace();
                this.updateWordPressPreviewButtonState();
                Espo.Ui.notify(this.translateMessage('wpUpdaterPreviewBuilding'));

                try {
                    const result = await this.postWordPressUpdaterRequest('preview', {});

                    this.wpUpdaterPreview = {
                        sourceFileId: result.previewSourceFileId,
                        rows: (Array.isArray(result.rows) ? result.rows : []).map(row => Object.assign({}, row, {
                            status: row.error ? this.translateLabel('wpUpdaterStatusError') :
                                this.translateLabel('wpUpdaterStatusReady'),
                            _localFinalDates: Array.isArray(row.finalDates) ? row.finalDates.slice() : [],
                            _localPayload: this.cloneWordPressValue(row.payload)
                        }))
                    };
                    this.wpUpdaterGlobalSuccess = this.translateMessage('wpUpdaterPreviewReady');
                    Espo.Ui.notify(false);
                    Espo.Ui.success(this.translateMessage('wpUpdaterPreviewReady'));
                } catch (error) {
                    this.wpUpdaterGlobalError = this.getWordPressUpdaterError(error);
                    Espo.Ui.notify(false);
                    Espo.Ui.error(this.wpUpdaterGlobalError);
                } finally {
                    this.wpUpdaterBusy = null;
                    this.renderWordPressWorkspace();
                    this.updateWordPressPreviewButtonState();
                }
            }

            async connectWordPress() {
                if (this.wpUpdaterBusy || this.hasWordPressRowBusy() || !this.model.id) {
                    return;
                }

                const workspace = this.getWordPressWorkspaceContainer();
                const baseUrlInput = workspace ? workspace.querySelector('[data-role="wp-base-url"]') : null;
                const usernameInput = workspace ? workspace.querySelector('[data-role="wp-username"]') : null;
                const passwordInput = workspace ? workspace.querySelector('[data-role="wp-password"]') : null;
                const baseUrl = baseUrlInput ? baseUrlInput.value.trim() : '';
                const username = usernameInput ? usernameInput.value.trim() : '';
                const password = passwordInput ? passwordInput.value : '';

                if (!baseUrl || !username || !password) {
                    Espo.Ui.warning(this.translateMessage('wpUpdaterCredentialsRequired'));

                    return;
                }

                this.wpUpdaterBaseUrl = baseUrl;
                this.wpUpdaterUsername = username;
                this.wpUpdaterPassword = password;
                this.wpUpdaterBusy = 'connect';
                this.wpUpdaterGlobalError = null;
                this.wpUpdaterGlobalSuccess = null;
                this.renderWordPressWorkspace({baseUrl: baseUrl, username: username});
                this.updateWordPressPreviewButtonState();
                Espo.Ui.notify(this.translateMessage('wpUpdaterConnecting'));

                try {
                    const result = await this.postWordPressUpdaterRequest('connect', {
                        wpBaseUrl: baseUrl,
                        wpUsername: username,
                        wpAppPassword: this.wpUpdaterPassword
                    });

                    this.wpUpdaterApplyingVerifiedSettings = true;

                    try {
                        this.model.set({
                            wpBaseUrl: result.wpBaseUrl || baseUrl,
                            wpUsername: result.wpUsername || username
                        });
                    } finally {
                        this.wpUpdaterApplyingVerifiedSettings = false;
                    }
                    this.wpUpdaterBaseUrl = result.wpBaseUrl || baseUrl;
                    this.wpUpdaterUsername = result.wpUsername || username;
                    this.wpUpdaterConnected = true;
                    this.wpUpdaterUser = result.user || null;
                    this.resetWordPressRemoteRows();
                    this.wpUpdaterGlobalSuccess = result.message || this.translateMessage('wpUpdaterConnected');
                    Espo.Ui.notify(false);
                    Espo.Ui.success(result.message || this.translateMessage('wpUpdaterConnected'));
                } catch (error) {
                    this.wpUpdaterConnected = false;
                    this.wpUpdaterUser = null;
                    this.resetWordPressRemoteRows();
                    this.wpUpdaterGlobalError = this.getWordPressUpdaterError(error);
                    Espo.Ui.notify(false);
                    Espo.Ui.error(this.wpUpdaterGlobalError);
                } finally {
                    this.wpUpdaterBusy = null;
                    this.renderWordPressWorkspace();
                    this.updateWordPressPreviewButtonState();
                }
            }

            disconnectWordPress() {
                if (this.hasWordPressRowBusy()) {
                    return;
                }

                this.wpUpdaterConnected = false;
                this.wpUpdaterUser = null;
                this.clearWordPressPassword();
                this.resetWordPressRemoteRows();
                this.wpUpdaterGlobalError = null;
                this.wpUpdaterGlobalSuccess = this.translateMessage('wpUpdaterDisconnected');
                this.renderWordPressWorkspace();
                Espo.Ui.success(this.translateMessage('wpUpdaterDisconnected'));
            }

            async runWordPressRowAction(sourceRow, action) {
                const row = this.findWordPressPreviewRow(sourceRow);

                if (!row || this.wpUpdaterBusy || this.wpUpdaterRowBusy[sourceRow] ||
                    !this.canRunWordPressRowAction(row, action)) {
                    return;
                }

                this.wpUpdaterRowBusy[sourceRow] = action;
                row.status = action === 'fetchDates' ?
                    this.translateMessage('wpUpdaterFetchingDates') :
                    this.translateMessage('wpUpdaterUpdatingRow');
                row.error = null;
                this.renderWordPressWorkspace();

                try {
                    const result = await this.postWordPressUpdaterRequest(action, {
                        previewSourceFileId: this.wpUpdaterPreview.sourceFileId,
                        sourceRow: sourceRow,
                        wpAppPassword: this.wpUpdaterPassword
                    });

                    Object.assign(row, result);
                    row.status = action === 'fetchDates' ? this.translateMessage('wpUpdaterDatesFetched') :
                        this.translateMessage(result.updated ? 'wpUpdaterRowUpdated' : 'wpUpdaterRowUnchanged');

                    if (action === 'updateRow') {
                        row.canUpdate = false;
                    }

                    Espo.Ui.success(this.translateMessage(
                        action === 'fetchDates' ? 'wpUpdaterDatesFetched' :
                            (result.updated ? 'wpUpdaterRowUpdated' : 'wpUpdaterRowUnchanged')
                    ));
                } catch (error) {
                    row.status = this.translateLabel('wpUpdaterStatusError');
                    row.error = this.getWordPressUpdaterError(error);

                    if (this.getErrorStatus(error) === 409) {
                        this.wpUpdaterGlobalError = row.error;
                        this.wpUpdaterGlobalSuccess = null;
                        this.wpUpdaterPreview = null;
                        Espo.Ui.error(row.error);
                    } else {
                        Espo.Ui.error(row.error);
                    }
                } finally {
                    delete this.wpUpdaterRowBusy[sourceRow];
                    this.renderWordPressWorkspace();
                    this.updateWordPressPreviewButtonState();
                }
            }

            renderWordPressWorkspace(inputValues) {
                const container = this.getWordPressWorkspaceContainer();

                if (!container) {
                    return;
                }

                const baseUrl = inputValues && inputValues.baseUrl !== undefined ?
                    inputValues.baseUrl : this.wpUpdaterBaseUrl;
                const username = inputValues && inputValues.username !== undefined ?
                    inputValues.username : this.wpUpdaterUsername;
                const rows = this.wpUpdaterPreview ? this.wpUpdaterPreview.rows : [];
                const disabled = this.wpUpdaterBusy || this.hasWordPressRowBusy();

                container.innerHTML = [
                    '<section class="wordpress-updater-workspace" aria-labelledby="wp-updater-workspace-title">',
                    '<div class="panel panel-default wordpress-updater-connection">',
                    '<div class="panel-heading"><h4 class="panel-title" id="wp-updater-workspace-title">',
                    this.escapeHtml(this.translateLabel('wpUpdaterConnection')), '</h4></div>',
                    '<div class="panel-body">',
                    '<p class="text-muted wordpress-updater-warning"><i class="fas fa-shield-alt" aria-hidden="true"></i> ',
                    this.escapeHtml(this.translateMessage('wpUpdaterVpnWarning')), '</p>',
                    '<div class="wordpress-updater-connection-grid">',
                    this.composeWordPressInput('wp-base-url', 'url', 'wpUpdaterBaseUrl', baseUrl),
                    this.composeWordPressInput('wp-username', 'text', 'wpUpdaterUsername', username),
                    this.composeWordPressInput('wp-password', 'password', 'wpUpdaterAppPassword', this.wpUpdaterPassword),
                    '</div>',
                    '<div class="wordpress-updater-connection-actions">',
                    '<button type="button" class="btn btn-default" data-action="wp-connect"', disabled ? ' disabled' : '', '>',
                    '<i class="fas fa-plug" aria-hidden="true"></i> ', this.escapeHtml(this.translateLabel('wpUpdaterConnect')), '</button>',
                    '<button type="button" class="btn btn-default" data-action="wp-disconnect"',
                    disabled ? ' disabled' : '', '>',
                    this.escapeHtml(this.translateLabel('wpUpdaterDisconnect')), '</button>',
                    '<div class="wordpress-updater-connection-status" role="status" aria-live="polite">',
                    this.composeWordPressConnectionStatus(),
                    '</div></div>',
                    '</div></div>',
                    '<div class="panel panel-default wordpress-updater-results">',
                    '<div class="panel-heading"><h4 class="panel-title">',
                    this.escapeHtml(this.translateLabel('wpUpdaterResults')), '</h4></div>',
                    '<div class="panel-body">',
                    '<div class="wordpress-updater-global-status" role="status" aria-live="polite">',
                    this.composeWordPressGlobalStatus(rows), '</div>',
                    this.wpUpdaterPreview ? this.composeWordPressResultsTable(rows) : '',
                    '</div></div>',
                    '</section>'
                ].join('');

                this.bindWordPressWorkspaceEvents(container);
                this.setupWordPressTableScrolling(container);
            }

            getWordPressWorkspaceContainer() {
                let container = this.element.querySelector('[data-name="wordpress-updater-workspace"]');

                if (container) {
                    return container;
                }

                const recordContainer = this.element.querySelector('.record') || this.element;

                container = document.createElement('div');
                container.dataset.name = 'wordpress-updater-workspace';
                recordContainer.appendChild(container);

                return container;
            }

            bindWordPressWorkspaceEvents(container) {
                const connect = container.querySelector('[data-action="wp-connect"]');
                const disconnect = container.querySelector('[data-action="wp-disconnect"]');

                if (connect) {
                    connect.addEventListener('click', () => this.connectWordPress());
                }

                if (disconnect) {
                    disconnect.addEventListener('click', () => this.disconnectWordPress());
                }

                const baseUrlInput = container.querySelector('[data-role="wp-base-url"]');
                const usernameInput = container.querySelector('[data-role="wp-username"]');
                const passwordInput = container.querySelector('[data-role="wp-password"]');

                if (baseUrlInput) {
                    baseUrlInput.addEventListener('input', () => {
                        this.wpUpdaterBaseUrl = baseUrlInput.value;
                    });
                }

                if (usernameInput) {
                    usernameInput.addEventListener('input', () => {
                        this.wpUpdaterUsername = usernameInput.value;
                    });
                }

                if (passwordInput) {
                    passwordInput.addEventListener('input', () => {
                        this.wpUpdaterPassword = passwordInput.value;
                    });
                }

                Array.from(container.querySelectorAll('[data-row-action]')).forEach(button => {
                    button.addEventListener('click', () => {
                        this.runWordPressRowAction(Number(button.dataset.sourceRow), button.dataset.rowAction);
                    });
                });
            }

            composeWordPressInput(role, type, label, value) {
                return [
                    '<label class="wordpress-updater-input">',
                    '<span class="control-label">', this.escapeHtml(this.translateLabel(label)), '</span>',
                    '<input class="form-control" data-role="', role, '" type="', type, '" value="',
                    this.escapeHtml(value), '" autocomplete="off"',
                    this.wpUpdaterBusy ? ' disabled' : '', '>',
                    '</label>'
                ].join('');
            }

            composeWordPressConnectionStatus() {
                if (this.wpUpdaterBusy === 'connect') {
                    return '<span class="text-muted"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> ' +
                        this.escapeHtml(this.translateMessage('wpUpdaterConnecting')) + '</span>';
                }

                if (!this.wpUpdaterConnected) {
                    return '<span class="text-muted">' +
                        this.escapeHtml(this.translateMessage('wpUpdaterNotConnected')) + '</span>';
                }

                const identity = this.getWordPressUserIdentity(this.wpUpdaterUser);

                return '<span class="text-primary"><i class="fas fa-check" aria-hidden="true"></i> ' +
                    this.escapeHtml(this.translateMessage('wpUpdaterConnectedAs').replace('{user}', identity)) + '</span>';
            }

            composeWordPressGlobalStatus(rows) {
                if (this.wpUpdaterBusy === 'preview') {
                    return '<span class="text-muted"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> ' +
                        this.escapeHtml(this.translateMessage('wpUpdaterPreviewBuilding')) + '</span>';
                }

                if (this.wpUpdaterGlobalError) {
                    return '<span class="wordpress-updater-row-error" role="alert">' +
                        this.escapeHtml(this.wpUpdaterGlobalError) + '</span>';
                }

                if (this.wpUpdaterGlobalSuccess) {
                    return '<span class="text-primary"><i class="fas fa-check" aria-hidden="true"></i> ' +
                        this.escapeHtml(this.wpUpdaterGlobalSuccess) + '</span>';
                }

                if (!this.wpUpdaterPreview) {
                    return '<span class="text-muted">' +
                        this.escapeHtml(this.translateMessage('wpUpdaterNoPreview')) + '</span>';
                }

                return '<span class="text-primary">' + this.escapeHtml(
                    this.translateMessage('wpUpdaterPreviewSummary').replace('{count}', String(rows.length))
                ) + '</span>';
            }

            composeWordPressResultsTable(rows) {
                return [
                    '<div class="wordpress-updater-top-scroll" data-role="wp-top-scroll" tabindex="0" aria-label="',
                    this.escapeHtml(this.translateLabel('wpUpdaterHorizontalScroll')), '"><div></div></div>',
                    '<div class="table-responsive wordpress-updater-table-scroll" data-role="wp-table-scroll" tabindex="0">',
                    '<table class="table table-bordered table-striped table-hover">',
                    '<thead><tr>',
                    '<th scope="col">', this.escapeHtml(this.translateLabel('wpUpdaterCourse')), '</th>',
                    '<th scope="col">', this.escapeHtml(this.translateLabel('wpUpdaterFileDates')), '</th>',
                    '<th scope="col">', this.escapeHtml(this.translateLabel('wpUpdaterExistingDates')), '</th>',
                    '<th scope="col">', this.escapeHtml(this.translateLabel('wpUpdaterFinalProgram')), '</th>',
                    '<th scope="col">', this.escapeHtml(this.translateLabel('wpUpdaterStatus')), '</th>',
                    '<th scope="col">', this.escapeHtml(this.translateLabel('wpUpdaterActions')), '</th>',
                    '</tr></thead><tbody>',
                    rows.length ? rows.map(row => this.composeWordPressResultRow(row)).join('') :
                        '<tr><td colspan="6" class="text-muted">' +
                        this.escapeHtml(this.translateMessage('wpUpdaterNoRows')) + '</td></tr>',
                    '</tbody></table></div>'
                ].join('');
            }

            composeWordPressResultRow(row) {
                const busyAction = this.wpUpdaterRowBusy[row.sourceRow] || null;
                const fetchDisabled = !this.canRunWordPressRowAction(row, 'fetchDates') || !!busyAction;
                const updateDisabled = !this.canRunWordPressRowAction(row, 'updateRow') || !!busyAction;

                return [
                    '<tr data-source-row="', this.escapeHtml(row.sourceRow), '">',
                    '<td class="wordpress-updater-course">', this.composeWordPressCourse(row), '</td>',
                    '<td>', this.composeWordPressDates(row.excelDates), '</td>',
                    '<td>', row.currentDatesLoaded ? this.composeWordPressDates(row.existingValidDates) :
                        '<span class="text-muted">' + this.escapeHtml(this.translateLabel('wpUpdaterNotFetched')) + '</span>', '</td>',
                    '<td>', this.composeWordPressDates(row.finalDates), this.composeWordPressPayload(row), '</td>',
                    '<td>', this.composeWordPressRowStatus(row, busyAction), '</td>',
                    '<td class="wordpress-updater-row-actions">',
                    '<button type="button" class="btn btn-default btn-sm" data-row-action="fetchDates" data-source-row="',
                    this.escapeHtml(row.sourceRow), '"', fetchDisabled ? ' disabled' : '', '>',
                    this.escapeHtml(this.translateLabel('wpUpdaterFetchDates')), '</button>',
                    '<button type="button" class="btn btn-default btn-sm" data-row-action="updateRow" data-source-row="',
                    this.escapeHtml(row.sourceRow), '"', updateDisabled ? ' disabled' : '', '>',
                    this.escapeHtml(this.translateLabel('wpUpdaterUpdateRow')), '</button>',
                    '</td></tr>'
                ].join('');
            }

            composeWordPressCourse(row) {
                const title = row.title || this.translateLabel('wpUpdaterUntitledCourse');
                const suffix = '<div class="text-muted">' +
                    this.escapeHtml(this.translateLabel('wpUpdaterSourceRow').replace('{row}', String(row.sourceRow))) + '</div>';

                if (!this.isSafeWordPressPermalink(row.permalink)) {
                    return '<strong>' + this.escapeHtml(title) + '</strong>' + suffix;
                }

                return '<a class="text-primary" href="' + this.escapeHtml(row.permalink) +
                    '" target="_blank" rel="noopener noreferrer"><strong>' + this.escapeHtml(title) +
                    '</strong></a>' + suffix;
            }

            composeWordPressDates(values) {
                const dates = Array.isArray(values) ? values.filter(value => typeof value === 'string' && value !== '') : [];

                if (!dates.length) {
                    return '<span class="text-muted">' + this.escapeHtml(this.translateLabel('wpUpdaterNone')) + '</span>';
                }

                return '<ul class="wordpress-updater-date-list">' + dates.map(value =>
                    '<li>' + this.escapeHtml(value) + '</li>'
                ).join('') + '</ul>';
            }

            composeWordPressPayload(row) {
                if (!row.payload) {
                    return '';
                }

                return [
                    '<details class="wordpress-updater-payload">',
                    '<summary>', this.escapeHtml(this.translateLabel('wpUpdaterReviewPayload')), '</summary>',
                    '<pre>', this.escapeHtml(JSON.stringify(row.payload, null, 2)), '</pre>',
                    '</details>'
                ].join('');
            }

            composeWordPressRowStatus(row, busyAction) {
                if (busyAction) {
                    return '<span class="text-muted"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> ' +
                        this.escapeHtml(row.status || '') + '</span>';
                }

                const status = row.status || this.translateLabel('wpUpdaterStatusReady');
                const error = row.error ? '<div class="wordpress-updater-row-error" role="alert">' +
                    this.escapeHtml(row.error) + '</div>' : '';

                return '<span class="' + (row.error ? 'text-muted' : 'text-primary') + '">' +
                    this.escapeHtml(status) + '</span>' + error;
            }

            setupWordPressTableScrolling(container) {
                const topScroller = container.querySelector('[data-role="wp-top-scroll"]');
                const tableScroller = container.querySelector('[data-role="wp-table-scroll"]');
                const table = tableScroller ? tableScroller.querySelector('table') : null;
                const topInner = topScroller ? topScroller.firstElementChild : null;

                if (!topScroller || !tableScroller || !table || !topInner) {
                    return;
                }

                topInner.style.width = table.scrollWidth + 'px';
                topScroller.addEventListener('scroll', () => {
                    tableScroller.scrollLeft = topScroller.scrollLeft;
                });
                tableScroller.addEventListener('scroll', () => {
                    topScroller.scrollLeft = tableScroller.scrollLeft;
                });
            }

            canRunWordPressRowAction(row, action) {
                if (!this.wpUpdaterConnected || !this.wpUpdaterPassword || !this.wpUpdaterPreview || this.wpUpdaterBusy) {
                    return false;
                }

                return action === 'fetchDates' ? row.canFetch === true : row.canUpdate === true;
            }

            hasWordPressPreviewInput() {
                return !!this.model.id && !!this.model.get('wpScheduleFileId');
            }

            hasWordPressRowBusy() {
                return Object.keys(this.wpUpdaterRowBusy).length > 0;
            }

            findWordPressPreviewRow(sourceRow) {
                const rows = this.wpUpdaterPreview ? this.wpUpdaterPreview.rows : [];

                return rows.find(row => Number(row.sourceRow) === Number(sourceRow)) || null;
            }

            invalidateWordPressPreview() {
                this.wpUpdaterPreview = null;
                this.wpUpdaterRowBusy = {};
                this.wpUpdaterGlobalError = null;
                this.wpUpdaterGlobalSuccess = null;
                this.renderWordPressWorkspace();
                this.updateWordPressPreviewButtonState();
            }

            invalidateWordPressConnection() {
                this.wpUpdaterConnected = false;
                this.wpUpdaterUser = null;
                this.clearWordPressPassword();
                this.resetWordPressRemoteRows();
                this.wpUpdaterGlobalError = null;
                this.wpUpdaterGlobalSuccess = null;
                this.renderWordPressWorkspace();
            }

            resetWordPressRemoteRows() {
                const rows = this.wpUpdaterPreview ? this.wpUpdaterPreview.rows : [];

                rows.forEach(row => {
                    row.postId = null;
                    row.existingValidDates = [];
                    row.currentDatesLoaded = false;
                    row.finalDates = Array.isArray(row._localFinalDates) ? row._localFinalDates.slice() : [];
                    row.payload = this.cloneWordPressValue(row._localPayload);
                    row.canUpdate = !row.error && row.canFetch && Array.isArray(row.finalDates) && row.finalDates.length > 0;
                    row.status = row.error ? this.translateLabel('wpUpdaterStatusError') :
                        this.translateLabel('wpUpdaterStatusReady');
                });
            }

            clearWordPressPassword() {
                this.wpUpdaterPassword = '';
                const input = this.element ? this.element.querySelector('[data-role="wp-password"]') : null;

                if (input) {
                    input.value = '';
                }
            }

            updateWordPressPreviewButtonState() {
                const button = this.element.querySelector('[data-action="buildWordPressPreview"]');

                if (!button) {
                    return;
                }

                const unavailable = !this.hasWordPressPreviewInput();
                const disabled = unavailable || !!this.wpUpdaterBusy || this.hasWordPressRowBusy();

                button.disabled = disabled;
                button.classList.toggle('disabled', disabled);
                button.title = disabled ? this.translateMessage(
                    unavailable ? 'wpUpdaterPreviewUnavailable' : 'wpUpdaterBusy'
                ) : '';
            }

            postWordPressUpdaterRequest(action, payload) {
                return Espo.Ajax.postRequest(
                    'GeneratorPerioadeCursuriWordPressUpdater/' + encodeURIComponent(this.model.id) + '/' + action,
                    payload
                );
            }

            cloneWordPressValue(value) {
                if (value === null || value === undefined) {
                    return value;
                }

                return JSON.parse(JSON.stringify(value));
            }

            getWordPressUserIdentity(user) {
                if (!user || typeof user !== 'object') {
                    return this.model.get('wpUsername') || this.translateLabel('wpUpdaterUnknownUser');
                }

                return user.name || user.displayName || user.slug || this.model.get('wpUsername') ||
                    this.translateLabel('wpUpdaterUnknownUser');
            }

            isSafeWordPressPermalink(value) {
                if (typeof value !== 'string' || value === '') {
                    return false;
                }

                try {
                    const url = new URL(value);

                    return url.protocol === 'http:' || url.protocol === 'https:';
                } catch (error) {
                    return false;
                }
            }

            getErrorStatus(error) {
                return error && (error.status || (error.xhr && error.xhr.status)) || null;
            }

            getWordPressUpdaterError(error) {
                const fallback = this.translateMessage('wpUpdaterOperationFailed');

                if (!error) {
                    return fallback;
                }

                if (error.responseJSON && typeof error.responseJSON.error === 'string') {
                    return error.responseJSON.error;
                }

                if (error.xhr && error.xhr.responseJSON && typeof error.xhr.responseJSON.error === 'string') {
                    return error.xhr.responseJSON.error;
                }

                if (error.responseText && error.responseText.charAt(0) === '{') {
                    try {
                        const data = JSON.parse(error.responseText);

                        if (typeof data.error === 'string') {
                            return data.error;
                        }
                    } catch (parseError) {}
                }

                if (typeof error.getResponseHeader === 'function') {
                    const reason = error.getResponseHeader('X-Status-Reason');

                    if (reason) {
                        return reason;
                    }
                }

                return fallback;
            }

            translateLabel(key) {
                return this.translate(key, 'labels', SCOPE);
            }

            translateMessage(key) {
                return this.translate(key, 'messages', SCOPE);
            }

            escapeHtml(value) {
                return String(value === null || value === undefined ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
        };
    }
);
