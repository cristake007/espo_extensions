define('modules/management-angajati/views/gestionare-angajati/rand-profil-concediu', ['view'], (Vizualizare) => {
    return class VizualizareRandProfilConcediu extends Vizualizare {
        template = 'management-angajati:gestionare-angajati/rand-profil-concediu'
        setup() {
            this.profil = this.options.profil;
            this.addActionHandler('salveaza', () => this.salveaza());
            this.addActionHandler('corecteaza', () => this.corecteaza());
            this.addActionHandler('corecteazaProgramAcordare', () => this.corecteazaProgramAcordare());
            this.wait(this.pregatesteData());
        }
        async pregatesteData() {
            this.modelProfil = await this.getModelFactory().create('ProfilConcediu');
            this.modelProfil.set({
                dataUrmatoareiAcordari: this.profil.dataUrmatoareiAcordari || null,
                zileAnuale: Number(this.profil.zileAnuale ?? 21),
                zileDisponibileAnCurent: 0,
                zileReportateAnAnterior: 0,
                diferentaCorectie: null,
            });
            const vizualizari = [
                this.createView('dataUrmatoareiAcordari', 'views/fields/date', {
                    model: this.modelProfil,
                    mode: 'edit',
                    selector: this.profil.initializat ?
                        '.field[data-name="dataCorectieAcordare"]' :
                        '.field[data-name="dataUrmatoareiAcordari"]',
                    defs: {name: 'dataUrmatoareiAcordari'},
                }),
                this.createView('zileAnuale', 'views/fields/int', {
                    model: this.modelProfil,
                    mode: 'edit',
                    selector: '.field[data-name="zileAnuale"]',
                    defs: {name: 'zileAnuale', params: {min: 0}},
                }),
            ];

            if (this.profil.initializat) {
                vizualizari.push(this.createView('diferentaCorectie', 'views/fields/int', {
                    model: this.modelProfil,
                    mode: 'edit',
                    selector: '.field[data-name="diferentaCorectie"]',
                    defs: {name: 'diferentaCorectie'},
                }));
            } else {
                vizualizari.push(this.createView('zileDisponibileAnCurent', 'views/fields/int', {
                    model: this.modelProfil,
                    mode: 'edit',
                    selector: '.field[data-name="zileDisponibileAnCurent"]',
                    defs: {name: 'zileDisponibileAnCurent', params: {min: 0}},
                }));
                vizualizari.push(this.createView('zileReportateAnAnterior', 'views/fields/int', {
                    model: this.modelProfil,
                    mode: 'edit',
                    selector: '.field[data-name="zileReportateAnAnterior"]',
                    defs: {name: 'zileReportateAnAnterior', params: {min: 0}},
                }));
            }

            await Promise.all(vizualizari);
        }
        data() {
            const anCurent = Number(this.getDateTime().getToday().slice(0, 4));
            const drepturi = this.profil.sold?.peAni || [];
            const disponibileAnCurent = drepturi
                .filter(drept => Number(drept.anOrigine) === anCurent)
                .reduce((total, drept) => total + Number(drept.disponibile || 0), 0);
            const disponibileReportate = drepturi
                .filter(drept => Number(drept.anOrigine) < anCurent)
                .reduce((total, drept) => total + Number(drept.disponibile || 0), 0);
            return {...this.profil,
                disponibileAnCurent,
                disponibileReportate,
                profilInitializat: this.translate(
                    'profilInitializat', 'labels', 'ProfilConcediu'
                ),
                profilNeinitializat: this.translate(
                    'profilNeinitializat', 'labels', 'ProfilConcediu'
                ),
                explicatieProfilInitializat: this.translate(
                    'explicatieProfilInitializat', 'messages', 'ProfilConcediu'
                ),
                explicatieProfilNeinitializat: this.translate(
                    'explicatieProfilNeinitializat', 'messages', 'ProfilConcediu'
                ),
                dataAngajareEticheta: this.translate(
                    'dataAngajare', 'fields', 'GestionareAngajati'
                ),
                dataAngajareAfisata: this.profil.dataAngajare ?
                    this.getDateTime().toDisplayDate(this.profil.dataAngajare) : '—',
                salvare: this.translate('Save'),
                salveazaDreptAnual: this.translate(
                    'salveazaDreptAnual', 'labels', 'ProfilConcediu'
                ),
                initializare: this.translate('initializeaza', 'labels', 'ProfilConcediu'),
                zile: this.translate('zile', 'labels', 'ProfilConcediu'),
                zileAnualeEticheta: this.translate('zileAnuale', 'fields', 'ProfilConcediu'),
                disponibileInitialEticheta: this.translate('zileDisponibileAnCurent', 'fields', 'ProfilConcediu'),
                reportateInitialEticheta: this.translate('zileReportateAnAnterior', 'fields', 'ProfilConcediu'),
                dataAcordareEticheta: this.translate('dataUrmatoareiAcordari', 'fields', 'ProfilConcediu'),
                dataAcordareAfisata: this.profil.dataUrmatoareiAcordari ?
                    this.getDateTime().toDisplayDate(this.profil.dataUrmatoareiAcordari) : '—',
                dataNouaAcordareEticheta: this.translate(
                    'dataNouaAcordare', 'fields', 'ProfilConcediu'
                ),
                motivProgramAcordareEticheta: this.translate(
                    'motivProgramAcordare', 'fields', 'ProfilConcediu'
                ),
                corecteazaProgramAcordareEticheta: this.translate(
                    'corecteazaProgramAcordare', 'labels', 'ProfilConcediu'
                ),
                aplicaEticheta: this.translate(
                    'aplica', 'labels', 'ProfilConcediu'
                ),
                corectiiAdministrative: this.translate(
                    'corectiiAdministrative', 'labels', 'ProfilConcediu'
                ),
                explicatieCorectiiAdministrative: this.translate(
                    'explicatieCorectiiAdministrative', 'messages', 'ProfilConcediu'
                ),
                explicatieCorectieProgram: this.translate(
                    'explicatieCorectieProgram', 'messages', 'ProfilConcediu'
                ),
                diferentaEticheta: this.translate('diferentaCorectie', 'fields', 'ProfilConcediu'),
                motivEticheta: this.translate('motivCorectie', 'fields', 'ProfilConcediu'),
                corecteazaEticheta: this.translate('corecteazaSold', 'labels', 'ProfilConcediu'),
                explicatieCorectieSold: this.translate(
                    'explicatieCorectieSold', 'messages', 'ProfilConcediu'
                ),
            };
        }
        async salveaza() {
            const initializare = !this.profil.initializat;
            this.getView('zileAnuale').fetchToModel();
            if (this.getView('zileAnuale').validate()) return;
            const corp = {
                idAngajat: this.profil.idAngajat,
                initializare,
                zileAnuale: Number(this.modelProfil.get('zileAnuale')),
            };
            if (initializare) {
                this.getView('dataUrmatoareiAcordari').fetchToModel();
                this.getView('zileDisponibileAnCurent').fetchToModel();
                this.getView('zileReportateAnAnterior').fetchToModel();
                if (this.getView('dataUrmatoareiAcordari').validate() ||
                    this.getView('zileDisponibileAnCurent').validate() ||
                    this.getView('zileReportateAnAnterior').validate()) return;
                corp.dataUrmatoareiAcordari = this.modelProfil.get('dataUrmatoareiAcordari');
                corp.zileDisponibileAnCurent = Number(
                    this.modelProfil.get('zileDisponibileAnCurent')
                );
                corp.zileReportateAnAnterior = Number(
                    this.modelProfil.get('zileReportateAnAnterior')
                );
            }
            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest('ManagementAngajati/profiluri-concediu', corp);
            } finally { Espo.Ui.notify(false); }

            Espo.Ui.success(this.translate('profilSalvat', 'messages', 'ProfilConcediu'));
            this.trigger('salvat');
        }

        async corecteazaProgramAcordare() {
            this.getView('dataUrmatoareiAcordari').fetchToModel();
            if (this.getView('dataUrmatoareiAcordari').validate()) return;

            const dataNoua = this.modelProfil.get('dataUrmatoareiAcordari');
            const motiv = String(
                this.$el.find('[data-camp="motivProgramAcordare"]').val() || ''
            ).trim();

            if (!dataNoua || motiv.length < 3) {
                Espo.Ui.warning(this.translate(
                    'corectieProgramAcordareInvalida',
                    'messages',
                    'ProfilConcediu'
                ));
                return;
            }

            const calePrevizualizare =
                `ManagementAngajati/profiluri-concediu/${this.profil.idAngajat}` +
                '/previzualizare-corectie-program-acordare';
            const previzualizare = await Espo.Ajax.postRequest(calePrevizualizare, {
                dataNoua: dataNoua,
            });
            const confirmat = await this.afiseazaImpactCorectie(previzualizare);
            if (!confirmat) return;

            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest(
                    `ManagementAngajati/profiluri-concediu/${this.profil.idAngajat}/corectie-program-acordare`,
                    {dataNoua: dataNoua, motiv: motiv}
                );
            } catch (eroare) {
                if (eroare.status !== 409) throw eroare;
                eroare.errorIsHandled = true;
                const impactActualizat = await Espo.Ajax.postRequest(calePrevizualizare, {
                    dataNoua: dataNoua,
                });
                await this.afiseazaImpactCorectie(impactActualizat);
                return;
            } finally { Espo.Ui.notify(false); }

            Espo.Ui.success(this.translate(
                'corectieProgramAcordareSalvata',
                'messages',
                'ProfilConcediu'
            ));
            this.trigger('salvat');
        }

        afiseazaImpactCorectie(previzualizare) {
            return new Promise(async rezolva => {
                let decizieLuata = false;
                const date = {
                    ...previzualizare,
                    dataVecheAfisata: this.getDateTime().toDisplayDate(
                        previzualizare.dataVeche
                    ),
                    dataNouaAfisata: this.getDateTime().toDisplayDate(
                        previzualizare.dataNoua
                    ),
                    mesaj: this.translate(
                        previzualizare.codBlocare || 'programAcordarePoateFiCorectat',
                        'messages',
                        'ProfilConcediu'
                    ),
                    cereriBlocante: (previzualizare.cereriBlocante || []).map(cerere => ({
                        ...cerere,
                        stareAfisata: this.translate(cerere.stare, 'options', 'CerereConcediu'),
                        perioadaAfisata:
                            `${this.getDateTime().toDisplayDate(cerere.dataInceput)} – ` +
                            this.getDateTime().toDisplayDate(cerere.dataSfarsit),
                    })),
                };
                const butoane = [];
                if (date.permis) {
                    butoane.push({
                        name: 'confirma',
                        text: this.translate(
                            'corecteazaProgramAcordare',
                            'labels',
                            'ProfilConcediu'
                        ),
                        style: 'primary',
                        onClick: dialog => {
                            decizieLuata = true;
                            rezolva(true);
                            dialog.close();
                        },
                    });
                }
                butoane.push({
                    name: 'cancel',
                    label: 'Close',
                    onClick: dialog => {
                        decizieLuata = true;
                        rezolva(false);
                        dialog.close();
                    },
                });

                const dialog = await this.createView(
                    'dialogImpactCorectieAcordare',
                    'management-angajati:views/modals/impact-corectie-acordare',
                    {
                        impact: date,
                        headerText: this.translate(
                            'impactCorectieProgramAcordare',
                            'labels',
                            'ProfilConcediu'
                        ),
                        buttonList: butoane,
                        templateContent:
                            '<div class="alert alert-{{#if viewObject.options.impact.permis}}info{{else}}danger{{/if}}">' +
                            '{{viewObject.options.impact.mesaj}}</div>' +
                            '<dl class="dl-horizontal">' +
                            '<dt>{{translate "dataAcordareCurenta" category="fields" scope="ProfilConcediu"}}</dt>' +
                            '<dd>{{viewObject.options.impact.dataVecheAfisata}}</dd>' +
                            '<dt>{{translate "dataNouaAcordare" category="fields" scope="ProfilConcediu"}}</dt>' +
                            '<dd>{{viewObject.options.impact.dataNouaAfisata}}</dd>' +
                            '<dt>{{translate "drepturiAfectate" category="fields" scope="ProfilConcediu"}}</dt>' +
                            '<dd>{{viewObject.options.impact.drepturiAfectate}}' +
                            '<div class="text-muted small">{{translate "explicatieDrepturiAfectate" category="messages" scope="ProfilConcediu"}}</div></dd>' +
                            '</dl>' +
                            '{{#if viewObject.options.impact.cereriBlocante.length}}' +
                            '<h4>{{translate "cereriBlocante" category="labels" scope="ProfilConcediu"}}</h4>' +
                            '<ul class="list-group">{{#each viewObject.options.impact.cereriBlocante}}' +
                            '<li class="list-group-item"><a href="#AdministrareConcedii/view/{{id}}">{{nume}}</a>' +
                            '<div class="text-muted small">{{perioadaAfisata}} · {{stareAfisata}}</div></li>' +
                            '{{/each}}</ul>{{/if}}',
                    }
                );
                dialog.once('close', () => {
                    if (!decizieLuata) rezolva(false);
                });
                await dialog.render();
            });
        }

        async corecteaza() {
            this.getView('diferentaCorectie').fetchToModel();
            if (this.getView('diferentaCorectie').validate()) return;
            const diferenta = Number(this.modelProfil.get('diferentaCorectie'));
            const motiv = String(this.$el.find('[data-camp="motivCorectie"]').val() || '').trim();

            if (!Number.isInteger(diferenta) || diferenta === 0 || motiv.length < 3) {
                Espo.Ui.warning(this.translate(
                    'corectieSoldInvalida',
                    'messages',
                    'ProfilConcediu'
                ));
                return;
            }

            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest(
                    `ManagementAngajati/profiluri-concediu/${this.profil.idAngajat}/corectie`,
                    {diferenta: diferenta, motiv: motiv}
                );
            } finally { Espo.Ui.notify(false); }

            Espo.Ui.success(this.translate('corectieSoldSalvata', 'messages', 'ProfilConcediu'));
            this.trigger('salvat');
        }
    };
});
