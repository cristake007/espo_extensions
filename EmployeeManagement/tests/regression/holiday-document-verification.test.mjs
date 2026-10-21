import assert from 'node:assert/strict';
import fs from 'node:fs';

const root = new URL('../../', import.meta.url);
const read = path => fs.readFileSync(new URL(path, root), 'utf8');
const approval = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayApprovalService.php');
const capture = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayApprovalDocumentService.php');
const generator = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayDocumentGenerator.php');
const verification = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayDocumentVerificationService.php');
const reissue = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayDocument/HolidayDocumentReissueService.php');
const page = read('files/client/custom/modules/employee-management/src/views/holiday-document/verification.js');

const checks = [
    ['secure-128-bit-code', capture.includes('bin2hex(random_bytes(16))')],
    ['retry-stable-snapshot-code', capture.includes("'verificationCode'=>$verificationCode") && approval.includes("$document['verificationCode']")],
    ['final-bytes-digest', approval.indexOf('$this->documents->render') < approval.indexOf("hash('sha256',$contents)")],
    ['protected-registration', approval.includes('$this->registry->registerApproval(')],
    ['manager-native-authorization', verification.includes('$this->approvers->isApprover($this->user)') && verification.includes('canReadApprovalRequest')],
    ['request-and-attachment-acl', verification.includes("checkField(self::REQUEST, 'approvalDocuments', 'read')") && verification.includes('checkEntity($attachment')],
    ['bounded-upload', verification.includes('10 * 1024 * 1024') && verification.includes('base64_decode($encoded, true)')],
    ['rate-limit', verification.includes('RATE_LIMIT = 20') && verification.includes("createdAt>='")],
    ['status-derived-validity', ['Cancelled', 'CancellationPending', 'Superseded'].every(value => verification.includes(value))],
    ['modified-file-never-matches', verification.includes("$integrity === 'Modified'") && verification.includes('hash_equals')],
    ['no-upload-persistence', !verification.includes('putContents') && !verification.includes("getNewEntity('Attachment')")],
    ['cli-does-not-run-approval', !reissue.includes('HolidayApprovalService') && !reissue.includes('->initiate(')],
    ['original-preserved-as-history', reissue.includes("setTargetField('supersededApprovalDocuments')")],
    ['explicit-cli-confirmation', reissue.includes('confirmedAttachmentId')],
    ['browser-optional-docx', page.includes('readAsDataURL') && page.includes('10 * 1024 * 1024')],
    ['docx-only-label', generator.includes("'Cod de verificare: ' . $code") && !generator.includes('EspoCRM')],
    ['code-outside-main-table', generator.includes("query('//w:body/w:tbl[1]')") && generator.includes('insertBefore($paragraph, $next)')],
    ['visible-right-aligned-code', generator.includes("setAttributeNS(self::WORD_NAMESPACE, 'w:val', 'right')") && generator.includes("setAttributeNS(self::WORD_NAMESPACE, 'w:val', '000000')")],
];

for (const [name, passed] of checks) assert.equal(passed, true, name);
console.log(`PASS: Holiday document verification regression (${checks.length}/${checks.length}).`);
