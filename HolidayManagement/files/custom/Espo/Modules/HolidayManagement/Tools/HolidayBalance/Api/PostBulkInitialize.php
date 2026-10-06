<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayBalance\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Core\Utils\Language;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;

final class PostBulkInitialize implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private User $user,
        private Language $language,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden($this->language->translateLabel(
                'profilesAccessForbidden', 'messages', 'Admin'
            ));
        }

        $data = $request->getParsedBody() ?? (object) [];

        if (!isset($data->items) || !is_array($data->items)) {
            throw new BadRequest($this->language->translateLabel(
                'itemsArrayRequired', 'messages', 'Admin'
            ));
        }

        return ResponseComposer::json(['list' => $this->service->bulkInitialize($data->items)]);
    }
}
