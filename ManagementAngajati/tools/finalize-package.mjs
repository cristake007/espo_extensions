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

const frontendBundle =
    'files/client/custom/modules/management-angajati/lib/module-management-angajati.js';

if (!entries.includes(frontendBundle)) {
    throw new Error('Generated package does not contain the compiled frontend bundle.');
}

fs.mkdirSync(path.dirname(outputZip), {recursive: true});
archive.writeZip(outputZip);

const checksum = crypto
    .createHash('sha256')
    .update(fs.readFileSync(outputZip))
    .digest('hex');

process.stdout.write(`Created ${outputZip}\nSHA-256 ${checksum}\n`);
