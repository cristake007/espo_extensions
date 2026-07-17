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
        $this->unregisterCalendarEntity($container);
        $this->removeScheduledJob($container);
    }

    private function unregisterCalendarEntity(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $calendarEntityList = $config->get('calendarEntityList') ?? [];

        if (!is_array($calendarEntityList)) {
            throw new RuntimeException('calendarEntityList must be an array.');
        }

        $filtered = array_values(array_filter(
            $calendarEntityList,
            static fn (mixed $entityType): bool => $entityType !== self::ENTITY_TYPE,
        ));

        if ($filtered === $calendarEntityList) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);
        $configWriter->set('calendarEntityList', $filtered);
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
