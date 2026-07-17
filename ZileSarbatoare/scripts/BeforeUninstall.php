<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\ScheduledJob;
use Espo\ORM\EntityManager;

class BeforeUninstall
{
    private const ENTITY_TYPE = 'ZileLibere';
    private const SCHEDULED_JOB = 'SyncZileSarbatoare';

    public function run(Container $container): void
    {
        $this->unregisterEntity($container);
        $this->removeScheduledJob($container);
    }

    private function unregisterEntity(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $configChanges = [];

        foreach (['calendarEntityList', 'tabList', 'quickCreateList'] as $listName) {
            $entityTypeList = $config->get($listName) ?? [];

            if (!is_array($entityTypeList)) {
                throw new RuntimeException("$listName must be an array.");
            }

            $filtered = array_values(array_filter(
                $entityTypeList,
                static fn (mixed $entityType): bool => $entityType !== self::ENTITY_TYPE,
            ));

            if ($filtered !== $entityTypeList) {
                $configChanges[$listName] = $filtered;
            }
        }

        if ($configChanges === []) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        foreach ($configChanges as $name => $value) {
            $configWriter->set($name, $value);
        }

        $configWriter->save();
    }

    private function removeScheduledJob(Container $container): void
    {
        $entityManager = $container->getByClass(EntityManager::class);
        $scheduledJobs = $entityManager
            ->getRDBRepositoryByClass(ScheduledJob::class)
            ->where(['job' => self::SCHEDULED_JOB])
            ->find();

        foreach ($scheduledJobs as $scheduledJob) {
            $entityManager->removeEntity($scheduledJob);
        }
    }
}
