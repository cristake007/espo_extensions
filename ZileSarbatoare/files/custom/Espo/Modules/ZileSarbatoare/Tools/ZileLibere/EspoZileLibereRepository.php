<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere;

use Espo\Modules\ZileSarbatoare\Entities\ZileLibere;
use Espo\ORM\EntityManager;

final class EspoZileLibereRepository implements ZileLibereRepository
{
    public function __construct(private EntityManager $entityManager)
    {}

    public function findInDateRange(
        string $countryCode,
        string $dateFrom,
        string $dateUntil,
    ): array {
        $collection = $this->entityManager
            ->getRDBRepositoryByClass(ZileLibere::class)
            ->where([
                'countryCode' => $countryCode,
                'dateStart>=' => $dateFrom,
                'dateStart<' => $dateUntil,
            ])
            ->find();
        $result = [];

        foreach ($collection as $entity) {
            $result[] = new ZileLibereData(
                (string) $entity->getId(),
                (string) $entity->get('dateStart'),
                (string) $entity->get('name'),
                (string) $entity->get('countryCode'),
                (string) $entity->get('source'),
            );
        }

        return $result;
    }
}
