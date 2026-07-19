<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue;

final readonly class EntityFieldItem
{
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $direct,
        public bool $calculated,
        public bool $required,
        public bool $readOnly,
        public bool $custom,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
