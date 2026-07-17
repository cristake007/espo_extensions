<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const MENU_GROUP_TEXT = 'Generator perioade cursuri';
    private const MENU_GROUP_ITEMS = [
        'GeneratorPerioadeCursuri',
        'GeneratorPerioadeCursuriWordMatcher',
        'GeneratorPerioadeCursuriXmlConverter',
        'GeneratorPerioadeCursuriWordPressUpdater',
    ];
    private const LEGACY_MENU_GROUP_TEXT = 'Planificări';
    private const LEGACY_MENU_GROUP_ITEMS = [
        'Planificari',
        'PlanificariWordMatcher',
    ];

    public function run(Container $container): void
    {
        $this->assertRequiredPackageFiles();
        $this->removeStalePackageFiles();
        $this->addMenuGroup($container);
    }

    private function assertRequiredPackageFiles(): void
    {
        $requiredPaths = [
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuri.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuriWordMatcher.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuriXmlConverter.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuriWordPressUpdater.json',
        ];

        foreach ($requiredPaths as $path) {
            if (!is_file($path)) {
                throw new RuntimeException(sprintf(
                    'Generator perioade cursuri package is incomplete; required file is missing: %s',
                    $path
                ));
            }
        }
    }

    private function addMenuGroup(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $originalTabList = $config->get('tabList') ?? [];

        if (!is_array($originalTabList)) {
            return;
        }

        $tabList = $this->removeGeneratorPerioadeCursuriMenuEntries($originalTabList);
        $tabList[] = $this->buildMenuGroup();

        if ($tabList !== $originalTabList) {
            $configWriter->set('tabList', $tabList);
            $configWriter->save();
        }
    }

    /**
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function removeGeneratorPerioadeCursuriMenuEntries(array $tabList): array
    {
        $filteredTabList = [];

        foreach ($tabList as $item) {
            if (in_array($item, $this->getManagedMenuItems(), true)) {
                continue;
            }

            if (is_array($item)) {
                if ($this->isGeneratorPerioadeCursuriMenuGroup($item)) {
                    continue;
                }

                $itemList = $item['itemList'] ?? null;

                if (is_array($itemList) && $this->containsManagedMenuItem($itemList)) {
                    $item['itemList'] = array_values(array_filter(
                        $itemList,
                        fn ($childItem) => !in_array($childItem, $this->getManagedMenuItems(), true)
                    ));

                    if ($item['itemList'] === []) {
                        continue;
                    }
                }
            }

            $filteredTabList[] = $item;
        }

        return array_values($filteredTabList);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isGeneratorPerioadeCursuriMenuGroup(array $item): bool
    {
        return in_array(
            $item['text'] ?? null,
            [self::MENU_GROUP_TEXT, self::LEGACY_MENU_GROUP_TEXT],
            true
        );
    }

    /**
     * @param array<int, mixed> $itemList
     */
    private function containsManagedMenuItem(array $itemList): bool
    {
        foreach ($this->getManagedMenuItems() as $menuItem) {
            if (in_array($menuItem, $itemList, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function getManagedMenuItems(): array
    {
        return array_merge(self::MENU_GROUP_ITEMS, self::LEGACY_MENU_GROUP_ITEMS);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMenuGroup(): array
    {
        return [
            'text' => self::MENU_GROUP_TEXT,
            'iconClass' => 'fas fa-calendar-alt',
            'color' => '#164194',
            'itemList' => self::MENU_GROUP_ITEMS,
        ];
    }

    private function removeStalePackageFiles(): void
    {
        $paths = [
            'custom/Espo/Modules/Planificari/Resources/metadata/scopes/PlanificariRow.json',
            'custom/Espo/Modules/Planificari/Resources/metadata/entityDefs/PlanificariRow.json',
            'custom/Espo/Modules/Planificari/Resources/metadata/clientDefs/PlanificariRow.json',
            'custom/Espo/Modules/Planificari/Resources/metadata/aclDefs/PlanificariRow.json',
            'custom/Espo/Modules/Planificari/Resources/metadata/entityAcl/PlanificariRow.json',
            'custom/Espo/Modules/Planificari/Resources/metadata/recordDefs/PlanificariRow.json',
            'custom/Espo/Modules/Planificari/Resources/layouts/PlanificariRow/detail.json',
            'custom/Espo/Modules/Planificari/Resources/layouts/PlanificariRow/edit.json',
            'custom/Espo/Modules/Planificari/Resources/layouts/PlanificariRow/list.json',
            'custom/Espo/Modules/Planificari/Resources/layouts/PlanificariRow/search.json',
            'custom/Espo/Modules/Planificari/Resources/i18n/en_US/PlanificariRow.json',
            'custom/Espo/Modules/Planificari/Resources/i18n/ro_RO/PlanificariRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuriRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityDefs/GeneratorPerioadeCursuriRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/clientDefs/GeneratorPerioadeCursuriRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/aclDefs/GeneratorPerioadeCursuriRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityAcl/GeneratorPerioadeCursuriRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/recordDefs/GeneratorPerioadeCursuriRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriRow/detail.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriRow/edit.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriRow/list.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriRow/search.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/GeneratorPerioadeCursuriRow.json',
            'custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/GeneratorPerioadeCursuriRow.json',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
