<?php

declare(strict_types=1);

namespace Espo\Core {
    class Container
    {
        /** @param array<class-string, object> $services */
        public function __construct(private array $services)
        {}

        public function getByClass(string $className): object
        {
            return $this->services[$className];
        }
    }

    class InjectableFactory
    {
        public function __construct(private object $instance)
        {}

        public function create(string $className): object
        {
            return $this->instance;
        }
    }
}

namespace Espo\Core\Utils {
    class Config
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values)
        {}

        public function get(string $name): mixed
        {
            return $this->values[$name] ?? null;
        }
    }
}

namespace Espo\Core\Utils\Config {
    class ConfigWriter
    {
        /** @var array<string, mixed> */
        public array $changes = [];
        public int $saveCount = 0;

        public function set(string $name, mixed $value): void
        {
            $this->changes[$name] = $value;
        }

        public function save(): void
        {
            $this->saveCount++;
        }
    }
}

namespace Espo\Entities {
    class Integration {}
    class ScheduledJob {}
}

namespace Espo\ORM {
    final class FakeRepository
    {
        /** @param list<object> $scheduledJobs */
        public function __construct(
            private object $integration,
            private array $scheduledJobs,
            private ?array &$lastWhere,
        ) {}

        public function getById(string $id): object
        {
            return $this->integration;
        }

        public function where(array $where): self
        {
            $this->lastWhere = $where;

            return $this;
        }

        /** @return list<object> */
        public function find(): array
        {
            return array_values(array_filter(
                $this->scheduledJobs,
                fn (object $job): bool => $job->job === ($this->lastWhere['job'] ?? null),
            ));
        }
    }

    class EntityManager
    {
        public int $saveCount = 0;
        /** @var list<object> */
        public array $removed = [];
        /** @var list<class-string> */
        public array $repositoryClasses = [];
        /** @var array<string, mixed>|null */
        public ?array $lastWhere = null;

        /** @param list<object> $scheduledJobs */
        public function __construct(
            private object $integration,
            private array $scheduledJobs = [],
        ) {}

        public function getRDBRepositoryByClass(string $className): object
        {
            $this->repositoryClasses[] = $className;

            return new FakeRepository($this->integration, $this->scheduledJobs, $this->lastWhere);
        }

        public function saveEntity(object $entity): void
        {
            $this->saveCount++;
        }

        public function removeEntity(object $entity): void
        {
            $this->removed[] = $entity;
        }
    }
}

namespace {
    use Espo\Core\Container;
    use Espo\Core\InjectableFactory;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Config\ConfigWriter;
    use Espo\Entities\ScheduledJob;
    use Espo\ORM\EntityManager;

    require_once __DIR__ . '/../../scripts/AfterInstall.php';
    require_once __DIR__ . '/../../scripts/BeforeUninstall.php';

    final class IntegrationRecord
    {
        /** @param array<string, mixed> $values */
        public function __construct(
            private bool $new,
            public array $values = [],
        ) {}

        public function isNew(): bool
        {
            return $this->new;
        }

        /** @param array<string, mixed> $values */
        public function set(array $values): void
        {
            $this->values = $values;
        }
    }

