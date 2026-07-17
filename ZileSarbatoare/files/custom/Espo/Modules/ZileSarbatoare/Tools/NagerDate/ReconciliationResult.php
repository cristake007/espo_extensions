<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

final readonly class ReconciliationResult
{
    public function __construct(
        public int $accepted,
        public int $created,
        public int $updated,
        public int $removed,
    ) {}
}
