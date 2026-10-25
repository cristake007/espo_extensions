define('modules/management-angajati/controllers/administrare-concedii', ['controller'], (Controlor) => {
    return class ControlorAdministrareConcedii extends Controlor {
        defaultAction = 'index'
        checkAccessGlobal() {
            return this.getUser().isAdmin() ||
                this.getAcl().checkScope('AdministrareConcedii');
        }
        actionIndex() {
            this.afiseazaPagina();
        }
        actionView(optiuni) {
            this.afiseazaPagina(optiuni.id);
        }
        afiseazaPagina(idCerere = null) {
            this.main(
                'management-angajati:views/administrare-concedii/index',
                {params: {idCerere}},
                vizualizare => vizualizare.render()
            );
        }
    };
});
