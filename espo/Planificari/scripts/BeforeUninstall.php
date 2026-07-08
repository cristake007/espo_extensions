<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class BeforeUninstall
{
    private const MENU_GROUP_TEXT = 'Planificări';
    private const MENU_GROUP_ITEMS = [
        'Planificari',
        'PlanificariWordMatcher',
    ];

    public function run(Container $container): void
    {
        try {
            $this->removeMenuGroup($container);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[Planificari] BeforeUninstall cleanup skipped: %s',
                $e->getMessage()
            ));
        }
    }

    private function removeMenuGroup(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $tabList = $config->get('tabList') ?? [];

        if (!is_array($tabList)) {
            return;
        }

        $filteredTabList = $this->removePlanificariMenuEntries($tabList);

        if ($filteredTabList !== $tabList) {
            $configWriter->set('tabList', $filteredTabList);
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
}
