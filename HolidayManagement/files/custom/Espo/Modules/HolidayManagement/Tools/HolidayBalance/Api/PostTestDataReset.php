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
use Espo\Modules\HolidayManagement\Tools\HolidayBalance\HolidayDataResetService;

final class PostTestDataReset implements Action
{
    private const CONFIRMATION = 'RESET HOLIDAY DATA';

    public function __construct(
        private HolidayDataResetService $service,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can reset Holiday Management test data.');
        }

        $data = $request->getParsedBody() ?? (object) [];

        if (($data->confirmation ?? null) !== self::CONFIRMATION) {
            throw new BadRequest('The Holiday Management reset confirmation is invalid.');
        }

        return ResponseComposer::json($this->service->reset());
    }
}
