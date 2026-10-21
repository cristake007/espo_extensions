<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument;

use Espo\Core\AclManager;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Job\Job\Data;
use Espo\Entities\{User, Attachment};
use Espo\ORM\{Entity, EntityManager};
use Espo\Modules\EmployeeManagement\Tools\Attendance\{AttendanceMonthService, HolidayAttendanceGuard};
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayDecisionNotifier;
use Espo\Modules\EmployeeManagement\Jobs\GenerateHolidayApproval;
use RuntimeException;
use Throwable;

/** Native Job orchestration only. HolidayRequestService remains the sole accounting owner. */
final class HolidayApprovalService
{
    private const REQUEST='EmployeeHolidayRequest';
    public function __construct(private EntityManager $em, private JobSchedulerFactory $schedulers,
        private HolidayApprovalDocumentService $documents, private FileStorageManager $storage,
        private AttendanceMonthService $months, private HolidayAttendanceGuard $attendance,
        private AclManager $aclManager,
        private \Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidayApproverProvider $approvers,
        private HolidayDecisionNotifier $decisionNotifier, private HolidayDocumentRegistry $registry) {}

    /** Caller owns User/Profile/Request/month locks and command authorization. */
    public function initiate(Entity $request,User $target,Entity $profile,User $approver,float $pendingDays): void
    {
        $this->assertRecipient($target,$request);
        $snapshot=$this->documents->capture($request,$target,$profile,$approver,$pendingDays);
        $revision=(int)$request->get('accountingRevision')+1;
        $request->set(['status'=>'ApprovalProcessing','accountingRevision'=>$revision,'approvalRevision'=>$revision,
            'approvalOperationKey'=>hash('sha256',$request->get('accountingKey').':approval:'.$revision),
            'approvalSnapshot'=>(object)$snapshot,'approvalManifest'=>(object)[], 'approvalAttempts'=>0,
            'decidedById'=>$snapshot['decidedById'],'decidedAt'=>$snapshot['decidedAt']]);
        $this->schedule($request);
    }

    /** Never supersede a live native Job on the basis of a clock timeout. */
    public function retry(Entity $request): void
    {
        if ($request->get('status')!=='ApprovalProcessing' || !$request->get('approvalOperationKey')) { throw new Conflict('No recoverable approval operation.'); }
        $job=$this->em->getEntityById('Job',(string)$request->get('approvalJobId'));
        if ($job && in_array($job->get('status'),['Pending','Ready','Running'],true)) { throw new Conflict('The native approval Job is still active. Wait for native failure detection.'); }
        $this->schedule($request);
    }

    private function schedule(Entity $request): void
    {
        $token=bin2hex(random_bytes(16)); $group='employee-holiday:'.$request->getId();
        $data=Data::create(['requestId'=>$request->getId(),'operationKey'=>$request->get('approvalOperationKey'),'token'=>$token])
            ->withTargetId($request->getId())->withTargetType(self::REQUEST);
        $this->schedulers->create()->setClassName(GenerateHolidayApproval::class)->setGroup($group)->setData($data)->schedule();
        // Scheduler::schedule is void in 10.0.1. Read back its own insert under the request lock.
        $job=$this->em->getRDBRepository('Job')->where(['targetId'=>$request->getId(),'targetType'=>self::REQUEST,
            'className'=>GenerateHolidayApproval::class,'group'=>$group])->order('number','DESC')->findOne();
        if (!$job || ((array)$job->get('data'))['token']!==$token) { throw new RuntimeException('The native approval Job was not persisted.'); }
        $job->set('attempts',0); $this->em->saveEntity($job); // Explicit business retry, no hidden native retry loop.
        $request->set(['approvalJobId'=>$job->getId(),'approvalToken'=>$token,'approvalState'=>'Pending','approvalError'=>null]);
    }

