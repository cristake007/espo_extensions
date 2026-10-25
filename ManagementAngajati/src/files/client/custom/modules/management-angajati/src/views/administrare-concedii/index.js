define('modules/management-angajati/views/administrare-concedii/index', 
    ['view', 'management-angajati:instrumente/prezenta'],
    (Vizualizare, InstrumentePrezenta) => {
    return class VizualizareAdministrareConcedii extends Vizualizare {
        template = 'management-angajati:administrare-concedii/index'
        setup() {
            this.lunaSelectata = InstrumentePrezenta.obtineLunaCurenta();
            this.lunaMinimaDisponibila = null;
            this.date = {
                sectiuni: {cereri: [], anulari: [], aprobate: []},
                total: 0,
                deSolutionat: 0,
                rezumatLunar: {
                    luna: this.lunaSelectata,
                    angajatiInConcediu: 0,
                    concediiAprobate: 0,
                    cereriInAsteptare: 0,
                    anulariSolicitate: 0,
                    detalii: {
                        angajatiInConcediu: [],
                        concediiAprobate: [],
                        cereriInAsteptare: [],
                        anulariSolicitate: [],
                    },
                },
            };
            this.sectiuneActiva = 'cereri';
            this.idCerereSelectata = this.options.params?.idCerere || null;
            this.addActionHandler('schimbaSectiune', (e, el) =>
                this.schimbaSectiune(el.dataset.sectiune));
            this.addActionHandler('aproba', (e, el) => this.decide(el.dataset.id, 'Aproba'));
            this.addActionHandler('respinge', (e, el) => this.decide(el.dataset.id, 'Respinge'));
            this.addActionHandler('aprobaAnularea', (e, el) =>
                this.decideAnulare(el.dataset.id, 'Aproba'));
            this.addActionHandler('respingeAnularea', (e, el) =>
                this.decideAnulare(el.dataset.id, 'Respinge'));
            this.addActionHandler('genereazaDocumente', (e, el) =>
                this.genereazaDocumente(el.dataset.id));
            this.addActionHandler('lunaAnterioara', () => this.schimbaLuna(-1));
            this.addActionHandler('lunaUrmatoare', () => this.schimbaLuna(1));
            this.addActionHandler('lunaCurenta', () => this.schimbaLaLunaCurenta());
            this.wait(this.incarca());
        }
        async incarca() {
            this.date = await Espo.Ajax.getRequest(
                'AdministrareConcedii/cereri',
                {luna: this.lunaSelectata}
            );
            this.lunaSelectata = this.date.rezumatLunar?.luna || this.lunaSelectata;
            this.lunaMinimaDisponibila = this.date.lunaMinimaDisponibila || null;

            if (this.idCerereSelectata) {
                const sectiuneSelectata = Object.entries(this.date.sectiuni).find(
                    ([, cereri]) => cereri.some(cerere =>
                        cerere.id === this.idCerereSelectata
                    )
                );

                if (sectiuneSelectata) {
                    this.sectiuneActiva = sectiuneSelectata[0];
                }
            }
        }
        data() {
            const [an, luna] = this.lunaSelectata.split('-');
            const rezumatLunar = this.date.rezumatLunar || {};
            const detaliiLunare = rezumatLunar.detalii || {};
            const pregatesteCerere = cerere => ({
                ...cerere,
                esteSelectata: cerere.id === this.idCerereSelectata,
                dataInceputAfisata: this.getDateTime().toDisplayDate(cerere.dataInceput),
                dataSfarsitAfisata: this.getDateTime().toDisplayDate(cerere.dataSfarsit),
                stareAfisata: this.translate(cerere.stare, 'options', 'CerereConcediu'),
                stareClasa: this.clasaStare(cerere.stare),
                progresAprobari: `${cerere.esteAnulareSolicitata ?
                    cerere.numarAprobariAnulare : cerere.numarAprobari}/${cerere.aprobariNecesare}`,
                areActiuni: cerere.poateGeneraDocumente || cerere.poateFiDecisa ||
                    cerere.poateDecideAnulare || cerere.aprobareInregistrataDeManager,
            });
            const grupuri = {
                cereri: this.date.sectiuni.cereri.map(pregatesteCerere),
                anulari: this.date.sectiuni.anulari.map(pregatesteCerere),
                aprobate: this.date.sectiuni.aprobate.map(pregatesteCerere),
            };
            const cereriAfisate = grupuri[this.sectiuneActiva] || grupuri.cereri;
            const configuratieSectiuni = {
                cereri: {
                    titlu: 'cereriInAsteptare',
                    descriere: 'descriereCereriInAsteptare',
                    fara: 'faraCereriInAsteptare',
                    faraExplicatie: 'faraCereriInAsteptareExplicatie',
                    icon: 'fa-clipboard-check',
                },
                anulari: {
                    titlu: 'cereriAnulare',
                    descriere: 'descriereCereriAnulare',
                    fara: 'faraCereriAnulare',
                    faraExplicatie: 'faraCereriAnulareExplicatie',
                    icon: 'fa-calendar-times',
                },
                aprobate: {
                    titlu: 'concediiAprobate',
                    descriere: 'descriereConcediiAprobate',
                    fara: 'faraConcediiAprobate',
                    faraExplicatie: 'faraConcediiAprobateExplicatie',
                    icon: 'fa-calendar-check',
                },
            };
            const configuratieActiva = configuratieSectiuni[this.sectiuneActiva];

            return {
                titlu: this.translate('administrareConcedii', 'labels', 'AdministrareConcedii'),
                spratitlu: this.translate('managementAngajati', 'labels', 'AdministrareConcedii'),
                descriere: this.translate(
                    'descriereAdministrareConcedii', 'messages', 'AdministrareConcedii'
                ),
                navigareSectiuni: this.translate(
                    'navigareSectiuni', 'labels', 'AdministrareConcedii'
                ),
                sectiuni: [
                    this.dateSectiune('cereri', grupuri.cereri.length, 'fa-clipboard-check'),
                    this.dateSectiune('anulari', grupuri.anulari.length, 'fa-calendar-times'),
                    this.dateSectiune('aprobate', grupuri.aprobate.length, 'fa-calendar-check'),
                ],
                listaCereri: this.translate(
                    configuratieActiva.titlu,
                    'labels',
                    'AdministrareConcedii'
                ),
                descriereLista: this.translate(
                    configuratieActiva.descriere,
                    'messages',
                    'AdministrareConcedii'
                ),
                iconSectiune: configuratieActiva.icon,
                cereri: cereriAfisate,
                areCereri: cereriAfisate.length > 0,
                contor: `${this.date.total} ${this.translate(
                    'cereri', 'labels', 'AdministrareConcedii'
                )} · ${this.date.deSolutionat} ${this.translate(
                    'deSolutionat', 'labels', 'AdministrareConcedii'
                )}`,
                contorSectiune: cereriAfisate.length,
                rezumatLunarTitlu: this.translate(
                    'rezumatLunar', 'labels', 'AdministrareConcedii'
                ),
                rezumatLunarDescriere: this.translate(
                    'descriereRezumatLunar', 'messages', 'AdministrareConcedii'
                ),
                lunaAfisata: `${this.translate(luna, 'luni', 'Prezenta')} ${an}`,
                lunaCurenta: this.translate('lunaCurenta', 'labels', 'Prezenta'),
                lunaAnterioara: this.translate('lunaAnterioara', 'labels', 'Prezenta'),
                lunaUrmatoare: this.translate('lunaUrmatoare', 'labels', 'Prezenta'),
                poateMergeAnterior: !this.lunaMinimaDisponibila ||
                    this.lunaSelectata > this.lunaMinimaDisponibila,
                statisticiLunare: [
                    this.dateStatistica(
                        'angajatiInConcediu',
                        rezumatLunar.angajatiInConcediu,
                        'fa-users',
                        'angajati',
                        detaliiLunare.angajatiInConcediu,
                        'listaAngajatiInConcediu',
                        true
                    ),
                    this.dateStatistica(
                        'concediiAprobateActive',
                        rezumatLunar.concediiAprobate,
                        'fa-calendar-check',
                        'aprobate',
                        detaliiLunare.concediiAprobate,
                        'listaConcediiAprobate'
                    ),
                    this.dateStatistica(
                        'cereriInAsteptareLuna',
                        rezumatLunar.cereriInAsteptare,
                        'fa-clock',
                        'asteptare',
                        detaliiLunare.cereriInAsteptare,
                        'listaCereriInAsteptare'
                    ),
                    this.dateStatistica(
                        'anulariSolicitateLuna',
                        rezumatLunar.anulariSolicitate,
                        'fa-calendar-times',
                        'anulari',
                        detaliiLunare.anulariSolicitate,
                        'listaAnulariSolicitate'
                    ),
                ],
                faraCereri: this.translate(
                    configuratieActiva.fara,
                    'labels',
                    'AdministrareConcedii'
                ),
                faraCereriExplicatie: this.translate(
                    configuratieActiva.faraExplicatie,
                    'messages',
                    'AdministrareConcedii'
                ),
                angajat: this.translate('angajat', 'fields', 'AdministrareConcedii'),
                perioada: this.translate('perioada', 'labels', 'CerereConcediu'),
                zile: this.translate('numarZile', 'fields', 'CerereConcediu'),
                stare: this.translate('stare', 'fields', 'CerereConcediu'),
                aproba: this.translate('aproba', 'labels', 'AdministrareConcedii'),
                respinge: this.translate('respinge', 'labels', 'AdministrareConcedii'),
                aprobareInregistrata: this.translate(
                    'aprobareInregistrata',
                    'labels',
                    'AdministrareConcedii'
                ),
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
        dateStatistica(
            cheie,
            valoare,
            icon,
            clasa,
            detalii = [],
            cheieEtichetaDetalii = '',
            suntAngajati = false
        ) {
            const listaDetalii = (detalii || []).map(detaliu => suntAngajati ? {
                eticheta: detaliu.nume,
                valoare: `${Number(detaliu.numarConcedii || 0)} ${this.translate(
                    Number(detaliu.numarConcedii || 0) === 1 ?
                        'concediuLunar' : 'concediiLunare',
                    'labels',
                    'AdministrareConcedii'
                )}`,
            } : {
                eticheta: detaliu.numeAngajat,
                valoare: `${this.getDateTime().toDisplayDate(detaliu.dataInceput)} – ${
                    this.getDateTime().toDisplayDate(detaliu.dataSfarsit)
                }`,
            });

            return {
                valoare: Number(valoare || 0),
                icon,
                clasa,
                detalii: listaDetalii,
                areDetalii: listaDetalii.length > 0,
                etichetaDetalii: this.translate(
                    cheieEtichetaDetalii,
                    'labels',
                    'AdministrareConcedii'
                ),
                titlu: this.translate(cheie, 'labels', 'AdministrareConcedii'),
                descriere: this.translate(
                    `descriere${cheie.charAt(0).toUpperCase()}${cheie.slice(1)}`,
                    'messages',
                    'AdministrareConcedii'
                ),
            };
        }
        dateSectiune(cheie, numar, icon) {
            return {
                cheie,
                numar,
                icon,
                esteActiva: this.sectiuneActiva === cheie,
                clasaActiva: this.sectiuneActiva === cheie ? 'este-activa' : '',
                titlu: this.translate(
                    `sectiune${cheie.charAt(0).toUpperCase()}${cheie.slice(1)}`,
                    'labels',
                    'AdministrareConcedii'
                ),
                descriere: this.translate(
                    `descriereSelector${cheie.charAt(0).toUpperCase()}${cheie.slice(1)}`,
                    'messages',
                    'AdministrareConcedii'
                ),
            };
        }
        async schimbaSectiune(sectiune) {
            if (!['cereri', 'anulari', 'aprobate'].includes(sectiune) ||
                sectiune === this.sectiuneActiva) {
                return;
            }

            this.sectiuneActiva = sectiune;
            this.idCerereSelectata = null;
            await this.reRender();
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
                await this.incarca();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
            }
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
