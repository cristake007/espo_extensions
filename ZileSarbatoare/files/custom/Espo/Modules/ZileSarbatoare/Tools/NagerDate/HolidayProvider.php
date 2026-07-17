<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

interface HolidayProvider
{
    /**
     * @param list<string> $acceptedTypes
     * @return list<Holiday>
     * @throws ClientException
     */
    public function fetch(
        string $countryCode,
        int $year,
        array $acceptedTypes = ['Public'],
        bool $nationalOnly = true,
    ): array;
}
