<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Attendance;

use Espo\Core\Exceptions\Conflict;
use Espo\ORM\{Entity, EntityManager};

/** D8 integration of the existing daily statuses; no Holiday accounting or state transition.
 * Commands/approval finalizer must hold the beneficiary User and affected month locks.
 * The future Job must repeat this check in its finalization transaction, not before rendering.
 */
final class HolidayAttendanceGuard
{
    public function __construct(private EntityManager $entityManager) {}

    public function assertCompatible(Entity $request): void
    {
        $conflict = $this->entityManager->getRDBRepository('EmployeeAttendanceRecord')->where([
            'assignedUserId' => $request->get('assignedUserId'),
            'date>=' => $request->get('dateStartDate'),
            'date<=' => $request->get('dateEndDate'),
            'status' => ['AtWork', 'BusinessTrip'],
        ])->order('date')->select(['id','date'])->forUpdate()->findOne();
        if ($conflict) {
            // Only the authorized request's beneficiary/date; no colleague record or private field returned.
            throw new Conflict('Holiday conflicts with worked/business-trip attendance on ' . $conflict->get('date') . '. Resolve the attendance explicitly before booking or approval.');
        }
    }
}
