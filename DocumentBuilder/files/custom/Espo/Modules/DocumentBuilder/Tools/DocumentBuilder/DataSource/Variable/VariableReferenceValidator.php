<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

interface VariableReferenceValidator
{
    /** @param array<string, mixed> $layout */
    public function validate(array $layout, mixed $spreadsheetSchema): void;
}
