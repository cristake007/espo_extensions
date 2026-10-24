define(['view'], (Vizualizare) => {
    return class VizualizareSetariCondica extends Vizualizare {

        template = 'management-angajati:gestionare-angajati/setari-condica'

        setup() {
            this.setari = {
                modAplicareRemote: 'curent',
                numarLuniAnterioareEditabile: 1,
                angajati: [],
            };
            this.cheiVizualizariAngajati = [];
            this.vizualizariAngajati = {};
            this.previzualizareReset = null;
            this.resetareInCurs = false;

            this.addActionHandler('salveaza', () => this.salveaza());
            this.addActionHandler('reseteazaDateTest', () => this.reseteazaDateTest());
            this.addHandler(
                'input',
                '[data-camp="confirmareReset"]',
                () => this.actualizeazaDisponibilitateResetare()
            );
            this.addHandler(
                'change',
                '[data-camp="modAplicareRemote"]',
                (eveniment, element) => this.actualizeazaCampuriRemote(element.value)
            );

            this.wait(this.incarca());
        }

        data() {
            return {
                titlu: this.translate('setariCondica', 'labels', 'GestionareAngajati'),
                explicatie: this.translate('explicatieSetari', 'messages', 'GestionareAngajati'),
                modRemote: this.translate('modAplicareRemote', 'fields', 'GestionareAngajati'),
                modCurent: this.translate('curent', 'options', 'GestionareAngajati'),
                modInterval: this.translate('interval', 'options', 'GestionareAngajati'),
                salvare: this.translate('Save'),
                angajat: this.translate('angajat', 'fields', 'GestionareAngajati'),
                remote: this.translate('remoteActiv', 'fields', 'ConfiguratieCondicaAngajat'),
                cic: this.translate('cicActiv', 'fields', 'ConfiguratieCondicaAngajat'),
                luniEditabile: this.translate(
                    'numarLuniAnterioareEditabile',
                    'fields',
                    'GestionareAngajati'
                ),
                explicatieLuniEditabile: this.translate(
                    'explicatieLuniEditabile',
                    'messages',
                    'GestionareAngajati'
                ),
                modAplicareRemote: this.setari.modAplicareRemote,
                numarLuniAnterioareEditabile:
                    this.setari.numarLuniAnterioareEditabile,
                resetareTitlu: this.translate(
                    'resetareDateTestCondica',
                    'labels',
                    'GestionareAngajati'
                ),
                resetareAvertisment: this.translate(
                    'avertismentResetareDateTestCondica',
                    'messages',
                    'GestionareAngajati'
                ),
                resetareExplicatie: this.translate(
                    'explicatieResetareDateTestCondica',
                    'messages',
                    'GestionareAngajati'
                ),
                confirmareReset: this.translate(
                    'confirmareResetCondica',
                    'labels',
                    'GestionareAngajati'
                ),
                expresieConfirmare: 'RESET DATE CONDICA',
                previzualizareReset: this.previzualizareReset,
                eticheteReset: {
                    prezente: this.translate('prezenteSterse', 'labels', 'GestionareAngajati'),
                    stariLuna: this.translate('stariLunaSterse', 'labels', 'GestionareAngajati'),
                    remindere: this.translate('remindereSterse', 'labels', 'GestionareAngajati'),
                    configuratii: this.translate(
                        'configuratiiCicRemoteSterse',
                        'labels',
                        'GestionareAngajati'
                    ),
                    dateAngajare: this.translate(
                        'dateAngajareGolire',
                        'labels',
                        'GestionareAngajati'
                    ),
                    calendare: this.translate(
                        'calendareLucruPastrate',
                        'labels',
                        'GestionareAngajati'
                    ),
                },
            };
        }

        async incarca() {
            [this.setari, this.previzualizareReset] = await Promise.all([
                Espo.Ajax.getRequest('ManagementAngajati/setari-condica'),
                Espo.Ajax.getRequest('ManagementAngajati/reset-date-condica'),
            ]);
        }

        afterRender() {
            this.creeazaVizualizarileAngajatilor();
        }

        creeazaVizualizarileAngajatilor() {
            this.curataVizualizarileAngajatilor();

            const container = this.$el.find('[data-role="randuri-angajati"]').empty();

            this.setari.angajati.forEach((configuratieAngajat, index) => {
                const cheie = `rand-angajat-${index}`;

                this.cheiVizualizariAngajati.push(cheie);
                container.append($('<tr>').attr('data-view-key', cheie));

                this.createView(
                    cheie,
                    'management-angajati:views/gestionare-angajati/rand-setari-condica',
                    {
                        selector: `[data-view-key="${cheie}"]`,
                        configuratieAngajat,
                        modAplicareRemote: this.setari.modAplicareRemote,
                    }
                ).then(vizualizare => {
                    this.vizualizariAngajati[cheie] = vizualizare;

                    if (this.isRendered()) {
                        vizualizare.render();
                    }

                    this.actualizeazaDisponibilitateSalvare();
                });
            });

            this.actualizeazaDisponibilitateSalvare();
            this.actualizeazaDisponibilitateResetare();
        }

        curataVizualizarileAngajatilor() {
            this.cheiVizualizariAngajati.forEach(cheie => this.clearView(cheie));
            this.cheiVizualizariAngajati = [];
            this.vizualizariAngajati = {};
        }

        actualizeazaCampuriRemote(mod) {
            this.$el
                .find('[data-role="campuri-remote-interval"]')
                .toggleClass('hidden', mod !== 'interval');
        }

        actualizeazaDisponibilitateSalvare() {
            const toateRandurileSuntPregatite =
                Object.keys(this.vizualizariAngajati).length === this.setari.angajati.length;

            this.$el
                .find('[data-action="salveaza"]')
                .prop('disabled', !toateRandurileSuntPregatite);
        }

        actualizeazaDisponibilitateResetare() {
            const confirmare = String(
                this.$el.find('[data-camp="confirmareReset"]').val() || ''
            );

            this.$el
                .find('[data-action="reseteazaDateTest"]')
                .prop(
                    'disabled',
                    this.resetareInCurs ||
                    !this.previzualizareReset ||
                    confirmare !== 'RESET DATE CONDICA'
                );
        }

        async salveaza() {
            const mod = this.$el.find('[data-camp="modAplicareRemote"]').val();
            const numarLuni = Number(
                this.$el.find('[data-camp="numarLuniAnterioareEditabile"]').val()
            );
            const angajati = this.cheiVizualizariAngajati.map(
                cheie => this.vizualizariAngajati[cheie]?.obtineConfiguratie(mod)
            );

            if (angajati.some(configuratie => !configuratie)) {
                return;
            }

            Espo.Ui.notifyWait();

            try {
                await Espo.Ajax.putRequest('ManagementAngajati/setari-condica', {
                    modAplicareRemote: mod,
                    numarLuniAnterioareEditabile: numarLuni,
                    angajati: angajati,
                });
                await this.incarca();
                this.curataVizualizarileAngajatilor();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
            }

            Espo.Ui.success(this.translate('setariSalvate', 'messages', 'GestionareAngajati'));
        }

        async reseteazaDateTest() {
            const confirmare = String(
                this.$el.find('[data-camp="confirmareReset"]').val() || ''
            );

            if (confirmare !== 'RESET DATE CONDICA') {
                return;
            }

            await this.confirm({
                message: this.translate(
                    'confirmareFinalaResetareDateTestCondica',
                    'messages',
                    'GestionareAngajati'
                ),
                confirmText: this.translate(
                    'resetareDateTestCondica',
                    'labels',
                    'GestionareAngajati'
                ),
                confirmStyle: 'danger',
                backdrop: 'static',
            });

            this.resetareInCurs = true;
            this.actualizeazaDisponibilitateResetare();
            Espo.Ui.notifyWait();

            try {
                await Espo.Ajax.postRequest('ManagementAngajati/reset-date-condica', {
                    confirmare: confirmare,
                });
                await this.incarca();
                this.curataVizualizarileAngajatilor();
                await this.reRender();
            } finally {
                this.resetareInCurs = false;
                Espo.Ui.notify(false);
            }

            Espo.Ui.success(this.translate(
                'resetareDateTestCondicaReusita',
                'messages',
                'GestionareAngajati'
            ));
        }

    };
});
