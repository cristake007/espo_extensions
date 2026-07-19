<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;

final readonly class DirectVariableReference
{
    public function __construct(
        public VariableIdentity $identity,
        public string $field,
    ) {}
}
