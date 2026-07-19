<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use InvalidArgumentException;

final readonly class NestingDepth
{
    public const HARD_MAXIMUM = 16;

    public function __construct(private int $value)
    {
        if ($value < 0 || $value > self::HARD_MAXIMUM) {
            throw new InvalidArgumentException('Layout nesting depth is outside the hard boundary.');
        }
    }

    public function value(): int
    {
        return $this->value;
    }
}
