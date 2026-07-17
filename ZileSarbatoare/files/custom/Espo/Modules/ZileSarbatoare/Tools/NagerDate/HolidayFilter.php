<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

final class HolidayFilter
{
    /**
     * @param list<Holiday> $holidays
     * @param list<string> $acceptedTypes
     * @return list<Holiday>
     */
    public function filter(array $holidays, array $acceptedTypes, bool $nationalOnly): array
    {
        return array_values(array_filter(
            $holidays,
            static fn (Holiday $holiday): bool =>
                (!$nationalOnly || $holiday->nationalHoliday) &&
                array_intersect($holiday->holidayTypes, $acceptedTypes) !== [],
        ));
    }
}
