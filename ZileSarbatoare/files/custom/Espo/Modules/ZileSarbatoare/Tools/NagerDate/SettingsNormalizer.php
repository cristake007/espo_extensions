<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;
use InvalidArgumentException;

final class SettingsNormalizer
{
    private const FREQUENCIES = ['Daily', 'Weekly', 'Monthly', 'ManualOnly'];
    private const HOLIDAY_TYPES = ['Public', 'Bank', 'School', 'Authorities', 'Optional', 'Observance'];

    /** @param array<string, mixed> $input */
    public function normalize(array $input, DateTimeImmutable $now): Settings
    {
        $countryCode = strtoupper(trim((string) ($input['countryCode'] ?? 'RO')));

        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new InvalidArgumentException('Country must be a two-letter ISO code.');
        }

        $years = $input['years'] ?? [(int) $now->format('Y'), (int) $now->format('Y') + 1];

        if (!is_array($years) || $years === []) {
            throw new InvalidArgumentException('At least one synchronization year is required.');
        }

        $years = array_values(array_unique(array_map(
            static function (mixed $value): int {
                if (!is_int($value) && (!is_string($value) || !preg_match('/^[0-9]{4}$/', $value))) {
                    throw new InvalidArgumentException('Synchronization years must contain four digits.');
                }

                $year = (int) $value;

                if ($year < 1970 || $year > 2100) {
                    throw new InvalidArgumentException('Synchronization years must be between 1970 and 2100.');
                }

                return $year;
            },
            $years,
        )));
        sort($years);

        $holidayTypes = $input['holidayTypes'] ?? ['Public'];

        if (!is_array($holidayTypes) || $holidayTypes === []) {
            throw new InvalidArgumentException('At least one holiday type is required.');
        }

        $holidayTypes = array_values(array_unique(array_map('strval', $holidayTypes)));

        foreach ($holidayTypes as $type) {
            if (!in_array($type, self::HOLIDAY_TYPES, true)) {
                throw new InvalidArgumentException('Unknown Nager.Date holiday type.');
            }
        }

        $frequency = (string) ($input['frequency'] ?? 'Weekly');

        if (!in_array($frequency, self::FREQUENCIES, true)) {
            throw new InvalidArgumentException('Unknown synchronization frequency.');
        }

        $timeOfDay = (string) ($input['timeOfDay'] ?? '03:00');

        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $timeOfDay)) {
            throw new InvalidArgumentException('Time of day must use the HH:MM 24-hour format.');
        }

        $dayOfWeek = $this->normalizeBoundedInt($input['dayOfWeek'] ?? 1, 1, 7, 'Day of week');
        $dayOfMonth = $this->normalizeBoundedInt($input['dayOfMonth'] ?? 1, 1, 31, 'Day of month');

        return new Settings(
            $this->normalizeBool($input['enabled'] ?? true, 'Enabled'),
            $countryCode,
            $years,
            $holidayTypes,
            $this->normalizeBool($input['nationalOnly'] ?? true, 'National holidays only'),
            $this->normalizeBool($input['automaticSync'] ?? true, 'Automatic synchronization'),
            $frequency,
            $timeOfDay,
            $dayOfWeek,
            $dayOfMonth,
        );
    }

    private function normalizeBool(mixed $value, string $label): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException("$label must be boolean.");
        }

        return $value;
    }

    private function normalizeBoundedInt(mixed $value, int $min, int $max, string $label): int
    {
        if (!is_int($value) && (!is_string($value) || !preg_match('/^[0-9]+$/', $value))) {
            throw new InvalidArgumentException("$label must be an integer.");
        }

        $value = (int) $value;

        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("$label must be between $min and $max.");
        }

        return $value;
    }
}
