<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\VisibilityCondition;
use InvalidArgumentException;
use stdClass;

final readonly class CompiledVariableReferenceValidator implements VariableReferenceValidator
{
    public function __construct(private VariablePathCompiler $compiler)
    {}

    /** @param array<string, mixed> $layout */
    public function validate(array $layout, mixed $spreadsheetSchema): void
    {
        $source = $layout['dataSource'] ?? null;

        if (!is_array($source) || array_is_list($source)) {
            throw new InvalidArgumentException('A variable reference requires a valid document source.');
        }

        $this->compileVariables($layout, $source, $this->spreadsheetColumns($spreadsheetSchema));
    }

    /** @param array<string, mixed> $source @param list<string> $columns */
    private function compileVariables(mixed $value, array $source, array $columns): void
    {
        if (!is_array($value)) {
            return;
        }

        if (array_key_exists('condition', $value)) {
            if (!is_array($value['condition']) || array_is_list($value['condition'])) {
                throw new InvalidArgumentException('A stored visibility condition is invalid.');
            }

            $condition = VisibilityCondition::fromArray($value['condition']);

            foreach ($condition->rules as $rule) {
                $this->compiler->compile($rule->identity->toArray(), $source, VariableUsage::Scalar, $columns);
            }
        }

        if (($value['type'] ?? null) === 'variable') {
            $identity = $value['identity'] ?? null;

            if (!is_array($identity) || array_is_list($identity)) {
                throw new InvalidArgumentException('A stored variable identity is invalid.');
            }

            $this->compiler->compile($identity, $source, VariableUsage::Scalar, $columns);

            return;
        }

        foreach ($value as $item) {
            $this->compileVariables($item, $source, $columns);
        }
    }

    /** @return list<string> */
    private function spreadsheetColumns(mixed $schema): array
    {
        if ($schema instanceof stdClass) {
            $schema = (array) $schema;
        }

        if (!is_array($schema)) {
            return [];
        }

        $columns = $schema['columns'] ?? [];

        if ($columns instanceof stdClass) {
            $columns = (array) $columns;
        }

        if (!is_array($columns)) {
            return [];
        }

        $names = [];

        foreach ($columns as $key => $column) {
            if ($column instanceof stdClass) {
                $column = (array) $column;
            }

            $name = is_string($column) ? $column :
                (is_array($column) && is_string($column['name'] ?? null) ? $column['name'] : null);

            if ($name === null && is_string($key)) {
                $name = $key;
            }

            if ($name !== null && preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $name) === 1) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }
}
