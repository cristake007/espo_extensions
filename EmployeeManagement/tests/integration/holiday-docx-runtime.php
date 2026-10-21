<?php

declare(strict_types=1);

if (getenv('EMPLOYEE_TEST_LAB') !== 'employee-docx-0813-lab') {
    throw new RuntimeException('Isolated Employee Management 0.8.13 laboratory only.');
}
chdir('/var/www/html');
require 'bootstrap.php';
$application = new Espo\Core\Application();
$application->setupSystemUser();
$container = $application->getContainer();
$config = $container->get('config');
if ($config->get('siteUrl') !== 'http://127.0.0.1:18089' || $config->get('version') !== '10.0.1') {
    throw new RuntimeException('Wrong runtime.');
}
$em = $container->get('entityManager');
$factory = $container->get('injectableFactory');
$storage = $factory->create(Espo\Core\FileStorage\Manager::class);
$results = [];
function check(string $name, bool $ok, mixed $observed = null): void
{
    global $results;
    $results[] = ['test' => $name, 'status' => $ok ? 'PASS' : 'FAIL', 'observed' => $observed];
    echo json_encode(end($results), JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
function fresh(string $type, array $values): Espo\ORM\Entity
{
    global $em;
    $entity = $em->getNewEntity($type); $entity->set($values); $em->saveEntity($entity); return $entity;
}

$request = $em->getRDBRepository('EmployeeHolidayRequest')->where(['name' => 'employee-docx-0813-preservation'])->findOne();
$profile = $em->getEntityById('EmployeeHolidayProfile', (string) $request->get('profileId'));
$documents = iterator_to_array($em->getRDBRepository('EmployeeHolidayDocument')->where(['requestId' => $request->getId()])->order('version')->find());
$attachments = iterator_to_array($em->getRDBRepository('Attachment')->where(['parentId' => $request->getId()])->order('createdAt')->find());
$legacy = array_values(array_filter($documents, fn ($item) => $item->get('issueType') === 'Legacy'))[0] ?? null;
$issued = array_values(array_filter($documents, fn ($item) => $item->get('status') === 'Issued'))[0] ?? null;
$original = $legacy ? $em->getEntityById('Attachment', (string) $legacy->get('attachmentId')) : null;
$replacement = $issued ? $em->getEntityById('Attachment', (string) $issued->get('attachmentId')) : null;
check('one legacy and one issued identity', count($documents) === 2 && $legacy && $issued, array_map(fn ($d) => [$d->get('issueType'), $d->get('status')], $documents));
check('original bytes preserved', $original && hash('sha256', $storage->getContents($original)) === hash('sha256', 'legacy-docx-bytes-0813'));
check('original marked superseded and replacement published', $original?->get('field') === 'supersededApprovalDocuments' && $replacement?->get('field') === 'approvalDocuments');
check('replacement digest bound to final bytes', hash_equals((string) $issued->get('sha256'), hash('sha256', $storage->getContents($replacement))));
check('approval and accounting unchanged by reissue', $request->get('status') === 'Approved' && (int) $request->get('accountingRevision') === 2 && (float) $profile->get('balance') === 0.0);
check('ledger attendance and monthly scope unchanged',
    $em->getRDBRepository('EmployeeHolidayLedger')->count() === 2 &&
    $em->getRDBRepository('EmployeeAttendanceRecord')->count() === 1 &&
    count($attachments) === 2 && (string) $issued->get('month') === '2027-01');

$bytes = $storage->getContents($replacement);
$path = tempnam(sys_get_temp_dir(), 'docx-runtime-'); file_put_contents($path, $bytes);
$zip = new ZipArchive(); $opened = $zip->open($path) === true; $xml = $opened ? (string) $zip->getFromName('word/document.xml') : ''; if ($opened) $zip->close(); unlink($path);
$code = (string) $issued->get('verificationCode');
$documentXml = new DOMDocument(); $documentXml->loadXML($xml);
$xpath = new DOMXPath($documentXml); $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$rows = $xpath->query('//w:tbl[1]/w:tr');
$headers = $xpath->query('./w:tc', $rows->item(8));
$values = $xpath->query('./w:tc', $rows->item(9));
$cellText = static function (DOMNode $cell) use ($xpath): string {
    $text = ''; foreach ($xpath->query('.//w:t', $cell) as $node) { $text .= $node->textContent; } return $text;
};
check('actual reissued DOCX preserves original headers',
    $cellText($headers->item(0)) === 'Total zile' && $cellText($headers->item(1)) === 'Deja primite' &&
    $cellText($headers->item(2)) === 'Au rămas' && $cellText($headers->item(3)) === 'Zile cerute' &&
    $cellText($headers->item(4)) === 'Rest zile');
check('actual reissued DOCX contains two-line January total and 0/24/3/21',
    $cellText($values->item(0)) === '21 - 20273 - report 2026' &&
    $xpath->query('.//w:br', $values->item(0))->length === 1 &&
    $cellText($values->item(1)) === '0' && $cellText($values->item(2)) === '24' &&
    $cellText($values->item(3)) === '3' && $cellText($values->item(4)) === '21' &&
    !str_contains($xml, 'Report an anterior'));
$codeNode = $xpath->query('//w:body//w:t[. = "Cod de verificare: ' . $code . '"]')->item(0);
$codeParagraph = $codeNode ? $xpath->query('ancestor::w:p', $codeNode)->item(0) : null;
$table = $xpath->query('//w:body/w:tbl[1]')->item(0); $afterTable = $table?->nextSibling;
while ($afterTable && $afterTable->nodeType !== XML_ELEMENT_NODE) { $afterTable = $afterTable->nextSibling; }
check('actual reissued DOCX contains one visible right-aligned code below the table',
    strlen($code) === 32 && substr_count($xml, 'Cod de verificare: ' . $code) === 1 &&
    $codeParagraph instanceof DOMElement && $afterTable instanceof DOMElement &&
    $codeParagraph->isSameNode($afterTable) && $xpath->query('ancestor::w:tc', $codeNode)->length === 0 &&
    $xpath->query('./w:pPr/w:jc[@w:val="right"]', $codeParagraph)->length === 1 &&
    $xpath->query('.//w:vanish|.//w:webHidden|.//w:br|.//w:cr', $codeParagraph)->length === 0);

$roleData = [
    'EmployeeManagement' => true, 'EmployeeHolidayApproval' => true,
    'EmployeeHolidayRequest' => (object) ['read' => 'all', 'create' => 'no', 'edit' => 'no', 'delete' => 'no'],
];
$role = $em->getRDBRepository('Role')->where(['name' => 'DOCX verification team role'])->findOne()
    ?: fresh('Role', ['name' => 'DOCX verification team role', 'data' => (object) $roleData, 'fieldData' => (object) []]);
$team = $em->getRDBRepository('Team')->where(['name' => 'DOCX verification team'])->findOne()
    ?: fresh('Team', ['name' => 'DOCX verification team', 'rolesIds' => [$role->getId()]]);
$manager = $em->getRDBRepository('User')->where(['userName' => 'docx_team_manager'])->findOne()
    ?: fresh('User', ['userName' => 'docx_team_manager', 'firstName' => 'Team', 'lastName' => 'Manager', 'type' => 'regular', 'isActive' => true, 'teamsIds' => [$team->getId()]]);
$employeeRole = $em->getRDBRepository('Role')->where(['name' => 'DOCX employee role'])->findOne()
    ?: fresh('Role', ['name' => 'DOCX employee role', 'data' => (object) ['EmployeeManagement' => true, 'EmployeeHolidayRequest' => (object) ['read' => 'own']], 'fieldData' => (object) []]);
$employee = $em->getRDBRepository('User')->where(['userName' => 'docx_unauthorized_employee'])->findOne()
    ?: fresh('User', ['userName' => 'docx_unauthorized_employee', 'firstName' => 'Unauthorized', 'lastName' => 'Employee', 'type' => 'regular', 'isActive' => true, 'rolesIds' => [$employeeRole->getId()]]);
$clearer = $factory->create(Espo\Core\Acl\Cache\Clearer::class);
$clearer->clearForUser($manager);
$clearer->clearForUser($employee);
$provider = $factory->create(Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidayApproverProvider::class);
check('team-inherited approval permission recognized', $provider->isApprover($manager) && $provider->canReadApprovalRequest($manager, $request));

$registry = $factory->create(Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentRegistry::class);
$aclManager = $container->getByClass(Espo\Core\AclManager::class);
$log = $container->getByClass(Espo\Core\Utils\Log::class);
$verification = new Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentVerificationService(
    $em, $storage, $registry, $provider, $manager, $aclManager->createUserAcl($manager), $log,
);
$plain = $verification->verify(['code' => $code]);
$match = $verification->verify(['code' => $code, 'fileName' => 'document.docx', 'fileContents' => base64_encode($bytes)]);
$modified = $verification->verify(['code' => $code, 'fileName' => 'document.docx', 'fileContents' => base64_encode($bytes . 'changed')]);
$unknown = $verification->verify(['code' => str_repeat('A', 32)]);
check('authorized lookup returns official details', $plain['found'] && $plain['documentValidity'] === 'Valid' && $plain['integrity'] === 'NotChecked');
check('exact bytes match and modified bytes fail', $match['integrity'] === 'Match' && $modified['integrity'] === 'Modified');
check('unknown code discloses no record', $unknown === ['found' => false, 'documentValidity' => 'Unknown', 'integrity' => 'NotChecked']);

$request->set('status', 'CancellationPending'); $em->saveEntity($request);
$pending = $verification->verify(['code' => $code]);
$request->set('status', 'Cancelled'); $em->saveEntity($request);
$revoked = $verification->verify(['code' => $code]);
$request->set('status', 'Approved'); $em->saveEntity($request);
check('current cancellation states drive validity', $pending['documentValidity'] === 'CancellationPending' && $revoked['documentValidity'] === 'Revoked');
$issued->set('status', 'Superseded'); $em->saveEntity($issued);
$superseded = $verification->verify(['code' => $code]);
$issued->set('status', 'Issued'); $em->saveEntity($issued);
check('older issued version reports superseded', $superseded['documentValidity'] === 'Superseded');

$denied = false;
try {
    (new Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentVerificationService(
        $em, $storage, $registry, $provider, $employee, $aclManager->createUserAcl($employee), $log,
    ))->verify(['code' => $code]);
} catch (Espo\Core\Exceptions\Forbidden) { $denied = true; }
check('unauthorized employee is rejected', $denied);
check('verification attempts are audited', $em->getRDBRepository('EmployeeHolidayDocumentVerificationLog')->count() >= 7);

$failures = count(array_filter($results, fn ($result) => $result['status'] === 'FAIL'));
$output = ['status' => $failures ? 'FAIL' : 'PASS', 'assertions' => count($results), 'failures' => $failures, 'results' => $results];
file_put_contents('/tmp/employee-docx-0813-runtime-results.json', json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['status' => $output['status'], 'assertions' => count($results), 'failures' => $failures]) . PHP_EOL;
exit($failures ? 1 : 0);
