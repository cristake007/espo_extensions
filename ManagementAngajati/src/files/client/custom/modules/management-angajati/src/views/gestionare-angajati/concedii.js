define(['view'], (Vizualizare) => {
    return class VizualizareSetariConcedii extends Vizualizare {
        template = 'management-angajati:gestionare-angajati/concedii'
        setup() {
            this.date = {profiluri: [], setari: {
                aprobatoriIds: [],
                necesitaAmbiiManageri: false,
                acordareAnualaAutomata: true,
            }};
            this.resetare = {};
            this.chei = [];
            this.addActionHandler('salveazaSetari', () => this.salveazaSetari());
            this.addActionHandler('reseteazaDateTest', () => this.reseteazaDateTest());
            this.wait(this.incarca());
        }
        async incarca() {
            [this.date, this.resetare] = await Promise.all([
                Espo.Ajax.getRequest('ManagementAngajati/profiluri-concediu'),
                Espo.Ajax.getRequest('ManagementAngajati/reset-date-concedii'),
            ]);
            await this.pregatesteCampuriSetari();
        }
        async pregatesteCampuriSetari() {
            if (!this.modelSetari) {
                this.modelSetari = await this.getModelFactory().create('ProfilConcediu');
            }

            this.modelSetari.set({
                aprobatoriIds: this.date.setari.aprobatoriIds || [],
                necesitaAmbiiManageri: this.date.setari.necesitaAmbiiManageri === true,
                acordareAnualaAutomata: this.date.setari.acordareAnualaAutomata === true,
            });

            if (this.getView('aprobatoriIds')) return;

            const optiuni = (this.date.setari.candidati || []).map(candidat => candidat.id);
            const denumiri = Object.fromEntries(
                (this.date.setari.candidati || []).map(candidat => [candidat.id, candidat.name])
            );

            await Promise.all([
                this.createView('aprobatoriIds', 'views/fields/checklist', {
                    selector: '.field[data-name="aprobatoriIds"]',
                    model: this.modelSetari,
                    mode: 'edit',
                    defs: {
                        name: 'aprobatoriIds',
                        params: {
                            options: optiuni,
                            translatedOptions: denumiri,
                            allowCustomOptions: false,
                        },
                    },
                }),
                this.createView('necesitaAmbiiManageri', 'views/fields/bool', {
                    selector: '.field[data-name="necesitaAmbiiManageri"]',
                    model: this.modelSetari,
                    mode: 'edit',
                    defs: {name: 'necesitaAmbiiManageri'},
                }),
                this.createView('acordareAnualaAutomata', 'views/fields/bool', {
                    selector: '.field[data-name="acordareAnualaAutomata"]',
                    model: this.modelSetari,
                    mode: 'edit',
                    defs: {name: 'acordareAnualaAutomata'},
                }),
            ]);
        }
        data() {
            return {
                titlu: this.translate('setariConcedii', 'labels', 'GestionareAngajati'),
                profiluri: this.date.profiluri,
                aprobatoriEticheta: this.translate('aprobatoriConcedii', 'fields', 'GestionareAngajati'),
                ambiiEticheta: this.translate('necesitaAmbiiManageri', 'fields', 'GestionareAngajati'),
                acordareAutomataEticheta: this.translate(
                    'acordareAnualaAutomata',
                    'fields',
                    'GestionareAngajati'
                ),
                acordareAutomataExplicatie: this.translate(
                    'explicatieAcordareAnualaAutomata',
                    'messages',
                    'GestionareAngajati'
                ),
                salvare: this.translate('Save'),
                angajat: this.translate('angajat', 'fields', 'GestionareAngajati'),
                zileAnuale: this.translate('zileAnuale', 'fields', 'ProfilConcediu'),
                soldCurent: this.translate('disponibileAnCurent', 'fields', 'ProfilConcediu'),
                soldReportat: this.translate('disponibileReportate', 'fields', 'ProfilConcediu'),
                dataAcordare: this.translate('dataUrmatoareiAcordari', 'fields', 'ProfilConcediu'),
                explicatieInitializare: this.translate('explicatieInitializare', 'messages', 'ProfilConcediu'),
                resetareTitlu: this.translate('resetareDateTestConcedii', 'labels', 'GestionareAngajati'),
                resetareExplicatie: this.translate('explicatieResetareDateTestConcedii', 'messages', 'GestionareAngajati'),
                confirmareReset: this.translate('confirmareResetConcedii', 'labels', 'GestionareAngajati'),
                profiluriEticheta: this.translate('profiluriConcediu', 'labels', 'GestionareAngajati'),
                cereriEticheta: this.translate('cereriConcediu', 'labels', 'GestionareAngajati'),
                registruEticheta: this.translate('registruConcediu', 'labels', 'GestionareAngajati'),
                resetare: this.resetare,
            };
        }
        afterRender() {
            this.randeazaRandurile();
        }
        randeazaRandurile() {
            this.chei.forEach(c => this.clearView(c)); this.chei = [];
            const container = this.$el.find('[data-role="profiluri"]').empty();

            this.date.profiluri.forEach((profil, i) => {
                const cheie = `profil-concediu-${i}`;

                this.chei.push(cheie);
                container.append($('<tr>').attr('data-view-key', cheie));

                this.createView(
                    cheie,
                    'management-angajati:views/gestionare-angajati/rand-profil-concediu',
                    {
                        selector: `[data-view-key="${cheie}"]`,
                        profil,
                    }
                ).then(vizualizare => {
                    vizualizare.listenTo(vizualizare, 'salvat', async () => {
                        await this.incarca();
                        await this.reRender();
                    });

                    if (this.isRendered()) {
                        vizualizare.render();
                    }
                });
            });
        }
        async salveazaSetari() {
            const campuri = [
                'aprobatoriIds',
                'necesitaAmbiiManageri',
                'acordareAnualaAutomata',
            ];
            campuri.forEach(nume => this.getView(nume).fetchToModel());
            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.putRequest('ManagementAngajati/setari-concedii', {
                    aprobatoriIds: this.modelSetari.get('aprobatoriIds') || [],
                    necesitaAmbiiManageri:
                        this.modelSetari.get('necesitaAmbiiManageri') === true,
                    acordareAnualaAutomata:
                        this.modelSetari.get('acordareAnualaAutomata') === true,
                });
            } finally { Espo.Ui.notify(false); }

            Espo.Ui.success(this.translate('setariSalvate', 'messages', 'GestionareAngajati'));
        }

        async reseteazaDateTest() {
            const confirmare = String(
                this.$el.find('[data-camp="confirmareResetConcedii"]').val() || ''
            );

            if (confirmare !== 'RESET DATE CONCEDII') {
                Espo.Ui.warning(this.translate(
                    'confirmareResetConcediiInvalida',
                    'messages',
                    'GestionareAngajati'
                ));
                return;
            }

            await this.confirm({
                message: this.translate(
                    'confirmareFinalaResetareDateTestConcedii',
                    'messages',
                    'GestionareAngajati'
                ),
                confirmText: this.translate(
                    'resetareDateTestConcedii',
                    'labels',
                    'GestionareAngajati'
                ),
                confirmStyle: 'danger',
                backdrop: 'static',
            });

            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest('ManagementAngajati/reset-date-concedii', {
                    confirmare: confirmare,
                });
                await this.incarca();
                await this.reRender();
            } finally { Espo.Ui.notify(false); }

            Espo.Ui.success(this.translate(
                'resetareDateTestConcediiReusita',
                'messages',
                'GestionareAngajati'
            ));
        }
    };
});
