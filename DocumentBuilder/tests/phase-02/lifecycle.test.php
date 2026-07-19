<?php

declare(strict_types=1);

namespace Espo\Core\Utils {
    final class Config
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data)
        {
        }

        public function get(string $name, mixed $default = null): mixed
        {
            return $this->data[$name] ?? $default;
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
        /** @var array<string, mixed> */
        private array $pending = [];
        public int $saveCount = 0;

        public function __construct(private Config $config)
        {
        }

        public function set(string $name, mixed $value): void
        {
            $this->pending[$name] = $value;
        }

        public function save(): void
        {
            foreach ($this->pending as $name => $value) {
                $this->config->set($name, $value);
            }

            $this->pending = [];
            $this->saveCount++;
        }
    }
}

namespace Espo\Core {
    use Espo\Core\Utils\Config\ConfigWriter;
    use RuntimeException;

    final class InjectableFactory
    {
        public function __construct(private ConfigWriter $writer)
        {
        }

        public function create(string $className): object
        {
            if ($className !== ConfigWriter::class) {
                throw new RuntimeException("Unexpected injectable class: $className");
            }

            return $this->writer;
        }
    }

    final class Container
    {
        /** @param array<class-string, object> $services */
        public function __construct(private array $services)
        {
        }

        public function getByClass(string $className): object
        {
            return $this->services[$className] ??
                throw new RuntimeException("Missing service: $className");
        }
    }
}

namespace {
    use Espo\Core\Container;
    use Espo\Core\InjectableFactory;
    use Espo\Core\Utils\Config;
    use Espo\Core\Utils\Config\ConfigWriter;

    require dirname(__DIR__, 2) . '/scripts/AfterInstall.php';
    require dirname(__DIR__, 2) . '/scripts/BeforeUninstall.php';

    /** @param array<int, mixed> $tabList */
    function createLifecycleContext(array $tabList): array
    {
        $config = new Config(['tabList' => $tabList]);
        $writer = new ConfigWriter($config);
        $factory = new InjectableFactory($writer);
        $container = new Container([
            Config::class => $config,
            InjectableFactory::class => $factory,
        ]);

        return [$container, $config, $writer];
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . sprintf(
                "\nExpected: %s\nActual: %s",
                var_export($expected, true),
                var_export($actual, true),
            ));
        }
    }

    function assertThrowsRuntimeException(callable $callback, string $message): void
    {
        try {
            $callback();
        } catch (RuntimeException) {
            return;
        }

        throw new RuntimeException($message);
    }

    $canonicalGroup = [
        'type' => 'group',
        'id' => 'document-builder',
        'text' => '$DocumentBuilder',
        'iconClass' => 'fas fa-file-alt',
        'itemList' => ['DocumentBuilderTemplate', 'DocumentBuilderDocument'],
    ];
    $unrelatedGroup = [
        'type' => 'group',
        'id' => 'user-document-builder',
        'text' => '$DocumentBuilder',
        'itemList' => ['Account'],
    ];
    $unrelatedDivider = (object) [
        'type' => 'divider',
        'id' => 'activities',
        'text' => '$Activities',
    ];
    $unrelatedEntries = ['Home', $unrelatedDivider, $unrelatedGroup, 'Account'];

    [$container, $config, $writer] = createLifecycleContext($unrelatedEntries);
    $install = new AfterInstall();
    $uninstall = new BeforeUninstall();

    $install->run($container);
    assertSameValue(
        [...$unrelatedEntries, $canonicalGroup],
        $config->get('tabList'),
        'Install must append one canonical group and preserve unrelated entries.',
    );
    assertSameValue(1, $writer->saveCount, 'Initial install must save exactly once.');

    $install->run($container, ['isUpgrade' => true]);
    assertSameValue(1, $writer->saveCount, 'Repeated install must not write unchanged config.');

    $uninstall->run($container);
    assertSameValue(
        $unrelatedEntries,
        $config->get('tabList'),
        'Uninstall must remove only the owned group.',
    );
    assertSameValue(2, $writer->saveCount, 'Initial uninstall must save exactly once.');

    $uninstall->run($container);
    assertSameValue(2, $writer->saveCount, 'Repeated uninstall must not write unchanged config.');

    $staleGroup = (object) [
        'type' => 'group',
        'id' => 'document-builder',
        'text' => 'Old label',
        'itemList' => ['OldScope'],
    ];
    [$duplicateContainer, $duplicateConfig, $duplicateWriter] = createLifecycleContext([
        'Home',
        $staleGroup,
        'Account',
        $canonicalGroup,
    ]);

    $install->run($duplicateContainer);
    assertSameValue(
        ['Home', $canonicalGroup, 'Account'],
        $duplicateConfig->get('tabList'),
        'Install must replace the first stale group and remove owned duplicates.',
    );
    assertSameValue(1, $duplicateWriter->saveCount, 'Duplicate normalization must save once.');

    $uninstall->run($duplicateContainer, ['isUpgrade' => true]);
    $install->run($duplicateContainer, ['isUpgrade' => true]);
    assertSameValue(
        ['Home', 'Account', $canonicalGroup],
        $duplicateConfig->get('tabList'),
        'Upgrade uninstall/install must preserve unrelated entries and restore one group.',
    );
    assertSameValue(3, $duplicateWriter->saveCount, 'Upgrade lifecycle must perform one removal and one registration save.');

    $invalidConfig = new Config(['tabList' => 'invalid']);
    $invalidWriter = new ConfigWriter($invalidConfig);
    $invalidContainer = new Container([
        Config::class => $invalidConfig,
        InjectableFactory::class => new InjectableFactory($invalidWriter),
    ]);

    assertThrowsRuntimeException(
        fn () => $install->run($invalidContainer),
        'Install must reject a non-array tabList.',
    );
    assertThrowsRuntimeException(
        fn () => $uninstall->run($invalidContainer),
        'Uninstall must reject a non-array tabList.',
    );
    assertSameValue(0, $invalidWriter->saveCount, 'Invalid config must not be written.');

    $uninstallSource = file_get_contents(dirname(__DIR__, 2) . '/scripts/BeforeUninstall.php');

    if ($uninstallSource === false) {
        throw new RuntimeException('Could not read BeforeUninstall.php.');
    }

    foreach (['EntityManager', 'Attachment', 'removeEntity', 'unlink(', 'DROP TABLE', 'DELETE FROM'] as $forbiddenText) {
        if (str_contains($uninstallSource, $forbiddenText)) {
            throw new RuntimeException("Uninstall script contains forbidden destructive integration: $forbiddenText");
        }
    }

    echo "Phase 02 lifecycle tests passed.\n";
}
