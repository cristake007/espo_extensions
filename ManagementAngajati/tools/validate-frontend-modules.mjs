import fs from 'node:fs';
import path from 'node:path';

const sourceDir = path.resolve(
    import.meta.dirname,
    '../src/files/client/custom/modules/management-angajati/src'
);
const modulePrefix = 'modules/management-angajati/';
const visited = new Set();
let count = 0;

function inspect(directory, relativeDir = '') {
    for (const entry of fs.readdirSync(directory, {withFileTypes: true})) {
        const relative = path.posix.join(relativeDir, entry.name);
        const filePath = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            inspect(filePath, relative);
            continue;
        }

        if (!entry.isFile() || !/\.(js|ts)$/.test(entry.name) || entry.name.endsWith('.d.ts')) {
            continue;
        }

        const expected = modulePrefix + relative.replace(/\.(js|ts)$/, '');
        const source = fs.readFileSync(filePath, 'utf8').trimStart();
        const amd = source.match(/^define\s*\(\s*(['"])([^'"]+)\1\s*,/);

        if (source.startsWith('define') && (!amd || amd[2] !== expected)) {
            throw new Error(
                'Invalid bundled AMD ID in ' + relative + '. Expected define("' + expected + '", ...).'
            );
        }

        if (!amd && !/\bexport\s/.test(source)) {
            throw new Error(
                'Unrecognized frontend module format in ' + relative + '. ' +
                'Use a named AMD define or an ES module export.'
            );
        }

        if (visited.has(expected)) {
            throw new Error('Duplicate bundled module: ' + expected);
        }

        visited.add(expected);
        count += 1;
    }
}

inspect(sourceDir);

if (count === 0) {
    throw new Error('No frontend modules found.');
}

process.stdout.write('Validated ' + count + ' frontend module IDs.\n');
