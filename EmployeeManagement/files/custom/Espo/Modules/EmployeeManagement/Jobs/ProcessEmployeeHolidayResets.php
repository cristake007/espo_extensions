<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayProfileService;

/** Adapted from Holiday 1.9.4 ProcessHolidayResets; unique job name avoids legacy collision. */
final class ProcessEmployeeHolidayResets implements JobDataLess
{
    public function __construct(private HolidayProfileService $service) {}
    public function run(): void { $this->service->processDueResets(); }
}
