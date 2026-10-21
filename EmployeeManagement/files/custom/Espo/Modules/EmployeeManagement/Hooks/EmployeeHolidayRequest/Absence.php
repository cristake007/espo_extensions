<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Hooks\EmployeeHolidayRequest;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Modules\EmployeeManagement\Tools\Calendar\EmployeeAbsenceService;

/** Native ORM hook, in the same transaction as the authorized Holiday command/worker. */
final class Absence implements AfterSave
{
    public static int $order = 20;
    public function __construct(private EmployeeAbsenceService $service) {}
    public function afterSave(Entity $entity, SaveOptions $options): void { $this->service->sync($entity); }
}
