define(['view'], View => class extends View {
    templateContent = `
        <main class="employee-holiday-page employee-holiday-verification-page">
            <header class="employee-holiday-hero">
                <div class="employee-holiday-hero-copy">
                    <div class="employee-holiday-hero-icon" aria-hidden="true"><span class="fas fa-shield-alt"></span></div>
                    <div>
                        <div class="employee-holiday-eyebrow">Employee Management</div>
                        <h3>Verificare document de concediu</h3>
                        <p>Verificați documentul înregistrat și, opțional, integritatea exactă a fișierului DOCX.</p>
                    </div>
                </div>
                <a class="btn btn-default" href="#EmployeeManagement/holiday"><span class="fas fa-arrow-left"></span> Înapoi la concedii</a>
            </header>
            <section class="employee-holiday-requests employee-holiday-verification-card">
                <form data-name="verification-form" class="employee-holiday-verification-form">
                    <div class="form-group">
                        <label for="holiday-verification-code">Cod de verificare</label>
                        <input id="holiday-verification-code" class="form-control" name="code" maxlength="32"
                            autocomplete="off" spellcheck="false" placeholder="32 caractere hexazecimale" required>
                    </div>
                    <div class="form-group">
                        <label for="holiday-verification-file">Fișier DOCX (opțional)</label>
                        <input id="holiday-verification-file" class="form-control" name="document" type="file"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        <p class="help-block">Limita fișierului: 10 MB. Fișierul este comparat în memorie și nu este păstrat.</p>
                    </div>
                    <button class="btn btn-primary" type="submit" data-action="verify"><span class="fas fa-search"></span> Verifică</button>
                </form>
                <div class="employee-holiday-verification-result hidden" data-name="verification-result" aria-live="polite"></div>
            </section>
        </main>
    `;

    events = {'submit [data-name="verification-form"]': 'verify'};

    async verify(event) {
        event.preventDefault();
        const button = this.$el.find('[data-action="verify"]');
        const code = String(this.$el.find('[name="code"]').val() || '').trim().toUpperCase();
        const file = this.$el.find('[name="document"]')[0].files[0] || null;

        if (!/^[A-F0-9]{32}$/.test(code)) {
            this.showMessage('danger', 'Codul trebuie să conțină exact 32 de caractere hexazecimale.');
            return;
        }
        if (file && (file.size > 10 * 1024 * 1024 || !/\.docx$/i.test(file.name))) {
            this.showMessage('danger', 'Selectați un fișier DOCX de maximum 10 MB.');
            return;
        }

        button.prop('disabled', true);
        try {
            const payload = {code};
            if (file) {
                payload.fileName = file.name;
                payload.fileContents = await this.readBase64(file);
            }
            const result = await Espo.Ajax.postRequest('EmployeeManagement/holiday-document/verify', payload);
            this.renderResult(result);
        } catch (error) {
            this.showMessage('danger', error.message || 'Verificarea nu a putut fi efectuată.');
        } finally {
            button.prop('disabled', false);
        }
    }

    readBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result).split(',')[1] || '');
            reader.onerror = () => reject(new Error('Fișierul nu a putut fi citit.'));
            reader.readAsDataURL(file);
        });
    }

    renderResult(result) {
        if (!result.found) {
            this.showMessage('warning', 'Document necunoscut sau inaccesibil.');
            return;
        }

        const validity = {
            Valid: 'Autentic și valabil', Revoked: 'Autentic, dar revocat',
            Superseded: 'Autentic, dar înlocuit oficial',
            CancellationPending: 'Autentic; anularea așteaptă decizia managerului',
            NotEffective: 'Autentic, dar aprobarea nu este în vigoare',
        }[result.documentValidity] || result.documentValidity;
        const integrity = {
            Match: 'Fișierul corespunde exact originalului', Modified: 'Fișier modificat',
            NotChecked: 'Fișierul nu a fost încărcat',
        }[result.integrity] || result.integrity;
        const tone = result.integrity === 'Modified' || ['Revoked', 'Superseded', 'NotEffective'].includes(result.documentValidity)
            ? 'danger' : result.documentValidity === 'CancellationPending' ? 'warning' : 'success';
        const fields = [
            ['Angajat', result.employeeName], ['Referință document', result.reference],
            ['Perioadă aprobată', `${result.dateStart} – ${result.dateEnd}`],
            ['Luna documentului', result.month], ['Zile lucrătoare', result.workingDays],
            ['Manager aprobator', result.approvedByName], ['Data aprobării', result.approvedAt],
            ['Data emiterii', result.issuedAt], ['Status cerere', result.requestStatus],
            ['Valabilitate', validity], ['Integritate fișier', integrity],
        ];
        const container = this.$el.find('[data-name="verification-result"]')
            .removeClass('hidden alert-success alert-warning alert-danger')
            .addClass(`alert alert-${tone}`).empty();
        const list = document.createElement('dl');
        list.className = 'employee-holiday-verification-details';
        fields.forEach(([label, value]) => {
            const term = document.createElement('dt'); term.textContent = label;
            const description = document.createElement('dd'); description.textContent = value ?? '—';
            list.append(term, description);
        });
        container.append(list);
    }

    showMessage(tone, message) {
        this.$el.find('[data-name="verification-result"]')
            .removeClass('hidden alert-success alert-warning alert-danger')
            .addClass(`alert alert-${tone}`).empty().text(message);
    }
});
