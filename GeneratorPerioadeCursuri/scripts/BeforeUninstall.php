<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class BeforeUninstall
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
        $this->removeTabs($container);
    }

    private function removeTabs(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $tabList = $config->get('tabList') ?? [];

        if (!is_array($tabList)) {
            return;
        }

        $filteredTabList = $this->removeGeneratorPerioadeCursuriMenuEntries($tabList);

        if ($filteredTabList !== $tabList) {
            $configWriter->set('tabList', $filteredTabList);
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
}
