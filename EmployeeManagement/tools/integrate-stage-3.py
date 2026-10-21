"""Historical extraction recipe. Emits apply_patch; do not rerun over maintained code."""
from pathlib import Path
import re,json
root=Path(__file__).resolve().parents[1]
base='files/custom/Espo/Modules/EmployeeManagement/'
pending=root/'pending/Holiday'/base
source=(pending/'Tools/HolidayBalance/HolidayBalanceService.php').read_text()
def method(name):
    match=re.search(r'    (?:public|private) function '+name+r'\(',source)
    end=re.search(r'\n    (?:/\*\*|(?:public|private) function)',source[match.end():])
    stop=match.end()+end.start() if end else source.rfind('\n}')
    return source[match.start():stop].rstrip()+'\n'
patch=[]
def add(path,text):
    patch.append('*** Add File: '+str(root/path)+'\n'+'\n'.join('+'+x for x in text.rstrip().splitlines()))
names=['getMyBalance','getPendingDays','listManagedBookingUsers','prepareHolidayForUser','reserveHoliday','adjustHoliday','findManagedEmployee','normalizeRequestDates','requestDate','assertBookingDatesAllowed','assertManagedBookingDatesAllowed','assertNoRequestOverlap','assertBalanceLimit','formatDays','validateIdempotencyKey','message','translateValidationMessage']
methods='\n'.join(method(n) for n in names)
methods=methods.replace("'userId'", "'assignedUserId'").replace("'userName'", "'assignedUserName'").replace("->order('userName')", "->order('assignedUserName')")
methods=methods.replace('$this->requestManagerAccessChecker->assertManager();', '$this->assertManager();')
methods=methods.replace("['status' => self::STATUS_PENDING]", "['status' => [self::STATUS_PENDING, 'ApprovalProcessing']]")
methods=methods.replace("'OR' => [\n                ['status!=' => self::STATUS_REJECTED],\n                ['status' => null],\n            ],", "'status' => ['Pending', 'ApprovalProcessing', 'Approved', 'CancellationPending'],")
methods=methods.replace('$this->countWorkingDays($dateStart, $dateEnd)', '$this->calculate($targetUser, $dateStart, $dateEnd, $request)')
methods=methods.replace('$this->assertRequestOwner($userId);', '')
methods=methods.replace('$this->createLedger(', '$this->profiles->createLedger(').replace('$this->snapshot(', '$this->profiles->snapshot(')
methods=methods.replace("$revision = (int) $request->getFetched('accountingRevision') + 1;", "$revision = (int) $request->get('accountingRevision');")
methods=methods.replace('$request->getFetched(\'accountingKey\')', '$request->get(\'accountingKey\')')
header='''<?php
declare(strict_types=1);
namespace Espo\\Modules\\EmployeeManagement\\Tools\\HolidayBalance;
use Espo\\Core\\Acl;
use Espo\\Core\\Exceptions\\{BadRequest, Conflict, Forbidden, NotFound};
use Espo\\Core\\Utils\\{Config, Language};
use Espo\\Core\\Utils\\DateTime as DateTimeUtil;
use Espo\\Core\\Utils\\Id\\RecordIdGenerator;
use Espo\\Entities\\User;
use Espo\\ORM\\{Entity, EntityManager};
use Espo\\Modules\\EmployeeManagement\\Tools\\EmployeeHolidayRequest\\{BookingDatePolicy, WorkingDayCalculator, NonWorkingDayProvider};
use InvalidArgumentException;

/** Request/accounting subset of Holiday 1.9.4. Commands own transactions; generic CRUD stays closed. */
final class HolidayRequestService
{
    private const PROFILE = 'EmployeeHolidayProfile';
    private const REQUEST = 'EmployeeHolidayRequest';
    private const STATUS_PENDING = 'Pending';
    private const STATUS_APPROVED = 'Approved';
    private const STATUS_REJECTED = 'Rejected';
    public function __construct(private EntityManager $entityManager, private Config $config,
        private User $user, private DateTimeUtil $dateTime, private Language $language,
        private RecordIdGenerator $recordIdGenerator, private BookingDatePolicy $bookingDatePolicy,
        private WorkingDayCalculator $workingDayCalculator, private NonWorkingDayProvider $nonWorkingDayProvider,
        private HolidayProfileService $profiles, private Acl $acl) {}
'''
add(base+'Tools/HolidayBalance/HolidayRequestService.php',header+methods+'\n}\n')
for name in ['NonWorkingDayProvider','WorkingDayCalculator']:
    add(base+'Tools/EmployeeHolidayRequest/'+name+'.php',(pending/('Tools/EmployeeHolidayRequest/'+name+'.php')).read_text())
# Existing native profile/ledger operations, not a new accounting implementation.
path=root/base/'Tools/HolidayBalance/HolidayProfileService.php'
old=path.read_text();new=old.replace('private function createLedger(', 'public function createLedger(').replace('private function snapshot(', 'public function snapshot(')
correct=method('correct').replace('$this->validateIdempotencyKey($idempotencyKey);', "$this->accessChecker->assertProfiles();\n        $this->accessChecker->assertScope('EmployeeHolidayBalanceCorrection');\n        $this->validateIdempotencyKey($idempotencyKey);")
correct=correct.replace('return $this->entityManager->getTransactionManager()->run(function () use (', "$operationHash = hash('sha256', json_encode(['correction', $profileId, $delta, trim($reason)], JSON_THROW_ON_ERROR));\n        return $this->entityManager->getTransactionManager()->run(function () use (\n            $operationHash,")
correct=correct.replace('$existing = $this->findLedgerByKey($idempotencyKey);', '$profile = $this->lockProfile($profileId);\n            $existing = $this->findLedgerByKey($idempotencyKey);',1)
correct=correct.replace('$this->duplicateResult($existing)', '$this->duplicateResult($existing, $operationHash)').replace('$this->mutationResult($profile, $existingAfterLock, true)', '$this->duplicateResult($existingAfterLock, $operationHash)')
correct=correct.replace('                $idempotencyKey,\n', '                $idempotencyKey, null, $operationHash,\n')
history=method('listCorrections').replace('$profile = $this->entityManager', "$this->accessChecker->assertProfiles();\n        $this->accessChecker->assertScope('EmployeeHolidayBalanceCorrection');\n        $profile = $this->entityManager",1).replace('$this->assertProfileForActiveRegularUser($profile);', "$owner = $this->entityManager->getEntityById('User', (string) $profile->get('assignedUserId'));\n        if (!$owner || !$owner->isRegular() || !$owner->isActive()) { throw new BadRequest('Ineligible employee.'); }")
new=new.rstrip()[:-1]+correct+'\n'+history+'}\n'
patch.append('*** Update File: '+str(path)+'\n@@\n'+'\n'.join('-'+x for x in old.rstrip().splitlines())+'\n'+'\n'.join('+'+x for x in new.rstrip().splitlines()))
print('*** Begin Patch\n'+'\n'.join(patch)+'\n*** End Patch')
