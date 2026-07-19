define([], () => {
    const IDENTIFIER = /^[A-Za-z][A-Za-z0-9]{0,99}$/;

    return class EntityCatalogueApi {
        constructor(ajax = Espo.Ajax) {
            this.ajax = ajax;
        }

        async get() {
            const response = await this.ajax.getRequest('DocumentBuilder/entity-catalogue');

            if (!response || !Array.isArray(response.list)) {
                throw new TypeError('The entity catalogue response is invalid.');
            }

            const identifiers = new Set();
            const list = response.list.map(item => {
                if (
                    !item ||
                    !IDENTIFIER.test(item.entityType || '') ||
                    typeof item.label !== 'string' ||
                    !item.label.trim() ||
                    item.label.length > 200 ||
                    typeof item.custom !== 'boolean' ||
                    identifiers.has(item.entityType)
                ) {
                    throw new TypeError('The entity catalogue contains an invalid item.');
                }

                identifiers.add(item.entityType);

                return Object.freeze({
                    entityType: item.entityType,
                    label: item.label,
                    custom: item.custom,
                });
            });

            return Object.freeze(list);
        }
    };
});
