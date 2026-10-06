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

final class PostReset implements Action
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
                'resetAccessForbidden', 'messages', 'Admin'
            ));
        }

        $data = $request->getParsedBody() ?? (object) [];

        if (!is_string($data->profileId ?? null)) {
            throw new BadRequest($this->language->translateLabel(
                'profileIdRequired', 'messages', 'Admin'
            ));
        }

        return ResponseComposer::json($this->service->reset(
            $data->profileId,
            is_string($data->idempotencyKey ?? null) ? $data->idempotencyKey : '',
            (bool) ($data->force ?? false),
            is_string($data->reason ?? null) ? $data->reason : null,
        ));
    }
}
