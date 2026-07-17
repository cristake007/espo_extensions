<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use InvalidArgumentException;

final class Reconciler
{
    public function __construct(private HolidayStore $store)
    {}

    /**
     * @param list<int> $years
     * @param list<Holiday> $holidays
     */
    public function reconcile(
        string $countryCode,
        array $years,
        array $holidays,
        string $syncedAt,
    ): ReconciliationResult {
        $this->validateInput($countryCode, $years, $holidays);

        return $this->store->transactional(function () use ($countryCode, $years, $holidays, $syncedAt) {
            return $this->reconcileInTransaction($countryCode, $years, $holidays, $syncedAt);
        });
    }

    /** @param list<int> $years @param list<Holiday> $holidays */
    private function reconcileInTransaction(
        string $countryCode,
        array $years,
        array $holidays,
        string $syncedAt,
    ): ReconciliationResult {
        /** @var array<string, list<StoredHoliday>> $existingByKey */
        $existingByKey = [];

        foreach ($this->store->findManagedScope($countryCode, $years) as $stored) {
            $existingByKey[$this->key($stored->date, $stored->name)][] = $stored;
        }

        $created = 0;
        $updated = 0;

        foreach ($holidays as $holiday) {
            $key = $this->key($holiday->date, $holiday->name);
            $stored = null;

            if (($existingByKey[$key] ?? []) !== []) {
                $stored = array_shift($existingByKey[$key]);
            }

            $writeData = $this->writeData($holiday, $syncedAt);

            if (!$stored) {
                $this->store->create($writeData);
                $created++;

                continue;
            }

            if ($this->hasChanges($stored, $holiday)) {
                $this->store->update($stored->id, $writeData);
                $updated++;
            }
        }

        $removed = 0;

        foreach ($existingByKey as $remaining) {
            foreach ($remaining as $stored) {
                $this->store->remove($stored->id);
                $removed++;
            }
        }

        return new ReconciliationResult(count($holidays), $created, $updated, $removed);
    }

    /** @param list<int> $years @param list<Holiday> $holidays */
    private function validateInput(string $countryCode, array $years, array $holidays): void
    {
        $seen = [];

        foreach ($holidays as $holiday) {
            $year = (int) substr($holiday->date, 0, 4);

            if ($holiday->countryCode !== $countryCode || !in_array($year, $years, true)) {
                throw new InvalidArgumentException('A holiday is outside the requested reconciliation scope.');
            }

            $key = $this->key($holiday->date, $holiday->name);

            if (isset($seen[$key])) {
                throw new InvalidArgumentException('The upstream payload contains a duplicate holiday identity.');
            }

            $seen[$key] = true;
        }
    }

    private function hasChanges(StoredHoliday $stored, Holiday $holiday): bool
    {
        return $stored->date !== $holiday->date ||
            $stored->name !== $holiday->name ||
            $stored->countryCode !== $holiday->countryCode ||
            $stored->sourceYear !== (int) substr($holiday->date, 0, 4) ||
            $stored->nationalHoliday !== $holiday->nationalHoliday ||
            $stored->subdivisionCodes !== $holiday->subdivisionCodes ||
            $stored->holidayTypes !== $holiday->holidayTypes;
    }

    private function writeData(Holiday $holiday, string $syncedAt): HolidayWriteData
    {
        return new HolidayWriteData(
            $holiday->date,
            $holiday->name,
            $holiday->countryCode,
            (int) substr($holiday->date, 0, 4),
            $holiday->nationalHoliday,
            $holiday->subdivisionCodes,
            $holiday->holidayTypes,
            $syncedAt,
        );
    }

    private function key(string $date, string $name): string
    {
        return $date . "\0" . $name;
    }
}
