define(['view'], (Vizualizare) => {
    return class VizualizareConcediiPersonale extends Vizualizare {
        template = 'management-angajati:concedii/spatiu-personal'

        setup() {
            this.date = {disponibil: true, sold: null, cereri: []};
            this.addActionHandler('trimiteCerere', () => this.trimiteCerere());
            this.addActionHandler('anuleazaCerere', (e, el) => this.anuleazaCerere(
                el.dataset.id,
                el.dataset.stare
            ));
            this.addActionHandler('genereazaDocumente', (e, el) =>
                this.genereazaDocumente(el.dataset.id));
            this.wait(this.pregateste());
        }

        async pregateste() {
            this.date = await Espo.Ajax.getRequest('Concedii/spatiu-personal');

            if (this.date.disponibil === false) {
                return;
            }

            this.modelCerere = await this.getModelFactory().create('CerereConcediu');
            await Promise.all(['dataInceput', 'dataSfarsit'].map(nume => this.createView(nume, 'views/fields/date', {
                model: this.modelCerere,
                mode: 'edit',
                selector: `.field[data-name="${nume}"]`,
                defs: {name: nume},
            })));
        }

        data() {
            const esteAdministratorFaraConcediu =
                this.date.motivIndisponibil === 'administratorFaraConcediuPersonal';
            const poateGestionaProfiluri = this.getUser().isAdmin() ||
                this.getAcl().checkScope('GestionareAngajati');

            return {
                titlu: this.translate('concediileMele', 'labels', 'CerereConcediu'),
                disponibil: this.date.disponibil !== false,
                indisponibilTitlu: this.translate(
                    esteAdministratorFaraConcediu ?
                        'concediiIndisponibileAdministrator' :
                        'concediiIndisponibile',
                    'labels',
                    'CerereConcediu'
                ),
                indisponibilExplicatie: this.translate(
                    esteAdministratorFaraConcediu ?
                        'administratorFaraConcediuPersonal' :
                        'profilConcediuNeinitializatExplicatie',
                    'messages',
                    'CerereConcediu'
                ),
                poateAfisaActiune: esteAdministratorFaraConcediu || poateGestionaProfiluri,
                urlActiune: esteAdministratorFaraConcediu ?
                    '#GestionareAngajati' :
                    '#GestionareAngajati/concedii',
                etichetaActiune: this.translate(
                    esteAdministratorFaraConcediu ?
                        'managementAngajati' :
                        'configureazaProfiluriConcediu',
                    'labels',
                    esteAdministratorFaraConcediu ? 'GestionareAngajati' : 'CerereConcediu'
                ),
                cerereNoua: this.translate('cerereNoua', 'labels', 'CerereConcediu'),
                dataInceputEticheta: this.translate('dataInceput', 'fields', 'CerereConcediu'),
                dataSfarsitEticheta: this.translate('dataSfarsit', 'fields', 'CerereConcediu'),
                descriereEticheta: this.translate('descriere', 'fields', 'CerereConcediu'),
                trimite: this.translate('trimiteCerere', 'labels', 'CerereConcediu'),
                cereri: (this.date.cereri || []).map(cerere => ({
                    ...cerere,
                    dataInceputAfisata: this.getDateTime().toDisplayDate(cerere.dataInceput),
                    dataSfarsitAfisata: this.getDateTime().toDisplayDate(cerere.dataSfarsit),
                    stareAfisata: this.translate(cerere.stare, 'options', 'CerereConcediu'),
                    esteInAsteptare: cerere.stare === 'InAsteptare',
                })),
                sold: this.date.sold,
                soldDisponibil: this.translate('soldDisponibil', 'labels', 'CerereConcediu'),
                soldRezervat: this.translate('soldRezervat', 'labels', 'CerereConcediu'),
                soldConsumat: this.translate('soldConsumat', 'labels', 'CerereConcediu'),
                perioada: this.translate('perioada', 'labels', 'CerereConcediu'),
                zile: this.translate('numarZile', 'fields', 'CerereConcediu'),
                stare: this.translate('stare', 'fields', 'CerereConcediu'),
                retrageCerere: this.translate('retrageCerere', 'labels', 'CerereConcediu'),
                solicitaAnulare: this.translate('solicitaAnulare', 'labels', 'CerereConcediu'),
                genereazaDocumente: this.translate('genereazaDocumente', 'labels', 'CerereConcediu'),
            };
        }

        async genereazaDocumente(id) {
            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest(`Concedii/cereri/${id}/documente`);
                this.date = await Espo.Ajax.getRequest('Concedii/spatiu-personal');
                await this.reRender();
            } finally { Espo.Ui.notify(false); }
            Espo.Ui.success(this.translate('documenteGenerate', 'messages', 'CerereConcediu'));
        }

        async trimiteCerere() {
            ['dataInceput', 'dataSfarsit'].forEach(n => this.getView(n).fetchToModel());
            if (['dataInceput', 'dataSfarsit'].some(n => this.getView(n).validate())) return;
            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest('Concedii/cereri', {
                    dataInceput: this.modelCerere.get('dataInceput'),
                    dataSfarsit: this.modelCerere.get('dataSfarsit'),
                    descriere: this.$el.find('[data-name="descriere"]').val() || '',
                });
                this.date = await Espo.Ajax.getRequest('Concedii/spatiu-personal');
                await this.reRender();
            } finally { Espo.Ui.notify(false); }

            Espo.Ui.success(this.translate('cerereTrimisa', 'messages', 'CerereConcediu'));
        }

        async anuleazaCerere(id, stare) {
            let motiv = null;

            if (stare === 'InAsteptare') {
                await this.confirm({
                    message: this.translate('confirmaRetragere', 'labels', 'CerereConcediu'),
                    confirmText: this.translate('retrageCerere', 'labels', 'CerereConcediu'),
                });
            } else {
                motiv = await this.solicitaMotivAnulare();

                if (motiv === null) return;
            }

            Espo.Ui.notifyWait();
            try {
                await Espo.Ajax.postRequest(`Concedii/cereri/${id}/anulare`, {motiv});
                this.date = await Espo.Ajax.getRequest('Concedii/spatiu-personal');
                await this.reRender();
            } finally { Espo.Ui.notify(false); }

            Espo.Ui.success(this.translate(
                stare === 'InAsteptare' ? 'cerereRetrasa' : 'anulareSolicitata',
                'messages',
                'CerereConcediu'
            ));
        }

        solicitaMotivAnulare() {
            return new Promise(async rezolva => {
                const dialog = await this.createView('dialogSolicitareAnulare', 'views/modal', {
                    templateContent:
                        '<div class="form-group">' +
                        '<label>{{viewObject.options.eticheta}}</label>' +
                        '<textarea class="form-control" data-name="motiv" rows="4"></textarea>' +
                        '</div>',
                    headerText: this.translate('solicitaAnulare', 'labels', 'CerereConcediu'),
                    eticheta: this.translate('motivAnulare', 'fields', 'CerereConcediu'),
                    buttonList: [
                        {
                            name: 'trimite',
                            text: this.translate(
                                'trimiteSolicitareAnulare',
                                'labels',
                                'CerereConcediu'
                            ),
                            style: 'primary',
                            onClick: () => {
                                const motiv = dialog.$el.find('[data-name="motiv"]').val().trim();

                                if (motiv.length < 3) {
                                    Espo.Ui.warning(this.translate(
                                        'motivAnulareObligatoriu',
                                        'messages',
                                        'CerereConcediu'
                                    ));
                                    return;
                                }

                                dialog.close();
                                rezolva(motiv);
                            },
                        },
                        {
                            name: 'cancel',
                            label: 'Cancel',
                            onClick: () => {
                                dialog.close();
                                rezolva(null);
                            },
                        },
                    ],
                });

                await dialog.render();
            });
        }
    };
});
