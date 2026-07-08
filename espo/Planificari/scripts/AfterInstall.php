<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    public function run(Container $container): void
    {
        $this->removeStalePackageFiles();

        $config = $container->getByClass(Config::class);
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $tabList = $config->get('tabList') ?? [];

        foreach (['Planificari', 'PlanificariWordMatcher'] as $tab) {
            if (!in_array($tab, $tabList, true)) {
                $tabList[] = $tab;
            }
        }

        if ($tabList !== ($config->get('tabList') ?? [])) {
            $configWriter->set('tabList', $tabList);
            $configWriter->save();
        }
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
