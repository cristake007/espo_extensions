<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\NagerDate;

final class Settings
{
    /** @param list<int> $years @param list<string> $holidayTypes */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $countryCode,
        public readonly array $years,
        public readonly array $holidayTypes,
        public readonly bool $nationalOnly,
        public readonly bool $automaticSync,
        public readonly string $frequency,
        public readonly string $timeOfDay,
        public readonly int $dayOfWeek,
        public readonly int $dayOfMonth,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'countryCode' => $this->countryCode,
            'years' => array_map(static fn (int $year): string => (string) $year, $this->years),
            'holidayTypes' => $this->holidayTypes,
            'nationalOnly' => $this->nationalOnly,
            'automaticSync' => $this->automaticSync,
            'frequency' => $this->frequency,
            'timeOfDay' => $this->timeOfDay,
            'dayOfWeek' => (string) $this->dayOfWeek,
            'dayOfMonth' => $this->dayOfMonth,
        ];
    }
}
