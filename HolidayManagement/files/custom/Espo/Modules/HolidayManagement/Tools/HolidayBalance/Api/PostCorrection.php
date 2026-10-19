<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Language;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceCorrectionAccessChecker;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;

final class PostCorrection implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private HolidayBalanceCorrectionAccessChecker $accessChecker,
        private Language $language,
    ) {}

    public function process(Request $request): Response
    {
        $this->accessChecker->assertManager();

        $data = $request->getParsedBody() ?? (object) [];

        if (!is_string($data->profileId ?? null) || !is_numeric($data->delta ?? null)) {
            throw new BadRequest($this->language->translateLabel(
                'correctionPayloadRequired', 'messages', 'Admin'
            ));
        }

        return ResponseComposer::json($this->service->correct(
            $data->profileId,
            (float) $data->delta,
            is_string($data->reason ?? null) ? $data->reason : '',
            is_string($data->idempotencyKey ?? null) ? $data->idempotencyKey : '',
        ));
    }
}
