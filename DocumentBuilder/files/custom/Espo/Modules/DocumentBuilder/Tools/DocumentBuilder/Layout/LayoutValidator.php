<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;
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

        if (!is_array($capabilities) || !array_is_list($capabilities)) {
            $this->add($errors, 'capabilities.type', '/capabilities');
        } elseif ($capabilities !== []) {
            $this->add($errors, 'capability.unsupported', '/capabilities');
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
                    $errors,
                );
            }
        }

        if ($elementCount > $this->settings->maxElements()) {
            $this->add($errors, 'elements.limit', '/');
        }

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
            ['page', 'defaults', 'titlePattern', 'filenamePattern'],
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
     * @param list<ValidationError> $errors
     */
    private function validateNode(
        mixed $node,
        NodeKind $kind,
        string $indexPath,
        int $depth,
        array &$ids,
        int &$elementCount,
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
            } catch (UnknownNodeType) {
                $this->add($errors, 'node.unknownType', "$path/type", $elementId);
            } catch (CapabilityUnavailable) {
                $this->add($errors, 'node.capabilityUnavailable', "$path/type", $elementId);
            }
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
                $errors,
            );
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
