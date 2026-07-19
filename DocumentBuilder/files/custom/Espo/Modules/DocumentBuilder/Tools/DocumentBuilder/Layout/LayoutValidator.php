<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Condition\VisibilityCondition;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeKind;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\NodeRegistry;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Node\UnknownNodeType;

final readonly class LayoutValidator
{
    private const MAX_ERRORS = 100;

    private const PAGE_DIMENSIONS_MM = [
        'A4' => [210.0, 297.0],
        'Letter' => [215.9, 279.4],
        'Legal' => [215.9, 355.6],
    ];

    private const ROOT_KEYS = [
        'schemaVersion',
        'capabilities',
        'document',
        'dataSource',
        'header',
        'sections',
        'footer',
    ];

    public function __construct(
        private Settings $settings,
        private NodeRegistry $nodeRegistry,
        private CapabilityRegistry $capabilityRegistry,
    ) {}

    /** @param array<string, mixed> $layout */
    public function validate(array $layout): ValidationResult
    {
        $errors = [];
        $this->rejectUnknownKeys($layout, self::ROOT_KEYS, '', $errors);

        if (($layout['schemaVersion'] ?? null) !== SchemaVersion::current()->value) {
            $this->add($errors, 'schemaVersion.unsupported', '/schemaVersion');
        }

        $capabilities = $layout['capabilities'] ?? null;

        $declaredCapabilities = [];

        if (!is_array($capabilities) || !array_is_list($capabilities)) {
            $this->add($errors, 'capabilities.type', '/capabilities');
        } else {
            foreach ($capabilities as $index => $marker) {
                $capability = is_string($marker) ? Capability::tryFrom($marker) : null;

                if ($capability === null || isset($declaredCapabilities[$marker])) {
                    $this->add($errors, 'capability.unsupported', "/capabilities/$index");

                    continue;
                }

                if ($this->capabilityRegistry->status($capability) === CapabilityStatus::SchemaOnly) {
                    $this->add($errors, 'capability.unavailable', "/capabilities/$index");
                }

                $declaredCapabilities[$marker] = true;
            }
        }

        $this->validateDocument($layout['document'] ?? null, $errors);
        $this->validateSource($layout['dataSource'] ?? null, $errors);

        $ids = [];
        $elementCount = 0;

        foreach (['header', 'sections', 'footer'] as $region) {
            $nodes = $layout[$region] ?? null;

            if (!is_array($nodes) || !array_is_list($nodes)) {
                $this->add($errors, 'sequence.type', "/$region");

                continue;
            }

            if ($region === 'sections' && count($nodes) > $this->settings->maxSections()) {
                $this->add($errors, 'sections.limit', '/sections');
                $nodes = array_slice($nodes, 0, $this->settings->maxSections());
            }

            $kind = $region === 'sections' ? NodeKind::Section : NodeKind::Element;

            foreach ($nodes as $index => $node) {
                if ($kind === NodeKind::Element && $elementCount > $this->settings->maxElements()) {
                    break;
                }

                $this->validateNode(
                    $node,
                    $kind,
                    "/$region/$index",
                    1,
                    $ids,
                    $elementCount,
                    $declaredCapabilities,
                    $errors,
                );
            }
        }

        if ($elementCount > $this->settings->maxElements()) {
            $this->add($errors, 'elements.limit', '/');
        }

        if (isset($declaredCapabilities[Capability::FlowLayout->value]) &&
            ($layout['sections'] ?? []) === [] && ($layout['header'] ?? []) === [] &&
            ($layout['footer'] ?? []) === []) {
            $this->add($errors, 'capability.unused', '/capabilities');
        }

        $this->validatePageChromeGeometry($layout, $errors);

        return new ValidationResult($errors);
    }

    /** @param list<ValidationError> $errors */
    private function validateDocument(mixed $document, array &$errors): void
    {
        if (!$this->isObject($document)) {
            $this->add($errors, 'document.type', '/document');

            return;
        }

        $this->rejectUnknownKeys(
            $document,
            ['page', 'defaults', 'chrome', 'titlePattern', 'filenamePattern', 'style'],
            '/document',
            $errors,
        );
        $page = $document['page'] ?? null;

        if (!$this->isObject($page)) {
            $this->add($errors, 'page.type', '/document/page');
        } else {
            $this->rejectUnknownKeys($page, ['size', 'orientation', 'margins'], '/document/page', $errors);

            if (!array_key_exists((string) ($page['size'] ?? ''), $this->pageDimensions())) {
                $this->add($errors, 'page.size', '/document/page/size');
            }

            if (!in_array($page['orientation'] ?? null, ['portrait', 'landscape'], true)) {
                $this->add($errors, 'page.orientation', '/document/page/orientation');
            }

            $this->validateBox($page['margins'] ?? null, '/document/page/margins', $errors);
            $this->validatePrintableArea($page, $errors);
        }

        $defaults = $document['defaults'] ?? null;

        if (!$this->isObject($defaults)) {
            $this->add($errors, 'defaults.type', '/document/defaults');

            return;
        }

        $this->rejectUnknownKeys(
            $defaults,
            ['fontFamily', 'fontSize', 'color', 'lineHeight', 'locale', 'timezone'],
            '/document/defaults',
            $errors,
        );

        $font = $defaults['fontFamily'] ?? null;

        if (!is_string($font) || !in_array($font, $this->settings->allowedFontList(), true)) {
            $this->add($errors, 'defaults.fontFamily', '/document/defaults/fontFamily');
        }

        $this->validateMeasurement($defaults['fontSize'] ?? null, Unit::Point, '/document/defaults/fontSize', $errors);

        if (!is_string($defaults['color'] ?? null) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $defaults['color']) !== 1) {
            $this->add($errors, 'value.color', '/document/defaults/color');
        }

        $lineHeight = $defaults['lineHeight'] ?? null;

        if (
            (!is_int($lineHeight) && !is_float($lineHeight)) ||
            $lineHeight < 0.5 ||
            $lineHeight > 5
        ) {
            $this->add($errors, 'defaults.lineHeight', '/document/defaults/lineHeight');
        }

        if (!is_string($defaults['locale'] ?? null) || preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $defaults['locale']) !== 1) {
            $this->add($errors, 'defaults.locale', '/document/defaults/locale');
        }

        if (
            !is_string($defaults['timezone'] ?? null) ||
            preg_match('/^(?:UTC|[A-Za-z_]+(?:\/[A-Za-z0-9_+.-]+)+)$/D', $defaults['timezone']) !== 1
        ) {
            $this->add($errors, 'defaults.timezone', '/document/defaults/timezone');
        }

        $this->validatePageChrome($document['chrome'] ?? null, $errors);

        $titlePattern = $document['titlePattern'] ?? null;

        if (!is_string($titlePattern) || mb_strlen($titlePattern) > 255) {
            $this->add($errors, 'document.titlePattern', '/document/titlePattern');
        }

        $filenamePattern = $document['filenamePattern'] ?? null;

        if (
            !is_string($filenamePattern) ||
            $filenamePattern === '' ||
            mb_strlen($filenamePattern) > 255 ||
            preg_match('/[\\\\\/\x00-\x1F]/', $filenamePattern) === 1
        ) {
            $this->add($errors, 'document.filenamePattern', '/document/filenamePattern');
        }

        if (array_key_exists('style', $document)) {
            $this->validateStyle($document['style'], '/document/style', null, $errors);
        }
    }

    /** @param list<ValidationError> $errors */
    private function validatePageChrome(mixed $chrome, array &$errors): void
    {
        if (!$this->isObject($chrome)) {
            $this->add($errors, 'pageChrome.type', '/document/chrome');

            return;
        }

        $this->rejectUnknownKeys($chrome, ['header', 'footer'], '/document/chrome', $errors);

        foreach (['header', 'footer'] as $region) {
            $settings = $chrome[$region] ?? null;
            $path = "/document/chrome/$region";

            if (!$this->isObject($settings)) {
                $this->add($errors, 'pageChrome.region', $path);

                continue;
            }

            $this->rejectUnknownKeys(
                $settings,
                ['height', 'showOnFirstPage', 'disableOnFullPage'],
                $path,
                $errors,
            );
            $this->validateBoundedMillimetres(
                $settings['height'] ?? null,
                0.0,
                100.0,
                "$path/height",
                'pageChrome.height',
                null,
                $errors,
            );

            foreach (['showOnFirstPage', 'disableOnFullPage'] as $flag) {
                if (!is_bool($settings[$flag] ?? null)) {
                    $this->add($errors, "pageChrome.$flag", "$path/$flag");
                }
            }
        }
    }

    /** @param array<string, mixed> $layout @param list<ValidationError> $errors */
    private function validatePageChromeGeometry(array $layout, array &$errors): void
    {
        $margins = $layout['document']['page']['margins'] ?? null;
        $chrome = $layout['document']['chrome'] ?? null;

        if (!$this->isObject($margins) || !$this->isObject($chrome)) {
            return;
        }

        foreach (['header' => 'top', 'footer' => 'bottom'] as $region => $edge) {
            $nodes = $layout[$region] ?? null;
            $height = $chrome[$region]['height']['value'] ?? null;
            $margin = $margins[$edge]['value'] ?? null;

            if (!is_array($nodes) || !is_int($height) && !is_float($height) ||
                !is_int($margin) && !is_float($margin)) {
                continue;
            }

            if (($nodes === [] && (float) $height !== 0.0) || ($nodes !== [] && (float) $height <= 0.0)) {
                $this->add($errors, 'pageChrome.enabledHeight', "/document/chrome/$region/height");
            }

            if ((float) $height > (float) $margin) {
                $this->add($errors, 'pageChrome.marginReserved', "/document/chrome/$region/height");
            }
        }
    }

    /**
     * @param array<string, mixed> $page
     * @param list<ValidationError> $errors
     */
    private function validatePrintableArea(array $page, array &$errors): void
    {
        $size = $page['size'] ?? null;
        $orientation = $page['orientation'] ?? null;
        $margins = $page['margins'] ?? null;

        if (
            !is_string($size) ||
            !isset($this->pageDimensions()[$size]) ||
            !in_array($orientation, ['portrait', 'landscape'], true) ||
            !$this->isObject($margins)
        ) {
            return;
        }

        $values = [];

        foreach (['top', 'right', 'bottom', 'left'] as $edge) {
            $measurement = $margins[$edge] ?? null;

            if (
                !$this->isObject($measurement) ||
                ($measurement['unit'] ?? null) !== Unit::Millimetre->value ||
                (!is_int($measurement['value'] ?? null) && !is_float($measurement['value'] ?? null))
            ) {
                return;
            }

            $values[$edge] = (float) $measurement['value'];
        }

        [$width, $height] = $this->pageDimensions()[$size];

        if ($orientation === 'landscape') {
            [$width, $height] = [$height, $width];
        }

        if ($values['left'] + $values['right'] >= $width) {
            $this->add($errors, 'page.printableWidth', '/document/page/margins');
        }

        if ($values['top'] + $values['bottom'] >= $height) {
            $this->add($errors, 'page.printableHeight', '/document/page/margins');
        }
    }

    /** @return array<string, array{float, float}> */
    private function pageDimensions(): array
    {
        $dimensions = self::PAGE_DIMENSIONS_MM;

        foreach ($this->settings->customPageSizeList() as $definition) {
            $dimensions[$definition['id']] = [
                (float) $definition['widthMm'],
                (float) $definition['heightMm'],
            ];
        }

        return $dimensions;
    }

    /** @param list<ValidationError> $errors */
    private function validateSource(mixed $source, array &$errors): void
    {
        if (!$this->isObject($source)) {
            $this->add($errors, 'source.type', '/dataSource');

            return;
        }

        $type = $source['type'] ?? null;

        if ($type === 'none') {
            $this->rejectUnknownKeys($source, ['type'], '/dataSource', $errors);

            return;
        }

        if ($type === 'entity') {
            $this->rejectUnknownKeys(
                $source,
                ['type', 'entityType', 'relationshipDepth'],
                '/dataSource',
                $errors,
            );

            if (
                !is_string($source['entityType'] ?? null) ||
                preg_match('/^[A-Za-z][A-Za-z0-9]{0,99}$/D', $source['entityType']) !== 1
            ) {
                $this->add($errors, 'source.entityType', '/dataSource/entityType');
            } else {
                $enabled = $this->settings->enabledSourceEntityTypeList();
                $disabled = $this->settings->disabledSourceEntityTypeList();

                if (
                    ($enabled !== [] && !in_array($source['entityType'], $enabled, true)) ||
                    in_array($source['entityType'], $disabled, true)
                ) {
                    $this->add($errors, 'source.entityTypeDisabled', '/dataSource/entityType');
                }
            }

            $depth = $source['relationshipDepth'] ?? null;

            if (!is_int($depth) || $depth < 1 || $depth > $this->settings->maxRelationshipDepth()) {
                $this->add($errors, 'source.relationshipDepth', '/dataSource/relationshipDepth');
            }

            return;
        }

        if ($type === 'spreadsheet') {
            $this->rejectUnknownKeys($source, ['type', 'format', 'worksheet'], '/dataSource', $errors);
            $format = $source['format'] ?? null;

            if (!in_array($format, ['csv', 'xlsx'], true)) {
                $this->add($errors, 'source.spreadsheetFormat', '/dataSource/format');
            }

            $worksheet = $source['worksheet'] ?? null;

            if (
                ($format === 'csv' && array_key_exists('worksheet', $source)) ||
                ($worksheet !== null && (!is_string($worksheet) || trim($worksheet) === '' || mb_strlen($worksheet) > 100))
            ) {
                $this->add($errors, 'source.worksheet', '/dataSource/worksheet');
            }

            return;
        }

        $this->add($errors, 'source.typeValue', '/dataSource/type');
    }

    /**
     * @param array<string, true> $ids
     * @param array<string, true> $declaredCapabilities
     * @param list<ValidationError> $errors
     */
    private function validateNode(
        mixed $node,
        NodeKind $kind,
        string $indexPath,
        int $depth,
        array &$ids,
        int &$elementCount,
        array $declaredCapabilities,
        array &$errors,
    ): void {
        if ($kind === NodeKind::Element) {
            ++$elementCount;

            if ($elementCount > $this->settings->maxElements()) {
                return;
            }
        }

        if (!$this->isObject($node)) {
            $this->add($errors, 'node.type', $indexPath);

            return;
        }

        $id = $node['id'] ?? null;
        $elementId = null;
        $path = $indexPath;

        try {
            if (!is_string($id)) {
                throw new \InvalidArgumentException();
            }

            $elementId = (new StableId($id))->value();
            $path = preg_replace('/\/\d+$/D', '[id=' . $elementId . ']', $indexPath) ?? $indexPath;

            if (isset($ids[$elementId])) {
                $this->add($errors, 'node.idDuplicate', "$path/id", $elementId);
            }

            $ids[$elementId] = true;
        } catch (\InvalidArgumentException) {
            $this->add($errors, 'node.id', "$indexPath/id");
        }

        if ($depth > $this->settings->maxNestingDepth()) {
            $this->add($errors, 'node.depth', $path, $elementId);
        }

        $type = $node['type'] ?? null;

        if (!is_string($type)) {
            $this->add($errors, 'node.typeName', "$path/type", $elementId);
        } else {
            try {
                $definition = $this->nodeRegistry->require($kind, $type);
                $this->capabilityRegistry->requireUsable($definition->requiredCapabilities());

                foreach ($definition->requiredCapabilities() as $requiredCapability) {
                    if (!isset($declaredCapabilities[$requiredCapability->value])) {
                        $this->add($errors, 'capability.missing', '/capabilities', $elementId);
                    }
                }
            } catch (UnknownNodeType) {
                $this->add($errors, 'node.unknownType', "$path/type", $elementId);
            } catch (CapabilityUnavailable) {
                $this->add($errors, 'node.capabilityUnavailable', "$path/type", $elementId);
            }
        }

        $this->validateFlowNode($node, $kind, $path, $elementId, $errors);
        $this->validateContentNode($node, $kind, $path, $elementId, $errors);
        $this->validateVariableElement($node, $kind, $path, $elementId, $errors);
        $this->validateBasicFlowElement($node, $kind, $path, $elementId, $errors);
        if (array_key_exists('condition', $node)) {
            try {
                VisibilityCondition::fromArray($node['condition']);
            } catch (\InvalidArgumentException|\TypeError) {
                $this->add($errors, 'condition.invalid', "$path/condition", $elementId);
            }
        }
        if (array_key_exists('style', $node)) {
            $this->validateStyle($node['style'], "$path/style", $elementId, $errors);
        }

        if (!array_key_exists('children', $node)) {
            return;
        }

        $children = $node['children'];

        if (!is_array($children) || !array_is_list($children)) {
            $this->add($errors, 'node.children', "$path/children", $elementId);

            return;
        }

        foreach ($children as $index => $child) {
            if ($elementCount > $this->settings->maxElements()) {
                break;
            }

            $this->validateNode(
                $child,
                NodeKind::Element,
                "$path/children/$index",
                $depth + 1,
                $ids,
                $elementCount,
                $declaredCapabilities,
                $errors,
            );
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param list<ValidationError> $errors
     */
    private function validateFlowNode(
        array $node,
        NodeKind $kind,
        string $path,
        ?string $elementId,
        array &$errors,
    ): void {
        $type = $node['type'] ?? null;

        if ($type !== 'flow-section' && $type !== 'flow-container') {
            return;
        }

        $allowedKeys = ['id', 'type', 'children', 'margin', 'padding', 'minHeight', 'keepTogether', 'style', 'condition'];

        if ($type === 'flow-section') {
            $allowedKeys[] = 'startNewPage';
        }

        $this->rejectUnknownKeys($node, $allowedKeys, $path, $errors);

        if (
            ($type === 'flow-section' && $kind !== NodeKind::Section) ||
            ($type === 'flow-container' && $kind !== NodeKind::Element)
        ) {
            $this->add($errors, 'flow.parent', "$path/type", $elementId);
        }

        $this->validateBox($node['margin'] ?? null, "$path/margin", $errors);
        $this->validateBox($node['padding'] ?? null, "$path/padding", $errors);
        $this->validateMeasurement(
            $node['minHeight'] ?? null,
            Unit::Millimetre,
            "$path/minHeight",
            $errors,
        );

        if (!is_bool($node['keepTogether'] ?? null)) {
            $this->add($errors, 'flow.keepTogether', "$path/keepTogether", $elementId);
        }

        if ($type === 'flow-section' && !is_bool($node['startNewPage'] ?? null)) {
            $this->add($errors, 'flow.startNewPage', "$path/startNewPage", $elementId);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param list<ValidationError> $errors
     */
    private function validateContentNode(
        array $node,
        NodeKind $kind,
        string $path,
        ?string $elementId,
        array &$errors,
    ): void {
        $type = $node['type'] ?? null;

        if (!in_array($type, ['heading', 'static-text', 'paragraph'], true)) {
            return;
        }

        if ($kind !== NodeKind::Element ||
            (!$this->isFlowChildPath($path) && !$this->isPageChromePath($path))) {
            $this->add($errors, 'content.parent', "$path/type", $elementId);
        }

        if ($this->isPageChromePath($path) && $type === 'heading') {
            $this->add($errors, 'pageChrome.element', "$path/type", $elementId);
        }
        if ($type === 'static-text') {
            $this->rejectUnknownKeys($node, ['id', 'type', 'text', 'content', 'style', 'condition'], $path, $errors);
            $hasText = array_key_exists('text', $node);
            $hasContent = array_key_exists('content', $node);

            if ($hasText === $hasContent) {
                $this->add($errors, 'content.representation', $path, $elementId);
            } elseif ($hasText) {
                $this->validatePlainText($node['text'], "$path/text", 'content.text', $elementId, $errors);
            } else {
                $this->validateInlineContent(
                    $node['content'],
                    "$path/content",
                    $elementId,
                    $errors,
                    true,
                );
            }

            return;
        }

        $allowedKeys = $type === 'heading' ?
            ['id', 'type', 'content', 'level', 'keepWithNext', 'style', 'condition'] :
            ['id', 'type', 'content', 'alignment', 'style', 'condition'];
        $this->rejectUnknownKeys($node, $allowedKeys, $path, $errors);
        $this->validateInlineContent(
            $node['content'] ?? null,
            "$path/content",
            $elementId,
            $errors,
            $type === 'paragraph',
        );

        if ($type === 'heading') {
            if (!is_int($node['level'] ?? null) || $node['level'] < 1 || $node['level'] > 6) {
                $this->add($errors, 'heading.level', "$path/level", $elementId);
            }

            if (!is_bool($node['keepWithNext'] ?? null)) {
                $this->add($errors, 'heading.keepWithNext', "$path/keepWithNext", $elementId);
            }

            return;
        }

        if (!in_array($node['alignment'] ?? null, ['start', 'center', 'end', 'justify'], true)) {
            $this->add($errors, 'paragraph.alignment', "$path/alignment", $elementId);
        }
    }

    /** @param array<string, mixed> $node @param list<ValidationError> $errors */
    private function validateVariableElement(
        array $node,
        NodeKind $kind,
        string $path,
        ?string $elementId,
        array &$errors,
    ): void {
        if (($node['type'] ?? null) !== 'variable') {
            return;
        }

        if ($kind !== NodeKind::Element || !$this->isFlowChildPath($path)) {
            $this->add($errors, 'variable.parent', "$path/type", $elementId);
        }

        $this->rejectUnknownKeys(
            $node,
            ['id', 'type', 'label', 'identity', 'presentation', 'style', 'condition'],
            $path,
            $errors,
        );
        $this->validatePlainText(
            $node['label'] ?? null,
            "$path/label",
            'variable.label',
            $elementId,
            $errors,
            100,
        );

        if (($node['label'] ?? null) === '') {
            $this->add($errors, 'variable.label', "$path/label", $elementId);
        }
        if (!$this->isScalarVariableIdentity($node['identity'] ?? null)) {
            $this->add($errors, 'variable.identity', "$path/identity", $elementId);
        }
        if (!$this->isVariablePresentation($node['presentation'] ?? null)) {
            $this->add($errors, 'variable.presentation', "$path/presentation", $elementId);
        }
    }

    /** @param array<string, mixed> $node @param list<ValidationError> $errors */
    private function validateBasicFlowElement(
        array $node,
        NodeKind $kind,
        string $path,
        ?string $elementId,
        array &$errors,
    ): void {
        $type = $node['type'] ?? null;

        if (!in_array($type, ['divider', 'spacer', 'page-break'], true)) {
            return;
        }

        if ($kind !== NodeKind::Element ||
            (!$this->isFlowChildPath($path) && !$this->isPageChromePath($path))) {
            $this->add($errors, 'flowElement.parent', "$path/type", $elementId);
        }

        if ($this->isPageChromePath($path) && $type !== 'divider') {
            $this->add($errors, 'pageChrome.element', "$path/type", $elementId);
        }

        if ($type === 'page-break') {
            $this->rejectUnknownKeys($node, ['id', 'type', 'style', 'condition'], $path, $errors);

            return;
        }

        if ($type === 'spacer') {
            $this->rejectUnknownKeys($node, ['id', 'type', 'height', 'style', 'condition'], $path, $errors);
            $this->validateBoundedMillimetres(
                $node['height'] ?? null, 0.1, 500.0, "$path/height", 'spacer.height', $elementId, $errors,
            );

            return;
        }

        $this->rejectUnknownKeys(
            $node,
            ['id', 'type', 'orientation', 'lineStyle', 'color', 'thickness', 'length', 'style', 'condition'],
            $path,
            $errors,
        );

        if (!in_array($node['orientation'] ?? null, ['horizontal', 'vertical'], true)) {
            $this->add($errors, 'divider.orientation', "$path/orientation", $elementId);
        }

        if (!in_array($node['lineStyle'] ?? null, ['solid', 'dashed', 'dotted', 'double'], true)) {
            $this->add($errors, 'divider.style', "$path/lineStyle", $elementId);
        }

        if (!is_string($node['color'] ?? null) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $node['color']) !== 1) {
            $this->add($errors, 'value.color', "$path/color", $elementId);
        }

        $this->validateBoundedMillimetres(
            $node['thickness'] ?? null, 0.1, 20.0, "$path/thickness", 'divider.thickness', $elementId, $errors,
        );
        $this->validateBoundedMillimetres(
            $node['length'] ?? null, 1.0, 2000.0, "$path/length", 'divider.length', $elementId, $errors,
        );
    }

    /** @param list<ValidationError> $errors */
    private function validateBoundedMillimetres(
        mixed $measurement,
        float $minimum,
        float $maximum,
        string $path,
        string $code,
        ?string $elementId,
        array &$errors,
    ): void {
        $this->validateMeasurement($measurement, Unit::Millimetre, $path, $errors);

        if (!$this->isObject($measurement)) {
            return;
        }

        $value = $measurement['value'] ?? null;

        if ((!is_int($value) && !is_float($value)) || $value < $minimum || $value > $maximum) {
            $this->add($errors, $code, "$path/value", $elementId);
        }
    }

    private function isFlowChildPath(string $path): bool
    {
        return (str_starts_with($path, '/sections/') || str_starts_with($path, '/sections[')) &&
            (str_contains($path, '/children/') || str_contains($path, '/children['));
    }

    private function isPageChromePath(string $path): bool
    {
        return str_starts_with($path, '/header/') || str_starts_with($path, '/header[') ||
            str_starts_with($path, '/footer/') || str_starts_with($path, '/footer[');
    }

    /** @param list<ValidationError> $errors */
    private function validateStyle(mixed $style, string $path, ?string $elementId, array &$errors): void
    {
        if (!$this->isObject($style)) {
            $this->add($errors, 'style.type', $path, $elementId);
            return;
        }
        $this->rejectUnknownKeys($style, [
            'margin', 'padding', 'backgroundColor', 'border', 'opacity',
            'horizontalAlignment', 'verticalAlignment', 'width', 'height', 'color',
            'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'textDecoration',
            'lineHeight', 'letterSpacing', 'textTransform',
        ], $path, $errors);
        foreach (['margin', 'padding'] as $key) if (array_key_exists($key, $style)) {
            $this->validateBox($style[$key], "$path/$key", $errors);
        }
        foreach (['backgroundColor', 'color'] as $key) if (array_key_exists($key, $style) &&
            (!is_string($style[$key]) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $style[$key]) !== 1)) {
            $this->add($errors, 'value.color', "$path/$key", $elementId);
        }
        if (array_key_exists('opacity', $style) && ((!is_int($style['opacity']) && !is_float($style['opacity'])) || $style['opacity'] < 0 || $style['opacity'] > 1)) {
            $this->add($errors, 'style.opacity', "$path/opacity", $elementId);
        }
        $enums = [
            'horizontalAlignment'=>['start','center','end','stretch'], 'verticalAlignment'=>['start','center','end'],
            'fontWeight'=>['normal','bold','100','200','300','400','500','600','700','800','900'],
            'fontStyle'=>['normal','italic'], 'textDecoration'=>['none','underline'],
            'textTransform'=>['none','uppercase','lowercase','capitalize'],
        ];
        foreach ($enums as $key=>$values) if (array_key_exists($key,$style) && !in_array($style[$key],$values,true)) {
            $this->add($errors, 'style.enum', "$path/$key", $elementId);
        }
        if (array_key_exists('fontFamily',$style) && (!is_string($style['fontFamily']) || !in_array($style['fontFamily'],$this->settings->allowedFontList(),true))) {
            $this->add($errors, 'style.fontFamily', "$path/fontFamily", $elementId);
        }
        if (array_key_exists('fontSize',$style)) {
            $this->validateMeasurement($style['fontSize'], Unit::Point, "$path/fontSize", $errors);
            $value=$style['fontSize']['value']??null;
            if ((!is_int($value)&&!is_float($value))||$value<1||$value>512) $this->add($errors,'style.fontSize',"$path/fontSize/value",$elementId);
        }
        if (array_key_exists('lineHeight',$style) && ((!is_int($style['lineHeight'])&&!is_float($style['lineHeight']))||$style['lineHeight']<0.5||$style['lineHeight']>5)) {
            $this->add($errors,'style.lineHeight',"$path/lineHeight",$elementId);
        }
        foreach (['width','height'] as $key) if (array_key_exists($key,$style)) {
            $measurement=$style[$key]; $unit=is_array($measurement)?($measurement['unit']??null):null;
            if (!$this->isObject($measurement)||!in_array($unit,['mm','percent'],true)||(!is_int($measurement['value']??null)&&!is_float($measurement['value']??null))||$measurement['value']<0||($unit==='mm'&&$measurement['value']>2000)||($unit==='percent'&&$measurement['value']>100)||array_diff(array_keys($measurement),['value','unit'])!==[]) {
                $this->add($errors,'style.dimension',"$path/$key",$elementId);
            }
        }
        if (array_key_exists('letterSpacing',$style)) {
            $measurement=$style['letterSpacing'];
            if (!$this->isObject($measurement)||($measurement['unit']??null)!=='pt'||(!is_int($measurement['value']??null)&&!is_float($measurement['value']??null))||$measurement['value'] < -20||$measurement['value']>100||array_diff(array_keys($measurement),['value','unit'])!==[]) {
                $this->add($errors,'style.letterSpacing',"$path/letterSpacing",$elementId);
            }
        }
        if (array_key_exists('border',$style)) {
            $border=$style['border'];
            if (!$this->isObject($border)) $this->add($errors,'style.border',"$path/border",$elementId);
            else {
                $this->rejectUnknownKeys($border,['width','style','color'],"$path/border",$errors);
                $this->validateMeasurement($border['width']??null,Unit::Point,"$path/border/width",$errors);
                if (!in_array($border['style']??null,['none','solid','dashed','dotted','double'],true)) $this->add($errors,'style.border',"$path/border/style",$elementId);
                if (!is_string($border['color']??null)||preg_match('/^#[0-9A-Fa-f]{6}$/D',$border['color'])!==1) $this->add($errors,'value.color',"$path/border/color",$elementId);
            }
        }
    }

    /** @param list<ValidationError> $errors */
    private function validateInlineContent(
        mixed $content,
        string $path,
        ?string $elementId,
        array &$errors,
        bool $allowLists = false,
    ): void {
        if (!is_array($content) || !array_is_list($content) || count($content) > 1000) {
            $this->add($errors, 'content.sequence', $path, $elementId);

            return;
        }

        foreach ($content as $index => $item) {
            $itemPath = "$path/$index";

            if (!$this->isObject($item)) {
                $this->add($errors, 'content.item', $itemPath, $elementId);

                continue;
            }

            $type = $item['type'] ?? null;

            if ($type === 'list' && $allowLists) {
                $this->rejectUnknownKeys($item, ['type', 'style', 'items'], $itemPath, $errors);

                if (!in_array($item['style'] ?? null, ['bulleted', 'numbered'], true)) {
                    $this->add($errors, 'content.listStyle', "$itemPath/style", $elementId);
                }

                $items = $item['items'] ?? null;

                if (!is_array($items) || !array_is_list($items) || $items === [] || count($items) > 100) {
                    $this->add($errors, 'content.listItems', "$itemPath/items", $elementId);
                    continue;
                }

                foreach ($items as $listIndex => $listItem) {
                    $this->validateInlineContent(
                        $listItem,
                        "$itemPath/items/$listIndex",
                        $elementId,
                        $errors,
                    );
                }

                continue;
            }

            if ($type === 'text') {
                $this->rejectUnknownKeys($item, ['type', 'text', 'marks', 'color'], $itemPath, $errors);
                $this->validatePlainText(
                    $item['text'] ?? null,
                    "$itemPath/text",
                    'content.text',
                    $elementId,
                    $errors,
                );
                $marks = $item['marks'] ?? null;

                if (
                    !is_array($marks) || !array_is_list($marks) ||
                    count($marks) !== count(array_unique($marks)) ||
                    array_diff($marks, ['bold', 'italic', 'underline']) !== []
                ) {
                    $this->add($errors, 'content.marks', "$itemPath/marks", $elementId);
                }

                if (array_key_exists('color', $item) &&
                    (!is_string($item['color']) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $item['color']) !== 1)) {
                    $this->add($errors, 'value.color', "$itemPath/color", $elementId);
                }

                continue;
            }

            if ($type === 'break') {
                $this->rejectUnknownKeys($item, ['type'], $itemPath, $errors);

                continue;
            }

            if ($type === 'variable') {
                $this->rejectUnknownKeys(
                    $item,
                    ['type', 'tokenId', 'label', 'identity', 'presentation'],
                    $itemPath,
                    $errors,
                );

                if (!is_string($item['tokenId'] ?? null) ||
                    preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $item['tokenId']) !== 1) {
                    $this->add($errors, 'content.tokenId', "$itemPath/tokenId", $elementId);
                }

                $this->validatePlainText(
                    $item['label'] ?? null,
                    "$itemPath/label",
                    'content.tokenLabel',
                    $elementId,
                    $errors,
                    100,
                );
                if (($item['label'] ?? null) === '') {
                    $this->add($errors, 'content.tokenLabel', "$itemPath/label", $elementId);
                }

                if (!$this->isScalarVariableIdentity($item['identity'] ?? null)) {
                    $this->add($errors, 'content.variableIdentity', "$itemPath/identity", $elementId);
                }

                if (!$this->isVariablePresentation($item['presentation'] ?? null)) {
                    $this->add($errors, 'content.variablePresentation', "$itemPath/presentation", $elementId);
                }

                continue;
            }

            $this->add($errors, 'content.type', "$itemPath/type", $elementId);
        }
    }

    private function isScalarVariableIdentity(mixed $identity): bool
    {
        if (!$this->isObject($identity)) {
            return false;
        }

        $source = $identity['source'] ?? null;
        $type = $identity['type'] ?? null;
        $path = $identity['path'] ?? null;
        $entity = $identity['entityType'] ?? null;
        $allowedKeys = $source === 'entity' ?
            ['source', 'type', 'entityType', 'path'] : ['source', 'type', 'path'];

        if (count($identity) !== count($allowedKeys) ||
            array_diff(array_keys($identity), $allowedKeys) !== [] ||
            array_diff($allowedKeys, array_keys($identity)) !== [] ||
            !is_array($path) || !array_is_list($path) || $path === [] || count($path) > 4) {
            return false;
        }

        foreach ($path as $segment) {
            if (!is_string($segment) ||
                preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D', $segment) !== 1) {
                return false;
            }
        }

        if ($source === 'entity') {
            return in_array($type, ['direct', 'related'], true) &&
                is_string($entity) &&
                preg_match('/\A[A-Za-z][A-Za-z0-9]{0,99}\z/D', $entity) === 1 &&
                (($type === 'direct' && count($path) === 1) ||
                    ($type === 'related' && count($path) >= 2));
        }

        return count($path) === 1 && (
            ($source === 'system' && $type === 'system') ||
            ($source === 'spreadsheet' && $type === 'spreadsheet')
        );
    }

    private function isVariablePresentation(mixed $presentation): bool
    {
        if (!$this->hasExactKeys($presentation, ['format', 'missing']) ||
            !$this->hasExactKeys($presentation['format'], [
                'type', 'decimals', 'dateStyle', 'timeStyle', 'currency', 'trueLabel',
                'falseLabel', 'separator', 'trim', 'case', 'prefix', 'suffix', 'fallback',
            ])) {
            return false;
        }

        $format = $presentation['format'];
        $nullableTexts = [
            'currency' => [3, '/\A[A-Z]{3}\z/D'],
            'trueLabel' => [100, null],
            'falseLabel' => [100, null],
            'fallback' => [200, null],
        ];

        foreach ($nullableTexts as $key => [$maximum, $pattern]) {
            $value = $format[$key];

            if ($value !== null && (!$this->isSafeBoundedText($value, $maximum) ||
                ($pattern !== null && preg_match($pattern, $value) !== 1))) {
                return false;
            }
        }

        if (!in_array($format['type'], [
            'auto', 'date', 'datetime', 'number', 'currency', 'boolean', 'enum', 'multiValue',
        ], true) || !is_int($format['decimals']) || $format['decimals'] < 0 ||
            $format['decimals'] > 6 ||
            !in_array($format['dateStyle'], ['short', 'medium', 'long'], true) ||
            !in_array($format['timeStyle'], ['short', 'medium'], true) ||
            !$this->isSafeBoundedText($format['separator'], 10) || $format['separator'] === '' ||
            !is_bool($format['trim']) ||
            !in_array($format['case'], ['none', 'upper', 'lower', 'title'], true) ||
            !$this->isSafeBoundedText($format['prefix'], 100) ||
            !$this->isSafeBoundedText($format['suffix'], 100) ||
            !in_array($presentation['missing'], [
                'empty', 'fallback', 'hideElement', 'hideRow', 'hideSection', 'warning', 'required',
            ], true)) {
            return false;
        }

        return $presentation['missing'] !== 'fallback' || $format['fallback'] !== null;
    }

    /** @param list<string> $keys */
    private function hasExactKeys(mixed $value, array $keys): bool
    {
        return $this->isObject($value) && count($value) === count($keys) &&
            array_diff(array_keys($value), $keys) === [] &&
            array_diff($keys, array_keys($value)) === [];
    }

    private function isSafeBoundedText(mixed $value, int $maximum): bool
    {
        return is_string($value) && mb_strlen($value) <= $maximum &&
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }

    /** @param list<ValidationError> $errors */
    private function validatePlainText(
        mixed $value,
        string $path,
        string $code,
        ?string $elementId,
        array &$errors,
        int $maxLength = 10000,
    ): void {
        if (
            !is_string($value) ||
            mb_strlen($value) > $maxLength ||
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1
        ) {
            $this->add($errors, $code, $path, $elementId);
        }
    }

    /** @param list<ValidationError> $errors */
    private function validateBox(mixed $box, string $path, array &$errors): void
    {
        if (!$this->isObject($box)) {
            $this->add($errors, 'value.box', $path);

            return;
        }

        $this->rejectUnknownKeys($box, ['top', 'right', 'bottom', 'left'], $path, $errors);

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $this->validateMeasurement($box[$side] ?? null, Unit::Millimetre, "$path/$side", $errors);
        }
    }

    /** @param list<ValidationError> $errors */
    private function validateMeasurement(mixed $measurement, Unit $expectedUnit, string $path, array &$errors): void
    {
        if (!$this->isObject($measurement)) {
            $this->add($errors, 'value.measurement', $path);

            return;
        }

        $this->rejectUnknownKeys($measurement, ['value', 'unit'], $path, $errors);
        $value = $measurement['value'] ?? null;
        $unit = $measurement['unit'] ?? null;

        if ((!is_int($value) && !is_float($value)) || $unit !== $expectedUnit->value) {
            $this->add($errors, 'value.measurement', $path);

            return;
        }

        try {
            new Measurement($value, $expectedUnit);
        } catch (\InvalidArgumentException) {
            $this->add($errors, 'value.bounds', "$path/value");
        }
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string> $allowed
     * @param list<ValidationError> $errors
     */
    private function rejectUnknownKeys(array $value, array $allowed, string $path, array &$errors): void
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                $this->add($errors, 'property.unknown', $path . '/' . $key);
            }
        }
    }

    private function isObject(mixed $value): bool
    {
        return is_array($value) && (!array_is_list($value) || $value === []);
    }

    /** @param list<ValidationError> $errors */
    private function add(array &$errors, string $code, string $path, ?string $elementId = null): void
    {
        if (count($errors) >= self::MAX_ERRORS) {
            return;
        }

        $errors[] = new ValidationError($code, $path === '' ? '/' : $path, $elementId);
    }
}
