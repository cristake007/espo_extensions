<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayRequest\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;
use Espo\Modules\HolidayManagement\Tools\HolidayRequest\HolidayRequestManagerAccessChecker;

final class GetManagedBookingUsers implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private HolidayRequestManagerAccessChecker $accessChecker,
    ) {}

    public function process(Request $request): Response
    {
        $this->accessChecker->assertManager();

        return ResponseComposer::json(['list' => $this->service->listManagedBookingUsers()]);
    }
}
