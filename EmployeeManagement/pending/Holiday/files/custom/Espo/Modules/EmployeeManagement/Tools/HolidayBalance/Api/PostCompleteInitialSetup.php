<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Language;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayBalanceService;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayProfileAccessChecker;

final class PostCompleteInitialSetup implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private HolidayProfileAccessChecker $accessChecker,
        private Language $language,
    ) {}

    public function process(Request $request): Response
    {
        $this->accessChecker->assertManager();
        $data = $request->getParsedBody() ?? (object) [];

        if (
            !is_string($data->profileId ?? null) ||
            !is_numeric($data->openingBalance ?? null)
        ) {
            throw new BadRequest($this->language->translateLabel(
                'initialSetupPayloadRequired', 'messages', 'Admin'
            ));
        }

        return ResponseComposer::json($this->service->completeInitialSetup(
            $data->profileId,
            (float) $data->openingBalance,
            is_string($data->idempotencyKey ?? null) ? $data->idempotencyKey : '',
        ));
    }
}
