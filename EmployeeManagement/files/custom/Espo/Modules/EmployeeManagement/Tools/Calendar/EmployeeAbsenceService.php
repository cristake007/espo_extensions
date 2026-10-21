<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Calendar;

use Espo\Entities\User;
use Espo\ORM\{Entity, EntityManager};

/** Synchronous read projection. Callers own the native source transaction/User lock. */
final class EmployeeAbsenceService
{
    public function __construct(private EntityManager $em) {}

    public function sync(Entity $request): void
    {
        $query = $this->em->getQueryBuilder()->select()->from('EmployeeAbsence')->withDeleted()
            ->where(['sourceId' => $request->getId()])->select(['id', 'sourceId', 'deleted'])->build();
        $existing = $this->em->getRDBRepository('EmployeeAbsence')->clone($query)->findOne();
        $owner = $this->em->getEntityById('User', (string) $request->get('assignedUserId'));
        $effective = in_array($request->get('status'), ['Approved', 'CancellationPending'], true);
        if (!$effective) {
            if ($existing && !$existing->get('deleted')) { $this->em->removeEntity($existing); }
            return;
        }
        // Retain an existing projection during technical deactivation/type changes, but never create one
        // for an ineligible target. Native Select + record ACL hide it; reactivation needs no User hook.
        if (!$owner instanceof User || !$owner->isRegular() || !$owner->isActive()) { return; }
        if ($existing?->get('deleted')) {
            $this->em->getRDBRepository('EmployeeAbsence')->restoreDeleted($existing->getId());
        }
        $absence = $existing ?? $this->em->getNewEntity('EmployeeAbsence');
        $absence->set([
            'deleted' => false,
            'sourceId' => $request->getId(), 'name' => $owner->get('name'),
            'assignedUserId' => $owner->getId(),
            'dateStart' => $request->get('dateStartDate'), 'dateEnd' => $request->get('dateEndDate'),
        ]);
        $this->em->saveEntity($absence);
    }

    /** Upgrade/backfill only; safe to repeat, no balance/document recalculation. */
    public function rebuild(): void
    {
        foreach ($this->em->getRDBRepository('EmployeeHolidayRequest')
            ->select(['id', 'assignedUserId'])->find() as $seed) {
            $this->em->getTransactionManager()->run(function () use ($seed): void {
                $this->em->getRDBRepository('User')->where(['id' => $seed->get('assignedUserId')])
                    ->select(['id'])->forUpdate()->findOne();
                $request = $this->em->getRDBRepository('EmployeeHolidayRequest')->where(['id' => $seed->getId()])
                    ->select(['id', 'assignedUserId', 'status', 'dateStartDate', 'dateEndDate'])->forUpdate()->findOne();
                if ($request) { $this->sync($request); }
            });
        }
    }
}
