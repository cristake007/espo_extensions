define('modules/management-angajati/controllers/concedii', ['controllers/record'], (ControlorInregistrare) => {
    return class ControlorConcedii extends ControlorInregistrare {
        entityType = 'CerereConcediu'
        checkAccessGlobal() {
            return this.getUser().isAdmin() || this.getAcl().checkScope('CerereConcediu');
        }
    };
});
