<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendanceAccessChecker;

final class GetOverviewAccess implements Action
{
    public function __construct(private AttendanceAccessChecker $accessChecker)
    {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json(['isManager' => $this->accessChecker->isManager()]);
    }
}
