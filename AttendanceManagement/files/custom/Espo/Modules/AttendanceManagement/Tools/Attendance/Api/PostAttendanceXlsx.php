<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Conflict;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendanceOverviewService;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendanceXlsxGenerator;

final class PostAttendanceXlsx implements Action
{
    private const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private AttendanceOverviewService $overviewService,
        private AttendanceXlsxGenerator $xlsxGenerator,
    ) {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody() ?? (object) [];
        $month = is_string($data->month ?? null) ? $data->month : null;
        $overview = $this->overviewService->getOverview($month);

        if (!$overview['downloadReady']) {
            throw new Conflict('The attendance register can only be downloaded after every employee has completed it.');
        }

        $file = $this->xlsxGenerator->generate($overview);

        return ResponseComposer::json([
            'filename' => $file['filename'],
            'mediaType' => self::MIME_TYPE,
            'content' => base64_encode($file['contents']),
        ]);
    }
}
