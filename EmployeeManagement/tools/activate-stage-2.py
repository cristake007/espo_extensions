"""One-time import recipe. Emits apply_patch; never edits originals or pending."""
from pathlib import Path
import json,re,hashlib
root=Path(__file__).resolve().parents[1]
module='files/custom/Espo/Modules/EmployeeManagement/'
out={}; reuse=[]
def stage(source,destination,text):
    out[destination]=text
    reuse.append({'source':source,'destination':destination,'sourceSha256':hashlib.sha256((root/source).read_bytes()).hexdigest(),'treatment':'Adapted from preserved legacy implementation; ACL/allowlists/ownership/idempotency hardened for stage 2.'})

src='pending/Attendance/'+module+'Tools/Attendance/AttendanceSettingsService.php'
t=(root/src).read_text().replace('private AttendanceAccessChecker $accessChecker','private \\Espo\\Modules\\EmployeeManagement\\Tools\\Administration\\AdministrationAccessChecker $accessChecker').replace('$this->accessChecker->assertManager();',"$this->accessChecker->assertScope('EmployeeAttendanceSettings');")
t=t.replace('$editablePastMonths = $data->editablePastMonths ?? null;',"$this->accessChecker->assertKeys($data, ['editablePastMonths', 'allowIncompleteExports']);\n        $editablePastMonths = $data->editablePastMonths ?? null;")
# Archive initializer depends on the future WTC/snapshot integration. Do not ship it.
t=t[:t.index('    /** @return array{archiveStartMonth: string} */')]+ '}\n'
stage(src,module+'Tools/Attendance/AttendanceSettingsService.php',t)

src='pending/Holiday/'+module+'Tools/HolidayAdministration/HolidaySettingsService.php'
t=(root/src).read_text().replace('private EmployeeManagementAccessChecker $accessChecker','private \\Espo\\Modules\\EmployeeManagement\\Tools\\Administration\\AdministrationAccessChecker $accessChecker').replace('$this->accessChecker->assertSettings();',"$this->accessChecker->assertScope('EmployeeHolidaySettings');")
t=t.replace('        $annualEntitlement = $data->',"        $this->accessChecker->assertKeys($data, [...self::STRING_FIELDS, 'employeeHolidayAnnualEntitlementDays', 'employeeHolidayResetDate', 'employeeHolidayAutomaticAnnualGrantEnabled', 'employeeHolidayCarryOverLimitDays', 'employeeHolidayNegativeBalanceLimitDays', 'employeeHolidayApproversIds']);\n        $annualEntitlement = $data->",1)
t=t.replace("$resetDate = trim((string) ($data->employeeHolidayResetDate ?? ''));","if (!is_string($data->employeeHolidayResetDate ?? null)) { throw new BadRequest('Invalid reset date type.'); }\n        $resetDate = trim($data->employeeHolidayResetDate);")
t=t.replace("        $values = [", "        if ($automaticGrant) { throw new BadRequest('Automatic grants are not enabled in this implementation stage.'); }\n\n        $values = [",1)
t=t.replace("            $value = trim((string) ($data->{$field} ?? ''));", "            if (!is_string($data->{$field} ?? '')) { throw new BadRequest('Printed approval values must be strings.'); }\n            $value = trim($data->{$field} ?? '');")
t=t.replace("                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],", "                'type' => User::TYPE_REGULAR,")
t=t.replace("        $ids = array_values", "        foreach ($value as $id) { if (!is_string($id) || $id === '') { throw new BadRequest('Invalid approver ID.'); } }\n        $ids = array_values",1)
t=t.replace("        return $ids;\n    }", "        foreach ($ids as $id) {\n            $candidate = $this->entityManager->getEntityById('User', $id);\n            if (!$this->aclManager->createUserAcl($candidate)->checkScope('EmployeeHolidayApproval')) { throw new BadRequest('Approvers require the native EmployeeHolidayApproval scope.'); }\n        }\n        return $ids;\n    }",1)
t=t.replace('        private User $user,','        private User $user,\n        private \\Espo\\Core\\AclManager $aclManager,')
pos=t.index('    /** @return list<string> */\n    private function approverIds')
t=t[:pos]+'''    /** Only a routing list; this does not assign Roles or grant approval permission. */
    public function approverOptions(): array
    {
        $this->accessChecker->assertScope('EmployeeHolidaySettings');
        $list = [];
        foreach ($this->entityManager->getRDBRepository('User')->where(['type' => User::TYPE_REGULAR, 'isActive' => true])->order('name')->find() as $candidate) {
            if ($this->aclManager->createUserAcl($candidate)->checkScope('EmployeeHolidayApproval')) {
                $list[] = ['id' => $candidate->getId(), 'name' => $candidate->get('name')];
            }
        }
        return $list;
    }

'''+t[pos:]
stage(src,module+'Tools/HolidayAdministration/HolidaySettingsService.php',t)

