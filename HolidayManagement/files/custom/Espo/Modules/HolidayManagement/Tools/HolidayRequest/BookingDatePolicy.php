<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayRequest;

use InvalidArgumentException;

final class BookingDatePolicy
{
    public function assertAllowed(
        string $dateStart,
        string $dateEnd,
        string $today,
        ?string $originalDateStart = null,
        ?string $originalDateEnd = null,
    ): void {
        $datesChanged = $originalDateStart === null ||
            $originalDateEnd === null ||
            $dateStart !== $originalDateStart ||
            $dateEnd !== $originalDateEnd;

        if ($datesChanged && $dateStart < $today) {
            throw new InvalidArgumentException(
                'Holiday requests cannot start before today.'
            );
        }
    }
}
