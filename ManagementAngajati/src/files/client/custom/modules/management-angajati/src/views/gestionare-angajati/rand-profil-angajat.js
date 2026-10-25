define(['view'], (Vizualizare) => {
    return class VizualizareRandProfilAngajat extends Vizualizare {

        template = 'management-angajati:gestionare-angajati/rand-profil-angajat'

        setup() {
            this.profilAngajat = this.options.profilAngajat;
            this.calendareLucru = this.options.calendareLucru;
            this.wait(this.pregatesteCampurile());
        }

        async pregatesteCampurile() {
            this.modelAngajat = await this.getModelFactory().create('User');
            this.modelAngajat.id = this.profilAngajat.idAngajat;
            this.modelAngajat.set({
                dataAngajare: this.profilAngajat.dataAngajare,
                calendarLucruId: this.profilAngajat.calendarLucruId,
            });

            const optiuniCalendare = [
                '',
                ...this.calendareLucru.map(calendar => calendar.id),
            ];
            const denumiriCalendare = {
                '': '',
                ...Object.fromEntries(
                    this.calendareLucru.map(calendar => [calendar.id, calendar.nume])
                ),
            };

            await Promise.all([
                this.createView('dataAngajare', 'views/fields/date', {
                    model: this.modelAngajat,
                    mode: 'edit',
                    selector: '.field[data-name="dataAngajare"]',
                    defs: {name: 'dataAngajare'},
                    params: {required: true},
                }),
                this.createView('calendarLucruId', 'views/fields/enum', {
                    model: this.modelAngajat,
                    mode: 'edit',
                    selector: '.field[data-name="calendarLucruId"]',
                    defs: {name: 'calendarLucruId'},
                    params: {required: true},
                    customOptionList: optiuniCalendare,
                    translatedOptions: denumiriCalendare,
                }),
            ]);
        }

        data() {
            const configurat = Boolean(
                this.profilAngajat.dataAngajare &&
                this.profilAngajat.calendarLucruId
            );

            return {
                ...this.profilAngajat,
                descriereAngajat: this.translate('profilAngajatActiv', 'messages', 'GestionareAngajati'),
                dataAngajareEticheta: this.translate('dataAngajare', 'fields', 'GestionareAngajati'),
                calendarLucruEticheta: this.translate('calendarLucru', 'fields', 'GestionareAngajati'),
                dataAngajareAjutor: this.translate('ajutorDataAngajareProfil', 'messages', 'GestionareAngajati'),
                calendarLucruAjutor: this.translate('ajutorCalendarLucruProfil', 'messages', 'GestionareAngajati'),
                stare: this.translate(
                    configurat ? 'profilConfigurat' : 'profilIncomplet',
                    'labels',
                    'GestionareAngajati'
                ),
                clasaStare: configurat ? 'este-configurat' : 'este-incomplet',
                iconStare: configurat ? 'fa-check' : 'fa-exclamation',
            };
        }

        obtineProfil() {
            const campuri = ['dataAngajare', 'calendarLucruId'];

            campuri.forEach(nume => this.getView(nume).fetchToModel());

            if (campuri.some(nume => this.getView(nume).validate())) {
                return null;
            }

            return {
                angajatId: this.profilAngajat.idAngajat,
                dataAngajare: this.modelAngajat.get('dataAngajare') || null,
                calendarLucruId: this.modelAngajat.get('calendarLucruId') || null,
            };
        }
    };
});
