<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

final readonly class RelatedLinkStep
{
    public function __construct(
        public string $sourceEntityType,
        public string $link,
        public string $targetEntityType,
        public bool $readable,
    ) {}
}
