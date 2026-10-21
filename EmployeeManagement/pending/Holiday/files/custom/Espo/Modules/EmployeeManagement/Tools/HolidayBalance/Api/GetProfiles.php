<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayBalanceService;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayProfileAccessChecker;

final class GetProfiles implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private HolidayProfileAccessChecker $accessChecker,
    ) {}

    public function process(Request $request): Response
    {
        $this->accessChecker->assertManager();

        return ResponseComposer::json(['list' => $this->service->listProfiles()]);
    }
}
