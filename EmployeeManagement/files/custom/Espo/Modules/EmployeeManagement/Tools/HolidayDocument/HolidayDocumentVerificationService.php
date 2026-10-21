<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayDocument;

use DateTimeImmutable;
use Espo\Core\Acl;
use Espo\Core\Exceptions\{BadRequest, Forbidden};
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\Log;
use Espo\Entities\{Attachment, User};
use Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidayApproverProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Manager-only lookup and optional byte-for-byte DOCX integrity comparison. */
final class HolidayDocumentVerificationService
{
    private const REQUEST = 'EmployeeHolidayRequest';
    private const AUDIT = 'EmployeeHolidayDocumentVerificationLog';
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    private const RATE_LIMIT = 20;
    private const RATE_WINDOW = '-5 minutes';

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $storage,
        private HolidayDocumentRegistry $registry,
        private HolidayApproverProvider $approvers,
        private User $user,
        private Acl $acl,
        private Log $log,
    ) {}

    /** @param array<string, mixed> $data */
    public function verify(array $data): array
    {
        $this->assertManager();
        $this->assertRateLimit();
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if (!preg_match('/^[A-F0-9]{32}$/D', $code)) {
            throw new BadRequest('Codul de verificare trebuie să conțină exact 32 de caractere hexazecimale.');
        }

        $uploaded = $this->uploadedContents($data);
        $document = $this->registry->findByCode($code);

        if (!$document || $document->get('status') === HolidayDocumentRegistry::STATUS_PREPARED) {
            $this->audit($code, null, null, 'Unknown', $uploaded !== null);
            return $this->unknown($uploaded !== null);
        }

        $request = $this->entityManager->getEntityById(self::REQUEST, (string) $document->get('requestId'));
        $attachment = $this->entityManager->getEntityById('Attachment', (string) $document->get('attachmentId'));

        if (
            !$request ||
            !$attachment instanceof Attachment ||
            !$this->approvers->canReadApprovalRequest($this->user, $request) ||
            !$this->acl->checkField(self::REQUEST, 'approvalDocuments', 'read') ||
            !$this->acl->checkEntity($attachment, 'read')
        ) {
            // Do not disclose whether a valid code belongs to an inaccessible record.
            $this->audit($code, $document, $request, 'Denied', $uploaded !== null);
            return $this->unknown($uploaded !== null);
        }

        $integrity = 'NotChecked';
        if ($uploaded !== null) {
            $integrity = hash_equals((string) $document->get('sha256'), hash('sha256', $uploaded))
                ? 'Match'
                : 'Modified';
        }

        $validity = $this->validity($document, $request);
        $outcome = $integrity === 'Modified' ? 'Modified' : $validity;
        $this->audit($code, $document, $request, $outcome, $uploaded !== null);

        if ($integrity === 'Modified') {
            $this->log->warning('Holiday document verification detected modified bytes.', [
                'holidayDocumentId' => $document->getId(),
                'holidayRequestId' => $request->getId(),
                'actorUserId' => $this->user->getId(),
            ]);
        }

        return [
            'found' => true,
            'reference' => $code,
            'employeeName' => (string) $document->get('employeeName'),
            'dateStart' => (string) $document->get('dateStart'),
            'dateEnd' => (string) $document->get('dateEnd'),
            'month' => (string) $document->get('month'),
            'workingDays' => (int) $document->get('workingDays'),
            'approvedByName' => (string) $document->get('approvedByName'),
            'approvedAt' => $document->get('approvedAt'),
            'issuedAt' => $document->get('issuedAt'),
            'requestStatus' => (string) $request->get('status'),
            'documentValidity' => $validity,
            'integrity' => $integrity,
            'attachmentName' => (string) $attachment->getName(),
        ];
    }

    private function assertManager(): void
    {
        if (
            !$this->user->isRegular() ||
            !$this->user->isActive() ||
            !$this->acl->checkScope('EmployeeManagement') ||
            !$this->approvers->isApprover($this->user)
        ) {
            throw new Forbidden('Holiday document verification requires effective approval permission.');
        }
    }

    private function assertRateLimit(): void
    {
        $since = (new DateTimeImmutable(self::RATE_WINDOW))->format('Y-m-d H:i:s');
        $count = $this->entityManager->getRDBRepository(self::AUDIT)->where([
            'actorId' => $this->user->getId(),
            'createdAt>=' => $since,
        ])->count();

        if ($count >= self::RATE_LIMIT) {
            throw new \Espo\Core\Exceptions\Conflict('Prea multe verificări. Încercați din nou peste câteva minute.');
        }
    }

    /** @param array<string, mixed> $data */
    private function uploadedContents(array $data): ?string
    {
        $encoded = $data['fileContents'] ?? null;

        if ($encoded === null || $encoded === '') {
            return null;
        }

        if (!is_string($encoded) || strlen($encoded) > (int) ceil(self::MAX_FILE_SIZE * 4 / 3) + 16) {
            throw new BadRequest('Fișierul depășește limita de 10 MB.');
        }

        $name = (string) ($data['fileName'] ?? '');
        if (!preg_match('/\.docx$/iD', $name)) {
            throw new BadRequest('Este acceptat numai un fișier DOCX.');
        }

        $contents = base64_decode($encoded, true);
        if ($contents === false || strlen($contents) > self::MAX_FILE_SIZE || !str_starts_with($contents, "PK")) {
            throw new BadRequest('Fișierul DOCX este invalid sau prea mare.');
        }

        return $contents;
    }

    private function validity(Entity $document, Entity $request): string
    {
        if ($document->get('status') === HolidayDocumentRegistry::STATUS_SUPERSEDED) {
            return 'Superseded';
        }

        return match ($request->get('status')) {
            'Approved' => 'Valid',
            'Cancelled' => 'Revoked',
            'CancellationPending' => 'CancellationPending',
            default => 'NotEffective',
        };
    }

    private function audit(
        string $code,
        ?Entity $document,
        ?Entity $request,
        string $outcome,
        bool $fileProvided,
    ): void {
        $entry = $this->entityManager->getNewEntity(self::AUDIT);
        $entry->set([
            'name' => 'Verificare document concediu',
            'actorId' => $this->user->getId(),
            'actorName' => $this->user->getName(),
            'documentId' => $document?->getId(),
            'requestId' => $request?->getId(),
            'codeFingerprint' => substr(hash('sha256', $code), 0, 16),
            'outcome' => $outcome,
            'fileProvided' => $fileProvided,
        ]);
        $this->entityManager->saveEntity($entry);
    }

    private function unknown(bool $fileProvided): array
    {
        return [
            'found' => false,
            'documentValidity' => 'Unknown',
            'integrity' => $fileProvided ? 'Unknown' : 'NotChecked',
        ];
    }
}
