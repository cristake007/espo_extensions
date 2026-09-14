<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendanceService;

final class PostMarkAttendance implements Action
{
    public function __construct(private AttendanceService $attendanceService)
    {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody() ?? (object) [];

        if (!is_string($data->date ?? null) || !is_string($data->status ?? null)) {
            throw new BadRequest('Attendance date and status are required.');
        }

        return ResponseComposer::json(
            $this->attendanceService->mark($data->date, $data->status),
        );
    }
}
