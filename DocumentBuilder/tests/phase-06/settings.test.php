<?php

declare(strict_types=1);

namespace Espo\Core\Utils {
    final class Config
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data)
        {}

        public function get(string $name, mixed $default = null): mixed
        {
            return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }
    }

    final class Metadata
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data)
        {}

        /** @param string|list<string>|null $key */
        public function get(string|array|null $key = null, mixed $default = null): mixed
        {
            if ($key === null) {
                return $this->data;
            }

            $path = is_array($key) ? $key : [$key];
            $value = $this->data;

            foreach ($path as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $default;
                }

                $value = $value[$segment];
            }

            return $value;
        }
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use DocumentBuilder\Tests\Support\FixtureLoader;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Metadata;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\ConfigProvider;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\InvalidConfiguration;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\Settings;

    require dirname(__DIR__) . '/bootstrap.php';

    $extensionRoot = dirname(__DIR__, 2);
    $moduleRoot = $extensionRoot . '/files/custom/Espo/Modules/DocumentBuilder';
    $resourceLoader = new FixtureLoader($moduleRoot . '/Resources');
    $definition = $resourceLoader->json('metadata/app/documentBuilder.json');

    require $moduleRoot . '/Tools/DocumentBuilder/Config/InvalidConfiguration.php';
    require $moduleRoot . '/Tools/DocumentBuilder/Config/Settings.php';
    require $moduleRoot . '/Tools/DocumentBuilder/Config/SettingsProvider.php';
    require $moduleRoot . '/Tools/DocumentBuilder/Config/ConfigProvider.php';

    /**
     * @param mixed $overrides
     * @param array<string, mixed>|null $metadataDefinition
     */
    function createProvider(mixed $overrides, ?array $metadataDefinition = null): ConfigProvider
    {
        global $definition;

        return new ConfigProvider(
            new Config(['documentBuilder' => $overrides]),
            new Metadata(['app' => ['documentBuilder' => $metadataDefinition ?? $definition]]),
        );
    }

    $configMetadata = $resourceLoader->json('metadata/app/config.json');
    Assert::same(
        ['level' => 'admin'],
        $configMetadata['params']['documentBuilder'] ?? null,
        'The nested Document Builder config must be administrator-only.',
    );
    Assert::same(1, $definition['schemaVersion'] ?? null, 'Unexpected settings metadata version.');
    Assert::same('documentBuilder', $definition['configKey'] ?? null, 'Unexpected settings config key.');
    Assert::same(false, $definition['lockedValues']['allowRemoteResources'] ?? null, 'Remote resources must be locked off.');

    $settings = createProvider([])->get();
    $settingsWithoutStoredOverrides = new ConfigProvider(
        new Config([]),
        new Metadata(['app' => ['documentBuilder' => $definition]]),
    );
    Assert::same(
        500,
        $settingsWithoutStoredOverrides->get()->maxElements(),
        'Missing stored configuration must use the metadata defaults.',
    );

    foreach (Settings::KEY_LIST as $key) {
        Assert::isTrue(method_exists($settings, $key), "Settings getter is missing: $key.");
        Assert::same(
            $definition['defaults'][$key] ?? null,
            $settings->$key(),
            "Default did not reach the normalized settings object: $key.",
        );
    }

    foreach ([
        'maxRelationshipDepth' => 2,
        'maxLayoutBytes' => 1048576,
        'maxElements' => 500,
        'maxNestingDepth' => 8,
        'maxFreeformElementsPerSection' => 200,
        'maxSections' => 100,
        'maxConditions' => 250,
        'maxRelatedTableColumns' => 20,
        'maxCollectionRows' => 500,
        'temporaryImportRetentionHours' => 72,
    ] as $key => $expected) {
        Assert::same($expected, $settings->$key(), "PRD default changed: $key.");
    }

    Assert::same(['DejaVu Sans'], $settings->allowedFontList(), 'The Unicode-capable default font changed.');
    Assert::isFalse($settings->allowSvg(), 'SVG must default off.');
    Assert::isFalse($settings->allowWebp(), 'WebP must default off until runtime capability is proven.');
    Assert::isFalse($settings->allowRemoteResources(), 'Remote resources must remain off.');
    Assert::isFalse($settings->enableListViewMassGeneration(), 'Mass generation must require explicit enablement.');
    Assert::isTrue($settings->storeTemplateSnapshot(), 'Template snapshots must default on.');
    Assert::isTrue($settings->storeResolvedDataSnapshot(), 'Resolved data snapshots must default on.');

    $validOverrides = (object) [
        'enabledSourceEntityTypeList' => ['Account', 'Contact'],
        'disabledSourceEntityTypeList' => ['User'],
        'maxRelationshipDepth' => 3,
        'maxElements' => 250,
        'allowedFontList' => ['DejaVu Sans', 'DejaVu Serif'],
        'customPageSizeList' => [[
            'id' => 'Badge',
            'label' => 'Badge',
            'widthMm' => 90,
            'heightMm' => 55,
        ]],
        'defaultFont' => 'DejaVu Serif',
        'allowSvg' => true,
        'allowWebp' => true,
        'allowRemoteResources' => false,
        'enableListViewMassGeneration' => true,
        'defaultLocale' => 'ro_RO',
        'defaultPageSize' => 'Letter',
    ];
    $overridden = createProvider($validOverrides)->get();
    Assert::same(['Account', 'Contact'], $overridden->enabledSourceEntityTypeList(), 'Source allow-list override failed.');
    Assert::same(['User'], $overridden->disabledSourceEntityTypeList(), 'Source deny-list override failed.');
    Assert::same(3, $overridden->maxRelationshipDepth(), 'Relationship-depth hard boundary must be accepted.');
    Assert::same(250, $overridden->maxElements(), 'Lower element override failed.');
    Assert::same('DejaVu Serif', $overridden->defaultFont(), 'Default-font override failed.');
    Assert::isTrue($overridden->allowSvg(), 'SVG administrator override failed.');
    Assert::isTrue($overridden->allowWebp(), 'WebP administrator override failed.');
    Assert::isTrue($overridden->enableListViewMassGeneration(), 'Mass-generation override failed.');
    Assert::same('ro_RO', $overridden->defaultLocale(), 'Locale override failed.');
    Assert::same('Letter', $overridden->defaultPageSize(), 'Page-size override failed.');
    Assert::same('Badge', $overridden->customPageSizeList()[0]['id'], 'Custom page-size override failed.');

    foreach ($definition['minimums'] as $key => $minimum) {
        Assert::throws(
            fn () => createProvider([$key => $minimum - 1])->get(),
            InvalidConfiguration::class,
            "A value below the minimum must fail: $key.",
        );
    }

    foreach ($definition['hardLimits'] as $key => $hardLimit) {
        $default = $definition['defaults'][$key] ?? null;

        if (!is_int($default)) {
            continue;
        }

        Assert::same(
            $hardLimit,
            createProvider([$key => $hardLimit])->get()->$key(),
            "The exact hard boundary must be accepted: $key.",
        );
        Assert::throws(
            fn () => createProvider([$key => $hardLimit + 1])->get(),
            InvalidConfiguration::class,
            "A value above the hard boundary must fail: $key.",
        );
    }

    foreach ([
        ['maxElements' => '500'],
        ['allowSvg' => 1],
        ['defaultFont' => 123],
        ['enabledSourceEntityTypeList' => 'Contact'],
        ['unknownLimit' => 1],
        ['allowRemoteResources' => true],
        ['enabledSourceEntityTypeList' => ['Contact', 'Contact']],
        ['enabledSourceEntityTypeList' => ['Contact/../../User']],
        ['enabledSourceEntityTypeList' => [str_repeat('A', 101)]],
        ['enabledSourceEntityTypeList' => ['Contact'], 'disabledSourceEntityTypeList' => ['Contact']],
        ['allowedFontList' => []],
        ['allowedFontList' => ['DejaVu Sans'], 'defaultFont' => 'Other Font'],
        ['allowedFontList' => ['Unsafe<script>'], 'defaultFont' => 'Unsafe<script>'],
        ['defaultLocale' => '../en_US'],
        ['defaultPdfEngine' => 'RemotePdf'],
        ['defaultPageSize' => 'Unlimited'],
        ['customPageSizeList' => [['id' => 'A4', 'label' => 'Override', 'widthMm' => 10, 'heightMm' => 10]]],
        ['customPageSizeList' => [['id' => 'Badge', 'label' => '', 'widthMm' => 90, 'heightMm' => 55]]],
    ] as $invalidOverrides) {
        Assert::throws(
            fn () => createProvider($invalidOverrides)->get(),
            InvalidConfiguration::class,
            'Invalid or unsafe settings must fail closed.',
        );
    }

    foreach ([null, 'invalid', 1, false, new class {}] as $invalidRoot) {
        Assert::throws(
            fn () => createProvider($invalidRoot)->get(),
            InvalidConfiguration::class,
            'The stored Document Builder config root must be an object.',
        );
    }

    Assert::throws(
        fn () => createProvider(array_fill(0, 251, 'EntityType'))->get(),
        InvalidConfiguration::class,
        'A list-shaped root config value must be rejected.',
    );
    Assert::throws(
        fn () => createProvider(['enabledSourceEntityTypeList' => array_map(
            static fn (int $index): string => "Entity$index",
            range(1, 251),
        )])->get(),
        InvalidConfiguration::class,
        'Source entity lists must enforce their hard entry limit.',
    );
    Assert::same(
        20,
        count(createProvider([
            'allowedFontList' => array_map(
                static fn (int $index): string => "Fixture Font $index",
                range(1, 20),
            ),
            'defaultFont' => 'Fixture Font 1',
        ])->get()->allowedFontList()),
        'The exact font-list hard boundary must be accepted.',
    );
    Assert::throws(
        fn () => createProvider([
            'allowedFontList' => array_map(
                static fn (int $index): string => "Fixture Font $index",
                range(1, 21),
            ),
            'defaultFont' => 'Fixture Font 1',
        ])->get(),
        InvalidConfiguration::class,
        'The font list must enforce its hard entry limit.',
    );

    $badDefinition = $definition;
    $badDefinition['hardLimits']['maxElements'] = 100;
    Assert::throws(
        fn () => createProvider([], $badDefinition)->get(),
        InvalidConfiguration::class,
        'A metadata default above its hard limit must fail closed.',
    );

    $badDefinition = $definition;
    unset($badDefinition['defaults']['maxElements']);
    Assert::throws(
        fn () => createProvider([], $badDefinition)->get(),
        InvalidConfiguration::class,
        'Incomplete settings metadata must fail closed.',
    );

    $badDefinition = $definition;
    unset($badDefinition['lockedValues']['allowRemoteResources']);
    Assert::throws(
        fn () => createProvider([], $badDefinition)->get(),
        InvalidConfiguration::class,
        'Metadata must not remove the remote-resource lock.',
    );

    $badDefinition = $definition;
    $badDefinition['hardLimits']['unownedLimit'] = 1;
    Assert::throws(
        fn () => createProvider([], $badDefinition)->get(),
        InvalidConfiguration::class,
        'Unknown hard-limit definitions must fail closed.',
    );

    foreach (['en_US', 'ro_RO'] as $locale) {
        $i18n = $resourceLoader->json("i18n/$locale/DocumentBuilder.json");
        $fieldKeys = array_keys($i18n['fields'] ?? []);
        Assert::same(Settings::KEY_LIST, $fieldKeys, "$locale settings labels must match the settings contract.");
        Assert::isTrue(
            is_string($i18n['tooltips']['allowRemoteResources'] ?? null) &&
                $i18n['tooltips']['allowRemoteResources'] !== '',
            "$locale must explain that remote resources are locked off.",
        );
    }

    echo "Phase 06 settings and hard-limit checks passed.\n";
}
