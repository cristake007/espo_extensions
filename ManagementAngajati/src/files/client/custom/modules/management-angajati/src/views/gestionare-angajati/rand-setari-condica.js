define(['view'], (Vizualizare) => {
    return class VizualizareRandSetariCondica extends Vizualizare {

        template = 'management-angajati:gestionare-angajati/rand-setari-condica'

        setup() {
            this.configuratieAngajat = this.options.configuratieAngajat;
            this.modAplicareRemote = this.options.modAplicareRemote;
            this.tipuriCampuri = {
                remoteActiv: 'bool',
                dataInceputRemote: 'date',
                dataSfarsitRemote: 'date',
                cicActiv: 'bool',
                dataInceputCic: 'date',
                dataSfarsitCic: 'date',
            };
            this.numeCampuriData = Object.keys(this.tipuriCampuri)
                .filter(nume => this.tipuriCampuri[nume] === 'date');

            this.wait(this.pregatesteCampurileData());
        }

        async pregatesteCampurileData() {
            this.modelConfiguratie = await this.getModelFactory().create(
                'ConfiguratieCondicaAngajat'
            );

            this.modelConfiguratie.set(
                Object.fromEntries(
                    Object.keys(this.tipuriCampuri).map(nume => [
                        nume,
                        this.configuratieAngajat[nume] ?? null,
                    ])
                )
            );

            await Promise.all(
                Object.entries(this.tipuriCampuri).map(([nume, tip]) => this.createView(
                    nume,
                    `views/fields/${tip}`,
                    {
                        model: this.modelConfiguratie,
                        mode: 'edit',
                        selector: `.field[data-name="${nume}"]`,
                        defs: {
                            name: nume,
                        },
                    }
                ))
            );
        }

        data() {
            const dataAngajare = this.configuratieAngajat.dataAngajare;

            return {
                ...this.configuratieAngajat,
                dataAngajareEticheta: this.translate(
                    'dataAngajare',
                    'fields',
                    'GestionareAngajati'
                ),
                dataAngajareAfisata: dataAngajare ?
                    this.getDateTime().toDisplayDate(dataAngajare) :
                    this.translate('dataAngajareLipsa', 'labels', 'Prezenta'),
                dataAngajareLipsa: !dataAngajare,
                remote: this.translate('remoteActiv', 'fields', 'ConfiguratieCondicaAngajat'),
                cic: this.translate('cicActiv', 'fields', 'ConfiguratieCondicaAngajat'),
                dataInceput: this.translate('dataInceput', 'fields', 'GestionareAngajati'),
                dataSfarsit: this.translate('dataSfarsit', 'fields', 'GestionareAngajati'),
                faraCalendar: this.translate(
                    'faraCalendarLucru',
                    'messages',
                    'GestionareAngajati'
                ),
                modRemoteInterval: this.modAplicareRemote === 'interval',
            };
        }

        obtineConfiguratie(modAplicareRemote) {
            Object.keys(this.tipuriCampuri).forEach(
                nume => this.getView(nume).fetchToModel()
            );
            const remoteActiv = this.modelConfiguratie.get('remoteActiv') === true;
            const cicActiv = this.modelConfiguratie.get('cicActiv') === true;

            const campuriDeValidat = [
                ...(remoteActiv && modAplicareRemote === 'interval' ?
                    ['dataInceputRemote', 'dataSfarsitRemote'] : []),
                ...(cicActiv ? ['dataInceputCic', 'dataSfarsitCic'] : []),
            ];

            if (campuriDeValidat.some(nume => this.getView(nume).validate())) {
                return null;
            }

            const citesteData = nume => this.modelConfiguratie.get(nume) || null;

            return {
                angajatId: this.configuratieAngajat.idAngajat,
                remoteActiv: remoteActiv,
                dataInceputRemote: modAplicareRemote === 'interval' ?
                    citesteData('dataInceputRemote') : null,
                dataSfarsitRemote: modAplicareRemote === 'interval' ?
                    citesteData('dataSfarsitRemote') : null,
                cicActiv: cicActiv,
                dataInceputCic: citesteData('dataInceputCic'),
                dataSfarsitCic: citesteData('dataSfarsitCic'),
            };
        }
    };
});
