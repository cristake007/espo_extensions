<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use InvalidArgumentException;

final readonly class OrmEntityRecordReader implements EntityRecordReader
{
    public function __construct(private EntityManager $entityManager)
    {}

    public function find(string $entityType, string $recordId, array $fields): ?Entity
    {
        if ($fields === [] || !array_is_list($fields)) {
            throw new InvalidArgumentException('The entity select plan is invalid.');
        }

        return $this->entityManager
            ->getRDBRepository($entityType)
            ->select($fields)
            ->where(['id' => $recordId, 'deleted' => false])
            ->findOne();
    }
}
