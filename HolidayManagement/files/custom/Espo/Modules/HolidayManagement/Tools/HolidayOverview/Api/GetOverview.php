<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayOverview\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\HolidayManagement\Tools\HolidayOverview\HolidayOverviewService;

final class GetOverview implements Action
{
    public function __construct(private HolidayOverviewService $service) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json(
            $this->service->get($request->getQueryParam('month')),
        );
    }
}