src='pending/Holiday/'+module+'Tools/HolidayBalance/HolidayBalanceService.php'
legacy=(root/src).read_text()
names=['listProfiles','bulkInitialize','initializeOne','findEligibleUser','validateCalendarColor','findLedgerByKey','createLedger','snapshot','mutationResult','duplicateResult','getDefaultNextResetDate','validateIdempotencyKey','validateDate','finiteNumber','message']
parts=[]
for name in names:
    match=re.search(r'^    (?:public|private) function '+name+r'\(',legacy,re.M)
    next_method=re.search(r'^    (?:public|private) function ',legacy[match.end():],re.M)
    end=match.end()+next_method.start() if next_method else len(legacy)
    chunk=legacy[match.start():end]
    chunk=chunk[:chunk.rfind('\n    }')+6]
    parts.append(chunk)
t='''<?php
declare(strict_types=1);
namespace Espo\\Modules\\EmployeeManagement\\Tools\\HolidayBalance;
use DateTimeImmutable;
use Espo\\Core\\Exceptions\\{BadRequest, Conflict, NotFound};
use Espo\\Core\\Utils\\{Config, Language};
use Espo\\Core\\Utils\\DateTime as DateTimeUtil;
use Espo\\Entities\\User;
use Espo\\ORM\\{Entity, EntityManager};
use Espo\\Modules\\EmployeeManagement\\Tools\\Administration\\AdministrationAccessChecker;

/** Profile subset extracted from HolidayBalanceService, not a replacement ledger. */
final class HolidayProfileService
{
    private const PROFILE = 'EmployeeHolidayProfile';
    private const LEDGER = 'EmployeeHolidayLedger';
    private const REQUEST = 'EmployeeHolidayRequest';
    private const DEFAULT_CALENDAR_COLOR = '#4F8A8B';
    public function __construct(private EntityManager $entityManager, private Config $config,
        private User $user, private DateTimeUtil $dateTime, private Language $language,
        private AdministrationAccessChecker $accessChecker) {}

'''+ '\n\n'.join(parts)+'\n}\n'
# Native ownership normalization, not an additional identity association.
t=t.replace("get('userId')","get('assignedUserId')").replace("get('userName')","get('assignedUserName')")
t=t.replace("'userId' => $eligibleUser->getId(),\n                    'userName'", "'assignedUserId' => $eligibleUser->getId(),\n                    'assignedUserName'")
t=t.replace("'userId' => $profile->get('assignedUserId')", "'assignedUserId' => $profile->get('assignedUserId')").replace("'userName' => $profile->get('assignedUserName')", "'assignedUserName' => $profile->get('assignedUserName')")
t=t.replace("->where(['userId' => $userId])","->where(['assignedUserId' => $userId])")
t=t.replace("        $profilesByUser = [];", "        $this->accessChecker->assertProfiles();\n        $profilesByUser = [];")
t=t.replace("$this->canCompleteInitialSetup($profile)", 'false')
t=t.replace("                'resetPending' =>", "                'resetPending' =>",1)
t=t.replace("        if ($items === []) {", "        $this->accessChecker->assertProfiles();\n        if (count($items) > 100) { throw new BadRequest('Too many profile items.'); }\n        if ($items === []) {")
t=t.replace("        $result = [];\n\n        foreach ($items as $rawItem)", "        return $this->entityManager->getTransactionManager()->run(function () use ($items): array {\n        $result = [];\n        usort($items, fn ($a, $b) => strcmp((string) ((array) $a)['userId'], (string) ((array) $b)['userId']));\n        foreach ($items as $rawItem)")
t=t.replace("        return $result;\n    }\n\n    private function initializeOne", "        return $result;\n        });\n    }\n\n    private function initializeOne")
t=t.replace("        $userId = trim((string)", "        $this->accessChecker->assertKeys((object) $item, ['userId', 'annualEntitlement', 'openingBalance', 'nextResetDate', 'calendarColor', 'idempotencyKey']);\n        foreach (['userId', 'nextResetDate', 'idempotencyKey'] as $key) { if (!is_string($item[$key] ?? null)) { throw new BadRequest('Invalid profile identifier or date type.'); } }\n        $userId = trim((string)",1)
t=t.replace("        return $this->entityManager->getTransactionManager()->run(function () use (\n            $userId,", "        $operationHash = hash('sha256', json_encode([$userId, $nextResetDate, $annualEntitlement, $openingBalance, $calendarColor], JSON_THROW_ON_ERROR));\n        return $this->entityManager->getTransactionManager()->run(function () use (\n            $operationHash,\n            $userId,",1)
t=t.replace("            $existing = $this->findLedgerByKey($idempotencyKey);", "            $eligibleUser = $this->findEligibleUser($userId);\n            $existing = $this->findLedgerByKey($idempotencyKey);",1)
t=t.replace("            $eligibleUser = $this->findEligibleUser($userId);\n            $profile", "            $profile",1)
t=t.replace("$this->duplicateResult($existing)","$this->duplicateResult($existing, $operationHash)").replace("$this->duplicateResult($existingAfterLock)","$this->duplicateResult($existingAfterLock, $operationHash)")
t=t.replace("            $before = $this->snapshot($profile);", "            if (!$requiresOpeningBalance && $openingBalance !== null) { throw new BadRequest('Opening balance cannot change an initialized profile.'); }\n            $before = $this->snapshot($profile);",1)
t=t.replace("                $idempotencyKey,\n            );", "                $idempotencyKey, null, $operationHash,\n            );",1)
# Lock an existing native User row before a possibly-absent profile row.
t=t.replace("                'type' => User::TYPE_REGULAR,\n            ])\n            ->findOne();", "                'type' => User::TYPE_REGULAR,\n            ])\n            ->forUpdate()\n            ->findOne();",1)
t=t.replace("        ?Entity $request = null,", "        ?Entity $request = null,\n        ?string $operationHash = null,")
t=t.replace("            'idempotencyKey' => $idempotencyKey,", "            'idempotencyKey' => $idempotencyKey,\n            'operationHash' => $operationHash,")
t=t.replace('private function duplicateResult(Entity $ledger): array','private function duplicateResult(Entity $ledger, string $operationHash): array')
t=t.replace("    {\n        $profile = $this->entityManager\n            ->getRDBRepository(self::PROFILE)\n            ->where(['id' => $ledger->get('profileId')])", "    {\n        if ($ledger->get('operationHash') !== $operationHash) { throw new Conflict('Idempotency key already used for different profile parameters.'); }\n        $profile = $this->entityManager\n            ->getRDBRepository(self::PROFILE)\n            ->where(['id' => $ledger->get('profileId')])")
stage(src,module+'Tools/HolidayBalance/HolidayProfileService.php',t)

