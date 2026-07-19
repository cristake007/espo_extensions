<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\FormattedVariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\MissingValueDisposition;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormatContext;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormatter;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableIdentity;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariablePresentation;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueState;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableValueType;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\ConditionEvaluator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\ConditionTarget;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\RequiredVariableFailure;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\VisibilityCondition;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ResolvedStyleProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\DocumentValue;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\DocumentWarning;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedDocument;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedInline;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\ResolvedNode;
use InvalidArgumentException;

final readonly class DocumentTreeBuilder
{
    public function __construct(
        private VariableFormatter $formatter,
        private ConditionEvaluator $conditions,
        private ResolvedStyleProvider $styles,
    ) {}

    /**
     * @param array<string, mixed> $layout
     * @param list<DocumentValue> $values
     */
    public function build(array $layout, array $values, VariableFormatContext $formatContext): ResolvedDocument
    {
        $valueMap = $this->valueMap($values);
        $locations = [];
        $this->index($layout['sections'] ?? [], null, null, $locations);
        $hidden = $this->conditionHidden($locations, $valueMap);
        $this->addPolicyHidden($locations, $hidden, $valueMap, $formatContext);
        $warnings = [];
        $defaults = $layout['document']['defaults'] ?? [];
        $sections = [];

        foreach ($layout['sections'] ?? [] as $index => $section) {
            $node = $this->node($section, "/sections/$index", $hidden, $valueMap, $formatContext, $defaults, $warnings);
            if ($node !== null) {
                $sections[] = $node;
            }
        }

        $header = $this->region($layout['header'] ?? [], '/header', $valueMap, $formatContext, $defaults, $warnings);
        $footer = $this->region($layout['footer'] ?? [], '/footer', $valueMap, $formatContext, $defaults, $warnings);

        return new ResolvedDocument(
            $layout['document']['page'] ?? [],
            $defaults,
            $sections,
            $warnings,
            $header,
            $footer,
            $layout['document']['chrome'] ?? [],
        );
    }

    /**
     * @param mixed $nodes
     * @param array<string, DocumentValue> $values
     * @param list<DocumentWarning> $warnings
     * @return list<ResolvedNode>
     */
    private function region(
        mixed $nodes,
        string $path,
        array $values,
        VariableFormatContext $context,
        array $defaults,
        array &$warnings,
    ): array {
        if (!is_array($nodes) || !array_is_list($nodes)) return [];
        $resolved = [];
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) continue;
            $item = $this->node($node, "$path/$index", [], $values, $context, $defaults, $warnings);
            if ($item !== null) $resolved[] = $item;
        }

        return $resolved;
    }

    /** @param list<DocumentValue> $values @return array<string, DocumentValue> */
    private function valueMap(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!$value instanceof DocumentValue) {
                throw new InvalidArgumentException('A resolved document value has an invalid type.');
            }
            $result[$value->key()] = $value;
        }

        return $result;
    }

    /**
     * @param mixed $nodes
     * @param array<string, array{node: array<string, mixed>, parentId: ?string, sectionId: string}> $locations
     */
    private function index(mixed $nodes, ?string $parentId, ?string $sectionId, array &$locations): void
    {
        if (!is_array($nodes) || !array_is_list($nodes)) return;
        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['id'] ?? null)) continue;
            $currentSection = $node['type'] === 'flow-section' ? $node['id'] : $sectionId;
            if ($currentSection === null) continue;
            $locations[$node['id']] = ['node'=>$node, 'parentId'=>$parentId, 'sectionId'=>$currentSection];
            $this->index($node['children'] ?? [], $node['id'], $currentSection, $locations);
        }
    }

    /**
     * @param array<string, array{node: array<string, mixed>, parentId: ?string, sectionId: string}> $locations
     * @param array<string, DocumentValue> $values
     * @return array<string, true>
     */
    private function conditionHidden(array $locations, array $values): array
    {
        $hidden = [];
        foreach ($locations as $id => $location) {
            if (!is_array($location['node']['condition'] ?? null)) continue;
            $condition = VisibilityCondition::fromArray($location['node']['condition']);
            $result = $this->conditions->evaluate($condition, function (array $identity) use ($values): ?VariableValue {
                return $values[json_encode($identity, JSON_THROW_ON_ERROR)]->value ?? null;
            });
            if (!$result->visible) {
                $target = $result->target === ConditionTarget::Parent ? ($location['parentId'] ?? $id) : $id;
                $hidden[$target] = true;
            }
        }

        return $hidden;
    }

    /**
     * @param array<string, array{node: array<string, mixed>, parentId: ?string, sectionId: string}> $locations
     * @param array<string, true> $hidden
     * @param array<string, DocumentValue> $values
     */
    private function addPolicyHidden(
        array $locations,
        array &$hidden,
        array $values,
        VariableFormatContext $context,
    ): void {
        foreach ($locations as $id => $location) {
            if ($this->isHidden($id, $locations, $hidden)) continue;
            foreach ($this->nodeVariables($location['node']) as $item) {
                $formatted = $this->formatVariable($item, $values, $context);
                $target = match ($formatted->disposition) {
                    MissingValueDisposition::HideElement => $id,
                    MissingValueDisposition::HideRow => $location['parentId'] ?? $id,
                    MissingValueDisposition::HideSection => $location['sectionId'],
                    default => null,
                };
                if ($target !== null) $hidden[$target] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, true> $hidden
     * @param array<string, DocumentValue> $values
     * @param array<string, mixed> $defaults
     * @param list<DocumentWarning> $warnings
     */
    private function node(
        array $node,
        string $path,
        array $hidden,
        array $values,
        VariableFormatContext $context,
        array $defaults,
        array &$warnings,
    ): ?ResolvedNode {
        if (isset($hidden[$node['id']])) return null;
        $children = [];
        foreach ($node['children'] ?? [] as $index => $child) {
            $resolved = $this->node($child, "$path/children/$index", $hidden, $values, $context, $defaults, $warnings);
            if ($resolved !== null) $children[] = $resolved;
        }
        $inline = $this->resolveInlineSequence(
            $node['content'] ?? [],
            "$path/content",
            $node['id'],
            $values,
            $context,
            $warnings,
        );
        if ($node['type'] === 'static-text') {
            $inline[] = new ResolvedInline('text', $node['text']);
        }
        if ($node['type'] === 'variable') {
            $resolvedVariable = $this->resolveVariable(
                $node,
                $path,
                $node['id'],
                $values,
                $context,
                $warnings,
            );
            if ($resolvedVariable !== null) $inline[] = $resolvedVariable;
        }
        $attributes = array_diff_key($node, array_flip([
            'id', 'type', 'children', 'content', 'text', 'label', 'identity', 'presentation',
            'style', 'condition',
        ]));
        ksort($attributes, SORT_STRING);

        return new ResolvedNode(
            $node['id'],
            $node['type'],
            $this->styles->resolve($defaults, is_array($node['style'] ?? null) ? $node['style'] : []),
            $attributes,
            $inline,
            $children,
            true,
        );
    }

    /**
     * @param array<string, mixed> $node
     * @return list<array<string, mixed>>
     */
    private function nodeVariables(array $node): array
    {
        if (($node['type'] ?? null) === 'variable') return [$node];

        return $this->variablesInContent($node['content'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    private function variablesInContent(mixed $content): array
    {
        if (!is_array($content) || !array_is_list($content)) return [];
        $variables = [];

        foreach ($content as $item) {
            if (!is_array($item) || array_is_list($item)) continue;
            if (($item['type'] ?? null) === 'variable') {
                $variables[] = $item;
                continue;
            }
            if (($item['type'] ?? null) !== 'list') continue;
            foreach ($item['items'] ?? [] as $listItem) {
                array_push($variables, ...$this->variablesInContent($listItem));
            }
        }

        return $variables;
    }

    /**
     * @param list<mixed> $content
     * @param array<string, DocumentValue> $values
     * @param list<DocumentWarning> $warnings
     * @return list<ResolvedInline>
     */
    private function resolveInlineSequence(
        array $content,
        string $path,
        string $nodeId,
        array $values,
        VariableFormatContext $context,
        array &$warnings,
    ): array {
        $resolved = [];

        foreach ($content as $index => $item) {
            if (!is_array($item) || array_is_list($item)) continue;
            $itemPath = "$path/$index";

            if (($item['type'] ?? null) === 'text') {
                $resolved[] = new ResolvedInline(
                    'text',
                    is_string($item['text'] ?? null) ? $item['text'] : '',
                    is_array($item['marks'] ?? null) ? $item['marks'] : [],
                    is_string($item['color'] ?? null) ? $item['color'] : null,
                );
            } elseif (($item['type'] ?? null) === 'break') {
                $resolved[] = new ResolvedInline('break', "\n");
            } elseif (($item['type'] ?? null) === 'variable') {
                $variable = $this->resolveVariable($item, $itemPath, $nodeId, $values, $context, $warnings);
                if ($variable !== null) $resolved[] = $variable;
            } elseif (($item['type'] ?? null) === 'list') {
                $listItems = [];
                foreach ($item['items'] ?? [] as $listIndex => $listItem) {
                    if (!is_array($listItem) || !array_is_list($listItem)) continue;
                    $listItems[] = $this->resolveInlineSequence(
                        $listItem,
                        "$itemPath/items/$listIndex",
                        $nodeId,
                        $values,
                        $context,
                        $warnings,
                    );
                }
                $resolved[] = new ResolvedInline(
                    'list',
                    '',
                    listStyle: ($item['style'] ?? null) === 'numbered' ? 'numbered' : 'bulleted',
                    items: $listItems,
                );
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, DocumentValue> $values
     * @param list<DocumentWarning> $warnings
     */
    private function resolveVariable(
        array $item,
        string $path,
        string $nodeId,
        array $values,
        VariableFormatContext $context,
        array &$warnings,
    ): ?ResolvedInline {
        $placeholder = $this->rendererPlaceholder($item['identity'] ?? null);
        if ($placeholder === 'pageCount') throw new PageCountUnavailable();
        if ($placeholder === 'pageNumber') return new ResolvedInline('page-number', '');
        $formatted = $this->formatVariable($item, $values, $context);
        if ($formatted->disposition === MissingValueDisposition::Failure) {
            throw new RequiredVariableFailure([json_encode($item['identity'], JSON_THROW_ON_ERROR)]);
        }
        if ($formatted->disposition === MissingValueDisposition::Warning) {
            $warnings[] = new DocumentWarning('variable.' . $formatted->state->value, $path, $nodeId);
        }
        if (in_array($formatted->disposition, [MissingValueDisposition::HideElement,
            MissingValueDisposition::HideRow, MissingValueDisposition::HideSection], true)) {
            return null;
        }
        $value = $this->findValue($item['identity'], $values);
        $provenance = $value->value->state === VariableValueState::Present ? $value->provenance : null;

        return new ResolvedInline('variable', $formatted->text ?? '', [], null, $provenance);
    }

    private function rendererPlaceholder(mixed $identity): ?string
    {
        if (!is_array($identity) || array_is_list($identity) ||
            ($identity['source'] ?? null) !== 'system' || ($identity['type'] ?? null) !== 'system' ||
            !is_array($identity['path'] ?? null) || count($identity['path']) !== 1) {
            return null;
        }

        $name = $identity['path'][0] ?? null;

        return is_string($name) && in_array($name, ['pageNumber', 'pageCount'], true) ? $name : null;
    }

    /** @param array<string, mixed> $item @param array<string, DocumentValue> $values */
    private function formatVariable(
        array $item,
        array $values,
        VariableFormatContext $context,
    ): FormattedVariableValue
    {
        $value = $this->findValue($item['identity'], $values);
        $presentation = VariablePresentation::fromArray($item['presentation']);

        return $this->formatter->format($value->value, $presentation, $context);
    }

    /** @param array<string, mixed> $identity @param array<string, DocumentValue> $values */
    private function findValue(array $identity, array $values): DocumentValue
    {
        $key = json_encode(VariableIdentity::fromArray($identity)->toArray(), JSON_THROW_ON_ERROR);

        return $values[$key] ?? new DocumentValue(
            VariableIdentity::fromArray($identity),
            new VariableValue(VariableValueType::Text, VariableValueState::Missing),
        );
    }

    /**
     * @param array<string, array{node: array<string, mixed>, parentId: ?string, sectionId: string}> $locations
     * @param array<string, true> $hidden
     */
    private function isHidden(string $id, array $locations, array $hidden): bool
    {
        while (isset($locations[$id])) {
            if (isset($hidden[$id])) return true;
            $id = $locations[$id]['parentId'] ?? '';
        }

        return false;
    }
}
