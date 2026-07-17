<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere;

use DateTimeImmutable;
use InvalidArgumentException;

final class ZileLibereCalendarService implements ZileLibereCalendar
{
    private const MINIMUM_YEAR = 1;
    private const MAXIMUM_YEAR = 9998;

    public function __construct(
        private ZileLibereRepository $repository,
        private ZileLibereValidator $validator,
    ) {}

    public function getZileLiberePentruLuni(
        int $year,
        array $months,
        string $countryCode = 'RO',
    ): array {
        $this->validateYear($year);
        $months = $this->normalizeMonths($months);
        $countryCode = $this->validator->normalizeCountryCode($countryCode);
        [$dateFrom, $dateUntil] = $this->monthRange($year, $months);
        $selectedMonths = array_fill_keys($months, true);
        $records = array_values(array_filter(
            $this->repository->findInDateRange($countryCode, $dateFrom, $dateUntil),
            static function (ZileLibereData $record) use ($year, $countryCode, $selectedMonths): bool {
                return $record->countryCode === $countryCode &&
                    (int) substr($record->date, 0, 4) === $year &&
                    isset($selectedMonths[(int) substr($record->date, 5, 2)]);
            },
        ));

        usort($records, static fn (ZileLibereData $left, ZileLibereData $right): int =>
            [$left->date, $left->name, $left->id] <=> [$right->date, $right->name, $right->id]
        );

        return $records;
    }

    public function getDateLiberePentruLuni(
        int $year,
        array $months,
        string $countryCode = 'RO',
    ): array {
        $dates = [];

        foreach ($this->getZileLiberePentruLuni($year, $months, $countryCode) as $record) {
            $dates[$record->date] = true;
        }

        return array_keys($dates);
    }

    public function esteZiLibera(string $date, string $countryCode = 'RO'): bool
    {
        $date = $this->validator->normalizeDate($date);
        $countryCode = $this->validator->normalizeCountryCode($countryCode);
        $year = (int) substr($date, 0, 4);
        $this->validateYear($year);
        $dateUntil = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');

        return $this->repository->findInDateRange($countryCode, $date, $dateUntil) !== [];
    }

    private function validateYear(int $year): void
    {
        if ($year < self::MINIMUM_YEAR || $year > self::MAXIMUM_YEAR) {
            throw new InvalidArgumentException('Year must be between 1 and 9998.');
        }
    }

    /** @param list<int> $months @return list<int> */
    private function normalizeMonths(array $months): array
    {
        if ($months === []) {
            throw new InvalidArgumentException('Select at least one month.');
        }

        $normalized = [];

        foreach ($months as $month) {
            if (!is_int($month) || $month < 1 || $month > 12) {
                throw new InvalidArgumentException('Every month must be an integer between 1 and 12.');
            }

            $normalized[$month] = true;
        }

        $normalized = array_keys($normalized);
        sort($normalized);

        return $normalized;
    }

    /** @param non-empty-list<int> $months @return array{string, string} */
    private function monthRange(int $year, array $months): array
    {
        $firstMonth = $months[0];
        $lastMonth = $months[count($months) - 1];
        $dateFrom = sprintf('%04d-%02d-01', $year, $firstMonth);

        if ($lastMonth === 12) {
            return [$dateFrom, sprintf('%04d-01-01', $year + 1)];
        }

        return [$dateFrom, sprintf('%04d-%02d-01', $year, $lastMonth + 1)];
    }
}
