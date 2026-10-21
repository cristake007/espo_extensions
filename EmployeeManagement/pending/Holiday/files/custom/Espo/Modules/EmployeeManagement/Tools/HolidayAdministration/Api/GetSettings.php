<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidaySettingsService;

final class GetSettings implements Action
{
    public function __construct(private HolidaySettingsService $service) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json($this->service->get());
    }
}
