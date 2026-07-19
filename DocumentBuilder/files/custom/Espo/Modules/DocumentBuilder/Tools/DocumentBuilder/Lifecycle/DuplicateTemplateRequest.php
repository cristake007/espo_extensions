<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use InvalidArgumentException;

final readonly class DuplicateTemplateRequest
{
    public ?string $name;

    public function __construct(
        public int $expectedRevision,
        ?string $name = null,
    ) {
        $name = $name === null ? null : trim($name);
        $this->name = $name === '' ? null : $name;

        if (
            $expectedRevision < 0 ||
            ($this->name !== null && mb_strlen($this->name) > 150)
        ) {
            throw new InvalidArgumentException('Duplicate-template input is invalid.');
        }
    }
}
