<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

final readonly class StoredHoliday
{
    /** @param list<string> $subdivisionCodes @param list<string> $holidayTypes */
    public function __construct(
        public string $id,
        public string $date,
        public string $name,
        public string $countryCode,
        public int $sourceYear,
        public bool $nationalHoliday,
        public array $subdivisionCodes,
        public array $holidayTypes,
    ) {}
}
