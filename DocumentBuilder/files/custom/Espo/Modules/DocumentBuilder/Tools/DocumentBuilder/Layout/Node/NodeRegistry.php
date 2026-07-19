<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use InvalidArgumentException;

final readonly class NodeRegistry
{
    /** @var array<string, NodeDefinition> */
    private array $definitions;

    public function __construct(NodeDefinition ...$definitions)
    {
        $indexed = [];

        foreach ($definitions as $definition) {
            $key = $definition->kind()->value . ':' . $definition->type();

            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("Duplicate layout node definition: $key.");
            }

            $indexed[$key] = $definition;
        }

        $this->definitions = $indexed;
    }

    public function require(NodeKind $kind, string $type): NodeDefinition
    {
        return $this->definitions[$kind->value . ':' . $type]
            ?? throw new UnknownNodeType($kind, $type);
    }

    public static function phase19(): self
    {
        return new self(
            new NodeDefinition(NodeKind::Section, 'flow-section', [Capability::FlowLayout]),
            new NodeDefinition(NodeKind::Element, 'flow-container', [Capability::FlowLayout]),
        );
    }
}
