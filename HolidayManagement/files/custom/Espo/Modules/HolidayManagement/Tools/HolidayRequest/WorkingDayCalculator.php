<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayRequest;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;

final class WorkingDayCalculator
{
    /** @param list<string> $nonWorkingDates */
    public function count(string $dateStart, string $dateEnd, array $nonWorkingDates = []): int
    {
        $start = $this->parse($dateStart);
        $end = $this->parse($dateEnd);
        $nonWorkingDateMap = array_fill_keys($nonWorkingDates, true);

        if ($end < $start) {
            throw new InvalidArgumentException('The last day cannot be before the first day.');
        }

        if ($start->diff($end)->days > 366) {
            throw new InvalidArgumentException('A holiday booking cannot span more than 367 days.');
        }

        $workingDays = 0;
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));

        foreach ($period as $date) {
            if (
                (int) $date->format('N') <= 5 &&
                !isset($nonWorkingDateMap[$date->format('Y-m-d')])
            ) {
                $workingDays++;
            }
        }

        if ($workingDays === 0) {
            throw new InvalidArgumentException('The selected period contains no working days.');
        }

        return $workingDays;
    }

    private function parse(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) ||
            $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException('Dates must use the YYYY-MM-DD format.');
        }

        return $date;
    }
}
