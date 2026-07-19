<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\ORM\Entity;

interface RelatedRecordReader
{
    /** @param list<string> $fields */
    public function find(Entity $source, string $link, array $fields): ?Entity;
}
