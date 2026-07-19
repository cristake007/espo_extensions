<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntityMetadataTreeService;
use InvalidArgumentException;

final readonly class VariablePathCompiler
{
    public function __construct(
        private EntityMetadataTreeService $metadataTree,
        private SystemVariableRegistry $systemVariables,
    ) {}

    /**
     * @param array<string, mixed> $reference
     * @param array<string, mixed> $dataSource
     * @param list<string> $spreadsheetColumns
     */
    public function compile(
        array $reference,
        array $dataSource,
        VariableUsage $usage,
        array $spreadsheetColumns = [],
    ): VariableIdentity {
        $requested = VariableIdentity::fromArray($reference);
        $compiled = match ($requested->source) {
            VariableSource::Entity => $this->compileEntity($requested, $dataSource),
            VariableSource::System => $this->compileSystem($requested),
            VariableSource::Spreadsheet => $this->compileSpreadsheet(
                $requested,
                $dataSource,
                $spreadsheetColumns,
            ),
        };

        if ($compiled->usage() !== $usage || $compiled->toArray() !== $requested->toArray()) {
            throw new InvalidArgumentException('A variable identity is incompatible with its usage or path.');
        }

        return $compiled;
    }

    /** @param array<string, mixed> $dataSource */
    private function compileEntity(VariableIdentity $requested, array $dataSource): VariableIdentity
    {
        if (($dataSource['type'] ?? null) !== 'entity' ||
            ($dataSource['entityType'] ?? null) !== $requested->entityType) {
            throw new InvalidArgumentException('An entity variable is incompatible with the document source.');
        }

        $segments = $requested->path->segments();
        $relationshipPath = [];

        foreach ($segments as $index => $segment) {
            $tree = $this->metadataTree->get($requested->entityType, $relationshipPath);
            $field = $this->findByName($tree->fields, $segment);

            if ($field !== null) {
                if ($index !== array_key_last($segments)) {
                    throw new InvalidArgumentException('A scalar field must terminate a variable path.');
                }

                return new VariableIdentity(
                    VariableSource::Entity,
                    $relationshipPath === [] ? VariableType::Direct : VariableType::Related,
                    new VariablePath($segments),
                    $requested->entityType,
                );
            }

            $relationship = $this->findByName($tree->relationships, $segment);

            if ($relationship === null) {
                throw new InvalidArgumentException('A variable path is not present in readable metadata.');
            }

            if ($relationship->collection) {
                if ($index !== array_key_last($segments)) {
                    throw new InvalidArgumentException('A collection relationship must terminate a variable path.');
                }

                return new VariableIdentity(
                    VariableSource::Entity,
                    VariableType::Collection,
                    new VariablePath($segments),
                    $requested->entityType,
                );
            }

            if (!$relationship->single || !$relationship->expandable) {
                throw new InvalidArgumentException('A relationship cannot be traversed by this variable path.');
            }

            $relationshipPath[] = $segment;
        }

        throw new InvalidArgumentException('A single-record relationship cannot be used as a value.');
    }

    private function compileSystem(VariableIdentity $requested): VariableIdentity
    {
        $name = $requested->path->segments()[0];

        if (!$this->systemVariables->has($name)) {
            throw new InvalidArgumentException('A system variable is unsupported.');
        }

        return new VariableIdentity(
            VariableSource::System,
            VariableType::System,
            new VariablePath([$name]),
        );
    }

    /** @param array<string, mixed> $dataSource @param list<string> $columns */
    private function compileSpreadsheet(
        VariableIdentity $requested,
        array $dataSource,
        array $columns,
    ): VariableIdentity {
        $name = $requested->path->segments()[0];

        if (($dataSource['type'] ?? null) !== 'spreadsheet' || !in_array($name, $columns, true)) {
            throw new InvalidArgumentException('A spreadsheet variable is incompatible with the document source.');
        }

        return new VariableIdentity(
            VariableSource::Spreadsheet,
            VariableType::Spreadsheet,
            new VariablePath([$name]),
        );
    }

    /** @param array<object> $items */
    private function findByName(array $items, string $name): ?object
    {
        foreach ($items as $item) {
            if (($item->name ?? null) === $name) {
                return $item;
            }
        }

        return null;
    }
}
