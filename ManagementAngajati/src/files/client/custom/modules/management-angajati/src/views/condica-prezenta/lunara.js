define('modules/management-angajati/views/condica-prezenta/lunara', 
    ['view', 'management-angajati:instrumente/prezenta'],
    (View, InstrumentePrezenta) => {
    return class VizualizareCondicaPrezentaLunara extends View {

        template = 'management-angajati:condica-prezenta/lunara'

        setup() {
            this.lunaSelectata = InstrumentePrezenta.obtineLunaCurenta();
            this.angajati = [];
            this.zile = [];
            this.statistici = {};
            this.actiuniPrezenta = {};
            this.urmatoareaCheieActiune = 0;
            this.marcariInCurs = new Set();
            this.lunaMinimaDisponibila = null;
            this.cicluLunar = {
                stare: 'deschisa',
                esteInchisa: false,
                permiteExportCondicaIncompleta: false,
                lunaMinimaEditabila: InstrumentePrezenta.obtineLunaCurenta(),
            };

            this.addActionHandler('lunaAnterioara', () => this.schimbaLuna(-1));
            this.addActionHandler('lunaUrmatoare', () => this.schimbaLuna(1));
            this.addActionHandler('lunaCurenta', () => this.schimbaLaLunaCurenta());
            this.addActionHandler('marcheazaPrezenta', (eveniment, element) => {
                this.marcheazaPrezentaDupaCheie(
                    element.dataset.cheieActiune,
                    element
                );
            });
            this.addActionHandler('inchideLuna', () => this.inchideLuna());
            this.addActionHandler('trimiteRemindere', () => this.trimiteRemindere());

            this.wait(this.incarcaLuna());
        }

        data() {
            const [an, luna] = this.lunaSelectata.split('-');
            const angajatiAfectatiLista = this.angajati.filter(
                angajat => Number(angajat.zileLipsa || 0) > 0
            ).sort((primul, alDoilea) =>
                Number(alDoilea.zileLipsa || 0) - Number(primul.zileLipsa || 0) ||
                String(primul.numeAngajat || '').localeCompare(String(alDoilea.numeAngajat || ''))
            );
            const angajatiAfectati = angajatiAfectatiLista.length;
            const zileScadente = this.angajati.reduce(
                (total, angajat) => total + Number(angajat.zileScadente || 0),
                0
            );
            const zileCompletate = this.angajati.reduce(
                (total, angajat) => total + Number(angajat.zileCompletate || 0),
                0
            );
            const calendareLipsa = this.angajati.filter(
                angajat => angajat.areCalendarLucru !== true
            );
            const motiveBlocare = this.obtineMotiveBlocare();
            const poateExporta = this.cicluLunar.esteInchisa === true ||
                this.cicluLunar.permiteExportCondicaIncompleta === true;

            return {
                titlu: this.translate('condicaPrezenta', 'labels', 'CondicaPrezenta'),
                spratitlu: this.translate('prezentaEchipei', 'labels', 'CondicaPrezenta'),
                descrierePagina: this.translate(
                    'descriereCondica',
                    'messages',
                    'CondicaPrezenta'
                ),
                lunaAfisata: `${this.translate(luna, 'luni', 'Prezenta')} ${an}`,
                lunaCurenta: this.translate('lunaCurenta', 'labels', 'Prezenta'),
                anterior: this.translate('lunaAnterioara', 'labels', 'Prezenta'),
                urmator: this.translate('lunaUrmatoare', 'labels', 'Prezenta'),
                poateMergeAnterior: !this.lunaMinimaDisponibila ||
                    this.lunaSelectata > this.lunaMinimaDisponibila,
                data: this.translate('dataPrezenta', 'fields', 'Prezenta'),
                angajati: this.angajati,
                zile: this.zile,
                statistici: this.statistici,
                cicluLunar: this.cicluLunar,
                poateInchideLuna:
                    !this.cicluLunar.esteInchisa &&
                    this.statistici.poateFiInchisa === true,
                inchideLuna: this.translate('inchideLuna', 'labels', 'CondicaPrezenta'),
                trimiteRemindere: this.translate(
                    'trimiteRemindere',
                    'labels',
                    'CondicaPrezenta'
                ),
                descarcaXlsx: this.translate('descarcaXlsx', 'labels', 'CondicaPrezenta'),
                descarcaPdf: this.translate('descarcaPdf', 'labels', 'CondicaPrezenta'),
                poateExporta,
                urlExportXlsx: this.obtineUrlExport('xlsx'),
                urlExportPdf: this.obtineUrlExport('pdf'),
                poateTrimiteRemindere:
                    !this.cicluLunar.esteInchisa &&
                    this.lunaSelectata >= this.cicluLunar.lunaMinimaEditabila &&
                    this.lunaSelectata <= InstrumentePrezenta.obtineLunaCurenta(),
                stareLuna: this.translate(
                    this.cicluLunar.stare,
                    'stariLuna',
                    'CondicaPrezenta'
                ),
                actiuniRapide: this.translate('actiuniRapide', 'labels', 'CondicaPrezenta'),
                actiuniRapideDescriere: this.translate(
                    'actiuniRapideDescriere',
                    'messages',
                    'CondicaPrezenta'
                ),
                stareLunara: this.translate('stareLunara', 'labels', 'CondicaPrezenta'),
                stareLunaraDescriere: this.translate(
                    'stareLunaraDescriere',
                    'messages',
                    'CondicaPrezenta'
                ),
                vedereZilnica: this.translate('vedereZilnica', 'labels', 'CondicaPrezenta'),
                vedereZilnicaDescriere: this.translate(
                    'vedereZilnicaDescriere',
                    'messages',
                    'CondicaPrezenta'
                ),
                legendaStari: this.translate('legendaStari', 'labels', 'CondicaPrezenta'),
                pregatireExport: this.translate('pregatireExport', 'labels', 'CondicaPrezenta'),
                exportDisponibil: this.translate(
                    poateExporta ? 'exportDisponibil' : 'exportIndisponibil',
                    'labels',
                    'CondicaPrezenta'
                ),
                angajatiAfectati,
                detaliiCompletare: this.angajati.map(angajat => ({
                    idAngajat: angajat.idAngajat,
                    nume: angajat.numeAngajat,
                    valoare: `${Number(angajat.zileCompletate || 0)}/${Number(angajat.zileScadente || 0)}`,
                })),
                detaliiLipsa: angajatiAfectatiLista.map(angajat => ({
                    idAngajat: angajat.idAngajat,
                    nume: angajat.numeAngajat,
                    valoare: Number(angajat.zileLipsa || 0),
                })),
                detaliiCalendare: calendareLipsa.map(angajat => ({
                    nume: angajat.numeAngajat,
                })),
                detaliiExport: poateExporta ? [] : motiveBlocare.map(motiv => ({nume: motiv})),
                descriereCompletare: this.formateazaMesajTradus(
                    'descriereCompletare',
                    {completate: zileCompletate, scadente: zileScadente}
                ),
                descriereLipsa: this.formateazaMesajTradus(
                    'descriereLipsa',
                    {lipsa: Number(this.statistici.zileLipsa || 0), angajati: angajatiAfectati}
                ),
                descriereCalendare: this.formateazaMesajTradus(
                    'descriereCalendare',
                    {
                        configurate: Number(this.statistici.calendareConfigurate || 0),
                        total: Number(this.statistici.numarAngajati || 0),
                    }
                ),
                descriereExport: this.translate(
                    this.cicluLunar.esteInchisa ?
                        'descriereExportDisponibil' :
                        (poateExporta ?
                            'descriereExportIncompletPermis' :
                            'descriereExportIndisponibil'),
                    'messages',
                    'CondicaPrezenta'
                ),
                etichetaAngajatiAfectati: this.translate(
                    'angajatiAfectati',
                    'labels',
                    'CondicaPrezenta'
                ),
                completatPeAngajat: this.translate(
                    'completatPeAngajat',
                    'labels',
                    'CondicaPrezenta'
                ),
                calendareNeconfigurate: this.translate(
                    'calendareNeconfigurate',
                    'labels',
                    'CondicaPrezenta'
                ),
                detalii: this.translate('detalii', 'labels', 'CondicaPrezenta'),
                totalAngajatiZile: this.translate('angajatiZile', 'labels', 'CondicaPrezenta')
                    .replace('{angajati}', String(this.statistici.numarAngajati || 0))
                    .replace('{zile}', String(this.zile.length)),
                legenda: [
                    ['este-prezent', 'P'],
                    ['este-delegatie', 'D'],
                    ['este-medical', 'CM'],
                    ['este-concediu', 'CO'],
                    ['este-cic', 'CIC'],
                ].map(([clasa, cod]) => ({
                    clasa,
                    eticheta: this.getLanguage().translateOption(
                        cod,
                        'codPrezenta',
                        'Prezenta'
                    ),
                })).concat([{
                    clasa: 'este-lipsa',
                    eticheta: this.translate('prezenteLipsa', 'labels', 'CondicaPrezenta'),
                }]),
                etichete: {
                    completare: this.translate('completare', 'labels', 'CondicaPrezenta'),
                    lipsa: this.translate('prezenteLipsa', 'labels', 'CondicaPrezenta'),
                    calendare: this.translate('calendareLucru', 'labels', 'CondicaPrezenta'),
                    angajati: this.translate('angajati', 'labels', 'CondicaPrezenta'),
                    remote: this.translate('remote', 'labels', 'CondicaPrezenta'),
                    calendarLipsa: this.translate('calendarLipsa', 'labels', 'CondicaPrezenta'),
                    dataAngajareLipsa: this.translate(
                        'dataAngajareLipsa',
                        'labels',
                        'Prezenta'
                    ),
                    inainteDeAngajare: this.translate(
                        'inainteDeAngajare',
                        'labels',
                        'Prezenta'
                    ),
                    ziNelucratoare: this.translate('ziNelucratoare', 'labels', 'Prezenta'),
                    ziViitoare: this.translate('ziViitoare', 'labels', 'Prezenta'),
                    stareCompletitudine: this.obtineStareCompletitudine(),
                    motiveBlocare: this.obtineMotiveBlocare(),
                },
            };
        }

        formateazaMesajTradus(cheie, valori) {
            let mesaj = this.translate(cheie, 'messages', 'CondicaPrezenta');

            Object.entries(valori).forEach(([nume, valoare]) => {
                mesaj = mesaj.replace(`{${nume}}`, String(valoare));
            });

            return mesaj;
        }

        obtineStareCompletitudine() {
            if (this.cicluLunar.esteInchisa) {
                return this.translate('lunaInghetata', 'labels', 'CondicaPrezenta');
            }

            if (this.statistici.poateFiInchisa === true) {
                return this.translate('pregatitaPentruInchidere', 'labels', 'CondicaPrezenta');
            }

            return this.translate('necesitaCompletare', 'labels', 'CondicaPrezenta');
        }

        obtineMotiveBlocare() {
            if (this.cicluLunar.esteInchisa) {
                return [];
            }

            return (this.statistici.motiveBlocareInchidere || []).map(motiv =>
                this.translate(motiv, 'motiveBlocareInchidere', 'CondicaPrezenta')
            );
        }

        obtineUrlExport(format) {
            const managerTema = this.getThemeManager();
            const culoriGrafic = managerTema.getParam('chartColorList') || [];
            const parametri = new URLSearchParams({
                luna: this.lunaSelectata,
                culoarePrincipala: culoriGrafic[0] || '',
                culoareAccent: culoriGrafic[2] || managerTema.getParam('chartSuccessColor') || '',
                culoareText: managerTema.getParam('textColor') || '',
                culoareContur: managerTema.getParam('chartGridColor') || '',
                culoarePrezent: managerTema.getParam('chartSuccessColor') || culoriGrafic[4] || '',
                culoareDelegatie: culoriGrafic[5] || culoriGrafic[2] || '',
                culoareMedical: culoriGrafic[2] || '',
                culoareCic: culoriGrafic[3] || culoriGrafic[0] || '',
                culoareConcediu: culoriGrafic[1] || culoriGrafic[3] || '',
            });

            return `${this.getBasePath()}api/v1/CondicaPrezenta/export/${format}?${parametri}`;
        }

        async incarcaLuna() {
            const raspuns = await Espo.Ajax.getRequest(
                'CondicaPrezenta/condica-lunara',
                {luna: this.lunaSelectata}
            );

            this.angajati = raspuns.angajati.map(angajat => ({
                ...angajat,
                rezumat: !angajat.areCalendarLucru ?
                    this.translate('calendarLipsa', 'labels', 'CondicaPrezenta') :
                    (!angajat.areDataAngajare ?
                        this.translate('dataAngajareLipsa', 'labels', 'Prezenta') :
                        `${angajat.zileCompletate}/${angajat.zileScadente}`),
            }));
            const angajatiDupaId = Object.fromEntries(
                raspuns.angajati.map(angajat => [angajat.idAngajat, angajat])
            );

            this.actiuniPrezenta = {};
            this.urmatoareaCheieActiune = 0;
            this.zile = raspuns.zile.map(zi => ({
                ...zi,
                dataAfisata: this.getDateTime().toDisplayDate(zi.data),
                numeZi: this.translate(String(zi.ziSaptamana), 'zileSaptamana', 'Prezenta'),
                celule: zi.celule.map(celula => this.pregatesteCelula(
                    celula,
                    angajatiDupaId[celula.idAngajat],
                    zi.data
                )),
            }));
            this.statistici = raspuns.statistici;
            this.cicluLunar = raspuns.cicluLunar;
            this.lunaMinimaDisponibila = raspuns.lunaMinimaDisponibila || null;
        }

        pregatesteCelula(celula, angajat, data) {
            let stare = celula.codPrezenta || '—';

            if (!celula.areDataAngajare) {
                stare = this.translate('dataAngajareLipsa', 'labels', 'Prezenta');
            } else if (celula.esteInainteDeAngajare) {
                stare = this.translate('inainteDeAngajare', 'labels', 'Prezenta');
            } else if (!celula.esteZiLucratoare) {
                stare = this.translate('ziNelucratoare', 'labels', 'Prezenta');
            } else if (celula.esteViitoare && !celula.codPrezenta) {
                stare = this.translate('ziViitoare', 'labels', 'Prezenta');
            }

            const coduriPrezenta = InstrumentePrezenta.obtineCoduriPrezenta(
                this.getMetadata()
            );

            return {
                ...celula,
                data: data,
                numeAngajat: angajat?.numeAngajat || celula.idAngajat,
                stare: stare,
                clasaVizuala: this.obtineClasaVizualaCelula(celula),
                poateMarca: celula.poateFiCompletata || celula.poateFiCorectata,
                optiuni: coduriPrezenta.map(cod => this.pregatesteActiunePrezenta(
                    celula,
                    angajat,
                    data,
                    cod
                )),
            };
        }

        obtineClasaVizualaCelula(celula) {
            if (celula.esteLipsa) return 'este-lipsa';
            if (celula.esteCic || celula.codPrezenta === 'CIC') return 'este-cic';
            if (celula.esteConcediu || celula.codPrezenta === 'CO') return 'este-concediu';

            return {
                P: 'este-prezent',
                D: 'este-delegatie',
                CM: 'este-medical',
            }[celula.codPrezenta] || 'este-inactiva';
        }

        pregatesteActiunePrezenta(celula, angajat, data, cod) {
            const cheieActiune = `actiune-${++this.urmatoareaCheieActiune}`;

            this.actiuniPrezenta[cheieActiune] = {
                idAngajat: celula.idAngajat,
                data: data,
                cod: cod,
            };

            return {
                cod: cod,
                cheieActiune: cheieActiune,
                eticheta: this.getLanguage().translateOption(
                    cod,
                    'codPrezenta',
                    'Prezenta'
                ),
                activ: cod === celula.codPrezenta,
                poateMarca: celula.poateFiCompletata || celula.poateFiCorectata,
            };
        }

        async marcheazaPrezentaDupaCheie(cheieActiune, element) {
            const actiune = this.actiuniPrezenta[cheieActiune];

            if (!actiune) {
                Espo.Ui.error(this.translate(
                    'actiunePrezentaInvalida',
                    'messages',
                    'CondicaPrezenta'
                ));

                return;
            }

            await this.marcheazaPrezenta(
                actiune.idAngajat,
                actiune.data,
                actiune.cod,
                element
            );
        }

        async marcheazaPrezenta(idAngajat, data, cod, element) {
            if (this.cicluLunar.esteInchisa) {
                await this.corecteazaPrezenta(idAngajat, data, cod);

                return;
            }

            const cheieCelula = `${idAngajat}:${data}`;

            if (this.marcariInCurs.has(cheieCelula)) {
                return;
            }

            const celula = this.obtineCelula(idAngajat, data);

            if (!celula) {
                Espo.Ui.error(this.translate(
                    'actiunePrezentaInvalida',
                    'messages',
                    'CondicaPrezenta'
                ));

                return;
            }

            const codAnterior = celula.codPrezenta || null;

            this.marcariInCurs.add(cheieCelula);
            this.evidentiazaMarcareSelectata(element);
            this.seteazaCelulaInCurs(element, true);

            try {
                const raspuns = await Espo.Ajax.postRequest(
                    'CondicaPrezenta/marcheaza-prezenta',
                    {idAngajat: idAngajat, data: data, cod: cod}
                );

                this.aplicaMarcareSalvata(
                    celula,
                    idAngajat,
                    cod,
                    element
                );

                Espo.Ui.success(this.translate(
                    raspuns.aFostCreata ? 'prezentaCreata' : 'prezentaModificata',
                    'messages',
                    'Prezenta'
                ));
            } catch (eroare) {
                this.restaureazaMarcareVizuala(element, codAnterior);
                Espo.Ui.error(this.translate(
                    'marcarePrezentaInvalida',
                    'messages',
                    'CondicaPrezenta'
                ));
            } finally {
                this.marcariInCurs.delete(cheieCelula);
                this.seteazaCelulaInCurs(element, false);
            }
        }

        obtineCelula(idAngajat, data) {
            const zi = this.zile.find(element => element.data === data);

            return zi?.celule.find(element => element.idAngajat === idAngajat) || null;
        }

        seteazaCelulaInCurs(element, esteInCurs) {
            const grup = element.closest('.condica-prezenta-actiuni');
            const celula = element.closest('.condica-prezenta-celula');

            grup?.querySelectorAll('.btn').forEach(buton => {
                buton.disabled = esteInCurs;
            });
            celula?.classList.toggle('condica-prezenta-celula-pending', esteInCurs);
        }

        restaureazaMarcareVizuala(element, codAnterior) {
            const grup = element.closest('.condica-prezenta-actiuni');

            if (!grup) {
                return;
            }

            grup.querySelectorAll('[data-cheie-actiune]').forEach(buton => {
                const actiune = this.actiuniPrezenta[buton.dataset.cheieActiune];
                const esteActiv = actiune?.cod === codAnterior;

                buton.classList.toggle('btn-primary', esteActiv);
                buton.classList.toggle('btn-default', !esteActiv);
            });
        }

        aplicaMarcareSalvata(celula, idAngajat, cod, element) {
            const eraLipsa = celula.esteLipsa === true;

            celula.codPrezenta = cod;
            celula.stare = cod;
            celula.esteLipsa = false;
            celula.optiuni.forEach(optiune => {
                optiune.activ = optiune.cod === cod;
            });

            const elementCelula = element.closest('.condica-prezenta-celula');
            const elementStare = elementCelula?.querySelector('.condica-prezenta-stare strong');

            if (elementStare) {
                elementStare.textContent = cod;
            }
            elementCelula?.classList.remove('danger');
            elementCelula?.classList.remove(
                'este-lipsa',
                'este-prezent',
                'este-delegatie',
                'este-medical',
                'este-concediu',
                'este-cic',
                'este-inactiva'
            );
            elementCelula?.classList.add(this.obtineClasaVizualaCelula(celula));

            if (eraLipsa) {
                this.actualizeazaStatisticiDupaCompletare(idAngajat);
            }

            this.actualizeazaRezumatVizual();
        }

        actualizeazaStatisticiDupaCompletare(idAngajat) {
            const angajat = this.angajati.find(element => element.idAngajat === idAngajat);

            if (angajat) {
                angajat.zileCompletate = Number(angajat.zileCompletate || 0) + 1;
                angajat.zileLipsa = Math.max(0, Number(angajat.zileLipsa || 0) - 1);
                angajat.rezumat = `${angajat.zileCompletate}/${angajat.zileScadente}`;
            }

            this.statistici.zileLipsa = Math.max(
                0,
                Number(this.statistici.zileLipsa || 0) - 1
            );

            const zileScadente = this.angajati.reduce(
                (total, element) => total + Number(element.zileScadente || 0),
                0
            );
            const zileCompletate = this.angajati.reduce(
                (total, element) => total + Number(element.zileCompletate || 0),
                0
            );

            this.statistici.procentCompletare = zileScadente > 0 ?
                Math.round(zileCompletate / zileScadente * 100) : 0;

            if (this.statistici.zileLipsa === 0) {
                this.statistici.motiveBlocareInchidere = (
                    this.statistici.motiveBlocareInchidere || []
                ).filter(motiv => motiv !== 'prezenteLipsa');
            }

            this.statistici.poateFiInchisa =
                this.statistici.zileLipsa === 0 &&
                (this.statistici.motiveBlocareInchidere || []).length === 0;
        }

        actualizeazaRezumatVizual() {
            const procent = this.element.querySelector('[data-role="procent-completare"]');
            const lipsa = this.element.querySelector('[data-role="prezente-lipsa"]');
            const stare = this.element.querySelector('[data-role="stare-completitudine"]');
            const motive = this.element.querySelector('[data-role="motive-blocare"]');
            const butonInchidere = this.element.querySelector('[data-action="inchideLuna"]');

            if (procent) {
                procent.textContent = String(this.statistici.procentCompletare || 0);
            }
            if (lipsa) {
                lipsa.textContent = String(this.statistici.zileLipsa || 0);
            }

            const angajatiAfectati = this.angajati.filter(
                angajat => Number(angajat.zileLipsa || 0) > 0
            ).length;
            const zileScadente = this.angajati.reduce(
                (total, angajat) => total + Number(angajat.zileScadente || 0),
                0
            );
            const zileCompletate = this.angajati.reduce(
                (total, angajat) => total + Number(angajat.zileCompletate || 0),
                0
            );
            const descriereCompletare = this.element.querySelector(
                '[data-role="descriere-completare"]'
            );
            const descriereLipsa = this.element.querySelector('[data-role="descriere-lipsa"]');
            const contorLipsa = this.element.querySelector('[data-role="contor-detalii-lipsa"]');

            if (descriereCompletare) {
                descriereCompletare.textContent = this.formateazaMesajTradus(
                    'descriereCompletare',
                    {completate: zileCompletate, scadente: zileScadente}
                );
            }
            if (descriereLipsa) {
                descriereLipsa.textContent = this.formateazaMesajTradus(
                    'descriereLipsa',
                    {
                        lipsa: Number(this.statistici.zileLipsa || 0),
                        angajati: angajatiAfectati,
                    }
                );
            }
            if (contorLipsa) {
                contorLipsa.textContent = String(angajatiAfectati);
            }
            if (stare) {
                stare.textContent = this.obtineStareCompletitudine();
            }
            if (motive) {
                const lista = this.obtineMotiveBlocare();

                motive.textContent = lista.length > 0 ? `— ${lista.join('; ')}` : '';
            }
            butonInchidere?.classList.toggle(
                'hidden',
                this.statistici.poateFiInchisa !== true
            );

            this.angajati.forEach((angajat, indice) => {
                const antet = this.element.querySelectorAll(
                    '.condica-prezenta-angajat .text-muted'
                )[indice];

                if (antet) {
                    antet.textContent = angajat.rezumat;
                }

                const valoareCompletare = this.element.querySelector(
                    `[data-detaliu-completare="${angajat.idAngajat}"] strong`
                );
                const randLipsa = this.element.querySelector(
                    `[data-detaliu-lipsa="${angajat.idAngajat}"]`
                );

                if (valoareCompletare) {
                    valoareCompletare.textContent =
                        `${Number(angajat.zileCompletate || 0)}/${Number(angajat.zileScadente || 0)}`;
                }
                if (randLipsa) {
                    const numarLipsa = Number(angajat.zileLipsa || 0);

                    randLipsa.classList.toggle('hidden', numarLipsa === 0);
                    randLipsa.querySelector('strong').textContent = String(numarLipsa);
                }
            });
        }

        evidentiazaMarcareSelectata(element) {
            const grup = element.closest('.condica-prezenta-actiuni');

            if (!grup) {
                return;
            }

            grup.querySelectorAll('.btn').forEach(buton => {
                buton.classList.remove('btn-primary');
                buton.classList.add('btn-default');
            });
            element.classList.remove('btn-default');
            element.classList.add('btn-primary');
        }


        async inchideLuna() {
            await this.confirm({
                message: this.translate('confirmareInchidereLuna', 'messages', 'CondicaPrezenta'),
                confirmText: this.translate('inchideLuna', 'labels', 'CondicaPrezenta'),
            });
            Espo.Ui.notifyWait();

            try {
                await Espo.Ajax.postRequest('CondicaPrezenta/inchide-luna', {
                    luna: this.lunaSelectata,
                });
                await this.incarcaLuna();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
            }

            Espo.Ui.success(this.translate('lunaInchisaCuSucces', 'messages', 'CondicaPrezenta'));
        }

        async trimiteRemindere() {
            await this.confirm({
                message: this.translate(
                    'confirmareTrimitereRemindere',
                    'messages',
                    'CondicaPrezenta'
                ),
                confirmText: this.translate(
                    'trimiteRemindere',
                    'labels',
                    'CondicaPrezenta'
                ),
            });
            Espo.Ui.notifyWait();
            let raspuns;

            try {
                raspuns = await Espo.Ajax.postRequest(
                    'CondicaPrezenta/trimite-remindere',
                    {luna: this.lunaSelectata}
                );
            } finally {
                Espo.Ui.notify(false);
            }

            const numar = raspuns.notificariCreate + raspuns.notificariActualizate;
            const eticheta = numar > 0 ? 'remindereTrimise' : 'niciunReminderTrimis';
            const mesaj = this.translate(eticheta, 'messages', 'CondicaPrezenta')
                .replace('{numar}', String(numar));

            Espo.Ui.success(mesaj);
        }

        async corecteazaPrezenta(idAngajat, data, cod) {
            const motiv = await this.solicitaMotivCorectie();

            if (!motiv) {
                return;
            }

            const pozitieDerulare = this.obtinePozitieDerulareTabel();

            Espo.Ui.notifyWait();

            try {
                await Espo.Ajax.postRequest('CondicaPrezenta/corecteaza-luna', {
                    luna: this.lunaSelectata,
                    idAngajat: idAngajat,
                    data: data,
                    cod: cod,
                    motiv: motiv,
                });
                await this.incarcaLuna();
                await this.reRender();
                this.restaureazaPozitieDerulareTabel(pozitieDerulare);
            } finally {
                Espo.Ui.notify(false);
            }

            Espo.Ui.success(this.translate('corectieSalvata', 'messages', 'CondicaPrezenta'));
        }

        obtinePozitieDerulareTabel() {
            const container = this.element.querySelector('.condica-prezenta-container');

            if (!container) {
                return null;
            }

            return {
                stanga: container.scrollLeft,
                sus: container.scrollTop,
            };
        }

        restaureazaPozitieDerulareTabel(pozitie) {
            if (!pozitie) {
                return;
            }

            requestAnimationFrame(() => {
                const container = this.element.querySelector('.condica-prezenta-container');

                if (!container) {
                    return;
                }

                container.scrollLeft = pozitie.stanga;
                container.scrollTop = pozitie.sus;
            });
        }

        solicitaMotivCorectie() {
            return new Promise(async rezolva => {
                const dialog = await this.createView('dialogCorectieCondica', 'views/modal', {
                    templateContent:
                        '<div class="form-group">' +
                        '<label>{{viewObject.options.eticheta}}</label>' +
                        '<textarea class="form-control" data-name="motiv" rows="4"></textarea>' +
                        '</div>',
                    headerText: this.translate('motivCorectie', 'fields', 'Prezenta'),
                    eticheta: this.translate('motivCorectie', 'fields', 'Prezenta'),
                    buttonList: [
                        {
                            name: 'salveaza',
                            text: this.translate('Save'),
                            style: 'primary',
                            onClick: () => {
                                const motiv = dialog.$el.find('[data-name="motiv"]').val().trim();

                                if (!motiv) {
                                    Espo.Ui.warning(this.translate(
                                        'motivCorectieObligatoriu',
                                        'messages',
                                        'CondicaPrezenta'
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