    final class ScheduledJobRecord
    {
        public function __construct(
            public string $id,
            public string $job,
        ) {}
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . ' Expected ' . var_export($expected, true) .
                ', received ' . var_export($actual, true) . '.');
        }
    }

    function container(
        array $configValues,
        ConfigWriter $writer,
        EntityManager $entityManager,
    ): Container {
        return new Container([
            Config::class => new Config($configValues),
            InjectableFactory::class => new InjectableFactory($writer),
            EntityManager::class => $entityManager,
        ]);
    }

    $freshWriter = new ConfigWriter();
    $freshIntegration = new IntegrationRecord(true);
    $freshEntityManager = new EntityManager($freshIntegration);
    (new AfterInstall())->run(container([
        'calendarEntityList' => ['Meeting', 'Call'],
        'timeZone' => 'Europe/Bucharest',
        'integrations' => (object) ['ExistingConnector' => true],
    ], $freshWriter, $freshEntityManager));

    assertSameValue(
        ['Meeting', 'Call', 'ZileLibere'],
        $freshWriter->changes['calendarEntityList'],
        'Fresh installation did not preserve unrelated Calendar entries.',
    );
    assertSameValue(
        true,
        $freshWriter->changes['integrations']->ExistingConnector,
        'Fresh installation overwrote an unrelated integration flag.',
    );
    assertSameValue(
        true,
        $freshWriter->changes['integrations']->NagerDate,
        'Fresh installation did not register Nager.Date.',
    );
    assertSameValue(1, $freshWriter->saveCount, 'Fresh installation did not save config once.');
    assertSameValue(1, $freshEntityManager->saveCount, 'Fresh integration defaults were not saved once.');

    $savedValues = [
        'enabled' => false,
        'countryCode' => 'DE',
        'years' => ['2030'],
        'frequency' => 'ManualOnly',
    ];
    $upgradeWriter = new ConfigWriter();
    $upgradeIntegration = new IntegrationRecord(false, $savedValues);
    $upgradeEntityManager = new EntityManager($upgradeIntegration);
    (new AfterInstall())->run(container([
        'calendarEntityList' => ['Meeting', 'ZileLibere', 'Call'],
        'timeZone' => 'UTC',
        'integrations' => (object) ['NagerDate' => false, 'ExistingConnector' => true],
    ], $upgradeWriter, $upgradeEntityManager));

    assertSameValue([], $upgradeWriter->changes, 'Upgrade rewrote existing configuration.');
    assertSameValue(0, $upgradeWriter->saveCount, 'Upgrade saved unchanged configuration.');
    assertSameValue(0, $upgradeEntityManager->saveCount, 'Upgrade overwrote saved integration settings.');
    assertSameValue($savedValues, $upgradeIntegration->values, 'Upgrade changed saved integration values.');

    $syncJobOne = new ScheduledJobRecord('sync-1', 'SyncZileSarbatoare');
    $otherJob = new ScheduledJobRecord('other', 'Cleanup');
    $syncJobTwo = new ScheduledJobRecord('sync-2', 'SyncZileSarbatoare');
    $uninstallWriter = new ConfigWriter();
    $uninstallEntityManager = new EntityManager(
        $upgradeIntegration,
        [$syncJobOne, $otherJob, $syncJobTwo],
    );
    $holidayRecordCount = 23;
    (new BeforeUninstall())->run(container([
        'calendarEntityList' => ['Meeting', 'ZileLibere', 'Call', 'ZileLibere'],
    ], $uninstallWriter, $uninstallEntityManager));

    assertSameValue(
        ['Meeting', 'Call'],
        $uninstallWriter->changes['calendarEntityList'],
        'Uninstall did not remove only the extension Calendar entries.',
    );
    assertSameValue(1, $uninstallWriter->saveCount, 'Uninstall did not save Calendar cleanup once.');
    assertSameValue(
        ['job' => 'SyncZileSarbatoare'],
        $uninstallEntityManager->lastWhere,
        'Uninstall used an unsafe scheduled-job scope.',
    );
    assertSameValue(
        [$syncJobOne, $syncJobTwo],
        $uninstallEntityManager->removed,
        'Uninstall did not remove exactly the extension-owned scheduled jobs.',
    );
    assertSameValue(
        [ScheduledJob::class],
        $uninstallEntityManager->repositoryClasses,
        'Uninstall accessed a repository other than ScheduledJob.',
    );
    assertSameValue(23, $holidayRecordCount, 'Uninstall changed retained holiday data.');

    $reinstallWriter = new ConfigWriter();
    $reinstallEntityManager = new EntityManager($upgradeIntegration);
    (new AfterInstall())->run(container([
        'calendarEntityList' => ['Meeting', 'Call'],
        'timeZone' => 'UTC',
        'integrations' => (object) ['NagerDate' => false, 'ExistingConnector' => true],
    ], $reinstallWriter, $reinstallEntityManager));

    assertSameValue(
        ['Meeting', 'Call', 'ZileLibere'],
        $reinstallWriter->changes['calendarEntityList'],
        'Reinstall did not restore Calendar registration.',
    );
    assertSameValue(1, $reinstallWriter->saveCount, 'Reinstall did not save only required config.');
    assertSameValue(0, $reinstallEntityManager->saveCount, 'Reinstall reset retained integration settings.');
    assertSameValue($savedValues, $upgradeIntegration->values, 'Reinstall changed retained integration values.');

    echo "PHASE-006 install, upgrade, uninstall, and reinstall lifecycle tests passed.\n";
}
