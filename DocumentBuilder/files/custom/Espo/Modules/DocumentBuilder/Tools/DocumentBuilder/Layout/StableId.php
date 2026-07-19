<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use InvalidArgumentException;

final readonly class StableId
{
    public const MAX_LENGTH = 64;

    public function __construct(private string $value)
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $value) !== 1) {
            throw new InvalidArgumentException('A layout ID must use the canonical safe-ID format.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
