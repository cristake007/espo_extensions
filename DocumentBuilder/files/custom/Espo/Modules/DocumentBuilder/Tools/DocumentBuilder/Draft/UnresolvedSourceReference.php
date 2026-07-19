<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use InvalidArgumentException;

final readonly class UnresolvedSourceReference
{
    public function __construct(public string $id, public string $path)
    {
        if (
            preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $id) !== 1 ||
            preg_match('/^\/[A-Za-z0-9_.\/\[\]=:-]{0,511}$/D', $path) !== 1
        ) {
            throw new InvalidArgumentException('An unresolved source reference is invalid.');
        }
    }

    /** @return array{id: string, path: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'path' => $this->path];
    }
}
