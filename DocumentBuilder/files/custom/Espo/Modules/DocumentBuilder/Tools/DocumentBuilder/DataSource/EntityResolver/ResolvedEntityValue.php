<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;

final readonly class ResolvedEntityValue
{
    public function __construct(
        public VariableIdentity $identity,
        public VariableValue $value,
        public SourceProvenance $provenance,
    ) {}
}
