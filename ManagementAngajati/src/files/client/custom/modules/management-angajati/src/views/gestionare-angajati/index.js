define(['view'], (Vizualizare) => {
    return class VizualizareGestionareAngajati extends Vizualizare {

        template = 'management-angajati:gestionare-angajati/index'

        data() {
            return {
                titlu: this.translate('managementAngajati', 'labels', 'GestionareAngajati'),
                explicatie: this.translate(
                    'explicatieManagement',
                    'messages',
                    'GestionareAngajati'
                ),
                setariCondica: this.translate('setariCondica', 'labels', 'GestionareAngajati'),
                explicatieSetari: this.translate(
                    'explicatieSetari',
                    'messages',
                    'GestionareAngajati'
                ),
                profiluriAngajati: this.translate(
                    'profiluriAngajati',
                    'labels',
                    'GestionareAngajati'
                ),
                explicatieProfiluri: this.translate(
                    'explicatieProfiluri',
                    'messages',
                    'GestionareAngajati'
                ),
                setariConcedii: this.translate('setariConcedii', 'labels', 'GestionareAngajati'),
                explicatieConcedii: this.translate('explicatieConcedii', 'messages', 'GestionareAngajati'),
            };
        }
    };
});
