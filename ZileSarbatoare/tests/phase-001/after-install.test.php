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
}

namespace Espo\ORM {
    class EntityManager
    {
        public int $saveCount = 0;

        public function __construct(private object $entity)
        {}

        public function getRDBRepositoryByClass(string $className): object
        {
            return new class ($this->entity) {
                public function __construct(private object $entity)
                {}

                public function getById(string $id): object
                {
                    return $this->entity;
                }
            };
        }

        public function saveEntity(object $entity): void
        {
            $this->saveCount++;
        }
    }
}

namespace {
    use Espo\Core\Container;
    use Espo\Core\InjectableFactory;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Config\ConfigWriter;
    use Espo\ORM\EntityManager;

    require_once __DIR__ . '/../../scripts/AfterInstall.php';

    final class IntegrationRecord
    {
        /** @var array<string, mixed> */
        public array $values = [];

        public function __construct(private bool $new)
        {}

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

    function makeContainer(
        mixed $calendarEntityList,
        ConfigWriter $writer,
        IntegrationRecord $integration,
        ?object $integrations = null,
    ): Container
    {
        return new Container([
            Config::class => new Config([
                'calendarEntityList' => $calendarEntityList,
                'timeZone' => 'Europe/Bucharest',
                'integrations' => $integrations,
            ]),
            InjectableFactory::class => new InjectableFactory($writer),
            EntityManager::class => new EntityManager($integration),
        ]);
    }

    $writer = new ConfigWriter();
    $newIntegration = new IntegrationRecord(true);
    (new AfterInstall())->run(makeContainer(['Meeting', 'Call'], $writer, $newIntegration));

    if ($writer->changes['calendarEntityList'] !== ['Meeting', 'Call', 'ZileLibere']) {
        throw new RuntimeException('Calendar registration must preserve existing entries and append ZileLibere.');
    }

    if ($writer->saveCount !== 1) {
        throw new RuntimeException('Fresh-install configuration must be saved once.');
    }

    if (($writer->changes['integrations']->NagerDate ?? null) !== true) {
        throw new RuntimeException('NagerDate must be enabled only when its record is first created.');
    }

    if ($newIntegration->values['countryCode'] !== 'RO' || $newIntegration->values['frequency'] !== 'Weekly') {
        throw new RuntimeException('Fresh integration defaults were not initialized.');
    }

    $writer = new ConfigWriter();
    $savedIntegration = new IntegrationRecord(false);
    $savedConfig = (object) ['NagerDate' => false];
    (new AfterInstall())->run(makeContainer(
        ['Meeting', 'ZileLibere', 'Call'],
        $writer,
        $savedIntegration,
        $savedConfig,
    ));

    if ($writer->saveCount !== 0 || $writer->changes !== []) {
        throw new RuntimeException('Existing calendar and integration settings must not be rewritten.');
    }

    if ($savedIntegration->values !== [] || $savedConfig->NagerDate !== false) {
        throw new RuntimeException('An upgrade must preserve saved integration settings.');
    }

    $writer = new ConfigWriter();

    try {
        (new AfterInstall())->run(makeContainer('Meeting', $writer, new IntegrationRecord(false)));
        throw new RuntimeException('Invalid calendar configuration must be rejected.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'calendarEntityList must be an array.') {
            throw $e;
        }
    }

    echo "PHASE-001 installation tests passed.\n";
}
