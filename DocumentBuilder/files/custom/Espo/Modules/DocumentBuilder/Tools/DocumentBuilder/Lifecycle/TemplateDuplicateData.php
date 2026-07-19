<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

final readonly class TemplateDuplicateData
{
    /**
     * @param array<string, mixed> $attributes
     * @param list<string> $teamIds
     */
    public function __construct(
        public array $attributes,
        public array $teamIds,
    ) {}
}
