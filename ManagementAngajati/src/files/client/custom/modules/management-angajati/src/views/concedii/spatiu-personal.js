define(['view'], (Vizualizare) => {
    return class VizualizareConcediiPersonale extends Vizualizare {
        template = 'management-angajati:concedii/spatiu-personal'

        setup() {
            this.date = {disponibil: true, sold: null, cereri: []};
            this.numarCererePrevizualizare = 0;
            this.poateTrimiteCererea = false;
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

            this.listenTo(
                this.modelCerere,
                'change:dataInceput change:dataSfarsit',
                () => this.programeazaPrevizualizareaZilelor()
            );
            this.once('remove', () => clearTimeout(this.temporizatorPrevizualizare));
        }

        data() {
            const esteAdministratorFaraConcediu =
                this.date.motivIndisponibil === 'administratorFaraConcediuPersonal';
            const esteCicActiv = this.date.motivIndisponibil === 'angajatInCic';
            const poateGestionaProfiluri = this.getUser().isAdmin() ||
                this.getAcl().checkScope('GestionareAngajati');
            const poateInitializaProfilulPropriu =
                this.date.motivIndisponibil === 'profilConcediuNeinitializat' &&
                poateGestionaProfiluri;
            const cereri = (this.date.cereri || []).map(cerere => {
                const esteAnulata = cerere.stare === 'Anulata';
                const esteRespinsa = cerere.stare === 'Respinsa';
                const numarZileAfisat = esteRespinsa ? '0' :
                    `${esteAnulata ? '+' : '−'}${cerere.numarZile}`;
                const zileClasa = esteRespinsa ? 'este-neutru' :
                    (esteAnulata ? 'este-restituit' : 'este-rezervat');
                const cheieExplicatie = esteRespinsa ? 'zileFaraImpactTabel' :
                    (esteAnulata ? 'zileRestituiteTabel' : 'zileRezervateTabel');

                return {
                    ...cerere,
                    dataInceputAfisata: this.getDateTime().toDisplayDate(cerere.dataInceput),
                    dataSfarsitAfisata: this.getDateTime().toDisplayDate(cerere.dataSfarsit),
                    numarZileAfisat,
                    zileClasa,
                    zileExplicatie: this.translate(
                        cheieExplicatie,
                        'messages',
                        'CerereConcediu'
                    ),
                    stareAfisata: this.getLanguage().translateOption(
                        cerere.stare,
                        'stare',
                        'CerereConcediu'
                    ),
                    stareClasa: this.clasaStare(cerere.stare),
                    stareIcon: this.iconStare(cerere.stare),
                    esteInAsteptare: cerere.stare === 'InAsteptare',
                    areDocumente: (cerere.documente || []).length > 0,
                    areActiuni: cerere.poateGeneraDocumente || cerere.poateSolicitaAnulare,
                };
            });
            const faraObservatii = this.translate(
                'faraObservatiiIstoric',
                'messages',
                'CerereConcediu'
            );
            const istoricSold = (this.date.istoricSold || []).map(eveniment => {
                const esteInitializare = Boolean(eveniment.esteInitializare);
                const esteRestituire = eveniment.tipEveniment === 'Restituire';
                const impactFinal = Number(eveniment.impactFinal || 0);
                const soldFinal = Number(eveniment.soldDupa || 0);
                const cheieTitluSold = esteInitializare ? 'soldInitialIstoric' :
                    (esteRestituire ? 'soldDupaRestituire' : 'soldDupaRezervare');
                const cheieTitluEveniment = esteInitializare ? 'initializareSold' :
                    (esteRestituire ? 'restituirePentruCerere' : 'rezervarePentruCerere');
                const cheieEtichetaDetaliu = esteInitializare ? 'zileDisponibileInitial' :
                    (esteRestituire ? 'zileRestituiteIstoric' : 'zileRezervateIstoric');

                return {
                    ...eveniment,
                    soldFinal,
                    titluSold: this.translate(
                        cheieTitluSold, 'labels', 'CerereConcediu'
                    ),
                    titluEveniment: this.translate(
                        cheieTitluEveniment, 'labels', 'CerereConcediu'
                    ),
                    perioadaAfisata: esteInitializare ?
                        this.translate('punctPornireIstoric', 'labels', 'CerereConcediu') :
                        `${this.getDateTime().toDisplayDate(eveniment.dataInceput)} – ` +
                            this.getDateTime().toDisplayDate(eveniment.dataSfarsit),
                    descriereAfisata: esteInitializare ?
                        this.translate(
                            'descriereInitializareIstoric', 'messages', 'CerereConcediu'
                        ) :
                        (String(eveniment.descriere || '').trim() || faraObservatii),
                    stareAfisata: this.translate(
                        esteInitializare ? 'initializare' :
                            (esteRestituire ? 'restituire' : 'rezervare'),
                        'labels',
                        'CerereConcediu'
                    ),
                    stareClasa: esteInitializare ? 'label-info' :
                        (esteRestituire ? 'label-success' : 'label-warning'),
                    stareIcon: esteInitializare ? 'fa-flag' :
                        (esteRestituire ? 'fa-plus-circle' : 'fa-minus-circle'),
                    valoareDetaliu: esteInitializare ? soldFinal : eveniment.numarZile,
                    etichetaDetaliu: this.translate(
                        cheieEtichetaDetaliu, 'labels', 'CerereConcediu'
                    ),
                    impactFinalAfisat: this.formateazaValoareCuSemn(impactFinal),
                    impactClasa: impactFinal < 0 ? 'este-negativ' :
                        (impactFinal > 0 ? 'este-pozitiv' : 'este-neutru'),
                    explicatieSold: esteInitializare ?
                        this.translate(
                            'explicatieInitializareIstoric', 'messages', 'CerereConcediu'
                        ).replace('{soldInitial}', String(soldFinal)) :
                        this.explicaOperatiaSoldului(eveniment, soldFinal, esteRestituire),
                };
            });
            const istoricMaxim = Math.max(0, istoricSold.length - 1);
            const istoricSelectat = istoricSold[istoricMaxim] || null;

            this.istoricSoldPregatit = istoricSold;

            return {
                titlu: this.translate('concediileMele', 'labels', 'CerereConcediu'),
                spratitlu: this.translate('spatiuPersonal', 'labels', 'CerereConcediu'),
                descrierePagina: this.translate(
                    'descriereConcediiPersonale', 'messages', 'CerereConcediu'
                ),
                disponibil: this.date.disponibil !== false,
                esteCicActiv: esteCicActiv,
                indisponibilTitlu: this.translate(
                    esteCicActiv ? 'concediiIndisponibileCic' :
                    (esteAdministratorFaraConcediu ?
                        'concediiIndisponibileAdministrator' :
                        'concediiIndisponibile'),
                    'labels',
                    'CerereConcediu'
                ),
                indisponibilExplicatie: this.translate(
                    esteCicActiv ? 'concediiSuspendateCic' :
                    (esteAdministratorFaraConcediu ?
                        'administratorFaraConcediuPersonal' :
                        'profilConcediuNeinitializatExplicatie'),
                    'messages',
                    'CerereConcediu'
                ),
                poateAfisaActiune: !esteCicActiv &&
                    (esteAdministratorFaraConcediu || poateGestionaProfiluri),
                afiseazaIndrumareProfil: !esteAdministratorFaraConcediu,
                cicStare: this.translate('cicActiv', 'labels', 'CerereConcediu'),
                cicPerioada: this.translate('perioadaCic', 'labels', 'CerereConcediu'),
                cicDataInceput: this.date.situatieCic?.dataInceput ?
                    this.getDateTime().toDisplayDate(this.date.situatieCic.dataInceput) : '',
                cicDataSfarsit: this.date.situatieCic?.dataSfarsit ?
                    this.getDateTime().toDisplayDate(this.date.situatieCic.dataSfarsit) : '',
                cicFaraCereri: this.translate('cicFaraCereri', 'labels', 'CerereConcediu'),
                cicContactManager: this.translate(
                    'cicContactManager',
                    'messages',
                    'CerereConcediu'
                ),
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
                profilNecesitaConfigurare: this.translate(
                    poateInitializaProfilulPropriu ?
                        'initializeazaProfilulPropriu' :
                        'profilNecesitaConfigurare',
                    'labels',
                    'CerereConcediu'
                ),
                profilIndrumare: this.translate(
                    poateInitializaProfilulPropriu ?
                        'profilPropriuNeinitializatIndrumare' :
                        'profilIndrumare',
                    'messages',
                    'CerereConcediu'
                ),
                rezumatSold: this.translate('rezumatSold', 'labels', 'CerereConcediu'),
                cerereNoua: this.translate('cerereNoua', 'labels', 'CerereConcediu'),
                cerereNouaDescriere: this.translate(
                    'cerereNouaDescriere', 'messages', 'CerereConcediu'
                ),
                dataInceputEticheta: this.translate('dataInceput', 'fields', 'CerereConcediu'),
                dataSfarsitEticheta: this.translate('dataSfarsit', 'fields', 'CerereConcediu'),
                descriereEticheta: this.translate('descriere', 'fields', 'CerereConcediu'),
                perioadaSolicitata: this.translate(
                    'perioadaSolicitata',
                    'labels',
                    'CerereConcediu'
                ),
                zileEligibile: this.translate('zileEligibile', 'labels', 'CerereConcediu'),
                selecteazaPerioadaPentruCalcul: this.translate(
                    'selecteazaPerioadaPentruCalcul',
                    'messages',
                    'CerereConcediu'
                ),
                trimiteDezactivat: !this.poateTrimiteCererea,
                trimite: this.translate('trimiteCerere', 'labels', 'CerereConcediu'),
                cereri,
                areCereri: cereri.length > 0,
                istoricCereri: this.translate('istoricCereri', 'labels', 'CerereConcediu'),
                istoricCereriDescriere: this.translate(
                    'istoricCereriDescriere', 'messages', 'CerereConcediu'
                ),
                areIstoricSold: istoricSold.length > 0,
                istoricSelectat,
                istoricMaxim,
                istoricPozitie: this.etichetaPozitieIstoric(istoricMaxim),
                zileDisponibileEticheta: this.translate(
                    'zileDisponibileEticheta', 'labels', 'CerereConcediu'
                ),
                impactFinalCerere: this.translate(
                    'impactFinalCerere', 'labels', 'CerereConcediu'
                ),
                evolutieSoldCerere: this.translate(
                    'evolutieSoldCerere', 'labels', 'CerereConcediu'
                ),
                primaCerere: this.translate('initializareSold', 'labels', 'CerereConcediu'),
                ultimaCerere: this.translate('ultimaCerere', 'labels', 'CerereConcediu'),
                navigareIstoricSold: this.translate(
                    'navigareIstoricSold', 'labels', 'CerereConcediu'
                ),
                contorCereri: `${cereri.length} ${this.translate(
                    cereri.length === 1 ? 'cerere' : 'cereri',
                    'labels',
                    'CerereConcediu'
                )}`,
                faraCereri: this.translate('faraCereri', 'labels', 'CerereConcediu'),
                faraCereriExplicatie: this.translate(
                    'faraCereriExplicatie', 'messages', 'CerereConcediu'
                ),
                sold: this.date.sold,
                soldDisponibil: this.translate('soldDisponibil', 'labels', 'CerereConcediu'),
                soldRezervat: this.translate('soldRezervat', 'labels', 'CerereConcediu'),
                soldConsumat: this.translate('soldConsumat', 'labels', 'CerereConcediu'),
                perioada: this.translate('perioada', 'labels', 'CerereConcediu'),
                zile: this.translate('numarZile', 'fields', 'CerereConcediu'),
                documente: this.translate(
                    'documenteConcediu', 'fields', 'CerereConcediu'
                ),
                stare: this.translate('stare', 'fields', 'CerereConcediu'),
                actiuni: this.translate('actiuni', 'labels', 'CerereConcediu'),
                retrageCerere: this.translate('retrageCerere', 'labels', 'CerereConcediu'),
                solicitaAnulare: this.translate('solicitaAnulare', 'labels', 'CerereConcediu'),
                genereazaDocumente: this.translate('genereazaDocumente', 'labels', 'CerereConcediu'),
            };
        }

        afterRender() {
            const $slider = this.$el.find('[data-role="istoric-sold-slider"]');

            if (!$slider.length) return;

            $slider.off('.istoricSold').on('input.istoricSold change.istoricSold', event => {
                this.actualizeazaIstoricSold(Number(event.currentTarget.value));
            });
            this.actualizeazaIstoricSold(Number($slider.val()));
        }

        etichetaPozitieIstoric(index) {
            const total = this.istoricSoldPregatit?.length || 0;

            return this.translate('pozitieIstoricSold', 'messages', 'CerereConcediu')
                .replace('{curenta}', String(total ? index + 1 : 0))
                .replace('{total}', String(total));
        }

        actualizeazaIstoricSold(index) {
            const eveniment = this.istoricSoldPregatit?.[index];

            if (!eveniment) return;

            const $stare = this.$el.find('[data-role="istoric-stare"]');
            const claseStare = [
                'label-warning',
                'label-success',
                'label-info',
                'label-danger',
                'label-default',
            ];

            this.$el.find('[data-role="istoric-sold-disponibil"]').text(eveniment.soldFinal);
            this.$el.find('[data-role="istoric-titlu-sold"]').text(eveniment.titluSold);
            this.$el.find('[data-role="istoric-impact-final"]')
                .text(eveniment.impactFinalAfisat);
            this.$el.find('[data-role="istoric-impact"]')
                .removeClass('este-negativ este-pozitiv este-neutru')
                .addClass(eveniment.impactClasa);
            this.$el.find('[data-role="istoric-explicatie-sold"]')
                .text(eveniment.explicatieSold);
            this.$el.find('[data-role="istoric-titlu-eveniment"]')
                .text(eveniment.titluEveniment);
            this.$el.find('[data-role="istoric-perioada"]').text(eveniment.perioadaAfisata);
            this.$el.find('[data-role="istoric-numar-zile"]').text(eveniment.valoareDetaliu);
            this.$el.find('[data-role="istoric-eticheta-zile"]')
                .text(eveniment.etichetaDetaliu);
            this.$el.find('[data-role="istoric-descriere"]').text(eveniment.descriereAfisata);
            this.$el.find('[data-role="istoric-stare-text"]').text(eveniment.stareAfisata);
            this.$el.find('[data-role="istoric-pozitie"]').text(
                this.etichetaPozitieIstoric(index)
            );
            $stare.removeClass(claseStare.join(' ')).addClass(eveniment.stareClasa);
            $stare.find('.fas')
                .removeClass(
                    'fa-clock fa-check-circle fa-undo fa-times-circle fa-ban fa-circle fa-flag ' +
                    'fa-plus-circle fa-minus-circle'
                )
                .addClass(eveniment.stareIcon);

            const maxim = Math.max(0, this.istoricSoldPregatit.length - 1);
            const progres = maxim === 0 ? 100 : Math.round(index / maxim * 100);
            this.$el.find('[data-role="istoric-sold-slider"]')
                .css('--istoric-progres', `${progres}%`);
        }

        formateazaValoareCuSemn(valoare) {
            const numar = Number(valoare || 0);

            return numar > 0 ? `+${numar}` : String(numar);
        }

        explicaOperatiaSoldului(eveniment, soldFinal, esteRestituire) {
            const soldInainte = Number(eveniment.soldInainte || 0);
            const zileRezervate = Number(eveniment.zileRezervate || 0);
            const zileRestituite = Number(eveniment.zileRestituite || 0);
            const cheie = esteRestituire ?
                'explicatieEtapaRestituire' : 'explicatieEtapaRezervare';

            return this.translate(cheie, 'messages', 'CerereConcediu')
                .replace('{soldInainte}', String(soldInainte))
                .replace('{zileRezervate}', String(zileRezervate))
                .replace('{zileRestituite}', String(zileRestituite))
                .replace('{soldFinal}', String(soldFinal));
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

        iconStare(stare) {
            return {
                InAsteptare: 'fa-clock',
                Aprobata: 'fa-check-circle',
                AnulareSolicitata: 'fa-undo',
                Respinsa: 'fa-times-circle',
                Anulata: 'fa-ban',
            }[stare] || 'fa-circle';
        }

        programeazaPrevizualizareaZilelor() {
            clearTimeout(this.temporizatorPrevizualizare);
            this.temporizatorPrevizualizare = setTimeout(
                () => this.actualizeazaPrevizualizareaZilelor(),
                150
            );
        }

        async actualizeazaPrevizualizareaZilelor() {
            const dataInceput = this.modelCerere.get('dataInceput');
            const dataSfarsit = this.modelCerere.get('dataSfarsit');

            if (!dataInceput || !dataSfarsit) {
                this.numarCererePrevizualizare++;
                this.afiseazaPrevizualizareaZilelor(
                    null,
                    this.translate(
                        'selecteazaPerioadaPentruCalcul',
                        'messages',
                        'CerereConcediu'
                    )
                );
                return;
            }

            const numarCerere = ++this.numarCererePrevizualizare;
            this.afiseazaPrevizualizareaZilelor(
                null,
                this.translate('seCalculeazaZilele', 'messages', 'CerereConcediu'),
                true
            );

            try {
                const rezultat = await Espo.Ajax.postRequest(
                    'Concedii/previzualizare-perioada',
                    {dataInceput, dataSfarsit}
                );

                if (numarCerere !== this.numarCererePrevizualizare) return;

                const numarZile = Number(rezultat.numarZile || 0);
                const soldDisponibil = Number(this.date.sold?.disponibile || 0);
                const depasesteSoldul = numarZile > soldDisponibil;

                this.afiseazaPrevizualizareaZilelor(
                    numarZile,
                    depasesteSoldul ? this.translate(
                        'zileSolicitateDepasescSoldul',
                        'messages',
                        'CerereConcediu'
                    ) : '',
                    false,
                    depasesteSoldul
                );
            } catch (eroare) {
                if (numarCerere !== this.numarCererePrevizualizare) return;

                if (eroare && 'errorIsHandled' in eroare) {
                    eroare.errorIsHandled = true;
                }
                this.afiseazaPrevizualizareaZilelor(
                    null,
                    this.translate('perioadaConcediuInvalida', 'messages', 'CerereConcediu'),
                    false,
                    true
                );
            }
        }

        afiseazaPrevizualizareaZilelor(
            numarZile,
            explicatie = '',
            seCalculeaza = false,
            esteIncompatibila = false
        ) {
            const $container = this.$el.find('[data-name="previzualizareZile"]');

            if (!$container.length) return;

            const esteValida = Number.isFinite(numarZile) &&
                numarZile > 0 &&
                !esteIncompatibila;

            $container
                .toggleClass('se-calculeaza', seCalculeaza)
                .toggleClass('este-valida', esteValida)
                .toggleClass('este-incompatibila', esteIncompatibila);
            $container.find('[data-role="numar-zile"]')
                .text(numarZile === null ? '—' : String(numarZile));
            $container.find('[data-role="explicatie-zile"]')
                .text(explicatie)
                .toggle(Boolean(explicatie));

            const soldDisponibil = Number(this.date.sold?.disponibile || 0);
            this.poateTrimiteCererea = esteValida &&
                numarZile <= soldDisponibil &&
                !seCalculeaza;
            this.$el.find('[data-action="trimiteCerere"]')
                .prop('disabled', !this.poateTrimiteCererea)
                .attr('aria-disabled', String(!this.poateTrimiteCererea));
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
            if (!this.poateTrimiteCererea) return;

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
