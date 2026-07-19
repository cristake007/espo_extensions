<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use InvalidArgumentException;

final readonly class OrmRelatedRecordReader implements RelatedRecordReader
{
    public function __construct(private EntityManager $entityManager)
    {}

    public function find(Entity $source, string $link, array $fields): ?Entity
    {
        if ($fields === [] || !array_is_list($fields)) {
            throw new InvalidArgumentException('The related select plan is invalid.');
        }

        return $this->entityManager
            ->getRelation($source, $link)
            ->select($fields)
            ->where(['deleted' => false])
            ->findOne();
    }
}