    /** Native worker entry. Rendering outside accounting locks; short tracked storage/publication transactions. */
    public function execute(string $id,string $key,string $token): void
    {
        try {
            $snapshot=$this->locked($id,function(Entity $request)use($key,$token):?array{
                if (!$this->current($request,$key,$token)) { return null; }
                $request->set(['approvalState'=>'Processing','approvalError'=>null,'approvalAttempts'=>(int)$request->get('approvalAttempts')+1]);
                $this->em->saveEntity($request); return $this->array($request->get('approvalSnapshot'));
            });
            if ($snapshot===null) { return; }
            foreach ($snapshot['documents'] as $document) {
                $existing=$this->locked($id,function(Entity $request)use($key,$token,$document):?array{
                    if (!$this->current($request,$key,$token)) { return null; }
                    $manifest=$this->array($request->get('approvalManifest'));
                    return $manifest[$document['month']]??[];
                });
                if ($existing===null) { return; }
                $attachment=isset($existing['id'])?$this->em->getEntityById('Attachment',$existing['id']):null;
                if ($attachment instanceof Attachment && $this->validFile($attachment,$existing)) { continue; }
                $contents=$this->documents->render($document,$snapshot['templateHash']);
                // Reserve a durable Attachment ID before any storage write: crash cannot leave an untracked file.
                $prepared=$this->locked($id,function(Entity $request)use($key,$token,$document,$contents):?array{
                    if (!$this->current($request,$key,$token)) { return null; }
                    $manifest=$this->array($request->get('approvalManifest'));$item=$manifest[$document['month']]??[];
                    $a=isset($item['id'])?$this->em->getEntityById('Attachment',$item['id']):$this->em->getNewEntity('Attachment');
                    if (!$a instanceof Attachment) { throw new RuntimeException('Tracked approval attachment is missing.'); }
                    $a->setName($document['filename'])->setType(HolidayApprovalDocumentService::MIME_TYPE)->setRole(Attachment::ROLE_ATTACHMENT)
                        ->setSize(strlen($contents))->setParent($request)->setTargetField('approvalStaging');
                    $this->em->saveEntity($a);
                    $item=['id'=>$a->getId(),'sha256'=>hash('sha256',$contents),'size'=>strlen($contents)];
                    $manifest[$document['month']]=$item;$request->set('approvalManifest',(object)$manifest);$this->em->saveEntity($request);
                    return $item;
                });
                if ($prepared===null) { return; }
                $this->locked($id,function(Entity $request)use($key,$token,$prepared,$contents):void{
                    if (!$this->current($request,$key,$token)) { return; }
                    $a=$this->em->getEntityById('Attachment',$prepared['id']);
                    // Serialize the small storage write with retry fencing. DOCX rendering is outside this lock.
                    $this->storage->putContents($a,$contents);
                    if (!$this->validFile($a,$prepared)) { throw new RuntimeException('Approval file verification failed.'); }
                });
            }
            $this->locked($id,function(Entity $request,User $target)use($key,$token,$snapshot):void{
                if (!$this->current($request,$key,$token)) { return; }
                if ((int)$request->get('accountingRevision')!==(int)$request->get('approvalRevision') ||
                    !hash_equals($snapshot['calculationHash'],hash('sha256',json_encode($this->array($request->get('calculationSnapshot')),JSON_THROW_ON_ERROR)))) {
                    throw new Conflict('The reserved approval revision changed.');
                }
                $states=$this->months->lockPeriods([[$request->get('dateStartDate'),$request->get('dateEndDate')]]);
                $this->attendance->assertCompatible($request);
                $decider=$this->em->getEntityById('User',$snapshot['decidedById']);
                $acl=$decider instanceof User?$this->aclManager->createUserAcl($decider):null;
                if (!$decider?->isRegular() || !$decider->isActive() || !$acl?->checkScope('EmployeeManagement') ||
                    !$this->approvers->isApprover($decider) || !$acl->checkEntity($request,'read')) { throw new Conflict('Approval authorization is no longer valid.'); }
                $this->assertRecipient($target,$request);
                $manifest=$this->array($request->get('approvalManifest'));
                $ids=[];
                foreach ($snapshot['documents'] as $document) {
                    $item=$manifest[$document['month']]??[];
                    $a=isset($item['id'])?$this->em->getEntityById('Attachment',$item['id']):null;
                    if (!$a instanceof Attachment || $a->get('parentId')!==$request->getId() || $a->get('parentType')!==self::REQUEST || !$this->validFile($a,$item)) { throw new RuntimeException('A required approval document is not persisted.'); }
                    if (isset($document['verificationCode'])) {
                        $this->registry->registerApproval($request,$a,$document,$snapshot,(string)$item['sha256']);
                    }
                    $a->setTargetField('approvalDocuments');$this->em->saveEntity($a);$ids[]=$a->getId();
                }
                $request->set(['approvalDocumentsIds'=>$ids,'status'=>'Approved','approvalState'=>'Completed','approvalError'=>null,
                    'accountingRevision'=>(int)$request->get('accountingRevision')+1]);
                $this->em->saveEntity($request);
                $this->decisionNotifier->notify($request, 'Approved');
                $this->months->touch($states);
            });
        } catch (Throwable $e) {
            $this->locked($id,function(Entity $request)use($key,$token,$e):void{
                if (!$this->current($request,$key,$token)) { return; }
                $message=$e instanceof Conflict?$e->getMessage():'Document generation/storage failed. The operation remains reserved and can be retried by an authorized manager.';
                $request->set(['approvalState'=>'Failed','approvalError'=>$message]);$this->em->saveEntity($request);
            });
            throw $e; // Native JobRunner records Failed and the technical stack; no false Success.
        }
    }

