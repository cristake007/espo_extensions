<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere;

interface ZileLibereRepository
{
    /**
     * Returns active records in the inclusive/exclusive date range.
     *
     * @return list<ZileLibereData>
     */
    public function findInDateRange(
        string $countryCode,
        string $dateFrom,
        string $dateUntil,
    ): array;
}
