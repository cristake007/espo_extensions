define(['document-builder:editor/state/node-tree'], (NodeTree) => {
    const defaultTokenGenerator = () => {
        if (!globalThis.crypto || typeof globalThis.crypto.getRandomValues !== 'function') {
            throw new Error('Secure random values are required to create layout IDs.');
        }

        const bytes = new Uint8Array(10);

        globalThis.crypto.getRandomValues(bytes);

        return Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
    };

    return class StableIdFactory {
        constructor(tokenGenerator = defaultTokenGenerator) {
            this.tokenGenerator = tokenGenerator;
            this.allocated = new Set();
        }

        synchronize(layout) {
            NodeTree.index(layout).forEach((location, id) => this.allocated.add(id));
        }

        reserve(id) {
            if (typeof id !== 'string' || !NodeTree.STABLE_ID_PATTERN.test(id)) {
                throw new TypeError('A reserved layout ID must use the canonical safe-ID format.');
            }

            if (this.allocated.has(id)) {
                throw new TypeError(`Layout ID ${id} is already allocated.`);
            }

            this.allocated.add(id);

            return id;
        }

        create(type = 'node') {
            const prefix = this.normalizePrefix(type);

            for (let attempt = 0; attempt < 100; attempt++) {
                const token = String(this.tokenGenerator());

                if (!/^[A-Za-z0-9_-]+$/.test(token)) {
                    throw new TypeError('A stable-ID token contains unsafe characters.');
                }

                const id = `${prefix}_${token}`.slice(0, 64);

                if (!this.allocated.has(id)) {
                    this.allocated.add(id);

                    return id;
                }
            }

            throw new Error('Could not allocate a unique layout ID.');
        }

        normalizePrefix(type) {
            let prefix = String(type || 'node')
                .replace(/[^A-Za-z0-9_-]/g, '-')
                .replace(/^[^A-Za-z]+/, '');

            if (!prefix) {
                prefix = 'node';
            }

            return prefix.slice(0, 42);
        }
    };
});
