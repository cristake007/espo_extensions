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

final class PostReset implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can process holiday resets.');
        }

        $data = $request->getParsedBody() ?? (object) [];

        if (!is_string($data->profileId ?? null)) {
            throw new BadRequest('Profile ID is required.');
        }

        return ResponseComposer::json($this->service->reset(
            $data->profileId,
            is_string($data->idempotencyKey ?? null) ? $data->idempotencyKey : '',
            (bool) ($data->force ?? false),
            is_string($data->reason ?? null) ? $data->reason : null,
        ));
    }
}
