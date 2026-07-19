<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use stdClass;

final class ConfigProvider implements SettingsProvider
{
    private const METADATA_PATH = ['app', 'documentBuilder'];

    public function __construct(
        private Config $config,
        private Metadata $metadata,
    ) {}

    public function get(): Settings
    {
        $definition = $this->metadata->get(self::METADATA_PATH);

        if (!is_array($definition) || ($definition['schemaVersion'] ?? null) !== 1) {
            throw new InvalidConfiguration('Document Builder settings metadata is missing or unsupported.');
        }

        if (($definition['configKey'] ?? null) !== 'documentBuilder') {
            throw new InvalidConfiguration('Document Builder settings metadata has an invalid config key.');
        }

        $defaults = $this->requireMap($definition, 'defaults');
        $minimums = $this->requireMap($definition, 'minimums');
        $hardLimits = $this->requireMap($definition, 'hardLimits');
        $lockedValues = $this->requireMap($definition, 'lockedValues');
        $allowedValues = $this->requireMap($definition, 'allowedValues');

        if (array_keys($defaults) !== Settings::KEY_LIST) {
            throw new InvalidConfiguration('Document Builder settings metadata does not match the settings contract.');
        }

        $integerKeys = array_keys(array_filter($defaults, is_int(...)));
        $listKeys = array_keys(array_filter($defaults, is_array(...)));
        $this->assertKeySet($minimums, $integerKeys, 'minimums');
        $this->assertKeySet($hardLimits, [...$listKeys, ...$integerKeys], 'hardLimits');
        $this->assertKeySet($allowedValues, ['defaultPdfEngine', 'defaultPageSize'], 'allowedValues');

        if ($lockedValues !== ['allowRemoteResources' => false]) {
            throw new InvalidConfiguration('Remote resources must be the only locked setting and must remain disabled.');
        }

        $overrides = $this->readOverrides();
        $unknownKeys = array_diff(array_keys($overrides), Settings::KEY_LIST);

        if ($unknownKeys !== []) {
            throw new InvalidConfiguration(sprintf(
                'Unknown Document Builder setting: %s.',
                (string) reset($unknownKeys),
            ));
        }

        foreach ($lockedValues as $key => $lockedValue) {
            if (!array_key_exists($key, $defaults) || $defaults[$key] !== $lockedValue) {
                throw new InvalidConfiguration("Invalid locked setting definition: $key.");
            }

            if (array_key_exists($key, $overrides) && $overrides[$key] !== $lockedValue) {
                throw new InvalidConfiguration("Setting $key is locked and cannot be enabled.");
            }
        }

        $values = array_replace($defaults, $overrides);

        foreach ($defaults as $key => $defaultValue) {
            $value = $values[$key];
            $this->assertSameType($key, $defaultValue, $value);

            if (is_int($defaultValue)) {
                $this->assertIntegerBoundary($key, $value, $minimums, $hardLimits);
            }
        }

        $values['enabledSourceEntityTypeList'] = $this->normalizeIdentifierList(
            'enabledSourceEntityTypeList',
            $values['enabledSourceEntityTypeList'],
            $hardLimits,
            '/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D',
        );
        $values['disabledSourceEntityTypeList'] = $this->normalizeIdentifierList(
            'disabledSourceEntityTypeList',
            $values['disabledSourceEntityTypeList'],
            $hardLimits,
            '/\A[A-Za-z][A-Za-z0-9_]{0,99}\z/D',
        );

        if (array_intersect($values['enabledSourceEntityTypeList'], $values['disabledSourceEntityTypeList']) !== []) {
            throw new InvalidConfiguration('Source entity allow and deny lists must not overlap.');
        }

        $values['allowedFontList'] = $this->normalizeIdentifierList(
            'allowedFontList',
            $values['allowedFontList'],
            $hardLimits,
            '/\A[A-Za-z0-9][A-Za-z0-9 ._-]{0,99}\z/D',
        );
        $values['customPageSizeList'] = $this->normalizeCustomPageSizeList(
            $values['customPageSizeList'],
            $hardLimits,
        );

        if ($values['allowedFontList'] === []) {
            throw new InvalidConfiguration('At least one allowed font is required.');
        }

        foreach ($allowedValues as $key => $allowedList) {
            if (!array_key_exists($key, $values) || !is_array($allowedList)) {
                throw new InvalidConfiguration("Invalid allowed-values definition: $key.");
            }

            if (!in_array($values[$key], $allowedList, true)) {
                throw new InvalidConfiguration("Setting $key contains an unsupported value.");
            }
        }

        if (
            !is_string($values['defaultLocale']) ||
            preg_match('/\A[a-z]{2}_[A-Z]{2}\z/D', $values['defaultLocale']) !== 1
        ) {
            throw new InvalidConfiguration('Setting defaultLocale must use an ll_CC locale identifier.');
        }

        if (!in_array($values['defaultFont'], $values['allowedFontList'], true)) {
            throw new InvalidConfiguration('The default font must be present in the allowed font list.');
        }

        return new Settings($values);
    }