# Preserve original profile field views; expose only supported actions.
src='pending/Holiday/files/client/custom/modules/employee-management/src/views/admin/profiles.js'
t=(root/src).read_text().replace("EmployeeManagement/profiles", "EmployeeManagement/administration/profiles").replace("EmployeeManagement/bulkInitialize", "EmployeeManagement/administration/profiles/bulkInitialize")
t=t.replace("this.getAcl().check('HolidayBalanceCorrection')",'false').replace('Boolean(row.isInitialized && row.profileId)', 'false')
# No modal or hidden handler can call future correction/complete-setup operations.
start=t.index('        actionCompleteInitialSetup(event)')
t=t[:start]+'    }\n});\n'
t=t.replace("            'click [data-action=\"completeInitialSetup\"]': 'actionCompleteInitialSetup',\n",'').replace("            'click [data-action=\"correctBalance\"]': 'actionCorrectBalance',\n",'').replace("            'click [data-action=\"viewCorrectionHistory\"]': 'actionViewCorrectionHistory',\n",'')
stage(src,'files/client/custom/modules/employee-management/src/views/administration/profiles.js',t)
for lang in ['en_US','ro_RO']:
    src='pending/Holiday/'+module+'Resources/i18n/'+lang+'/Admin.json'
    if (root/src).exists():
        stage(src,module+'Resources/i18n/'+lang+'/EmployeeAdministration.json',(root/src).read_text())
# Translate within our own scope; never merge extension labels into global Admin.
path='files/client/custom/modules/employee-management/src/views/administration/profiles.js'
out[path]=out[path].replace("scope='Admin'", "scope='EmployeeAdministration'").replace("'Admin'", "'EmployeeAdministration'")
out['STAGE-2-REUSE.json']=json.dumps(reuse,ensure_ascii=False,indent=2)+'\n'
print('*** Begin Patch')
for path,text in out.items():
    print('*** Add File: '+str(root/path))
    print('\n'.join('+'+line for line in text.splitlines()))
print('*** End Patch')
