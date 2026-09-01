<?php

declare(strict_types=1);

namespace Espo\Core {
    final class Container
    {
        /** @param array<class-string, object> $services */
        public function __construct(private array $services) {}

        public function getByClass(string $className): object
        {
            return $this->services[$className];
        }
    }

    final class InjectableFactory
    {
        public function __construct(private object $instance) {}

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
        public function __construct(private array $values) {}

        public function has(string $name): bool
        {
            return array_key_exists($name, $this->values);
        }

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

        /** @param array<string, mixed> $values */
        public function setMultiple(array $values): void
        {
            $this->changes = [...$this->changes, ...$values];
        }

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
    class ScheduledJob {}
}

namespace Espo\ORM {
    final class FakeRepository
    {
        public function where(array $where): self
        {
            return $this;
        }

        public function find(): array
        {
            return [];
        }
    }

    final class EntityManager
    {
        public function getRDBRepositoryByClass(string $className): FakeRepository
        {
            return new FakeRepository();
        }

        public function removeEntity(object $entity): void
        {}
    }
}

namespace {
    use Espo\Core\Container;
    use Espo\Core\InjectableFactory;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Config\ConfigWriter;
    use Espo\ORM\EntityManager;

    require_once __DIR__ . '/../../scripts/AfterInstall.php';
    require_once __DIR__ . '/../../scripts/BeforeUninstall.php';

    function container(array $values, ConfigWriter $writer): Container
    {
        return new Container([
            Config::class => new Config($values),
            InjectableFactory::class => new InjectableFactory($writer),
            EntityManager::class => new EntityManager(),
        ]);
    }

    $writer = new ConfigWriter();
    (new AfterInstall())->run(container([
        'calendarEntityList' => ['Meeting', 'Call'],
        'tabList' => ['Home', 'Calendar'],
    ], $writer));

    if ($writer->changes['calendarEntityList'] !== ['Meeting', 'Call', 'HolidayRequest']) {
        throw new RuntimeException('Install did not append HolidayRequest while preserving Calendar entries.');
    }

    if ($writer->changes['tabList'] !== ['Home', 'Calendar', 'HolidayRequest']) {
        throw new RuntimeException('Install did not append HolidayRequest to the main navigation.');
    }

    if (!array_key_exists('holidayManagementAnnualEntitlementDays', $writer->changes)) {
        throw new RuntimeException('Install stopped persisting missing Holiday Management defaults.');
    }

    if ($writer->saveCount !== 1) {
        throw new RuntimeException('Install configuration must be saved once.');
    }

    $writer = new ConfigWriter();
    (new BeforeUninstall())->run(container([
        'calendarEntityList' => ['Meeting', 'HolidayRequest', 'Call', 'HolidayRequest'],
        'tabList' => ['Home', 'HolidayRequest', 'Calendar', 'HolidayRequest'],
    ], $writer));

    if ($writer->changes !== [
        'calendarEntityList' => ['Meeting', 'Call'],
        'tabList' => ['Home', 'Calendar'],
    ]) {
        throw new RuntimeException('Uninstall did not remove only HolidayRequest navigation entries.');
    }

    if ($writer->saveCount !== 1) {
        throw new RuntimeException('Uninstall configuration must be saved once.');
    }

    echo "PHASE-003 lifecycle tests passed.\n";
}
