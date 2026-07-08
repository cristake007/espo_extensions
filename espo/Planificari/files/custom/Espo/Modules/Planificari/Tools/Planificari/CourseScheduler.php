<?php

namespace Espo\Modules\Planificari\Tools\Planificari;

use DateInterval;
use DateTimeImmutable;

class CourseScheduler
{
    /** @var array<string, true> */
    private array $holidayMap = [];

    /** @var array<string, DateTimeImmutable[]> */
    private array $availableDateCache = [];

    private int $availableDateCalculations = 0;

    /**
     * @param string[] $holidays
     */
    public function __construct(private int $year, array $holidays)
    {
        foreach ($holidays as $holiday) {
            $date = DateTimeImmutable::createFromFormat('!d.m.Y', $holiday);

            if ($date) {
                $this->holidayMap[$date->format('Y-m-d')] = true;
            }
        }
    }

    public function getAvailableDateCalculations(): int
    {
        return $this->availableDateCalculations;
    }

    public function isBusinessDay(DateTimeImmutable $date): bool
    {
        if ((int) $date->format('N') >= 6) {
            return false;
        }

        return !isset($this->holidayMap[$date->format('Y-m-d')]);
    }

    public function canScheduleCourse(DateTimeImmutable $startDate, int $duration): bool
    {
        if (!$this->isBusinessDay($startDate)) {
            return false;
        }

        $currentDate = $startDate;
        $businessDays = 0;
        $allowCrossPeriod = $duration > 5;
        $weekStart = $startDate->modify('monday this week');

        while ($businessDays < $duration) {
            if (isset($this->holidayMap[$currentDate->format('Y-m-d')])) {
                return false;
            }

            if ($currentDate->format('Y') !== $startDate->format('Y')) {
                return false;
            }

            if (!$allowCrossPeriod) {
                if ((int) $weekStart->diff($currentDate)->format('%a') >= 5) {
                    return false;
                }

                if ($currentDate->format('n') !== $startDate->format('n')) {
                    return false;
                }
            }

            if ($this->isBusinessDay($currentDate)) {
                $businessDays++;
            }

            $currentDate = $currentDate->add(new DateInterval('P1D'));
        }

        return true;
    }

    /**
     * @return DateTimeImmutable[]
     */
    public function getAvailableStartDays(int $month, int $duration): array
    {
        $cacheKey = $month . ':' . $duration;

        if (array_key_exists($cacheKey, $this->availableDateCache)) {
            return $this->availableDateCache[$cacheKey];
        }

        $this->availableDateCalculations++;
        $currentDate = new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $month));
        $endDate = $currentDate->modify('last day of this month');
        $dates = [];

        while ($currentDate <= $endDate) {
            if ($this->canScheduleCourse($currentDate, $duration)) {
                $dates[] = $currentDate;
            }

            $currentDate = $currentDate->add(new DateInterval('P1D'));
        }

        $this->availableDateCache[$cacheKey] = $dates;

        return $dates;
    }

    public function countBusinessDaysAvailableFromMonth(int $month): int
    {
        $currentDate = new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $month));
        $endDate = new DateTimeImmutable(sprintf('%04d-12-31', $this->year));
        $count = 0;

        while ($currentDate <= $endDate) {
            if ($this->isBusinessDay($currentDate)) {
                $count++;
            }

            $currentDate = $currentDate->add(new DateInterval('P1D'));
        }

        return $count;
    }

    public function formatDateRange(DateTimeImmutable $startDate, int $duration): string
    {
        if ($duration === 1) {
            return $startDate->format('d.m.Y');
        }

        $businessDays = 0;
        $currentDate = $startDate;

        while ($businessDays < $duration) {
            if ($this->isBusinessDay($currentDate)) {
                $businessDays++;
            }

            if ($businessDays < $duration) {
                $currentDate = $currentDate->add(new DateInterval('P1D'));
            }
        }

        if ($startDate->format('m.Y') !== $currentDate->format('m.Y')) {
            return $startDate->format('d.m') . '-' . $currentDate->format('d.m.Y');
        }

        return $startDate->format('d') . '-' . $currentDate->format('d.m.Y');
    }
}
