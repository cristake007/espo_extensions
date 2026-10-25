<?php

namespace Espo\Modules\ManagementAngajati\Hooks\Team;

use Espo\Core\Hook\Hook\AfterRelate;
use Espo\Core\Hook\Hook\AfterUnrelate;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Concedii\SincronizatorEchipeCereriConcediu as Sincronizator;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RelateOptions;
use Espo\ORM\Repository\Option\UnrelateOptions;

/** @implements AfterRelate<Entity> @implements AfterUnrelate<Entity> */
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
        $this->sincronizeaza($relationName, $relatedEntity);
    }

    public function afterUnrelate(
        Entity $entity,
        string $relationName,
        Entity $relatedEntity,
        UnrelateOptions $options,
    ): void {
        $this->sincronizeaza($relationName, $relatedEntity);
    }

    private function sincronizeaza(string $relationName, Entity $relatedEntity): void
    {
        if ($relationName === 'users' && $relatedEntity instanceof User) {
            $this->sincronizator->sincronizeaza($relatedEntity);
        }
    }
}
