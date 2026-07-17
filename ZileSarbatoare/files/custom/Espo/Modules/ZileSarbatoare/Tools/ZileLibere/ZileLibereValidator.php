<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere;

use DateTimeImmutable;
use InvalidArgumentException;

final class ZileLibereValidator
{
    public function normalizeName(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        return trim($value);
    }

    public function normalizeDate(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Date must use the YYYY-MM-DD format.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
            $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException('Date must be a valid calendar date in YYYY-MM-DD format.');
        }

        return $value;
    }

    public function normalizeCountryCode(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Country code must contain two letters.');
        }

        $countryCode = strtoupper(trim($value));

        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new InvalidArgumentException('Country code must contain two ISO uppercase letters.');
        }

        return $countryCode;
    }
}
