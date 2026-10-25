define('modules/management-angajati/views/prezenta/prezenta-indisponibila', ['view'], (Vizualizare) => {
    return class VizualizarePrezentaIndisponibil extends Vizualizare {

        template = 'management-angajati:prezenta/prezenta-indisponibila'

        data() {
            return {
                titlu: this.translate(
                    'prezentaIndisponibilaAdministrator',
                    'labels',
                    'Prezenta'
                ),
                explicatie: this.translate(
                    'administratorFaraPrezentaPersonala',
                    'messages',
                    'Prezenta'
                ),
                condicaPrezenta: this.translate(
                    'condicaPrezenta',
                    'labels',
                    'Prezenta'
                ),
            };
        }
    };
});
