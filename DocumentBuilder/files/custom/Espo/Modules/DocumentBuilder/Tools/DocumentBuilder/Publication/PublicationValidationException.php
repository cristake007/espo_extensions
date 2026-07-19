<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use DomainException;

final class PublicationValidationException extends DomainException
{
    public function __construct(
        public readonly PublicationBlockerCategory $category,
        public readonly string $blockerCode,
    ) {
        parent::__construct(sprintf('Publication blocked by %s validation.', $category->value));
    }

    /** @return array{category: string, code: string} */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'code' => $this->blockerCode,
        ];
    }
}
