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
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;

final class PostBulkInitialize implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can manage holiday profiles.');
        }

        $data = $request->getParsedBody() ?? (object) [];

        if (!isset($data->items) || !is_array($data->items)) {
            throw new BadRequest('An items array is required.');
        }

        return ResponseComposer::json(['list' => $this->service->bulkInitialize($data->items)]);
    }
}
