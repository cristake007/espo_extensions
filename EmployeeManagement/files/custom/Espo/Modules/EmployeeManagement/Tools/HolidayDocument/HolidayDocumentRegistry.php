<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument;

use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

/** Protected, append-only identity metadata for issued Holiday DOCX versions. */
final class HolidayDocumentRegistry
{
    public const ENTITY = 'EmployeeHolidayDocument';
    public const STATUS_PREPARED = 'Prepared';
    public const STATUS_ISSUED = 'Issued';
    public const STATUS_SUPERSEDED = 'Superseded';

    public function __construct(private EntityManager $entityManager) {}

    public function registerApproval(
        Entity $request,
        Attachment $attachment,
        array $document,
        array $snapshot,
        string $sha256,
    ): Entity {
        $code = (string) ($document['verificationCode'] ?? '');

        // Version-1 snapshots created before 0.8.13 intentionally remain
        // compatible legacy documents and are not claimed as verified.
        if ($code === '') {
            throw new RuntimeException('Legacy approval documents cannot be registered as cryptographically verified.');
        }

        $this->assertCode($code);
        $issueKey = hash('sha256', (string) $request->get('approvalOperationKey') . ':' . $document['month']);
        $existing = $this->entityManager->getRDBRepository(self::ENTITY)
            ->where(['issueKey' => $issueKey])->findOne();

        if ($existing) {
            if (
                $existing->get('attachmentId') !== $attachment->getId() ||
                !hash_equals((string) $existing->get('sha256'), $sha256) ||
                !hash_equals((string) $existing->get('verificationCodeHash'), hash('sha256', $code))
            ) {
                throw new RuntimeException('The approval document identity conflicts with an existing issuance.');
            }

            return $existing;
        }

        $record = $this->entityManager->getNewEntity(self::ENTITY);
        $record->set([
            'name' => 'Document concediu ' . $document['month'] . ' v1',
            'issueKey' => $issueKey,
            'verificationCode' => $code,
            'verificationCodeHash' => hash('sha256', $code),
            'sha256' => $sha256,
            'requestId' => $request->getId(),
            'requestName' => $request->get('name'),
            'attachmentId' => $attachment->getId(),
            'attachmentName' => $attachment->getName(),
            'month' => $document['month'],
            'version' => 1,
            'status' => self::STATUS_ISSUED,
            'issueType' => 'Approval',
            'employeeName' => $request->get('assignedUserName'),
            'dateStart' => $request->get('dateStartDate'),
            'dateEnd' => $request->get('dateEndDate'),
            'workingDays' => (int) $document['values']['requestedDays'],
            'approvedById' => $snapshot['decidedById'],
            'approvedByName' => $snapshot['decidedByName'],
            'approvedAt' => $snapshot['decidedAt'],
            'issuedAt' => \Espo\Core\Utils\DateTime::getSystemNowString(),
        ]);
        $this->entityManager->saveEntity($record);

        return $record;
    }

    public function findByCode(string $code): ?Entity
    {
        $this->assertCode($code);

        return $this->entityManager->getRDBRepository(self::ENTITY)
            ->where(['verificationCodeHash' => hash('sha256', $code)])
            ->findOne();
    }

    public function assertCode(string $code): void
    {
        if (!preg_match('/^[A-F0-9]{32}$/D', $code)) {
            throw new RuntimeException('Invalid document verification code.');
        }
    }
}
