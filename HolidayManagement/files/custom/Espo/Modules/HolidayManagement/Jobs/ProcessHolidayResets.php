<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;

final class ProcessHolidayResets implements JobDataLess
{
    public function __construct(private HolidayBalanceService $service)
    {}

    public function run(): void
    {
        $this->service->processDueResets();
    }
}
