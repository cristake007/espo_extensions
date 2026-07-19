<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Html;

final readonly class ElementDefinition
{
    public function __construct(
        public string $tag,
        public string $className,
        public bool $void = false,
    ) {}
}
