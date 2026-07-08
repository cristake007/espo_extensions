<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class BeforeUninstall
{
    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $tabList = $config->get('tabList') ?? [];
        $filteredTabList = array_values(array_filter(
            $tabList,
            static fn ($item) => !in_array($item, ['Planificari', 'PlanificariWordMatcher'], true)
        ));

        if ($filteredTabList !== $tabList) {
            $configWriter->set('tabList', $filteredTabList);
            $configWriter->save();
        }
    }
}