    /** @return array<string, mixed> */
    private function readOverrides(): array
    {
        $value = $this->config->get('documentBuilder', []);

        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            throw new InvalidConfiguration('The documentBuilder config value must be an object.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function requireMap(array $definition, string $key): array
    {
        $value = $definition[$key] ?? null;

        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            throw new InvalidConfiguration("Document Builder metadata section $key must be an object.");
        }

        return $value;
    }

    private function assertSameType(string $key, mixed $defaultValue, mixed $value): void
    {
        $valid = match (true) {
            is_array($defaultValue) => is_array($value) && array_is_list($value),
            is_bool($defaultValue) => is_bool($value),
            is_int($defaultValue) => is_int($value),
            is_string($defaultValue) => is_string($value),
            default => false,
        };

        if (!$valid) {
            throw new InvalidConfiguration("Setting $key has an invalid type.");
        }
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $expectedKeys
     */
    private function assertKeySet(array $map, array $expectedKeys, string $section): void
    {
        $actualKeys = array_keys($map);
        sort($actualKeys);
        sort($expectedKeys);

        if ($actualKeys !== $expectedKeys) {
            throw new InvalidConfiguration("Document Builder metadata section $section has invalid keys.");
        }
    }

    /**
     * @param array<string, mixed> $minimums
     * @param array<string, mixed> $hardLimits
     */
    private function assertIntegerBoundary(
        string $key,
        int $value,
        array $minimums,
        array $hardLimits,
    ): void {
        $minimum = $minimums[$key] ?? null;
        $hardLimit = $hardLimits[$key] ?? null;

        if (!is_int($minimum) || !is_int($hardLimit) || $minimum > $hardLimit) {
            throw new InvalidConfiguration("Invalid numeric boundary definition: $key.");
        }

        if ($value < $minimum) {
            throw new InvalidConfiguration("Setting $key is below its minimum.");
        }

        if ($value > $hardLimit) {
            throw new InvalidConfiguration("Setting $key exceeds its hard limit.");
        }
    }

    /**
     * @param list<mixed> $value
     * @param array<string, mixed> $hardLimits
     * @return list<string>
     */
    private function normalizeIdentifierList(
        string $key,
        array $value,
        array $hardLimits,
        string $pattern,
    ): array {
        $hardLimit = $hardLimits[$key] ?? null;

        if (!is_int($hardLimit) || $hardLimit < 1) {
            throw new InvalidConfiguration("Invalid list boundary definition: $key.");
        }

        if (count($value) > $hardLimit) {
            throw new InvalidConfiguration("Setting $key exceeds its hard limit.");
        }

        foreach ($value as $item) {
            if (!is_string($item) || preg_match($pattern, $item) !== 1) {
                throw new InvalidConfiguration("Setting $key contains an invalid identifier.");
            }
        }

        if (count($value) !== count(array_unique($value))) {
            throw new InvalidConfiguration("Setting $key contains duplicate values.");
        }

        return $value;
    }

    /**
     * @param list<mixed> $value
     * @param array<string, mixed> $hardLimits
     * @return list<array{id: string, label: string, widthMm: float|int, heightMm: float|int}>
     */
    private function normalizeCustomPageSizeList(array $value, array $hardLimits): array
    {
        $hardLimit = $hardLimits['customPageSizeList'] ?? null;

        if (!is_int($hardLimit) || $hardLimit < 1 || count($value) > $hardLimit) {
            throw new InvalidConfiguration('Setting customPageSizeList exceeds its hard limit.');
        }

        $result = [];

        foreach ($value as $item) {
            if ($item instanceof stdClass) {
                $item = get_object_vars($item);
            }

            $keys = is_array($item) ? array_keys($item) : [];
            sort($keys);

            if (
                !is_array($item) ||
                $keys !== ['heightMm', 'id', 'label', 'widthMm'] ||
                !is_string($item['id']) ||
                preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', $item['id']) !== 1 ||
                in_array($item['id'], ['A4', 'Letter', 'Legal'], true) ||
                !is_string($item['label']) || trim($item['label']) === '' || mb_strlen($item['label']) > 100 ||
                (!is_int($item['widthMm']) && !is_float($item['widthMm'])) ||
                $item['widthMm'] < 10 || $item['widthMm'] > 2000 ||
                (!is_int($item['heightMm']) && !is_float($item['heightMm'])) ||
                $item['heightMm'] < 10 || $item['heightMm'] > 2000
            ) {
                throw new InvalidConfiguration('Setting customPageSizeList contains an invalid definition.');
            }

            $result[] = $item;
        }

        $ids = array_column($result, 'id');

        if (count($ids) !== count(array_unique($ids))) {
            throw new InvalidConfiguration('Setting customPageSizeList contains duplicate identifiers.');
        }

        return $result;
    }
}
