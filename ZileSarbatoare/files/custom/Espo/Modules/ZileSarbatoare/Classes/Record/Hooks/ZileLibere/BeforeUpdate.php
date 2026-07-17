<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Classes\Record\Hooks\ZileLibere;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\Entities\User;
use Espo\Modules\ZileSarbatoare\Entities\ZileLibere;
use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ManualRecordPolicy;
use Espo\ORM\Entity;
use InvalidArgumentException;

final class BeforeUpdate implements UpdateHook
{
    public function __construct(
        private User $user,
        private ManualRecordPolicy $manualRecordPolicy,
    ) {}

    public function process(Entity $entity, UpdateParams $params): void
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can update Zile libere records.');
        }

        if (
            (bool) $entity->getFetched('managed') ||
            $entity->getFetched('source') === ZileLibere::SOURCE_NAGER_DATE
        ) {
            throw new Forbidden('Synchronized Zile libere records are read-only.');
        }

        try {
            $this->manualRecordPolicy->normalize($entity);
        } catch (InvalidArgumentException $e) {
            throw new BadRequest($e->getMessage());
        }
    }
}
