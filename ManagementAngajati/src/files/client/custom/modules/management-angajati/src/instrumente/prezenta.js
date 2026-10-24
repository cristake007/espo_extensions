define([], () => ({
    obtineCoduriPrezenta(metadate) {
        const optiuni = metadate.get([
            'entityDefs',
            'Prezenta',
            'fields',
            'codPrezenta',
            'options',
        ]);

        if (!Array.isArray(optiuni)) {
            throw new Error('metadataPrezentaInvalida');
        }

        return optiuni;
    },

    obtineLunaCurenta() {
        return this.formateazaLuna(new Date());
    },

    deplaseazaLuna(lunaSelectata, deplasare) {
        const [an, luna] = lunaSelectata.split('-').map(Number);

        return this.formateazaLuna(new Date(an, luna - 1 + deplasare, 1));
    },

    formateazaLuna(data) {
        return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}`;
    },
}));
