<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\Attendance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EmployeeManagement\Tools\Attendance\AttendanceOverviewService;

final class PostManagerAttendance implements Action
{
    public function __construct(private AttendanceOverviewService $overviewService)
    {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody() ?? (object) [];

        if (
            !is_string($data->userId ?? null) ||
            !is_string($data->date ?? null) ||
            !is_string($data->status ?? null)
        ) {
            throw new BadRequest('Employee, attendance date and status are required.');
        }

        return ResponseComposer::json($this->overviewService->markForUser(
            $data->userId,
            $data->date,
            $data->status,
        ));
    }
}
