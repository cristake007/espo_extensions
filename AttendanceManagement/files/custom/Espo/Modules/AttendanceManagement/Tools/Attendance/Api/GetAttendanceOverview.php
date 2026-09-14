<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendanceOverviewService;

final class GetAttendanceOverview implements Action
{
    public function __construct(private AttendanceOverviewService $overviewService)
    {}

    public function process(Request $request): Response
    {
        $month = $request->getQueryParam('month');

        return ResponseComposer::json(
            $this->overviewService->getOverview(is_string($month) ? $month : null),
        );
    }
}
