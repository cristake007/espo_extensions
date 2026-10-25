define('modules/management-angajati/views/gestionare-angajati/profiluri-angajati', ['view'], (Vizualizare) => {
    return class VizualizareProfiluriAngajati extends Vizualizare {

        template = 'management-angajati:gestionare-angajati/profiluri-angajati'

        setup() {
            this.dateProfiluri = {angajati: [], calendareLucru: []};
            this.cheiVizualizari = [];
            this.vizualizari = {};

            this.addActionHandler('salveaza', () => this.salveaza());
            this.wait(this.incarca());
        }

        data() {
            const numarAngajati = this.dateProfiluri.angajati.length;

            return {
                titlu: this.translate('profiluriAngajati', 'labels', 'GestionareAngajati'),
                configurareProfiluri: this.translate('configurareProfiluriAngajati', 'labels', 'GestionareAngajati'),
                explicatie: this.translate(
                    'explicatieProfiluri',
                    'messages',
                    'GestionareAngajati'
                ),
                inapoiAdministrare: this.translate('inapoiAdministrare', 'labels', 'GestionareAngajati'),
                cumFunctioneaza: this.translate('cumFunctioneazaProfilurile', 'labels', 'GestionareAngajati'),
                explicatieConfigurare: this.translate('explicatieConfigurareProfiluri', 'messages', 'GestionareAngajati'),
                dataAngajare: this.translate(
                    'dataAngajare',
                    'fields',
                    'GestionareAngajati'
                ),
                calendarLucru: this.translate(
                    'calendarLucru',
                    'fields',
                    'GestionareAngajati'
                ),
                explicatieDataAngajare: this.translate('explicatieDataAngajareProfil', 'messages', 'GestionareAngajati'),
                explicatieCalendarLucru: this.translate('explicatieCalendarLucruProfil', 'messages', 'GestionareAngajati'),
                angajatiEticheta: this.translate('angajatiActiviProfiluri', 'labels', 'GestionareAngajati'),
                explicatieLista: this.translate('explicatieListaProfiluri', 'messages', 'GestionareAngajati'),
                numarAngajati: this.translate('numarAngajatiProfiluri', 'messages', 'GestionareAngajati')
                    .replace('{numar}', numarAngajati),
                explicatieSalvare: this.translate('explicatieSalvareProfiluri', 'messages', 'GestionareAngajati'),
                salvare: this.translate('Save'),
            };
        }

        async incarca() {
            this.dateProfiluri = await Espo.Ajax.getRequest(
                'ManagementAngajati/profiluri-angajati'
            );
        }

        afterRender() {
            this.creeazaRandurile();
        }

        creeazaRandurile() {
            this.curataRandurile();

            const container = this.$el.find('[data-role="randuri-angajati"]').empty();

            this.dateProfiluri.angajati.forEach((profilAngajat, index) => {
                const cheie = `profil-angajat-${index}`;

                this.cheiVizualizari.push(cheie);
                container.append($('<div>').attr('data-view-key', cheie));

                this.createView(
                    cheie,
                    'management-angajati:views/gestionare-angajati/rand-profil-angajat',
                    {
                        selector: `[data-view-key="${cheie}"]`,
                        profilAngajat: profilAngajat,
                        calendareLucru: this.dateProfiluri.calendareLucru,
                    }
                ).then(vizualizare => {
                    this.vizualizari[cheie] = vizualizare;

                    if (this.isRendered()) {
                        vizualizare.render();
                    }

                    this.actualizeazaDisponibilitateSalvare();
                });
            });

            this.actualizeazaDisponibilitateSalvare();
        }

        curataRandurile() {
            this.cheiVizualizari.forEach(cheie => this.clearView(cheie));
            this.cheiVizualizari = [];
            this.vizualizari = {};
        }

        actualizeazaDisponibilitateSalvare() {
            const toateRandurileSuntPregatite =
                Object.keys(this.vizualizari).length === this.dateProfiluri.angajati.length;

            this.$el
                .find('[data-action="salveaza"]')
                .prop('disabled', !toateRandurileSuntPregatite);
        }

        async salveaza() {
            const angajati = this.cheiVizualizari.map(
                cheie => this.vizualizari[cheie]?.obtineProfil()
            );

            if (angajati.some(profil => !profil)) {
                return;
            }

            Espo.Ui.notifyWait();

            try {
                this.dateProfiluri = await Espo.Ajax.putRequest(
                    'ManagementAngajati/profiluri-angajati',
                    {angajati: angajati}
                );
                this.curataRandurile();
                await this.reRender();
            } finally {
                Espo.Ui.notify(false);
            }

            Espo.Ui.success(this.translate(
                'profiluriSalvate',
                'messages',
                'GestionareAngajati'
            ));
        }
    };
});
