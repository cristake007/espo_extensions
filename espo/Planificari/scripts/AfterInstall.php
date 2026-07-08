<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const MENU_GROUP_TEXT = 'Planificări';
    private const MENU_GROUP_ITEMS = [
        'Planificari',
        'PlanificariWordMatcher',
    ];

    public function run(Container $container): void
    {
        $this->removeStalePackageFiles();
        $this->addMenuGroup($container);
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

        $tabList = $this->removePlanificariMenuEntries($originalTabList);
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
    private function removePlanificariMenuEntries(array $tabList): array
    {
        $filteredTabList = [];

        foreach ($tabList as $item) {
            if (in_array($item, self::MENU_GROUP_ITEMS, true)) {
                continue;
            }

            if (is_array($item)) {
                $itemList = $item['itemList'] ?? null;

                if (is_array($itemList)) {
                    $item['itemList'] = array_values(array_filter(
                        $itemList,
                        static fn ($childItem) => !in_array($childItem, self::MENU_GROUP_ITEMS, true)
                    ));
                }

                if ($this->isPlanificariMenuGroup($item)) {
                    continue;
                }

                if (($item['itemList'] ?? null) === []) {
                    continue;
                }
            }

            $filteredTabList[] = $item;
        }

        return array_values($filteredTabList);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isPlanificariMenuGroup(array $item): bool
    {
        $itemList = $item['itemList'] ?? null;

        if (!is_array($itemList)) {
            return false;
        }

        if (($item['text'] ?? null) === self::MENU_GROUP_TEXT) {
            return true;
        }

        foreach (self::MENU_GROUP_ITEMS as $menuItem) {
            if (in_array($menuItem, $itemList, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMenuGroup(): array
    {
        return [
            'text' => self::MENU_GROUP_TEXT,
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
            'custom/Espo/Modules/Planificari/Tools/Planificari/Api/PostGenerationSpike.php',
            'client/custom/modules/planificari/src/handlers/generation-spike-action.js',
            'client/custom/modules/planificari/src/views/generation-spike-modal.js',
            'client/custom/modules/planificari/res/templates/generation-spike-modal.tpl',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
