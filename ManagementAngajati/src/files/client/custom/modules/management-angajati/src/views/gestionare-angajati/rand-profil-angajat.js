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
            return this.profilAngajat;
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
