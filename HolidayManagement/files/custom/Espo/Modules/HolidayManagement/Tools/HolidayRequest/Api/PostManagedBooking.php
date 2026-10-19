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
use Espo\Modules\HolidayManagement\Tools\HolidayRequest\HolidayRequestManagerAccessChecker;

final class PostManagedBooking implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private HolidayRequestManagerAccessChecker $accessChecker,
        private Language $language,
    ) {}

    public function process(Request $request): Response
    {
        $this->accessChecker->assertManager();
        $data = $request->getParsedBody() ?? (object) [];

        if (
            !is_string($data->userId ?? null) ||
            !is_string($data->dateStart ?? null) ||
            !is_string($data->dateEnd ?? null) ||
            !is_string($data->description ?? '')
        ) {
            throw new BadRequest($this->language->translateLabel(
                'managedBookingFieldsRequired',
                'messages',
                'HolidayRequest',
            ));
        }

        return ResponseComposer::json($this->service->createManagedBooking(
            $data->userId,
            $data->dateStart,
            $data->dateEnd,
            $data->description ?? '',
        ));
    }
}
