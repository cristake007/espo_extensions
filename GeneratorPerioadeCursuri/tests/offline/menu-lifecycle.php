<?php

declare(strict_types=1);

namespace Espo\Core\Utils {
    class Config
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data)
        {
        }

        public function get(string $name): mixed
        {
            return $this->data[$name] ?? null;
        }

        public function set(string $name, mixed $value): void
        {
            $this->data[$name] = $value;
        }
    }
}

namespace Espo\Core\Utils\Config {
    use Espo\Core\Utils\Config;

    class ConfigWriter
    {
        public int $saveCount = 0;
        /** @var array<string, mixed> */
        private array $pending = [];

        public function __construct(
            private Config $config,
            private bool $failOnSave = false,
        ) {
        }

        public function set(string $name, mixed $value): void
        {
            $this->pending[$name] = $value;
        }

        public function save(): void
        {
            if ($this->failOnSave) {
                throw new \RuntimeException('Unable to save config');
            }

            foreach ($this->pending as $name => $value) {
                $this->config->set($name, $value);
            }

            $this->pending = [];
            $this->saveCount++;
        }
    }
}

namespace Espo\Core {
    class InjectableFactory
    {
        public function __construct(private object $instance)
        {
        }

        public function create(string $className): object
        {
            return $this->instance;
        }
    }

    class Container
    {
        /** @param array<string, object> $services */
        public function __construct(private array $services)
        {
        }

        public function getByClass(string $className): object
        {
            return $this->services[$className];
        }
    }
}

namespace {
    use Espo\Core\Container;
    use Espo\Core\InjectableFactory;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Config\ConfigWriter;

    $extensionRoot = dirname(__DIR__, 2);

    require $extensionRoot . '/scripts/AfterInstall.php';
    require $extensionRoot . '/scripts/BeforeUninstall.php';

    $checks = 0;

    $assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks): void {
        $checks++;

        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                "%s\nExpected: %s\nActual: %s",
                $message,
                var_export($expected, true),
                var_export($actual, true),
            ));
        }
    };

    $assertTrue = static function (bool $condition, string $message) use (&$checks): void {
        $checks++;

        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $managedItems = [
        'GeneratorPerioadeCursuri',
        'GeneratorPerioadeCursuriWordMatcher',
        'GeneratorPerioadeCursuriXmlConverter',
        'GeneratorPerioadeCursuriWordPressUpdater',
    ];
    $canonicalGroup = [
        'text' => 'Generator perioade cursuri',
        'iconClass' => 'fas fa-calendar-alt',
        'color' => '#164194',
        'itemList' => $managedItems,
    ];
    $preservedEntries = [
        'Home',
        [
            'text' => 'Customized workflows',
            'itemList' => ['Account'],
        ],
        [
            'text' => 'Unrelated empty group',
            'itemList' => [],
        ],
        'Case',
    ];
    $initialTabList = [
        'Home',
        $canonicalGroup,
        $canonicalGroup,
        ['text' => 'Generator perioade cursuri'],
        [
            'text' => 'Planificări',
            'itemList' => ['Planificari', 'PlanificariWordMatcher'],
        ],
        'GeneratorPerioadeCursuri',
        [
            'text' => 'Customized workflows',
            'itemList' => ['GeneratorPerioadeCursuriWordMatcher', 'Account'],
        ],
        [
            'text' => 'Renamed extension group',
            'itemList' => $managedItems,
        ],
        [
            'text' => 'Unrelated empty group',
            'itemList' => [],
        ],
        'Case',
    ];

    $config = new Config(['tabList' => $initialTabList]);
    $writer = new ConfigWriter($config);
    $factory = new InjectableFactory($writer);
    $container = new Container([
        Config::class => $config,
        InjectableFactory::class => $factory,
    ]);
    $addMenuGroup = new ReflectionMethod(AfterInstall::class, 'addMenuGroup');

    $addMenuGroup->invoke(new AfterInstall(), $container);
    $installedTabList = $config->get('tabList');
    $assertSame([...$preservedEntries, $canonicalGroup], $installedTabList, 'Install must consolidate managed navigation entries into one canonical group.');
    $assertSame(1, $writer->saveCount, 'Install must save one changed tab list.');
    $assertSame(1, count(array_filter(
        $installedTabList,
        static fn (mixed $item): bool => is_array($item) && ($item['text'] ?? null) === 'Generator perioade cursuri'
    )), 'Install must leave exactly one canonical menu group.');

    $addMenuGroup->invoke(new AfterInstall(), $container);
    $assertSame($installedTabList, $config->get('tabList'), 'Repeated install must be idempotent.');
    $assertSame(1, $writer->saveCount, 'Repeated install must not perform a redundant config save.');

    (new BeforeUninstall())->run($container);
    $assertSame($preservedEntries, $config->get('tabList'), 'Uninstall must remove all managed navigation entries and preserve unrelated entries.');
    $assertSame(2, $writer->saveCount, 'Uninstall must save the cleaned tab list.');

    (new BeforeUninstall())->run($container);
    $assertSame($preservedEntries, $config->get('tabList'), 'Repeated uninstall must be idempotent.');
    $assertSame(2, $writer->saveCount, 'Repeated uninstall must not perform a redundant config save.');

    $failureConfig = new Config(['tabList' => [$canonicalGroup]]);
    $failureWriter = new ConfigWriter($failureConfig, true);
    $failureContainer = new Container([
        Config::class => $failureConfig,
        InjectableFactory::class => new InjectableFactory($failureWriter),
    ]);
    $failurePropagated = false;

    try {
        (new BeforeUninstall())->run($failureContainer);
    } catch (RuntimeException) {
        $failurePropagated = true;
    }

    $assertTrue($failurePropagated, 'Uninstall must propagate config-writer failures.');
    $assertSame([$canonicalGroup], $failureConfig->get('tabList'), 'A failed uninstall must not report a partially cleaned config.');

    fwrite(STDOUT, "Menu install/uninstall lifecycle: {$checks} checks passed.\n");
}
