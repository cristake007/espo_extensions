<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use InvalidArgumentException;

final readonly class Measurement
{
    private const BOUNDS = [
        Unit::Millimetre->value => [0.0, 2000.0],
        Unit::Point->value => [0.0, 512.0],
        Unit::Percent->value => [0.0, 100.0],
        Unit::GridSpan->value => [1.0, 24.0],
    ];

    public function __construct(private int|float $value, private Unit $unit)
    {
        $numericValue = (float) $value;
        [$minimum, $maximum] = self::BOUNDS[$unit->value];

        if (!is_finite($numericValue) || $numericValue < $minimum || $numericValue > $maximum) {
            throw new InvalidArgumentException('A measurement is outside the canonical unit bounds.');
        }

        if ($unit === Unit::GridSpan && floor($numericValue) !== $numericValue) {
            throw new InvalidArgumentException('A grid span must be a whole number.');
        }
    }

    /** @return array{value: int|float, unit: string} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'unit' => $this->unit->value];
    }
}
