<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Classes\Record\Hooks\ZileLibere;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\Hook\DeleteHook;
use Espo\Entities\User;
use Espo\Modules\ZileSarbatoare\Entities\ZileLibere;
use Espo\ORM\Entity;

final class BeforeDelete implements DeleteHook
{
    public function __construct(private User $user)
    {}

    public function process(Entity $entity, DeleteParams $params): void
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can delete Zile libere records.');
        }

        if (
            (bool) $entity->get('managed') ||
            $entity->get('source') === ZileLibere::SOURCE_NAGER_DATE
        ) {
            throw new Forbidden('Synchronized Zile libere records cannot be deleted manually.');
        }
    }
}
