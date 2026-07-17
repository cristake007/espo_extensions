<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere;

interface ZileLibereCalendar
{
    /**
     * Returns records for exactly the selected months.
     *
     * Duplicate months are accepted and normalized. Empty selections, values
     * outside 1..12, invalid years, and invalid country codes are rejected.
     *
     * Example:
     * `$calendar->getZileLiberePentruLuni(2026, [2, 3, 7], 'RO');`
     *
     * @param list<int> $months
     * @return list<ZileLibereData>
     */
    public function getZileLiberePentruLuni(
        int $year,
        array $months,
        string $countryCode = 'RO',
    ): array;

    /**
     * Returns sorted, unique ISO dates for exactly the selected months.
     *
     * Example:
     * `$calendar->getDateLiberePentruLuni(2026, [2, 3, 7]);`
     *
     * @param list<int> $months
     * @return list<string>
     */
    public function getDateLiberePentruLuni(
        int $year,
        array $months,
        string $countryCode = 'RO',
    ): array;

    /** @throws \InvalidArgumentException When the date or country is invalid. */
    public function esteZiLibera(
        string $date,
        string $countryCode = 'RO',
    ): bool;
}
