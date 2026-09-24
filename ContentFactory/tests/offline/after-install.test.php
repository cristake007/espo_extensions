<?php

declare(strict_types=1);

namespace Espo\Core {
    final class Container
    {
        /** @param array<class-string, object> $services */
        public function __construct(private array $services)
        {}

        public function getByClass(string $className): object
        {
            return $this->services[$className];
        }
    }

    final class InjectableFactory
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
    final class Config
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
    final class ConfigWriter
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
    final class EntityManager
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

    require dirname(__DIR__, 2) . '/scripts/AfterInstall.php';

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

    /** @param array<int, mixed> $tabList */
    function container(
        array $tabList,
        object $integrations,
        IntegrationRecord $record,
        ConfigWriter $writer,
        ?EntityManager &$entityManager = null,
    ): Container {
        $entityManager = new EntityManager($record);

        return new Container([
            Config::class => new Config([
                'tabList' => $tabList,
                'integrations' => $integrations,
            ]),
            InjectableFactory::class => new InjectableFactory($writer),
            EntityManager::class => $entityManager,
        ]);
    }

    $writer = new ConfigWriter();
    $record = new IntegrationRecord(true);
    $existingIntegrations = (object) ['ExistingConnector' => true];
    (new AfterInstall())->run(container(
        ['Home'],
        $existingIntegrations,
        $record,
        $writer,
        $entityManager,
    ));

    if ($record->values !== [
        'enabled' => false,
        'connectTimeoutSeconds' => 5,
        'responseTimeoutSeconds' => 20,
    ]) {
        throw new RuntimeException('Fresh integration defaults are incorrect.');
    }

    if (
        $entityManager->saveCount !== 2 ||
        ($writer->changes['integrations']->ExistingConnector ?? null) !== true ||
        ($writer->changes['integrations']->ContentFactoryN8n ?? null) !== false ||
        ($writer->changes['integrations']->ContentFactoryWordPress ?? null) !== false
    ) {
        throw new RuntimeException('Fresh integration registration is incorrect.');
    }

    $writer = new ConfigWriter();
    $record = new IntegrationRecord(false);
    $tabList = [
        'Home',
        [
            'type' => 'group',
            'id' => 'content-factory',
            'text' => '$ContentFactory',
            'iconClass' => 'fas fa-magic',
            'itemList' => [
                'ContentFactoryGenerator',
                'ContentFactoryAnalytics',
                'ContentFactoryContent',
            ],
        ],
    ];
    (new AfterInstall())->run(container(
        $tabList,
        (object) ['ContentFactoryN8n' => true],
        $record,
        $writer,
        $upgradeEntityManager,
    ));

    if (
        $record->values !== [] ||
        $upgradeEntityManager->saveCount !== 0 ||
        $writer->saveCount !== 0
    ) {
        throw new RuntimeException('Upgrade changed existing integration settings.');
    }

    echo "After-install integration tests passed.\n";
}
