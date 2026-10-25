import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import AdmZip from 'adm-zip';

const [sourceZip, outputZip, manifestPath, readmePath] = process.argv.slice(2);

if (!sourceZip || !outputZip || !manifestPath || !readmePath) {
    throw new Error(
        'Usage: node tools/finalize-package.mjs SOURCE_ZIP OUTPUT_ZIP MANIFEST README'
    );
}

for (const requiredPath of [sourceZip, manifestPath, readmePath]) {
    if (!fs.existsSync(requiredPath)) {
        throw new Error(`Required file is missing: ${requiredPath}`);
    }
}

const archive = new AdmZip(sourceZip);

archive.deleteFile('manifest.json');
archive.deleteFile('README.md');
archive.addLocalFile(manifestPath, '', 'manifest.json');
archive.addLocalFile(readmePath, '', 'README.md');

const entries = archive.getEntries().map(entry => entry.entryName);
const requiredPrefixes = ['files/', 'scripts/'];

for (const prefix of requiredPrefixes) {
    if (!entries.some(entry => entry.startsWith(prefix))) {
        throw new Error(`Generated package does not contain ${prefix}`);
    }
}

const frontendRoot = 'files/client/custom/modules/management-angajati/lib/';
const frontendInit = frontendRoot + 'init.js';
const frontendBundle = frontendRoot + 'module-management-angajati.js';
const frontendClientMetadata =
    'files/custom/Espo/Modules/ManagementAngajati/Resources/metadata/app/client.json';
const frontendInitUrl = 'client/custom/modules/management-angajati/lib/init.js';

for (const required of [frontendInit, frontendBundle, frontendClientMetadata]) {
    if (!entries.includes(required)) {
        throw new Error(`Generated package does not contain required frontend file: ${required}`);
    }
}

const clientMetadata = JSON.parse(
    archive.getEntry(frontendClientMetadata).getData().toString('utf8')
);

if (
    !Array.isArray(clientMetadata.scriptList) ||
    !clientMetadata.scriptList.includes('__APPEND__') ||
    !clientMetadata.scriptList.includes(frontendInitUrl)
) {
    throw new Error(
        'Bundled frontend is not registered in app/client.json scriptList: ' +
        frontendInitUrl
    );
}

// Check actual AMD definitions, not just the bundle-mapping table. EspoCRM
// may map a module to this bundle even when its anonymous define() was
// packaged without the required module ID.
const sourceDir = path.join(
    path.dirname(manifestPath),
    'src/files/client/custom/modules/management-angajati/src'
);

function sourceModulePaths(directory, prefix = '') {
    return fs.readdirSync(directory, {withFileTypes: true}).flatMap(entry => {
        const relative = path.posix.join(prefix, entry.name);
        const absolute = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            return sourceModulePaths(absolute, relative);
        }

        if (!entry.isFile() || !/\\.(js|ts)$/.test(entry.name) || entry.name.endsWith('.d.ts')) {
            return [];
        }

        return [relative.replace(/\\.(js|ts)$/, '')];
    });
}

const modulePaths = sourceModulePaths(sourceDir);

if (modulePaths.length === 0) {
    throw new Error('No frontend source modules found.');
}

const bundleContents = archive.getEntry(frontendBundle).getData().toString('utf8');

for (const modulePath of modulePaths) {
    const moduleId = 'modules/management-angajati/' + modulePath;
    const escapedId = moduleId.replace(/[.*+?^${}()|[\\]\\]/g, '\\\\if (
    !Array.isArray(clientMetadata.scriptList) ||
    !clientMetadata.scriptList.includes('__APPEND__') ||
    !clientMetadata.scriptList.includes(frontendInitUrl)
) {
    throw new Error(
        'Bundled frontend is not registered in app/client.json scriptList: ' +
        frontendInitUrl
    );
}
');
    const definitionPattern = new RegExp('\\\\bdefine\\\\s*\\\\(\\\\s*[\\\'"]' + escapedId + '[\\\'"]\\\\s*,');

    if (!definitionPattern.test(bundleContents)) {
        throw new Error('Bundled frontend does not define AMD module: ' + moduleId);
    }
}

fs.mkdirSync(path.dirname(outputZip), {recursive: true});
archive.writeZip(outputZip);

const checksum = crypto
    .createHash('sha256')
    .update(fs.readFileSync(outputZip))
    .digest('hex');

process.stdout.write(`Created ${outputZip}\nSHA-256 ${checksum}\n`);
