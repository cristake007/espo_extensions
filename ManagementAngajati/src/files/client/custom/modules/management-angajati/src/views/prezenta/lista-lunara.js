define(
    ['view', 'management-angajati:instrumente/prezenta'],
    (View, InstrumentePrezenta) => {
    return class VizualizarePrezentaPersonala extends View {

        template = 'management-angajati:prezenta/lista-lunara'

        setup() {
            const lunaDinRuta = this.options.params?.luna;

            this.lunaSelectata = typeof lunaDinRuta === 'string' &&
                /^\d{4}-(0[1-9]|1[0-2])$/.test(lunaDinRuta) ?
                lunaDinRuta :
                InstrumentePrezenta.obtineLunaCurenta();
            this.zile = [];
            this.ziAstazi = null;
            this.dataCurenta = null;
            this.cicluLunar = {};
            this.esteLunaEditabila = true;
            this.lunaMinimaDisponibila = null;
            this.situatieCic = {activa: false, dataInceput: null, dataSfarsit: null};

            this.addActionHandler('lunaAnterioara', () => this.schimbaLuna(-1));
            this.addActionHandler('lunaUrmatoare', () => this.schimbaLuna(1));
            this.addActionHandler('lunaCurenta', () => this.schimbaLaLunaCurenta());
            this.addActionHandler('marcheazaPrezenta', (eveniment, element) => {
                this.marcheazaPrezenta(element.dataset.data, element.dataset.cod);
            });

            this.wait(this.incarcaLuna());
        }

        data() {
            const [an, luna] = this.lunaSelectata.split('-');
            const zileInregistrate = this.zile.filter(zi => zi.codCurent !== '—').length;
            const zileNecompletate = this.zile.filter(
                zi => !zi.esteViitoare &&
                    zi.esteZiLucratoare &&
                    zi.areDataAngajare &&
                    !zi.esteInainteDeAngajare &&
                    zi.codCurent === '—'
            ).length;
            const zileViitoare = this.zile.filter(zi => zi.esteViitoare).length;
            const esteInchisa = this.cicluLunar.esteInchisa === true;
            const prezentareAstazi = this.prezentareCod(this.ziAstazi?.codCurent);
            const stareAstazi = this.ziAstazi?.stare ||
                this.translate('ziNelucratoare', 'labels', 'Prezenta');
            const esteCicActiv = this.situatieCic.activa === true;

            return {
                titlu: this.translate('prezentaPersonala', 'labels', 'Prezenta'),
                spratitlu: this.translate('prezentaAngajatului', 'labels', 'Prezenta'),
                descriere: this.translate(
                    'descrierePrezentaPersonala', 'messages', 'Prezenta'
                ),
                esteCicActiv: esteCicActiv,
                cicTitlu: this.translate('prezentaIndisponibilaCic', 'labels', 'Prezenta'),
                cicStare: this.translate('cicActiv', 'labels', 'Prezenta'),
                cicExplicatie: this.translate('prezentaSuspendataCic', 'messages', 'Prezenta'),
                cicPerioada: this.translate('perioadaCic', 'labels', 'Prezenta'),
                cicDataInceput: this.situatieCic.dataInceput ?
                    this.getDateTime().toDisplayDate(this.situatieCic.dataInceput) : '',
                cicDataSfarsit: this.situatieCic.dataSfarsit ?
                    this.getDateTime().toDisplayDate(this.situatieCic.dataSfarsit) : '',
                cicFaraActiune: this.translate('cicFaraPontare', 'labels', 'Prezenta'),
                cicContactManager: this.translate('cicContactManager', 'messages', 'Prezenta'),
                lunaAfisata: `${this.translate(luna, 'luni', 'Prezenta')} ${an}`,
                lunaCurenta: this.translate('lunaCurenta', 'labels', 'Prezenta'),
                anterior: this.translate('lunaAnterioara', 'labels', 'Prezenta'),
                urmator: this.translate('lunaUrmatoare', 'labels', 'Prezenta'),
                poateMergeAnterior: !this.lunaMinimaDisponibila ||
                    this.lunaSelectata > this.lunaMinimaDisponibila,
                data: this.translate('dataPrezenta', 'fields', 'Prezenta'),
                descriereLuna: this.translate('descriereLuna', 'messages', 'Prezenta'),
                stareLunaAfisata: this.translate(
                    esteInchisa ? 'lunaInchisa' :
                        (this.esteLunaEditabila ? 'lunaDeschisa' : 'lunaNeeditabila'),
                    'labels',
                    'Prezenta'
                ),
                stareLunaClasa: esteInchisa || !this.esteLunaEditabila ?
                    'label-default' : 'label-success',
                areZile: this.zile.length > 0,
                faraZile: this.translate('faraZile', 'labels', 'Prezenta'),
                faraZileExplicatie: this.translate(
                    'faraZileExplicatie', 'messages', 'Prezenta'
                ),
                prezentaAstazi: this.translate('prezentaAstazi', 'labels', 'Prezenta'),
                descrierePrezentaAstazi: this.translate(
                    'descrierePrezentaAstazi', 'messages', 'Prezenta'
                ),
                stareCurenta: this.translate('stareCurenta', 'labels', 'Prezenta'),
                alegePrezenta: this.translate('alegePrezenta', 'labels', 'Prezenta'),
                registruLunar: this.translate('registruLunar', 'labels', 'Prezenta'),
                dataAstazi: this.dataCurenta ?
                    this.getDateTime().toDisplayDate(this.dataCurenta) : '',
                etichetaAstazi: this.ziAstazi?.codEticheta ||
                    stareAstazi,
                stareAstazi: stareAstazi,
                clasaAstazi: prezentareAstazi.clasa,
                iconAstazi: prezentareAstazi.icon,
                optiuniAstazi: this.ziAstazi?.optiuni || this.creeazaOptiuni(null, false),
                poateMarcaAstazi: this.ziAstazi?.poateFiCompletata === true,
                nicioActiuneNecesara: this.translate(
                    'nicioActiuneNecesara', 'labels', 'Prezenta'
                ),
                zileCompletate: this.translate('zileCompletate', 'labels', 'Prezenta'),
                zileNecompletate: this.translate('zileNecompletate', 'labels', 'Prezenta'),
                zileViitoare: this.translate('zileViitoare', 'labels', 'Prezenta'),
                numarZileCompletate: zileInregistrate,
                numarZileNecompletate: zileNecompletate,
                numarZileViitoare: zileViitoare,
                zile: this.zile,
            };
        }

        async incarcaLuna() {
            const lunaCurenta = InstrumentePrezenta.obtineLunaCurenta();
            const [calendar, colectie] = await Promise.all([
                Espo.Ajax.getRequest(
                    'Prezenta/prezenta-personala',
                    {luna: this.lunaSelectata}
                ),
                this.incarcaColectie(this.lunaSelectata),
            ]);
            this.situatieCic = calendar.situatieCic || {
                activa: false,
                dataInceput: null,
                dataSfarsit: null,
            };
            this.dataCurenta = calendar.dataCurenta || null;
            this.cicluLunar = calendar.cicluLunar;
            this.esteLunaEditabila = calendar.esteLunaEditabila === true;
            this.lunaMinimaDisponibila = calendar.lunaMinimaDisponibila || null;

            if (this.situatieCic.activa === true) {
                this.colectiePrezente = colectie;
                this.colectiePrezenteAstazi = colectie;
                this.zile = [];
                this.ziAstazi = null;

                return;
            }

            let calendarAstazi = calendar;
            let colectieAstazi = colectie;

            if (this.lunaSelectata !== lunaCurenta) {
                [calendarAstazi, colectieAstazi] = await Promise.all([
                    Espo.Ajax.getRequest(
                        'Prezenta/prezenta-personala',
                        {luna: lunaCurenta}
                    ),
                    this.incarcaColectie(lunaCurenta),
                ]);
            }

            this.colectiePrezente = colectie;
            this.colectiePrezenteAstazi = colectieAstazi;
            this.dataCurenta = calendarAstazi.dataCurenta || calendar.dataCurenta || null;
            this.pregatesteZile(calendar.zile);
            this.pregatesteAstazi(calendarAstazi.zile, colectieAstazi, calendarAstazi);
        }

        async incarcaColectie(lunaSelectata) {
            const colectie = await this.getCollectionFactory().create('Prezenta');
            const primaZi = `${lunaSelectata}-01`;
            const [an, luna] = lunaSelectata.split('-').map(Number);
            const numarZile = new Date(an, luna, 0).getDate();
            const ultimaZi = `${lunaSelectata}-${String(numarZile).padStart(2, '0')}`;

            colectie.where = [
                {
                    type: 'equals',
                    attribute: 'assignedUserId',
                    value: this.getUser().id,
                },
                {
                    type: 'between',
                    attribute: 'dataPrezenta',
                    value: [primaZi, ultimaZi],
                },
            ];
            colectie.maxSize = 32;

            await colectie.fetch({reset: true});

            return colectie;
        }

        pregatesteZile(zileCalendar) {
            const prezente = {};
            const poateCrea = this.getAcl().check('Prezenta', 'create');
            const coduriPrezenta = InstrumentePrezenta.obtineCoduriPrezenta(
                this.getMetadata()
            );

            this.colectiePrezente.models.forEach(model => {
                prezente[model.get('dataPrezenta')] = model;
            });

            this.zile = zileCalendar.map(zi => this.pregatesteZi(
                zi,
                prezente[zi.data] || null,
                poateCrea,
                coduriPrezenta
            ));
        }

        pregatesteAstazi(zileCalendar, colectie, calendar) {
            const zi = zileCalendar.find(item => item.data === this.dataCurenta) || null;

            if (!zi) {
                this.ziAstazi = null;

                return;
            }

            const model = colectie.models.find(
                item => item.get('dataPrezenta') === this.dataCurenta
            ) || null;

            this.ziAstazi = this.pregatesteZi(
                zi,
                model,
                this.getAcl().check('Prezenta', 'create'),
                InstrumentePrezenta.obtineCoduriPrezenta(this.getMetadata()),
                {
                    cicluLunar: calendar.cicluLunar,
                    esteLunaEditabila: calendar.esteLunaEditabila === true,
                }
            );
        }

        pregatesteZi(zi, model, poateCrea, coduriPrezenta, contextLuna = {}) {
            const poateEdita = model ? this.getAcl().checkModel(model, 'edit') : false;
            const codCurent = zi.codPrezentaAutomat || (model ? model.get('codPrezenta') : null);
            const poateMarca = !zi.codPrezentaAutomat &&
                zi.poateFiCompletata &&
                (model ? poateEdita : poateCrea);
            const prezentare = this.prezentareCod(codCurent);

            return {
                ...zi,
                dataAfisata: this.getDateTime().toDisplayDate(zi.data),
                numeZi: this.translate(String(zi.ziSaptamana), 'zileSaptamana', 'Prezenta'),
                codCurent: codCurent || '—',
                stare: this.obtineStareZi(
                    zi,
                    model,
                    poateCrea,
                    poateEdita,
                    contextLuna.cicluLunar || this.cicluLunar,
                    contextLuna.esteLunaEditabila ?? this.esteLunaEditabila
                ),
                randClasa: this.clasaRand(zi, codCurent, poateMarca),
                codClasa: prezentare.clasa,
                codIcon: prezentare.icon,
                codEticheta: prezentare.eticheta,
                poateFiCompletata: poateMarca,
                optiuni: this.creeazaOptiuni(codCurent, poateMarca, coduriPrezenta),
            };
        }

        creeazaOptiuni(codCurent, poateMarca, coduriPrezenta = null) {
            const coduri = coduriPrezenta ||
                InstrumentePrezenta.obtineCoduriPrezenta(this.getMetadata());

            return coduri.map(cod => {
                const prezentare = this.prezentareCod(cod);

                return {
                    cod: cod,
                    eticheta: prezentare.eticheta,
                    ajutor: this.translate(`ajutor${cod}`, 'messages', 'Prezenta'),
                    icon: prezentare.icon,
                    butonClasa: prezentare.butonClasa,
                    actiuneClasa: prezentare.actiuneClasa,
                    activ: cod === codCurent,
                    poateMarca: poateMarca,
                };
            });
        }

        prezentareCod(cod) {
            const configuratie = {
                P: {clasa: 'este-prezent', icon: 'fa-building', butonClasa: 'btn-success', actiuneClasa: 'actiune-prezent'},
                D: {clasa: 'este-delegatie', icon: 'fa-car', butonClasa: 'btn-warning', actiuneClasa: 'actiune-delegatie'},
                CM: {clasa: 'este-medical', icon: 'fa-briefcase-medical', butonClasa: 'btn-danger', actiuneClasa: 'actiune-medical'},
                CO: {clasa: 'este-concediu', icon: 'fa-umbrella-beach', butonClasa: 'btn-info', actiuneClasa: 'actiune-info'},
                CIC: {clasa: 'este-cic', icon: 'fa-child', butonClasa: 'btn-info', actiuneClasa: 'actiune-info'},
            }[cod] || {
                clasa: 'este-nemarcat',
                icon: 'fa-minus-circle',
                butonClasa: 'btn-default',
                actiuneClasa: 'actiune-neutra',
            };

            return {
                ...configuratie,
                eticheta: cod && cod !== '—' ? this.getLanguage().translateOption(
                    cod,
                    'codPrezenta',
                    'Prezenta'
                ) : this.translate('nemarcat', 'labels', 'Prezenta'),
            };
        }

        clasaRand(zi, codCurent, poateMarca) {
            if (zi.esteViitoare || zi.esteInainteDeAngajare || !zi.esteZiLucratoare) {
                return 'este-neutra';
            }

            if (codCurent === 'CIC' || codCurent === 'CO') return 'este-info';
            if (codCurent === 'P') return 'este-succes';
            if (codCurent === 'D') return 'este-atentionare';
            if (codCurent === 'CM') return 'este-pericol';
            if (poateMarca) return 'este-lipsa';

            return 'este-neutra';
        }

        obtineStareZi(
            zi,
            model,
            poateCrea,
            poateEdita,
            cicluLunar,
            esteLunaEditabila
        ) {
            if (!zi.areDataAngajare) {
                return this.translate('dataAngajareLipsa', 'labels', 'Prezenta');
            }

            if (zi.esteInainteDeAngajare) {
                return this.translate('inainteDeAngajare', 'labels', 'Prezenta');
            }

            if (zi.esteCic) {
                return this.translate('cicAutomat', 'labels', 'Prezenta');
            }

            if (zi.esteConcediu) {
                return this.translate('concediuAprobatAutomat', 'labels', 'Prezenta');
            }

            if (zi.esteViitoare) {
                return this.translate('ziViitoare', 'labels', 'Prezenta');
            }

            if (zi.esteSarbatoareLegala) {
                return this.translate('sarbatoareLegala', 'labels', 'Prezenta');
            }

            if (!zi.esteZiLucratoare) {
                return this.translate('ziNelucratoare', 'labels', 'Prezenta');
            }

            if (model && !poateEdita) {
                return this.translate('faraPermisiuneModificare', 'labels', 'Prezenta');
            }

            if (model) {
                return this.translate('prezentaInregistrata', 'labels', 'Prezenta');
            }

            if (cicluLunar.esteInchisa) {
                return this.translate('lunaInchisa', 'labels', 'Prezenta');
            }

            if (!esteLunaEditabila) {
                return this.translate('lunaNeeditabila', 'labels', 'Prezenta');
            }

            if (!poateCrea) {
                return this.translate('faraPermisiuneCreare', 'labels', 'Prezenta');
            }

            return this.translate('disponibilaPentruPrezenta', 'labels', 'Prezenta');
        }

        async marcheazaPrezenta(data, cod) {
            const modele = [
                ...this.colectiePrezente.models,
                ...(this.colectiePrezenteAstazi === this.colectiePrezente ? [] :
                    this.colectiePrezenteAstazi.models),
            ];
            const modelExistent = modele.find(
                model => model.get('dataPrezenta') === data
            );
            let mesajConfirmare = null;

            this.marcheazaZiOcupata(data, true);
            Espo.Ui.notifyWait();

            try {
                if (modelExistent) {
                    await modelExistent.save({codPrezenta: cod}, {wait: true, patch: true});
                    mesajConfirmare = this.translate(
                        'prezentaModificata',
                        'messages',
                        'Prezenta'
                    );
                } else {
                    const model = await this.getModelFactory().create('Prezenta');

                    model.set({
                        dataPrezenta: data,
                        codPrezenta: cod,
                        assignedUserId: this.getUser().id,
                        assignedUserName: this.getUser().get('name'),
                    });

                    await model.save(null, {wait: true});
                    mesajConfirmare = this.translate(
                        'prezentaCreata',
                        'messages',
                        'Prezenta'
                    );
                }

                await this.incarcaLuna();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
                this.marcheazaZiOcupata(data, false);
            }

            Espo.Ui.success(mesajConfirmare);
        }

        marcheazaZiOcupata(data, ocupata) {
            const rand = this.$el.find(`[data-zi="${data}"]`);
            rand.toggleClass('este-ocupata', ocupata).attr(
                'aria-busy',
                ocupata ? 'true' : 'false'
            );
            rand.find('[data-action="marcheazaPrezenta"]').prop('disabled', ocupata);
        }

        async schimbaLuna(deplasare) {
            const lunaNoua = InstrumentePrezenta.deplaseazaLuna(
                this.lunaSelectata,
                deplasare
            );

            if (deplasare < 0 && this.lunaMinimaDisponibila &&
                lunaNoua < this.lunaMinimaDisponibila) {
                return;
            }

            this.lunaSelectata = lunaNoua;
            await this.reincarca();
        }

        async schimbaLaLunaCurenta() {
            this.lunaSelectata = InstrumentePrezenta.obtineLunaCurenta();
            await this.reincarca();
        }

        async reincarca() {
            Espo.Ui.notifyWait();

            try {
                await this.incarcaLuna();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
            }
        }

    };
    }
);
