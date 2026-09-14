import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..', '..');
const moduleRoot = path.join(
    extensionRoot,
    'files', 'custom', 'Espo', 'Modules', 'HolidayManagement'
);

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(moduleRoot, ...segments), 'utf8'));
}

async function readModuleSource(...segments) {
    return readFile(path.join(moduleRoot, ...segments), 'utf8');
}

test('approved holidays generate stored DOCX attachments from the bundled template', async () => {
    const balance = await readModuleSource(
        'Tools', 'HolidayBalance', 'HolidayBalanceService.php'
    );
    const service = await readModuleSource(
        'Tools', 'HolidayDocument', 'HolidayApprovalDocumentService.php'
    );
    const generator = await readModuleSource(
        'Tools', 'HolidayDocument', 'HolidayDocumentGenerator.php'
    );
    const template = await readFile(path.join(
        moduleRoot, 'Resources', 'templates', 'holiday-request.docx'
    ));

    assert.match(balance, /HolidayApprovalDocumentService/);
    assert.match(balance, /statusAfter === self::STATUS_APPROVED/);
    assert.match(balance, /approvalDocumentService->generate\(\$request\)/);
    assert.match(service, /Attachment::ENTITY_TYPE/);
    assert.match(service, /setParent\(\$request\)/);
    assert.match(service, /setTargetField\('approvalDocuments'\)/);
    assert.match(service, /fileStorageManager->putContents/);
    assert.match(generator, /Resources\/templates\/holiday-request\.docx/);
    assert.match(generator, /ZipArchive/);
    assert.match(generator, /word\/document\.xml/);
    assert.equal(template.subarray(0, 2).toString(), 'PK');
});

test('document data uses requester names, configured approval blocks, and monthly segments', async () => {
    const service = await readModuleSource(
        'Tools', 'HolidayDocument', 'HolidayApprovalDocumentService.php'
    );

    assert.match(service, /User::ENTITY_TYPE, \$userId/);
    assert.match(service, /\$user->get\('lastName'\)/);
    assert.match(service, /\$user->get\('firstName'\)/);
    assert.match(service, /holidayManagementApprovalBlock1Title/);
    assert.match(service, /holidayManagementApprovalBlock1Name/);
    assert.match(service, /holidayManagementApprovalBlock2Title/);
    assert.match(service, /holidayManagementApprovalBlock2Name/);
    assert.match(service, /first day of next month/);
    assert.match(service, /nonWorkingDayProvider->getDates/);
    assert.match(service, /workingDayCalculator->count/);
    assert.match(service, /Cerere concediu - %s %d\.docx/);
    assert.match(
        service,
        /foreach \(\$segments as \$segment\) \{\s*\$daysAlreadyUsed = max\(0\.0, \$totalDays - \$balanceBefore\);\s*\$balanceAfter = \$balanceBefore - \$segment\['days'\]/
    );
    assert.match(service, /\$balanceBefore = \$balanceAfter;/);
});

test('approved document attachments are read-only and visible on request details', async () => {
    const defs = await readJson('Resources', 'metadata', 'entityDefs', 'HolidayRequest.json');
    const acl = await readJson('Resources', 'metadata', 'entityAcl', 'HolidayRequest.json');
    const detail = await readJson('Resources', 'layouts', 'HolidayRequest', 'detail.json');
    const detailSmall = await readJson(
        'Resources', 'layouts', 'HolidayRequest', 'detailSmall.json'
    );

    assert.equal(defs.fields.approvalDocuments.type, 'attachmentMultiple');
    assert.equal(defs.fields.approvalDocuments.readOnly, true);
    assert.deepEqual(defs.fields.approvalDocuments.accept, ['.docx']);
    assert.equal(acl.fields.approvalDocuments.readOnly, true);

    for (const layout of [detail, detailSmall]) {
        assert.ok(layout[0].rows.flat().some(
            item => item && item.name === 'approvalDocuments'
        ));
    }

    for (const locale of ['en_US', 'ro_RO']) {
        const labels = await readJson(
            'Resources', 'i18n', locale, 'HolidayRequest.json'
        );
        assert.equal(typeof labels.fields.approvalDocuments, 'string');
    }
});

test('approval detail refresh fetches generated attachment data immediately', async () => {
    const source = await readFile(path.join(
        extensionRoot,
        'files', 'client', 'custom', 'modules', 'holiday-management',
        'src', 'views', 'holiday-request', 'record', 'approval-actions.js'
    ), 'utf8');

    assert.match(source, /view\.model\.set\(result\);\s*await view\.model\.fetch\(\);/);
});
