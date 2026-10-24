define(['view'], (Vizualizare) => {
    return class VizualizareAdministrareConcedii extends Vizualizare {
        template = 'management-angajati:administrare-concedii/index'
        setup() {
            this.date = {cereri: []};
            this.idCerereSelectata = this.options.params?.idCerere || null;
            this.addActionHandler('aproba', (e, el) => this.decide(el.dataset.id, 'Aproba'));
            this.addActionHandler('respinge', (e, el) => this.decide(el.dataset.id, 'Respinge'));
            this.addActionHandler('aprobaAnularea', (e, el) =>
                this.decideAnulare(el.dataset.id, 'Aproba'));
            this.addActionHandler('respingeAnularea', (e, el) =>
                this.decideAnulare(el.dataset.id, 'Respinge'));
            this.addActionHandler('genereazaDocumente', (e, el) =>
                this.genereazaDocumente(el.dataset.id));
            this.wait(this.incarca());
        }
        async incarca() { this.date = await Espo.Ajax.getRequest('AdministrareConcedii/cereri'); }
        data() {
            const cereri = this.date.cereri.map(cerere => ({
                ...cerere,
                esteSelectata: cerere.id === this.idCerereSelectata,
                dataInceputAfisata: this.getDateTime().toDisplayDate(cerere.dataInceput),
                dataSfarsitAfisata: this.getDateTime().toDisplayDate(cerere.dataSfarsit),
                stareAfisata: this.translate(cerere.stare, 'options', 'CerereConcediu'),
                stareClasa: this.clasaStare(cerere.stare),
                progresAprobari: `${cerere.esteAnulareSolicitata ?
                    cerere.numarAprobariAnulare : cerere.numarAprobari}/${cerere.aprobariNecesare}`,
                areActiuni: cerere.poateGeneraDocumente || cerere.poateFiDecisa ||
                    cerere.poateDecideAnulare,
            }));
            const deSolutionat = cereri.filter(cerere =>
                cerere.poateFiDecisa || cerere.poateDecideAnulare
            ).length;

            return {
                titlu: this.translate('administrareConcedii', 'labels', 'AdministrareConcedii'),
                spratitlu: this.translate('managementAngajati', 'labels', 'AdministrareConcedii'),
                descriere: this.translate(
                    'descriereAdministrareConcedii', 'messages', 'AdministrareConcedii'
                ),
                listaCereri: this.translate('listaCereri', 'labels', 'AdministrareConcedii'),
                descriereLista: this.translate(
                    'descriereListaCereri', 'messages', 'AdministrareConcedii'
                ),
                cereri,
                areCereri: cereri.length > 0,
                contor: `${cereri.length} ${this.translate(
                    'cereri', 'labels', 'AdministrareConcedii'
                )} · ${deSolutionat} ${this.translate(
                    'deSolutionat', 'labels', 'AdministrareConcedii'
                )}`,
                faraCereri: this.translate('faraCereri', 'labels', 'AdministrareConcedii'),
                faraCereriExplicatie: this.translate(
                    'faraCereriExplicatie', 'messages', 'AdministrareConcedii'
                ),
                angajat: this.translate('angajat', 'fields', 'AdministrareConcedii'),
                perioada: this.translate('perioada', 'labels', 'CerereConcediu'),
                zile: this.translate('numarZile', 'fields', 'CerereConcediu'),
                stare: this.translate('stare', 'fields', 'CerereConcediu'),
                aproba: this.translate('aproba', 'labels', 'AdministrareConcedii'),
                respinge: this.translate('respinge', 'labels', 'AdministrareConcedii'),
                aprobaAnularea: this.translate(
                    'aprobaAnularea', 'labels', 'AdministrareConcedii'
                ),
                respingeAnularea: this.translate(
                    'respingeAnularea', 'labels', 'AdministrareConcedii'
                ),
                motivAnulare: this.translate(
                    'motivAnulare', 'labels', 'AdministrareConcedii'
                ),
                genereazaDocumente: this.translate(
                    'genereazaDocumente', 'labels', 'CerereConcediu'
                ),
                documente: this.translate('documente', 'labels', 'AdministrareConcedii'),
                actiuni: this.translate('actiuni', 'labels', 'AdministrareConcedii'),
            };
        }
        clasaStare(stare) {
            return {
                InAsteptare: 'label-warning',
                Aprobata: 'label-success',
                AnulareSolicitata: 'label-info',
                Respinsa: 'label-danger',
                Anulata: 'label-default',
            }[stare] || 'label-default';
        }
        afterRender() {
            if (!this.idCerereSelectata) return;

            const rand = this.$el.find('[data-id-cerere]').filter((index, element) =>
                element.dataset.idCerere === this.idCerereSelectata
            ).get(0);

            if (rand) {
                rand.scrollIntoView({block: 'center'});
            }
        }
        async decide(id, decizie) {
            this.marcheazaRandOcupat(id, true);
            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest(`AdministrareConcedii/cereri/${id}/decizie`, {decizie});
                await this.incarca();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
                this.marcheazaRandOcupat(id, false);
            }

            Espo.Ui.success(this.translate('decizieSalvata', 'messages', 'AdministrareConcedii'));
        }

        async decideAnulare(id, decizie) {
            this.marcheazaRandOcupat(id, true);
            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest(
                    `AdministrareConcedii/cereri/${id}/decizie-anulare`,
                    {decizie}
                );
                await this.incarca();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
                this.marcheazaRandOcupat(id, false);
            }

            Espo.Ui.success(this.translate(
                'decizieAnulareSalvata',
                'messages',
                'AdministrareConcedii'
            ));
        }

        async genereazaDocumente(id) {
            this.marcheazaRandOcupat(id, true);
            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest(`Concedii/cereri/${id}/documente`);
                await this.incarca();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
                this.marcheazaRandOcupat(id, false);
            }
            Espo.Ui.success(this.translate('documenteGenerate', 'messages', 'CerereConcediu'));
        }
        marcheazaRandOcupat(id, ocupat) {
            const rand = this.$el.find(`[data-id-cerere="${id}"]`);
            rand.toggleClass('este-ocupat', ocupat).attr(
                'aria-busy',
                ocupat ? 'true' : 'false'
            );
            rand.find('[data-action]').prop('disabled', ocupat);
        }
    };
});
