<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\ORM\Entity;

interface EntityRecordReader
{
    /** @param list<string> $fields */
    public function find(string $entityType, string $recordId, array $fields): ?Entity;
}
