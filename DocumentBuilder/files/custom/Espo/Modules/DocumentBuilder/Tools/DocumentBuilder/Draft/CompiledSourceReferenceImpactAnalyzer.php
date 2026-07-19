<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariablePathCompiler;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableUsage;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\VisibilityCondition;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;

final readonly class CompiledSourceReferenceImpactAnalyzer implements SourceReferenceImpactAnalyzer
{
    public function __construct(private VariablePathCompiler $compiler)
    {}

    public function analyze(array $currentLayout, array $nextLayout): array
    {
        $source = $nextLayout['dataSource'] ?? null;

        if (!is_array($source) || array_is_list($source)) {
            throw new InvalidArgumentException('A source impact requires a valid next source.');
        }

        $unresolved = [];
        $this->walk($nextLayout, $source, '', $unresolved, null);

        return $unresolved;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<UnresolvedSourceReference> $unresolved
     */
    private function walk(mixed $value, array $source, string $path, array &$unresolved, ?string $nodeId): void
    {
        if (!is_array($value)) {
            return;
        }

        $currentId = is_string($value['id'] ?? null) ? $value['id'] : $nodeId;

        if (($value['type'] ?? null) === 'variable') {
            $identity = $value['identity'] ?? null;
            $tokenId = $value['tokenId'] ?? null;

            if (is_array($identity) && !array_is_list($identity) && is_string($tokenId)) {
                $this->compile($identity, $source, $tokenId, $path, $unresolved);
            }

            return;
        }

        if (is_array($value['condition'] ?? null) && !array_is_list($value['condition'])) {
            try {
                $condition = VisibilityCondition::fromArray($value['condition']);

                foreach ($condition->rules as $index => $rule) {
                    $this->compile(
                        $rule->identity->toArray(),
                        $source,
                        $currentId ?? 'condition',
                        "$path/condition/rules/$index",
                        $unresolved,
                    );
                }
            } catch (InvalidArgumentException) {
                if ($currentId !== null) {
                    $unresolved[] = new UnresolvedSourceReference($currentId, "$path/condition");
                }
            }
        }

        foreach ($value as $key => $item) {
            if ($key === 'condition') {
                continue;
            }

            $segment = is_int($key) ? (string) $key :
                (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', (string) $key) === 1 ? (string) $key : 'item');
            $this->walk($item, $source, "$path/$segment", $unresolved, $currentId);
        }
    }

    /**
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $source
     * @param list<UnresolvedSourceReference> $unresolved
     */
    private function compile(array $identity, array $source, string $id, string $path, array &$unresolved): void
    {
        try {
            $this->compiler->compile($identity, $source, VariableUsage::Scalar, []);
        } catch (InvalidArgumentException | PermissionDenied) {
            $unresolved[] = new UnresolvedSourceReference($id, $path === '' ? '/' : $path);
        }
    }
}
