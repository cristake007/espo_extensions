<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\EmployeeHolidayRequest\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayBalanceService;

final class GetApprovalQueue implements Action
{
    public function __construct(private HolidayBalanceService $balanceService)
    {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json($this->balanceService->listPendingApprovals());
    }
}
