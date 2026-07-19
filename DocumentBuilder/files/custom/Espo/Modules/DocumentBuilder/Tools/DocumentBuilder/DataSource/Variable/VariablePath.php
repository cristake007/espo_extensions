<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use InvalidArgumentException;

final readonly class VariablePath
{
    /** @param list<string> $segments */
    public function __construct(private array $segments)
    {
        if ($segments === [] || !array_is_list($segments) || count($segments) > 4) {
            throw new InvalidArgumentException('A variable path must contain between one and four segments.');
        }

        foreach ($segments as $segment) {
            if (!is_string($segment) ||
                preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $segment) !== 1) {
                throw new InvalidArgumentException('A variable path contains an invalid identifier.');
            }
        }
    }

    /** @return list<string> */
    public function segments(): array
    {
        return $this->segments;
    }
}
