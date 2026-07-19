<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

final readonly class DirectEntityQueryPlan
{
    /**
     * @param list<string> $selectFields
     * @param list<DirectFieldResolution> $fields
     */
    public function __construct(
        public array $selectFields,
        public array $fields,
    ) {}
}
