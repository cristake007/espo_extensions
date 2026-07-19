<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;

final readonly class DirectFieldResolution
{
    public function __construct(
        public VariableIdentity $identity,
        public string $field,
        public VariableValueType $valueType,
        public bool $readable,
        public ?string $currencyField = null,
    ) {}
}
