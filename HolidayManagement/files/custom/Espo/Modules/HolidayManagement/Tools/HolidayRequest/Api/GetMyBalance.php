<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayRequest\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Core\Utils\Language;
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayBalanceService;

final class GetMyBalance implements Action
{
    public function __construct(
        private HolidayBalanceService $balanceService,
        private User $user,
        private Language $language,
    ) {}

    public function process(Request $request): Response
    {
        if (
            !(bool) $this->user->get('isActive') ||
            !in_array($this->user->get('type'), [User::TYPE_REGULAR, User::TYPE_ADMIN], true)
        ) {
            throw new Forbidden($this->language->translateLabel(
                'balanceAccessForbidden', 'messages', 'HolidayRequest'
            ));
        }

        return ResponseComposer::json($this->balanceService->getMyBalance());
    }
}
