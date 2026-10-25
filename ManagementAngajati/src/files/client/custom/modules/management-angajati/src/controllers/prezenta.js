define('modules/management-angajati/controllers/prezenta', ['controllers/record'], (ControlorInregistrare) => {
    return class ControlorPrezenta extends ControlorInregistrare {

        getViewName(tip) {
            if (tip === 'list' && this.getUser().isAdmin()) {
                return 'management-angajati:views/prezenta/prezenta-indisponibila';
            }

            return super.getViewName(tip);
        }

    };
});
