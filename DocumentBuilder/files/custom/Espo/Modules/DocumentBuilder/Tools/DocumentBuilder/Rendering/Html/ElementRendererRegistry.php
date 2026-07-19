<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Html;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedNode;
use InvalidArgumentException;

final readonly class ElementRendererRegistry
{
    public function definition(ResolvedNode $node): ElementDefinition
    {
        return match ($node->type) {
            'flow-section' => new ElementDefinition('section', 'db-section'),
            'flow-container' => new ElementDefinition('div', 'db-container'),
            'heading' => new ElementDefinition('h' . $this->headingLevel($node), 'db-heading'),
            'static-text' => new ElementDefinition('p', 'db-static-text'),
            'paragraph' => new ElementDefinition('p', 'db-paragraph'),
            'divider' => new ElementDefinition('hr', 'db-divider', true),
            'spacer' => new ElementDefinition('div', 'db-spacer'),
            'page-break' => new ElementDefinition('div', 'db-page-break'),
            default => throw new InvalidArgumentException('The resolved node type has no HTML renderer.'),
        };
    }

    private function headingLevel(ResolvedNode $node): int
    {
        $level = $node->attributes['level'] ?? 2;

        return is_int($level) && $level >= 1 && $level <= 6 ? $level : 2;
    }
}
