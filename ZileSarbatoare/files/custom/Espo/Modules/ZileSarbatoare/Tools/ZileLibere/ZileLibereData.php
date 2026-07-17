<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere;

final readonly class ZileLibereData
{
    public function __construct(
        public string $id,
        public string $date,
        public string $name,
        public string $countryCode,
        public string $source,
    ) {}
}
