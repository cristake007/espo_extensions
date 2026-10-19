<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\Config;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

final class HolidayDataResetService
{
    private const PROFILE = 'HolidayProfile';
    private const LEDGER = 'HolidayLedger';
    private const REQUEST = 'HolidayRequest';
    private const APPROVAL_DOCUMENT_FIELD = 'approvalDocuments';
    private const SKIP_PROCESSING = [
        'skipHooks' => true,
        'silent' => true,
    ];

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
        private Config $config,
    ) {}

    /** @return array<string, int|bool> */
    public function preview(): array
    {
        return [
            'automaticAnnualGrantEnabled' =>
                $this->config->get('holidayManagementAutomaticAnnualGrantEnabled') === true,
            'profiles' => $this->count($this->initializedProfiles()),
            'requests' => $this->count($this->holidayRequests()),
            'ledgerEntries' => $this->count($this->ledgerEntries()),
            'documents' => $this->count($this->approvalDocuments()),
        ];
    }

    /** @return array<string, int|bool> */
    public function reset(): array
    {
        if ($this->config->get('holidayManagementAutomaticAnnualGrantEnabled') === true) {
            throw new Conflict(
                'Disable automatic annual grants before resetting Holiday Management test data.'
            );
        }

        $before = $this->preview();
        $documents = [];

        foreach ($this->approvalDocuments() as $attachment) {
            $documents[] = $attachment;
        }

        $this->entityManager->getTransactionManager()->run(
            function () use ($documents): void {
                foreach ($documents as $attachment) {
                    $this->entityManager->removeEntity($attachment, self::SKIP_PROCESSING);
                }

                foreach ($this->holidayRequests() as $request) {
                    $this->entityManager->removeEntity($request, self::SKIP_PROCESSING);
                }

                foreach ($this->ledgerEntries() as $entry) {
                    $this->entityManager->removeEntity($entry, self::SKIP_PROCESSING);
                }

                foreach ($this->allProfiles() as $profile) {
                    $this->resetProfile($profile);
                }
            },
        );

        foreach ($documents as $attachment) {
            if ($this->fileStorageManager->exists($attachment)) {
                $this->fileStorageManager->unlink($attachment);
            }
        }

        return $before;
    }

    /** @return iterable<Entity> */
    private function initializedProfiles(): iterable
    {
        return $this->entityManager
            ->getRDBRepository(self::PROFILE)
            ->where(['isInitialized' => true])
            ->find();
    }

    /** @return iterable<Entity> */
    private function allProfiles(): iterable
    {
        return $this->entityManager->getRDBRepository(self::PROFILE)->find();
    }

    /** @return iterable<Entity> */
    private function holidayRequests(): iterable
    {
        return $this->entityManager->getRDBRepository(self::REQUEST)->find();
    }

    /** @return iterable<Entity> */
    private function ledgerEntries(): iterable
    {
        return $this->entityManager->getRDBRepository(self::LEDGER)->find();
    }

    /** @return iterable<Attachment> */
    private function approvalDocuments(): iterable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Attachment::class)
            ->where([
                'parentType' => self::REQUEST,
                'field' => self::APPROVAL_DOCUMENT_FIELD,
            ])
            ->find();
    }

    private function resetProfile(Entity $profile): void
    {
        $profile->set([
            'annualEntitlement' => 0.0,
            'balance' => 0.0,
            'isInitialized' => false,
            'resetPending' => false,
            'pendingResetDate' => null,
            'pendingResetKey' => null,
        ]);
        $this->entityManager->saveEntity($profile, self::SKIP_PROCESSING);
    }

    /** @param iterable<mixed> $items */
    private function count(iterable $items): int
    {
        $count = 0;

        foreach ($items as $_item) {
            $count++;
        }

        return $count;
    }
}
