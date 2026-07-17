<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

final readonly class Holiday
{
    /** @param list<string> $subdivisionCodes @param list<string> $holidayTypes */
    public function __construct(
        public string $date,
        public string $name,
        public string $countryCode,
        public bool $nationalHoliday,
        public array $subdivisionCodes,
        public array $holidayTypes,
    ) {}
}
