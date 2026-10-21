<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Log;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;
use Throwable;

/** Manual, system-operator-only reissuance. It never invokes approval or accounting services. */
final class HolidayDocumentReissueService
{
    private const REQUEST = 'EmployeeHolidayRequest';

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $storage,
        private HolidayApprovalDocumentService $documents,
        private Config $config,
        private Log $log,
    ) {}

    /** @return array<string, mixed> */
    public function dryRun(string $requestId, string $month): array
    {
        $request = $this->request($requestId);
        $this->assertEligible($request, $month);
        $original = $this->currentAttachment($request, $month);
        $prepared = $this->documents->prepareReissue($request, $month, $this->legacyInputs($request, $month));
        $projection = $prepared['projection'];

        return [
            'requestId' => $requestId,
            'month' => $month,
            'annualEntitlement' => (float) $projection['annualEntitlement'],
            'carryover' => (float) $projection['carryover'],
            'availableBeforeLeave' => (float) $projection['balanceBefore'],
            'requestedDays' => (float) $projection['days'],
            'projectedRemaining' => (float) $projection['balanceAfter'],
            'originalAttachmentId' => (string) $original->getId(),
            'originalAttachmentName' => (string) $original->getName(),
            'originalSha256' => hash('sha256', $this->storage->getContents($original)),
            'accountingRevision' => (int) $request->get('accountingRevision'),
            'accountingChanges' => 'NONE',
            'originalDocument' => 'PRESERVED',
        ];
    }

    /** @return array<string, mixed> */
    public function execute(
        string $requestId,
        string $month,
        string $reason,
        string $confirmedAttachmentId,
        string $operatorContext,
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Execute requires a nonempty reissue reason.');
        }

        $plan = $this->dryRun($requestId, $month);
        if (!hash_equals($plan['originalAttachmentId'], $confirmedAttachmentId)) {
            throw new Conflict('Target confirmation does not match the current document attachment.');
        }

        $request = $this->request($requestId);
        $prepared = $this->documents->prepareReissue($request, $month, $this->legacyInputs($request, $month));
        $issueKey = hash('sha256', implode(':', ['reissue-v1', $requestId, $month, $confirmedAttachmentId, hash('sha256', $reason)]));
        $record = $this->entityManager->getRDBRepository(HolidayDocumentRegistry::ENTITY)
            ->where(['issueKey' => $issueKey])->findOne();

        if ($record && $record->get('status') === HolidayDocumentRegistry::STATUS_ISSUED) {
            throw new Conflict('This exact reissuance has already been published.');
        }

        if ($record) {
            $code = (string) $record->get('verificationCode');
            $prepared['document']['verificationCode'] = $code;
            $prepared['document']['values']['verificationCode'] = $code;
        }

        $contents = $this->documents->render($prepared['document'], $prepared['templateHash']);
        $digest = hash('sha256', $contents);

        if ($record && !hash_equals((string) $record->get('sha256'), $digest)) {
            throw new Conflict('The prepared reissue bytes no longer match their immutable digest.');
        }

        if (!$record) {
            [$record, $attachment] = $this->prepare(
                $request,
                $prepared['document'],
                $issueKey,
                $digest,
                strlen($contents),
                $reason,
                $operatorContext,
            );
        } else {
            $attachment = $this->entityManager->getEntityById('Attachment', (string) $record->get('attachmentId'));
            if (!$attachment instanceof Attachment) {
                throw new RuntimeException('The tracked reissue attachment is missing.');
            }
        }

        try {
            if (!$this->storage->exists($attachment)) {
                $this->storage->putContents($attachment, $contents);
            }
            if (!hash_equals($digest, hash('sha256', $this->storage->getContents($attachment)))) {
                throw new RuntimeException('Stored reissue bytes failed SHA-256 verification.');
            }
            $this->publish(
                $requestId,
                $month,
                $confirmedAttachmentId,
                (int) $plan['accountingRevision'],
                (string) $record->getId(),
                (string) $attachment->getId(),
                $digest,
                (string) $plan['originalSha256'],
            );
        } catch (Throwable $e) {
            $this->log->error('Holiday document reissue failed before publication.', [
                'holidayRequestId' => $requestId,
                'month' => $month,
                'holidayDocumentId' => $record->getId(),
                'exceptionClass' => $e::class,
            ]);
            throw $e;
        }

        return $plan + [
            'newAttachmentId' => (string) $attachment->getId(),
            'newDocumentId' => (string) $record->getId(),
            'verificationCode' => (string) $record->get('verificationCode'),
            'sha256' => $digest,
            'status' => 'ISSUED',
        ];
    }

    /** @return array{0: Entity, 1: Attachment} */
    private function prepare(
        Entity $request,
        array $document,
        string $issueKey,
        string $digest,
        int $size,
        string $reason,
        string $operatorContext,
    ): array {
        return $this->entityManager->getTransactionManager()->run(function () use (
            $request, $document, $issueKey, $digest, $size, $reason, $operatorContext,
        ): array {
            $locked = $this->entityManager->getRDBRepository(self::REQUEST)
                ->where(['id' => $request->getId()])->forUpdate()->findOne();
            if (!$locked || (int) $locked->get('accountingRevision') !== (int) $request->get('accountingRevision')) {
                throw new Conflict('The request changed while preparing reissuance.');
            }

            $existing = $this->entityManager->getRDBRepository(HolidayDocumentRegistry::ENTITY)
                ->where(['issueKey' => $issueKey])->findOne();
            if ($existing) {
                $attachment = $this->entityManager->getEntityById('Attachment', (string) $existing->get('attachmentId'));
                if (!$attachment instanceof Attachment) {
                    throw new RuntimeException('The concurrent reissue has no tracked attachment.');
                }
                return [$existing, $attachment];
            }

            $maxVersion = 0;
            foreach ($this->entityManager->getRDBRepository(HolidayDocumentRegistry::ENTITY)->where([
                'requestId' => $request->getId(), 'month' => $document['month'],
            ])->find() as $prior) {
                $maxVersion = max($maxVersion, (int) $prior->get('version'));
            }
            // A legacy attachment has no registry row yet and occupies version 1.
            $version = max(2, $maxVersion + 1);
            $attachment = $this->entityManager->getNewEntity('Attachment');
            if (!$attachment instanceof Attachment) {
                throw new RuntimeException('Could not create the tracked reissue attachment.');
            }
            $baseName = preg_replace('/\.docx$/i', '', (string) $document['filename']);
            $attachment->setName($baseName . ' - reemis v' . $version . '.docx')
                ->setType(HolidayApprovalDocumentService::MIME_TYPE)
                ->setRole(Attachment::ROLE_ATTACHMENT)->setSize($size)
                ->setParent($locked)->setTargetField('approvalStaging');
            $this->entityManager->saveEntity($attachment);

            $record = $this->entityManager->getNewEntity(HolidayDocumentRegistry::ENTITY);
            $code = (string) $document['verificationCode'];
            $record->set([
                'name' => 'Document concediu ' . $document['month'] . ' v' . $version,
                'issueKey' => $issueKey,
                'verificationCode' => $code,
                'verificationCodeHash' => hash('sha256', $code),
                'sha256' => $digest,
                'requestId' => $locked->getId(),
                'requestName' => $locked->get('name'),
                'attachmentId' => $attachment->getId(),
                'attachmentName' => $attachment->getName(),
                'month' => $document['month'],
                'version' => $version,
                'status' => HolidayDocumentRegistry::STATUS_PREPARED,
                'issueType' => 'Reissue',
                'employeeName' => $locked->get('assignedUserName'),
                'dateStart' => $locked->get('dateStartDate'),
                'dateEnd' => $locked->get('dateEndDate'),
                'workingDays' => (int) $document['values']['requestedDays'],
                'approvedById' => $locked->get('decidedById'),
                'approvedByName' => $locked->get('decidedByName'),
                'approvedAt' => $locked->get('decidedAt'),
                'issuedAt' => DateTimeUtil::getSystemNowString(),
                'reissueReason' => $reason,
                'operatorContext' => $operatorContext,
            ]);
            $this->entityManager->saveEntity($record);
            return [$record, $attachment];
        });
    }

    private function publish(
        string $requestId,
        string $month,
        string $originalAttachmentId,
        int $accountingRevision,
        string $documentId,
        string $attachmentId,
        string $digest,
        string $originalDigest,
    ): void {
        $this->entityManager->getTransactionManager()->run(function () use (
            $requestId, $month, $originalAttachmentId, $accountingRevision, $documentId, $attachmentId, $digest, $originalDigest,
        ): void {
            $request = $this->entityManager->getRDBRepository(self::REQUEST)
                ->where(['id' => $requestId])->forUpdate()->findOne();
            $document = $this->entityManager->getRDBRepository(HolidayDocumentRegistry::ENTITY)
                ->where(['id' => $documentId])->forUpdate()->findOne();
            $original = $this->entityManager->getEntityById('Attachment', $originalAttachmentId);
            $replacement = $this->entityManager->getEntityById('Attachment', $attachmentId);

            if (!$request || !$document || !$original instanceof Attachment || !$replacement instanceof Attachment) {
                throw new RuntimeException('Reissue publication state is incomplete.');
            }
            $this->assertEligible($request, $month);
            if ((int) $request->get('accountingRevision') !== $accountingRevision || $original->get('field') !== 'approvalDocuments') {
                throw new Conflict('The request or target document changed before publication.');
            }
            if (!hash_equals($digest, hash('sha256', $this->storage->getContents($replacement)))) {
                throw new RuntimeException('Reissue publication digest mismatch.');
            }
            if (!hash_equals($originalDigest, hash('sha256', $this->storage->getContents($original)))) {
                throw new Conflict('The original document bytes changed after dry-run validation.');
            }

            $prior = $this->entityManager->getRDBRepository(HolidayDocumentRegistry::ENTITY)->where([
                'attachmentId' => $originalAttachmentId,
            ])->findOne();
            if (!$prior) {
                $prior = $this->legacyRecord($request, $original, $month);
            }
            $prior->set([
                'status' => HolidayDocumentRegistry::STATUS_SUPERSEDED,
                'supersededAt' => DateTimeUtil::getSystemNowString(),
                'supersededByDocumentId' => $document->getId(),
            ]);
            $this->entityManager->saveEntity($prior);
            $original->setTargetField('supersededApprovalDocuments');
            $this->entityManager->saveEntity($original);
            $replacement->setTargetField('approvalDocuments');
            $this->entityManager->saveEntity($replacement);
            $document->set('status', HolidayDocumentRegistry::STATUS_ISSUED);
            $this->entityManager->saveEntity($document);
            $currentIds = [];
            $historicalIds = [];
            foreach ($this->entityManager->getRDBRepository('Attachment')->where([
                'parentId' => $requestId, 'parentType' => self::REQUEST,
                'field' => ['approvalDocuments', 'supersededApprovalDocuments'],
            ])->find() as $item) {
                if ($item->get('field') === 'approvalDocuments') { $currentIds[] = $item->getId(); }
                else { $historicalIds[] = $item->getId(); }
            }
            $request->set([
                'approvalDocumentsIds' => array_values(array_unique($currentIds)),
                'supersededApprovalDocumentsIds' => array_values(array_unique($historicalIds)),
            ]);
            $this->entityManager->saveEntity($request);
            if ((int) $request->get('accountingRevision') !== $accountingRevision) {
                throw new RuntimeException('Reissue attempted to change the accounting revision.');
            }
        });
    }

    private function legacyRecord(Entity $request, Attachment $attachment, string $month): Entity
    {
        $record = $this->entityManager->getNewEntity(HolidayDocumentRegistry::ENTITY);
        $snapshot = $this->array($request->get('approvalSnapshot'));
        $record->set([
            'name' => 'Document concediu ' . $month . ' legacy',
            'issueKey' => hash('sha256', 'legacy:' . $request->getId() . ':' . $month . ':' . $attachment->getId()),
            'sha256' => hash('sha256', $this->storage->getContents($attachment)),
            'requestId' => $request->getId(), 'requestName' => $request->get('name'),
            'attachmentId' => $attachment->getId(), 'attachmentName' => $attachment->getName(),
            'month' => $month, 'version' => 1,
            'status' => HolidayDocumentRegistry::STATUS_SUPERSEDED, 'issueType' => 'Legacy',
            'employeeName' => $request->get('assignedUserName'),
            'dateStart' => $request->get('dateStartDate'), 'dateEnd' => $request->get('dateEndDate'),
            'workingDays' => $this->monthDays($request, $month),
            'approvedById' => $snapshot['decidedById'] ?? $request->get('decidedById'),
            'approvedByName' => $snapshot['decidedByName'] ?? $request->get('decidedByName'),
            'approvedAt' => $snapshot['decidedAt'] ?? $request->get('decidedAt'),
            'issuedAt' => $request->get('decidedAt') ?: $request->get('modifiedAt'),
        ]);
        $this->entityManager->saveEntity($record);
        return $record;
    }

    /** @return null|array{annualEntitlement: float, balanceBeforeRequest: float, nextResetDate: string, automaticAnnualGrantEnabled: bool} */
    private function legacyInputs(Entity $request, string $month): ?array
    {
        $snapshot = $this->array($request->get('approvalSnapshot'));
        if (($snapshot['version'] ?? 1) >= 2 && isset($snapshot['projectionInputs'])) {
            return null;
        }
        $documents = (array) ($snapshot['documents'] ?? []);
        $first = $documents[0] ?? null;
        if (!is_array($first) || !isset($first['values']['balanceBefore'])) {
            throw new RuntimeException('Legacy snapshot does not contain a reliable opening balance.');
        }
        $reservation = $this->entityManager->getRDBRepository('EmployeeHolidayLedger')->where([
            'requestId' => $request->getId(),
            'type' => ['holidayBooked', 'holidayAdjusted'],
        ])->order('createdAt', 'DESC')->findOne();
        if (!$reservation) {
            throw new RuntimeException(
                'Legacy projection is missing the request reservation ledger snapshot; manually confirm and audit the annual entitlement and reset rule before reissuance.'
            );
        }
        $entitlement = (float) $reservation->get('entitlementBefore');
        $entitlementAfter = (float) $reservation->get('entitlementAfter');
        $resetBefore = (string) $reservation->get('resetDateBefore');
        $resetAfter = (string) $reservation->get('resetDateAfter');
        $snapshotTotal = filter_var($first['values']['totalDays'] ?? null, FILTER_VALIDATE_FLOAT);
        if (
            $entitlement <= 0.0 ||
            $entitlementAfter !== $entitlement ||
            $resetBefore === '' ||
            $resetAfter !== $resetBefore ||
            $snapshotTotal === false ||
            (float) $snapshotTotal < $entitlement
        ) {
            throw new RuntimeException(
                'Legacy annual entitlement or scheduled-reset evidence is ambiguous; manually confirm and audit the projection before reissuance.'
            );
        }
        $monthStart = $month . '-01';
        if ($resetBefore > $monthStart || $resetBefore < (string) $request->get('dateStartDate')) {
            throw new RuntimeException(
                'The reservation snapshot does not prove an annual reset before the selected month; manually confirm and audit the projection before reissuance.'
            );
        }
        $configuredReset = $this->config->get('employeeHolidayResetDate');
        if (
            $this->config->get('employeeHolidayAutomaticAnnualGrantEnabled') !== true ||
            !is_string($configuredReset) ||
            !preg_match('/^\d{4}-(\d{2}-\d{2})$/D', $configuredReset, $configuredResetMatch) ||
            substr($resetBefore, 5) !== $configuredResetMatch[1]
        ) {
            throw new RuntimeException(
                'The legacy snapshot proves the scheduled reset date, but the applicable automatic-grant rule cannot be confirmed; document a manual review of the historical configuration before reissuance.'
            );
        }
        return [
            'annualEntitlement' => $entitlement,
            'balanceBeforeRequest' => (float) $first['values']['balanceBefore'],
            'nextResetDate' => $resetBefore,
            'automaticAnnualGrantEnabled' => true,
        ];
    }

    private function request(string $id): Entity
    {
        $request = $this->entityManager->getEntityById(self::REQUEST, $id);
        if (!$request) {
            throw new RuntimeException('Holiday request not found.');
        }
        return $request;
    }

    private function assertEligible(Entity $request, string $month): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/D', $month)) {
            throw new RuntimeException('Month must use YYYY-MM.');
        }
        if (!in_array($request->get('status'), ['Approved', 'CancellationPending'], true)) {
            throw new Conflict('Only an effective or cancellation-pending approval can be reissued.');
        }
        if ($month < substr((string) $request->get('dateStartDate'), 0, 7) ||
            $month > substr((string) $request->get('dateEndDate'), 0, 7) || $this->monthDays($request, $month) < 1) {
            throw new RuntimeException('The selected month is not an approved working-day segment.');
        }
    }

    private function currentAttachment(Entity $request, string $month): Attachment
    {
        $registered = $this->entityManager->getRDBRepository(HolidayDocumentRegistry::ENTITY)->where([
            'requestId' => $request->getId(), 'month' => $month,
            'status' => HolidayDocumentRegistry::STATUS_ISSUED,
        ])->order('version', 'DESC')->findOne();
        if ($registered) {
            $attachment = $this->entityManager->getEntityById('Attachment', (string) $registered->get('attachmentId'));
            if ($attachment instanceof Attachment && $attachment->get('field') === 'approvalDocuments') {
                return $attachment;
            }
        }

        $snapshot = $this->array($request->get('approvalSnapshot'));
        $name = null;
        foreach ((array) ($snapshot['documents'] ?? []) as $document) {
            if (($document['month'] ?? null) === $month) { $name = $document['filename'] ?? null; break; }
        }
        if (!$name) { throw new RuntimeException('Historical document filename is unavailable.'); }
        $matches = [];
        foreach ($this->entityManager->getRDBRepository('Attachment')->where([
            'parentId' => $request->getId(), 'parentType' => self::REQUEST,
            'field' => 'approvalDocuments', 'name' => $name,
        ])->find() as $attachment) { $matches[] = $attachment; }
        if (count($matches) !== 1 || !$matches[0] instanceof Attachment || !$this->storage->exists($matches[0])) {
            throw new RuntimeException('The current historical document is missing or ambiguous.');
        }
        return $matches[0];
    }

    private function monthDays(Entity $request, string $month): int
    {
        $calculation = $this->array($request->get('calculationSnapshot'));
        return (int) (($calculation['monthlyDays'] ?? [])[$month] ?? 0);
    }

    private function array(mixed $value): array
    {
        return json_decode(json_encode($value ?? (object) [], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
