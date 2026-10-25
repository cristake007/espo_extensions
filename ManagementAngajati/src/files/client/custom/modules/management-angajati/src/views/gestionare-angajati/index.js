define('modules/management-angajati/views/gestionare-angajati/index', ['view'], (Vizualizare) => {
    return class VizualizareGestionareAngajati extends Vizualizare {

        template = 'management-angajati:gestionare-angajati/index'

        data() {
            return {
                spratitlu: this.translate('managementAngajati', 'labels', 'GestionareAngajati'),
                titlu: this.translate('administrareExtensie', 'labels', 'GestionareAngajati'),
                explicatie: this.translate(
                    'explicatieAdministrareExtensie',
                    'messages',
                    'GestionareAngajati'
                ),
                zoneAdministrare: this.translate(
                    'zoneAdministrare',
                    'labels',
                    'GestionareAngajati'
                ),
                explicatieZoneAdministrare: this.translate(
                    'explicatieZoneAdministrare',
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
