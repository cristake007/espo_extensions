define(['controller'], (Controlor) => {
    return class ControlorGestionareAngajati extends Controlor {

        defaultAction = 'index'

        checkAccessGlobal() {
            return this.getUser().isAdmin() ||
                this.getAcl().checkScope('GestionareAngajati');
        }

        actionIndex() {
            this.main(
                'management-angajati:views/gestionare-angajati/index',
                {},
                vizualizare => vizualizare.render()
            );
        }

        actionSetariCondica() {
            this.main(
                'management-angajati:views/gestionare-angajati/setari-condica',
                {},
                vizualizare => vizualizare.render()
            );
        }

        actionProfiluriAngajati() {
            this.main(
                'management-angajati:views/gestionare-angajati/profiluri-angajati',
                {},
                vizualizare => vizualizare.render()
            );
        }

        actionConcedii() {
            this.main(
                'management-angajati:views/gestionare-angajati/concedii',
                {},
                vizualizare => vizualizare.render()
            );
        }
    };
});
