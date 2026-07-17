<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use Closure;
use Espo\Modules\ZileSarbatoare\Entities\ZileLibere;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

final class EspoHolidayStore implements HolidayStore
{
    public function __construct(private EntityManager $entityManager)
    {}

    public function transactional(Closure $operation): ReconciliationResult
    {
        /** @var ReconciliationResult */
        return $this->entityManager->getTransactionManager()->run($operation);
    }

    public function findManagedScope(string $countryCode, array $years): array
    {
        $collection = $this->entityManager
            ->getRDBRepositoryByClass(ZileLibere::class)
            ->forUpdate()
            ->where([
                'source' => ZileLibere::SOURCE_NAGER_DATE,
                'managed' => true,
                'countryCode' => $countryCode,
                'sourceYear' => $years,
            ])
            ->find();
        $result = [];

        foreach ($collection as $entity) {
            $result[] = new StoredHoliday(
                (string) $entity->getId(),
                (string) $entity->get('dateStart'),
                (string) $entity->get('name'),
                (string) $entity->get('countryCode'),
                (int) $entity->get('sourceYear'),
                (bool) $entity->get('nationalHoliday'),
                $this->stringList($entity->get('subdivisionCodes')),
                $this->stringList($entity->get('holidayTypes')),
            );
        }

        return $result;
    }

    public function create(HolidayWriteData $data): void
    {
        $entity = $this->entityManager->getRDBRepositoryByClass(ZileLibere::class)->getNew();
        $this->apply($entity, $data);
        $this->entityManager->saveEntity($entity);
    }

    public function update(string $id, HolidayWriteData $data): void
    {
        $entity = $this->entityManager->getEntityById(ZileLibere::ENTITY_TYPE, $id);

        if (!$entity) {
            throw new RuntimeException('A synchronized holiday disappeared during reconciliation.');
        }

        $this->apply($entity, $data);
        $this->entityManager->saveEntity($entity);
    }

    public function remove(string $id): void
    {
        $entity = $this->entityManager->getEntityById(ZileLibere::ENTITY_TYPE, $id);

        if (!$entity) {
            throw new RuntimeException('A stale synchronized holiday disappeared during reconciliation.');
        }

        $this->entityManager->removeEntity($entity);
    }

    private function apply(Entity $entity, HolidayWriteData $data): void
    {
        $entity->set([
            'name' => $data->name,
            'dateStart' => $data->date,
            'countryCode' => $data->countryCode,
            'source' => ZileLibere::SOURCE_NAGER_DATE,
            'managed' => true,
            'sourceYear' => $data->sourceYear,
            'holidayTypes' => $data->holidayTypes,
            'nationalHoliday' => $data->nationalHoliday,
            'subdivisionCodes' => $data->subdivisionCodes,
            'syncedAt' => $data->syncedAt,
        ]);
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('A synchronized holiday contains invalid stored array data.');
        }

        return array_values(array_map('strval', $value));
    }
}
