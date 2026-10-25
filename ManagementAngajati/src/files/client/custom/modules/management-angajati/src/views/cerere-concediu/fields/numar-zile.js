define('modules/management-angajati/views/cerere-concediu/fields/numar-zile', ['views/fields/int'], (CampIntreg) => {
    return class CampNumarZileConcediu extends CampIntreg {
        setup() {
            super.setup();

            this.ultimaPerioadaCalculata = null;
            this.numarCerereCalcul = 0;
            this.listenTo(
                this.model,
                'change:dateStartDate change:dateEndDate',
                () => this.programeazaCalculul()
            );
            this.once('remove', () => clearTimeout(this.temporizatorCalcul));
        }

        afterRender() {
            super.afterRender();
            this.programeazaCalculul();
        }

        programeazaCalculul() {
            if (!this.model.isNew()) return;

            clearTimeout(this.temporizatorCalcul);
            this.temporizatorCalcul = setTimeout(() => this.calculeaza(), 120);
        }

        async calculeaza() {
            const dataInceput = this.model.get('dateStartDate');
            const dataSfarsit = this.model.get('dateEndDate');
            const perioada = `${dataInceput || ''}|${dataSfarsit || ''}`;

            if (!dataInceput || !dataSfarsit) {
                this.ultimaPerioadaCalculata = null;
                this.model.set('numarZile', 0);
                return;
            }

            if (perioada === this.ultimaPerioadaCalculata) return;

            this.ultimaPerioadaCalculata = perioada;
            const numarCerere = ++this.numarCerereCalcul;

            try {
                const rezultat = await Espo.Ajax.postRequest(
                    'Concedii/previzualizare-perioada',
                    {dataInceput, dataSfarsit}
                );

                if (numarCerere !== this.numarCerereCalcul) return;

                this.model.set('numarZile', Number(rezultat.numarZile || 0));
            } catch (eroare) {
                if (numarCerere !== this.numarCerereCalcul) return;

                eroare.errorIsHandled = true;
                this.model.set('numarZile', 0);
            }
        }
    };
});
