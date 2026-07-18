<?php

declare(strict_types=1);

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class WordPressProgramMerger
{
    /**
     * @param array<int, mixed>|false|null $existingProgram
     * @param array<int, mixed> $fileDates
     * @return array{
     *     existingValidDates: array<int, string>,
     *     finalDates: array<int, string>,
     *     payload: array{acf: array{program: array<int, array{data: string}>|false}},
     *     changed: bool
     * }
     */
    public function merge(array|false|null $existingProgram, array $fileDates, DateTimeImmutable $today): array
    {
        $existingValidDates = $this->filterExistingDates($existingProgram, $today);
        $validatedFileDates = $this->validateFileDates($fileDates);
        $finalDates = $existingValidDates;
        $seen = array_fill_keys($existingValidDates, true);

        foreach ($validatedFileDates as $date) {
            if (isset($seen[$date])) {
                continue;
            }

            $finalDates[] = $date;
            $seen[$date] = true;
        }

        $program = array_map(
            static fn (string $date): array => ['data' => $date],
            $finalDates
        );

        return [
            'existingValidDates' => $existingValidDates,
            'finalDates' => $finalDates,
            'payload' => ['acf' => ['program' => $program !== [] ? $program : false]],
            'changed' => $existingValidDates !== $finalDates,
        ];
    }

    public function parseEffectiveEndDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches) === 1) {
            return $this->createDate((int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})-(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches) === 1) {
            $startDate = $this->createDate((int) $matches[5], (int) $matches[2], (int) $matches[1]);
            $endDate = $this->createDate((int) $matches[5], (int) $matches[4], (int) $matches[3]);

            if ($startDate === null || $endDate === null || $startDate > $endDate) {
                return null;
            }

            return $endDate;
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $matches) !== 1) {
            return null;
        }

        $startDay = (int) $matches[1];
        $endDay = (int) $matches[2];
        $month = (int) $matches[3];
        $year = (int) $matches[4];

        if ($startDay > $endDay || !checkdate($month, $startDay, $year)) {
            return null;
        }

        return $this->createDate($year, $month, $endDay);
    }

    /**
     * @param array<int, mixed>|false|null $existingProgram
     * @return array<int, string>
     */
    public function filterExistingDates(array|false|null $existingProgram, DateTimeImmutable $today): array
    {
        $result = [];
        $seen = [];
        $today = $today
            ->setTimezone(new DateTimeZone('Europe/Bucharest'))
            ->setTime(0, 0);

        foreach ($existingProgram ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $value = $this->normalizeValue($row['data'] ?? null);

            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $effectiveDate = $this->parseEffectiveEndDate($value);

            if ($effectiveDate === null || $effectiveDate < $today) {
                continue;
            }

            $result[] = $value;
            $seen[$value] = true;
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $fileDates
     * @return array<int, string>
     */
    public function validateFileDates(array $fileDates): array
    {
        $result = [];
        $seen = [];

        foreach ($fileDates as $value) {
            $normalized = $this->normalizeValue($value);

            if ($normalized === '' || $this->parseEffectiveEndDate($normalized) === null) {
                throw new InvalidArgumentException('The schedule contains an invalid WordPress program date.');
            }

            if (!isset($seen[$normalized])) {
                $result[] = $normalized;
                $seen[$normalized] = true;
            }
        }

        return $result;
    }

    private function createDate(int $year, int $month, int $day): ?DateTimeImmutable
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d', $year, $month, $day),
            new DateTimeZone('Europe/Bucharest')
        );
    }

    private function normalizeValue(mixed $value): string
    {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return trim((string) $value);
    }
}
