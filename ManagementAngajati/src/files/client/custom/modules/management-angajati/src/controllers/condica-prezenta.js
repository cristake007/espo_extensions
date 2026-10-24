define(['controllers/record'], (ControlorInregistrare) => {
    return class ControlorCondicaPrezenta extends ControlorInregistrare {

        entityType = 'Prezenta'

        checkAccessGlobal() {
            return this.getUser().isAdmin() ||
                this.getAcl().checkScope('CondicaPrezenta');
        }

        checkAccess(actiune) {
            if (!this.checkAccessGlobal()) {
                return false;
            }

            return this.getAcl().check(this.entityType, actiune);
        }
    };
});
