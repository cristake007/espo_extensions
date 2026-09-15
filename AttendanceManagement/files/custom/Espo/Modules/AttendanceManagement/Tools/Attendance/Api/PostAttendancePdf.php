<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Conflict;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendanceOverviewService;
use Espo\Modules\AttendanceManagement\Tools\Attendance\AttendancePdfGenerator;

final class PostAttendancePdf implements Action
{
    private const MIME_TYPE = 'application/pdf';

    public function __construct(
        private AttendanceOverviewService $overviewService,
        private AttendancePdfGenerator $pdfGenerator,
    ) {}

    public function process(Request $request): Response
    {
        $data = $request->getParsedBody() ?? (object) [];
        $month = is_string($data->month ?? null) ? $data->month : null;
        $overview = $this->overviewService->getOverview($month);

        if (!$overview['downloadReady']) {
            throw new Conflict(
                'The attendance register is incomplete and incomplete exports are disabled.',
            );
        }

        $file = $this->pdfGenerator->generate($overview);

        return ResponseComposer::json([
            'filename' => $file['filename'],
            'mediaType' => self::MIME_TYPE,
            'content' => base64_encode($file['contents']),
        ]);
    }
}
