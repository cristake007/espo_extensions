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
        public function __construct(private object $service) {}

        public function create(string $className): object
        {
            return $this->service;
        }
    }
}

namespace Espo\Core\Utils {
    final class Config
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data) {}

        public function get(string $name): mixed
        {
            return $this->data[$name] ?? null;
        }

        public function has(string $name): bool
        {
            return array_key_exists($name, $this->data);
        }

        public function set(string $name, mixed $value): void
        {
            $this->data[$name] = $value;
        }
    }
}

namespace Espo\Core\Utils\Config {
    use Espo\Core\Utils\Config;

    final class ConfigWriter
    {
        public int $saveCount = 0;

        public function __construct(private Config $config) {}

        public function set(string $name, mixed $value): self
        {
            $this->config->set($name, $value);

            return $this;
        }

        /** @param array<string, mixed> $values */
        public function setMultiple(array $values): self
        {
            foreach ($values as $name => $value) {
                $this->config->set($name, $value);
            }

            return $this;
        }

        public function save(): void
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

    require dirname(__DIR__) . '/scripts/AfterInstall.php';
    require dirname(__DIR__) . '/scripts/BeforeUninstall.php';

    /**
     * @param array<int, mixed> $tabList
     * @return array{Container, Config, ConfigWriter}
     */
    function createContext(array $tabList): array
    {
        $config = new Config(['tabList' => $tabList]);
        $writer = new ConfigWriter($config);
        $container = new Container([
            Config::class => $config,
            InjectableFactory::class => new InjectableFactory($writer),
        ]);

        return [$container, $config, $writer];
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                $message . "\nExpected: " . var_export($expected, true) .
                "\nActual: " . var_export($actual, true),
            );
        }
    }

    $group = [
        'type' => 'group',
        'id' => 'attendance-management',
        'text' => '$AttendanceManagement',
        'iconClass' => 'fas fa-clipboard-check',
        'itemList' => ['Attendance', 'AttendanceOverview'],
    ];

    [$container, $config, $writer] = createContext([
        'Home',
        'Attendance',
        'Account',
        'AttendanceOverview',
    ]);
    $install = new AfterInstall();
    $uninstall = new BeforeUninstall();

    $install->run($container);
    assertSameValue(
        ['Home', $group, 'Account'],
        $config->get('tabList'),
        'Install must migrate loose attendance links into one navigation group.',
    );
    assertSameValue([], $config->get('attendanceManagementManagersIds'), 'Manager IDs default is missing.');
    assertSameValue([], (array) $config->get('attendanceManagementManagersNames'), 'Manager names default is missing.');
    assertSameValue(1, $config->get('attendanceManagementEditablePastMonths'), 'Edit-lock default is missing.');
    assertSameValue(1, $writer->saveCount, 'Install must save all changes once.');

    $install->run($container, ['isUpgrade' => true]);
    assertSameValue(1, $writer->saveCount, 'Repeated install must be idempotent.');

    $uninstall->run($container);
    assertSameValue(
        ['Home', 'Account'],
        $config->get('tabList'),
        'Uninstall must remove only the attendance navigation group.',
    );

    $unrelatedGroup = (object) [
        'type' => 'group',
        'id' => 'another-extension',
        'text' => '$AnotherExtension',
        'itemList' => ['Contact'],
    ];
    $staleGroup = (object) [
        'type' => 'group',
        'id' => 'attendance-management',
        'text' => 'Old attendance label',
        'itemList' => ['Attendance'],
    ];
    [$duplicateContainer, $duplicateConfig] = createContext([
        $unrelatedGroup,
        $staleGroup,
        'AttendanceOverview',
        $group,
        'Account',
    ]);

    $install->run($duplicateContainer);
    assertSameValue(
        [$unrelatedGroup, $group, 'Account'],
        $duplicateConfig->get('tabList'),
        'Install must canonicalize stale and duplicate owned navigation entries.',
    );

    echo "Attendance lifecycle tests passed.\n";
}
