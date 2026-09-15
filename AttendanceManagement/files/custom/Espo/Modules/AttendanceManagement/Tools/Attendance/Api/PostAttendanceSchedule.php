<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendanceOverviewService;

final class PostAttendanceSchedule implements Action
{
    public function __construct(private AttendanceOverviewService $overviewService)
    {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody() ?? (object) [];

        if (
            !is_string($data->userId ?? null) ||
            !is_string($data->startTime ?? null) ||
            !is_string($data->endTime ?? null)
        ) {
            throw new BadRequest('Employee, start time and end time are required.');
        }

        return ResponseComposer::json($this->overviewService->saveSchedule(
            $data->userId,
            $data->startTime,
            $data->endTime,
        ));
    }
}
