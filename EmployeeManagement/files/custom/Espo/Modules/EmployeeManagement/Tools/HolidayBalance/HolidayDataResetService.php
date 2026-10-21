<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\Config;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Modules\EmployeeManagement\Jobs\GenerateHolidayApproval;

/** Destructive test-data reset adapted from Holiday Management 1.9.4. */
final class HolidayDataResetService
{
    private const PROFILE = 'EmployeeHolidayProfile';
    private const LEDGER = 'EmployeeHolidayLedger';
    private const REQUEST = 'EmployeeHolidayRequest';
    private const ABSENCE = 'EmployeeAbsence';
    private const NOTIFICATION = 'Notification';
    private const SKIP_PROCESSING = ['skipHooks' => true, 'silent' => true];

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
                $this->config->get('employeeHolidayAutomaticAnnualGrantEnabled') === true,
            'profiles' => $this->count($this->initializedProfiles()),
            'requests' => $this->count($this->holidayRequests()),
            'ledgerEntries' => $this->count($this->ledgerEntries()),
            'documents' => $this->count($this->approvalDocuments()),
            'notifications' => $this->count($this->holidayNotifications()),
            'absences' => $this->count($this->absenceProjections()),
            'approvalJobs' => $this->count($this->approvalJobs()),
            'activeApprovalJobs' => $this->count($this->activeApprovalJobs()),
        ];
    }

    /** @return array<string, int|bool> */
    public function reset(): array
    {
        if ($this->config->get('employeeHolidayAutomaticAnnualGrantEnabled') === true) {
            throw new Conflict(
                'Disable automatic annual grants before resetting Employee Management Holiday test data.'
            );
        }

        if ($this->count($this->activeApprovalJobs()) > 0) {
            throw new Conflict(
                'Wait for active Holiday approval Jobs to finish before resetting test data.'
            );
        }

        $before = $this->preview();
        $documents = [];

        foreach ($this->approvalDocuments() as $attachment) {
            $documents[] = $attachment;
        }

        $this->entityManager->getTransactionManager()->run(
            function () use ($documents): void {
                // Recheck inside the transaction so a newly active worker cannot be ignored.
                if ($this->count($this->activeApprovalJobs()) > 0) {
                    throw new Conflict(
                        'Wait for active Holiday approval Jobs to finish before resetting test data.'
                    );
                }

                foreach ($documents as $attachment) {
                    $this->entityManager->removeEntity($attachment, self::SKIP_PROCESSING);
                }

                foreach ($this->absenceProjections() as $absence) {
                    $this->entityManager->removeEntity($absence, self::SKIP_PROCESSING);
                }

                foreach ($this->approvalJobs() as $job) {
                    $this->entityManager->removeEntity($job, self::SKIP_PROCESSING);
                }

                foreach ($this->holidayRequests() as $request) {
                    $this->entityManager->removeEntity($request, self::SKIP_PROCESSING);
                }

                foreach ($this->ledgerEntries() as $entry) {
                    $this->entityManager->removeEntity($entry, self::SKIP_PROCESSING);
                }

                foreach ($this->holidayNotifications() as $notification) {
                    $this->entityManager->removeEntity($notification, self::SKIP_PROCESSING);
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

    /** @return iterable<Entity> */
    private function absenceProjections(): iterable
    {
        return $this->entityManager->getRDBRepository(self::ABSENCE)->find();
    }

    /** @return iterable<Entity> */
    private function holidayNotifications(): iterable
    {
        $notifications = $this->entityManager
            ->getRDBRepository(self::NOTIFICATION)
            ->where(['relatedType' => [self::PROFILE, self::REQUEST]])
            ->find();

        foreach ($notifications as $notification) {
            $data = $notification->get('data');
            $data = is_object($data)
                ? get_object_vars($data)
                : (is_array($data) ? $data : []);

            if (
                $notification->get('relatedType') === self::PROFILE ||
                in_array(
                    $data['employeeHolidayType'] ?? null,
                    ['balanceWarning', 'decision'],
                    true,
                )
            ) {
                yield $notification;
            }
        }
    }

    /** @return iterable<Entity> */
    private function approvalJobs(): iterable
    {
        return $this->entityManager
            ->getRDBRepository('Job')
            ->where([
                'targetType' => self::REQUEST,
                'className' => GenerateHolidayApproval::class,
            ])
            ->find();
    }

    /** @return iterable<Entity> */
    private function activeApprovalJobs(): iterable
    {
        return $this->entityManager
            ->getRDBRepository('Job')
            ->where([
                'targetType' => self::REQUEST,
                'className' => GenerateHolidayApproval::class,
                'status' => ['Pending', 'Ready', 'Running'],
            ])
            ->find();
    }

    /** @return iterable<Attachment> */
    private function approvalDocuments(): iterable
    {
        return $this->entityManager
            ->getRDBRepositoryByClass(Attachment::class)
            ->where([
                'parentType' => self::REQUEST,
                'field' => ['approvalDocuments', 'approvalStaging'],
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
        foreach ($items as $_item) { $count++; }
        return $count;
    }
}
