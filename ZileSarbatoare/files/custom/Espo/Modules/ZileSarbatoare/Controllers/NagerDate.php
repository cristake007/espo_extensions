<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\ZileSarbatoare\Services\NagerDate as Service;
use InvalidArgumentException;
use stdClass;

final class NagerDate
{
    /** @throws Forbidden */
    public function __construct(
        private Service $service,
        User $user,
    ) {
        if (!$user->isAdmin()) {
            throw new Forbidden();
        }
    }

    /** @throws BadRequest */
    public function postActionSaveSettings(Request $request): stdClass
    {
        try {
            return $this->service->saveSettings($request->getParsedBody());
        } catch (InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }
    }

    public function getActionStatus(): stdClass
    {
        return $this->service->status();
    }

    public function postActionSynchronize(): stdClass
    {
        return $this->service->synchronize();
    }
}