    public function state(Entity $request): array
    {
        $state=$request->get('approvalState');$error=$request->get('approvalError');$retry=false;
        if ($request->get('status')==='ApprovalProcessing' && $request->get('approvalOperationKey')) {
            $job=$this->em->getEntityById('Job',(string)$request->get('approvalJobId'));
            $retry=!$job || in_array($job->get('status'),['Failed','Success'],true);
            if ($retry && $state!=='Failed') { $state='Failed';$error='The native worker stopped before approval completed. An authorized manager can retry the same operation.'; }
        }
        return ['approvalState'=>$state,'approvalError'=>$error,'approvalAttempts'=>(int)$request->get('approvalAttempts'),'approvalRetryAvailable'=>$retry];
    }

    private function assertRecipient(User $target,Entity $request): void
    {
        $acl=$this->aclManager->createUserAcl($target);
        if (!$target->isRegular() || !$target->isActive() || !$acl->checkEntity($request,'read') || !$acl->checkField(self::REQUEST,'approvalDocuments','read')) {
            throw new Conflict('The employee requires native read access to the request and approval documents.');
        }
    }
    private function current(Entity $request,string $key,string $token): bool
    {
        return $request->get('status')==='ApprovalProcessing' && $request->get('approvalOperationKey')===$key && $request->get('approvalToken')===$token;
    }
    private function validFile(Attachment $a,array $item): bool
    {
        return isset($item['sha256']) && $this->storage->exists($a) && hash_equals($item['sha256'],hash('sha256',$this->storage->getContents($a)));
    }
    private function array(mixed $value): array { return json_decode(json_encode($value??(object)[],JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR); }
    private function locked(string $id,callable $callback): mixed
    {
        $pre=$this->em->getEntityById(self::REQUEST,$id);
        if (!$pre) { throw new RuntimeException('Approval request no longer exists.'); }
        $userId=$pre->get('assignedUserId');
        return $this->em->getTransactionManager()->run(function()use($id,$userId,$callback):mixed{
            $target=$this->em->getRDBRepository('User')->where(['id'=>$userId])->select(['id','name','type','isActive','firstName','lastName','workingTimeCalendarId'])->forUpdate()->findOne();
            $this->em->getRDBRepository('EmployeeHolidayProfile')->where(['assignedUserId'=>$userId])->select(['id','assignedUserId','isInitialized'])->forUpdate()->findOne();
            $request=$this->em->getRDBRepository(self::REQUEST)->where(['id'=>$id])->select(\Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayRequestService::LOCK_FIELDS)->forUpdate()->findOne();
            if (!$request || !$target || $request->get('assignedUserId')!==$userId) { throw new RuntimeException('Approval beneficiary changed.'); }
            return $callback($request,$target);
        });
    }
}
