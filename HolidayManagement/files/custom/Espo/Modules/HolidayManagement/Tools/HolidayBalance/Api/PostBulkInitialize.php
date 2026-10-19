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

final class PostBulkInitialize implements Action
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

        if (!isset($data->items) || !is_array($data->items)) {
            throw new BadRequest($this->language->translateLabel(
                'itemsArrayRequired', 'messages', 'Admin'
            ));
        }

        return ResponseComposer::json(['list' => $this->service->bulkInitialize($data->items)]);
    }
}
