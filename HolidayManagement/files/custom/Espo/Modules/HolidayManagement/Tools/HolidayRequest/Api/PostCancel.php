<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayRequest\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Language;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;

final class PostCancel implements Action
{
    public function __construct(
        private HolidayBalanceService $balanceService,
        private Language $language,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $data = $request->getParsedBody() ?? (object) [];

        if (!is_string($id) || $id === '') {
            throw new BadRequest($this->language->translateLabel(
                'requestIdRequired', 'messages', 'HolidayRequest'
            ));
        }

        if (!is_string($data->reason ?? null) || trim($data->reason) === '') {
            throw new BadRequest($this->language->translateLabel(
                'cancellationReasonRequired', 'messages', 'HolidayRequest'
            ));
        }

        return ResponseComposer::json(
            $this->balanceService->cancelApprovedHoliday($id, $data->reason),
        );
    }
}
