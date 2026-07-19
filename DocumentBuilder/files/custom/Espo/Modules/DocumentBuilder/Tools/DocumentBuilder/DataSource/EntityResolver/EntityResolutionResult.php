<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;

final readonly class EntityResolutionResult
{
    /** @param list<ResolvedEntityValue> $values */
    public function __construct(public array $values)
    {}

    public function find(VariableIdentity $identity): ?ResolvedEntityValue
    {
        $expected = $identity->toArray();

        foreach ($this->values as $value) {
            if ($value->identity->toArray() === $expected) {
                return $value;
            }
        }

        return null;
    }
}
