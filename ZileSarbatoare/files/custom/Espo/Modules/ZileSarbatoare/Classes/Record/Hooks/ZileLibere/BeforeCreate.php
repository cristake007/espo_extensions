<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Classes\Record\Hooks\ZileLibere;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\Hook\CreateHook;
use Espo\Entities\User;
use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ManualRecordPolicy;
use Espo\ORM\Entity;
use InvalidArgumentException;

final class BeforeCreate implements CreateHook
{
    public function __construct(
        private User $user,
        private ManualRecordPolicy $manualRecordPolicy,
    ) {}

    public function process(Entity $entity, CreateParams $params): void
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can create Zile libere records.');
        }

        try {
            $this->manualRecordPolicy->normalize($entity);
        } catch (InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }
    }
}
