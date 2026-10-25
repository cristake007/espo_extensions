<?php

namespace Espo\Modules\ManagementAngajati\Hooks\User;

use Espo\Core\Hook\Hook\AfterRelate;
use Espo\Core\Hook\Hook\AfterUnrelate;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SincronizatorEchipeCereriConcediu as Sincronizator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RelateOptions;
use Espo\ORM\Repository\Option\UnrelateOptions;

/** @implements AfterRelate<User> @implements AfterUnrelate<User> */
class SincronizeazaEchipeCereriConcediu implements AfterRelate, AfterUnrelate
{
    public function __construct(private Sincronizator $sincronizator) {}

    public function afterRelate(
        Entity $entity,
        string $relationName,
        Entity $relatedEntity,
        array $columnData,
        RelateOptions $options,
    ): void {
        $this->sincronizeaza($entity, $relationName);
    }

    public function afterUnrelate(
        Entity $entity,
        string $relationName,
        Entity $relatedEntity,
        UnrelateOptions $options,
    ): void {
        $this->sincronizeaza($entity, $relationName);
    }

    private function sincronizeaza(Entity $entity, string $relationName): void
    {
        if ($relationName === 'teams' && $entity instanceof User) {
            $this->sincronizator->sincronizeaza($entity);
        }
    }
}
