<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Language;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayProfileAccessChecker;

final class GetCorrectionHistory implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private HolidayProfileAccessChecker $accessChecker,
        private Language $language,
    ) {}

    public function process(Request $request): Response
    {
        $this->accessChecker->assertManager();
        $profileId = $request->getRouteParam('id');

        if (!is_string($profileId) || $profileId === '') {
            throw new BadRequest($this->language->translateLabel(
                'profileIdRequired', 'messages', 'Admin'
            ));
        }

        return ResponseComposer::json([
            'list' => $this->service->listCorrections($profileId),
        ]);
    }
}
