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

final class PostCorrection implements Action
{
    public function __construct(
        private HolidayBalanceService $service,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can correct holiday balances.');
        }

        $data = $request->getParsedBody() ?? (object) [];

        if (!is_string($data->profileId ?? null) || !is_numeric($data->delta ?? null)) {
            throw new BadRequest('Profile ID and numeric delta are required.');
        }

        return ResponseComposer::json($this->service->correct(
            $data->profileId,
            (float) $data->delta,
            is_string($data->reason ?? null) ? $data->reason : '',
            is_string($data->idempotencyKey ?? null) ? $data->idempotencyKey : '',
        ));
    }
}
