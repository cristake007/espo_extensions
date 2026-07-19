<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class BeforeUninstall
{
    private const NAVIGATION_GROUP_ID = 'document-builder';

    /**
     * @param array<string, mixed> $params
     */
    public function run(Container $container, array $params = []): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $filteredTabList = array_values(array_filter(
            $tabList,
            fn (mixed $item): bool => !$this->isManagedGroup($item),
        ));

        if ($filteredTabList === $tabList) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $configWriter->set('tabList', $filteredTabList);
        $configWriter->save();
    }

    private function isManagedGroup(mixed $item): bool
    {
        if (is_object($item)) {
            $item = (array) $item;
        }

        return is_array($item) && ($item['id'] ?? null) === self::NAVIGATION_GROUP_ID;
    }
}
