<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

use DateTimeImmutable;

final class PayloadNormalizer
{
    private const REQUIRED_FIELDS = [
        'date',
        'localName',
        'countryCode',
        'global',
        'counties',
        'types',
    ];

    /**
     * @return list<Holiday>
     * @throws ClientException
     */
    public function normalize(mixed $payload, string $expectedCountry, int $expectedYear): array
    {
        if (!is_array($payload) || !array_is_list($payload)) {
            throw $this->schemaError('The response root must be a list.');
        }

        $holidays = [];

        foreach ($payload as $index => $row) {
            if (!is_object($row)) {
                throw $this->rowError($index, 'must be an object.');
            }

            $row = get_object_vars($row);

            foreach (self::REQUIRED_FIELDS as $field) {
                if (!array_key_exists($field, $row)) {
                    throw $this->rowError($index, "is missing '$field'.");
                }
            }

            $date = $this->normalizeDate($row['date'], $expectedYear, $index);
            $name = $this->normalizeName($row['localName'], $index);
            $countryCode = $this->normalizeCountry($row['countryCode'], $expectedCountry, $index);

            if (!is_bool($row['global'])) {
                throw $this->rowError($index, "has an invalid 'global'.");
            }

            $subdivisionCodes = $this->normalizeSubdivisionCodes($row['counties'], $index);
            $holidayTypes = $this->normalizeHolidayTypes($row['types'], $index);

            $holidays[] = new Holiday(
                $date,
                $name,
                $countryCode,
                $row['global'],
                $subdivisionCodes,
                $holidayTypes,
            );
        }

        return $holidays;
    }

    private function normalizeDate(mixed $value, int $expectedYear, int $index): string
    {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value)) {
            throw $this->rowError($index, "has an invalid 'date'.");
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
            $date->format('Y-m-d') !== $value || (int) $date->format('Y') !== $expectedYear) {
            throw $this->rowError($index, "has a date outside the requested valid year.");
        }

        return $value;
    }

    private function normalizeName(mixed $value, int $index): string
    {
        if (!is_string($value)) {
            throw $this->rowError($index, "has an invalid 'localName'.");
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 255 || preg_match('/[<>=]/u', $value)) {
            throw $this->rowError($index, "has an invalid 'localName'.");
        }

        return $value;
    }

    private function normalizeCountry(mixed $value, string $expectedCountry, int $index): string
    {
        if (!is_string($value) || $value !== $expectedCountry) {
            throw $this->rowError($index, "has a country different from the request.");
        }

        return $value;
    }

    /** @return list<string> */
    private function normalizeSubdivisionCodes(mixed $value, int $index): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->rowError($index, "has invalid 'counties'.");
        }

        foreach ($value as $code) {
            if (!is_string($code) || !preg_match('/^[A-Z]{2}-[A-Z0-9]{1,3}$/', $code)) {
                throw $this->rowError($index, "has an invalid county code.");
            }
        }

        $value = array_values(array_unique($value));
        sort($value);

        return $value;
    }

    /** @return list<string> */
    private function normalizeHolidayTypes(mixed $value, int $index): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw $this->rowError($index, "has invalid 'types'.");
        }

        foreach ($value as $type) {
            if (!is_string($type) || !in_array($type, HolidayType::ALL, true)) {
                throw $this->rowError($index, "has an unknown holiday type.");
            }
        }

        $value = array_values(array_unique($value));
        sort($value);

        return $value;
    }

    private function rowError(int $index, string $detail): ClientException
    {
        return $this->schemaError("Nager.Date row $index $detail");
    }

    private function schemaError(string $message): ClientException
    {
        return new ClientException(ClientException::SCHEMA, $message);
    }
}
